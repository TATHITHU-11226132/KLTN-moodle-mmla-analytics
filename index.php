<?php
// 1. Gọi file cấu hình lõi của Moodle (Bắt buộc)
require_once('../../config.php');

// 2. Bắt buộc người dùng phải đăng nhập mới được xem
require_login();

// 3. Thiết lập thông tin cơ bản cho trang web
$url = new moodle_url('/local/mmla_analytics/index.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance()); // Quyền truy cập toàn hệ thống
$PAGE->set_title('MMLA Dashboard');             // Tên hiển thị trên tab trình duyệt
$PAGE->set_heading('Trạm Phân Tích MMLA');      // Tiêu đề lớn trên trang

// 4. Bắt đầu in giao diện (Header)
echo $OUTPUT->header();

echo "<h2>Danh sách Phiên học gần đây</h2>";

// Gọi đối tượng Database của Moodle để tương tác
global $DB;

// Lấy toàn bộ dữ liệu từ bảng local_mmla_session
$sql = "SELECT s.* FROM {local_mmla_session} s 
        WHERE EXISTS (SELECT 1 FROM {local_mmla_multimodal} m WHERE m.sessionid = s.id)
           OR EXISTS (SELECT 1 FROM {local_mmla_srl} sr WHERE sr.sessionid = s.id)
           OR EXISTS (SELECT 1 FROM {local_mmla_coding} cd WHERE cd.sessionid = s.id)
        ORDER BY s.id DESC";
$sessions = $DB->get_records_sql($sql);

// Kiểm tra xem có dữ liệu không và in ra dạng bảng HTML
if ($sessions) {
    echo "<table class='admintable generaltable'>";
    // Thêm cột "Hành động" vào Header của bảng
    echo "<thead><tr><th>Session ID</th><th>User ID</th><th>Course ID</th><th>Thời gian bắt đầu</th><th>Hành động</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($sessions as $session) {
        // Hàm userdate() giúp chuyển đổi dãy số giây thành ngày giờ hiển thị đẹp mắt
        $time_format = userdate($session->timestart); 
        
        // Tạo đường dẫn động trỏ sang trang view.php kèm theo ID của phiên học
       $detail_url = new moodle_url('/local/mmla_analytics/view.php', array('session' => $session->id));
        
        // ĐÃ FIX LỖI: Sử dụng ->out() để ép Moodle in ra đường link hoàn chỉnh có chứa tham số ?id=...
        $btn_view = "<a href='" . $detail_url->out() . "' class='btn btn-primary btn-sm'>Xem chi tiết</a>";
        
        echo "<tr>
                <td>{$session->id}</td>
                <td>{$session->userid}</td>
                <td>{$session->courseid}</td>
                <td>{$time_format}</td>
                <td>{$btn_view}</td>
              </tr>";
    }
    echo "</tbody></table>";
} else {
    // Nếu bảng trống thì báo dòng chữ này
    echo "<div class='alert alert-info'>Chưa có phiên học nào được ghi nhận. Hệ thống đang chờ dữ liệu...</div>";
}

// 5. In phần chân trang (Footer)
echo $OUTPUT->footer();