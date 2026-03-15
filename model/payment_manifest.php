<?php

if (!function_exists('paymentManifestSecret')) {
    function paymentManifestSecret() {
        if (defined('PAYMENT_MANIFEST_SECRET') && PAYMENT_MANIFEST_SECRET !== '') {
            return (string) PAYMENT_MANIFEST_SECRET;
        }

        $fallback = (defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : '') . '|' .
            (defined('FLW_SECRET_KEY') ? FLW_SECRET_KEY : '') . '|nivasity-payment-manifest';
        return hash('sha256', $fallback);
    }
}

if (!function_exists('paymentManifestNormalizeGateway')) {
    function paymentManifestNormalizeGateway($gatewayName) {
        $gatewayName = strtolower(trim((string) $gatewayName));
        return $gatewayName !== '' ? $gatewayName : 'flutterwave';
    }
}

if (!function_exists('paymentManifestCanonicalizeItems')) {
    function paymentManifestCanonicalizeItems(array $items) {
        $seen = [];
        $normalized = [];

        foreach ($items as $item) {
            $type = isset($item['type']) ? strtolower(trim((string) $item['type'])) : '';
            $itemId = isset($item['item_id']) ? (int) $item['item_id'] : 0;
            $price = isset($item['price']) ? (int) round((float) $item['price']) : 0;
            $sellerId = isset($item['seller_id']) ? (int) $item['seller_id'] : 0;

            if (($type !== 'manual' && $type !== 'event') || $itemId <= 0) {
                continue;
            }

            $key = $type . ':' . $itemId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $normalized[] = [
                'type' => $type,
                'item_id' => $itemId,
                'price' => $price,
                'seller_id' => $sellerId,
            ];
        }

        usort($normalized, function ($left, $right) {
            $leftKey = $left['type'] . ':' . str_pad((string) $left['item_id'], 12, '0', STR_PAD_LEFT);
            $rightKey = $right['type'] . ':' . str_pad((string) $right['item_id'], 12, '0', STR_PAD_LEFT);
            return strcmp($leftKey, $rightKey);
        });

        return $normalized;
    }
}

if (!function_exists('paymentManifestBuildPayload')) {
    function paymentManifestBuildPayload($refId, $userId, $schoolId, $gatewayName, array $items, $subtotal, $charge, $totalAmount) {
        return [
            'ref_id' => (string) $refId,
            'user_id' => (int) $userId,
            'school_id' => (int) $schoolId,
            'gateway' => paymentManifestNormalizeGateway($gatewayName),
            'subtotal' => (int) round((float) $subtotal),
            'charge' => (int) round((float) $charge),
            'total_amount' => (int) round((float) $totalAmount),
            'items' => paymentManifestCanonicalizeItems($items),
        ];
    }
}

