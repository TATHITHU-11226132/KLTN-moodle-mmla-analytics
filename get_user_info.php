<?php
// Bịt miệng PHP: Không in lỗi ra màn hình làm hỏng định dạng JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once('../../config.php');
global $CFG, $USER, $DB;
$CFG->debugdisplay = 0;

require_login();
header('Content-Type: application/json; charset=utf-8');

try {
    $userid = $USER->id;

    // 1. Tìm phiên làm bài mới nhất của user này
    $sessions = $DB->get_records('local_mmla_session', ['userid' => $userid], 'id DESC', '*', 0, 1);

    if ($sessions) {
        // Nếu ĐÃ CÓ: Lấy ID cũ
        $session = reset($sessions); 
        $session_id = $session->id;
    } else {
        // Nếu CHƯA CÓ (Lần đầu sinh viên vào): TỰ ĐỘNG TẠO MỚI
        $new_session = new stdClass();
        $new_session->userid = $userid;
        $new_session->courseid = 1; // Giả sử ID khóa học mặc định là 1
        $new_session->timestart = time();
        
        $session_id = $DB->insert_record('local_mmla_session', $new_session);
    }

    // 2. Trả về kết quả JSON thành công 100%
    echo json_encode([
        'status'     => 'success',
        'userid'     => $USER->id,
        'username'   => $USER->username,
        'session_id' => $session_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}
?>