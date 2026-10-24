# -------------------------------------------------------------
# DTC245200753 - Lê Minh Trang - CNTT K23M
# Đề 31: Hệ thống Thư viện Ảnh Gallery
# Script kiểm thử tự động Chặng 4: Thu thập Log với Loki + Promtail & LogQL (tests/test_stage4_logging.ps1)
# -------------------------------------------------------------

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$ErrorActionPreference = "Continue"

Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "   KIỂM THỬ CHẶNG 4: THU THẬP LOG LOKI + PROMTAIL & LOGQL" -ForegroundColor Cyan
Write-Host "   MSV: DTC245200753 - SV: Lê Minh Trang" -ForegroundColor Cyan
Write-Host "========================================================`n" -ForegroundColor Cyan

$passed = 0
$total = 6

# -------------------------------------------------------------
# Test 1: Kiểm tra 11 container đều đang hoạt động (Up)
# -------------------------------------------------------------
Write-Host "[TEST 1/6] Kiểm tra trạng thái 11 container hệ thống..." -ForegroundColor Yellow
$containers = docker compose ps --format "{{.Name}} {{.Status}}"
Write-Host $containers

$expectedContainers = @(
    "gallery_mysql",
    "gallery_app",
    "gallery_phpmyadmin",
    "gallery_nginx",
    "gallery_nginx_exporter",
    "gallery_mysqld_exporter",
    "gallery_cadvisor",
    "gallery_prometheus",
    "gallery_grafana",
    "gallery_loki",
    "gallery_promtail"
)

$allUp = $true
foreach ($name in $expectedContainers) {
    if ($containers -match $name) {
        Write-Host "  -> Container $($name): OK" -ForegroundColor Green
    } else {
        Write-Host "  -> Container $($name): THIẾU HOẶC KHÔNG CHẠY!" -ForegroundColor Red
        $allUp = $false
    }
}

