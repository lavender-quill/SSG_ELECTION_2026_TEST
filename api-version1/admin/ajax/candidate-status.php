<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';

requireAdminCsrf();

$sid    = trim($_POST['student_id']         ?? '');
$yr     = trim($_POST['election_year']      ?? '');
$status = strtoupper(trim($_POST['application_status'] ?? ''));

$allowedStatuses = ['PENDING', 'APPROVED', 'DENIED', 'DISQUALIFIED'];
if (!$sid || !$status) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}
if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid application status value.']);
    exit;
}

$res = callModel(function() use ($sid, $yr, $status) {
    Candidate::Profile_Status_Update([
        'Student_ID'         => $sid,
        'Election_Year'      => $yr,
        'Application_Status' => $status,
    ]);
});

$errMsg = $res['Status'] ?? '';
if (isError($res) && strpos($errMsg, 'Could not parse API response') === false) {
    echo json_encode(['success' => false, 'error' => $errMsg ?: 'Failed to update status']);
} else {
    echo json_encode(['success' => true]);
}
