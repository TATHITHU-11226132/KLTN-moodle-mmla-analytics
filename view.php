<?php
require_once('../../config.php');
global $DB, $PAGE, $OUTPUT;

require_login();

// Nhận tham số từ URL
$session_id = optional_param('session', 0, PARAM_INT); 
$user_id    = optional_param('userid', 0, PARAM_INT);  
$course_id  = optional_param('courseid', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/mmla_analytics/view.php'));
$PAGE->set_title('Dashboard MMLA'); 
$PAGE->set_heading('Hệ thống Quản lý Học tập Đa phương thức');

// ================================================================
// TẦNG 3: XEM CHI TIẾT MỘT PHIÊN HỌC CỤ THỂ (DASHBOARD AI)
// ================================================================
// ================================================================
// TẦNG 3: XEM CHI TIẾT MỘT PHIÊN HỌC CỤ THỂ (DASHBOARD AI)
// ================================================================
if ($session_id > 0) {
    echo $OUTPUT->header();
    
    // 1. Lấy thông tin user của session này
    $sql_user = "SELECT u.firstname, u.lastname, u.email, s.timestart, s.userid 
                 FROM {local_mmla_session} s 
                 JOIN {user} u ON s.userid = u.id 
                 WHERE s.id = :sessid";
    $user_info = $DB->get_record_sql($sql_user, ['sessid' => $session_id]);
    
    // THÊM ĐOẠN NÀY ĐỂ BẢO VỆ CODE: Kiểm tra xem có lấy được data không
    if (!$user_info) {
        echo '<div class="alert alert-danger">Lỗi: Phiên dữ liệu số #'.$session_id.' không tồn tại, hoặc tài khoản sinh viên đã bị xóa khỏi hệ thống!</div>';
        echo $OUTPUT->footer();
        die();
    }
    // KẾT THÚC ĐOẠN BẢO VỆ

    echo '<a href="view.php?userid='.$user_info->userid.($course_id > 0 ? '&courseid='.$course_id : '').'" class="btn btn-secondary mb-3">⬅️ Quay lại lịch sử của sinh viên</a>';
    // 2. Lấy dữ liệu Đa phương thức (Cảm xúc), Code, SRL
    $emotions = $DB->get_records('local_mmla_multimodal', ['sessionid' => $session_id], 'id ASC');
    $emotion_counts = ['Focused' => 0, 'Confused' => 0, 'Happy' => 0];
    foreach ($emotions as $e) {
        if (isset($emotion_counts[$e->emotion])) { $emotion_counts[$e->emotion]++; }
    }
    
    $code_logs = $DB->get_records('local_mmla_coding', ['sessionid' => $session_id], 'id DESC');
    $srl_logs = $DB->get_records('local_mmla_srl', ['sessionid' => $session_id], 'id ASC');

    // 3. HIỂN THỊ GIAO DIỆN TẦNG 3
    echo '<div class="alert alert-info shadow-sm">';
    echo '<h5 class="mb-1">Đang xem phân tích của sinh viên: <strong>' . $user_info->lastname . ' ' . $user_info->firstname . '</strong></h5>';
    echo '<p class="mb-0">Mã Phiên thi: <strong>#' . $session_id . '</strong> | Bắt đầu lúc: ' . userdate($user_info->timestart) . '</p>';
    echo '</div>';

    // --- QUY TRÌNH SRL ---
    echo '<div class="card shadow-sm mb-4"><div class="card-header bg-dark text-white"><h5 class="mb-0">Quy trình Tự điều chỉnh </h5></div><div class="card-body">';
    if (empty($srl_logs)) {
        echo '<p class="text-muted">Chưa ghi nhận tương tác SRL.</p>';
    } else {
        echo '<div class="d-flex flex-wrap gap-2">';
        foreach ($srl_logs as $log) {
            $srl_data = json_decode($log->action);
            $type = isset($srl_data->type) ? $srl_data->type : 'UNK';
            $act = isset($srl_data->act) ? $srl_data->act : 'Unknown';
            $badge = 'badge-secondary';
            if ($type == 'UGPP') $badge = 'badge-primary';
            if ($type == 'UMP') $badge = 'badge-warning text-dark';
            if ($type == 'USEP') $badge = 'badge-info';
            echo '<span class="badge '.$badge.' p-2" style="font-size: 14px;">'.$type.': '.$act.'</span>&nbsp;';
        }
        echo '</div>';
    }
    echo '</div></div>';

    // --- BIỂU ĐỒ VÀ CODE ---
    echo '<div class="row">';
    echo '<div class="col-md-5"><div class="card shadow-sm mb-4">';
    echo '<div class="card-header bg-info text-white"><h5 class="mb-0">Trạng thái Tâm lý </h5></div>';
    echo '<div class="card-body"><canvas id="emotionChart" width="400" height="400"></canvas></div>';
    echo '</div></div>';

    echo '<div class="col-md-7"><div class="card shadow-sm mb-4">';
    echo '<div class="card-header bg-secondary text-white"><h5 class="mb-0">Hành vi Viết mã </h5></div>';
    echo '<div class="card-body" style="max-height: 500px; overflow-y: auto;">';
    if (empty($code_logs)) {
        echo '<div class="alert alert-warning">Sinh viên chưa chạy đoạn code nào.</div>';
    } else {
        echo '<ul class="list-group">';
        foreach ($code_logs as $log) {
            $is_error = strpos($log->code_content, '# ERROR') !== false;
            $bg_color = $is_error ? 'list-group-item-danger' : 'list-group-item-light';
            echo '<li class="list-group-item '.$bg_color.'">';
            echo '<strong>'.userdate($log->timecreated).'</strong>';
            echo '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px; white-space: pre-wrap;">'.htmlspecialchars($log->code_content).'</pre>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div></div></div></div>';

    // ĐOẠN JAVASCRIPT ĐỂ VẼ BIỂU ĐỒ
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '<script>
        window.onload = function() {
            var ctx = document.getElementById("emotionChart").getContext("2d");
            new Chart(ctx, {
                type: "pie",
                data: {
                    labels: ["Tập trung (Focused)", "Bối rối (Confused)", "Tích cực (Happy)"],
                    datasets: [{
                        data: ['.$emotion_counts["Focused"].', '.$emotion_counts["Confused"].', '.$emotion_counts["Happy"].'],
                        backgroundColor: ["#28a745", "#dc3545", "#ffc107"]
                    }]
                }
            });
        };
    </script>';

    echo $OUTPUT->footer();
    die();
}

// ================================================================
// TẦNG 2: XEM LỊCH SỬ CÁC LẦN THI CỦA 1 SINH VIÊN CỤ THỂ
// ================================================================
if ($user_id > 0) {
    echo $OUTPUT->header();
    $user = $DB->get_record('user', ['id' => $user_id]);
    $fullname = $user->lastname . ' ' . $user->firstname;
    
    echo '<a href="view.php'.($course_id > 0 ? '?courseid='.$course_id : '').'" class="btn btn-secondary mb-3">⬅️ Quay lại Tổng quan Lớp</a>';
    echo '<div class="card shadow-sm">';
    echo '<div class="card-header bg-success text-white"><h5 class="mb-0">Lịch sử làm bài của: ' . htmlspecialchars($fullname) . '</h5></div>';
    echo '<div class="card-body">';

    $sql_user_sessions = "SELECT s.id AS sessionid, s.timestart, c.shortname AS coursename
                          FROM {local_mmla_session} s
                          JOIN {course} c ON s.courseid = c.id
                          WHERE s.userid = :userid
                          ORDER BY s.id DESC";
                          
    $user_sessions = $DB->get_records_sql($sql_user_sessions, ['userid' => $user_id]);

    if ($user_sessions) {
        echo '<table class="table table-hover table-bordered bg-white">';
        echo '<thead class="thead-light"><tr><th>Mã Phiên (Session ID)</th><th>Môn học</th><th>Thời gian thi</th><th class="text-center">Hành động</th></tr></thead><tbody>';
        foreach ($user_sessions as $sess) {
            $url = new moodle_url('/local/mmla_analytics/view.php', ['session' => $sess->sessionid, 'courseid' => $course_id]);
            echo '<tr>';
            echo '<td><strong>#' . $sess->sessionid . '</strong></td>';
            echo '<td><span class="badge badge-info">' . htmlspecialchars($sess->coursename) . '</span></td>';
            echo '<td>' . userdate($sess->timestart, '%d/%m/%Y %H:%M:%S') . '</td>';
            echo '<td class="text-center"><a href="'.$url.'" class="btn btn-sm btn-primary">📊 Xem phân tích AI</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>Sinh viên này chưa có dữ liệu làm bài.</p>';
    }
    echo '</div></div>';
    echo $OUTPUT->footer();
    die();
}

// ================================================================
// TẦNG 1: TỔNG QUAN DANH SÁCH SINH VIÊN (GOM NHÓM THEO ID)
// ================================================================
echo $OUTPUT->header();

echo '<div class="card shadow-sm">';
echo '<div class="card-header bg-primary text-white"><h5 class="mb-0">Tổng quan Sinh viên (Đã lọc trùng lặp)</h5></div>';
echo '<div class="card-body">';

$params = [];
$where_clause = "";
if ($course_id > 0) {
    $where_clause = "WHERE s.courseid = :courseid";
    $params['courseid'] = $course_id;
}

$sql_students = "SELECT u.id AS userid, u.firstname, u.lastname, u.email, 
                        COUNT(s.id) AS total_sessions, 
                        MAX(s.timestart) AS last_active
                 FROM {local_mmla_session} s
                 JOIN {user} u ON s.userid = u.id
                 $where_clause
                 GROUP BY u.id, u.firstname, u.lastname, u.email
                 ORDER BY last_active DESC";

$students = $DB->get_records_sql($sql_students, $params);

if ($students) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-hover table-bordered bg-white">';
    echo '<thead class="thead-light"><tr>
            <th width="5%">ID</th>
            <th width="30%">Họ và Tên Sinh viên</th>
            <th width="25%">Email</th>
            <th width="15%" class="text-center">Số lượt làm test</th>
            <th width="15%">Làm bài gần nhất</th>
            <th width="10%" class="text-center">Hành động</th>
          </tr></thead><tbody>';

    foreach ($students as $stu) {
        $url = new moodle_url('/local/mmla_analytics/view.php', ['userid' => $stu->userid, 'courseid' => $course_id]);
        $fullname = $stu->lastname . ' ' . $stu->firstname;
        
        echo '<tr>';
        echo '<td>' . $stu->userid . '</td>';
        echo '<td><span class="text-primary font-weight-bold">' . htmlspecialchars($fullname) . '</span></td>';
        echo '<td>' . htmlspecialchars($stu->email) . '</td>';
        echo '<td class="text-center"><span class="badge badge-pill badge-warning" style="font-size: 14px;">' . $stu->total_sessions . ' lượt</span></td>';
        echo '<td>' . userdate($stu->last_active, '%d/%m/%Y %H:%M') . '</td>';
        echo '<td class="text-center"><a href="'.$url.'" class="btn btn-sm btn-success">📂 Xem danh sách test</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
} else {
    echo '<p class="text-muted">Chưa có sinh viên nào làm bài trong khóa học này.</p>';
}

echo '</div></div>';
echo $OUTPUT->footer();
?>