<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Giao diện Khách xem nội dung được chia sẻ (share.php)
 * Chế độ View-Only: Khách chỉ được xem, không có quyền sửa/xóa/upload
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$rawToken = trim($_GET['token'] ?? '');
$error = null;
$shareData = null;
$albumPhotos = [];

if (empty($rawToken)) {
    $error = 'Thiếu mã Token trong liên kết chia sẻ.';
} else {
    try {
        $pdo = getDatabaseConnection();
        $tokenHash = hash('sha256', $rawToken);

        // 1. Kiểm tra Token hash trong CSDL
        $stmt = $pdo->prepare("
            SELECT s.id, s.target_type, s.photo_id, s.album_id, s.created_at, s.revoked_at,
                   p.title AS photo_title, p.size_bytes AS photo_size, p.mime_type AS photo_mime, p.created_at AS photo_created,
                   a.name AS album_name, a.description AS album_desc
            FROM share_links s
            LEFT JOIN photos p ON s.photo_id = p.id
            LEFT JOIN albums a ON s.album_id = a.id
            WHERE s.token_hash = :hash
            LIMIT 1
        ");
        $stmt->execute([':hash' => $tokenHash]);
        $shareData = $stmt->fetch();

        if (!$shareData) {
            $error = 'Liên kết chia sẻ không tồn tại hoặc đã bị xóa.';
        } elseif (!empty($shareData['revoked_at'])) {
            $error = 'Liên kết này đã bị chủ sở hữu THU HỒI. Quyền truy cập nội dung đã bị chấm dứt.';
        } else {
            // 2. Nếu chia sẻ cả album, lấy danh sách ảnh hiện tại trong album
            if ($shareData['target_type'] === 'album') {
                $photoStmt = $pdo->prepare("
                    SELECT id, title, size_bytes, created_at
                    FROM photos
                    WHERE album_id = :aid
                    ORDER BY id DESC
                ");
                $photoStmt->execute([':aid' => $shareData['album_id']]);
                $albumPhotos = $photoStmt->fetchAll();
            }
        }
    } catch (Exception $e) {
        error_log("[Share Page Error] " . $e->getMessage());
        $error = 'Lỗi hệ thống khi tải liên kết chia sẻ.';
    }
}

$pageTitle = $shareData ? ('Chia sẻ: ' . ($shareData['target_type'] === 'album' ? $shareData['album_name'] : $shareData['photo_title'])) : 'Xem nội dung chia sẻ';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
    <div style="max-width: 600px; margin: 3rem auto; text-align: center;">
        <div class="card" style="border-color: rgba(239, 68, 68, 0.4);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
            <h1 style="font-size: 1.4rem; color: #f87171; margin-bottom: 0.75rem;">Không Thể Truy Cập</h1>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;"><?= htmlspecialchars($error) ?></p>
            <a href="/gallery.php" class="btn btn-secondary">Về Trang Chủ Gallery</a>
        </div>
    </div>
<?php else: ?>
    <!-- Thông báo chế độ khách xem -->
    <div style="background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 8px; padding: 0.85rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <span style="color: #38bdf8; font-weight: 600;">👁️ Chế độ Khách (View-Only):</span>
            <span style="color: var(--text-muted); font-size: 0.9rem;">Bạn đang xem nội dung được chia sẻ công khai qua Token an toàn. Không thể chỉnh sửa hoặc tải lên.</span>
        </div>
        <span class="user-tag" style="background: #0369a1; color: white;">Khách Xem</span>
    </div>

    <?php if ($shareData['target_type'] === 'photo'): ?>
        <!-- Chia sẻ 1 bức ảnh cụ thể -->
        <div style="max-width: 800px; margin: 0 auto;">
            <div class="card">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <img src="/view.php?id=<?= $shareData['photo_id'] ?>&token=<?= urlencode($rawToken) ?>"
                         alt="<?= htmlspecialchars($shareData['photo_title']) ?>"
                         style="max-width: 100%; max-height: 65vh; border-radius: 8px; border: 1px solid var(--border); object-fit: contain; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);">
                </div>
                <div style="border-top: 1px solid var(--border); padding-top: 1.25rem;">
                    <h1 style="font-size: 1.5rem; color: #f8fafc; margin-bottom: 0.5rem;"><?= htmlspecialchars($shareData['photo_title']) ?></h1>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; color: var(--text-muted); font-size: 0.85rem;">
                        <span>📦 Dung lượng: <?= round($shareData['photo_size'] / 1024, 1) ?> KB</span>
                        <span>🎨 Định dạng: <?= htmlspecialchars($shareData['photo_mime']) ?></span>
                        <span>🕒 Ngày tải lên: <?= date('d/m/Y H:i', strtotime($shareData['photo_created'])) ?></span>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($shareData['target_type'] === 'album'): ?>
        <!-- Chia sẻ cả Album ảnh -->
        <div class="page-header">
            <div>
                <h1 class="page-title">📁 <?= htmlspecialchars($shareData['album_name']) ?></h1>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                    <?= htmlspecialchars($shareData['album_desc'] ?: 'Không có mô tả') ?> &bull;
                    <strong><?= count($albumPhotos) ?></strong> bức ảnh trong album
                </p>
            </div>
        </div>

        <?php if (empty($albumPhotos)): ?>
            <div class="card" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📭</div>
                <p>Album này hiện chưa có bức ảnh nào.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem;">
                <?php foreach ($albumPhotos as $p): ?>
                    <div class="card" style="padding: 0.85rem; display: flex; flex-direction: column; overflow: hidden;">
                        <a href="/view.php?id=<?= $p['id'] ?>&token=<?= urlencode($rawToken) ?>" target="_blank" style="text-decoration: none; overflow: hidden; border-radius: 6px; aspect-ratio: 4/3; background: #0b1120; display: flex; align-items: center; justify-content: center;">
                            <img src="/view.php?id=<?= $p['id'] ?>&token=<?= urlencode($rawToken) ?>"
                                 alt="<?= htmlspecialchars($p['title']) ?>"
                                 loading="lazy"
                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;"
                                 onmouseover="this.style.transform='scale(1.05)'"
                                 onmouseout="this.style.transform='scale(1)'">
                        </a>
                        <div style="margin-top: 0.75rem;">
                            <div style="font-weight: 600; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #f1f5f9;">
                                <?= htmlspecialchars($p['title']) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                <?= round($p['size_bytes'] / 1024, 1) ?> KB &bull; <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
