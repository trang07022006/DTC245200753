# -------------------------------------------------------------
# DTC245200753 - Kịch bản kiểm thử toàn diện Nghiệp vụ Chặng 2 (curl.exe)
# -------------------------------------------------------------

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "KIEM THU TOAN DIEN CHUC NANG GALLERY (END-TO-END)" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan

$allPass = $true
$cookieFile = "$PSScriptRoot\cookie.txt"
Remove-Item $cookieFile -ErrorAction SilentlyContinue

# 1. Kiem tra trang Login va CSRF token
Write-Host "`n[1/8] Kiem tra trang dang nhap (http://localhost/login.php):" -ForegroundColor Yellow
$loginHtml = (curl.exe -s -c $cookieFile "http://localhost/login.php" | Out-String)
if ($loginHtml -match 'name="csrf_token" value="([a-f0-9]{64})"') {
    $csrfLogin = $matches[1]
    Write-Host "==> PASS: Lay duoc CSRF token: $($csrfLogin.Substring(0, 16))..." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Khong lay duoc CSRF token tu /login.php" -ForegroundColor Red
    $allPass = $false
}

# 2. Dang nhap Admin qua POST
Write-Host "`n[2/8] Dang nhap tai khoan Admin voi mat khau băm bcrypt:" -ForegroundColor Yellow
$loginResp = (curl.exe -s -i -b $cookieFile -c $cookieFile -d "csrf_token=$csrfLogin&username=admin&password=Admin@123456" "http://localhost/login.php" | Out-String)
if ($loginResp -match "HTTP/\S+ 302" -and $loginResp -match "Location: /gallery.php") {
    Write-Host "==> PASS: Dang nhap Admin thanh cong, Session cookie duoc cap phat." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Dang nhap that bai." -ForegroundColor Red
    $allPass = $false
}

# 3. Kiem tra chan Khach (Guest) truy cap trang quan tri
Write-Host "`n[3/8] Kiem tra chan Khach (Guest khong cookie) truy cap /upload.php:" -ForegroundColor Yellow
$guestResp = (curl.exe -s -i "http://localhost/upload.php" | Out-String)
if ($guestResp -match "HTTP/\S+ 302" -and $guestResp -match "Location: /login.php") {
    Write-Host "==> PASS: Khach chua dang nhap bi chan va chuyen huong ve /login.php." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Khach khong bi chan." -ForegroundColor Red
    $allPass = $false
}

# 4. Tao Album moi qua form POST
Write-Host "`n[4/8] Tao Album moi qua POST /albums.php:" -ForegroundColor Yellow
$albumHtml = (curl.exe -s -b $cookieFile -c $cookieFile "http://localhost/albums.php" | Out-String)
if ($albumHtml -match 'name="csrf_token" value="([a-f0-9]{64})"') {
    $albumCsrf = $matches[1]
} else {
    $albumCsrf = $csrfLogin
}

$createResp = curl.exe -s -i -b $cookieFile -c $cookieFile -d "csrf_token=$albumCsrf&action=create&name=Album+Kiem+Thu+Tu+Dong&description=Album+tao+boi+test" "http://localhost/albums.php"
$createdAlbumId = (docker compose exec -T mysql mysql -u gallery_user -pGallery_SecurePass_2026! gallery_db -N -e "SELECT id FROM albums WHERE name='Album Kiem Thu Tu Dong' ORDER BY id DESC LIMIT 1;" 2>$null | Select-Object -Last 1).Trim()

if ([int]$createdAlbumId -gt 0) {
    Write-Host "==> PASS: Album duoc tao thanh cong trong CSDL voi ID: $createdAlbumId." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Khong tim thay album moi trong CSDL." -ForegroundColor Red
    $allPass = $false
}

# 5. Upload file anh that qua HTTP Multipart Form
Write-Host "`n[5/8] Upload anh JPEG that qua HTTP Multipart Form (POST /upload.php):" -ForegroundColor Yellow
$uploadHtml = (curl.exe -s -b $cookieFile -c $cookieFile "http://localhost/upload.php" | Out-String)
if ($uploadHtml -match 'name="csrf_token" value="([a-f0-9]{64})"') {
    $uploadCsrf = $matches[1]
} else {
    $uploadCsrf = $albumCsrf
}
$samplePath = "$PSScriptRoot\sample.jpg"

$upResp = curl.exe -s -i -b $cookieFile -c $cookieFile -F "csrf_token=$uploadCsrf" -F "title=Anh Hoang Hon Da Nang" -F "album_id=$createdAlbumId" -F "category_id=1" -F "photo=@$samplePath;type=image/jpeg" "http://localhost/upload.php"

