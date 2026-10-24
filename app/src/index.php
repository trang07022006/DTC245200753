<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Trang chủ tổng quan & Kiểm tra nền tảng hệ thống (index.php)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$dbStatus = 'Đang kiểm tra...';
$dbConnected = false;
$stats = ['albums' => 0, 'photos' => 0, 'categories' => 0, 'shares' => 0];

try {
    $pdo = getDatabaseConnection();
    $dbConnected = true;
    $dbStatus = 'Kết nối thành công (MySQL 8.0)';

    $stats['albums'] = (int)$pdo->query("SELECT COUNT(*) FROM `albums`")->fetchColumn();
    $stats['photos'] = (int)$pdo->query("SELECT COUNT(*) FROM `photos`")->fetchColumn();
    $stats['categories'] = (int)$pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();
    $stats['shares'] = (int)$pdo->query("SELECT COUNT(*) FROM `share_links` WHERE `revoked_at` IS NULL")->fetchColumn();
} catch (Exception $e) {
    $dbStatus = 'Lỗi kết nối CSDL: ' . htmlspecialchars($e->getMessage());
}

$pageTitle = 'Trang Chủ Tổng Quan';
require_once __DIR__ . '/includes/header.php';
?>

<div style="text-align: center; margin: 2rem 0 3rem 0;">
    <h1 style="font-size: 2.4rem; font-weight: 800; background: linear-gradient(to right, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem;">
        Hệ Thống Thư Viện Ảnh Gallery
    </h1>
    <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 650px; margin: 0 auto;">
        Đề 31: Quản lý album, phân loại, tải ảnh lên an toàn đa lớp và chia sẻ qua Token ngẫu nhiên.
    </p>
    <div style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
        <a href="/gallery.php" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-size: 1rem;">
            📸 Khám Phá Thư Viện Ảnh
        </a>
        <?php if (!isLoggedIn()): ?>
            <a href="/login.php" class="btn btn-secondary" style="padding: 0.75rem 1.5rem; font-size: 1rem;">
                🔐 Đăng Nhập Quản Trị
            </a>
        <?php else: ?>
            <a href="/upload.php" class="btn btn-secondary" style="padding: 0.75rem 1.5rem; font-size: 1rem;">
                📤 Tải Ảnh Lên Ngay
            </a>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
    <!-- Thông tin Sinh viên -->
    <div class="card">
        <h2 style="font-size: 1.2rem; color: #38bdf8; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            👤 Thông Tin Sinh Viên
        </h2>
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.95rem;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Họ và tên:</span>
                <strong>Lê Minh Trang</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Mã sinh viên:</span>
                <span class="user-tag">DTC245200753</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Lớp:</span>
                <strong>CNTT K23M</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Học phần:</span>
                <span>Triển khai & Quản trị Hệ thống</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Đề tài tốt nghiệp:</span>
                <span style="color: #38bdf8; font-weight: 600;">Đề 31 - Gallery</span>
            </div>
        </div>
    </div>

    <!-- Trạng thái Nền tảng -->
    <div class="card">
        <h2 style="font-size: 1.2rem; color: #38bdf8; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            ⚙️ Trạng Thái Hạ Tầng
        </h2>
        <div style="display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.95rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: var(--text-muted);">Reverse Proxy:</span>
                <span style="color: #34d399; font-weight: 600;">● Nginx (Port 80)</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: var(--text-muted);">Backend Server:</span>
                <span style="color: #34d399; font-weight: 600;">● PHP <?= phpversion() ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: var(--text-muted);">Cơ sở dữ liệu:</span>
                <span style="color: <?= $dbConnected ? '#34d399' : '#f87171' ?>; font-weight: 600;">
                    <?= $dbConnected ? '● MySQL 8.0' : '○ Lỗi kết nối' ?>
                </span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: var(--text-muted);">Thư mục Uploads:</span>
                <span style="color: #38bdf8; font-weight: 600;">Ghi an toàn (755)</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: var(--text-muted);">Chặn ảnh thô Nginx:</span>
                <span style="color: #34d399; font-weight: 600;">● 403 Forbidden Bật</span>
            </div>
        </div>
    </div>

    <!-- Dữ liệu Gallery hiện tại -->
    <div class="card">
        <h2 style="font-size: 1.2rem; color: #38bdf8; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
            📊 Thống Kê Thư Viện
        </h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; text-align: center; margin-bottom: 1rem;">
            <div style="background: rgba(15, 23, 42, 0.6); padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border);">
                <div style="font-size: 1.6rem; font-weight: 700; color: #38bdf8;"><?= $stats['photos'] ?></div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">Bức ảnh</div>
            </div>
            <div style="background: rgba(15, 23, 42, 0.6); padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border);">
                <div style="font-size: 1.6rem; font-weight: 700; color: #34d399;"><?= $stats['albums'] ?></div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">Album</div>
            </div>
            <div style="background: rgba(15, 23, 42, 0.6); padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border);">
                <div style="font-size: 1.6rem; font-weight: 700; color: #fbbf24;"><?= $stats['categories'] ?></div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">Danh mục</div>
            </div>
            <div style="background: rgba(15, 23, 42, 0.6); padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border);">
                <div style="font-size: 1.6rem; font-weight: 700; color: #a78bfa;"><?= $stats['shares'] ?></div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">Link Chia sẻ</div>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/health.php" target="_blank" class="btn btn-secondary btn-sm" style="flex: 1;">Health JSON</a>
            <a href="http://localhost:8082" target="_blank" class="btn btn-primary btn-sm" style="flex: 1;">phpMyAdmin</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
