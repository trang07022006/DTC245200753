<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Endpoint phục vụ ảnh an toàn có kiểm soát quyền truy cập (view.php)
 * Chặn IDOR / Truy cập trái phép ảnh riêng tư
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$photoId = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

if ($photoId <= 0) {
    http_response_code(400);
    exit('Yêu cầu không hợp lệ: Thiếu ID ảnh.');
}

try {
    $pdo = getDatabaseConnection();

    // 1. Truy vấn thông tin ảnh từ CSDL
    $stmt = $pdo->prepare("SELECT id, album_id, stored_filename, mime_type, size_bytes FROM photos WHERE id = :id");
    $stmt->execute([':id' => $photoId]);
    $photo = $stmt->fetch();

    if (!$photo) {
        http_response_code(404);
        exit('Không tìm thấy bức ảnh yêu cầu.');
    }

    // 2. Kiểm tra quyền truy cập (Access Control)
    $authorized = false;

    // A. Quản trị viên đã đăng nhập -> Có quyền xem tất cả ảnh
    if (isLoggedIn()) {
        $authorized = true;
    }
    // B. Khách chưa đăng nhập nhưng có Token chia sẻ hợp lệ và chưa bị thu hồi
    elseif (!empty($token)) {
        $tokenHash = hash('sha256', $token);
        $shareStmt = $pdo->prepare("
            SELECT id FROM share_links
            WHERE token_hash = :hash
              AND revoked_at IS NULL
              AND (
                  (target_type = 'photo' AND photo_id = :pid)
                  OR
                  (target_type = 'album' AND album_id = :aid)
              )
            LIMIT 1
        ");
        $shareStmt->execute([
            ':hash' => $tokenHash,
            ':pid'  => $photo['id'],
            ':aid'  => $photo['album_id']
        ]);
        if ($shareStmt->fetch()) {
            $authorized = true;
        }
    }

    if (!$authorized) {
        http_response_code(403);
        exit('Từ chối truy cập (403 Forbidden): Ảnh riêng tư hoặc liên kết chia sẻ không hợp lệ.');
    }

    // 3. Kiểm tra file vật lý trên đĩa
    $filePath = __DIR__ . '/uploads/' . $photo['stored_filename'];

    // Chống Path Traversal: Đảm bảo đường dẫn thực tế nằm bên trong thư mục uploads
    $realPath = realpath($filePath);
    $uploadDir = realpath(__DIR__ . '/uploads');

    if ($realPath === false || strpos($realPath, $uploadDir) !== 0 || !file_exists($realPath)) {
        http_response_code(404);
        exit('File ảnh vật lý không tồn tại trên hệ thống lưu trữ.');
    }

    // 4. Phục vụ dữ liệu ảnh với HTTP Headers chuẩn
    header('Content-Type: ' . $photo['mime_type']);
    header('Content-Length: ' . filesize($realPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400');
    header('Content-Disposition: inline; filename="' . rawurlencode($photo['stored_filename']) . '"');

    readfile($realPath);
    exit;

} catch (Exception $e) {
    error_log("[View Photo Error] " . $e->getMessage());
    http_response_code(500);
    exit('Lỗi máy chủ khi đọc file ảnh.');
}
