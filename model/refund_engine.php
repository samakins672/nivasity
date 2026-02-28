<?php
/**
 * Refund reservation engine
 *
 * Handles school-level refund reservation/consumption/release with
 * transaction safety and tx-level locking.
 */

if (!function_exists('refundEngineLog')) {
    function refundEngineLog($message, $context = []) {
        $payload = '[REFUND_ENGINE] ' . $message;
        if (!empty($context)) {
            $payload .= ' ' . json_encode($context);
        }
        error_log($payload);
    }
}

if (!function_exists('getSchoolSettlementSubaccount')) {
    function getSchoolSettlementSubaccount($conn, $schoolId, $gatewayName) {
        $schoolId = (int)$schoolId;
        $gatewaySafe = mysqli_real_escape_string($conn, strtolower((string)$gatewayName));
        $sql = "SELECT subaccount_code FROM settlement_accounts WHERE school_id = $schoolId AND type = 'school' AND gateway = '$gatewaySafe' ORDER BY id DESC LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if ($rs && mysqli_num_rows($rs) > 0) {
            $row = mysqli_fetch_assoc($rs);
            return isset($row['subaccount_code']) ? $row['subaccount_code'] : null;
        }
        return null;
    }
}

if (!function_exists('releaseExpiredReservations')) {
    function releaseExpiredReservations($conn, $ttlMinutes = 30) {
        $ttlMinutes = max(1, (int)$ttlMinutes);
        $refs = [];

        $sql = "SELECT DISTINCT ref_id FROM refund_reservations WHERE status = 'reserved' AND reserved_at <= DATE_SUB(NOW(), INTERVAL $ttlMinutes MINUTE)";
        $rs = mysqli_query($conn, $sql);
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                if (!empty($row['ref_id'])) {
                    $refs[] = $row['ref_id'];
                }
            }
        }

        $released = 0;
        foreach ($refs as $refId) {
            $released += (int)releaseReservationsForTx($conn, $refId, 'timeout');
        }

        return $released;
    }
}

if (!function_exists('withRefundInitLock')) {
    function withRefundInitLock($conn, $refId, callable $callback, $timeoutSeconds = 10) {
        $lockName = 'nivasity_refund_init_' . md5((string)$refId);
        $timeoutSeconds = max(1, (int)$timeoutSeconds);

        $lockNameSafe = mysqli_real_escape_string($conn, $lockName);
        $lockSql = "SELECT GET_LOCK('$lockNameSafe', $timeoutSeconds) AS got_lock";
        $lockRs = mysqli_query($conn, $lockSql);
        if (!$lockRs) {
            throw new Exception('Unable to acquire refund init lock: ' . mysqli_error($conn));
        }

        $lockRow = mysqli_fetch_assoc($lockRs);
        $gotLock = isset($lockRow['got_lock']) ? (int)$lockRow['got_lock'] : 0;
        if ($gotLock !== 1) {
            throw new Exception('Could not obtain init lock for reservation');
        }

        try {
            return $callback();
        } finally {
            $unlockSql = "SELECT RELEASE_LOCK('$lockNameSafe')";
            mysqli_query($conn, $unlockSql);
        }
    }
}