if (!function_exists('paymentManifestHashPayload')) {
    function paymentManifestHashPayload(array $payload) {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('paymentManifestSignHash')) {
    function paymentManifestSignHash($manifestHash) {
        return hash_hmac('sha256', (string) $manifestHash, paymentManifestSecret());
    }
}

if (!function_exists('paymentManifestBuildFromRequestedItems')) {
    function paymentManifestBuildFromRequestedItems($conn, $userId, $schoolId, $gatewayName, array $requestedItems, $refId) {
        $requestedItems = paymentManifestCanonicalizeItems($requestedItems);
        if (empty($requestedItems)) {
            return ['status' => false, 'message' => 'No valid items provided for checkout'];
        }

        $items = [];
        foreach ($requestedItems as $requestedItem) {
            $type = $requestedItem['type'];
            $itemId = (int) $requestedItem['item_id'];

            if ($type === 'manual') {
                $manualQuery = mysqli_query(
                    $conn,
                    "SELECT id, price, user_id FROM manuals
                     WHERE id = $itemId AND school_id = " . (int) $schoolId . " AND status = 'open'
                     LIMIT 1"
                );

                if ($manualQuery && mysqli_num_rows($manualQuery) > 0) {
                    $manual = mysqli_fetch_assoc($manualQuery);
                    $items[] = [
                        'type' => 'manual',
                        'item_id' => (int) $manual['id'],
                        'price' => (int) round((float) $manual['price']),
                        'seller_id' => (int) $manual['user_id'],
                    ];
                }
            } elseif ($type === 'event') {
                $eventQuery = mysqli_query(
                    $conn,
                    "SELECT id, price, user_id FROM events
                     WHERE id = $itemId AND status = 'open'
                     LIMIT 1"
                );

                if ($eventQuery && mysqli_num_rows($eventQuery) > 0) {
                    $event = mysqli_fetch_assoc($eventQuery);
                    $items[] = [
                        'type' => 'event',
                        'item_id' => (int) $event['id'],
                        'price' => (int) round((float) $event['price']),
                        'seller_id' => (int) $event['user_id'],
                    ];
                }
            }
        }

        $items = paymentManifestCanonicalizeItems($items);
        if (empty($items)) {
            return ['status' => false, 'message' => 'No valid purchasable items found for checkout'];
        }

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (int) $item['price'];
        }

        $charges = calculateGatewayCharges($subtotal, $gatewayName);
        $payload = paymentManifestBuildPayload(
            $refId,
            $userId,
            $schoolId,
            $gatewayName,
            $items,
            $subtotal,
            $charges['charge'] ?? 0,
            $charges['total_amount'] ?? ($subtotal + ($charges['charge'] ?? 0))
        );

        $manifestHash = paymentManifestHashPayload($payload);
        $signature = paymentManifestSignHash($manifestHash);

        return [
            'status' => true,
            'manifest' => [
                'ref_id' => $payload['ref_id'],
                'user_id' => $payload['user_id'],
                'school_id' => $payload['school_id'],
                'gateway' => $payload['gateway'],
                'subtotal' => $payload['subtotal'],
                'charge' => $payload['charge'],
                'total_amount' => $payload['total_amount'],
                'items' => $payload['items'],
                'items_json' => json_encode($payload['items'], JSON_UNESCAPED_SLASHES),
                'manifest_hash' => $manifestHash,
                'signature' => $signature,
            ],
        ];
    }
}

if (!function_exists('paymentManifestGatewayPayload')) {
    function paymentManifestGatewayPayload(array $manifest) {
        return [
            'ref_id' => (string) ($manifest['ref_id'] ?? ''),
            'user_id' => (int) ($manifest['user_id'] ?? 0),
            'school_id' => (int) ($manifest['school_id'] ?? 0),
            'gateway' => paymentManifestNormalizeGateway($manifest['gateway'] ?? ''),
            'subtotal' => (int) ($manifest['subtotal'] ?? 0),
            'charge' => (int) ($manifest['charge'] ?? 0),
            'total_amount' => (int) ($manifest['total_amount'] ?? 0),
            'items' => paymentManifestCanonicalizeItems(isset($manifest['items']) && is_array($manifest['items']) ? $manifest['items'] : []),
            'manifest_hash' => (string) ($manifest['manifest_hash'] ?? ''),
            'signature' => (string) ($manifest['signature'] ?? ''),
        ];
    }
}

if (!function_exists('paymentManifestPersist')) {
    function paymentManifestPersist($conn, array $manifest) {
        $refId = mysqli_real_escape_string($conn, (string) ($manifest['ref_id'] ?? ''));
        $gateway = mysqli_real_escape_string($conn, strtoupper((string) ($manifest['gateway'] ?? '')));
        $itemsJson = mysqli_real_escape_string($conn, (string) ($manifest['items_json'] ?? json_encode($manifest['items'] ?? [], JSON_UNESCAPED_SLASHES)));
        $manifestHash = mysqli_real_escape_string($conn, (string) ($manifest['manifest_hash'] ?? ''));
        $signature = mysqli_real_escape_string($conn, (string) ($manifest['signature'] ?? ''));
        $userId = (int) ($manifest['user_id'] ?? 0);
        $schoolId = (int) ($manifest['school_id'] ?? 0);
        $subtotal = (int) ($manifest['subtotal'] ?? 0);
        $charge = (int) ($manifest['charge'] ?? 0);
        $totalAmount = (int) ($manifest['total_amount'] ?? 0);

        if ($refId === '' || $userId <= 0 || $manifestHash === '' || $signature === '') {
            return false;
        }

        $sql = "INSERT INTO payment_manifests
                    (ref_id, user_id, school_id, gateway, subtotal, charge, total_amount, items_json, manifest_hash, signature)
                VALUES
                    ('$refId', $userId, $schoolId, '$gateway', $subtotal, $charge, $totalAmount, '$itemsJson', '$manifestHash', '$signature')
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    school_id = VALUES(school_id),
                    gateway = VALUES(gateway),
                    subtotal = VALUES(subtotal),
                    charge = VALUES(charge),
                    total_amount = VALUES(total_amount),
                    items_json = VALUES(items_json),
                    manifest_hash = VALUES(manifest_hash),
                    signature = VALUES(signature)";

        return mysqli_query($conn, $sql) === true;
    }
}

