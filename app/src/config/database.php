<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Cấu hình kết nối MySQL sử dụng PDO và Prepared Statements an toàn
 */

function getDatabaseConnection(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'mysql';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'gallery_db';
    $user = getenv('DB_USER') ?: 'gallery_user';
    $pass = getenv('DB_PASS') ?: 'Gallery_SecurePass_2026!';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Sử dụng prepared statement thực sự ở MySQL
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        // Tự động kiểm tra và khởi tạo tài khoản quản trị viên nếu chưa tồn tại
        initDefaultAdmin($pdo);
        return $pdo;
    } catch (PDOException $e) {
        // Ghi log máy chủ nhưng KHÔNG in mật khẩu hay stack trace ra ngoài
        error_log("[Database Connection Error] " . $e->getMessage());
        throw new RuntimeException("Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra dịch vụ.");
    }
}

/**
 * Khởi tạo tài khoản quản trị an toàn bằng password_hash (bcrypt)
 */
function initDefaultAdmin(PDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM `users` WHERE `username` = 'admin'");
        $row = $stmt->fetch();
        if ($row && (int)$row['total'] === 0) {
            $adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
            $adminPass = getenv('ADMIN_PASSWORD') ?: 'Admin@123456';
            $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 10]);

            $insert = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`, `full_name`) VALUES (:username, :hash, :name)");
            $insert->execute([
                ':username' => $adminUser,
                ':hash'     => $hash,
                ':name'     => 'Quản trị viên Hệ thống'
            ]);
            error_log("[Init] Tạo tài khoản admin mặc định thành công.");
        }
    } catch (Exception $e) {
        // Bỏ qua lỗi nếu bảng users chưa sẵn sàng
        error_log("[Init Admin Warning] " . $e->getMessage());
    }
}
