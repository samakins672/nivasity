<?php
// API: Serve a support ticket attachment.
//   GET /support/attachment.php?file=support_1_7XX4xXGV_10.jpg
// Attachments live in the website's assets/images/supports/ folder. The school domains now
// serve the white label portal (Cloudflare, no PHP), so they can no longer be opened at
// funaab.nivasity.com/assets/... . Only files in that folder are served (no paths), under
// the same hard-to-guess names they always had.
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$file = basename((string) ($_GET['file'] ?? ''));
if ($file === '' || !preg_match('/^support_[A-Za-z0-9_\-]+\.(jpe?g|png|pdf)$/i', $file)) {
    sendApiError('Attachment not found', 404);
}

$path = dirname(__DIR__, 2) . '/assets/images/supports/' . $file;
if (!is_file($path)) {
    sendApiError('Attachment not found', 404);
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $file . '"');
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
