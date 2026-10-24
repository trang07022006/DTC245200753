<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Quản lý Liên kết chia sẻ công khai (manage_shares.php)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getDatabaseConnection();
$newShareUrl = null;

// Xử lý tạo và thu hồi liên kết
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token không hợp lệ.');
        header('Location: /manage_shares.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Tạo liên kết chia sẻ mới bằng Token ngẫu nhiên (CSPs)
    if ($action === 'create') {
        $targetType = $_POST['target_type'] ?? '';
        $targetId = (int)($_POST['target_id'] ?? 0);

        if (!in_array($targetType, ['photo', 'album']) || $targetId <= 0) {
            setFlash('danger', 'Đối tượng chia sẻ không hợp lệ.');
        } else {
            // Sinh chuỗi bí mật ngẫu nhiên bảo mật cao (64 ký tự hex)
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);

            $photoId = ($targetType === 'photo') ? $targetId : null;
            $albumId = ($targetType === 'album') ? $targetId : null;

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO share_links (token_hash, target_type, photo_id, album_id)
                    VALUES (:hash, :type, :pid, :aid)
                ");
                $stmt->execute([
                    ':hash' => $tokenHash,
                    ':type' => $targetType,
                    ':pid'  => $photoId,
                    ':aid'  => $albumId
                ]);

                // Tạo URL đầy đủ để người dùng sao chép
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $shareUrl = "{$protocol}://{$host}/share.php?token={$rawToken}";

                setFlash('success', "Đã tạo liên kết chia sẻ thành công! Đường dẫn: <a href='{$shareUrl}' target='_blank' style='color:#38bdf8; font-weight:bold;'>{$shareUrl}</a>");
            } catch (PDOException $e) {
                setFlash('danger', 'Lỗi tạo liên kết: ' . $e->getMessage());
            }
        }
        header('Location: /manage_shares.php');
        exit;
    }

    // 2. Thu hồi liên kết (Revoke Link)
    if ($action === 'revoke') {
        $shareId = (int)($_POST['id'] ?? 0);
        if ($shareId > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE share_links SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL");
                $stmt->execute([':id' => $shareId]);
                setFlash('success', 'Đã thu hồi liên kết chia sẻ thành công. Khách sẽ không thể truy cập được nữa.');
            } catch (PDOException $e) {
                setFlash('danger', 'Lỗi thu hồi liên kết: ' . $e->getMessage());
            }
        }
        header('Location: /manage_shares.php');
        exit;
    }
}

