<?php
// 1. Gọi cấu hình lõi Moodle (Bắt buộc để sử dụng các hàm của Moodle)
require_once('../../config.php');

// 2. Định dạng dữ liệu trả về cho Python/Ứng dụng ngoài là chuẩn JSON
header('Content-Type: application/json; charset=utf-8');

// 3. Nhận dữ liệu đầu vào một cách an toàn
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$type      = optional_param('type', '', PARAM_TEXT);  // Nhận: 'emotion' hoặc 'coding'
$data      = optional_param('data', '', PARAM_RAW);   // Dùng RAW để giữ nguyên các ký tự đặc biệt của Code hoặc JSON
$token     = optional_param('token', '', PARAM_RAW);  // Dùng RAW để giữ lại dấu gạch dưới "_" trong Token

// 4. Lớp bảo vệ (Security Token)
$secret_token = 'sadie_mmla_2026'; 

// Kiểm tra Token
if ($token !== $secret_token) {
    die(json_encode([
        'status'  => 'error', 
        'message' => 'Từ chối truy cập: Sai Token bảo mật!'
    ]));
}

// Kiểm tra dữ liệu bắt buộc
if ($sessionid == 0 || empty($type) || empty($data)) {
    die(json_encode([
        'status'  => 'error', 
        'message' => 'Lỗi: Thiếu dữ liệu bắt buộc (sessionid, type, hoặc data)!'
    ]));
}

// 5. Kết nối Database
global $DB;

// 6. Phân loại và cất vào Database
try {
    if ($type === 'emotion') {
        // Chuẩn bị gói dữ liệu cho bảng Multimodal (Cảm xúc)
        $record = new stdClass();
        $record->sessionid = $sessionid;
        $record->emotion   = $data;
        
        $id = $DB->insert_record('local_mmla_multimodal', $record);
        
    } elseif ($type === 'coding') {
        // Chuẩn bị gói dữ liệu cho bảng Coding (Mã nguồn)
        $record = new stdClass();
        $record->sessionid    = $sessionid;
        $record->code_content = $data;
        
        $id = $DB->insert_record('local_mmla_coding', $record);
        
    } else {
        die(json_encode([
            'status'  => 'error', 
            'message' => 'Lỗi: Loại dữ liệu không hợp lệ (Chỉ nhận emotion hoặc coding)!'
        ]));
    }
    
    // Báo cáo thành công về cho bên gửi
    echo json_encode([
        'status'      => 'success', 
        'message'     => 'Đã lưu thành công!', 
        'inserted_id' => $id
    ]);
    
} catch (Exception $e) {
    // Nếu có lỗi ở tầng Database (ví dụ: Session ID không tồn tại gây lỗi khóa ngoại)
    echo json_encode([
        'status'  => 'error', 
        'message' => 'Lỗi Database: ' . $e->getMessage()
    ]);
}