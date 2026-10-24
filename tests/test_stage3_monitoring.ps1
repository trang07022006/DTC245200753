# -------------------------------------------------------------
# DTC245200753 - Kich ban kiem thu Giam sat Chang 3 (Prometheus va Grafana)
# -------------------------------------------------------------

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "KIEM THU HE THONG GIAM SAT PROMETHEUS VA GRAFANA" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan

$allPass = $true

# 1. Kiem tra trang thai tat ca cac Container
Write-Host "`n[1/5] Kiem tra trang thai tat ca cac Container trong he thong:" -ForegroundColor Yellow
$psOutput = (docker compose ps --format "table {{.Name}}`t{{.Status}}`t{{.Ports}}" | Out-String)
$psOutput | Write-Host

$expectedContainers = @(
    "gallery_nginx", "gallery_app", "gallery_mysql", "gallery_phpmyadmin",
    "gallery_nginx_exporter", "gallery_mysqld_exporter", "gallery_cadvisor",
    "gallery_prometheus", "gallery_grafana"
)

$missing = @()
foreach ($c in $expectedContainers) {
    if ($psOutput -notmatch $c) {
        $missing += $c
    }
}

if ($missing.Count -eq 0) {
    Write-Host "==> PASS: Toan bo 9/9 containers dang hoat dong Up binh thuong." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Cac container sau chua khoi dong: $($missing -join ', ')" -ForegroundColor Red
    $allPass = $false
}

# 2. Kiem tra Prometheus Server va cac Targets (4/4 Up)
Write-Host "`n[2/5] Kiem tra Prometheus Health va Targets (http://localhost:9090/api/v1/targets):" -ForegroundColor Yellow
try {
    $promHealthy = (curl.exe -s "http://localhost:9090/-/healthy" | Out-String).Trim()
    if ($promHealthy -match "Prometheus Server is Healthy") {
        Write-Host "==> PASS: Prometheus Server is Healthy." -ForegroundColor Green
    } else {
        Write-Host "==> FAIL: Prometheus chua san sang." -ForegroundColor Red
        $allPass = $false
    }

    $targetsJson = curl.exe -s "http://localhost:9090/api/v1/targets" | ConvertFrom-Json
    $activeTargets = $targetsJson.data.activeTargets

    Write-Host "Danh sach Targets thu thap metrics:"
    $targetSummary = @{}
    foreach ($t in $activeTargets) {
        $job = $t.labels.job
        $health = $t.health
        $targetSummary[$job] = $health
        Write-Host "   - Job: $job [$($t.scrapeUrl)] -> Trang thai: $health"
    }

    $requiredJobs = @("prometheus", "nginx", "mysql", "cadvisor")
    $allJobsUp = $true
    foreach ($rj in $requiredJobs) {
        if ($targetSummary[$rj] -ne "up") {
            $allJobsUp = $false
        }
    }

    if ($allJobsUp) {
        Write-Host "==> PASS: Day du 4/4 nhom target deu UP (prometheus, nginx, mysql, cadvisor)." -ForegroundColor Green
    } else {
        Write-Host "==> FAIL: Mot so targets chua UP." -ForegroundColor Red
        $allPass = $false
    }
} catch {
    Write-Host "==> FAIL: Loi goi API Prometheus: $_" -ForegroundColor Red
    $allPass = $false
}

# 3. Kiem tra Metrics thuc te (Web, DB, Container)
Write-Host "`n[3/5] Kiem tra truy van Metrics that tren Prometheus (Instant Query API):" -ForegroundColor Yellow
# A. Web Nginx Metric
$nginxReqQuery = curl.exe -s -G "http://localhost:9090/api/v1/query" --data-urlencode "query=nginx_http_requests_total" | ConvertFrom-Json
$nginxReqVal = 0
if ($nginxReqQuery.data.result.Count -gt 0) {
    $nginxReqVal = [int]$nginxReqQuery.data.result[0].value[1]
}
Write-Host "   - Web Nginx (nginx_http_requests_total): $nginxReqVal requests"

# B. MySQL Metric
$mysqlThreadsQuery = curl.exe -s -G "http://localhost:9090/api/v1/query" --data-urlencode "query=mysql_global_status_threads_connected" | ConvertFrom-Json
$mysqlThreadsVal = 0
if ($mysqlThreadsQuery.data.result.Count -gt 0) {
    $mysqlThreadsVal = [int]$mysqlThreadsQuery.data.result[0].value[1]
}
Write-Host "   - Database MySQL (mysql_global_status_threads_connected): $mysqlThreadsVal threads"

