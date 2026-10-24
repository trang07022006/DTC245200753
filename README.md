# Đề 31: Hệ thống Thư viện Ảnh Gallery (Gallery Photo Management System)

> **Học phần:** Triển khai và Quản trị Hệ thống Phần mềm
> **Sinh viên thực hiện:** Lê Minh Trang
> **Mã sinh viên:** DTC245200753
> **Lớp:** CNTT K23M
> **Khoa:** Công nghệ Thông tin

---

## 1. Giới thiệu Dự án

Hệ thống Thư viện Ảnh Gallery là ứng dụng web cho phép người dùng quản lý album ảnh, tải lên hình ảnh an toàn, phân loại theo danh mục và tạo liên kết chia sẻ công khai có bảo vệ bằng token ngẫu nhiên.

Hệ thống được đóng gói và vận hành hoàn toàn tự động bằng **Docker Compose**, tuân thủ kiến trúc nhiều lớp (multi-tier), reverse proxy an toàn và sẵn sàng cho hệ sinh thái quan sát (Prometheus, Grafana, Loki).

### Kiến trúc tổng thể (Architecture Overview)

```text
[ Trình duyệt Client / Admin ]
               │
               ▼ HTTP (Cổng 80)
       ┌───────────────┐
       │     Nginx     │  ◄── Reverse Proxy, Security Headers, Chặn truy cập ảnh thô
       └───────┬───────┘
               │ (Mạng nội bộ Docker: frontend)
               ▼
       ┌───────────────┐
       │   App (PHP)   │  ◄── PHP 8.2 + Apache, Xử lý logic, Xác thực, Ghi file
       └───┬───────┬───┘
           │       │
(Ghi ảnh)  │       │ (Truy vấn SQL qua PDO)
           ▼       ▼
┌──────────────┐ ┌──────────────┐
│Volume uploads│ │ MySQL 8.0 DB │  ◄── Lưu metadata, hash token, quan hệ album/ảnh
└──────────────┘ └──────┬───────┘
                        │
                ┌───────┴───────┐
                │  phpMyAdmin   │  ◄── Web UI quản trị CSDL (Cổng 8082, bind localhost)
                └───────────────┘
```

---

## 2. Bảng Dịch vụ & Cổng Truy cập

| Dịch vụ | Tên Container | Image sử dụng | Cổng Host (Bên ngoài) | Mạng Docker | Mục đích |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Nginx** | `gallery_nginx` | `nginx:1.25.5-alpine` | `80` (HTTP) | `frontend` | Reverse proxy đón request, gắn security headers, giới hạn kích thước upload |
| **App** | `gallery_app` | Build từ `app/Dockerfile` (PHP 8.2) | Nội bộ (`app:80`) | `frontend`, `backend` | Xử lý nghiệp vụ thư viện ảnh, phân quyền, API healthcheck |
| **MySQL** | `gallery_mysql` | `mysql:8.0.36` | Nội bộ (`mysql:3306`) | `backend` | Cơ sở dữ liệu quan hệ, lưu thông tin metadata, album, token |
| **phpMyAdmin** | `gallery_phpmyadmin` | `phpmyadmin:5.2.1-apache` | `127.0.0.1:8082` | `backend` | Công cụ trực quan quản trị CSDL cho sinh viên/giảng viên |

*(Lưu ý: Cổng MySQL `3306` không mở ra ngoài máy chủ để đảm bảo nguyên tắc an toàn tối thiểu - Principle of Least Privilege).*

---

## 3. Cấu trúc Thư mục

```text
DTC245200753/
├── app/                        # Ứng dụng Backend PHP
│   ├── Dockerfile              # Dockerfile đóng gói PHP 8.2 + Apache + GD + PDO
│   └── src/                    # Mã nguồn ứng dụng
│       ├── config/
│       │   └── database.php    # Kết nối CSDL bằng PDO an toàn
│       ├── uploads/            # Thư mục chứa ảnh vật lý (có .htaccess chặn thực thi)
│       ├── health.php          # Endpoint kiểm tra trạng thái dịch vụ (JSON)
│       └── index.php           # Giao diện chính kiểm tra nền tảng
├── database/
│   └── init.sql                # Script khởi tạo 5 bảng CSDL và seed data ban đầu
├── nginx/
│   ├── nginx.conf              # Cấu hình chính Nginx và định dạng log
│   └── conf.d/
│       └── default.conf        # Cấu hình Reverse Proxy & Security Headers
├── .env.example                # Mẫu biến môi trường (không chứa secret thật)
├── .gitignore                  # Loại trừ file nhạy cảm, log, và dữ liệu đĩa
├── compose.yaml                # Tệp cấu hình điều phối Docker Compose
└── README.md                   # Tài liệu hướng dẫn sử dụng và kiểm thử
```

---

## 4. Hướng dẫn Cài đặt & Vận hành

### Yêu cầu tiên quyết
- Hệ điều hành: Windows 10/11 (bật WSL2) hoặc Linux / macOS.
- Đã cài đặt **Git**, **Docker** và **Docker Compose**.

### Bước 1: Chuẩn bị Biến môi trường
Sao chép tệp mẫu `.env.example` thành `.env`:
```bash
cp .env.example .env
```
*(Trên Windows PowerShell: `Copy-Item .env.example .env`)*

### Bước 2: Khởi động Toàn bộ Hệ thống
Chạy lệnh sau tại thư mục gốc của dự án:
```bash
docker compose up -d --build
```

### Bước 3: Kiểm tra Trạng thái Dịch vụ
```bash
docker compose ps
```
Đảm bảo tất cả container đều ở trạng thái `Up` hoặc `Up (healthy)`.

---

## 5. Danh sách Đường dẫn Demo & Tài khoản

- **Trang chủ Thư viện Ảnh (qua Nginx):**
  [http://localhost](http://localhost)
- **Kiểm tra Health Check (JSON):**
  [http://localhost/health.php](http://localhost/health.php)
- **Quản trị Cơ sở dữ liệu (phpMyAdmin):**
  [http://localhost:8082](http://localhost:8082)
  - Máy chủ: `mysql`
  - Người dùng: `gallery_user`
  - Mật khẩu: `Gallery_SecurePass_2026!` (hoặc mật khẩu bạn đặt trong `.env`)
- **Tài khoản Quản trị viên (Admin) mặc định:**
  - Username: `admin`
  - Mật khẩu: `Admin@123456`

---

## 6. Hướng dẫn Sao lưu & Phục hồi CSDL

### Sao lưu (Backup):
```bash
docker compose exec mysql mysqldump -u gallery_user -pGallery_SecurePass_2026! gallery_db > backup_gallery.sql
```

### Phục hồi (Restore):
```bash
docker compose exec -T mysql mysql -u gallery_user -pGallery_SecurePass_2026! gallery_db < backup_gallery.sql
```

---

## 7. Lệnh Dừng và Xóa Hệ thống

- Dừng hệ thống nhưng **giữ nguyên dữ liệu** (ảnh và CSDL trong volume):
  ```bash
  docker compose down
  ```
- Dừng và xóa toàn bộ dữ liệu thử nghiệm (chỉ dùng khi muốn làm sạch hoàn toàn):
  ```bash
  docker compose down -v
  ```