if (!function_exists('paymentManifestFetchByRef')) {
    function paymentManifestFetchByRef($conn, $refId) {
        $refIdSafe = mysqli_real_escape_string($conn, (string) $refId);
        $query = mysqli_query($conn, "SELECT * FROM payment_manifests WHERE ref_id = '$refIdSafe' LIMIT 1");
        if (!$query || mysqli_num_rows($query) < 1) {
            return null;
        }

        $row = mysqli_fetch_assoc($query);
        $row['gateway'] = paymentManifestNormalizeGateway($row['gateway'] ?? '');
        $row['subtotal'] = (int) ($row['subtotal'] ?? 0);
        $row['charge'] = (int) ($row['charge'] ?? 0);
        $row['total_amount'] = (int) ($row['total_amount'] ?? 0);
        $row['user_id'] = (int) ($row['user_id'] ?? 0);
        $row['school_id'] = (int) ($row['school_id'] ?? 0);
        $decoded = json_decode((string) ($row['items_json'] ?? '[]'), true);
        $row['items'] = paymentManifestCanonicalizeItems(is_array($decoded) ? $decoded : []);
        return $row;
    }
}

if (!function_exists('paymentManifestIsValid')) {
    function paymentManifestIsValid(array $manifest) {
        $payload = paymentManifestBuildPayload(
            $manifest['ref_id'] ?? '',
            $manifest['user_id'] ?? 0,
            $manifest['school_id'] ?? 0,
            $manifest['gateway'] ?? '',
            isset($manifest['items']) && is_array($manifest['items']) ? $manifest['items'] : [],
            $manifest['subtotal'] ?? 0,
            $manifest['charge'] ?? 0,
            $manifest['total_amount'] ?? 0
        );

        $expectedHash = paymentManifestHashPayload($payload);
        $expectedSignature = paymentManifestSignHash($expectedHash);

        return hash_equals($expectedHash, (string) ($manifest['manifest_hash'] ?? '')) &&
            hash_equals($expectedSignature, (string) ($manifest['signature'] ?? ''));
    }
}

if (!function_exists('paymentManifestExtractGatewayPayload')) {
    function paymentManifestExtractGatewayPayload(array $gatewayData) {
        $candidates = [];

        if (isset($gatewayData['metadata'])) {
            $candidates[] = $gatewayData['metadata'];
        }
        if (isset($gatewayData['meta'])) {
            $candidates[] = $gatewayData['meta'];
        }
        if (isset($gatewayData['customer']) && is_array($gatewayData['customer'])) {
            $candidates[] = $gatewayData['customer'];
        }

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $payload = $candidate['payment_manifest'] ?? null;
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            if (is_array($payload)) {
                return paymentManifestGatewayPayload($payload);
            }
        }

        return null;
    }
}

if (!function_exists('paymentManifestGatewayMatchesStored')) {
    function paymentManifestGatewayMatchesStored(array $gatewayPayload, array $storedManifest) {
        $gatewayHash = (string) ($gatewayPayload['manifest_hash'] ?? '');
        $gatewaySignature = (string) ($gatewayPayload['signature'] ?? '');

        if ($gatewayHash === '' || $gatewaySignature === '') {
            return false;
        }

        return hash_equals((string) $storedManifest['manifest_hash'], $gatewayHash) &&
            hash_equals((string) $storedManifest['signature'], $gatewaySignature) &&
            (string) $storedManifest['ref_id'] === (string) ($gatewayPayload['ref_id'] ?? '') &&
            (int) $storedManifest['user_id'] === (int) ($gatewayPayload['user_id'] ?? 0);
    }
}