# Lay ID anh vua upload tu CSDL
$photoRow = (docker compose exec -T mysql mysql -u gallery_user -pGallery_SecurePass_2026! gallery_db -N -e "SELECT id, stored_filename FROM photos WHERE title='Anh Hoang Hon Da Nang' ORDER BY id DESC LIMIT 1;" 2>$null | Select-Object -Last 1).Trim()
$parts = $photoRow -split "\s+"
$createdPhotoId = $parts[0]
$storedFilename = $parts[1]

# Kiem tra file vat ly ben trong container
$fileCheck = (docker compose exec -T app ls -la "/var/www/html/uploads/$storedFilename" 2>$null | Out-String)

if ([int]$createdPhotoId -gt 0 -and $fileCheck -match $storedFilename) {
    Write-Host "==> PASS: Upload anh thanh cong! Photo ID: $createdPhotoId, File vat ly: $storedFilename." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Upload that bai." -ForegroundColor Red
    $allPass = $false
}

# 6. Kiem tra Phuc vu anh an toan (view.php vs /uploads/)
Write-Host "`n[6/8] Kiem tra phuc vu anh an toan qua /view.php:" -ForegroundColor Yellow
# A. Truc tiep vao /uploads/ -> Bi chan 403 Forbidden
$directResp = (curl.exe -s -i "http://localhost/uploads/$storedFilename" | Out-String)
if ($directResp -match "HTTP/\S+ 403") {
    Write-Host "==> PASS: Truy cap truc tiep /uploads/$storedFilename bi Nginx chan 403 Forbidden." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Thu muc uploads khong bi chan!" -ForegroundColor Red
    $allPass = $false
}

# B. Admin xem qua /view.php?id=... -> 200 OK image/jpeg
$adminView = (curl.exe -s -i -b $cookieFile "http://localhost/view.php?id=$createdPhotoId" | Out-String)
if ($adminView -match "HTTP/\S+ 200" -and $adminView -match "Content-Type: image/jpeg") {
    Write-Host "==> PASS: Admin xem duoc anh qua /view.php voi Content-Type: image/jpeg." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Admin khong xem duoc anh qua /view.php." -ForegroundColor Red
    $allPass = $false
}

# C. Khach xem qua /view.php khong token -> 403 Forbidden
$guestView = (curl.exe -s -i "http://localhost/view.php?id=$createdPhotoId" | Out-String)
if ($guestView -match "HTTP/\S+ 403") {
    Write-Host "==> PASS: Khach xem anh rieng tu khong token bi tu choi 403 Forbidden." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Khach khong bi chan khi xem anh rieng tu." -ForegroundColor Red
    $allPass = $false
}

# 7. Kiem tra Tao Token chia se va Thu hoi (Revoke)
Write-Host "`n[7/8] Kiem tra Tao Token chia se ngau nhien va Thu hoi lien ket:" -ForegroundColor Yellow
$shareHtml = (curl.exe -s -b $cookieFile -c $cookieFile "http://localhost/manage_shares.php" | Out-String)
if ($shareHtml -match 'name="csrf_token" value="([a-f0-9]{64})"') {
    $shareCsrf = $matches[1]
} else {
    $shareCsrf = $uploadCsrf
}

# Tao link chia se cho anh
$createShare = curl.exe -s -i -b $cookieFile -c $cookieFile -d "csrf_token=$shareCsrf&action=create&target_type=photo&target_id=$createdPhotoId" "http://localhost/manage_shares.php"

# Lay ID share link tu CSDL
$shareId = (docker compose exec -T mysql mysql -u gallery_user -pGallery_SecurePass_2026! gallery_db -N -e "SELECT id FROM share_links WHERE photo_id=$createdPhotoId AND revoked_at IS NULL ORDER BY id DESC LIMIT 1;" 2>$null | Select-Object -Last 1).Trim()

