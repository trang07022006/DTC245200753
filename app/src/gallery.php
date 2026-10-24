<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Lưới thư viện ảnh chính, Tìm kiếm, Lọc & Phân trang (gallery.php)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDatabaseConnection();
$loggedIn = isLoggedIn();

// Xử lý Xóa ảnh (Chỉ dành cho Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token không hợp lệ.');
        header('Location: /gallery.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Xóa ảnh
    if ($action === 'delete') {
        $photoId = (int)($_POST['id'] ?? 0);
        if ($photoId > 0) {
            try {
                // Lấy tên file trước khi xóa trong DB
                $stmt = $pdo->prepare("SELECT stored_filename, title FROM photos WHERE id = :id");
                $stmt->execute([':id' => $photoId]);
                $photo = $stmt->fetch();

                if ($photo) {
                    // Xóa bản ghi trong DB (các liên kết chia sẻ liên quan sẽ tự cascade xóa)
                    $delStmt = $pdo->prepare("DELETE FROM photos WHERE id = :id");
                    $delStmt->execute([':id' => $photoId]);

                    // Xóa file vật lý trên đĩa
                    $filePath = __DIR__ . '/uploads/' . $photo['stored_filename'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }

                    setFlash('success', "Đã xóa bức ảnh '{$photo['title']}' thành công.");
                }
            } catch (Exception $e) {
                setFlash('danger', 'Lỗi khi xóa ảnh: ' . $e->getMessage());
            }
        }
        header('Location: /gallery.php');
        exit;
    }

    // 2. Chỉnh sửa thông tin ảnh (tiêu đề, album, danh mục)
    if ($action === 'edit_meta') {
        $photoId = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $albumId = (int)($_POST['album_id'] ?? 0);
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

        if ($photoId > 0 && !empty($title) && $albumId > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE photos
                    SET title = :title, album_id = :album_id, category_id = :cat_id
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':title'    => $title,
                    ':album_id' => $albumId,
                    ':cat_id'   => $categoryId,
                    ':id'       => $photoId
                ]);
                setFlash('success', "Cập nhật thông tin ảnh thành công.");
            } catch (Exception $e) {
                setFlash('danger', 'Lỗi cập nhật ảnh: ' . $e->getMessage());
            }
        }
        header('Location: /gallery.php');
        exit;
    }
}

// -------------------------------------------------------------
// Xử lý Tìm kiếm, Lọc và Phân trang (Pagination)
// -------------------------------------------------------------
$searchKeyword = trim($_GET['q'] ?? '');
$filterAlbum = (int)($_GET['album_id'] ?? 0);
$filterCategory = (int)($_GET['category_id'] ?? 0);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12; // 12 ảnh mỗi trang
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if (!empty($searchKeyword)) {
    $whereClauses[] = "p.title LIKE :kw";
    $params[':kw'] = '%' . $searchKeyword . '%';
}
if ($filterAlbum > 0) {
    $whereClauses[] = "p.album_id = :aid";
    $params[':aid'] = $filterAlbum;
}
if ($filterCategory > 0) {
    $whereClauses[] = "p.category_id = :cid";
    $params[':cid'] = $filterCategory;
}

$whereSql = !empty($whereClauses) ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

// Đếm tổng số ảnh thỏa mãn điều kiện lọc
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM photos p {$whereSql}");
$countStmt->execute($params);
$totalPhotos = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalPhotos / $perPage));