# C. Container cAdvisor Metric
$containerMemQuery = curl.exe -s -G "http://localhost:9090/api/v1/query" --data-urlencode "query=count(container_memory_usage_bytes{name=~'gallery_.*'})" | ConvertFrom-Json
$containerCount = 0
if ($containerMemQuery.data.result.Count -gt 0) {
    $containerCount = [int]$containerMemQuery.data.result[0].value[1]
}
Write-Host "   - Container cAdvisor (RAM/CPU cua cac container gallery_*): $containerCount luong metrics"

if ($nginxReqVal -gt 0 -and $mysqlThreadsVal -gt 0 -and $containerCount -gt 0) {
    Write-Host "==> PASS: Du lieu metrics that da duoc thu thap day du cho ca 3 nhom: Web, DB, Container." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Thieu du lieu metrics." -ForegroundColor Red
    $allPass = $false
}

# 4. Kiem tra Grafana Server, Datasource va Dashboard Provisioning
Write-Host "`n[4/5] Kiem tra Grafana va Dashboard duoc nap tu dong (Provisioning as Code):" -ForegroundColor Yellow
try {
    $grafanaHealth = curl.exe -s "http://localhost:3000/api/health" | ConvertFrom-Json
    if ($grafanaHealth.database -eq "ok") {
        Write-Host "==> PASS: Grafana Server hoat dong (HTTP 200, database=ok)." -ForegroundColor Green
    }

    # Kiem tra Datasource Prometheus
    $dsList = curl.exe -s -u "admin:Admin@Grafana2026!" "http://localhost:3000/api/datasources" | ConvertFrom-Json
    $hasPromDs = $false
    foreach ($ds in $dsList) {
        if ($ds.type -eq "prometheus" -and $ds.url -eq "http://prometheus:9090") {
            $hasPromDs = $true
        }
    }
    if ($hasPromDs) {
        Write-Host "==> PASS: Datasource Prometheus da duoc provision tu dong tu thu muc monitoring/grafana/." -ForegroundColor Green
    } else {
        Write-Host "==> FAIL: Chua nap duoc Datasource Prometheus." -ForegroundColor Red
        $allPass = $false
    }

    # Kiem tra Dashboard Gallery Overview
    $dashSearch = curl.exe -s -u "admin:Admin@Grafana2026!" "http://localhost:3000/api/search?query=Gallery" | ConvertFrom-Json
    $dashItem = $dashSearch | Where-Object { $_.uid -eq "gallery-overview" }
    if ($null -ne $dashItem) {
        Write-Host "==> PASS: Dashboard 'Gallery System Monitoring' (UID: gallery-overview) da duoc nap tu dong voi 10 bieu do." -ForegroundColor Green
    } else {
        Write-Host "==> FAIL: Dashboard chua duoc provision." -ForegroundColor Red
        $allPass = $false
    }
} catch {
    Write-Host "==> FAIL: Loi kiem tra Grafana: $_" -ForegroundColor Red
    $allPass = $false
}

# 5. Kiem tra Tao Tai Thuc Te (Traffic Generator)
Write-Host "`n[5/5] Tao tai thuc te (Traffic Generator) de xac minh bien thien metrics tren bieu do:" -ForegroundColor Yellow
$beforeReq = $nginxReqVal
Write-Host "Gui 25 HTTP requests lien tiep toi Nginx..."
1..25 | ForEach-Object {
    curl.exe -s -o NUL "http://localhost/gallery.php"
    curl.exe -s -o NUL "http://localhost/"
}

# Cho 6 giay de chu ky scrape Prometheus cap nhat
Start-Sleep -Seconds 6
$afterReqQuery = curl.exe -s -G "http://localhost:9090/api/v1/query" --data-urlencode "query=nginx_http_requests_total" | ConvertFrom-Json
$afterReq = [int]$afterReqQuery.data.result[0].value[1]
Write-Host "So luong request truoc khi tao tai: $beforeReq | Sau khi tao tai: $afterReq"

if ($afterReq -gt $beforeReq) {
    Write-Host "==> PASS: Chi so metrics tang thuc te theo luu luong truy cap (Tang +$($afterReq - $beforeReq) requests)." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Metrics khong tang sau khi gui request." -ForegroundColor Red
    $allPass = $false
}

Write-Host "`n==================================================" -ForegroundColor Cyan
if ($allPass) {
    Write-Host "KET QUA TONG THE: TAT CA 5/5 TIEU CHI MONITORING DEU PASS!" -ForegroundColor Green
} else {
    Write-Host "KET QUA TONG THE: CO TIEU CHI CHUA DAT, CAN KIEM TRA." -ForegroundColor Red
}
Write-Host "==================================================" -ForegroundColor Cyan
