# Đề 31: Hệ thống Thư viện Ảnh Gallery (Gallery Photo Management System)

> **Học phần:** Triển khai và Quản trị Hệ thống Phần mềm
> **Sinh viên thực hiện:** Lê Minh Trang
> **Mã sinh viên:** DTC245200753
> **Lớp:** CNTT K23M
> **Khoa:** Công nghệ Thông tin

---

## 1. Giới thiệu Dự án

Hệ thống Thư viện Ảnh Gallery là ứng dụng web cho phép người dùng quản lý album ảnh, tải lên hình ảnh an toàn đa lớp, phân loại theo danh mục và tạo liên kết chia sẻ công khai có bảo vệ bằng token ngẫu nhiên.

Hệ thống được đóng gói và vận hành hoàn toàn tự động bằng **Docker Compose**, tuân thủ kiến trúc nhiều lớp (multi-tier), reverse proxy an toàn và tích hợp đầy đủ hệ sinh thái giám sát metrics (**Prometheus + Grafana + Exporters**) và thu thập log.

### Kiến trúc tổng thể (Architecture Overview)

```text
[ Trình duyệt Client / Quản trị viên ]
                   │
                   ▼ HTTP (Cổng 80)
           ┌───────────────┐
           │     Nginx     │  ◄── Reverse Proxy, Security Headers, Chặn truy cập ảnh thô
           └───────┬───────┘
                   │ (Mạng: frontend)
                   ▼
           ┌───────────────┐
           │   App (PHP)   │  ◄── PHP 8.2 + Apache, Xử lý logic, Xác thực, Ghi file
           └───┬───────┬───┘
               │       │
(Ghi ảnh thô)  │       │ (Truy vấn SQL qua PDO)
               ▼       ▼
    ┌──────────────┐ ┌──────────────┐
    │Volume uploads│ │ MySQL 8.0 DB │  ◄── Lưu metadata, hash token, quan hệ album/ảnh
    └──────────────┘ └──────┬───────┘
                            │
                    ┌───────┴───────┐
                    │  phpMyAdmin   │  ◄── Web UI quản trị CSDL (Cổng 8082, bind localhost)
                    └───────────────┘

════════════════════════════════════════════════════════════════════════════════════════
                        HỆ THỐNG GIÁM SÁT (MONITORING STACK)
════════════════════════════════════════════════════════════════════════════════════════

  [ cAdvisor ]              [ nginx-exporter ]          [ mysqld-exporter ]
(CPU/RAM Container)        (HTTP Requests, Active)      (QPS, Buffer Pool, Threads)
        │                           │                           │
        └───────────────────────────┼───────────────────────────┘
                                    │ (Scrape metrics 5s/lần)
                                    ▼
                         ┌─────────────────────┐
                         │  Prometheus Server  │ (Cổng 9090, lưu trữ time-series)
                         └──────────┬──────────┘
                                    │
                                    ▼ (Khai báo tự động Datasource)
                         ┌─────────────────────┐
                         │   Grafana Server    │ (Cổng 3000, Dashboards trực quan hóa)
                         └─────────────────────┘
```

---

## 2. Bảng Dịch vụ & Cổng Truy cập Toàn Hệ Thống

| Dịch vụ | Tên Container | Image sử dụng | Cổng Host (Bên ngoài) | Mạng Docker | Mục đích |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Nginx** | `gallery_nginx` | `nginx:1.25.5-alpine` | `80` (HTTP) | `frontend`, `monitoring` | Reverse proxy đón request, gắn security headers, chặn ảnh thô |
| **App** | `gallery_app` | Build từ `app/Dockerfile` (PHP 8.2) | Nội bộ (`app:80`) | `frontend`, `backend` | Xử lý nghiệp vụ thư viện ảnh, phân quyền, API healthcheck |
| **MySQL** | `gallery_mysql` | `mysql:8.0.36` | Nội bộ (`mysql:3306`) | `backend` | Cơ sở dữ liệu quan hệ, lưu thông tin metadata, album, token |
| **phpMyAdmin** | `gallery_phpmyadmin` | `phpmyadmin:5.2.1-apache` | `127.0.0.1:8082` | `backend` | Công cụ trực quan quản trị CSDL cho sinh viên/giảng viên |
| **nginx-exporter** | `gallery_nginx_exporter` | `nginx/nginx-prometheus-exporter:1.1.0` | Nội bộ (`9113`) | `monitoring` | Thu thập metrics Web Server từ `/stub_status` |
| **mysqld-exporter**| `gallery_mysqld_exporter` | `prom/mysqld-exporter:v0.15.1` | Nội bộ (`9104`) | `backend`, `monitoring` | Thu thập metrics CSDL với tài khoản tối thiểu `exporter` |
| **cadvisor** | `gallery_cadvisor` | `gcr.io/cadvisor/cadvisor:v0.49.1` | Nội bộ (`8080`) | `monitoring` | Thu thập chỉ số CPU, RAM, Network của từng container |
| **Prometheus** | `gallery_prometheus` | `prom/prometheus:v2.51.2` | `127.0.0.1:9090` | `monitoring` | Máy chủ trung tâm lưu trữ và truy vấn time-series metrics |
| **Grafana** | `gallery_grafana` | `grafana/grafana:10.4.2` | `3000` | `monitoring` | Giao diện hiển thị Dashboard giám sát tổng thể hệ thống |

