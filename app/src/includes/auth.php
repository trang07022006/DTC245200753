<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Module xác thực, quản lý phiên và bảo vệ CSRF (includes/auth.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    // Cấu hình cookie phiên an toàn (Hardening)
    $cookieParams = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

/**
 * Kiểm tra xem người dùng đã đăng nhập hay chưa
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Yêu cầu quyền đăng nhập; nếu chưa đăng nhập sẽ chuyển hướng về trang login
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Vui lòng đăng nhập để thực hiện chức năng này.');
        header('Location: /login.php');
        exit;
    }
}

/**
 * Sinh mã CSRF token ngẫu nhiên và lưu vào session
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Xác thực CSRF token từ form POST
 */
function verifyCsrf(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Đặt thông báo tạm thời (Flash Message)
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Lấy và xóa thông báo tạm thời
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
