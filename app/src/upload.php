<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Xử lý Tải ảnh lên an toàn đa lớp (upload.php)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getDatabaseConnection();
$error = null;

// Giới hạn dung lượng tải lên (5MB mặc định)
$maxBytes = (int)(getenv('UPLOAD_MAX_BYTES') ?: 5242880);
$maxMb = round($maxBytes / (1024 * 1024), 1);

// Lấy danh sách Album và Danh mục cho Form
$albums = $pdo->query("SELECT id, name FROM albums ORDER BY name ASC")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

if (empty($albums)) {
    setFlash('warning', 'Hệ thống chưa có album nào. Vui lòng tạo ít nhất 1 album trước khi tải ảnh.');
    header('Location: /albums.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Mã bảo mật CSRF không hợp lệ hoặc đã hết hạn.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $albumId = (int)($_POST['album_id'] ?? 0);
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

        // 1. Kiểm tra đầu vào cơ bản
        if (empty($title)) {
            $error = 'Vui lòng nhập tiêu đề cho bức ảnh.';
        } elseif ($albumId <= 0) {
            $error = 'Vui lòng chọn một album hợp lệ.';
        } elseif (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => "File vượt quá giới hạn cấu hình của máy chủ ({$maxMb}MB).",
                UPLOAD_ERR_FORM_SIZE  => "File vượt quá giới hạn form biểu mẫu cho phép.",
                UPLOAD_ERR_PARTIAL    => "File chỉ được tải lên một phần.",
                UPLOAD_ERR_NO_FILE    => "Vui lòng chọn một file ảnh từ máy tính của bạn.",
                UPLOAD_ERR_NO_TMP_DIR => "Thiếu thư mục tạm trên máy chủ.",
                UPLOAD_ERR_CANT_WRITE => "Không thể ghi file vào thư mục tạm trên đĩa.",
            ];
            $errCode = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
            $error = $uploadErrors[$errCode] ?? 'Lỗi không xác định khi tải file lên.';
        } else {
            $tmpPath = $_FILES['photo']['tmp_name'];
            $origName = $_FILES['photo']['name'];
            $fileSize = (int)$_FILES['photo']['size'];

            // 2. Kiểm tra dung lượng
            if ($fileSize > $maxBytes) {
                $error = "Dung lượng ảnh ({$fileSize} bytes) vượt quá giới hạn cho phép ({$maxMb} MB).";
            } else {
                // 3. Kiểm tra phần mở rộng (Extension Whitelist)
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    $error = 'Định dạng file không được hỗ trợ. Chỉ chấp nhận các file: JPG, JPEG, PNG, WEBP.';
                } else {
                    // 4. Kiểm tra MIME type thực tế (không tin vào header từ client)
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $actualMime = $finfo->file($tmpPath);
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                    if (!in_array($actualMime, $allowedMimes)) {
                        $error = "Nội dung file không phải ảnh hợp lệ (MIME thực tế: {$actualMime}).";
                    } else {
                        // 5. Kiểm tra tính toàn vẹn của file ảnh bằng getimagesize()
                        $imgInfo = @getimagesize($tmpPath);
                        if ($imgInfo === false) {
                            $error = 'File bị hỏng hoặc cố tình giả mạo cấu trúc ảnh nhị phân.';
                        } else {
                            // 6. Đổi tên lưu trữ ngẫu nhiên chống Path Traversal và trùng lặp
                            $storedExt = ($ext === 'jpeg') ? 'jpg' : $ext;
                            $storedFilename = bin2hex(random_bytes(16)) . '.' . $storedExt;
                            $uploadDir = __DIR__ . '/uploads';
                            $destination = $uploadDir . '/' . $storedFilename;

                            // 7. Di chuyển file vào thư mục uploads
                            if (!move_uploaded_file($tmpPath, $destination)) {
                                $error = 'Không thể lưu file vào thư mục uploads trên máy chủ.';
                            } else {
                                // Gán quyền an toàn 0644 (không dùng 777)
                                chmod($destination, 0644);

                                // 8. Lưu metadata vào cơ sở dữ liệu MySQL
                                try {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO photos (album_id, category_id, title, stored_filename, original_filename, mime_type, size_bytes)
                                        VALUES (:album_id, :category_id, :title, :stored, :orig, :mime, :size)
                                    ");
                                    $stmt->execute([
                                        ':album_id'    => $albumId,
                                        ':category_id' => $categoryId,
                                        ':title'       => $title,
                                        ':stored'      => $storedFilename,
                                        ':orig'        => $origName,
                                        ':mime'        => $actualMime,
                                        ':size'        => $fileSize
                                    ]);

                                    setFlash('success', "Tải ảnh '{$title}' lên thư viện thành công!");
                                    header('Location: /gallery.php');
                                    exit;
                                } catch (Exception $e) {
                                    // 9. CƠ CHẾ ROLLBACK FILE: Xóa file vừa upload nếu lưu DB thất bại
                                    if (file_exists($destination)) {
                                        unlink($destination);
                                    }
                                    error_log("[Upload DB Error] " . $e->getMessage());
                                    $error = 'Không thể ghi thông tin ảnh vào CSDL: ' . $e->getMessage();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

$pageTitle = 'Tải ảnh lên';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">📤 Tải Ảnh Lên Thư Viện</h1>
    <a href="/gallery.php" class="btn btn-secondary">&larr; Về Thư viện ảnh</a>
</div>

<div style="max-width: 650px; margin: 0 auto;">
    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>❌</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/upload.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <!-- Chọn file ảnh -->
            <div class="form-group">
                <label class="form-label" for="photo_file">Chọn tệp hình ảnh (*):</label>
                <input type="file" id="photo_file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp" required onchange="previewImage(this);">
                <div class="form-text">Định dạng hỗ trợ: <strong>JPEG, PNG, WEBP</strong>. Dung lượng tối đa: <strong><?= $maxMb ?> MB</strong>.</div>
            </div>

            <!-- Khung xem trước ảnh trước khi tải -->
            <div id="preview-box" style="display: none; margin-bottom: 1.25rem; text-align: center;">
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Xem trước ảnh:</p>
                <img id="img-preview" src="#" alt="Xem trước" style="max-height: 220px; max-width: 100%; border-radius: 8px; border: 1px solid var(--border); object-fit: contain;">
            </div>

            <!-- Tiêu đề ảnh -->
            <div class="form-group">
                <label class="form-label" for="photo_title">Tiêu đề ảnh (*):</label>
                <input type="text" id="photo_title" name="title" class="form-control" placeholder="Nhập tiêu đề hoặc tên mô tả ảnh..." required maxlength="150" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <!-- Thuộc Album -->
            <div class="form-group">
                <label class="form-label" for="photo_album">Thuộc Album (*):</label>
                <select id="photo_album" name="album_id" class="form-control" required>
                    <option value="">-- Chọn Album --</option>
                    <?php foreach ($albums as $alb): ?>
                        <option value="<?= $alb['id'] ?>" <?= (isset($_POST['album_id']) && (int)$_POST['album_id'] === (int)$alb['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($alb['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Phân loại Danh mục -->
            <div class="form-group">
                <label class="form-label" for="photo_cat">Danh mục phân loại (Tùy chọn):</label>
                <select id="photo_cat" name="category_id" class="form-control">
                    <option value="">-- Không phân loại --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 1rem; margin-top: 0.5rem;">
                🚀 Tải Ảnh Lên Hệ Thống
            </button>
        </form>
    </div>
</div>

<script>
function previewImage(input) {
    const previewBox = document.getElementById('preview-box');
    const previewImg = document.getElementById('img-preview');
    const titleInput = document.getElementById('photo_title');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewBox.style.display = 'block';
        }
        reader.readAsDataURL(file);

        // Tự động điền tiêu đề từ tên file nếu tiêu đề còn trống
        if (!titleInput.value) {
            let nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
            titleInput.value = nameWithoutExt;
        }
    } else {
        previewBox.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
