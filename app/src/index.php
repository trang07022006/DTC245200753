<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Trang chủ kiểm tra nền tảng triển khai (Milestone 1)
 */

require_once __DIR__ . '/config/database.php';

$dbStatus = 'Đang kiểm tra...';
$dbConnected = false;
$stats = ['albums' => 0, 'photos' => 0, 'categories' => 0];

try {
    $pdo = getDatabaseConnection();
    $dbConnected = true;
    $dbStatus = 'Kết nối thành công (MySQL 8.0)';

    // Thống kê sơ bộ
    $stats['albums'] = (int)$pdo->query("SELECT COUNT(*) FROM `albums`")->fetchColumn();
    $stats['photos'] = (int)$pdo->query("SELECT COUNT(*) FROM `photos`")->fetchColumn();
    $stats['categories'] = (int)$pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();
} catch (Exception $e) {
    $dbStatus = 'Lỗi kết nối CSDL: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đề 31 - Hệ thống Thư viện Ảnh Gallery | DTC245200753</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --border: #334155;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); min-height: 100vh; display: flex; flex-direction: column; }
        header { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.25rem; font-weight: 700; color: #38bdf8; display: flex; align-items: center; gap: 0.5rem; }
        .badge { background: #0369a1; color: #e0f2fe; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        main { flex: 1; max-width: 1100px; width: 100%; margin: 2rem auto; padding: 0 1rem; }
        .hero { text-align: center; margin-bottom: 2.5rem; }
        .hero h1 { font-size: 2.2rem; margin-bottom: 0.5rem; background: linear-gradient(to right, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { color: var(--text-muted); font-size: 1.05rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3); }
        .card h2 { font-size: 1.2rem; margin-bottom: 1rem; color: #38bdf8; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
        .status-pill { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.85rem; }
        .status-pill.success { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-pill.danger { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .info-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px dashed rgba(255,255,255,0.08); font-size: 0.95rem; }
        .info-label { color: var(--text-muted); }
        .info-value { font-weight: 500; }
        .btn-group { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.65rem 1.25rem; border-radius: 8px; font-weight: 600; text-decoration: none; transition: all 0.2s; font-size: 0.95rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: #334155; color: #f1f5f9; }
        .btn-secondary:hover { background: #475569; }
        footer { border-top: 1px solid var(--border); padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem; }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <span>📷</span> Gallery System - Đề 31
        </div>
        <div class="badge">CNTT K23M</div>
    </header>

    <main>
        <div class="hero">
            <h1>Hệ Thống Thư Viện Ảnh Gallery</h1>
            <p>Học phần: Triển khai và Quản trị Hệ thống Phần mềm</p>
        </div>

        <div class="grid">
            <!-- Thông tin Sinh viên -->
            <div class="card">
                <h2>Sinh viên thực hiện</h2>
                <div class="info-row">
                    <span class="info-label">Họ và tên:</span>
                    <span class="info-value">Lê Minh Trang</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mã sinh viên:</span>
                    <span class="info-value">DTC245200753</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Lớp:</span>
                    <span class="info-value">CNTT K23M</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Đề tài:</span>
                    <span class="info-value">Đề 31 - Thư viện ảnh Gallery</span>
                </div>
            </div>

            <!-- Trạng thái Dịch vụ -->
            <div class="card">
                <h2>Trạng thái Nền tảng (Chặng 1)</h2>
                <div class="info-row">
                    <span class="info-label">Nginx Reverse Proxy:</span>
                    <span class="status-pill success">● Đang hoạt động</span>
                </div>
                <div class="info-row">
                    <span class="info-label">App Container (PHP):</span>
                    <span class="status-pill success">● PHP <?= phpversion() ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cơ sở dữ liệu (MySQL):</span>
                    <span class="status-pill <?= $dbConnected ? 'success' : 'danger' ?>">
                        <?= $dbConnected ? '● Đã kết nối' : '○ Lỗi kết nối' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Thư mục Uploads:</span>
                    <span class="info-value"><?= is_writable(__DIR__ . '/uploads') ? 'Ghi được (755)' : 'Không ghi được' ?></span>
                </div>
            </div>

            <!-- Dữ liệu Database hiện tại -->
            <div class="card">
                <h2>Cơ sở dữ liệu Gallery</h2>
                <div class="info-row">
                    <span class="info-label">Số Album khởi tạo:</span>
                    <span class="info-value"><?= $stats['albums'] ?> album</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Số Danh mục phân loại:</span>
                    <span class="info-value"><?= $stats['categories'] ?> danh mục</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tổng số ảnh hiện có:</span>
                    <span class="info-value"><?= $stats['photos'] ?> ảnh</span>
                </div>
                <div class="btn-group">
                    <a href="/health.php" target="_blank" class="btn btn-secondary">Kiểm tra Health JSON</a>
                    <a href="http://localhost:8082" target="_blank" class="btn btn-primary">Mở phpMyAdmin</a>
                </div>
            </div>
        </div>
    </main>

    <footer>
        Hệ thống Triển khai & Quản trị Hệ thống Phần mềm &bull; Sinh viên Lê Minh Trang - DTC245200753 &bull; 2026
    </footer>
</body>
</html>
