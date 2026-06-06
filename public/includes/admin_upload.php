<?php
// Called via AJAX or direct POST from admin
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/upload_helpers.php';

header('Content-Type: application/json');

$uploadsDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. File upload
if ($action === 'upload') {
    if (!isset($_FILES['file'])) {
        // Чаще всего: файл превысил post_max_size — тогда $_FILES вообще пуст
        echo json_encode(['error' => 'Файл не получен. Возможно, он слишком большой (лимит сервера ' . ini_get('post_max_size') . ').']); exit;
    }
    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $msg = 'Файл слишком большой. Максимум — ' . ini_get('upload_max_filesize') . '.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $msg = 'Файл загрузился не полностью. Повторите попытку.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $msg = 'Файл не выбран.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                $msg = 'Сервер не может сохранить файл (нет прав на запись).';
                break;
            default:
                $msg = 'Ошибка загрузки (код ' . $file['error'] . ').';
        }
        echo json_encode(['error' => $msg]); exit;
    }
    // Определяем реальный MIME по содержимому, а не по тому, что прислал браузер
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('getimagesize')) {
        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? '';
    }
    if (!isset($extMap[$mime])) {
        echo json_encode(['error' => 'Неподдерживаемый формат' . ($mime ? ' (' . $mime . ')' : '') . '. Только JPEG, PNG, GIF, WebP.']); exit;
    }
    $name = 'img_' . time() . '_' . mt_rand(1000,9999) . '.' . $extMap[$mime];
    if (move_uploaded_file($file['tmp_name'], $uploadsDir . $name)) {
        echo json_encode(['ok' => true, 'path' => 'uploads/' . $name]);
    } else {
        echo json_encode(['error' => 'Не удалось сохранить файл на сервере.']);
    }
    exit;
}

// 2. URL download
if ($action === 'fetch_url') {
    $url = trim($_POST['url'] ?? '');
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'Неверный URL']); exit;
    }
    $ctx = stream_context_create(['http'=>['timeout'=>10,'user_agent'=>'Mozilla/5.0']]);
    $data = @file_get_contents($url, false, $ctx);
    if (!$data) {
        echo json_encode(['error' => 'Не удалось загрузить изображение по URL']); exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($data);
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!isset($extMap[$mime])) {
        echo json_encode(['error' => 'URL не содержит изображение']); exit;
    }
    $name = 'img_' . time() . '_' . mt_rand(1000,9999) . '.' . $extMap[$mime];
    file_put_contents($uploadsDir . $name, $data);
    echo json_encode(['ok' => true, 'path' => 'uploads/' . $name]);
    exit;
}

// 3. Delete
if ($action === 'delete') {
    $path = trim($_POST['path'] ?? '');
    // Удаляем файл, только если он наш и больше нигде в БД не используется
    delete_upload_if_unused($conn, $path);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);