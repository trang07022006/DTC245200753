<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Header giao diện chung (includes/header.php)
 */
require_once __DIR__ . '/auth.php';
$flash = getFlash();
$loggedIn = isLoggedIn();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Gallery System - Đề 31</title>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --primary-light: rgba(59, 130, 246, 0.15);
            --bg-body: #0b1120;
            --bg-surface: #1e293b;
            --bg-card: #162032;
            --border: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); min-height: 100vh; display: flex; flex-direction: column; line-height: 1.5; }

        /* Navigation Bar */
        header { background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0.75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .brand { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; color: #38bdf8; font-weight: 700; font-size: 1.2rem; }
        .nav-links { display: flex; align-items: center; gap: 0.5rem; list-style: none; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; color: var(--text-muted); padding: 0.45rem 0.85rem; border-radius: 6px; font-size: 0.9rem; font-weight: 500; transition: all 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: #ffffff; background: var(--border); }
        .nav-links a.btn-upload { background: var(--primary); color: white; font-weight: 600; }
        .nav-links a.btn-upload:hover { background: var(--primary-hover); }
        .user-tag { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; }

        /* Main Container */
        main { flex: 1; max-width: 1200px; width: 100%; margin: 1.75rem auto; padding: 0 1.25rem; }

        /* Flash Alerts */
        .alert { padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.95rem; }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .alert-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .alert-info { background: rgba(6, 182, 212, 0.15); color: #38bdf8; border: 1px solid rgba(6, 182, 212, 0.3); }

        /* Common Components */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .page-title { font-size: 1.6rem; font-weight: 700; color: #f1f5f9; display: flex; align-items: center; gap: 0.6rem; }
        .card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2); }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.55rem 1.1rem; border-radius: 7px; font-weight: 600; font-size: 0.9rem; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: #334155; color: #f1f5f9; }
        .btn-secondary:hover { background: #475569; }
        .btn-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
        .btn-danger:hover { background: #ef4444; color: white; }
        .btn-warning { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .btn-warning:hover { background: #f59e0b; color: white; }
        .btn-sm { padding: 0.35rem 0.7rem; font-size: 0.8rem; }

        /* Forms */
        .form-group { margin-bottom: 1.2rem; }
        .form-label { display: block; margin-bottom: 0.45rem; font-weight: 500; font-size: 0.9rem; color: #cbd5e1; }
        .form-control { width: 100%; padding: 0.6rem 0.9rem; border-radius: 7px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .form-text { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.3rem; }

        /* Tables */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        th { background: #162032; padding: 0.75rem 1rem; color: #94a3b8; font-weight: 600; border-bottom: 1px solid var(--border); }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid rgba(51, 65, 85, 0.6); vertical-align: middle; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        /* Footer */
        footer { border-top: 1px solid var(--border); background: #0f172a; padding: 1.25rem; text-align: center; color: var(--text-muted); font-size: 0.85rem; margin-top: auto; }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <a href="/gallery.php" class="brand">
                <span>📸</span> Gallery - Đề 31
            </a>
            <ul class="nav-links">
                <li><a href="/gallery.php" class="<?= $currentPage === 'gallery.php' ? 'active' : '' ?>">Thư viện ảnh</a></li>
                <li><a href="/albums.php" class="<?= $currentPage === 'albums.php' ? 'active' : '' ?>">Album</a></li>
                <li><a href="/categories.php" class="<?= $currentPage === 'categories.php' ? 'active' : '' ?>">Danh mục</a></li>
                <?php if ($loggedIn): ?>
                    <li><a href="/manage_shares.php" class="<?= $currentPage === 'manage_shares.php' ? 'active' : '' ?>">Liên kết chia sẻ</a></li>
                    <li><a href="/upload.php" class="btn-upload <?= $currentPage === 'upload.php' ? 'active' : '' ?>">+ Tải ảnh lên</a></li>
                    <li><span class="user-tag">👤 Admin</span></li>
                    <li><a href="/logout.php" style="color: #f87171;">Đăng xuất</a></li>
                <?php else: ?>
                    <li><a href="/login.php" class="btn-upload">Đăng nhập Quản trị</a></li>
                <?php endif; ?>
                <li><a href="/index.php" title="Trạng thái hệ thống" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">⚙️ Nền tảng</a></li>
            </ul>
        </div>
    </header>
    <main>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <span><?= $flash['type'] === 'success' ? '✅' : ($flash['type'] === 'danger' ? '❌' : 'ℹ️') ?></span>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
