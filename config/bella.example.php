<?php
// Bella (support assistant, Cloudflare Worker) — copy to bella.php.
// Bella calls API/support/bella-notify.php with this key when a teammate replies in a chat.
// Must match the Worker's NOTIFY_KEY secret (wrangler secret put NOTIFY_KEY).
define('BELLA_NOTIFY_KEY', 'REPLACE_WITH_A_LONG_RANDOM_SECRET');
