<?php
require_once('../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/local/mmla_analytics/start_session.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Khởi tạo Phiên MMLA');
$PAGE->set_heading('Trạm điều khiển MMLA');

echo $OUTPUT->header();

global $DB;
$action = optional_param('action', '', PARAM_TEXT);

// Khi bạn bấm nút Tạo phiên
if ($action === 'create') {
    $record = new stdClass();
    $record->userid = 2;       // Giả sử test với tài khoản Sinh viên ID = 2
    $record->courseid = 1;     // Giả sử đang ở Khóa học Python ID = 1
    $record->timestart = time();
    
    $new_id = $DB->insert_record('local_mmla_session', $record);
    
    echo "<div class='alert alert-success mt-3' style='font-size: 1.2rem;'>
            ✅ Khởi tạo thành công!<br>
            Hãy nhập số <strong>{$new_id}</strong> vào màn hình Python để bắt đầu thu thập dữ liệu.
          </div>";
    
    echo "<a href='index.php' class='btn btn-primary mt-2'>Đến trang Dashboard</a>";
} else {
    // Giao diện mặc định
    echo "<p>Nhấn nút bên dưới để tạo một phiên giám sát học tập (Session ID) mới cho sinh viên trước khi họ làm bài Test.</p>";
    echo "<a href='start_session.php?action=create' class='btn btn-success btn-lg'>Tạo Phiên Học Mới</a>";
}

echo $OUTPUT->footer();