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
if ($session_id > 0) {
    echo $OUTPUT->header();
    
    // 1. Lấy thông tin user
    $sql_user = "SELECT u.firstname, u.lastname, u.email, s.timestart, s.userid 
                 FROM {local_mmla_session} s 
                 JOIN {user} u ON s.userid = u.id 
                 WHERE s.id = :sessid";
    $user_info = $DB->get_record_sql($sql_user, ['sessid' => $session_id]);
    
    if (!$user_info) {
        echo '<div class="alert alert-danger">Lỗi: Phiên dữ liệu không tồn tại!</div>';
        echo $OUTPUT->footer();
        die();
    }

    echo '<a href="view.php?userid='.$user_info->userid.($course_id > 0 ? '&courseid='.$course_id : '').'" class="btn btn-secondary mb-3">⬅️ Quay lại lịch sử của sinh viên</a>';
    
    // Khung thông tin chung
    echo '<div class="alert alert-info shadow-sm">';
    echo '<h5 class="mb-1">Đang xem phân tích của sinh viên: <strong>' . htmlspecialchars($user_info->lastname . ' ' . $user_info->firstname) . '</strong></h5>';
    echo '<p class="mb-0">Mã Phiên: <strong>#' . $session_id . '</strong> | Bắt đầu lúc: ' . userdate($user_info->timestart) . '</p>';
    echo '</div>';

    // 2. THU THẬP VÀ CHUẨN HÓA DỮ LIỆU ĐỂ VẼ TIMELINE
    $emotions = $DB->get_records('local_mmla_multimodal', ['sessionid' => $session_id], 'timecreated ASC');
    $code_logs = $DB->get_records('local_mmla_coding', ['sessionid' => $session_id], 'timecreated ASC');
    $srl_logs = $DB->get_records('local_mmla_srl', ['sessionid' => $session_id], 'timecreated ASC');

    $timeline = [];
    $emotion_counts = ['Focused' => 0, 'Confused' => 0, 'Happy' => 0, 'NoFace' => 0];
    
    $example_pr = "Chưa có dữ liệu";
    $example_ps = "Chưa có dữ liệu";
    $appraisal_data = null;

    // Phân tích Cảm xúc
    if ($emotions) {
        foreach ($emotions as $e) {
            $timeline[] = ['time' => $e->timecreated, 'type' => 'emotion', 'data' => $e->emotion];
            if (isset($emotion_counts[$e->emotion])) { 
                $emotion_counts[$e->emotion]++; 
            }
        }
    }
    
    // Phân tích Code
    if ($code_logs) {
        foreach ($code_logs as $c) {
            $timeline[] = ['time' => $c->timecreated, 'type' => 'code', 'status' => $c->status, 'code' => $c->code_content, 'error' => $c->error_log];
        }
    }
    
    // Phân tích SRL và bóc tách dữ liệu đặc biệt (Bổ sung kiểm tra JSON an toàn)
    if ($srl_logs) {
        foreach ($srl_logs as $s) {
            $srl_data = json_decode($s->action);
            if (json_last_error() === JSON_ERROR_NONE && is_object($srl_data)) {
                $srl_type = isset($srl_data->type) ? $srl_data->type : 'UNK';
                $srl_act = isset($srl_data->act) ? $srl_data->act : 'Unknown';
            } else {
                // Rơi vào trường hợp chuỗi lưu thuần túy không phải JSON
                $srl_type = 'RAW';
                $srl_act = $s->action;
            }
            
            $timeline[] = ['time' => $s->timecreated, 'type' => 'srl', 'srl_type' => $srl_type, 'act' => $srl_act];

            if ($srl_type == 'PR') $example_pr = $srl_act;
            if ($srl_type == 'PS') $example_ps = str_replace("Chọn đáp án: ", "", $srl_act);
            if ($srl_type == 'USEP') $appraisal_data = $srl_act; 
        }
    }

    // Sắp xếp trộn 3 mảng theo thứ tự thời gian tăng dần
    usort($timeline, function($a, $b) { return $a['time'] <=> $b['time']; });

    echo '<div class="row">';
    
    // CỘT TRÁI: KẾT QUẢ PHÂN TÍCH NHẬN THỨC VÀ ĐÁNH GIÁ (Appraisal & Examples)
    echo '<div class="col-md-5">';
    
    // 1. Phân tích Học qua ví dụ
    echo '<div class="card shadow-sm mb-4"><div class="card-header bg-warning text-dark"><h5 class="mb-0">Phân tích Ví dụ</h5></div><div class="card-body">';
    echo '<p><strong>Trạng thái tiếp cận:</strong> ' . htmlspecialchars($example_pr) . '</p>';
    
    $ps_badge = 'badge-secondary';
    if ($example_ps == 'syntax') { $ps_badge = 'badge-success'; $example_ps = 'Lỗi cú pháp (Chính xác)'; }
    elseif ($example_ps == 'logic') { $ps_badge = 'badge-danger'; $example_ps = 'Lỗi logic (Sai)'; }
    elseif ($example_ps == 'indent') { $ps_badge = 'badge-danger'; $example_ps = 'Lỗi thụt lề (Sai)'; }
    
    echo '<p><strong>Nhận diện lỗi (PS):</strong> <span class="badge '.$ps_badge.' p-2" style="font-size: 14px;">' . htmlspecialchars($example_ps) . '</span></p>';
    echo '</div></div>';

    // 2. Phân tích Bảng Tự Đánh Giá (Appraisal)
    echo '<div class="card shadow-sm mb-4"><div class="card-header bg-primary text-white"><h5 class="mb-0">Bảng Tự đánh giá</h5></div><div class="card-body">';
    if ($appraisal_data) {
        $answers = explode(' | ', $appraisal_data);
        $dict = [
            'Q1:yes' => 'Hoàn thành 100%', 'Q1:partial' => 'Hoàn thành một phần', 'Q1:no' => 'Chưa hoàn thành',
            'Q2:good' => 'Rất hợp lý', 'Q2:ok' => 'Tạm ổn', 'Q2:bad' => 'Không hợp lý',
            'Q3:high' => 'Rất cao', 'Q3:medium' => 'Bình thường', 'Q3:low' => 'Thấp'
        ];
        
        $q1 = isset($answers[0]) && isset($dict[trim($answers[0])]) ? $dict[trim($answers[0])] : 'Không rõ';
        $q2 = isset($answers[1]) && isset($dict[trim($answers[1])]) ? $dict[trim($answers[1])] : 'Không rõ';
        $q3 = isset($answers[2]) && isset($dict[trim($answers[2])]) ? $dict[trim($answers[2])] : 'Không rõ';
        $q4_raw = isset($answers[3]) ? str_replace('Q4:', '', $answers[3]) : 'Không có';

        echo '<ul class="list-group list-group-flush">';
        echo '<li class="list-group-item"><strong>Hoàn thành nhiệm vụ:</strong> <span class="text-primary">'.$q1.'</span></li>';
        echo '<li class="list-group-item"><strong>Phân bổ thời gian:</strong> <span class="text-success">'.$q2.'</span></li>';
        echo '<li class="list-group-item"><strong>Mức độ tập trung:</strong> <span class="text-info">'.$q3.'</span></li>';
        echo '<li class="list-group-item"><strong>Chiến lược hiệu quả:</strong><br><em class="text-muted">"'.htmlspecialchars($q4_raw).'"</em></li>';
        echo '</ul>';
    } else {
        echo '<div class="alert alert-warning">Sinh viên chưa nộp bảng tự đánh giá cuối giờ.</div>';
    }
    echo '</div></div>';

    // 3. Biểu đồ cảm xúc
    $focused_val = (int)$emotion_counts["Focused"];
    $confused_val = (int)$emotion_counts["Confused"];
    $happy_val = (int)$emotion_counts["Happy"];

    echo '<div class="card shadow-sm mb-4"><div class="card-header bg-info text-white"><h5 class="mb-0">Biểu đồ trạng thái Tâm lý</h5></div><div class="card-body"><canvas id="emotionChart" width="400" height="300"></canvas>';
    
    if ($focused_val == 0 && $confused_val == 0 && $happy_val == 0) {
        echo '<p class="text-center text-muted mt-2"><small>Chưa có đủ dữ liệu khuôn mặt để phân tích.</small></p>';
    }
    
    echo '</div></div>';
    echo '</div>'; // Hết cột trái

    // CỘT PHẢI: LUỒNG HÀNH VI (PROCESS MINING TIMELINE)
    echo '<div class="col-md-7"><div class="card shadow-sm mb-4">';
    echo '<div class="card-header bg-dark text-white"><h5 class="mb-0">Bản đồ Luồng hành vi</h5></div>';
    echo '<div class="card-body" style="background: #f8f9fa; max-height: 800px; overflow-y: auto;">';
    
    if (empty($timeline)) {
        echo '<div class="alert alert-warning">Chưa có hành vi nào được ghi nhận.</div>';
    } else {
        echo '<div style="border-left: 3px solid #ccc; padding-left: 20px; margin-left: 10px;">';
        foreach ($timeline as $event) {
            $time_str = userdate($event['time'], '%H:%M:%S');
            
            if ($event['type'] == 'emotion') {
                if ($event['data'] == 'NoFace') continue; 
                
                $emo_color = $event['data'] == 'Focused' ? 'text-success' : ($event['data'] == 'Confused' ? 'text-danger' : 'text-warning');
                $emo_icon = $event['data'] == 'Focused' ? '🎯' : ($event['data'] == 'Confused' ? '❓' : '😊');
                echo '<div class="mb-3"><small class="text-muted">'.$time_str.'</small><br><span class="'.$emo_color.' font-weight-bold">'.$emo_icon.' Cảm xúc: '.$event['data'].'</span></div>';
            
            } elseif ($event['type'] == 'srl') {
                $badge = 'badge-secondary';
                if ($event['srl_type'] == 'UGPP') $badge = 'badge-primary';
                if ($event['srl_type'] == 'UMP') $badge = 'badge-warning text-dark';
                if ($event['srl_type'] == 'PR' || $event['srl_type'] == 'PS') $badge = 'badge-info';
                if ($event['srl_type'] == 'USEP') $badge = 'badge-dark';
                
                echo '<div class="mb-3"><small class="text-muted">'.$time_str.'</small><br><span class="badge '.$badge.' p-2" style="font-size:13px;">['.htmlspecialchars($event['srl_type']).'] '.htmlspecialchars($event['act']).'</span></div>';
            
            } elseif ($event['type'] == 'code') {
                $is_pc = $event['status'] == 'PC';
                $is_err = $event['status'] == 'Error';
                $border_color = $is_pc ? '#17a2b8' : ($is_err ? '#dc3545' : '#28a745');
                $status_label = $is_pc ? ' Đang gõ code (PC)' : ($is_err ? '❌ Chạy lỗi (DB)' : 'Chạy thành công (RP)');
                
                echo '<div class="mb-3"><small class="text-muted">'.$time_str.'</small><br>';
                echo '<div style="border-left: 4px solid '.$border_color.'; padding-left: 10px; background: #fff; padding: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                echo '<strong>'.$status_label.'</strong>';
                if (!$is_pc) {
                    echo '<pre style="background: #272822; color: #f8f8f2; padding: 8px; margin-top: 5px; border-radius: 4px; font-size: 12px; white-space: pre-wrap;">'.htmlspecialchars($event['code']).'</pre>';
                    if ($is_err) echo '<div class="text-danger mt-1" style="font-size: 12px;">'.htmlspecialchars($event['error']).'</div>';
                }
                echo '</div></div>';
            }
        }
        echo '</div>'; // Đóng đường kẻ dọc

        // --- BẮT ĐẦU ĐOẠN CODE VẼ SƠ ĐỒ MERMAID ---
        echo '<hr><h5 class="mt-4 mb-3">Sơ đồ Luồng trạng thái (Process Map)</h5>';
        echo '<div class="text-center bg-white p-3 border" style="border-radius: 8px;">';
        echo '<div class="mermaid">';
        
        echo "graph TD\n";
        echo "classDef academic fill:#fff,stroke:#000,stroke-width:2px,color:#000,font-family:Arial;\n";
        
        $prev_node = "";
        $node_counter = 1;

        foreach ($timeline as $event) {
            $label = "";
            if ($event['type'] == 'emotion' && $event['data'] != 'NoFace') {
                $label = "Cảm xúc: " . $event['data'];
            } elseif ($event['type'] == 'srl') {
                $label = "[" . $event['srl_type'] . "] " . $event['act'];
            } elseif ($event['type'] == 'code') {
                $label = $event['status'] == 'PC' ? "Gõ code (PC)" : ($event['status'] == 'Error' ? "Lỗi (DB)" : "Thành công (RP)");
            }
            
            if ($label != "") {
                $safe_label = str_replace('"', "'", $label);
                $current_node = "N" . $node_counter;
                echo $current_node . '("' . $safe_label . '"):::academic' . "\n";
                if ($prev_node != "") {
                    echo $prev_node . " --> " . $current_node . "\n";
                }
                $prev_node = $current_node;
                $node_counter++;
            }
        }
        echo '</div></div>';
        echo '<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>';
        echo '<script>mermaid.initialize({startOnLoad:true, theme: "neutral"});</script>';
        // --- KẾT THÚC ĐOẠN CODE VẼ SƠ ĐỒ MERMAID ---
    }
    echo '</div></div></div></div>'; // Đóng thẻ

    // Chart.js Script
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '<script>
        window.onload = function() {
            var ctx = document.getElementById("emotionChart").getContext("2d");
            new Chart(ctx, {
                type: "doughnut",
                data: {
                    labels: ["Tập trung (Focused)", "Bối rối (Confused)", "Tích cực (Happy)"],
                    datasets: [{
                        data: ['. $focused_val .', '. $confused_val .', '. $happy_val .'],
                        backgroundColor: ["#28a745", "#dc3545", "#ffc107"],
                        borderWidth: 1
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: "bottom" }
                    }
                }
            });
        };
    </script>';

    echo $OUTPUT->footer();
    die();
}

