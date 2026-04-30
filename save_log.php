<?php
require_once('../../config.php');

// ================================================================
// ROBUST ERROR LOGGING - GHI LOG MỌI REQUEST VÀO PHP ERROR LOG
// ================================================================
$raw_data = file_get_contents('php://input');
error_log("========== MMLA SAVE_LOG.PHP ==========");
error_log("RAW INPUT: " . $raw_data);

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    die();
}

// require_login(); // Vẫn đang tắt để test từ cổng 5500

header('Content-Type: application/json');
global $DB;

$payload = json_decode($raw_data);

if (!$payload) {
    $error_msg = 'Không nhận được dữ liệu hợp lệ. Raw: ' . $raw_data;
    error_log("MMLA ERROR: " . $error_msg);
    echo json_encode(['status' => 'error', 'message' => $error_msg]);
    die();
}

// ================================================================
// VALIDATE SESSION_ID - KIỂM TRA TRƯỚC KHI INSERT
// ================================================================
if (empty($payload->session_id)) {
    $error_msg = 'LỖI: session_id bị rỗng hoặc null!';
    error_log("MMLA ERROR: " . $error_msg);
    echo json_encode(['status' => 'error', 'message' => $error_msg]);
    die();
}

error_log("MMLA Processing: category=" . $payload->category . ", session_id=" . $payload->session_id);

// ================================================================
// INSERT VỚI TRY-CATCH RIÊNG CHO TỪNG BẢNG
// ================================================================
$insert_id = null;
$success = false;

try {
    if ($payload->category === 'SRL') {
        $record = new stdClass();
        $record->sessionid = $payload->session_id; 
        $record->scaffold_type = isset($payload->scaffold_type) ? $payload->scaffold_type : 'System';
        $record->action = $payload->action;
        $record->timecreated = isset($payload->timecreated) ? $payload->timecreated : time();
        
        error_log("MMLA SRL INSERT: " . print_r($record, true));
        $insert_id = $DB->insert_record('local_mmla_srl', $record);
        $success = true;
        error_log("MMLA SRL SUCCESS: inserted_id=$insert_id");

    } elseif ($payload->category === 'Coding') {
        $record = new stdClass();
        $record->sessionid = $payload->session_id;
        $record->code_content = isset($payload->code_content) ? $payload->code_content : '';
        $record->status = isset($payload->action) ? $payload->action : 'Unknown';
        $record->error_log = isset($payload->error_log) ? $payload->error_log : '';
        $record->timecreated = isset($payload->timecreated) ? $payload->timecreated : time();
        
        error_log("MMLA CODING INSERT: " . print_r($record, true));
        $insert_id = $DB->insert_record('local_mmla_coding', $record);
        $success = true;
        error_log("MMLA CODING SUCCESS: inserted_id=$insert_id");
        
    } elseif ($payload->category === 'Multimodal') {
        $record = new stdClass();
        $record->sessionid = $payload->session_id;
        $record->emotion = isset($payload->action) ? $payload->action : 'Unknown';
        $record->engagement_score = 0.9;
        $record->timestamp = isset($payload->timecreated) ? $payload->timecreated : time();
        
        error_log("MMLA MULTIMODAL INSERT: " . print_r($record, true));
        $insert_id = $DB->insert_record('local_mmla_multimodal', $record);
        $success = true;
        error_log("MMLA MULTIMODAL SUCCESS: inserted_id=$insert_id");
        
    } else {
        $error_msg = 'Category chưa được định nghĩa: ' . $payload->category;
        error_log("MMLA WARNING: " . $error_msg);
        echo json_encode(['status' => 'warning', 'message' => $error_msg]);
        die();
    }

    if ($success) {
        echo json_encode(['status' => 'success', 'inserted_id' => $insert_id]);
    }

} catch (Exception $e) {
    $error_msg = 'MMLA DB Error: ' . $e->getMessage();
    error_log("MMLA EXCEPTION: " . $error_msg);
    error_log("MMLA Stack trace: " . $e->getTraceAsString());
    echo json_encode(['status' => 'error', 'message' => $error_msg]);
} catch (dml_exception $e) {
    // Moodle-specific database exception
    $error_msg = 'MMLA Moodle DB Error: ' . $e->getMessage() . ' | Debug: ' . $e->debuginfo;
    error_log("MMLA DML EXCEPTION: " . $error_msg);
    echo json_encode(['status' => 'error', 'message' => $error_msg]);
}

error_log("========== MMLA SAVE_LOG.PHP END ==========");
?>