<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Quản lý Danh mục phân loại ảnh (categories.php)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin(); // Chỉ quản trị viên mới được quản lý danh mục

$pdo = getDatabaseConnection();
$error = null;

// Xử lý Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token không hợp lệ.');
        header('Location: /categories.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Thêm danh mục mới
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            setFlash('danger', 'Tên danh mục không được để trống.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
                $stmt->execute([':name' => $name]);
                setFlash('success', "Đã thêm danh mục '{$name}' thành công.");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('warning', "Danh mục '{$name}' đã tồn tại.");
                } else {
                    setFlash('danger', "Lỗi thêm danh mục: " . $e->getMessage());
                }
            }
        }
        header('Location: /categories.php');
        exit;
    }

    // 2. Chỉnh sửa danh mục
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id <= 0 || empty($name)) {
            setFlash('danger', 'Dữ liệu chỉnh sửa không hợp lệ.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = :name WHERE id = :id");
                $stmt->execute([':name' => $name, ':id' => $id]);
                setFlash('success', "Cập nhật danh mục thành công.");
            } catch (PDOException $e) {
                setFlash('danger', "Lỗi cập nhật: " . $e->getMessage());
            }
        }
        header('Location: /categories.php');
        exit;
    }

    // 3. Xóa danh mục
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Khóa ngoại category_id trong bảng photos là ON DELETE SET NULL, ảnh sẽ không bị xóa mất
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
                $stmt->execute([':id' => $id]);
                setFlash('success', 'Đã xóa danh mục thành công (các ảnh thuộc danh mục này được chuyển về Chưa phân loại).');
            } catch (PDOException $e) {
                setFlash('danger', 'Lỗi khi xóa danh mục: ' . $e->getMessage());
            }
        }
        header('Location: /categories.php');
        exit;
    }
}

// Lấy danh sách danh mục và số lượng ảnh tương ứng
$categories = $pdo->query("
    SELECT c.id, c.name, c.created_at, COUNT(p.id) AS total_photos
    FROM categories c
    LEFT JOIN photos p ON c.id = p.category_id
    GROUP BY c.id, c.name, c.created_at
    ORDER BY c.id ASC
")->fetchAll();

$pageTitle = 'Quản lý Danh mục';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">🏷️ Quản Lý Danh Mục Ảnh</h1>
    <a href="/gallery.php" class="btn btn-secondary">&larr; Quay lại Thư viện</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">
    <!-- Form thêm danh mục -->
    <div class="card">
        <h2 style="font-size: 1.15rem; margin-bottom: 1rem; color: #38bdf8;">Thêm Danh Mục Mới</h2>
        <form method="POST" action="/categories.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label" for="cat_name">Tên danh mục:</label>
                <input type="text" id="cat_name" name="name" class="form-control" placeholder="Ví dụ: Sự kiện, Kỷ niệm..." required>
                <div class="form-text">Tên danh mục là duy nhất trong hệ thống.</div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                + Thêm Danh Mục
            </button>
        </form>
    </div>

    <!-- Bảng danh sách danh mục -->
    <div class="card">
        <h2 style="font-size: 1.15rem; margin-bottom: 1rem; color: #38bdf8;">Danh Sách Danh Mục Hiện Có (<?= count($categories) ?>)</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Tên Danh Mục</th>
                        <th style="width: 120px;">Số Ảnh</th>
                        <th style="width: 160px; text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">Chưa có danh mục nào.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>#<?= $cat['id'] ?></td>
                                <td>
                                    <form method="POST" action="/categories.php" style="display: flex; gap: 0.5rem;" id="edit-form-<?= $cat['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                        <input type="text" name="name" value="<?= htmlspecialchars($cat['name']) ?>" class="form-control" style="padding: 0.3rem 0.6rem; font-size: 0.85rem;" required>
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Lưu tên mới">Lưu</button>
                                    </form>
                                </td>
                                <td>
                                    <a href="/gallery.php?category_id=<?= $cat['id'] ?>" style="color: #38bdf8; text-decoration: none;">
                                        <strong><?= $cat['total_photos'] ?></strong> ảnh
                                    </a>
                                </td>
                                <td style="text-align: right;">
                                    <form method="POST" action="/categories.php" onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục \'<?= htmlspecialchars(addslashes($cat['name'])) ?>\'? Các bức ảnh sẽ không bị xóa mất.');" style="display: inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                                    </form>
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
