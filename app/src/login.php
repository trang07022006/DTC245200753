<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Đăng nhập Quản trị viên (login.php)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /gallery.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($csrf)) {
        $error = 'Mã bảo mật CSRF không hợp lệ hoặc phiên đã hết hạn. Vui lòng thử lại.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
        } else {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name FROM users WHERE username = :u LIMIT 1");
                $stmt->execute([':u' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Tái tạo Session ID chống Session Fixation (Hardening)
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = (int)$user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];

                    setFlash('success', 'Đăng nhập thành công! Chào mừng ' . htmlspecialchars($user['full_name']) . '.');
                    header('Location: /gallery.php');
                    exit;
                } else {
                    $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
                }
            } catch (Exception $e) {
                error_log("[Login Error] " . $e->getMessage());
                $error = 'Lỗi hệ thống trong quá trình xác thực. Vui lòng thử lại sau.';
            }
        }
    }
}

$pageTitle = 'Đăng nhập Quản trị viên';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 440px; margin: 3rem auto;">
    <div class="card">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔐</div>
            <h1 style="font-size: 1.5rem; color: #38bdf8;">Đăng Nhập Quản Trị</h1>
            <p style="color: var(--text-muted); font-size: 0.85rem;">Hệ thống Thư viện Ảnh Gallery - Đề 31</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                <span>❌</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-group">
                <label class="form-label" for="username">Tên đăng nhập:</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Nhập username..." required autofocus value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Mật khẩu:</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Nhập mật khẩu..." required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem; padding: 0.75rem;">
                Đăng Nhập Hệ Thống
            </button>
        </form>

        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed var(--border); font-size: 0.85rem; color: var(--text-muted); text-align: center;">
            <p>Tài khoản mặc định: <strong>admin</strong> | Mật khẩu: <strong>Admin@123456</strong></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
