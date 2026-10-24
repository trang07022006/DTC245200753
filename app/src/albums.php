<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Quản lý Album ảnh (albums.php)
 * Thực thi quy tắc: KHÔNG cho phép xóa album khi còn ảnh
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getDatabaseConnection();

// Xử lý Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token không hợp lệ.');
        header('Location: /albums.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Tạo album mới
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            setFlash('danger', 'Tên album không được để trống.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO albums (name, description) VALUES (:name, :desc)");
                $stmt->execute([':name' => $name, ':desc' => $description]);
                setFlash('success', "Đã tạo album '{$name}' thành công.");
            } catch (PDOException $e) {
                setFlash('danger', 'Lỗi tạo album: ' . $e->getMessage());
            }
        }
        header('Location: /albums.php');
        exit;
    }

    // 2. Chỉnh sửa album
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($id <= 0 || empty($name)) {
            setFlash('danger', 'Tên album không được để trống.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE albums SET name = :name, description = :desc WHERE id = :id");
                $stmt->execute([':name' => $name, ':desc' => $description, ':id' => $id]);
                setFlash('success', "Cập nhật album '{$name}' thành công.");
            } catch (PDOException $e) {
                setFlash('danger', 'Lỗi cập nhật album: ' . $e->getMessage());
            }
        }
        header('Location: /albums.php');
        exit;
    }

    // 3. Xóa album (QUY TẮC BẢO VỆ DỮ LIỆU)
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Kiểm tra số lượng ảnh hiện có trong album
                $checkStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM photos WHERE album_id = :id");
                $checkStmt->execute([':id' => $id]);
                $photoCount = (int)$checkStmt->fetchColumn();

                if ($photoCount > 0) {
                    // Chặn xóa và hiển thị thông báo nghiệp vụ rõ ràng
                    setFlash('danger', "Không thể xóa album này vì vẫn còn {$photoCount} bức ảnh bên trong. Hãy di chuyển hoặc xóa các bức ảnh trước khi xóa album.");
                } else {
                    $delStmt = $pdo->prepare("DELETE FROM albums WHERE id = :id");
                    $delStmt->execute([':id' => $id]);
                    setFlash('success', 'Đã xóa album rỗng thành công.');
                }
            } catch (PDOException $e) {
                setFlash('danger', 'Lỗi ràng buộc CSDL: ' . $e->getMessage());
            }
        }
        header('Location: /albums.php');
        exit;
    }
}

// Lấy danh sách album và đếm số ảnh
$albums = $pdo->query("
    SELECT a.id, a.name, a.description, a.created_at, COUNT(p.id) AS total_photos
    FROM albums a
    LEFT JOIN photos p ON a.id = p.album_id
    GROUP BY a.id, a.name, a.description, a.created_at
    ORDER BY a.id DESC
")->fetchAll();

$pageTitle = 'Quản lý Album';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">📁 Quản Lý Album Ảnh</h1>
    <a href="/upload.php" class="btn btn-primary">+ Tải ảnh vào Album</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
    <!-- Form tạo album -->
    <div class="card">
        <h2 style="font-size: 1.15rem; margin-bottom: 1rem; color: #38bdf8;">Tạo Album Mới</h2>
        <form method="POST" action="/albums.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label" for="album_name">Tên Album (*):</label>
                <input type="text" id="album_name" name="name" class="form-control" placeholder="Ví dụ: Chuyến đi Đà Nẵng 2026..." required>
            </div>

            <div class="form-group">
                <label class="form-label" for="album_desc">Mô tả Album:</label>
                <textarea id="album_desc" name="description" class="form-control" rows="3" placeholder="Ghi chú về album..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                + Tạo Album
            </button>
        </form>
    </div>

    <!-- Danh sách album -->
    <div class="card">
        <h2 style="font-size: 1.15rem; margin-bottom: 1rem; color: #38bdf8;">Danh Sách Album (<?= count($albums) ?>)</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Tên & Mô tả</th>
                        <th style="width: 100px;">Số ảnh</th>
                        <th style="width: 200px; text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($albums)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">Chưa có album nào.</td></tr>
                    <?php else: ?>
                        <?php foreach ($albums as $alb): ?>
                            <tr>
                                <td>#<?= $alb['id'] ?></td>
                                <td>
                                    <div style="font-weight: 600; color: #f8fafc; font-size: 0.95rem;"><?= htmlspecialchars($alb['name']) ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;"><?= htmlspecialchars($alb['description'] ?: 'Chưa có mô tả') ?></div>
                                </td>
                                <td>
                                    <a href="/gallery.php?album_id=<?= $alb['id'] ?>" class="btn btn-sm btn-secondary" style="font-size: 0.8rem;">
                                        🖼️ <strong><?= $alb['total_photos'] ?></strong> ảnh
                                    </a>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.35rem;">
                                        <a href="/manage_shares.php?target_type=album&target_id=<?= $alb['id'] ?>" class="btn btn-sm btn-warning" title="Chia sẻ Album">🔗 Chia sẻ</a>
                                        <form method="POST" action="/albums.php" onsubmit="return confirm('Bạn có chắc chắn muốn xóa album \'<?= htmlspecialchars(addslashes($alb['name'])) ?>\'?');" style="display: inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $alb['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" <?= $alb['total_photos'] > 0 ? 'title="Album còn ảnh, không thể xóa trực tiếp"' : '' ?>>
                                                Xóa
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
