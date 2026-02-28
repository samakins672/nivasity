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
