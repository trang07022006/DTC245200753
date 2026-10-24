# Đề 31: Hệ thống Thư viện Ảnh Gallery (Gallery Photo Management System)

> **Học phần:** Triển khai và Quản trị Hệ thống Phần mềm
> **Sinh viên thực hiện:** Lê Minh Trang
> **Mã sinh viên:** DTC245200753
> **Lớp:** CNTT K23M
> **Khoa:** Công nghệ Thông tin

---

## 1. Giới thiệu Dự án

Hệ thống Thư viện Ảnh Gallery là ứng dụng web cho phép người dùng quản lý album ảnh, tải lên hình ảnh an toàn đa lớp, phân loại theo danh mục và tạo liên kết chia sẻ công khai có bảo vệ bằng token ngẫu nhiên.

Hệ thống được đóng gói và vận hành hoàn toàn tự động bằng **Docker Compose**, tuân thủ kiến trúc nhiều lớp (multi-tier), reverse proxy an toàn và tích hợp đầy đủ hệ sinh thái giám sát metrics (**Prometheus + Grafana + Exporters**) và thu thập log tập trung (**Grafana Loki + Promtail**).

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
                        HỆ THỐNG GIÁM SÁT & NHẬT KÝ (OBSERVABILITY STACK)
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
                                    │
   [ File Log Nginx & App ]         │
              │                     │
              ▼                     │
        ┌───────────┐               │
        │ Promtail  │               │
        └─────┬─────┘               │
              │ (Đẩy log theo batch)│
              ▼                     │
        ┌───────────┐               │
        │   Loki    │ (Cổng 3100)   │
        └─────┬─────┘               │
              │                     ▼ (Khai báo tự động Datasources & Dashboards)
              │          ┌─────────────────────┐
              └─────────►│   Grafana Server    │ (Cổng 3000, 12 Panels Metrics & Logs)
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
| **Grafana** | `gallery_grafana` | `grafana/grafana:10.4.2` | `3000` | `monitoring` | Bảng điều khiển tích hợp hiển thị Metrics & Live Logs |
| **Loki** | `gallery_loki` | `grafana/loki:2.9.8` | `127.0.0.1:3100` | `monitoring` | Lưu trữ và lập chỉ mục Log phân tán theo nhãn |
| **Promtail** | `gallery_promtail` | `grafana/promtail:2.9.8` | Nội bộ (`9080`) | `monitoring` | Thu thập log từ file Nginx & Apache đẩy về Loki |

> **Lưu ý chuyên môn về Promtail:**
> Theo lộ trình phát triển chính thức của Grafana Labs, Promtail đã công bố kế hoạch dừng hỗ trợ (End of Life - EOL) từ ngày 02/03/2026 để chuyển sang Grafana Alloy. Đồ án học phần sử dụng phiên bản ổn định `grafana/promtail:2.9.8` theo đúng chuẩn yêu cầu của đề tài, đồng thời đảm bảo tính sẵn sàng nâng cấp kiến trúc trong tương lai.

---

## 3. Cấu trúc Thư mục Dự án

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
├── logging/                                    # Cấu hình hệ thống thu thập Log
│   ├── loki/
│   │   └── loki-config.yaml                    # Cấu hình Grafana Loki (Filesystem, 7d retention)
│   └── promtail/
│       └── promtail-config.yaml                # Cấu hình Promtail (nhãn low-cardinality)
├── monitoring/
│   ├── prometheus/
│   │   └── prometheus.yml                      # Cấu hình chu kỳ cào và 4 nhóm targets
│   └── grafana/
│       └── provisioning/                       # Tự động hóa Grafana (Provisioning as Code)
│           ├── datasources/datasources.yml     # Nạp tự động Prometheus & Loki datasources
│           └── dashboards/
│               ├── dashboards.yml              # Khai báo provider nạp dashboard
│               └── json/gallery_dashboard.json # Dashboard 12 panels tích hợp Metrics & Logs
├── nginx/
│   ├── nginx.conf                              # Cấu hình chính Nginx và định dạng log chi tiết
│   └── conf.d/default.conf                     # Cấu hình Reverse Proxy & Security Headers
├── tests/                                      # Bộ kịch bản kiểm thử tự động
│   ├── test_milestone1.ps1                     # Kiểm thử Chặng 1 (Hạ tầng web & CSDL)
│   ├── test_stage2.ps1                         # Kiểm thử Chặng 2 (Nghiệp vụ thư viện ảnh)
│   ├── test_stage3_monitoring.ps1              # Kiểm thử Chặng 3 (Metrics Prometheus & Grafana)
│   └── test_stage4_logging.ps1                 # Kiểm thử Chặng 4 (Thu thập Log & LogQL)
├── .env.example                                # Mẫu biến môi trường
├── compose.yaml                                # Điều phối Docker Compose toàn diện 11 containers
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
Đảm bảo tất cả 11 container đều ở trạng thái `Up` hoặc `Up (healthy)`.

### Bước 3: Chạy Kiểm thử Tự động Chặng 4
```powershell
pwsh -File ./tests/test_stage4_logging.ps1
```

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
- **Bảng điều khiển Giám sát Grafana (Metrics & Logs):**
  [http://localhost:3000](http://localhost:3000)
  - Tên đăng nhập: `admin`
  - Mật khẩu: `Admin@Grafana2026!`
  - Dashboard: Vào menu **Dashboards** $\rightarrow$ chọn thư mục **Gallery Monitoring** $\rightarrow$ **Hệ Thống Thư Viện Ảnh Gallery - Giám Sát Tổng Hợp**
- **Máy chủ Prometheus:**
  [http://localhost:9090](http://localhost:9090)
  - Danh sách Targets: [http://localhost:9090/targets](http://localhost:9090/targets)
- **API Máy chủ Loki:**
  [http://localhost:3100/ready](http://localhost:3100/ready)
- **Tài khoản Quản trị viên (Admin) mặc định:**
  - Username: `admin`
  - Mật khẩu: `Admin@123456`

---

## 6. Danh mục Truy vấn LogQL Tiêu biểu

Hệ thống hỗ trợ truy vấn nhật ký trực tiếp qua Grafana Explore hoặc API Loki:

1. **Truy vấn theo nhãn dịch vụ (Stream filter):**
   ```logql
   {service="nginx"}
   ```
2. **Lọc dòng log phát sinh mã lỗi HTTP (Security & Failure filter):**
   ```logql
   {service="nginx"} |~ "(403|404|500)"
   ```
3. **Lọc nhật ký tải ảnh lên (Action filter):**
   ```logql
   {service="nginx"} |= "POST /upload.php"
   ```
4. **Đo tần suất yêu cầu HTTP từ nhật ký (Rate metric):**
   ```logql
   rate({service="nginx"}[1m])
   ```

---

## 7. Hướng dẫn Sao lưu & Phục hồi CSDL

### Sao lưu (Backup):
```bash
docker compose exec mysql mysqldump -u gallery_user -pGallery_SecurePass_2026! gallery_db > backup_gallery.sql
```

### Phục hồi (Restore):
```bash
docker compose exec -T mysql mysql -u gallery_user -pGallery_SecurePass_2026! gallery_db < backup_gallery.sql
```

---

## 8. Lệnh Dừng Hệ thống

- Dừng hệ thống nhưng **giữ nguyên dữ liệu** (ảnh, CSDL, lịch sử metrics & logs):
  ```bash
  docker compose down
  ```
- Dừng và xóa toàn bộ dữ liệu thử nghiệm:
  ```bash
  docker compose down -v
  ```
