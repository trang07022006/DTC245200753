<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Đăng xuất an toàn (logout.php)
 */

require_once __DIR__ . '/includes/auth.php';

// Hủy toàn bộ biến session
$_SESSION = [];

// Xóa session cookie trên trình duyệt
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Bắt đầu session mới chỉ để lưu thông báo
session_start();
session_regenerate_id(true);
setFlash('info', 'Bạn đã đăng xuất khỏi hệ thống thành công.');
header('Location: /login.php');
exit;