---

## 3. Cấu trúc Thư mục

```text
DTC245200753/
├── app/                                        # Ứng dụng Backend PHP
│   ├── Dockerfile                              # Đóng gói PHP 8.2 + Apache + GD + PDO
│   └── src/                                    # Mã nguồn ứng dụng
│       ├── config/database.php                 # Kết nối CSDL bằng PDO an toàn
│       ├── includes/                           # Module xác thực, bảo mật CSRF, header/footer
│       ├── uploads/                            # Thư mục lưu ảnh vật lý (có .htaccess chặn thực thi)
│       ├── albums.php                          # Quản lý Album (chặn xóa khi còn ảnh)
│       ├── categories.php                      # Quản lý Danh mục phân loại
│       ├── upload.php                          # Tải ảnh lên an toàn đa lớp
│       ├── gallery.php                         # Lưới ảnh, tìm kiếm, lọc, phân trang
│       ├── view.php                            # Stream ảnh có kiểm soát quyền
│       ├── manage_shares.php                   # Quản lý tạo và thu hồi liên kết token
│       ├── share.php                           # Giao diện Khách xem (View-Only)
│       ├── health.php                          # Endpoint kiểm tra trạng thái dịch vụ (JSON)
│       └── index.php                           # Trang chủ tổng quan & hạ tầng
├── database/
│   └── init.sql                                # Khởi tạo CSDL, ràng buộc khóa ngoại, tài khoản exporter
├── monitoring/
│   ├── prometheus/
│   │   └── prometheus.yml                      # Cấu hình chu kỳ cào và 4 nhóm targets
│   └── grafana/
│       └── provisioning/                       # Tự động hóa Grafana (Provisioning as Code)
│           ├── datasources/datasources.yml     # Khai báo tự động Datasource Prometheus
│           └── dashboards/
│               ├── dashboards.yml              # Khai báo provider nạp dashboard
│               └── json/gallery_dashboard.json # Dashboard 10 biểu đồ giám sát thực tế
├── nginx/
│   ├── nginx.conf                              # Cấu hình chính Nginx và định dạng log
│   └── conf.d/default.conf                     # Cấu hình Reverse Proxy & Security Headers
├── .env.example                                # Mẫu biến môi trường
├── compose.yaml                                # Điều phối Docker Compose toàn diện
└── README.md                                   # Tài liệu hướng dẫn sử dụng và kiểm thử
```

---

## 4. Hướng dẫn Cài đặt & Vận hành

### Yêu cầu tiên quyết
- Hệ điều hành: Windows 10/11 (bật WSL2) hoặc Linux / macOS.
- Đã cài đặt **Git**, **Docker** và **Docker Compose**.

### Bước 1: Khởi động Toàn bộ Hệ thống
Chạy lệnh sau tại thư mục gốc của dự án:
```bash
docker compose up -d --build
```

### Bước 2: Kiểm tra Trạng thái Dịch vụ
```bash
docker compose ps
```
Đảm bảo tất cả 9 container đều ở trạng thái `Up` hoặc `Up (healthy)`.

---

## 5. Danh sách Đường dẫn Demo & Tài khoản

- **Trang chủ Thư viện Ảnh (qua Nginx):**
  [http://localhost](http://localhost)
- **Thư viện Ảnh chính (Gallery Grid):**
  [http://localhost/gallery.php](http://localhost/gallery.php)
- **Kiểm tra Health Check (JSON):**
  [http://localhost/health.php](http://localhost/health.php)
- **Quản trị Cơ sở dữ liệu (phpMyAdmin):**
  [http://localhost:8082](http://localhost:8082)
  - Máy chủ: `mysql`
  - Người dùng: `gallery_user`
  - Mật khẩu: `Gallery_SecurePass_2026!`
- **Bảng điều khiển Giám sát Grafana:**
  [http://localhost:3000](http://localhost:3000)
  - Tên đăng nhập: `admin`
  - Mật khẩu: `Admin@Grafana2026!`
  - Dashboard: Vào menu **Dashboards** $\rightarrow$ chọn thư mục **Gallery Monitoring** $\rightarrow$ **Hệ Thống Thư Viện Ảnh Gallery - Giám Sát Tổng Hợp**
- **Máy chủ Prometheus:**
  [http://localhost:9090](http://localhost:9090)
  - Xem danh sách Targets: [http://localhost:9090/targets](http://localhost:9090/targets)
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

## 7. Lệnh Dừng Hệ thống

- Dừng hệ thống nhưng **giữ nguyên dữ liệu** (ảnh, CSDL, lịch sử metrics trong volume):
  ```bash
  docker compose down
  ```
- Dừng và xóa toàn bộ dữ liệu thử nghiệm:
  ```bash
  docker compose down -v
  ```
