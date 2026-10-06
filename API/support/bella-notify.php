<?php
// Called by the Bella Worker (server to server), header X-Bella-Key: BELLA_NOTIFY_KEY (config/bella.php).
//   POST {mode: "push", user_id, agent_name, preview}  a teammate replied: in-app + push notification
//   POST {mode: "transcript", user_id, messages[]}     the student did not open or answer the chat in
//                                                      time: email them the conversation
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/mail.php';
require_once __DIR__ . '/../../model/notifications.php';
if (file_exists(__DIR__ . '/../../config/bella.php')) {
    require_once __DIR__ . '/../../config/bella.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}
$key = (string) ($_SERVER['HTTP_X_BELLA_KEY'] ?? '');
if (!defined('BELLA_NOTIFY_KEY') || BELLA_NOTIFY_KEY === '' || !hash_equals((string) BELLA_NOTIFY_KEY, $key)) {
    sendApiError('Unauthorized', 401);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$mode = (string) ($input['mode'] ?? 'push');
$userId = (int) ($input['user_id'] ?? 0);
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, first_name, email FROM users WHERE id = $userId LIMIT 1"));
if (!$user) {
    sendApiError('User not found', 404);
}

// mode=push: a teammate just replied -> in-app notification + push to the student's devices
if ($mode === 'push') {
    $agent = trim((string) ($input['agent_name'] ?? 'Nivasity team')) ?: 'Nivasity team';
    $preview = trim((string) ($input['preview'] ?? ''));
    if (mb_strlen($preview) > 140) {
        $preview = mb_substr($preview, 0, 137) . '...';
    }
    $body = $preview !== '' ? "$agent: $preview" : "$agent replied to your support chat.";
    $result = notifyUser($conn, $userId, 'New reply from the Nivasity team', $body, 'support', ['action' => 'bella_chat']);
    sendApiSuccess('Notified', ['notification' => $result]);
}

// mode=transcript: the student has not opened or answered the chat -> email them the conversation
if ($mode === 'transcript') {
    if (empty($user['email'])) {
        sendApiSuccess('No email on file', ['email_sent' => false]);
    }
    $rows = '';
    foreach (array_slice((array) ($input['messages'] ?? []), -30) as $m) {
        $role = (string) ($m['role'] ?? '');
        $who = $role === 'student' ? 'You' : ($role === 'agent' ? (string) ($m['agent_name'] ?? 'Nivasity team') : 'Bella');
        $mine = $role === 'student';
        $rows .= '<tr><td style="padding:6px 0"><div style="font-size:12px;color:#888">' . htmlspecialchars($who) . ' &middot; ' . htmlspecialchars((string) ($m['created_at'] ?? '')) . ' UTC</div>'
            . '<div style="padding:8px 12px;border-radius:10px;background:' . ($mine ? '#fff4e5' : '#f3f3f6') . '">' . nl2br(htmlspecialchars((string) ($m['content'] ?? ''))) . '</div></td></tr>';
    }
    $name = htmlspecialchars((string) $user['first_name']);
    $sent = (bool) sendMail(
        'Your Nivasity support chat',
        "Hi $name,<br><br>The Nivasity team replied in your support chat and we haven't heard back from you. Here is the conversation:<br><br>"
        . '<table width="100%" cellspacing="0" cellpadding="0">' . $rows . '</table><br>'
        . 'To reply, open the Nivasity app or website and go to <b>Help &amp; Support</b>.<br><br>Nivasity Support',
        (string) $user['email']
    );
    sendApiSuccess('Transcript emailed', ['email_sent' => $sent]);
}

sendApiError('Unknown mode', 400);
