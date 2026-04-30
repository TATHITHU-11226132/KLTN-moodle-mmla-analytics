<?php
// ajax_session.php - Chuyên tạo Session ngầm
require_once('../../config.php');
require_login(); // Phải đăng nhập mới được tạo

header('Content-Type: application/json');
global $USER, $DB;

try {
    $record = new stdClass();
    $record->userid = $USER->id; // Tự động lấy đúng ID của sinh viên đang đăng nhập
    $record->courseid = 1;       // Tạm mặc định là khóa Python (ID = 1)
    $record->timestart = time();
    
    $new_id = $DB->insert_record('local_mmla_session', $record);
    
    // Trả về số ID bằng định dạng JSON
    echo json_encode(['status' => 'success', 'session_id' => $new_id]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}