// ================================================================
// TẦNG 2: XEM DANH SÁCH LỊCH SỬ CỦA 1 SINH VIÊN
// ================================================================
if ($user_id > 0) {
    echo $OUTPUT->header();
    $user = $DB->get_record('user', ['id' => $user_id]);
    $fullname = $user->lastname . ' ' . $user->firstname;
    
    echo '<a href="view.php'.($course_id > 0 ? '?courseid='.$course_id : '').'" class="btn btn-secondary mb-3"> Quay lại Tổng quan Lớp</a>';
    echo '<div class="card shadow-sm">';
    echo '<div class="card-header bg-success text-white"><h5 class="mb-0">Lịch sử làm bài của: ' . htmlspecialchars($fullname) . '</h5></div>';
    echo '<div class="card-body">';

    $sql_user_sessions = "SELECT s.id AS sessionid, s.timestart, c.shortname AS coursename
                          FROM {local_mmla_session} s
                          JOIN {course} c ON s.courseid = c.id
                          WHERE s.userid = :userid
                          AND (
                              EXISTS (SELECT 1 FROM {local_mmla_multimodal} m WHERE m.sessionid = s.id)
                              OR EXISTS (SELECT 1 FROM {local_mmla_srl} sr WHERE sr.sessionid = s.id)
                              OR EXISTS (SELECT 1 FROM {local_mmla_coding} cd WHERE cd.sessionid = s.id)
                          )
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
// TẦNG 1: TỔNG QUAN DANH SÁCH SINH VIÊN
// ================================================================
echo $OUTPUT->header();

echo '<div class="card shadow-sm">';
echo '<div class="card-header bg-primary text-white"><h5 class="mb-0">Tổng quan Sinh viên</h5></div>';
echo '<div class="card-body">';

$params = [];
$where_conditions = [
    "(EXISTS (SELECT 1 FROM {local_mmla_multimodal} m WHERE m.sessionid = s.id)
    OR EXISTS (SELECT 1 FROM {local_mmla_srl} sr WHERE sr.sessionid = s.id)
    OR EXISTS (SELECT 1 FROM {local_mmla_coding} cd WHERE cd.sessionid = s.id))"
];

if ($course_id > 0) {
    $where_conditions[] = "s.courseid = :courseid";
    $params['courseid'] = $course_id;
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

$sql_students = "SELECT u.id AS userid, u.firstname, u.lastname, u.email, 
                        COUNT(s.id) AS total_sessions, 
                        MAX(s.timestart) AS last_active
                 FROM {local_mmla_session} s
                 JOIN {user} u ON s.userid = u.id
                 $where_sql
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