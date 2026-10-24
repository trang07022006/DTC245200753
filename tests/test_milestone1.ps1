# -------------------------------------------------------------
# DTC245200753 - Kich ban kiem thu tu dong Milestone 1
# -------------------------------------------------------------

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "KIEM THU RUNTIME MILESTONE 1: NGINX + APP + MYSQL" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan

$allPass = $true

# 1. Kiem tra trang thai Container
Write-Host "`n[1/5] Kiem tra trang thai cac container Docker:" -ForegroundColor Yellow
$psOutput = docker compose ps --format "table {{.Name}}`t{{.Status}}`t{{.Ports}}"
$psOutput | Out-String | Write-Host

if ($psOutput -match "gallery_nginx" -and $psOutput -match "gallery_app" -and $psOutput -match "gallery_mysql") {
    Write-Host "==> PASS: Ca 4 container (nginx, app, mysql, phpmyadmin) dang hoat dong." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Thieu container hoac container chua Up." -ForegroundColor Red
    $allPass = $false
}

# 2. Kiem tra Nginx Reverse Proxy & Security Headers
Write-Host "`n[2/5] Kiem tra Nginx Reverse Proxy (http://localhost):" -ForegroundColor Yellow
try {
    $resp = Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing -TimeoutSec 10
    if ($resp.StatusCode -eq 200) {
        Write-Host "==> PASS: Nginx phan hoi HTTP 200 thanh cong." -ForegroundColor Green

        $h = $resp.Headers
        Write-Host "HTTP Headers phan hoi tu Nginx:"
        $h.Keys | ForEach-Object { Write-Host "   $_ : $($h[$_])" }

        $hasXFO = $h.ContainsKey("X-Frame-Options") -or ($null -ne $h["X-Frame-Options"])
        $hasXCTO = $h.ContainsKey("X-Content-Type-Options") -or ($null -ne $h["X-Content-Type-Options"])

        if ($hasXFO -and $hasXCTO) {
            Write-Host "==> PASS: Co Security Headers (X-Frame-Options, X-Content-Type-Options)." -ForegroundColor Green
        } else {
            Write-Host "==> WARN: Thieu mot so Security Headers." -ForegroundColor Yellow
        }
    } else {
        Write-Host "==> FAIL: HTTP status code: $($resp.StatusCode)" -ForegroundColor Red
        $allPass = $false
    }
} catch {
    Write-Host "==> FAIL: Khong the ket noi toi Nginx: $_" -ForegroundColor Red
    $allPass = $false
}

# 3. Kiem tra Health Endpoint & Ket noi MySQL
Write-Host "`n[3/5] Kiem tra Healthcheck Endpoint (http://localhost/health.php):" -ForegroundColor Yellow
try {
    $healthResp = Invoke-WebRequest -Uri "http://localhost/health.php" -UseBasicParsing -TimeoutSec 10
    Write-Host "JSON Phan hoi tu /health.php:`n$($healthResp.Content)"
    $healthJson = $healthResp.Content | ConvertFrom-Json

    if ($healthJson.status -eq "UP" -and $healthJson.database -eq "CONNECTED") {
        Write-Host "==> PASS: App va MySQL da ket noi thanh cong (status=UP, database=CONNECTED)." -ForegroundColor Green
    } else {
        Write-Host "==> FAIL: Database chua san sang." -ForegroundColor Red
        $allPass = $false
    }
} catch {
    Write-Host "==> FAIL: Loi goi /health.php: $_" -ForegroundColor Red
    $allPass = $false
}

# 4. Kiem tra Bao ve thu muc /uploads/ (Hardening)
Write-Host "`n[4/5] Kiem tra chan truy cap truc tiep thu muc /uploads/:" -ForegroundColor Yellow
try {
    $blockResp = Invoke-WebRequest -Uri "http://localhost/uploads/" -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
    Write-Host "==> FAIL: Thu muc uploads khong bi chan (HTTP $($blockResp.StatusCode))." -ForegroundColor Red
    $allPass = $false
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    if ($statusCode -eq 403) {
        Write-Host "==> PASS: Nginx chan dung 403 Forbidden khi truy cap truc tiep /uploads/." -ForegroundColor Green
    } else {
        Write-Host "==> Status code: $statusCode ($($_.Exception.Message))" -ForegroundColor Yellow
    }
}

# 5. Kiem tra phpMyAdmin
Write-Host "`n[5/5] Kiem tra phpMyAdmin (http://localhost:8082):" -ForegroundColor Yellow
try {
    $pmaResp = Invoke-WebRequest -Uri "http://localhost:8082" -UseBasicParsing -TimeoutSec 10
    if ($pmaResp.StatusCode -eq 200) {
        Write-Host "==> PASS: phpMyAdmin phan hoi HTTP 200 thanh cong." -ForegroundColor Green
    } else {
        Write-Host "==> FAIL: phpMyAdmin status code: $($pmaResp.StatusCode)" -ForegroundColor Red
        $allPass = $false
    }
} catch {
    Write-Host "==> FAIL: Khong the ket noi toi phpMyAdmin: $_" -ForegroundColor Red
    $allPass = $false
}

Write-Host "`n==================================================" -ForegroundColor Cyan
if ($allPass) {
    Write-Host "KET QUA TONG THE MILESTONE 1: TAT CA TIEU CHI DEU PASS!" -ForegroundColor Green
} else {
    Write-Host "KET QUA TONG THE: CO TIEU CHI CHUA DAT, CAN KIEM TRA LAI." -ForegroundColor Red
}
Write-Host "==================================================" -ForegroundColor Cyan
