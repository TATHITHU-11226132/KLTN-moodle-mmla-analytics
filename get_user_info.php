<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once('../../config.php');
global $USER, $DB;

require_login();
header('Content-Type: application/json; charset=utf-8');

try {
    $userid = $USER->id;
    // Tìm phiên làm bài mới nhất, không tự ý INSERT thêm
    $sessions = $DB->get_records('local_mmla_session', ['userid' => $userid], 'id DESC', '*', 0, 1);

    if ($sessions) {
        $session = reset($sessions); 
        echo json_encode([
            'status'     => 'success',
            'session_id' => $session->id,
            'username'   => $USER->username
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'no_active_session']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}