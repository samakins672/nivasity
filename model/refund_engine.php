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

require_once __DIR__ . '/internal_wallet_service.php';

if (!function_exists('getLegacyReservationCutoff')) {
    function getLegacyReservationCutoff() {
        return '2026-04-18 20:00:00';
    }
}

if (!function_exists('isLegacyReservationEligibleTransaction')) {
    function isLegacyReservationEligibleTransaction($createdAt) {
        $createdAt = trim((string)$createdAt);
        if ($createdAt === '') {
            return false;
        }

        $createdAtTs = strtotime($createdAt);
        $cutoffTs = strtotime(getLegacyReservationCutoff());
        if ($createdAtTs === false || $cutoffTs === false) {
            return false;
        }

        return $createdAtTs < $cutoffTs;
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
    function releaseExpiredReservations($conn, $ttlMinutes = 60) {
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

if (!function_exists('withRefundSchoolLock')) {
    function withRefundSchoolLock($conn, $schoolId, callable $callback, $timeoutSeconds = 10) {
        $schoolId = (int)$schoolId;
        if ($schoolId <= 0) {
            throw new Exception('Invalid school id for refund reservation lock');
        }

        $lockName = 'nivasity_refund_school_' . $schoolId;
        $timeoutSeconds = max(1, (int)$timeoutSeconds);

        $lockNameSafe = mysqli_real_escape_string($conn, $lockName);
        $lockSql = "SELECT GET_LOCK('$lockNameSafe', $timeoutSeconds) AS got_lock";
        $lockRs = mysqli_query($conn, $lockSql);
        if (!$lockRs) {
            throw new Exception('Unable to acquire school refund lock: ' . mysqli_error($conn));
        }

        $lockRow = mysqli_fetch_assoc($lockRs);
        $gotLock = isset($lockRow['got_lock']) ? (int)$lockRow['got_lock'] : 0;
        if ($gotLock !== 1) {
            throw new Exception('Could not obtain school-level refund lock');
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

        $txMetaSql = "SELECT created_at FROM transactions WHERE ref_id = '$refIdSafe' LIMIT 1";
        $txMetaRs = mysqli_query($conn, $txMetaSql);
        if (!$txMetaRs) {
            refundEngineLog('reserveRefundForSchoolShare could not inspect transaction timestamp', [
                'ref_id' => $refId,
                'school_id' => $schoolId,
                'error' => mysqli_error($conn),
            ]);
            return 0;
        }
        $txMetaRow = mysqli_fetch_assoc($txMetaRs);
        if (!$txMetaRow || !isLegacyReservationEligibleTransaction($txMetaRow['created_at'] ?? null)) {
            refundEngineLog('Skipped legacy reservation allocation for post-cutoff or unknown transaction', [
                'ref_id' => $refId,
                'school_id' => $schoolId,
                'cutoff' => getLegacyReservationCutoff(),
                'created_at' => $txMetaRow['created_at'] ?? null,
            ]);
            return 0;
        }

        try {
            return withRefundSchoolLock($conn, $schoolId, function() use ($conn, $refId, $refIdSafe, $schoolId, $payerUserId, $gatewaySafe, $channelSafe, $remainingNeed) {
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

                        // Atomic decrement prevents stale-read/lost-update over-reservation.
                        $updSql = "UPDATE refunds
                                   SET remaining_amount = remaining_amount - $alloc,
                                       status = CASE
                                           WHEN (remaining_amount - $alloc) >= amount THEN 'pending'
                                           ELSE 'partially_applied'
                                       END,
                                       updated_at = NOW()
                                   WHERE id = $refundId AND remaining_amount >= $alloc";
                        if (!mysqli_query($conn, $updSql)) {
                            throw new Exception('Failed to update refund balance: ' . mysqli_error($conn));
                        }
                        if ((int)mysqli_affected_rows($conn) !== 1) {
                            // Row changed concurrently or balance is no longer sufficient.
                            continue;
                        }

                        $splitSeqSql = "SELECT COALESCE(MAX(split_sequence), 0) + 1 AS next_split FROM refund_reservations WHERE refund_id = $refundId FOR UPDATE";
                        $splitSeqRs = mysqli_query($conn, $splitSeqSql);
                        if (!$splitSeqRs) {
                            throw new Exception('Failed to compute split sequence: ' . mysqli_error($conn));
                        }
                        $splitSeqRow = mysqli_fetch_assoc($splitSeqRs);
                        $splitSequence = $splitSeqRow && isset($splitSeqRow['next_split']) ? (int)$splitSeqRow['next_split'] : 1;

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
    if (!function_exists('reallocateOverlyReleasedToRefundCore')) {
        function reallocateOverlyReleasedToRefundCore($conn, $targetRefundId, $maxAmount = null, $reallocatedReason = 'overly_reallocated') {
            $targetRefundId = (int)$targetRefundId;
            $reallocatedReasonSafe = mysqli_real_escape_string($conn, (string)$reallocatedReason);
            $requestedAmount = ($maxAmount === null) ? null : max(0, (int)$maxAmount);

            if ($targetRefundId <= 0) {
                return 0;
            }

            $targetSql = "SELECT id, school_id, amount
                          FROM refunds
                          WHERE id = $targetRefundId
                          LIMIT 1
                          FOR UPDATE";
            $targetRs = mysqli_query($conn, $targetSql);
            if (!$targetRs) {
                throw new Exception('Failed to fetch target refund for overly reallocation: ' . mysqli_error($conn));
            }
            $targetRow = mysqli_fetch_assoc($targetRs);
            if (!$targetRow) {
                return 0;
            }

            $targetSchoolId = (int)$targetRow['school_id'];
            $targetAmount = (int)$targetRow['amount'];
            if ($targetSchoolId <= 0 || $targetAmount <= 0) {
                return 0;
            }

            $targetTotalsSql = "SELECT
                                    COALESCE(SUM(CASE WHEN status = 'consumed' THEN amount ELSE 0 END), 0) AS consumed_total,
                                    COALESCE(SUM(CASE WHEN status = 'reserved' THEN amount ELSE 0 END), 0) AS reserved_total
                                FROM refund_reservations
                                WHERE refund_id = $targetRefundId
                                FOR UPDATE";
            $targetTotalsRs = mysqli_query($conn, $targetTotalsSql);
            if (!$targetTotalsRs) {
                throw new Exception('Failed to compute target refund totals for overly reallocation: ' . mysqli_error($conn));
            }
            $targetTotalsRow = mysqli_fetch_assoc($targetTotalsRs);
            $targetConsumed = $targetTotalsRow && isset($targetTotalsRow['consumed_total']) ? (int)$targetTotalsRow['consumed_total'] : 0;
            $targetReserved = $targetTotalsRow && isset($targetTotalsRow['reserved_total']) ? (int)$targetTotalsRow['reserved_total'] : 0;

            $targetRemaining = max(0, $targetAmount - $targetConsumed - $targetReserved);
            if ($targetRemaining <= 0) {
                return 0;
            }

            $remainingNeed = ($requestedAmount === null) ? $targetRemaining : min($targetRemaining, $requestedAmount);
            if ($remainingNeed <= 0) {
                return 0;
            }

            $overlySql = "SELECT
                              rr.id AS reservation_id,
                              rr.refund_id AS source_refund_id,
                              rr.ref_id,
                              rr.school_id,
                              rr.payer_user_id,
                              rr.gateway,
                              rr.amount,
                              rr.channel,
                              rr.reserved_at,
                              rr.consumed_at
                          FROM refund_reservations rr
                          INNER JOIN refunds sr ON sr.id = rr.refund_id
                          WHERE rr.status = 'released'
                            AND rr.release_reason = 'overly'
                            AND sr.school_id = $targetSchoolId
                          ORDER BY rr.released_at ASC, rr.id ASC
                          FOR UPDATE";
            $overlyRs = mysqli_query($conn, $overlySql);
            if (!$overlyRs) {
                throw new Exception('Failed to fetch overly released reservations for reallocation: ' . mysqli_error($conn));
            }

            $reallocatedTotal = 0;
            $usedRefs = [];

            while ($remainingNeed > 0 && ($overlyRow = mysqli_fetch_assoc($overlyRs))) {
                $sourceReservationId = (int)$overlyRow['reservation_id'];
                $sourceRefundId = (int)$overlyRow['source_refund_id'];
                $sourceRefId = isset($overlyRow['ref_id']) ? (string)$overlyRow['ref_id'] : '';
                $sourceAmount = isset($overlyRow['amount']) ? (int)$overlyRow['amount'] : 0;
                $sourcePayerUserId = isset($overlyRow['payer_user_id']) ? (int)$overlyRow['payer_user_id'] : 0;
                $sourceSchoolId = isset($overlyRow['school_id']) ? (int)$overlyRow['school_id'] : $targetSchoolId;
                $sourceGatewaySafe = mysqli_real_escape_string($conn, strtoupper((string)($overlyRow['gateway'] ?? '')));
                $sourceChannelSafe = mysqli_real_escape_string($conn, strtolower((string)($overlyRow['channel'] ?? 'web')));

                if ($sourceReservationId <= 0 || $sourceRefundId <= 0 || $sourceAmount <= 0 || $sourceRefId === '') {
                    continue;
                }

                $moveAmount = min($sourceAmount, $remainingNeed);
                if ($moveAmount <= 0) {
                    continue;
                }

                if ($moveAmount >= $sourceAmount) {
                    $markSql = "UPDATE refund_reservations
                                SET release_reason = '$reallocatedReasonSafe'
                                WHERE id = $sourceReservationId
                                  AND status = 'released'
                                  AND release_reason = 'overly'";
                    if (!mysqli_query($conn, $markSql)) {
                        throw new Exception('Failed to mark overly row as reallocated: ' . mysqli_error($conn));
                    }
                    if ((int)mysqli_affected_rows($conn) !== 1) {
                        continue;
                    }
                } else {
                    // Keep leftover on original row as 'overly', create an audit split for reallocated part.
                    $leftover = $sourceAmount - $moveAmount;
                    $updLeftoverSql = "UPDATE refund_reservations
                                       SET amount = $leftover
                                       WHERE id = $sourceReservationId
                                         AND status = 'released'
                                         AND release_reason = 'overly'";
                    if (!mysqli_query($conn, $updLeftoverSql)) {
                        throw new Exception('Failed to reduce overly row amount during reallocation: ' . mysqli_error($conn));
                    }
                    if ((int)mysqli_affected_rows($conn) !== 1) {
                        continue;
                    }

                    $sourceSplitSql = "SELECT COALESCE(MAX(split_sequence), 0) + 1 AS next_split
                                       FROM refund_reservations
                                       WHERE refund_id = $sourceRefundId
                                       FOR UPDATE";
                    $sourceSplitRs = mysqli_query($conn, $sourceSplitSql);
                    if (!$sourceSplitRs) {
                        throw new Exception('Failed to compute source split sequence for overly reallocation: ' . mysqli_error($conn));
                    }
                    $sourceSplitRow = mysqli_fetch_assoc($sourceSplitRs);
                    $sourceNextSplit = $sourceSplitRow && isset($sourceSplitRow['next_split']) ? (int)$sourceSplitRow['next_split'] : 1;

                    $sourceRefIdSafe = mysqli_real_escape_string($conn, $sourceRefId);
                    $sourceReservedAtValue = !empty($overlyRow['reserved_at']) ? "'" . mysqli_real_escape_string($conn, (string)$overlyRow['reserved_at']) . "'" : "NOW()";
                    $sourceConsumedAtValue = !empty($overlyRow['consumed_at']) ? "'" . mysqli_real_escape_string($conn, (string)$overlyRow['consumed_at']) . "'" : "NULL";

                    $insSourceAuditSql = "INSERT INTO refund_reservations
                                          (refund_id, ref_id, split_sequence, school_id, payer_user_id, gateway, amount, channel, status, reserved_at, consumed_at, released_at, release_reason)
                                          VALUES
                                          ($sourceRefundId, '$sourceRefIdSafe', $sourceNextSplit, $sourceSchoolId, $sourcePayerUserId, '$sourceGatewaySafe', $moveAmount, '$sourceChannelSafe', 'released', $sourceReservedAtValue, $sourceConsumedAtValue, NOW(), '$reallocatedReasonSafe')";
                    if (!mysqli_query($conn, $insSourceAuditSql)) {
                        throw new Exception('Failed to insert source reallocation audit row: ' . mysqli_error($conn));
                    }
                }

                $targetSplitSql = "SELECT COALESCE(MAX(split_sequence), 0) + 1 AS next_split
                                   FROM refund_reservations
                                   WHERE refund_id = $targetRefundId
                                   FOR UPDATE";
                $targetSplitRs = mysqli_query($conn, $targetSplitSql);
                if (!$targetSplitRs) {
                    throw new Exception('Failed to compute target split sequence for overly reallocation: ' . mysqli_error($conn));
                }
                $targetSplitRow = mysqli_fetch_assoc($targetSplitRs);
                $targetSplitSequence = $targetSplitRow && isset($targetSplitRow['next_split']) ? (int)$targetSplitRow['next_split'] : 1;

                // Requested behavior: preserve source ref_id, create a consumed row immediately.
                $targetRefIdSafe = mysqli_real_escape_string($conn, $sourceRefId);
                $insTargetSql = "INSERT INTO refund_reservations
                                 (refund_id, ref_id, split_sequence, school_id, payer_user_id, gateway, amount, channel, status, reserved_at, consumed_at)
                                 VALUES
                                 ($targetRefundId, '$targetRefIdSafe', $targetSplitSequence, $targetSchoolId, $sourcePayerUserId, '$sourceGatewaySafe', $moveAmount, '$sourceChannelSafe', 'consumed', NOW(), NOW())";
                if (!mysqli_query($conn, $insTargetSql)) {
                    throw new Exception('Failed to create consumed target reservation from overly reallocation: ' . mysqli_error($conn));
                }

                $usedRefs[$sourceRefId] = true;
                $reallocatedTotal += $moveAmount;
                $remainingNeed -= $moveAmount;
            }

            if ($reallocatedTotal > 0) {
                foreach (array_keys($usedRefs) as $usedRefId) {
                    finalizeConsumedRefundsForTx($conn, $usedRefId);
                }

                refundEngineLog('Reallocated overly released reservations to target refund', [
                    'target_refund_id' => $targetRefundId,
                    'reallocated' => $reallocatedTotal,
                    'reason' => $reallocatedReason
                ]);
            }

            return $reallocatedTotal;
        }
    }

    if (!function_exists('reallocateOverlyReleasedToRefund')) {
        function reallocateOverlyReleasedToRefund($conn, $targetRefundId, $maxAmount = null, $reallocatedReason = 'overly_reallocated') {
            mysqli_begin_transaction($conn);
            try {
                $reallocated = reallocateOverlyReleasedToRefundCore($conn, $targetRefundId, $maxAmount, $reallocatedReason);
                mysqli_commit($conn);
                return $reallocated;
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                refundEngineLog('reallocateOverlyReleasedToRefund failed', [
                    'target_refund_id' => (int)$targetRefundId,
                    'error' => $e->getMessage()
                ]);
                return 0;
            }
        }
    }

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

            $sourceTxSql = "SELECT payment_channel, created_at FROM transactions WHERE ref_id = '$sourceRefSafe' LIMIT 1 FOR UPDATE";
            $sourceTxRs = mysqli_query($conn, $sourceTxSql);
            if (!$sourceTxRs) {
                throw new Exception('Failed to inspect source transaction payment channel: ' . mysqli_error($conn));
            }
            $sourceTxRow = mysqli_fetch_assoc($sourceTxRs);
            $paymentChannel = strtolower(trim((string)($sourceTxRow['payment_channel'] ?? 'gateway')));
            $refundMode = 'settlement_offset';
            $reallocatedOverly = 0;

            $directLedgerRefund = nivasityAdjustSchoolPayableForRefund($conn, $sourceRefId, $refundAmount, [
                'refund_id' => $refundId,
                'reason' => $reason,
                'student_id' => (int)$resolvedStudentId,
                'materials' => array_values($cleanMaterialIds),
                'source' => 'direct_ledger_refund',
            ]);

            if (($directLedgerRefund['status'] ?? '') === 'adjusted') {
                if ($paymentChannel === 'wallet') {
                    $walletRefund = nivasityCreditWalletRefund($conn, [
                        'user_id' => (int)$resolvedStudentId,
                        'refund_id' => $refundId,
                        'source_ref_id' => $sourceRefId,
                        'amount' => $refundAmount,
                        'description' => 'Refund for wallet purchase',
                        'metadata' => [
                            'reason' => $reason,
                            'materials' => array_values($cleanMaterialIds),
                        ],
                    ]);

                    if (($walletRefund['status'] ?? '') !== 'credited') {
                        throw new Exception('Failed to credit wallet refund.');
                    }

                    $refundMode = 'wallet_credit';
                }

                $updateRefundSql = "UPDATE refunds
                                    SET remaining_amount = 0,
                                        status = 'applied',
                                        updated_at = NOW()
                                    WHERE id = $refundId";
                if (!mysqli_query($conn, $updateRefundSql)) {
                    throw new Exception('Failed to finalize direct ledger refund: ' . mysqli_error($conn));
                }

                syncSourceTransactionRefundProgress($conn, $sourceRefId);
            } else {
                if (!isLegacyReservationEligibleTransaction($sourceTxRow['created_at'] ?? null)) {
                    throw new Exception(
                        'This transaction is not eligible for legacy reservation fallback. Transactions created on or after '
                        . getLegacyReservationCutoff()
                        . ' must have a school ledger row before refund processing.'
                    );
                }

                $reallocatedOverly = reallocateOverlyReleasedToRefundCore($conn, $refundId, null, 'overly_reallocated');

                if ($paymentChannel === 'wallet') {
                    $walletRefund = nivasityCreditWalletRefund($conn, [
                        'user_id' => (int)$resolvedStudentId,
                        'refund_id' => $refundId,
                        'source_ref_id' => $sourceRefId,
                        'amount' => $refundAmount,
                        'description' => 'Refund for wallet purchase',
                        'metadata' => [
                            'reason' => $reason,
                            'materials' => array_values($cleanMaterialIds),
                        ],
                    ]);

                    if (($walletRefund['status'] ?? '') === 'credited') {
                        $splitSeqSql = "SELECT COALESCE(MAX(split_sequence), 0) + 1 AS next_split FROM refund_reservations WHERE refund_id = $refundId FOR UPDATE";
                        $splitSeqRs = mysqli_query($conn, $splitSeqSql);
                        if (!$splitSeqRs) {
                            throw new Exception('Failed to compute wallet refund split sequence: ' . mysqli_error($conn));
                        }
                        $splitSeqRow = mysqli_fetch_assoc($splitSeqRs);
                        $splitSequence = $splitSeqRow && isset($splitSeqRow['next_split']) ? (int)$splitSeqRow['next_split'] : 1;
                        $gatewaySafe = mysqli_real_escape_string($conn, 'NIVASITY');
                        $channelSafe = mysqli_real_escape_string($conn, 'wallet_refund');

                        $insertReservationSql = "INSERT INTO refund_reservations (refund_id, ref_id, split_sequence, school_id, payer_user_id, gateway, amount, channel, status, reserved_at, consumed_at)
                                                 VALUES ($refundId, '$sourceRefSafe', $splitSequence, $resolvedSchoolId, " . (int)$resolvedStudentId . ", '$gatewaySafe', $refundAmount, '$channelSafe', 'consumed', NOW(), NOW())";
                        if (!mysqli_query($conn, $insertReservationSql)) {
                            throw new Exception('Failed to record wallet refund consumption: ' . mysqli_error($conn));
                        }

                        finalizeConsumedRefundsForTx($conn, $sourceRefId);
                        nivasityAdjustSchoolPayableForRefund($conn, $sourceRefId, $refundAmount, [
                            'refund_id' => $refundId,
                            'reason' => $reason,
                            'source' => 'wallet_refund',
                        ]);
                        $refundMode = 'wallet_credit';
                    }
                }

                syncSourceTransactionRefundProgress($conn, $sourceRefId);
            }

            mysqli_commit($conn);

            refundEngineLog('Created material-level refund', [
                'refund_id' => $refundId,
                'source_ref_id' => $sourceRefId,
                'school_id' => $resolvedSchoolId,
                'student_id' => (int)$resolvedStudentId,
                'materials' => $cleanMaterialIds,
                'amount' => $refundAmount,
                'overly_reallocated' => (int)$reallocatedOverly,
                'refund_mode' => $refundMode
            ]);

            return [
                'status' => true,
                'refund_id' => $refundId,
                'amount' => $refundAmount,
                'school_id' => $resolvedSchoolId,
                'student_id' => (int)$resolvedStudentId,
                'materials' => array_values($cleanMaterialIds),
                'overly_reallocated' => (int)$reallocatedOverly,
                'refund_mode' => $refundMode
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

        $refundSql = "SELECT COALESCE(SUM(amount), 0) AS expected_refund
                      FROM refunds
                      WHERE ref_id = '$sourceRefSafe'
                        AND COALESCE(status, '') <> 'cancelled'
                      FOR UPDATE";
        $refundRs = mysqli_query($conn, $refundSql);
        if (!$refundRs) {
            throw new Exception('Failed to fetch source refunds for progress sync: ' . mysqli_error($conn));
        }

        $refundRow = mysqli_fetch_assoc($refundRs);
        $expectedRefund = $refundRow && isset($refundRow['expected_refund'])
            ? round((float)$refundRow['expected_refund'], 2)
            : 0.0;
        if ($expectedRefund < 0) {
            $expectedRefund = 0.0;
        }

        $transactionSql = "SELECT id, COALESCE(refund, 0) AS refund
                           FROM transactions
                           WHERE ref_id = '$sourceRefSafe'
                           FOR UPDATE";
        $transactionRs = mysqli_query($conn, $transactionSql);
        if (!$transactionRs) {
            throw new Exception('Failed to fetch source transactions for refund sync: ' . mysqli_error($conn));
        }

        while ($transactionRow = mysqli_fetch_assoc($transactionRs)) {
            $transactionId = (int)($transactionRow['id'] ?? 0);
            if ($transactionId <= 0) {
                continue;
            }

            $currentRefund = round((float)($transactionRow['refund'] ?? 0), 2);
            if (abs($expectedRefund - $currentRefund) <= 0.00001) {
                continue;
            }

            $updateSql = "UPDATE transactions SET refund = $expectedRefund WHERE id = $transactionId";
            if (!mysqli_query($conn, $updateSql)) {
                throw new Exception('Failed to sync source transaction refund progress: ' . mysqli_error($conn));
            }
        }

        return $expectedRefund;
    }
}

if (!function_exists('removeRefundedMaterialsIfCompleted')) {
    function removeRefundedMaterialsIfCompleted($conn, $refundRow, $consumedTotal, $reservedCount, $remainingAmountOverride = null) {
        $refundId = (int)$refundRow['id'];
        $refundAmount = (int)$refundRow['amount'];
        $remainingAmount = ($remainingAmountOverride === null) ? (int)$refundRow['remaining_amount'] : (int)$remainingAmountOverride;
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
        $deletedRows = (int)mysqli_affected_rows($conn);

        // Fallback for legacy rows where buyer may not match the refund student_id.
        if ($studentId > 0 && $deletedRows === 0) {
            $fallbackDeleteSql = "DELETE FROM manuals_bought WHERE ref_id = '$sourceRefSafe' AND manual_id IN ($manualIdsCsv)";
            if (!mysqli_query($conn, $fallbackDeleteSql)) {
                throw new Exception('Failed to remove refunded materials with fallback delete: ' . mysqli_error($conn));
            }
            $deletedRows = (int)mysqli_affected_rows($conn);
            if ($deletedRows > 0) {
                refundEngineLog('Fallback delete removed refunded materials without buyer filter', [
                    'refund_id' => $refundId,
                    'source_ref_id' => $sourceRefId,
                    'student_id' => $studentId,
                    'materials' => $materialIds
                ]);
            }
        }

        refundEngineLog('Removed refunded materials from source transaction', [
            'refund_id' => $refundId,
            'source_ref_id' => $sourceRefId,
            'student_id' => $studentId,
            'materials' => $materialIds,
            'deleted_rows' => $deletedRows
        ]);
    }
}

if (!function_exists('finalizeConsumedRefundsForTx')) {
    if (!function_exists('releaseOverConsumedReservationsForRefund')) {
        function releaseOverConsumedReservationsForRefund($conn, $refundId, $refundAmount, $reason = 'overly') {
            $refundId = (int)$refundId;
            $refundAmount = (int)$refundAmount;
            $reasonSafe = mysqli_real_escape_string($conn, (string)$reason);
            if ($refundId <= 0 || $refundAmount <= 0) {
                return 0;
            }

            $sumConsumedSql = "SELECT COALESCE(SUM(amount), 0) AS total_consumed
                               FROM refund_reservations
                               WHERE refund_id = $refundId AND status = 'consumed'
                               FOR UPDATE";
            $sumConsumedRs = mysqli_query($conn, $sumConsumedSql);
            if (!$sumConsumedRs) {
                throw new Exception('Failed to compute over-consumed totals: ' . mysqli_error($conn));
            }
            $sumConsumedRow = mysqli_fetch_assoc($sumConsumedRs);
            $consumedTotal = $sumConsumedRow && isset($sumConsumedRow['total_consumed']) ? (int)$sumConsumedRow['total_consumed'] : 0;

            $excess = $consumedTotal - $refundAmount;
            if ($excess <= 0) {
                return 0;
            }

            $releasedTotal = 0;
            $rowsSql = "SELECT id, ref_id, split_sequence, school_id, payer_user_id, gateway, amount, channel, reserved_at, consumed_at
                        FROM refund_reservations
                        WHERE refund_id = $refundId AND status = 'consumed'
                        ORDER BY consumed_at DESC, id DESC
                        FOR UPDATE";
            $rowsRs = mysqli_query($conn, $rowsSql);
            if (!$rowsRs) {
                throw new Exception('Failed to fetch consumed reservation rows for correction: ' . mysqli_error($conn));
            }

            while ($excess > 0 && ($row = mysqli_fetch_assoc($rowsRs))) {
                $reservationId = (int)$row['id'];
                $rowAmount = (int)$row['amount'];
                if ($reservationId <= 0 || $rowAmount <= 0) {
                    continue;
                }

                if ($rowAmount <= $excess) {
                    $updSql = "UPDATE refund_reservations
                               SET status = 'released',
                                   released_at = NOW(),
                                   release_reason = '$reasonSafe'
                               WHERE id = $reservationId AND status = 'consumed'";
                    if (!mysqli_query($conn, $updSql)) {
                        throw new Exception('Failed to release over-consumed reservation row: ' . mysqli_error($conn));
                    }
                    if ((int)mysqli_affected_rows($conn) === 1) {
                        $releasedTotal += $rowAmount;
                        $excess -= $rowAmount;
                    }
                    continue;
                }

                // Partial correction: keep part consumed, split out excess as released.
                $partialRelease = $excess;
                $remainingConsumed = $rowAmount - $partialRelease;

                $updPartialSql = "UPDATE refund_reservations
                                  SET amount = $remainingConsumed
                                  WHERE id = $reservationId AND status = 'consumed'";
                if (!mysqli_query($conn, $updPartialSql)) {
                    throw new Exception('Failed to partially correct over-consumed reservation row: ' . mysqli_error($conn));
                }
                if ((int)mysqli_affected_rows($conn) !== 1) {
                    throw new Exception('Failed to lock consumed row for partial correction');
                }

                $splitSeqSql = "SELECT COALESCE(MAX(split_sequence), 0) + 1 AS next_split
                                FROM refund_reservations
                                WHERE refund_id = $refundId
                                FOR UPDATE";
                $splitSeqRs = mysqli_query($conn, $splitSeqSql);
                if (!$splitSeqRs) {
                    throw new Exception('Failed to compute split sequence during over-consume correction: ' . mysqli_error($conn));
                }
                $splitSeqRow = mysqli_fetch_assoc($splitSeqRs);
                $nextSplit = $splitSeqRow && isset($splitSeqRow['next_split']) ? (int)$splitSeqRow['next_split'] : 1;

                $refIdSafe = mysqli_real_escape_string($conn, (string)$row['ref_id']);
                $gatewaySafe = mysqli_real_escape_string($conn, (string)$row['gateway']);
                $channelSafe = mysqli_real_escape_string($conn, (string)$row['channel']);
                $schoolId = (int)$row['school_id'];
                $payerUserId = (int)$row['payer_user_id'];
                $reservedAt = !empty($row['reserved_at']) ? "'" . mysqli_real_escape_string($conn, (string)$row['reserved_at']) . "'" : "NOW()";
                $consumedAt = !empty($row['consumed_at']) ? "'" . mysqli_real_escape_string($conn, (string)$row['consumed_at']) . "'" : "NOW()";

                $insSql = "INSERT INTO refund_reservations
                           (refund_id, ref_id, split_sequence, school_id, payer_user_id, gateway, amount, channel, status, reserved_at, consumed_at, released_at, release_reason)
                           VALUES
                           ($refundId, '$refIdSafe', $nextSplit, $schoolId, $payerUserId, '$gatewaySafe', $partialRelease, '$channelSafe', 'released', $reservedAt, $consumedAt, NOW(), '$reasonSafe')";
                if (!mysqli_query($conn, $insSql)) {
                    throw new Exception('Failed to insert released split row during over-consume correction: ' . mysqli_error($conn));
                }

                $releasedTotal += $partialRelease;
                $excess = 0;
            }

            if ($releasedTotal > 0) {
                refundEngineLog('Released over-consumed reservations', [
                    'refund_id' => $refundId,
                    'released' => $releasedTotal,
                    'reason' => $reason
                ]);
            }

            return $releasedTotal;
        }
    }

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
            $refundAmount = isset($refundRow['amount']) ? (int)$refundRow['amount'] : 0;
            if ($refundAmount > 0) {
                // Guardrail: if a refund was over-consumed historically, release newest excess rows.
                releaseOverConsumedReservationsForRefund($conn, $refundId, $refundAmount, 'overly');
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

            $reservedSql = "SELECT COUNT(1) AS active_reserved, COALESCE(SUM(amount), 0) AS reserved_total
                            FROM refund_reservations
                            WHERE refund_id = $refundId AND status = 'reserved'
                            FOR UPDATE";
            $reservedRs = mysqli_query($conn, $reservedSql);
            if (!$reservedRs) {
                throw new Exception('Failed to fetch reserved count for refund: ' . mysqli_error($conn));
            }
            $reservedRow = mysqli_fetch_assoc($reservedRs);
            $reservedCount = $reservedRow && isset($reservedRow['active_reserved']) ? (int)$reservedRow['active_reserved'] : 0;
            $reservedTotal = $reservedRow && isset($reservedRow['reserved_total']) ? (int)$reservedRow['reserved_total'] : 0;

            $effectiveConsumed = min(max(0, $consumedTotal), max(0, $refundAmount));
            $expectedRemaining = max(0, $refundAmount - $consumedTotal - $reservedTotal);

            if ($expectedRemaining <= 0 && $reservedCount === 0 && $effectiveConsumed >= $refundAmount) {
                $targetStatus = 'applied';
            } elseif ($expectedRemaining >= $refundAmount) {
                $targetStatus = 'pending';
            } else {
                $targetStatus = 'partially_applied';
            }

            $updStatusSql = "UPDATE refunds SET remaining_amount = $expectedRemaining, status = '$targetStatus', updated_at = NOW() WHERE id = $refundId";
            if (!mysqli_query($conn, $updStatusSql)) {
                throw new Exception('Failed to update refund status during finalization: ' . mysqli_error($conn));
            }

            removeRefundedMaterialsIfCompleted($conn, $refundRow, $effectiveConsumed, $reservedCount, $expectedRemaining);

            $sourceRefId = isset($refundRow['ref_id']) ? (string)$refundRow['ref_id'] : '';
            if ($sourceRefId !== '') {
                syncSourceTransactionRefundProgress($conn, $sourceRefId);
            }
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

if (!function_exists('consumeReservationsForSettledTx')) {
    function consumeReservationsForSettledTx($conn, $refId) {
        $refIdSafe = mysqli_real_escape_string($conn, (string)$refId);
        if ($refIdSafe === '') {
            return 0;
        }

        mysqli_begin_transaction($conn);
        try {
            $refundApplied = consumeReservationsCore($conn, $refIdSafe);
            mysqli_commit($conn);
            return (int)$refundApplied;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            refundEngineLog('consumeReservationsForSettledTx failed', [
                'ref_id' => $refId,
                'error' => $e->getMessage()
            ]);
            return (int)getConsumedReservationTotalForTx($conn, $refIdSafe);
        }
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
            $netConsumedSql = "SELECT COALESCE(SUM(amount), 0) AS total_consumed
                               FROM refund_reservations
                               WHERE ref_id = '$refIdSafe' AND status = 'consumed'
                               FOR UPDATE";
            $netConsumedRs = mysqli_query($conn, $netConsumedSql);
            if (!$netConsumedRs) {
                throw new Exception('Failed to fetch net consumed totals after finalization: ' . mysqli_error($conn));
            }
            $netConsumedRow = mysqli_fetch_assoc($netConsumedRs);
            $netConsumed = $netConsumedRow && isset($netConsumedRow['total_consumed']) ? (int)$netConsumedRow['total_consumed'] : 0;

            refundEngineLog('Consumed refund reservations', [
                'ref_id' => $refId,
                'gross_amount' => $reservedTotal,
                'net_amount' => $netConsumed
            ]);
            return $netConsumed;
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

        if ($consumedTotal > 0) {
            finalizeConsumedRefundsForTx($conn, $refIdSafe);
            $netConsumedSql = "SELECT COALESCE(SUM(amount), 0) AS total_consumed
                               FROM refund_reservations
                               WHERE ref_id = '$refIdSafe' AND status = 'consumed'
                               FOR UPDATE";
            $netConsumedRs = mysqli_query($conn, $netConsumedSql);
            if (!$netConsumedRs) {
                throw new Exception('Failed to refresh consumed totals after finalization: ' . mysqli_error($conn));
            }
            $netConsumedRow = mysqli_fetch_assoc($netConsumedRs);
            $consumedTotal = $netConsumedRow && isset($netConsumedRow['total_consumed']) ? (int)$netConsumedRow['total_consumed'] : 0;
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
                if ($newRemaining >= $refundAmount) {
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
