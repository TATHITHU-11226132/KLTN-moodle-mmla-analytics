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
// TỪ ĐIỂN DỊCH TỪ KHÓA SANG CÂU VĂN (RULE-BASED DICTIONARY)
// ================================================================
$emotion_dict = array(
    'neutral'   => 'Sinh viên đang giữ trạng thái tập trung, nét mặt bình thản.',
    'happy'     => 'Sinh viên đang mỉm cười, có vẻ thoải mái với tiến độ làm bài.',
    'sad'       => 'Sinh viên đang nhăn nhó, buồn bã, có thể đang gặp khó khăn.',
    'angry'     => 'Sinh viên đang thể hiện sự bực tức, nhíu mày mạnh, căng thẳng.',
    'fear'      => 'Sinh viên đang bối rối, lo lắng trước màn hình.',
    'disgusted' => 'Sinh viên đang có biểu cảm khó chịu.',
    'surprised' => 'Sinh viên đang tỏ vẻ ngạc nhiên, bất ngờ.',
    'NoFace'    => 'Sinh viên đang mất dấu khuôn mặt, có thể đã cúi xuống hoặc che camera.'
);

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
    
    // Bộ đếm từ khóa chuẩn mới
    $emotion_counts = [
        'neutral' => 0, 'happy' => 0, 'sad' => 0, 'angry' => 0, 
        'fear' => 0, 'disgusted' => 0, 'surprised' => 0, 'NoFace' => 0
    ];
    
    $example_pr = "Chưa có dữ liệu";
    $example_ps = "Chưa có dữ liệu";
    $appraisal_data = null;

    // Phân tích Cảm xúc 
    if ($emotions) {
        foreach ($emotions as $e) {
            $timeline[] = ['time' => $e->timecreated, 'type' => 'emotion', 'data' => $e->emotion];
            
            // Đếm các từ khóa hợp lệ có trong từ điển
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
    
    // Phân tích SRL và bóc tách dữ liệu đặc biệt
    if ($srl_logs) {
        foreach ($srl_logs as $s) {
            $srl_data = json_decode($s->action);
            if (json_last_error() === JSON_ERROR_NONE && is_object($srl_data)) {
                $srl_type = isset($srl_data->type) ? $srl_data->type : 'UNK';
                $srl_act = isset($srl_data->act) ? $srl_data->act : 'Unknown';
            } else {
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

    // 3. Biểu đồ cảm xúc (Gom nhóm dữ liệu)
    $chart_neutral = $emotion_counts['neutral'];
    $chart_positive = $emotion_counts['happy'] + $emotion_counts['surprised'];
    $chart_negative = $emotion_counts['sad'] + $emotion_counts['angry'] + $emotion_counts['fear'] + $emotion_counts['disgusted'];

    echo '<div class="card shadow-sm mb-4"><div class="card-header bg-info text-white"><h5 class="mb-0">Biểu đồ trạng thái Tâm lý</h5></div><div class="card-body"><canvas id="emotionChart" width="400" height="300"></canvas>';
    
    if ($chart_neutral == 0 && $chart_positive == 0 && $chart_negative == 0) {
        echo '<p class="text-center text-muted mt-2"><small>Chưa đủ dữ liệu từ khóa cảm xúc mới để hiển thị biểu đồ.</small></p>';
    }
    
    echo '</div></div>';
    echo '</div>'; // Hết cột trái

    // ================================================================
    // CỘT PHẢI: TRẠM RADAR GIÁM SÁT HÀNH VI (TIMELINE PROCESS MINING)
    // ================================================================
    echo '<div class="col-md-7">';
    
    // Nhúng Style CSS cao cấp cho Trục thời gian (Timeline Axis) và hiệu ứng Cụm hành vi (Clusters)
    echo '<style>
        .mmla-radar-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid #eef2f5;
        }
        .mmla-timeline {
            position: relative;
            padding-left: 35px;
            margin-left: 15px;
            border-left: 3px solid #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 24px;
            transition: all 0.2s ease-in-out;
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        /* Nốt thắt thời gian trên trục radar */
        .timeline-dot {
            position: absolute;
            left: -46px;
            top: 4px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ffffff;
            border: 4px solid #6c757d;
            z-index: 3;
            box-shadow: 0 0 0 3px #ffffff;
        }
        /* Thẻ nội dung hành vi phân lớp */
        .timeline-card {
            border: 1px solid #eef2f5;
            border-radius: 8px;
            padding: 14px 16px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.01);
        }
        .timeline-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .timeline-time {
            font-family: "Courier New", Courier, monospace;
            font-weight: 700;
            color: #495057;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        /* PHÂN CỤM THỊ GIÁC THEO TRẠNG THÁI (VISUAL CLUSTERING) */
        /* 1. Cụm Cảm xúc Bình thản / Tập trung */
        .cluster-emotion-neutral { border-left: 5px solid #0d6efd !important; background-color: #f8fafd; }
        .cluster-emotion-neutral .timeline-dot { border-color: #0d6efd; background-color: #0d6efd; }
        
        /* 2. Cụm Cảm xúc Tích cực / Thoải mái */
        .cluster-emotion-positive { border-left: 5px solid #198754 !important; background-color: #f4fbf7; }
        .cluster-emotion-positive .timeline-dot { border-color: #198754; background-color: #198754; }
        
        /* 3. Cụm Cảm xúc Tiêu cực / Bối rối / Căng thẳng */
        .cluster-emotion-negative { border-left: 5px solid #fd7e14 !important; background-color: #fffaf5; }
        .cluster-emotion-negative .timeline-dot { border-color: #fd7e14; background-color: #fd7e14; }
        
        /* 4. Cảnh báo mất dấu khuôn mặt (Ghost state) */
        .cluster-noface { border-left: 5px dashed #dc3545 !important; background-color: #fff5f5; }
        .cluster-noface .timeline-dot { border-color: #dc3545; background-color: #ffffff; animation: pulse-red 2s infinite; }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        /* 5. Cụm Tác vụ Gõ code tích cực */
        .cluster-code-pc { border-left: 5px solid #6c757d !important; background-color: #f8f9fa; }
        .cluster-code-pc .timeline-dot { border-color: #6c757d; background-color: #ffffff; }
        
        /* 6. Cụm Lỗi Biên dịch chương trình (Bế tắc) */
        .cluster-code-error { border-left: 5px solid #dc3545 !important; background-color: #fff5f5; }
        .cluster-code-error .timeline-dot { border-color: #dc3545; background-color: #dc3545; }
        
        /* 7. Cụm Biên dịch thành công */
        .cluster-code-success { border-left: 5px solid #198754 !important; background-color: #f0fdf4; }
        .cluster-code-success .timeline-dot { border-color: #198754; background-color: #198754; }
        
        /* 8. Cụm Kích hoạt trợ giúp Giám sát Siêu nhận thức (SRL Scaffold) */
        .cluster-srl { border-left: 5px solid #6f42c1 !important; background-color: #faf5ff; }
        .cluster-srl .timeline-dot { border-color: #6f42c1; background-color: #6f42c1; }
    </style>';

    echo '<div class="card mmla-radar-container mb-4">';
    echo '<div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">';
    echo '<h5 class="mb-0">🎯 Bản đồ Luồng hành vi thời gian thực</h5>';
    echo '<span class="badge bg-secondary">MMLA Process Mining</span>';
    echo '</div>';
    
    echo '<div class="card-body" style="background: #ffffff; max-height: 800px; overflow-y: auto; padding: 25px 20px;">';
    
    if (empty($timeline)) {
        echo '<div class="alert alert-warning border-0 shadow-sm text-center">Chưa có chuỗi hành vi tương tác nào được ghi nhận từ hệ thống máy trạm.</div>';
    } else {
        // Khởi tạo container của trục thời gian dọc
        echo '<div class="mmla-timeline">';
        
        foreach ($timeline as $event) {
            $time_str = userdate($event['time'], '%H:%M:%S');
            $cluster_class = '';
            $content_html = '';
            
            // XỬ LÝ KHỐI SỰ KIỆN: CẢM XÚC (VISION)
            if ($event['type'] == 'emotion') {
                // ĐÃ SỬA LỖI: Chuẩn hóa chữ thường để bộ lọc ngoại lệ hoạt động chính xác tuyệt đối
                $emotion_key = strtolower($event['data']);
                if (in_array($emotion_key, array('focused', 'confused', 'happy'))) {
                    continue; 
                }

                // Kiểm tra trạng thái mất dấu khuôn mặt
                if ($event['data'] === 'NoFace') {
                    $cluster_class = 'cluster-noface';
                    $content_html = '<div class="timeline-meta">
                                        <span class="timeline-time">🕒 ' . $time_str . '</span>
                                        <span class="badge bg-danger text-white">⚠️ SECURITY ALERT</span>
                                     </div>
                                     <div class="d-flex align-items-center text-danger font-weight-bold" style="font-size: 14px;">
                                        <span style="font-size: 20px; margin-right: 8px;">👻</span>
                                        <span>Không tìm thấy khuôn mặt sinh viên (Rời vị trí hoặc che camera)</span>
                                     </div>';
                } else {
                    // Phân nhóm cảm xúc cục bộ để gán màu nền cụm trực quan
                    if (in_array($emotion_key, array('happy', 'surprised'))) {
                        $cluster_class = 'cluster-emotion-positive';
                        $badge_element = '<span class="badge bg-success">Tâm lý: Tích cực</span>';
                    } elseif (in_array($emotion_key, array('sad', 'angry', 'fear', 'disgusted'))) {
                        $cluster_class = 'cluster-emotion-negative';
                        $badge_element = '<span class="badge bg-warning text-dark">Tâm lý: Căng thẳng/Bối rối</span>';
                    } else {
                        $cluster_class = 'cluster-emotion-neutral';
                        $badge_element = '<span class="badge bg-primary">Tâm lý: Tập trung</span>';
                    }

                    $display_text = isset($emotion_dict[$event['data']]) ? $emotion_dict[$event['data']] : $event['data'];
                    
                    $content_html = '<div class="timeline-meta">
                                        <span class="timeline-time">🕒 ' . $time_str . '</span>
                                        ' . $badge_element . '
                                     </div>
                                     <div style="display: flex; align-items: flex-start; margin-top: 4px;">
                                        <span style="font-size: 18px; margin-right: 8px; line-height: 1.4;">🤖</span>
                                        <em class="text-dark" style="font-size: 14px; line-height: 1.5;">' . htmlspecialchars($display_text) . '</em>
                                     </div>';
                }
            
            // XỬ LÝ KHỐI SỰ KIỆN: TỰ ĐIỀU CHỈNH HỌC TẬP (SRL SCAFFOLD)
            } elseif ($event['type'] == 'srl') {
                $cluster_class = 'cluster-srl';
                
                $badge_class = 'bg-secondary';
                $srl_title = 'Tương tác Siêu nhận thức';
                if ($event['srl_type'] == 'UGPP') { $badge_class = 'bg-purple text-white'; $srl_title = 'SRL: Tiếp nhận gợi ý'; }
                if ($event['srl_type'] == 'UMP') { $badge_class = 'bg-info text-dark'; $srl_title = 'SRL: Giám sát tiến độ'; }
                if ($event['srl_type'] == 'PR' || $event['srl_type'] == 'PS') { $badge_class = 'bg-teal text-white'; $srl_title = 'SRL: Phân tích ví dụ'; }
                if ($event['srl_type'] == 'USEP') { $badge_class = 'bg-dark text-white'; $srl_title = 'SRL: Tự đánh giá'; }
                
                $content_html = '<div class="timeline-meta">
                                    <span class="timeline-time">🕒 ' . $time_str . '</span>
                                    <span class="badge ' . $badge_class . '" style="font-size:11px; padding: 4px 8px;">' . $srl_title . '</span>
                                 </div>
                                 <div class="mt-1" style="font-size: 14px; color: #553c9a; font-weight: 500;">
                                    🧩 <span class="badge bg-light text-dark border">[' . htmlspecialchars($event['srl_type']) . ']</span> ' . htmlspecialchars($event['act']) . '
                                 </div>';
            
            // XỬ LÝ KHỐI SỰ KIỆN: LẬP TRÌNH (CODING LOGIC)
            } elseif ($event['type'] == 'code') {
                $is_pc = ($event['status'] === 'PC');
                $is_err = ($event['status'] === 'Error');
                
                if ($is_pc) {
                    $cluster_class = 'cluster-code-pc';
                    $badge_code = '<span class="badge bg-secondary">Hành vi: Gõ code</span>';
                    $status_body = '<div class="text-muted" style="font-size:13px;"><i class="fa fa-pencil"></i> Sinh viên đang tích cực chỉnh sửa cấu trúc mã nguồn trên IDE...</div>';
                } elseif ($is_err) {
                    $cluster_class = 'cluster-code-error';
                    $badge_code = '<span class="badge bg-danger">Trạng thái: Lỗi Biên dịch ❌</span>';
                    $status_body = '<pre style="background: #272822; color: #f8f8f2; padding: 10px; margin-top: 6px; border-radius: 6px; font-size: 12px; font-family: Consolas, Monaco, monospace; white-space: pre-wrap; overflow-x: auto;">' . htmlspecialchars($event['code']) . '</pre>
                                    <div class="text-danger mt-1 font-weight-bold" style="font-size: 12px; background: #fff5f5; padding: 6px; border-radius: 4px; border: 1px dashed #f5c6cb;">
                                        🚨 Error Log: ' . htmlspecialchars($event['error']) . '
                                    </div>';
                } else {
                    $cluster_class = 'cluster-code-success';
                    $badge_code = '<span class="badge bg-success">Trạng thái: Chạy thành công  </span>';
                    $status_body = '<pre style="background: #272822; color: #a6e22e; padding: 10px; margin-top: 6px; border-radius: 6px; font-size: 12px; font-family: Consolas, Monaco, monospace; white-space: pre-wrap; overflow-x: auto;">' . htmlspecialchars($event['code']) . '</pre>';
                }
                
                $content_html = '<div class="timeline-meta">
                                    <span class="timeline-time">🕒 ' . $time_str . '</span>
                                    ' . $badge_code . '
                                 </div>
                                 <div class="mt-1">' . $status_body . '</div>';
            }
            
            // Tiến hành render thẻ phần tử tương tác ra giao diện dòng thời gian của máy chủ
            echo '<div class="timeline-item">';
            echo '  <div class="timeline-dot"></div>';
            echo '  <div class="timeline-card ' . $cluster_class . '">';
            echo '      ' . $content_html;
            echo '  </div>';
            echo '</div>';
        }
        
        echo '</div>'; // Đóng mmla-timeline
    }
    echo '</div></div></div></div>'; // Đóng toàn bộ thẻ bọc ngoài của Cột phải

    // Chart.js Script
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '<script>
        window.onload = function() {
            var ctx = document.getElementById("emotionChart").getContext("2d");
            new Chart(ctx, {
                type: "doughnut",
                data: {
                    labels: ["Tập trung/Bình thản", "Khó khăn/Căng thẳng", "Tích cực/Thoải mái"],
                    datasets: [{
                        data: ['. $chart_neutral .', '. $chart_negative .', '. $chart_positive .'],
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