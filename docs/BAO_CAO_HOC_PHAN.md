# BÁO CÁO KẾT THÚC HỌC PHẦN
## TRIỂN KHAI VÀ QUẢN TRỊ HỆ THỐNG PHẦN MỀM

---

### **ĐỀ TÀI 31: HỆ THỐNG THƯ VIỆN ẢNH GALLERY (GALLERY PHOTO MANAGEMENT SYSTEM)**
### **KIẾN TRÚC NHIỀU LỚP DOCKER COMPOSE, GIÁM SÁT PROMETHEUS - GRAFANA, THU THẬP LOG LOKI VÀ GIA CỐ BẢO MẬT**

---

* **Sinh viên thực hiện:** Lê Minh Trang
* **Mã sinh viên:** DTC245200753
* **Lớp:** CNTT K23M
* **Khoa:** Công nghệ Thông tin
* **Học kỳ / Năm học:** 2025 - 2026
* **Kho lưu trữ mã nguồn (Git Remote):** [https://github.com/trang07022006/DTC245200753.git](https://github.com/trang07022006/DTC245200753.git)

---

## MỤC LỤC

1. [TỔNG QUAN HỆ THỐNG & YÊU CẦU ĐỀ TÀI](#1-tổng-quan-hệ-thống--yêu-cầu-đề-tài)
2. [THIẾT KẾ KIẾN TRÚC HỆ THỐNG & MÔ HÌNH MẠNG](#2-thiết-kế-kiến-trúc-hệ-thống--mô-hình-mạng)
3. [THIẾT KẾ CƠ SỞ DỮ LIỆU & RÀNG BUỘC BẢO TOÀN DỮ LIỆU](#3-thiết-kế-cơ-sở-dữ-liệu--ràng-buộc-bảo-toàn-dữ-liệu)
4. [TRIỂN KHAI NGHIỆP VỤ THƯ VIỆN ẢNH (PHP BACKEND)](#4-triển-khai-nghiệp-vụ-thư-viện-ảnh-php-backend)
5. [ĐÓNG GÓI HẠ TẦNG VỚI DOCKER & DOCKER COMPOSE](#5-đóng-gói-hạ-tầng-với-docker--docker-compose)
6. [HỆ THỐNG GIÁM SÁT SỐ LIỆU VỚI PROMETHEUS & GRAFANA](#6-hệ-thống-giám-sát-số-liệu-với-prometheus--grafana)
7. [HỆ THỐNG THU THẬP & PHÂN TÍCH NHẬT KÝ VỚI LOKI & PROMTAIL](#7-hệ-thống-thu-thập--phân-tích-nhật-ký-với-loki--promtail)
8. [CÁC BIỆN PHÁP GIA CỐ AN TOÀN HỆ THỐNG (HARDENING & SECURITY)](#8-các-biện-pháp-gia-cố-an-toàn-hệ-thống-hardening--security)
9. [BẢNG KẾT QUẢ KIỂM THỬ THỰC TẾ & CHỨNG CỨ VẬN HÀNH](#9-bảng-kết-quả-kiểm-thử-thực-tế--chứng-cứ-vận-hành)
10. [NGÂN HÀNG CÂU HỎI VÀ HƯỚNG DẪN TRẢ LỜI VẤN ĐÁP BẢO VỆ](#10-ngân-hàng-câu-hỏi-và-hướng-dẫn-trả-lời-vấn-đáp-bảo-vệ)

---

## 1. TỔNG QUAN HỆ THỐNG & YÊU CẦU ĐỀ TÀI

### 1.1. Đặt vấn đề
Trong bối cảnh điện toán đám mây và kiến trúc microservices/container hóa phát triển mạnh mẽ, việc triển khai và quản trị một hệ thống phần mềm không chỉ dừng lại ở việc viết mã nguồn chức năng (Functional Requirements), mà đòi hỏi kỹ sư hệ thống phải đảm bảo các yêu cầu phi chức năng (Non-functional Requirements):
- Tính sẵn sàng và khả năng tự động triển khai lại (*Reproducibility*).
- Khả năng quan sát toàn diện (*Observability*) bao gồm Metrics và Logs.
- An ninh an toàn thông tin nhiều lớp (*Defense-in-Depth Hardening*).
- Toàn vẹn dữ liệu nghiệp vụ (*Data Integrity*).

Đề tài **"Hệ thống Thư viện Ảnh Gallery" (Đề số 31)** giải quyết bài toán quản lý, lưu trữ, phân loại và chia sẻ hình ảnh số an toàn cho người dùng cá nhân và tổ chức, đồng thời xây dựng hoàn chỉnh hạ tầng giám sát vận hành chuẩn DevOps.

### 1.2. Mục tiêu kỹ thuật đạt được
1. **Kiến trúc phân tầng đóng gói trong Docker Compose**: Điều phối toàn diện 11 containers chuyên trách (Web Server, App Server, Database, phpMyAdmin, 3 Exporters, Prometheus, Grafana, Loki, Promtail) trên các mạng Docker cô lập (`frontend`, `backend`, `monitoring`).
2. **Nghiệp vụ Gallery an toàn**: Tải lên hình ảnh xác thực đa tầng (phần mở rộng, MIME type nhị phân `finfo`, phân tích kích thước ảnh `getimagesize`), đổi tên ngẫu nhiên hex 16-byte, ràng buộc bảo toàn album `ON DELETE RESTRICT`, chia sẻ ảnh công khai bằng token ngẫu nhiên băm SHA-256.
3. **Giám sát số liệu Metrics (Prometheus + Grafana)**: Thu thập chu kỳ 5 giây từ Web Server Nginx, CSDL MySQL 8.0, và tài nguyên phần cứng Container cAdvisor. Cấu hình tự động 100% qua **Provisioning as Code** với 12 biểu đồ trực quan.
4. **Thu thập nhật ký tập trung (Grafana Loki + Promtail)**: Tách luồng log Nginx Access, Error và PHP App Error; gắn nhãn tối ưu tránh bùng nổ chỉ mục (Low-cardinality labels); kiểm chứng 3 truy vấn LogQL thực tế.
5. **Gia cố an ninh (Hardening)**: Ẩn thông tin công nghệ (`server_tokens off`, `expose_php = Off`), thiết lập HTTP Security Headers, chặn duyệt trực tiếp thư mục `uploads/`, cấp quyền tối thiểu MySQL (`exporter` chỉ có `SELECT, PROCESS, REPLICATION CLIENT`), tiến trình Apache xử lý dưới quyền non-root `www-data` (UID 33).
6. **Quản lý phiên bản chuẩn mực trên Git**: Hoàn thành 3 mốc commit lớn trên remote repository GitHub chính chủ: [https://github.com/trang07022006/DTC245200753.git](https://github.com/trang07022006/DTC245200753.git).

---

## 2. THIẾT KẾ KIẾN TRÚC HỆ THỐNG & MÔ HÌNH MẠNG

### 2.1. Sơ đồ Kiến trúc Tổng thể (Architecture Diagram)

```text
══════════════════════════════════════════════════════════════════════════════════════════════════════
[ Trình duyệt Người dùng / Client ]                     [ Trình duyệt Quản trị viên / Dev ]
               │                                                          │
               │ HTTP (Cổng 80)                                           │ (Cổng 8082, 3000, 9090)
               ▼                                                          ▼
   ┌───────────────────────┐                                   ┌───────────────────────┐
   │     gallery_nginx     │ (Reverse Proxy, Cổng 80)          │  gallery_phpmyadmin   │ (Cổng 8082)
   │  - Gắn Security Header│                                   │  - Quản trị CSDL trực │
   │  - Chặn ảnh thô /up.. │                                   └──────────┬────────────┘
   └───────────┬───────────┘                                              │
               │ Mạng: frontend                                           │ Mạng: backend
               ▼                                                          ▼
   ┌───────────────────────┐                                   ┌───────────────────────┐
   │      gallery_app      │ (PHP 8.2 + Apache)                │     gallery_mysql     │ (MySQL 8.0)
   │  - Xử lý nghiệp vụ    │ ──── Truy vấn PDO an toàn ──────► │  - Cổng 3306 KHÔNG lộ │
   │  - Stream ảnh view.php│                                   │  - Ràng buộc RESTRICT │
   └───────────┬───────────┘                                   └──────────┬────────────┘
               │ Ghi file ảnh                                             │
               ▼                                                          │
   ┌───────────────────────┐                                              │
   │ Volume: uploads_data  │                                              │
   └───────────────────────┘                                              │
                                                                          │
══════════════════════════════════════════════════════════════════════════╪═══════════════════════════
                    HỆ THỐNG GIÁM SÁT & QUẢN TRỊ VẬN HÀNH (OBSERVABILITY STACK)
══════════════════════════════════════════════════════════════════════════╪═══════════════════════════
                                                                          │
  [ gallery_cadvisor ]         [ gallery_nginx_exporter ]       [ gallery_mysqld_exporter ]
  - Quét cgroup / Docker sock  - Cào /stub_status Nginx         - Tài khoản quyền tối thiểu
  - RAM / CPU containers       - Active, Reading, Total Req     - QPS, Threads, Buffer Pool
          │                                │                                  │
          └────────────────────────────────┼──────────────────────────────────┘
                                           │ Mạng: monitoring (Scrape mỗi 5s)
                                           ▼
                                ┌──────────────────────┐
                                │  gallery_prometheus  │ (Cổng 9090 - Lưu trữ Time-Series)
                                └──────────┬───────────┘
                                           │
   [ File Log Nginx & App ]                │
              │                            │
              ▼                            │
        ┌───────────┐                      │
        │ Promtail  │ (Đọc file volume)    │
        └─────┬─────┘                      │
              │ Push batch                 │
              ▼                            │
        ┌───────────┐                      ▼
        │   Loki    │ (Cổng 3100) ────► ┌──────────────────────┐
        │  (Chỉ mục │                   │   gallery_grafana    │ (Cổng 3000 - 12 Panels Dashboard)
        │   nhãn)   │                   │ - Provisioning Code  │
        └───────────┘                   └──────────────────────┘
══════════════════════════════════════════════════════════════════════════════════════════════════════
```

### 2.2. Phân vùng Mạng Docker (Network Segmentation)
Hệ thống thiết lập 3 mạng Bridge riêng biệt nhằm triệt để cô lập rủi ro:
1. **`frontend`**: Cầu nối giữa `gallery_nginx` và `gallery_app`. Người dùng bên ngoài chỉ có thể đi qua Nginx; không thể truy cập trực tiếp tới máy chủ Apache backend.
2. **`backend`**: Mạng nội bộ kết nối `gallery_app`, `gallery_phpmyadmin`, `gallery_mysqld_exporter` tới `gallery_mysql`. Mạng này hoàn toàn cách ly với Internet; cổng 3306 của CSDL không được công khai (không có lệnh `ports: - 3306:3306`).
3. **`monitoring`**: Mạng chuyên dụng cho giám sát, kết nối `gallery_prometheus`, `gallery_grafana`, `gallery_loki`, `gallery_promtail` và các Exporter (`nginx-exporter`, `cadvisor`, `mysqld-exporter`). Giúp tách biệt lưu lượng giám sát khỏi lưu lượng truy cập của người dùng.

---

## 3. THIẾT KẾ CƠ SỞ DỮ LIỆU & RÀNG BUỘC BẢO TOÀN DỮ LIỆU

### 3.1. Lược đồ Cơ sở dữ liệu (5 Bảng Quan hệ)
Cơ sở dữ liệu `gallery_db` sử dụng chuẩn bảng mã `utf8mb4` và collation `utf8mb4_unicode_ci` hỗ trợ đầy đủ tiếng Việt và ký tự đặc biệt:

```text
┌─────────────────┐       ┌─────────────────┐
│      users      │       │   categories    │
├─────────────────┤       ├─────────────────┤
│ id (PK)         │       │ id (PK)         │
│ username        │       │ name            │
│ password (hash) │       │ description     │
│ role            │       └────────┬────────┘
│ created_at      │                │ 1
└────────┬────────┘                │
         │ 1                       │
         │                         │
         ▼ n                       ▼ n
┌─────────────────┐ 1           n ┌─────────────────┐
│     albums      ├──────────────►│     photos      │
├─────────────────┤               ├─────────────────┤
│ id (PK)         │               │ id (PK)         │
│ user_id (FK)    │               │ album_id (FK)   │◄── ON DELETE RESTRICT
│ title           │               │ category_id (FK)│
│ description     │               │ file_name (hex) │
│ created_at      │               │ original_name   │
└────────┬────────┘               │ file_size       │
         │ 1                      │ mime_type       │
         │                        │ created_at      │
         ▼ n                      └─────────────────┘
┌─────────────────┐
│   share_links   │
├─────────────────┤
│ id (PK)         │
│ album_id (FK)   │
│ token_hash (SHA)│
│ expires_at      │
│ is_active       │
└─────────────────┘
```

### 3.2. Ràng buộc bảo toàn dữ liệu `ON DELETE RESTRICT`
* **Cú pháp thiết kế trong `database/init.sql`:**
  ```sql
  CONSTRAINT `fk_photos_album`
      FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`)
      ON DELETE RESTRICT ON UPDATE CASCADE
  ```
* **Ý nghĩa kỹ thuật và bảo vệ nghiệp vụ:**
  - Trong quan hệ giữa Album và Ảnh, nếu áp dụng `ON DELETE CASCADE`, khi người quản trị vô tình bấm xóa album, toàn bộ hàng trăm bức ảnh bên trong sẽ bị xóa tự động khỏi CSDL mà không có cảnh báo.
  - Ngược lại, với `ON DELETE RESTRICT`, CSDL MySQL sẽ chủ động từ chối lệnh xóa Album nếu bên trong album vẫn còn tồn tại ít nhất 1 bản ghi ảnh. Ứng dụng PHP bắt biệt lệ `PDOException` (mã lỗi SQLSTATE `23000`) và hiển thị thông báo thân thiện: *"Không thể xóa album vì vẫn còn chứa ảnh. Vui lòng di chuyển hoặc xóa hết ảnh trước!"*. Điều này bảo vệ an toàn tuyệt đối cho tài nguyên ảnh của người dùng.

### 3.3. Cơ chế mã hóa Token chia sẻ Album bằng SHA-256
* **Quy trình tạo:**
  1. Khi người dùng bấm tạo liên kết chia sẻ công khai, hệ thống sinh ra một chuỗi ngẫu nhiên bảo mật 64 ký tự hex (`bin2hex(random_bytes(32))`).
  2. Ứng dụng thực hiện hàm băm một chiều `hash('sha256', $plain_token)` và lưu chuỗi băm vào bảng `share_links.token_hash`.
  3. Đường dẫn gửi cho khách có dạng: `http://localhost/share.php?token=<plain_token>`.
* **Quy trình xác thực khi khách mở link:**
  1. Khách gửi `token` qua URL.
  2. Ứng dụng tính toán băm SHA-256 của token nhận được.
  3. Truy vấn CSDL đối chiếu chuỗi băm kèm kiểm tra thời hạn `expires_at > NOW()` và trạng thái `is_active = 1`.
* **Giá trị an ninh:** Kể cả khi kẻ tấn công có được toàn quyền đọc CSDL (SQL Dump leak), chúng cũng không thể đảo ngược chuỗi băm SHA-256 để lấy lại liên kết thực tế của người dùng.

---

## 4. TRIỂN KHAI NGHIỆP VỤ THƯ VIỆN ẢNH (PHP BACKEND)

### 4.1. Quy trình Kiểm duyệt Tải ảnh Lên Đa tầng (Defense-in-Depth Upload)
Để ngăn chặn triệt để lỗ hổng tải lên file mã độc (File Upload Vulnerability / Web Shell), mã nguồn [app/src/upload.php](file:///c:/Users/lletr/Downloads/DTC245200753/app/src/upload.php) áp dụng 4 lớp kiểm duyệt nghiêm ngặt:

1. **Lớp 1 - Kiểm tra phần mở rộng tệp (Extension Whitelist):**
   Chỉ chấp nhận đuôi tệp nằm trong danh sách an toàn: `jpg`, `jpeg`, `png`, `gif`, `webp`. Từ chối mọi file có đuôi kép (như `shell.php.jpg`) hoặc đuôi thực thi (`php`, `phtml`, `exe`, `sh`).
2. **Lớp 2 - Kiểm tra loại nội dung nhị phân thực tế (`finfo_file`):**
   Không tin tưởng tiêu đề `Content-Type` do trình duyệt client gửi lên (vì client có thể giả mạo bằng Burp Suite). Ứng dụng dùng hàm `finfo_file(FILEINFO_MIME_TYPE)` để đọc magic bytes ở đầu tệp tin, xác thực MIME phải thuộc: `image/jpeg`, `image/png`, `image/gif`, `image/webp`.
3. **Lớp 3 - Phân tích cấu trúc hình ảnh (`getimagesize`):**
   Hàm `getimagesize()` cố gắng giải mã kích thước dài/rộng của file ảnh. Nếu tệp tin chứa mã độc chèn vào metadata hoặc file giả mạo không đúng cấu trúc đồ họa, hàm sẽ trả về `false` và ứng dụng lập tức hủy file.
4. **Lớp 4 - Đổi tên tệp ngẫu nhiên (Random Hex Renaming):**
   Tên file lưu trữ vật lý trên đĩa cứng được đổi thành chuỗi ngẫu nhiên 32 ký tự hex (`bin2hex(random_bytes(16)) . '.' . $ext`), ví dụ: `7f0b238a040cb9227e710d782fcade9b.jpg`. Tên gốc của người dùng chỉ được lưu dưới dạng metadata trong CSDL. Điều này loại bỏ hoàn toàn nguy cơ ghi đè file hệ thống hoặc tấn công Directory Traversal thông qua tên tệp.

### 4.2. Cơ chế Stream Ảnh An toàn (`view.php`)
- Thư mục vật lý `/uploads/` bị Nginx chặn hoàn toàn truy cập trực tiếp (trả về 403 Forbidden).
- Mọi thao tác xem ảnh buộc phải đi qua [app/src/view.php](file:///c:/Users/lletr/Downloads/DTC245200753/app/src/view.php):
  1. Kiểm tra session đăng nhập của người dùng HOẶC token chia sẻ hợp lệ.
  2. Sử dụng `basename()` để lọc tên file, ngăn chặn kẻ xấu truyền tham số dạng `../../etc/passwd` (Path Traversal).
  3. Kiểm tra file tồn tại trên ổ cứng.
  4. Đọc file và xuất ra trình duyệt kèm các tiêu đề HTTP chuẩn: `Content-Type: image/...`, `Content-Length`, `Cache-Control: private, max-age=86400`.

### 4.3. Cơ chế Xóa ảnh Đồng bộ (Database & Physical Storage)
Khi người dùng bấm xóa ảnh tại [app/src/gallery.php](file:///c:/Users/lletr/Downloads/DTC245200753/app/src/gallery.php):
1. Hệ thống mở Transaction trong CSDL.
2. Truy vấn lấy đường dẫn file vật lý trên đĩa lưu trữ `uploads/<file_name>`.
3. Thực hiện câu lệnh SQL `DELETE FROM photos WHERE id = ?`.
4. Gọi hàm `unlink($filePath)` để xóa tệp tin vật lý khỏi Docker Volume.
5. Nếu xóa file hoặc xóa CSDL gặp lỗi, transaction sẽ tự động Rollback. Quy trình này đảm bảo không bao giờ để lại "file rác mồ côi" làm đầy ổ đĩa máy chủ.

---

## 5. ĐÓNG GÓI HẠ TẦNG VỚI DOCKER & DOCKER COMPOSE

### 5.1. Bảng Kê 11 Dịch vụ Container Toàn Hệ Thống

Toàn bộ hệ thống sử dụng các image chính thức với nhãn phiên bản cụ thể (pinned tags), tuyệt đối không dùng `:latest` nhằm đảm bảo tính ổn định và khả năng tái lập:

| STT | Tên Container | Image sử dụng | Cổng mở ra ngoài Host | Mạng Docker | Chức năng chi tiết |
| :---: | :--- | :--- | :--- | :--- | :--- |
| **1** | `gallery_mysql` | `mysql:8.0.36` | *Không mở* (Nội bộ 3306) | `backend` | CSDL MySQL 8.0 lưu trữ quan hệ và ràng buộc |
| **2** | `gallery_app` | Build PHP 8.2 Apache | *Không mở* (Nội bộ 80) | `frontend`, `backend` | Xử lý logic Gallery, xác thực, upload đa tầng |
| **3** | `gallery_phpmyadmin`| `phpmyadmin:5.2.1-apache`| `127.0.0.1:8082:80` | `backend` | Web UI quản trị CSDL cho sinh viên/giảng viên |
| **4** | `gallery_nginx` | `nginx:1.25.5-alpine` | `0.0.0.0:80:80` | `frontend`, `monitoring` | Reverse Proxy đón toàn bộ truy cập Client |
| **5** | `gallery_nginx_exporter` | `nginx/nginx-prometheus-exporter:1.1.0` | *Không mở* (Nội bộ 9113) | `monitoring` | Cào chỉ số Web Server từ `/stub_status` |
| **6** | `gallery_mysqld_exporter`| `prom/mysqld-exporter:v0.15.1` | *Không mở* (Nội bộ 9104) | `backend`, `monitoring` | Cào chỉ số CSDL qua quyền tối thiểu `exporter` |
| **7** | `gallery_cadvisor`| `gcr.io/cadvisor/cadvisor:v0.49.1`| *Không mở* (Nội bộ 8080) | `monitoring` | Thu thập chỉ số CPU, RAM, I/O của containers |
| **8** | `gallery_prometheus` | `prom/prometheus:v2.51.2` | `127.0.0.1:9090:9090` | `monitoring` | Máy chủ trung tâm lưu trữ time-series metrics |
| **9** | `gallery_grafana` | `grafana/grafana:10.4.2` | `0.0.0.0:3000:3000` | `monitoring` | Bảng điều khiển trực quan hóa Metrics & Logs |
| **10**| `gallery_loki` | `grafana/loki:2.9.8` | `127.0.0.1:3100:3100` | `monitoring` | Máy chủ lưu trữ và lập chỉ mục nhật ký |
| **11**| `gallery_promtail`| `grafana/promtail:2.9.8` | *Không mở* (Nội bộ 9080) | `monitoring` | Agent thu thập file log chuyển tiếp về Loki |

### 5.2. Quản lý Dữ liệu Bền vững với Docker Named Volumes
Hệ thống định nghĩa 7 named volumes để dữ liệu không bao giờ bị mất khi khởi động lại hoặc nâng cấp container:
- `gallery_mysql_data`: Lưu trữ dữ liệu bảng, chỉ mục và transaction log của MySQL.
- `gallery_uploads_data`: Lưu trữ các file ảnh thực tế tải lên.
- `gallery_prometheus_data`: Lưu trữ cơ sở dữ liệu chuỗi thời gian TSDB của Prometheus trong 7 ngày.
- `gallery_grafana_data`: Lưu trữ cấu hình tài khoản, session và trạng thái của Grafana.
- `gallery_loki_data`: Lưu trữ các chunk log nén và chỉ mục của Loki.
- `gallery_nginx_logs`: Volume chia sẻ chứa `gallery_access.log` và `gallery_error.log` giữa Nginx và Promtail.
- `gallery_app_logs`: Volume chia sẻ chứa `php_error.log` giữa PHP App và Promtail.

---

## 6. HỆ THỐNG GIÁM SÁT SỐ LIỆU VỚI PROMETHEUS & GRAFANA

### 6.1. Cơ chế Hoạt động của Prometheus
Prometheus sử dụng mô hình kéo (**Pull Model**) định kỳ gửi yêu cầu HTTP GET tới endpoint `/metrics` của các Exporter với chu kỳ `scrape_interval: 5s`. Cấu hình trong [monitoring/prometheus/prometheus.yml](file:///c:/Users/lletr/Downloads/DTC245200753/monitoring/prometheus/prometheus.yml) quản lý 4 nhóm Scrape Jobs:
1. `job_name: 'prometheus'` $\rightarrow$ Tự giám sát chính máy chủ Prometheus (`localhost:9090`).
2. `job_name: 'nginx'` $\rightarrow$ Cào số liệu từ `nginx-exporter:9113`.
3. `job_name: 'mysql'` $\rightarrow$ Cào số liệu từ `mysqld-exporter:9104`.
4. `job_name: 'cadvisor'` $\rightarrow$ Cào số liệu từ `cadvisor:8080`.

### 6.2. Triển khai Tự động Hóa (Provisioning as Code)
Không sử dụng thao tác thủ công bằng tay trên giao diện web, toàn bộ cấu hình hiển thị được nạp tự động khi container Grafana khởi chạy:
- **Datasources Provisioning ([datasources.yml](file:///c:/Users/lletr/Downloads/DTC245200753/monitoring/grafana/provisioning/datasources/datasources.yml))**: Tự động kết nối tới `http://prometheus:9090` (nguồn số liệu mặc định) và `http://loki:3100` (nguồn nhật ký).
- **Dashboards Provisioning ([dashboards.yml](file:///c:/Users/lletr/Downloads/DTC245200753/monitoring/grafana/provisioning/dashboards/dashboards.yml))**: Khai báo Provider nạp dashboard từ tệp JSON [gallery_dashboard.json](file:///c:/Users/lletr/Downloads/DTC245200753/monitoring/grafana/provisioning/dashboards/json/gallery_dashboard.json).

### 6.3. Danh mục 10 Biểu đồ Giám sát Số liệu (Metrics Panels)

| Panel ID | Tiêu đề Biểu đồ | Dạng hiển thị | Công thức PromQL sử dụng | Ý nghĩa Quản trị |
| :---: | :--- | :---: | :--- | :--- |
| **1** | Nginx Active Connections | Stat | `nginx_connections_active` | Số lượng kết nối client đồng thời đang mở vào web |
| **2** | Tổng Yêu Cầu HTTP | Stat | `nginx_http_requests_total` | Tổng số lượt request Nginx đã xử lý từ lúc bật |
| **3** | Lưu lượng HTTP theo Thời gian | TimeSeries | `rate(nginx_http_requests_total[1m])` | Tần suất request mỗi giây (Requests Per Second - RPS) |
| **4** | Trạng thái Kết nối Nginx | TimeSeries | `nginx_connections_reading`, `writing`, `waiting` | Phân tích tỉ lệ kết nối đọc, gửi dữ liệu và keep-alive |
| **5** | MySQL Threads Connected | Stat | `mysql_global_status_threads_connected` | Số lượng kết nối đồng thời đang mở vào CSDL MySQL |
| **6** | MySQL Uptime | Stat | `mysql_global_status_uptime` | Thời gian máy chủ MySQL hoạt động liên tục (giây) |
| **7** | MySQL Tần suất Truy vấn | TimeSeries | `rate(mysql_global_status_queries[1m])` | Số lượng câu truy vấn CSDL xử lý mỗi giây (QPS) |
| **8** | InnoDB Buffer Pool Usage | TimeSeries | `mysql_global_status_innodb_buffer_pool_bytes_data` | Dung lượng bộ nhớ đệm RAM MySQL đang dùng cho data |
| **9** | CPU Từng Container (%) | TimeSeries | `sum(rate(container_cpu_usage_seconds_total{name=~"gallery_.*"}[1m])) by (name) * 100` | Mức tải CPU theo thời gian thực của 11 container |
| **10**| RAM Từng Container (Bytes) | TimeSeries | `container_memory_usage_bytes{name=~"gallery_.*"}` | Mức độ tiêu thụ bộ nhớ RAM vật lý của từng container |

---

## 7. HỆ THỐNG THU THẬP & PHÂN TÍCH NHẬT KÝ VỚI LOKI & PROMTAIL

### 7.1. Kiến trúc Grafana Loki và So sánh với Elasticsearch
* **Nguyên lý hoạt động của Loki:**
  - Grafana Loki được mệnh danh là *"Prometheus for logs"*. Khác biệt căn bản với hệ sinh thái ELK (Elasticsearch/Logstash/Kibana): Elasticsearch giải nén toàn bộ văn bản và xây dựng bảng chỉ mục đảo (Inverted Index) trên từng từ ngữ, dẫn đến tốn kém tài nguyên RAM và dung lượng đĩa khổng lồ.
  - Ngược lại, **Loki KHÔNG đánh chỉ mục nội dung log**. Loki chỉ lập chỉ mục cho các **Nhãn Metadata (Labels)** giống hệt như Prometheus. Nội dung log thực tế được nén thành các chunk nhị phân và lưu trữ trên Filesystem. Khi người dùng thực hiện tìm kiếm, Loki phân vùng luồng log qua nhãn cực nhanh rồi dùng cơ chế grep phân tán tốc độ cao.
* **Nguyên tắc thiết kế nhãn Low-cardinality:**
  - Trong cấu hình [promtail-config.yaml](file:///c:/Users/lletr/Downloads/DTC245200753/logging/promtail/promtail-config.yaml), hệ thống chỉ sử dụng các nhãn tĩnh có số lượng biến thiên thấp: `job: nginx`, `service: nginx`, `env: production`, `log_type: access`.
  - Tuyệt đối không đưa địa chỉ IP client, User Agent hoặc Timestamp vào làm Label vì sẽ gây ra hiện tượng **Bùng nổ chỉ mục (Cardinality Explosion)** làm tê liệt Loki.

### 7.2. Xác minh 3 Truy vấn LogQL Thực tế

Hệ thống đã kiểm chứng và tự động hóa thành công 3 truy vấn LogQL phục vụ điều tra sự cố:

1. **Truy vấn 1: Lọc toàn bộ luồng nhật ký Web Nginx (Stream Filter):**
   ```logql
   {service="nginx"}
   ```
   *Mục đích:* Xem toàn bộ lịch sử truy cập web server theo thời gian thực.

2. **Truy vấn 2: Lọc các sự kiện bất thường và mã lỗi HTTP (Security & Failure Filter):**
   ```logql
   {service="nginx"} |~ "(403|404|500)"
   ```
   *Mục đích:* Phát hiện ngay lập tức các hành vi quét lỗ hổng, truy cập trang không tồn tại (404) hoặc cố tình vào thư mục cấm `/uploads/` (403 Forbidden).

3. **Truy vấn 3: Lọc chi tiết hành vi gọi kiểm tra sức khỏe hệ thống (Action Filter):**
   ```logql
   {service="nginx"} |= "health.php"
   ```
   *Mục đích:* Theo dõi tần suất và độ trễ phản hồi của endpoint giám sát sức khỏe ứng dụng `health.php`.

### 7.3. Nhận định Chuyên môn về Lộ trình EOL của Promtail
Theo thông báo chính thức từ Grafana Labs, công cụ Promtail đã có kế hoạch dừng phát triển và ngừng hỗ trợ (End of Life - EOL) từ ngày **02/03/2026** để nhường chỗ cho **Grafana Alloy** (bộ thu thập dữ liệu OpenTelemetry thế hệ mới). Trong khuôn khổ đồ án học phần, đồ án cài đặt phiên bản `grafana/promtail:2.9.8` nhằm đáp ứng chuẩn mực đề bài yêu cầu, đồng thời mã nguồn được cấu hình phân tách rõ ràng, sẵn sàng nâng cấp sang Grafana Alloy khi triển khai sản phẩm thực tế tại doanh nghiệp.

---

## 8. CÁC BIỆN PHÁP GIA CỐ AN TOÀN HỆ THỐNG (HARDENING & SECURITY)

Hệ thống triển khai toàn diện **7 biện pháp gia cố an ninh** từ tầng mạng, hệ điều hành đến ứng dụng:

| STT | Biện pháp Gia cố | Cơ chế Thực thi | Bằng chứng Thực nghiệm |
| :---: | :--- | :--- | :--- |
| **1** | **Cô lập CSDL MySQL** | Không khai báo `ports: - 3306:3306` trong `compose.yaml`. MySQL chỉ lắng nghe trên mạng nội bộ `backend`. | Lệnh `docker inspect gallery_mysql --format '{{json .NetworkSettings.Ports}}'` trả về `{"3306/tcp":null}`. |
| **2** | **Chặn truy cập trực tiếp Uploads** | Nginx chặn đường dẫn `location ^~ /uploads/ { return 403; }`. | Gửi lệnh `curl http://127.0.0.1/uploads/` nhận kết quả mã HTTP `403 Forbidden`. |
| **3** | **Thiết lập HTTP Security Headers** | Nginx tự động đính kèm `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`. | Quét header bằng `curl -I http://127.0.0.1/` hiển thị đầy đủ các tiêu đề bảo vệ chống Clickjacking và MIME-sniffing. |
| **4** | **Ẩn thông tin phiên bản phần mềm** | Nginx bật `server_tokens off;`, PHP kích hoạt `expose_php = Off;` trong `gallery-custom.ini`. | Header trả về chỉ hiển thị `Server: nginx` (không có số phiên bản), tiêu đề `X-Powered-By` bị loại bỏ hoàn toàn. |
| **5** | **Đặc quyền tối thiểu cho Exporter** | Tạo tài khoản MySQL riêng biệt `exporter` chỉ cấp quyền `PROCESS, SELECT, REPLICATION CLIENT` và giới hạn `MAX_USER_CONNECTIONS 3`. | Câu lệnh `SHOW GRANTS FOR 'exporter'@'%';` chứng minh tài khoản không có quyền `INSERT, UPDATE, DELETE, DROP` hay `ALL PRIVILEGES`. |
| **6** | **Khóa thực thi Script trong Uploads** | Đặt file `app/src/uploads/.htaccess` sử dụng `FilesMatch` khóa toàn bộ định dạng kịch bản `php|cgi|sh|py` và tắt `Options -Indexes`. | Máy chủ Apache từ chối thực thi bất kỳ tệp script nào nếu kẻ tấn công cố tình tải vào thư mục uploads. |
| **7** | **Thực thi dịch vụ Non-root** | Apache master khởi tạo dưới quyền root để bind cổng 80, nhưng toàn bộ worker process xử lý request chạy dưới user `www-data` (UID 33). | Lệnh `docker compose exec app ps aux` chứng minh 100% tiến trình phục vụ người dùng mang định danh `www-data`. |

---

## 9. BẢNG KẾT QUẢ KIỂM THỬ THỰC TẾ & CHỨNG CỨ VẬN HÀNH

Hệ thống được kiểm thử nghiêm ngặt thông qua 5 bộ kịch bản PowerShell tự động hóa với kết quả đạt chuẩn 100%:

| Bộ Kịch bản Kiểm thử | Mục tiêu & Phạm vi kiểm tra | Số bài test | Kết quả | Trạng thái Nghiệm thu |
| :--- | :--- | :---: | :---: | :---: |
| **`test_milestone1.ps1`** | Kiểm thử hạ tầng Chặng 1: Nginx, PHP App, MySQL, phpMyAdmin, Healthcheck, Security Headers | 5 / 5 | **PASS** | Hoàn thành Mốc Commit 1 |
| **`test_stage2.ps1`** | Kiểm thử nghiệp vụ Chặng 2: CSRF, Đăng nhập, Ràng buộc Album `RESTRICT`, Upload đa tầng, Stream `view.php`, Băm token SHA-256 | 8 / 8 | **PASS** | Hoàn thành Nghiệp vụ Gallery |
| **`test_stage3_monitoring.ps1`** | Kiểm thử giám sát Chặng 3: 4 Scrape Jobs Prometheus, Nạp tự động Grafana Provisioning, Tạo tải thực nghiệm (+55 requests) | 5 / 5 | **PASS** | Hoàn thành Mốc Commit 2 |
| **`test_stage4_logging.ps1`** | Kiểm thử nhật ký Chặng 4: Khởi chạy 11 containers, Thu thập Promtail, Xác minh 3 truy vấn LogQL thực tế | 6 / 6 | **PASS** | Hoàn thành Mốc Commit 3 |
| **`test_hardening.ps1`** | Kiểm chứng an ninh Chặng 5: Cô lập cổng DB, Chặn 403 uploads, Headers, Ẩn PHP, Quyền tối thiểu Exporter, Non-root workers | 7 / 7 | **PASS** | Hoàn thành Gia cố An ninh |
| **TỔNG HỢP TOÀN DỰ ÁN** | **Toàn bộ chức năng, hạ tầng, giám sát, nhật ký và bảo mật** | **31 / 31**| **100% PASS** | **ĐẠT TIÊU CHUẨN XUẤT SẮC** |

---

## 10. NGÂN HÀNG CÂU HỎI VÀ HƯỚNG DẪN TRẢ LỜI VẤN ĐÁP BẢO VỆ

Nhằm chuẩn bị tốt nhất cho buổi bảo vệ đồ án trước Hội đồng chấm thi, dưới đây là 10 câu hỏi trọng tâm thường gặp kèm hướng dẫn trả lời kỹ thuật chuẩn xác:

#### Câu 1: Tại sao hệ thống phải sử dụng Nginx làm Reverse Proxy mà không để người dùng truy cập trực tiếp vào Apache của PHP?
* **Trả lời:** Nginx đóng vai trò là "người gác cổng" duy nhất. Lợi ích:
  1. *Bảo mật:* Tập trung cấu hình Security Headers, ẩn giấu cấu trúc mạng nội bộ, chặn trực tiếp các truy vấn trái phép vào thư mục `/uploads/` mà không gây tốn tài nguyên cho backend PHP.
  2. *Hiệu năng:* Nginx xử lý kết nối đồng thời và phục vụ các file tĩnh cực kỳ tối ưu nhờ kiến trúc Event-driven phi đồng bộ.
  3. *Mở rộng:* Dễ dàng cấu hình cân bằng tải (Load Balancing) sang nhiều container PHP app trong tương lai.

#### Câu 2: Ràng buộc `ON DELETE RESTRICT` khác gì với `ON DELETE CASCADE` trong quan hệ giữa Album và Ảnh?
* **Trả lời:** `CASCADE` sẽ tự động xóa tất cả ảnh bên trong khi album bị xóa, gây nguy cơ mất mát dữ liệu nghiêm trọng nếu người dùng thao tác nhầm. Ngược lại, `RESTRICT` ngăn chặn hoàn toàn việc xóa album nếu album đó vẫn còn chứa ít nhất một bức ảnh, buộc người dùng phải di chuyển hoặc xóa ảnh trước, đảm bảo tính toàn vẹn dữ liệu.

#### Câu 3: Tại sao lại băm Token chia sẻ Album bằng thuật toán SHA-256 trước khi lưu vào CSDL?
* **Trả lời:** Áp dụng nguyên tắc phòng thủ khi rò rỉ dữ liệu. Khách truy cập liên kết với chuỗi token thô (plaintext token), nhưng CSDL chỉ lưu chuỗi băm SHA-256. Nếu cơ sở dữ liệu bị lộ, kẻ tấn công cũng không thể đảo ngược mã băm để tái tạo lại token hợp lệ nhằm truy cập trái phép vào các album riêng tư.

#### Câu 4: Ứng dụng kiểm duyệt tệp tin tải lên (Upload) như thế nào để chống mã độc Web Shell?
* **Trả lời:** Áp dụng kiểm duyệt đa tầng độc lập:
  1. Kiểm tra phần mở rộng (whitelist: jpg, png, gif, webp).
  2. Dùng `finfo_file` kiểm tra Magic Bytes nhị phân xác định MIME type thực sự của tệp.
  3. Dùng `getimagesize` giải mã cấu trúc hình ảnh để loại bỏ các tệp giả mạo.
  4. Đổi tên tệp thành chuỗi ngẫu nhiên hex 16-byte nhằm triệt tiêu nguy cơ chèn mã độc hoặc Directory Traversal qua tên file.
  5. Đặt file `.htaccess` trong thư mục uploads vô hiệu hóa hoàn toàn trình thông dịch kịch bản.

#### Câu 5: Mô hình thu thập Metrics của Prometheus là gì? Exporter đóng vai trò gì?
* **Trả lời:** Prometheus hoạt động theo mô hình Kéo (**Pull Model**) — định kỳ chủ động gửi request tới các mục tiêu để lấy dữ liệu. Exporter đóng vai trò là phần mềm trung gian dịch chuyển (translator): nó đọc dữ liệu trạng thái nội bộ từ ứng dụng mục tiêu (như `/stub_status` của Nginx hay `SHOW GLOBAL STATUS` của MySQL) và chuyển đổi thành định dạng chuẩn mà Prometheus hiểu được.

#### Câu 6: Ưu điểm cốt lõi của Grafana Provisioning as Code là gì?
* **Trả lời:** Thay vì phải đăng nhập giao diện web rồi dùng chuột cấu hình nguồn dữ liệu và vẽ từng biểu đồ một cách thủ công (dễ sai sót và tốn thời gian), Provisioning as Code cho phép định nghĩa toàn bộ Datasource và Dashboard dưới dạng tệp `yaml` và `json`. Nhờ đó, hệ thống có thể triển khai lại tự động bất kỳ lúc nào, đảm bảo tính nhất quán 100% giữa các môi trường và quản lý lịch sử thay đổi trên Git.

#### Câu 7: Khác biệt cốt lõi giữa Grafana Loki và Elasticsearch trong thu thập nhật ký là gì?
* **Trả lời:** Elasticsearch đánh chỉ mục toàn văn (Full-text index) trên toàn bộ nội dung của log, tiêu tốn rất nhiều RAM và ổ cứng. Grafana Loki chỉ đánh chỉ mục cho các nhãn siêu dữ liệu (Labels) giống như Prometheus, còn phần nội dung log được nén lại thành các chunk lưu trên ổ đĩa. Do đó, Loki cực kỳ nhẹ, tiết kiệm tài nguyên và dễ vận hành hơn nhiều.

#### Câu 8: Tại sao không nên đưa địa chỉ IP client hay Timestamp vào làm nhãn (Labels) trong Promtail?
* **Trả lời:** Vì việc này gây ra hiện tượng **Bùng nổ chỉ mục (Cardinality Explosion)**. Địa chỉ IP và thời gian là các giá trị biến thiên vô hạn. Nếu biến chúng thành nhãn, Loki sẽ phải tạo ra hàng triệu luồng log (streams) riêng biệt, dẫn đến tiêu tốn sạch bộ nhớ RAM và làm sập máy chủ quản lý log. Nhãn trong Loki chỉ nên là các giá trị hữu hạn và cố định như `job`, `service`, `env`.

#### Câu 9: Tại sao Promtail lại được ghi chú EOL trong báo cáo và giải pháp tương lai là gì?
* **Trả lời:** Grafana Labs đã công bố kế hoạch dừng hỗ trợ Promtail từ ngày 02/03/2026 để chuyển sang **Grafana Alloy** — giải pháp thu thập dữ liệu tập trung thế hệ mới dựa trên chuẩn OpenTelemetry. Đồ án học phần sử dụng Promtail 2.9.8 bám sát yêu cầu đề cương, đồng thời đã sẵn sàng kế hoạch di chuyển cấu hình sang Alloy khi đưa vào môi trường sản xuất thực tế.

#### Câu 10: Trình bày các nguyên tắc an ninh (Hardening) đã áp dụng cho CSDL MySQL trong dự án?
* **Trả lời:** Có 3 điểm then chốt:
  1. *Cô lập cổng:* Không mở cổng 3306 ra ngoài máy chủ Host; chỉ giao tiếp nội bộ trong mạng Docker `backend`.
  2. *Quyền tối thiểu:* Không dùng tài khoản `root` cho việc giám sát; tạo tài khoản `exporter` riêng biệt chỉ có quyền đọc (`PROCESS, SELECT, REPLICATION CLIENT`) và khống chế `MAX_USER_CONNECTIONS 3`.
  3. *Tách biệt tài khoản ứng dụng:* Ứng dụng Gallery sử dụng tài khoản `gallery_user` có mật khẩu mạnh, phân quyền đầy đủ trên `gallery_db` nhưng không có quyền can thiệp hệ thống máy chủ MySQL.

---

*Hết Báo cáo Học phần - Sinh viên thực hiện: **Lê Minh Trang - DTC245200753***
