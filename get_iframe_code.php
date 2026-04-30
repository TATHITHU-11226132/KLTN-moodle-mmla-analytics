<?php
require_once('../../config.php');

$iframe_url = $CFG->wwwroot . '/local/mmla_analytics/ide.html';

$iframe_code = '<iframe src="' . $iframe_url . '" width="100%" height="600px" allow="camera; microphone" style="border: 1px solid #ccc; border-radius: 8px;"></iframe>';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Get Iframe Code</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f7f6; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #333; }
        textarea { width: 100%; height: 100px; font-family: monospace; padding: 10px; border: 1px solid #ccc; border-radius: 4px; resize: vertical; }
        .btn-copy { background: #28a745; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; margin-top: 10px; }
        .btn-copy:hover { background: #218838; }
        .preview { margin-top: 20px; border: 1px dashed #ccc; padding: 10px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📋 Iframe Code Generator</h2>
        
        <label for="code">Mã Iframe (copy đoạn bên dưới):</label>
        <textarea id="code" readonly><?php echo htmlspecialchars($iframe_code); ?></textarea>
        
        <button class="btn-copy" onclick="copyCode()">📋 Copy vào Clipboard</button>
        
        <div class="preview">
            <label>Xem trước (Preview):</label>
            <?php echo $iframe_code; ?>
        </div>
    </div>

    <script>
        function copyCode() {
            let textarea = document.getElementById('code');
            textarea.select();
            document.execCommand('copy');
            alert('Đã copy vào clipboard!');
        }
    </script>
</body>
</html>