if (!function_exists('paymentManifestSnapshotCartRows')) {
    function paymentManifestSnapshotCartRows($conn, $refId, $userId) {
        $refIdSafe = mysqli_real_escape_string($conn, (string) $refId);
        $userId = (int) $userId;
        $rows = [];
        $query = mysqli_query(
            $conn,
            "SELECT item_id, type, status, gateway
             FROM cart
             WHERE ref_id = '$refIdSafe' AND user_id = $userId
             ORDER BY type ASC, item_id ASC, id ASC"
        );

        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $rows[] = [
                    'type' => strtolower((string) ($row['type'] ?? '')),
                    'item_id' => (int) ($row['item_id'] ?? 0),
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'gateway' => strtoupper((string) ($row['gateway'] ?? '')),
                ];
            }
        }

        return $rows;
    }
}

if (!function_exists('paymentManifestCartMatchesItems')) {
    function paymentManifestCartMatchesItems(array $cartRows, array $manifestItems) {
        $cartRows = paymentManifestCanonicalizeItems($cartRows);
        $manifestItems = paymentManifestCanonicalizeItems($manifestItems);

        if (count($cartRows) !== count($manifestItems)) {
            return false;
        }

        for ($i = 0; $i < count($manifestItems); $i++) {
            if ($cartRows[$i]['type'] !== $manifestItems[$i]['type'] ||
                (int) $cartRows[$i]['item_id'] !== (int) $manifestItems[$i]['item_id']) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('paymentManifestAuditRepair')) {
    function paymentManifestAuditRepair($conn, $refId, $userId, $gatewayName, $reason, array $cartSnapshot, array $manifestSnapshot, $actionTaken) {
        $refIdSafe = mysqli_real_escape_string($conn, (string) $refId);
        $gatewaySafe = mysqli_real_escape_string($conn, strtoupper((string) $gatewayName));
        $reasonSafe = mysqli_real_escape_string($conn, (string) $reason);
        $actionSafe = mysqli_real_escape_string($conn, (string) $actionTaken);
        $cartJson = mysqli_real_escape_string($conn, json_encode($cartSnapshot, JSON_UNESCAPED_SLASHES));
        $manifestJson = mysqli_real_escape_string($conn, json_encode($manifestSnapshot, JSON_UNESCAPED_SLASHES));
        $userId = (int) $userId;

        return mysqli_query(
            $conn,
            "INSERT INTO payment_repair_audits
                (ref_id, user_id, gateway, reason, cart_snapshot_json, manifest_snapshot_json, action_taken)
             VALUES
                ('$refIdSafe', $userId, '$gatewaySafe', '$reasonSafe', '$cartJson', '$manifestJson', '$actionSafe')"
        ) === true;
    }
}

if (!function_exists('paymentManifestRepairCartRows')) {
    function paymentManifestRepairCartRows($conn, $refId, $userId, $gatewayName, array $manifestItems) {
        $refIdSafe = mysqli_real_escape_string($conn, (string) $refId);
        $gatewaySafe = mysqli_real_escape_string($conn, strtoupper((string) $gatewayName));
        $userId = (int) $userId;
        $manifestItems = paymentManifestCanonicalizeItems($manifestItems);

        mysqli_begin_transaction($conn);
        try {
            if (!mysqli_query($conn, "DELETE FROM cart WHERE ref_id = '$refIdSafe' AND user_id = $userId")) {
                throw new Exception('Failed to remove mismatched cart rows');
            }

            foreach ($manifestItems as $item) {
                $itemId = (int) $item['item_id'];
                $typeSafe = mysqli_real_escape_string($conn, (string) $item['type']);
                $insertSql = "INSERT INTO cart (ref_id, user_id, item_id, type, status, gateway)
                              VALUES ('$refIdSafe', $userId, $itemId, '$typeSafe', 'pending', '$gatewaySafe')";
                if (!mysqli_query($conn, $insertSql)) {
                    throw new Exception('Failed to recreate cart rows from manifest');
                }
            }

            mysqli_commit($conn);
            return true;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            return false;
        }
    }
}