# Lay raw token tu trang flash message
$sharesManageHtml = (curl.exe -s -b $cookieFile "http://localhost/manage_shares.php" | Out-String)
if ($sharesManageHtml -match 'share\.php\?token=([a-f0-9]{64})') {
    $rawToken = $matches[1]
    Write-Host "Lay duoc Raw Token chia se: $($rawToken.Substring(0, 16))..."

    # Khach truy cap bang Token hop le
    $guestShareResp = (curl.exe -s -i "http://localhost/share.php?token=$rawToken" | Out-String)
    $guestPhotoResp = (curl.exe -s -i "http://localhost/view.php?id=$createdPhotoId&token=$rawToken" | Out-String)

    if ($guestShareResp -match "HTTP/\S+ 200" -and $guestPhotoResp -match "Content-Type: image/jpeg") {
        Write-Host "==> PASS: Khach xem duoc ca trang chia se va anh thong qua Token hop le." -ForegroundColor Green
    } else {
        Write-Host "==> FAIL: Khach khong xem duoc qua token hop le." -ForegroundColor Red
        $allPass = $false
    }

    # Thu hoi lien ket chia se (Revoke)
    $revokeResp = curl.exe -s -i -b $cookieFile -c $cookieFile -d "csrf_token=$shareCsrf&action=revoke&id=$shareId" "http://localhost/manage_shares.php"
    $afterRevokeShare = (curl.exe -s "http://localhost/share.php?token=$rawToken" | Out-String)
    $afterRevokePhoto = (curl.exe -s -i "http://localhost/view.php?id=$createdPhotoId&token=$rawToken" | Out-String)

    if (($afterRevokeShare -match "THU H" -or $afterRevokeShare -match "Không Thể Truy Cập") -and $afterRevokePhoto -match "HTTP/\S+ 403") {
        Write-Host "==> PASS: Thu hoi thanh cong! Khach bi chan ngay lap tuc sau khi thu hoi (403 Forbidden)." -ForegroundColor Green
    } else {
        Write-Host "==> FAIL: Thu hoi chua chan duoc truy cap." -ForegroundColor Red
        $allPass = $false
    }
} else {
    Write-Host "==> FAIL: Khong lay duoc raw token chia se." -ForegroundColor Red
    $allPass = $false
}

# 8. Kiem tra Ràng buộc chan xoa Album con anh (ON DELETE RESTRICT)
Write-Host "`n[8/8] Kiem tra Ràng buộc chan xoa Album con chua anh:" -ForegroundColor Yellow
$delAlbumResp = curl.exe -s -i -b $cookieFile -c $cookieFile -d "csrf_token=$albumCsrf&action=delete&id=$createdAlbumId" "http://localhost/albums.php"
$checkAlbum = (docker compose exec -T mysql mysql -u gallery_user -pGallery_SecurePass_2026! gallery_db -N -e "SELECT COUNT(*) FROM albums WHERE id=$createdAlbumId;" 2>$null | Select-Object -Last 1).Trim()

if ($checkAlbum -eq "1") {
    Write-Host "==> PASS: He thong TU CHOI xoa album vi con chua anh (Bao ve toan ven du lieu)." -ForegroundColor Green
} else {
    Write-Host "==> FAIL: Album bi xoa mat!" -ForegroundColor Red
    $allPass = $false
}

# Don dep: Xoa anh roi moi xoa album
$galleryHtml = (curl.exe -s -b $cookieFile "http://localhost/gallery.php" | Out-String)
if ($galleryHtml -match 'name="csrf_token" value="([a-f0-9]{64})"') {
    $galleryCsrf = $matches[1]
} else {
    $galleryCsrf = $albumCsrf
}
$delPhotoResp = curl.exe -s -i -b $cookieFile -c $cookieFile -d "csrf_token=$galleryCsrf&action=delete&id=$createdPhotoId" "http://localhost/gallery.php"

# Kiem tra file vat ly da bi xoa khoi /uploads/
$fileAfterDel = (docker compose exec -T app ls -la "/var/www/html/uploads/$storedFilename" 2>$null | Out-String)
if (-not $fileAfterDel.Trim()) {
    Write-Host "==> PASS: Khi xoa anh, file vat ly tren dia cung duoc dọn dẹp sach se (Chong file mo coi)." -ForegroundColor Green
}

# Xoa album rong
$delEmptyResp = curl.exe -s -i -b $cookieFile -c $cookieFile -d "csrf_token=$albumCsrf&action=delete&id=$createdAlbumId" "http://localhost/albums.php"
$checkEmpty = (docker compose exec -T mysql mysql -u gallery_user -pGallery_SecurePass_2026! gallery_db -N -e "SELECT COUNT(*) FROM albums WHERE id=$createdAlbumId;" 2>$null | Select-Object -Last 1).Trim()
if ($checkEmpty -eq "0") {
    Write-Host "==> PASS: Sau khi album rong, lenh xoa album thanh cong." -ForegroundColor Green
}

Remove-Item $cookieFile -ErrorAction SilentlyContinue

Write-Host "`n==================================================" -ForegroundColor Cyan
if ($allPass) {
    Write-Host "KET QUA TONG THE CHIEU SAU: TAT CA 8/8 TIEU CHI DEU PASS!" -ForegroundColor Green
} else {
    Write-Host "KET QUA TONG THE: CO TIEU CHI CHUA DAT, CAN KIEM TRA." -ForegroundColor Red
}
Write-Host "==================================================" -ForegroundColor Cyan