if (!function_exists('reserveRefundForSchoolShare')) {
    function reserveRefundForSchoolShare($conn, $refId, $schoolId, $payerUserId, $gateway, $schoolShare, $channel) {
        $refIdSafe = mysqli_real_escape_string($conn, (string)$refId);
        $schoolId = (int)$schoolId;
        $payerUserId = (int)$payerUserId;
        $gatewaySafe = mysqli_real_escape_string($conn, strtoupper((string)$gateway));
        $channelSafe = mysqli_real_escape_string($conn, strtolower((string)$channel));
        $remainingNeed = max(0, (int)round((float)$schoolShare));

        if ($remainingNeed <= 0 || $schoolId <= 0 || $refIdSafe === '') {
            return 0;
        }

        try {
            return withRefundInitLock($conn, $refId, function() use ($conn, $refId, $refIdSafe, $schoolId, $payerUserId, $gatewaySafe, $channelSafe, $remainingNeed) {
                mysqli_begin_transaction($conn);
                try {
                    // Support incremental top-up for same ref_id until school_share target is reached.
                    $existingReserved = 0;
                    $existingSql = "SELECT COALESCE(SUM(amount), 0) AS total_reserved FROM refund_reservations WHERE ref_id = '$refIdSafe' AND status IN ('reserved', 'consumed') FOR UPDATE";
                    $existingRs = mysqli_query($conn, $existingSql);
                    if ($existingRs && ($existingRow = mysqli_fetch_assoc($existingRs))) {
                        $existingReserved = (int)$existingRow['total_reserved'];
                    }

                    if ($existingReserved >= $remainingNeed) {
                        mysqli_commit($conn);
                        return $existingReserved;
                    }

                    $reservedTotal = $existingReserved;
                    $remainingToReserve = max(0, $remainingNeed - $existingReserved);

                    $refundSql = "SELECT id, amount, remaining_amount FROM refunds WHERE school_id = $schoolId AND status IN ('pending', 'partially_applied') AND remaining_amount > 0 ORDER BY created_at ASC, id ASC FOR UPDATE";
                    $refundRs = mysqli_query($conn, $refundSql);

                    if (!$refundRs) {
                        throw new Exception('Failed to fetch refund rows: ' . mysqli_error($conn));
                    }

                    while ($remainingToReserve > 0 && ($refundRow = mysqli_fetch_assoc($refundRs))) {
                        $refundId = (int)$refundRow['id'];
                        $refundAmount = (int)$refundRow['amount'];
                        $refundRemaining = (int)$refundRow['remaining_amount'];
                        if ($refundRemaining <= 0) {
                            continue;
                        }

                        $alloc = min($refundRemaining, $remainingToReserve);
                        if ($alloc <= 0) {
                            continue;
                        }

                        $splitSeqSql = "SELECT COALESCE(MAX(split_sequence), 0) + 1 AS next_split FROM refund_reservations WHERE refund_id = $refundId FOR UPDATE";
                        $splitSeqRs = mysqli_query($conn, $splitSeqSql);
                        if (!$splitSeqRs) {
                            throw new Exception('Failed to compute split sequence: ' . mysqli_error($conn));
                        }
                        $splitSeqRow = mysqli_fetch_assoc($splitSeqRs);
                        $splitSequence = $splitSeqRow && isset($splitSeqRow['next_split']) ? (int)$splitSeqRow['next_split'] : 1;

                        $newRemaining = $refundRemaining - $alloc;
                        $newStatus = ($newRemaining <= 0) ? 'applied' : 'partially_applied';

                        $updSql = "UPDATE refunds SET remaining_amount = $newRemaining, status = '$newStatus', updated_at = NOW() WHERE id = $refundId";
                        if (!mysqli_query($conn, $updSql)) {
                            throw new Exception('Failed to update refund balance: ' . mysqli_error($conn));
                        }

                        $insSql = "INSERT INTO refund_reservations (refund_id, ref_id, split_sequence, school_id, payer_user_id, gateway, amount, channel, status, reserved_at) VALUES ($refundId, '$refIdSafe', $splitSequence, $schoolId, $payerUserId, '$gatewaySafe', $alloc, '$channelSafe', 'reserved', NOW())";
                        if (!mysqli_query($conn, $insSql)) {
                            throw new Exception('Failed to insert refund reservation: ' . mysqli_error($conn));
                        }

                        $reservedTotal += $alloc;
                        $remainingToReserve -= $alloc;
                    }

                    mysqli_commit($conn);

                    if ($reservedTotal > 0) {
                        refundEngineLog('Reserved refund amount', [
                            'ref_id' => $refId,
                            'school_id' => $schoolId,
                            'reserved' => $reservedTotal,
                            'channel' => $channelSafe,
                            'gateway' => $gatewaySafe
                        ]);
                    }

                    return $reservedTotal;
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    throw $e;
                }
            });
        } catch (Throwable $e) {
            refundEngineLog('reserveRefundForSchoolShare failed', [
                'ref_id' => $refId,
                'school_id' => $schoolId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}

if (!function_exists('createMaterialRefund')) {
    function createMaterialRefund($conn, $sourceRefId, $schoolId, $studentId, $materialIds, $reason = null) {
        $sourceRefSafe = mysqli_real_escape_string($conn, (string)$sourceRefId);
        $requestedSchoolId = (int)$schoolId;
        $requestedStudentId = (int)$studentId;
        $reasonSafe = mysqli_real_escape_string($conn, (string)$reason);

        $cleanMaterialIds = [];
        if (is_array($materialIds)) {
            foreach ($materialIds as $materialId) {
                $materialId = (int)$materialId;
                if ($materialId > 0) {
                    $cleanMaterialIds[$materialId] = true;
                }
            }
        }
        $cleanMaterialIds = array_keys($cleanMaterialIds);

        if ($sourceRefSafe === '' || empty($cleanMaterialIds)) {
            return ['status' => false, 'message' => 'Invalid source reference or materials'];
        }

        mysqli_begin_transaction($conn);
        try {
            $txSql = "SELECT id, status FROM transactions WHERE ref_id = '$sourceRefSafe' LIMIT 1 FOR UPDATE";
            $txRs = mysqli_query($conn, $txSql);
            if (!$txRs) {
                throw new Exception('Failed to fetch source transaction: ' . mysqli_error($conn));
            }
            $txRow = mysqli_fetch_assoc($txRs);
            if (!$txRow || (string)$txRow['status'] !== 'successful') {
                throw new Exception('Source transaction is not successful');
            }

            $idsCsv = implode(',', array_map('intval', $cleanMaterialIds));
            $manualSql = "SELECT manual_id, price, buyer, school_id
                          FROM manuals_bought
                          WHERE ref_id = '$sourceRefSafe'
                            AND manual_id IN ($idsCsv)";
            if ($requestedStudentId > 0) {
                $manualSql .= " AND buyer = $requestedStudentId";
            }
            $manualSql .= " FOR UPDATE";

            $manualRs = mysqli_query($conn, $manualSql);
            if (!$manualRs) {
                throw new Exception('Failed to fetch source materials: ' . mysqli_error($conn));
            }

            $materialPriceMap = [];
            $resolvedSchoolId = 0;
            $resolvedStudentId = $requestedStudentId;
            while ($manualRow = mysqli_fetch_assoc($manualRs)) {
                $manualId = (int)$manualRow['manual_id'];
                if (!isset($materialPriceMap[$manualId])) {
                    $materialPriceMap[$manualId] = (int)$manualRow['price'];
                }
                if ($resolvedSchoolId <= 0) {
                    $resolvedSchoolId = (int)$manualRow['school_id'];
                }
                if ($resolvedStudentId <= 0) {
                    $resolvedStudentId = (int)$manualRow['buyer'];
                }
            }

            if (count($materialPriceMap) !== count($cleanMaterialIds)) {
                throw new Exception('Some materials are not valid for this source transaction');
            }

            if ($requestedSchoolId > 0 && $resolvedSchoolId > 0 && $requestedSchoolId !== $resolvedSchoolId) {
                throw new Exception('School mismatch for source transaction materials');
            }
            if ($resolvedSchoolId <= 0) {
                $resolvedSchoolId = $requestedSchoolId;
            }
            if ($resolvedSchoolId <= 0) {
                throw new Exception('Unable to resolve school for source transaction');
            }

            $existingRefundsSql = "SELECT id, materials
                                   FROM refunds
                                   WHERE ref_id = '$sourceRefSafe'
                                     AND status IN ('pending', 'partially_applied', 'applied')
                                   FOR UPDATE";
            $existingRefundsRs = mysqli_query($conn, $existingRefundsSql);
            if (!$existingRefundsRs) {
                throw new Exception('Failed to validate existing refunds: ' . mysqli_error($conn));
            }
            while ($existingRefund = mysqli_fetch_assoc($existingRefundsRs)) {
                $existingMaterialIds = parseRefundMaterialIds($existingRefund['materials'] ?? null);
                if (!empty(array_intersect($cleanMaterialIds, $existingMaterialIds))) {
                    throw new Exception('One or more selected materials already have an active/completed refund');
                }
            }

            $refundAmount = 0;
            foreach ($cleanMaterialIds as $materialId) {
                $refundAmount += (int)$materialPriceMap[$materialId];
            }
            if ($refundAmount <= 0) {
                throw new Exception('Computed refund amount is invalid');
            }

            $materialsJson = mysqli_real_escape_string($conn, json_encode(array_values($cleanMaterialIds)));
            $insertRefundSql = "INSERT INTO refunds (school_id, student_id, ref_id, materials, amount, remaining_amount, status, reason, created_at, updated_at)
                                VALUES ($resolvedSchoolId, " . (int)$resolvedStudentId . ", '$sourceRefSafe', '$materialsJson', $refundAmount, $refundAmount, 'pending', '$reasonSafe', NOW(), NOW())";
            if (!mysqli_query($conn, $insertRefundSql)) {
                throw new Exception('Failed to create refund: ' . mysqli_error($conn));
            }

            $refundId = (int)mysqli_insert_id($conn);
            mysqli_commit($conn);

            refundEngineLog('Created material-level refund', [
                'refund_id' => $refundId,
                'source_ref_id' => $sourceRefId,
                'school_id' => $resolvedSchoolId,
                'student_id' => (int)$resolvedStudentId,
                'materials' => $cleanMaterialIds,
                'amount' => $refundAmount
            ]);

            return [
                'status' => true,
                'refund_id' => $refundId,
                'amount' => $refundAmount,
                'school_id' => $resolvedSchoolId,
                'student_id' => (int)$resolvedStudentId,
                'materials' => array_values($cleanMaterialIds)
            ];
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            refundEngineLog('createMaterialRefund failed', [
                'source_ref_id' => $sourceRefId,
                'school_id' => $schoolId,
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('parseRefundMaterialIds')) {
    function parseRefundMaterialIds($materialsJson) {
        if ($materialsJson === null || $materialsJson === '') {
            return [];
        }

        $decoded = json_decode((string)$materialsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $materialIds = [];
        foreach ($decoded as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $materialIds[$id] = true;
            }
        }

        return array_keys($materialIds);
    }
}

if (!function_exists('syncSourceTransactionRefundProgress')) {
    function syncSourceTransactionRefundProgress($conn, $sourceRefId) {
        $sourceRefSafe = mysqli_real_escape_string($conn, (string)$sourceRefId);
        if ($sourceRefSafe === '') {
            return 0;
        }

        $sumSql = "SELECT COALESCE(SUM(rr.amount), 0) AS total_consumed
                   FROM refunds r
                   LEFT JOIN refund_reservations rr ON rr.refund_id = r.id AND rr.status = 'consumed'
                   WHERE r.ref_id = '$sourceRefSafe'
                   FOR UPDATE";
        $sumRs = mysqli_query($conn, $sumSql);
        if (!$sumRs) {
            throw new Exception('Failed to compute source refund progress: ' . mysqli_error($conn));
        }
        $sumRow = mysqli_fetch_assoc($sumRs);
        $totalConsumed = $sumRow && isset($sumRow['total_consumed']) ? (int)$sumRow['total_consumed'] : 0;

        $updTxSql = "UPDATE transactions SET refund = $totalConsumed WHERE ref_id = '$sourceRefSafe'";
        if (!mysqli_query($conn, $updTxSql)) {
            throw new Exception('Failed to update source transaction refund progress: ' . mysqli_error($conn));
        }

        return $totalConsumed;
    }
}

if (!function_exists('removeRefundedMaterialsIfCompleted')) {
    function removeRefundedMaterialsIfCompleted($conn, $refundRow, $consumedTotal, $reservedCount) {
        $refundId = (int)$refundRow['id'];
        $refundAmount = (int)$refundRow['amount'];
        $remainingAmount = (int)$refundRow['remaining_amount'];
        $sourceRefId = isset($refundRow['ref_id']) ? (string)$refundRow['ref_id'] : '';
        $studentId = isset($refundRow['student_id']) ? (int)$refundRow['student_id'] : 0;
        $materialsJson = isset($refundRow['materials']) ? $refundRow['materials'] : null;

        $isComplete = ($remainingAmount <= 0) && ($reservedCount === 0) && ($consumedTotal >= $refundAmount);
        if (!$isComplete || $sourceRefId === '') {
            return;
        }

        $materialIds = parseRefundMaterialIds($materialsJson);
        if (empty($materialIds)) {
            return;
        }

        $sourceRefSafe = mysqli_real_escape_string($conn, $sourceRefId);
        $manualIdsCsv = implode(',', array_map('intval', $materialIds));

        $deleteSql = "DELETE FROM manuals_bought WHERE ref_id = '$sourceRefSafe' AND manual_id IN ($manualIdsCsv)";
        if ($studentId > 0) {
            $deleteSql .= " AND buyer = $studentId";
        }

        if (!mysqli_query($conn, $deleteSql)) {
            throw new Exception('Failed to remove refunded materials: ' . mysqli_error($conn));
        }

        refundEngineLog('Removed refunded materials from source transaction', [
            'refund_id' => $refundId,
            'source_ref_id' => $sourceRefId,
            'student_id' => $studentId,
            'materials' => $materialIds
        ]);
    }
}

if (!function_exists('finalizeConsumedRefundsForTx')) {
    function finalizeConsumedRefundsForTx($conn, $refId) {
        $refIdSafe = mysqli_real_escape_string($conn, (string)$refId);
        if ($refIdSafe === '') {
            return;
        }

        $refundIdsSql = "SELECT DISTINCT refund_id FROM refund_reservations WHERE ref_id = '$refIdSafe' AND status = 'consumed' FOR UPDATE";
        $refundIdsRs = mysqli_query($conn, $refundIdsSql);
        if (!$refundIdsRs) {
            throw new Exception('Failed to fetch consumed refund ids: ' . mysqli_error($conn));
        }

        while ($refundIdRow = mysqli_fetch_assoc($refundIdsRs)) {
            $refundId = (int)$refundIdRow['refund_id'];
            if ($refundId <= 0) {
                continue;
            }

            $refundSql = "SELECT *
                          FROM refunds
                          WHERE id = $refundId
                          LIMIT 1
                          FOR UPDATE";
            $refundRs = mysqli_query($conn, $refundSql);
            if (!$refundRs) {
                throw new Exception('Failed to fetch refund row for finalization: ' . mysqli_error($conn));
            }
            $refundRow = mysqli_fetch_assoc($refundRs);
            if (!$refundRow) {
                continue;
            }

            $consumedSql = "SELECT COALESCE(SUM(amount), 0) AS total_consumed
                            FROM refund_reservations
                            WHERE refund_id = $refundId AND status = 'consumed'
                            FOR UPDATE";
            $consumedRs = mysqli_query($conn, $consumedSql);
            if (!$consumedRs) {
                throw new Exception('Failed to fetch consumed total for refund: ' . mysqli_error($conn));
            }
            $consumedRow = mysqli_fetch_assoc($consumedRs);
            $consumedTotal = $consumedRow && isset($consumedRow['total_consumed']) ? (int)$consumedRow['total_consumed'] : 0;

            $reservedSql = "SELECT COUNT(1) AS active_reserved
                            FROM refund_reservations
                            WHERE refund_id = $refundId AND status = 'reserved'
                            FOR UPDATE";
            $reservedRs = mysqli_query($conn, $reservedSql);
            if (!$reservedRs) {
                throw new Exception('Failed to fetch reserved count for refund: ' . mysqli_error($conn));
            }
            $reservedRow = mysqli_fetch_assoc($reservedRs);
            $reservedCount = $reservedRow && isset($reservedRow['active_reserved']) ? (int)$reservedRow['active_reserved'] : 0;

            $sourceRefId = isset($refundRow['ref_id']) ? (string)$refundRow['ref_id'] : '';
            if ($sourceRefId !== '') {
                syncSourceTransactionRefundProgress($conn, $sourceRefId);
            }

            removeRefundedMaterialsIfCompleted($conn, $refundRow, $consumedTotal, $reservedCount);
        }
    }
}

if (!function_exists('getConsumedReservationTotalForTx')) {
    function getConsumedReservationTotalForTx($conn, $refId) {
        $refIdSafe = mysqli_real_escape_string($conn, (string)$refId);
        if ($refIdSafe === '') {
            return 0;
        }

        $sql = "SELECT COALESCE(SUM(amount), 0) AS total_consumed
                FROM refund_reservations
                WHERE ref_id = '$refIdSafe' AND status = 'consumed'";
        $rs = mysqli_query($conn, $sql);
        if (!$rs) {
            return 0;
        }

        $row = mysqli_fetch_assoc($rs);
        return $row && isset($row['total_consumed']) ? (int)$row['total_consumed'] : 0;
    }
}

if (!function_exists('consumeReservations')) {
    function consumeReservationsCore($conn, $refId) {
        $refIdSafe = mysqli_real_escape_string($conn, (string)$refId);
        if ($refIdSafe === '') {
            return 0;
        }

        $reservedTotal = 0;
        $sumReservedSql = "SELECT COALESCE(SUM(amount), 0) AS total_reserved FROM refund_reservations WHERE ref_id = '$refIdSafe' AND status = 'reserved' FOR UPDATE";
        $sumReservedRs = mysqli_query($conn, $sumReservedSql);
        if (!$sumReservedRs) {
            throw new Exception('Failed to fetch reserved totals: ' . mysqli_error($conn));
        }
        if ($sumReservedRow = mysqli_fetch_assoc($sumReservedRs)) {
            $reservedTotal = (int)$sumReservedRow['total_reserved'];
        }

        if ($reservedTotal > 0) {
            $updSql = "UPDATE refund_reservations SET status = 'consumed', consumed_at = NOW() WHERE ref_id = '$refIdSafe' AND status = 'reserved'";
            if (!mysqli_query($conn, $updSql)) {
                throw new Exception('Failed to consume reservations: ' . mysqli_error($conn));
            }
            finalizeConsumedRefundsForTx($conn, $refIdSafe);
            refundEngineLog('Consumed refund reservations', ['ref_id' => $refId, 'amount' => $reservedTotal]);
            return $reservedTotal;
        }

        $consumedTotal = 0;
        $sumConsumedSql = "SELECT COALESCE(SUM(amount), 0) AS total_consumed FROM refund_reservations WHERE ref_id = '$refIdSafe' AND status = 'consumed' FOR UPDATE";
        $sumConsumedRs = mysqli_query($conn, $sumConsumedSql);
        if (!$sumConsumedRs) {
            throw new Exception('Failed to fetch consumed totals: ' . mysqli_error($conn));
        }
        if ($sumConsumedRow = mysqli_fetch_assoc($sumConsumedRs)) {
            $consumedTotal = (int)$sumConsumedRow['total_consumed'];
        }

        return $consumedTotal;
    }

    function consumeReservations($conn, $refId) {
        $refIdSafe = mysqli_real_escape_string($conn, (string)$refId);
        if ($refIdSafe === '') {
            return 0;
        }

        mysqli_begin_transaction($conn);
        try {
            $consumedTotal = consumeReservationsCore($conn, $refId);
            mysqli_commit($conn);
            return $consumedTotal;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            refundEngineLog('consumeReservations failed', ['ref_id' => $refId, 'error' => $e->getMessage()]);
            return 0;
        }
    }
}

if (!function_exists('releaseReservationsForTx')) {
    function releaseReservationsForTx($conn, $refId, $reason = 'manual_release') {
        $refIdSafe = mysqli_real_escape_string($conn, (string)$refId);
        $reasonSafe = mysqli_real_escape_string($conn, (string)$reason);
        if ($refIdSafe === '') {
            return 0;
        }

        mysqli_begin_transaction($conn);
        try {
            $sql = "SELECT rr.id AS reservation_id, rr.refund_id, rr.amount AS reserved_amount, r.amount AS refund_amount, r.remaining_amount
                    FROM refund_reservations rr
                    INNER JOIN refunds r ON r.id = rr.refund_id
                    WHERE rr.ref_id = '$refIdSafe' AND rr.status = 'reserved'
                    ORDER BY rr.id ASC
                    FOR UPDATE";
            $rs = mysqli_query($conn, $sql);
            if (!$rs) {
                throw new Exception('Failed to fetch reserved rows: ' . mysqli_error($conn));
            }

            $releasedTotal = 0;
            while ($row = mysqli_fetch_assoc($rs)) {
                $reservationId = (int)$row['reservation_id'];
                $refundId = (int)$row['refund_id'];
                $reservedAmount = (int)$row['reserved_amount'];
                $refundAmount = (int)$row['refund_amount'];
                $currentRemaining = (int)$row['remaining_amount'];

                $newRemaining = min($refundAmount, $currentRemaining + $reservedAmount);
                if ($newRemaining <= 0) {
                    $newStatus = 'applied';
                } elseif ($newRemaining >= $refundAmount) {
                    $newStatus = 'pending';
                } else {
                    $newStatus = 'partially_applied';
                }

                $updRefundSql = "UPDATE refunds SET remaining_amount = $newRemaining, status = '$newStatus', updated_at = NOW() WHERE id = $refundId";
                if (!mysqli_query($conn, $updRefundSql)) {
                    throw new Exception('Failed to restore refund balance: ' . mysqli_error($conn));
                }

                $updResSql = "UPDATE refund_reservations SET status = 'released', released_at = NOW(), release_reason = '$reasonSafe' WHERE id = $reservationId";
                if (!mysqli_query($conn, $updResSql)) {
                    throw new Exception('Failed to update reservation status: ' . mysqli_error($conn));
                }

                $releasedTotal += $reservedAmount;
            }

            mysqli_commit($conn);

            if ($releasedTotal > 0) {
                refundEngineLog('Released refund reservations', [
                    'ref_id' => $refId,
                    'released' => $releasedTotal,
                    'reason' => $reason
                ]);
            }

            return $releasedTotal;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            refundEngineLog('releaseReservationsForTx failed', [
                'ref_id' => $refId,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}

if (!function_exists('withTxProcessingLock')) {
    function withTxProcessingLock($conn, $txRef, callable $callback, $timeoutSeconds = 10) {
        $lockName = 'nivasity_tx_' . md5((string)$txRef);
        $timeoutSeconds = max(1, (int)$timeoutSeconds);

        $lockNameSafe = mysqli_real_escape_string($conn, $lockName);
        $lockSql = "SELECT GET_LOCK('$lockNameSafe', $timeoutSeconds) AS got_lock";
        $lockRs = mysqli_query($conn, $lockSql);
        if (!$lockRs) {
            throw new Exception('Unable to acquire transaction lock: ' . mysqli_error($conn));
        }

        $lockRow = mysqli_fetch_assoc($lockRs);
        $gotLock = isset($lockRow['got_lock']) ? (int)$lockRow['got_lock'] : 0;
        if ($gotLock !== 1) {
            throw new Exception('Could not obtain processing lock for transaction');
        }

        try {
            return $callback();
        } finally {
            $unlockSql = "SELECT RELEASE_LOCK('$lockNameSafe')";
            mysqli_query($conn, $unlockSql);
        }
    }
}
?>