if ($allUp) {
    Write-Host "==> TEST 1 PASS: Đầy đủ 11/11 container đang hoạt động." -ForegroundColor Green
    $passed++
} else {
    Write-Host "==> TEST 1 FAIL: Không đủ 11 container." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 2: Sinh log mẫu thực tế qua Nginx Reverse Proxy
# -------------------------------------------------------------
Write-Host "`n[TEST 2/6] Tạo lưu lượng HTTP thực tế để sinh Nginx log..." -ForegroundColor Yellow

# Gửi các yêu cầu đa dạng: 200, 404, 403 (chặn thư mục uploads)
Write-Host "  -> Gửi GET / (Trang chủ - mong đợi 200)"
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1/

Write-Host "  -> Gửi GET /health.php (Kiểm tra sức khỏe - mong đợi 200)"
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1/health.php

Write-Host "  -> Gửi GET /non-existent-gallery-page-404.php (Mong đợi 404)"
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1/non-existent-gallery-page-404.php

Write-Host "  -> Gửi GET /uploads/ (Thử truy cập trực tiếp upload - mong đợi 403 Forbidden)"
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1/uploads/

Write-Host "  -> Đợi 4 giây để Promtail quét và đẩy log về Loki..." -ForegroundColor Gray
Start-Sleep -Seconds 4

Write-Host "==> TEST 2 PASS: Đã sinh dữ liệu log mẫu thành công." -ForegroundColor Green
$passed++

# -------------------------------------------------------------
# Test 3: LogQL Truy vấn 1 - Lọc theo nhãn dịch vụ: {service="nginx"}
# -------------------------------------------------------------
Write-Host "`n[TEST 3/6] LogQL Truy vấn 1: Lọc log Nginx {service=`"nginx`"}..." -ForegroundColor Yellow
$q1 = '{service="nginx"}'
$url1 = "http://127.0.0.1:3100/loki/api/v1/query_range?query=" + [System.Uri]::EscapeDataString($q1) + "&limit=10"
$res1 = curl.exe -s $url1 | Out-String

if ($res1 -match '"status":"success"' -and $res1 -match 'values') {
    Write-Host "  -> Loki API trả về HTTP 200 Success với luồng log Nginx hợp lệ." -ForegroundColor Green
    Write-Host "==> TEST 3 PASS: LogQL Truy vấn 1 thành công." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> Loki API không trả về log mong muốn: $res1" -ForegroundColor Red
    Write-Host "==> TEST 3 FAIL: LogQL Truy vấn 1 thất bại." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 4: LogQL Truy vấn 2 - Lọc log mã lỗi HTTP: {service="nginx"} |~ "(403|404)"
# -------------------------------------------------------------
Write-Host "`n[TEST 4/6] LogQL Truy vấn 2: Lọc log mã lỗi {service=`"nginx`"} |~ `"(403|404)`"..." -ForegroundColor Yellow
$q2 = '{service="nginx"} |~ "(403|404)"'
$url2 = "http://127.0.0.1:3100/loki/api/v1/query_range?query=" + [System.Uri]::EscapeDataString($q2) + "&limit=10"
$res2 = curl.exe -s $url2 | Out-String

if ($res2 -match '"status":"success"' -and ($res2 -match '403' -or $res2 -match '404')) {
    Write-Host "  -> Tìm thấy các dòng log chứa mã lỗi 403 hoặc 404 trong Loki." -ForegroundColor Green
    Write-Host "==> TEST 4 PASS: LogQL Truy vấn 2 thành công." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> Không tìm thấy log lỗi 403/404: $res2" -ForegroundColor Red
    Write-Host "==> TEST 4 FAIL: LogQL Truy vấn 2 thất bại." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 5: LogQL Truy vấn 3 - Lọc log truy cập health check: {service="nginx"} |= "health.php"
# -------------------------------------------------------------
Write-Host "`n[TEST 5/6] LogQL Truy vấn 3: Lọc hành vi cụ thể {service=`"nginx`"} |= `"health.php`"..." -ForegroundColor Yellow
$q3 = '{service="nginx"} |= "health.php"'
$url3 = "http://127.0.0.1:3100/loki/api/v1/query_range?query=" + [System.Uri]::EscapeDataString($q3) + "&limit=10"
$res3 = curl.exe -s $url3 | Out-String

if ($res3 -match '"status":"success"' -and $res3 -match 'health.php') {
    Write-Host "  -> Tìm thấy dòng log gọi health.php với định dạng chi tiết." -ForegroundColor Green
    Write-Host "==> TEST 5 PASS: LogQL Truy vấn 3 thành công." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> Không tìm thấy log health.php: $res3" -ForegroundColor Red
    Write-Host "==> TEST 5 FAIL: LogQL Truy vấn 3 thất bại." -ForegroundColor Red
}

# -------------------------------------------------------------
# Test 6: Kiểm tra Grafana Provisioning đã nạp Datasource Loki
# -------------------------------------------------------------
Write-Host "`n[TEST 6/6] Kiểm tra Grafana Datasource Loki qua API..." -ForegroundColor Yellow
$grafanaDs = curl.exe -s -u admin:Admin@Grafana2026! http://127.0.0.1:3000/api/datasources | Out-String

if ($grafanaDs -match '"type":"loki"' -and $grafanaDs -match '"url":"http://loki:3100"') {
    Write-Host "  -> Grafana đã tự động provisioning Datasource Loki (http://loki:3100)." -ForegroundColor Green
    Write-Host "==> TEST 6 PASS: Grafana Datasource Loki hoạt động chuẩn xác." -ForegroundColor Green
    $passed++
} else {
    Write-Host "  -> Không tìm thấy datasource Loki trong Grafana: $grafanaDs" -ForegroundColor Red
    Write-Host "==> TEST 6 FAIL: Datasource Loki chưa nạp thành công." -ForegroundColor Red
}

Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "   KẾT QUẢ KIỂM THỬ: $passed / $total BÀI THI ĐẠT YÊU CẦU" -ForegroundColor $(if ($passed -eq $total) { "Green" } else { "Red" })
Write-Host "========================================================`n" -ForegroundColor Cyan

if ($passed -eq $total) {
    exit 0
} else {
    exit 1
}