// Truy vấn danh sách ảnh theo phân trang
$querySql = "
    SELECT p.id, p.album_id, p.category_id, p.title, p.stored_filename, p.original_filename,
           p.mime_type, p.size_bytes, p.created_at,
           a.name AS album_name,
           c.name AS category_name
    FROM photos p
    JOIN albums a ON p.album_id = a.id
    LEFT JOIN categories c ON p.category_id = c.id
    {$whereSql}
    ORDER BY p.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($querySql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$photos = $stmt->fetchAll();

// Danh sách Album & Danh mục phục vụ bộ lọc
$allAlbums = $pdo->query("SELECT id, name FROM albums ORDER BY name ASC")->fetchAll();
$allCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$pageTitle = 'Thư viện ảnh';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📸 Thư Viện Ảnh Gallery</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            Tổng cộng: <strong><?= $totalPhotos ?></strong> bức ảnh &bull; Trang <?= $page ?> / <?= $totalPages ?>
        </p>
    </div>
    <?php if ($loggedIn): ?>
        <a href="/upload.php" class="btn btn-primary">+ Tải Ảnh Mới</a>
    <?php endif; ?>
</div>

<!-- Thanh Tìm Kiếm & Bộ Lọc -->
<div class="card" style="margin-bottom: 2rem; padding: 1.25rem;">
    <form method="GET" action="/gallery.php" style="display: grid; grid-template-columns: 2fr 1.2fr 1.2fr auto; gap: 0.75rem; align-items: end;">
        <div>
            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.2rem;">🔍 Tìm kiếm theo tiêu đề:</label>
            <input type="text" name="q" value="<?= htmlspecialchars($searchKeyword) ?>" class="form-control" placeholder="Nhập từ khóa cần tìm...">
        </div>
        <div>
            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.2rem;">📁 Lọc theo Album:</label>
            <select name="album_id" class="form-control">
                <option value="0">-- Tất cả Album --</option>
                <?php foreach ($allAlbums as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $filterAlbum === (int)$a['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.2rem;">🏷️ Lọc theo Danh mục:</label>
            <select name="category_id" class="form-control">
                <option value="0">-- Tất cả Danh mục --</option>
                <?php foreach ($allCategories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterCategory === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary">Lọc</button>
            <?php if (!empty($searchKeyword) || $filterAlbum > 0 || $filterCategory > 0): ?>
                <a href="/gallery.php" class="btn btn-secondary" title="Xóa bộ lọc">Xóa lọc</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Lưới Ảnh Gallery Grid -->
<?php if (empty($photos)): ?>
    <div class="card" style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🖼️</div>
        <h2 style="font-size: 1.25rem; color: #cbd5e1; margin-bottom: 0.5rem;">Không tìm thấy bức ảnh nào</h2>
        <p style="font-size: 0.95rem; margin-bottom: 1.5rem;">Chưa có ảnh tải lên hoặc không khớp với bộ lọc tìm kiếm hiện tại.</p>
        <?php if ($loggedIn): ?>
            <a href="/upload.php" class="btn btn-primary">+ Tải ảnh đầu tiên</a>
        <?php else: ?>
            <a href="/login.php" class="btn btn-primary">Đăng nhập Quản trị để Tải ảnh</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <?php foreach ($photos as $photo): ?>
            <div class="card" style="padding: 0; display: flex; flex-direction: column; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;">
                <!-- Khung hiển thị ảnh -->
                <div style="position: relative; aspect-ratio: 4/3; background: #0b1120; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                    <?php if ($loggedIn): ?>
                        <a href="/view.php?id=<?= $photo['id'] ?>" target="_blank" style="width: 100%; height: 100%; display: block;">
                            <img src="/view.php?id=<?= $photo['id'] ?>"
                                 alt="<?= htmlspecialchars($photo['title']) ?>"
                                 loading="lazy"
                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;"
                                 onmouseover="this.style.transform='scale(1.05)'"
                                 onmouseout="this.style.transform='scale(1)'">
                        </a>
                    <?php else: ?>
                        <!-- Khách xem khi chưa có token cụ thể -->
                        <div style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1rem;">
                            🔒 Ảnh riêng tư<br><span style="font-size: 0.75rem;">(Cần đăng nhập hoặc có liên kết token)</span>
                        </div>
                    <?php endif; ?>

                    <!-- Tag Album góc ảnh -->
                    <span style="position: absolute; top: 0.6rem; left: 0.6rem; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(4px); color: #38bdf8; font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid rgba(56, 189, 248, 0.3);">
                        📁 <?= htmlspecialchars($photo['album_name']) ?>
                    </span>
                </div>

                <!-- Thông tin mô tả ảnh -->
                <div style="padding: 1rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <h2 style="font-size: 1.05rem; font-weight: 600; color: #f8fafc; margin-bottom: 0.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($photo['title']) ?>">
                            <?= htmlspecialchars($photo['title']) ?>
                        </h2>
                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.6rem;">
                            <?php if (!empty($photo['category_name'])): ?>
                                <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 0.75rem; padding: 0.15rem 0.45rem; border-radius: 4px;">
                                    🏷️ <?= htmlspecialchars($photo['category_name']) ?>
                                </span>
                            <?php endif; ?>
                            <span style="color: var(--text-muted); font-size: 0.75rem;">
                                <?= round($photo['size_bytes'] / 1024, 1) ?> KB &bull; <?= date('d/m/Y', strtotime($photo['created_at'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Nút thao tác Quản trị -->
                    <?php if ($loggedIn): ?>
                        <div style="border-top: 1px solid var(--border); padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                            <div style="display: flex; gap: 0.35rem;">
                                <a href="/view.php?id=<?= $photo['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Mở ảnh kích thước đầy đủ">👁️ Xem</a>
                                <a href="/manage_shares.php?target_type=photo&target_id=<?= $photo['id'] ?>" class="btn btn-warning btn-sm" title="Tạo link chia sẻ">🔗 Chia sẻ</a>
                            </div>
                            <form method="POST" action="/gallery.php" onsubmit="return confirm('Bạn có chắc chắn muốn xóa bức ảnh \'<?= htmlspecialchars(addslashes($photo['title'])) ?>\'? Thao tác này sẽ xóa vĩnh viễn tệp ảnh khỏi đĩa.');" style="display: inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Xóa ảnh">Xóa</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Thanh Phân Trang (Pagination) -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 0.4rem; margin-top: 1.5rem; flex-wrap: wrap;">
            <?php
            $queryParams = $_GET;
            for ($i = 1; $i <= $totalPages; $i++):
                $queryParams['page'] = $i;
                $pageUrl = '/gallery.php?' . http_build_query($queryParams);
                $isCurrent = ($i === $page);
            ?>
                <a href="<?= $pageUrl ?>" class="btn <?= $isCurrent ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="min-width: 36px;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
