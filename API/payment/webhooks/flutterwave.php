<?php
// Flutterwave webhook entry point on the API host:
//   https://api.nivasity.com/payment/webhooks/flutterwave.php
// The school domains (e.g. funaab.nivasity.com) now serve the white label portal from
// Cloudflare, which cannot run PHP, so gateways must post here. This runs the existing
// handler (model/handle-fw-webhook.php) unchanged; its relative includes resolve from model/.
chdir(__DIR__ . '/../../../model');
require __DIR__ . '/../../../model/handle-fw-webhook.php';
