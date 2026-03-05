<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        throw new Exception("Yalnız POST.");
    if (!isset($_FILES['file']))
        throw new Exception("Fayl seçilməyib.");
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK)
        throw new Exception("Upload Xətası Kod: " . $_FILES['file']['error']);

    $file = $_FILES['file'];

    // Təhlükəsizlik: Fayl uzantısını yoxla
    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts)) {
        throw new Exception("Təhlükəsizlik xətası: Bu fayl növünə icazə verilmir! (" . $ext . ")");
    }

    // MIME type check (Əlavə qat)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // PHP/EXE fayllarının MIME-ni yoxla
    if (strpos($mime, 'php') !== false || strpos($mime, 'javascript') !== false) {
        throw new Exception("Təhlükəli fayl məzmunu aşkarlandı.");
    }

    // Path inside student-information-system
    $targetDir = __DIR__ . "/../uploads/chat_files/";

    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0777, true))
            throw new Exception("Qovluq yaradıla bilmədi.");
    }

    $fileName = uniqid("f_") . "." . $ext;
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];

        // Get script directory to build the URL properly
        $scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $baseUrl = $protocol . "://" . $host . $scriptPath;
        // Base URL points to /distant/api/, so we go up one level to /distant/
        $distantUrl = dirname($baseUrl);
        $url = $distantUrl . "/uploads/chat_files/" . $fileName;

        echo json_encode([
            "success" => true,
            "url" => $url,
            "fileName" => $file['name']
        ]);
    } else {
        throw new Exception("Fayl köçürülə bilmədi.");
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>