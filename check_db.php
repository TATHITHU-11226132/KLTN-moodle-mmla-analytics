<?php
require_once('../../config.php');
require_login();
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/mmla_analytics/check_db.php'));
$PAGE->set_title('Kiểm tra Database ');
$PAGE->set_heading(' Dữ Liệu MMLA');

echo $OUTPUT->header();
global $DB;

echo '<div class="container bg-white p-4 shadow-sm">';
echo '<h3 class="text-primary">1. Dữ liệu bảng: local_mmla_session</h3>';
$sessions = $DB->get_records('local_mmla_session', null, 'id DESC', '*', 0, 5); // Lấy 5 bản ghi mới nhất
if($sessions) {
    echo '<pre style="background:#f4f4f4; padding:10px;">' . print_r($sessions, true) . '</pre>';
} else {
    echo '<div class="alert alert-warning">Bảng trống.</div>';
}

echo '<h3 class="text-success mt-4">2. Dữ liệu bảng: local_mmla_multimodal (Cảm xúc)</h3>';
$emotions = $DB->get_records('local_mmla_multimodal', null, 'id DESC', '*', 0, 5);
if($emotions) {
    echo '<pre style="background:#f4f4f4; padding:10px;">' . print_r($emotions, true) . '</pre>';
} else {
    echo '<div class="alert alert-warning">Bảng trống.</div>';
}

echo '<h3 class="text-danger mt-4">3. Dữ liệu bảng: local_mmla_srl</h3>';
$srl = $DB->get_records('local_mmla_srl', null, 'id DESC', '*', 0, 5);
if($srl) {
    echo '<pre style="background:#f4f4f4; padding:10px;">' . print_r($srl, true) . '</pre>';
} else {
    echo '<div class="alert alert-warning">Bảng trống.</div>';
}
echo '</div>';

echo $OUTPUT->footer();
?>