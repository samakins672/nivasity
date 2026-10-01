<?php
// API: Material requests visible to the student (their department/faculty audience), with upvote
// progress toward the threshold. Pass ?token=<share_token> to also get that request as "highlighted".
define('NIVASITY_MATERIAL_REQUESTS_API', true);
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = materialRequestsApiUser($conn, false);
$requests = nivasityMaterialRequestFetchVisibleRequests($conn, $user);

$token = trim((string)($_GET['token'] ?? ''));
$highlighted = null;
if ($token !== '') {
    foreach ($requests as $request) {
        if (hash_equals((string)($request['share_token'] ?? ''), $token)) {
            $highlighted = $request;
            break;
        }
    }
}

sendApiSuccess('Material requests retrieved successfully', [
    'requests' => $requests,
    'highlighted' => $highlighted,
]);
