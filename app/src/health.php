<?php
/**
 * DTC245200753 - Đề 31: Hệ thống Thư viện Ảnh Gallery
 * Health Check Endpoint: Kiểm tra trạng thái máy chủ ứng dụng và MySQL
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

$response = [
    'service'   => 'gallery-app',
    'status'    => 'DOWN',
    'database'  => 'DISCONNECTED',
    'timestamp' => date('c')
];

try {
    $pdo = getDatabaseConnection();
    // Chạy truy vấn nhẹ để kiểm tra kết nối CSDL thực tế
    $stmt = $pdo->query("SELECT 1 AS alive");
    if ($stmt && $stmt->fetchColumn() == 1) {
        $response['status'] = 'UP';
        $response['database'] = 'CONNECTED';
        http_response_code(200);
    } else {
        http_response_code(503);
    }
} catch (Exception $e) {
    http_response_code(503);
    $response['error'] = 'Database service unavailable';
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
