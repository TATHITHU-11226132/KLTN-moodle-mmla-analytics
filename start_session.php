<?php
require_once('../../config.php');
require_login();

$course_id = optional_param('courseid', 3, PARAM_INT);
$action = optional_param('action', '', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/local/mmla_analytics/start_session.php', ['courseid' => $course_id]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Khởi tạo Phiên MMLA');

echo $OUTPUT->header();
global $DB, $USER;

if ($action === 'create') {
    // 1. Lấy phiên làm bài gần nhất của sinh viên này
    $last_sessions = $DB->get_records('local_mmla_session', ['userid' => $USER->id], 'id DESC', '*', 0, 1);
    $reuse_session_id = false;

    if ($last_sessions) {
        $last_session = reset($last_sessions);
        
        // 2. Kiểm tra xem phiên này đã có dữ liệu tương tác chưa
        $has_multimodal = $DB->record_exists('local_mmla_multimodal', ['sessionid' => $last_session->id]);
        $has_srl        = $DB->record_exists('local_mmla_srl', ['sessionid' => $last_session->id]);
        $has_coding     = $DB->record_exists('local_mmla_coding', ['sessionid' => $last_session->id]);

        // 3. Nếu cả 3 bảng đều KHÔNG có dữ liệu -> Đánh dấu là phiên rác để dùng lại
        if (!$has_multimodal && !$has_srl && !$has_coding) {
            $reuse_session_id = $last_session->id;
            
            // Làm mới lại mốc thời gian bắt đầu
            $last_session->timestart = time();
            $last_session->courseid = $course_id;
            $DB->update_record('local_mmla_session', $last_session);
        }
    }

    // 4. Nếu không có phiên rác nào để tái sử dụng, lúc này mới tạo ID mới
    if (!$reuse_session_id) {
        $record = new stdClass();
        $record->userid = $USER->id;       
        $record->courseid = $course_id; 
        $record->timestart = time();
        $DB->insert_record('local_mmla_session', $record);
    }
    
    // Chuyển hướng sang làm bài ngay
    redirect(new moodle_url('/local/mmla_analytics/test.html'));
    
} else {
    echo "<div class='card p-4 text-center'>";
    echo "<h3>Sẵn sàng làm bài thi MMLA?</h3>";
    echo "<p>Hệ thống AI sẽ bắt đầu ghi nhận dữ liệu ngay khi bạn nhấn nút.</p>";
    $url = new moodle_url('/local/mmla_analytics/start_session.php', ['action' => 'create', 'courseid' => $course_id]);
    echo "<a href='$url' class='btn btn-success btn-lg'>🚀 Bắt đầu làm bài</a>";
    echo "</div>";
}
echo $OUTPUT->footer();