// Lấy danh sách liên kết đã tạo
$shareLinks = $pdo->query("
    SELECT s.id, s.token_hash, s.target_type, s.created_at, s.revoked_at,
           p.title AS photo_title,
           a.name AS album_name
    FROM share_links s
    LEFT JOIN photos p ON s.photo_id = p.id
    LEFT JOIN albums a ON s.album_id = a.id
    ORDER BY s.id DESC
")->fetchAll();

// Dữ liệu cho modal tạo link
$photos = $pdo->query("SELECT id, title FROM photos ORDER BY id DESC LIMIT 50")->fetchAll();
$albums = $pdo->query("SELECT id, name FROM albums ORDER BY name ASC")->fetchAll();

// Lấy tham số tạo nhanh từ trang khác (nếu có)
$preType = $_GET['target_type'] ?? 'album';
$preId = (int)($_GET['target_id'] ?? 0);

$pageTitle = 'Quản lý Liên kết Chia sẻ';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">🔗 Quản Lý Liên Kết Chia Sẻ Công Khai</h1>
    <a href="/gallery.php" class="btn btn-secondary">&larr; Về Thư viện ảnh</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
    <!-- Form tạo liên kết mới -->
    <div class="card">
        <h2 style="font-size: 1.15rem; margin-bottom: 1rem; color: #38bdf8;">Tạo Liên Kết Mới</h2>
        <form method="POST" action="/manage_shares.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label" for="target_type">Loại đối tượng chia sẻ:</label>
                <select id="target_type" name="target_type" class="form-control" onchange="toggleTargetSelect(this.value);" required>
                    <option value="album" <?= $preType === 'album' ? 'selected' : '' ?>>Chia sẻ Toàn bộ Album</option>
                    <option value="photo" <?= $preType === 'photo' ? 'selected' : '' ?>>Chia sẻ Một bức ảnh cụ thể</option>
                </select>
            </div>

            <!-- Chọn Album -->
            <div class="form-group" id="group-album" style="<?= $preType === 'photo' ? 'display:none;' : '' ?>">
                <label class="form-label" for="target_album">Chọn Album:</label>
                <select id="target_album" name="target_id" class="form-control" <?= $preType === 'album' ? 'required' : '' ?>>
                    <?php foreach ($albums as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= ($preType === 'album' && $preId === (int)$a['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Người có liên kết sẽ xem được tất cả các bức ảnh hiện tại trong album này.</div>
            </div>

            <!-- Chọn Ảnh -->
            <div class="form-group" id="group-photo" style="<?= $preType === 'photo' ? '' : 'display:none;' ?>">
                <label class="form-label" for="target_photo">Chọn Ảnh:</label>
                <select id="target_photo" name="target_id" class="form-control" <?= $preType === 'photo' ? 'required' : '' ?>>
                    <?php foreach ($photos as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($preType === 'photo' && $preId === (int)$p['id']) ? 'selected' : '' ?>>
                            #<?= $p['id'] ?> - <?= htmlspecialchars($p['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Chỉ người có liên kết token mới xem được bức ảnh này.</div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                🔑 Sinh Token & Tạo Liên Kết
            </button>
        </form>
    </div>

    <!-- Bảng danh sách liên kết -->
    <div class="card">
        <h2 style="font-size: 1.15rem; margin-bottom: 1rem; color: #38bdf8;">Danh Sách Liên Kết Đã Tạo (<?= count($shareLinks) ?>)</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Đối tượng</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th style="text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shareLinks)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Chưa có liên kết chia sẻ nào được tạo.</td></tr>
                    <?php else: ?>
                        <?php foreach ($shareLinks as $s): ?>
                            <?php $isRevoked = !empty($s['revoked_at']); ?>
                            <tr>
                                <td>#<?= $s['id'] ?></td>
                                <td>
                                    <?php if ($s['target_type'] === 'album'): ?>
                                        <span class="user-tag" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border-color: rgba(16, 185, 129, 0.4);">📁 Album</span>
                                        <strong><?= htmlspecialchars($s['album_name'] ?? 'Album đã bị xóa') ?></strong>
                                    <?php else: ?>
                                        <span class="user-tag" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border-color: rgba(59, 130, 246, 0.4);">🖼️ Ảnh</span>
                                        <strong><?= htmlspecialchars($s['photo_title'] ?? 'Ảnh đã bị xóa') ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isRevoked): ?>
                                        <span style="color: #f87171; font-weight: 600; font-size: 0.8rem;">● Đã thu hồi</span>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('H:i d/m/Y', strtotime($s['revoked_at'])) ?></div>
                                    <?php else: ?>
                                        <span style="color: #34d399; font-weight: 600; font-size: 0.8rem;">● Đang hoạt động</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= date('H:i d/m/Y', strtotime($s['created_at'])) ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if (!$isRevoked): ?>
                                        <form method="POST" action="/manage_shares.php" onsubmit="return confirm('Bạn có chắc chắn muốn thu hồi liên kết này? Khách sẽ lập tức không thể xem được nữa.');" style="display: inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Thu hồi liên kết</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">Đã chặn</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleTargetSelect(val) {
    const groupAlbum = document.getElementById('group-album');
    const groupPhoto = document.getElementById('group-photo');
    const selAlbum = document.getElementById('target_album');
    const selPhoto = document.getElementById('target_photo');

    if (val === 'album') {
        groupAlbum.style.display = 'block';
        groupPhoto.style.display = 'none';
        selAlbum.required = true;
        selPhoto.required = false;
    } else {
        groupAlbum.style.display = 'none';
        groupPhoto.style.display = 'block';
        selAlbum.required = false;
        selPhoto.required = true;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
