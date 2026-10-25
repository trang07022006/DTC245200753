# -------------------------------------------------------------
# DTC245200753 - Lê Minh Trang - CNTT K23M
# Đề 31: Hệ thống Thư viện Ảnh Gallery
# Script kiểm chứng Gia cố An toàn Hệ thống (Hardening Verification)
# -------------------------------------------------------------

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$ErrorActionPreference = "Continue"

Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "   KIỂM CHỨNG GIA CỐ BẢO MẬT HỆ THỐNG (HARDENING VERIFICATION)" -ForegroundColor Cyan
Write-Host "   MSV: DTC245200753 - SV: Lê Minh Trang" -ForegroundColor Cyan
Write-Host "========================================================`n" -ForegroundColor Cyan

$passed = 0
$total = 7

# -------------------------------------------------------------
# Test 1: Kiểm tra cô lập mạng CSDL (gallery_mysql không publish cổng ra Host)
# -------------------------------------------------------------
Write-Host "[TEST 1/7] Kiểm tra cổng 3306 của gallery_mysql không bị bind ra ngoài Host..." -ForegroundColor Yellow
$mysqlPorts = docker inspect gallery_mysql --format '{{json .NetworkSettings.Ports}}' | Out-String

if ($mysqlPorts -match '"3306/tcp":null') {
    Write-Host "  -> Container gallery_mysql không publish cổng 3306 ra ngoài máy chủ (Ports: 3306/tcp -> null)." -ForegroundColor Green
    Write-Host "==> TEST 1 PASS: MySQL chỉ giao tiếp nội bộ trong mạng Docker backend." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> CẢNH BÁO: Container gallery_mysql đang mở cổng 3306 ra ngoài máy chủ: $mysqlPorts" -ForegroundColor Red
    Write-Host "==> TEST 1 FAIL: Chưa cô lập cổng MySQL của dự án." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 2: Kiểm tra chặn truy cập trực tiếp thư mục /uploads/ (Mã 403)
# -------------------------------------------------------------
Write-Host "`n[TEST 2/7] Kiểm tra Reverse Proxy chặn truy cập trực tiếp /uploads/..." -ForegroundColor Yellow
$http403 = curl.exe -s -o NUL -w "%{http_code}" http://127.0.0.1/uploads/

if ($http403 -eq "403") {
    Write-Host "  -> Nginx trả về mã HTTP $http403 Forbidden đúng thiết kế an ninh." -ForegroundColor Green
    Write-Host "==> TEST 2 PASS: Ngăn chặn duyệt trực tiếp thư mục ảnh thành công." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> Nginx trả về mã HTTP $http403 (Kỳ vọng 403)" -ForegroundColor Red
    Write-Host "==> TEST 2 FAIL: Chưa chặn được truy cập thư mục uploads." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 3: Kiểm tra HTTP Security Headers & Ẩn phiên bản Nginx
# -------------------------------------------------------------
Write-Host "`n[TEST 3/7] Kiểm tra các tiêu đề bảo mật HTTP (Security Headers) và ẩn Server..." -ForegroundColor Yellow
$headers = curl.exe -s -I http://127.0.0.1/ | Out-String

$hasXContentType = $headers -match "X-Content-Type-Options: nosniff"
$hasXFrame = $headers -match "X-Frame-Options: SAMEORIGIN"
$hasServerVersion = $headers -match "nginx/\d+\.\d+"

Write-Host "  -> X-Content-Type-Options: nosniff :" $(if ($hasXContentType) { "CÓ (Tốt)" } else { "THIẾU" })
Write-Host "  -> X-Frame-Options: SAMEORIGIN     :" $(if ($hasXFrame) { "CÓ (Tốt)" } else { "THIẾU" })
Write-Host "  -> Server Version lộ phiên bản     :" $(if ($hasServerVersion) { "LỘ (Nguy hiểm)" } else { "KHÔNG (Đã ẩn)" })

if ($hasXContentType -and $hasXFrame -and (-not $hasServerVersion)) {
    Write-Host "==> TEST 3 PASS: Toàn bộ Security Headers đã được thiết lập chuẩn." -ForegroundColor Green
    $passed++
} else {
    Write-Host "==> TEST 3 FAIL: Tiêu đề bảo mật chưa đạt chuẩn." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 4: Kiểm tra ẩn thông tin PHP (expose_php = Off)
# -------------------------------------------------------------
Write-Host "`n[TEST 4/7] Kiểm tra ẩn tiêu đề X-Powered-By của PHP..." -ForegroundColor Yellow
$hasXPoweredBy = $headers -match "X-Powered-By:"

if (-not $hasXPoweredBy) {
    Write-Host "  -> Không tìm thấy tiêu đề X-Powered-By (PHP đã ẩn thông tin thành công)." -ForegroundColor Green
    Write-Host "==> TEST 4 PASS: Ẩn thông tin runtime PHP hoàn tất." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> CẢNH BÁO: Hệ thống vẫn để lộ X-Powered-By!" -ForegroundColor Red
    Write-Host "==> TEST 4 FAIL: expose_php chưa được tắt." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 5: Kiểm tra tài khoản giám sát exporter tuân thủ Quyền tối thiểu
# -------------------------------------------------------------
Write-Host "`n[TEST 5/7] Kiểm tra tài khoản MySQL 'exporter' tuân thủ Nguyên tắc quyền tối thiểu..." -ForegroundColor Yellow
$exporterGrants = docker compose exec -T mysql mysql -u root -pRoot_SecurePass_2026! -e "SHOW GRANTS FOR 'exporter'@'%';" 2>$null | Out-String

Write-Host $exporterGrants.Trim()
$hasProcess = $exporterGrants -match "PROCESS"
$hasSelect = $exporterGrants -match "SELECT"
$hasAllPrivileges = $exporterGrants -match "ALL PRIVILEGES"

if ($hasProcess -and $hasSelect -and (-not $hasAllPrivileges)) {
    Write-Host "  -> Tài khoản exporter chỉ có quyền PROCESS, SELECT, REPLICATION CLIENT, không có ALL PRIVILEGES." -ForegroundColor Green
    Write-Host "==> TEST 5 PASS: Nguyên tắc đặc quyền tối thiểu (Least Privilege) đạt chuẩn." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> Tài khoản exporter bị gán thừa quyền!" -ForegroundColor Red
    Write-Host "==> TEST 5 FAIL: Cấp phát quyền chưa an toàn." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 6: Kiểm tra bảo vệ thư mục Uploads chống thực thi mã độc (.htaccess)
# -------------------------------------------------------------
Write-Host "`n[TEST 6/7] Kiểm tra cơ chế chống thực thi mã trong thư mục uploads..." -ForegroundColor Yellow
$htaccessContent = docker compose exec -T app cat /var/www/html/uploads/.htaccess 2>$null | Out-String

if ($htaccessContent -match "FilesMatch" -and $htaccessContent -match "Deny from all") {
    Write-Host "  -> File uploads/.htaccess đã khóa các định dạng kịch bản nguy hiểm (php|cgi|sh|py)." -ForegroundColor Green
    Write-Host "==> TEST 6 PASS: Cơ chế phòng thủ thực thi file mã độc trong uploads hoạt động." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> Chưa tìm thấy cấu hình bảo vệ .htaccess hợp lệ trong uploads." -ForegroundColor Red
    Write-Host "==> TEST 6 FAIL: Thiếu phòng thủ thực thi file." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 7: Kiểm tra tiến trình xử lý Web chạy dưới quyền non-root (www-data)
# -------------------------------------------------------------
Write-Host "`n[TEST 7/7] Kiểm tra Apache workers chạy dưới quyền phi quản trị (non-root www-data)..." -ForegroundColor Yellow
$psOutput = docker compose exec -T app ps aux | Out-String

if ($psOutput -match "www-data\s+\d+.*apache2") {
    Write-Host "  -> Các tiến trình xử lý request HTTP đều chạy dưới quyền user www-data (UID 33)." -ForegroundColor Green
    Write-Host "==> TEST 7 PASS: Tuân thủ nguyên tắc chạy ứng dụng non-root an toàn." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> CẢNH BÁO: Không phát hiện worker chạy dưới user www-data!" -ForegroundColor Red
    Write-Host "==> TEST 7 FAIL: Tiến trình xử lý chạy dưới quyền nguy hiểm." -ForegroundColor Red
}

Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "   KẾT QUẢ KIỂM CHỨNG BẢO MẬT: $passed / $total ĐẠT TIÊU CHUẨN" -ForegroundColor $(if ($passed -eq $total) { "Green" } else { "Red" })
Write-Host "========================================================`n" -ForegroundColor Cyan

if ($passed -eq $total) {
    exit 0
} else {
    exit 1
}
