<?php
// API: Bulk payments the signed-in student made for course mates (same list as the
// website's "Bulk Payments Made" on orders.php). Receipts: /payment/receipt-pdf.php?ref=<ref_id>
//   GET /materials/bulk/history.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/bulk_material_payment_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int) $user['id'];
$school_id = (int) ($user['school'] ?? 0);

$payments = [];
if (bulk_material_payment_has_table($conn, 'manual_bulk_payment_batches') && bulk_material_payment_has_table($conn, 'manual_bulk_payment_students')) {
    $rs = mysqli_query(
        $conn,
        "SELECT
            b.id,
            b.ref_id,
            b.manual_id,
            b.student_count,
            b.subtotal,
            b.fee_amount,
            b.total_amount,
            b.payment_status,
            COALESCE(b.paid_at, b.created_at) AS purchased_at,
            m.title,
            m.course_code,
            COUNT(s.id) AS listed_students
         FROM manual_bulk_payment_batches AS b
         INNER JOIN manuals AS m ON m.id = b.manual_id
         LEFT JOIN manual_bulk_payment_students AS s ON s.batch_id = b.id
         WHERE b.payer_user_id = {$user_id}
           AND b.school_id = {$school_id}
           AND b.payment_status = 'successful'
         GROUP BY
           b.id, b.ref_id, b.manual_id, b.student_count, b.subtotal, b.fee_amount, b.total_amount,
           b.payment_status, purchased_at, m.title, m.course_code
         ORDER BY purchased_at DESC, b.id DESC
         LIMIT 200"
    );
    while ($rs && ($row = mysqli_fetch_assoc($rs))) {
        $payments[] = [
            'id' => (int) $row['id'],
            'ref_id' => (string) $row['ref_id'],
            'manual_id' => (int) $row['manual_id'],
            'title' => (string) $row['title'],
            'course_code' => (string) $row['course_code'],
            'student_count' => max((int) $row['student_count'], (int) $row['listed_students']),
            'subtotal' => (float) $row['subtotal'],
            'fee_amount' => (float) $row['fee_amount'],
            'total_amount' => (float) $row['total_amount'],
            'status' => (string) $row['payment_status'],
            'paid_at' => (string) $row['purchased_at'],
        ];
    }
}

sendApiSuccess('Bulk payments retrieved successfully', ['payments' => $payments]);
