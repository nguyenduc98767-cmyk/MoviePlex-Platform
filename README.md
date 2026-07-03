# MoviePlex - Platform

Ứng dụng đặt vé xem phim nhóm xây dựng bằng PHP thuần, tổ chức theo mô hình MVC, chạy trên môi trường Docker (Apache + MySQL).

---

## Tính năng

- Đăng ký, đăng nhập, quên mật khẩu (gửi email qua SMTP)
- Xem danh sách phim, chi tiết phim, lịch chiếu theo rạp
- Chọn ghế, đặt vé, thanh toán, áp mã giảm giá (voucher)
- Quản lý vé cá nhân và hồ sơ người dùng
- Trang quản trị (Admin): quản lý phim, rạp, lịch chiếu, voucher, tài khoản, doanh thu, nhật ký hoạt động
- Giao diện nhân viên rạp

---

## Công nghệ sử dụng

| Thành phần       | Công nghệ                     |
| ---------------- | ----------------------------- |
| Backend          | PHP 8.2 (thuần)               |
| Frontend         | HTML / CSS / JavaScript thuần |
| Database         | MySQL 8                       |
| Web Server       | Apache 2 (mod_rewrite)        |
| Môi trường       | Docker + Docker Compose       |
| Gửi email        | PHPMailer (Composer)          |
| DB GUI           | phpMyAdmin                    |
| Quản lý mã nguồn | Git (GitHub) + SVN            |
| CI/CD            | GitHub Actions + ngrok        |

---

## Hạ tầng triển khai

Dự án chạy trên **máy chủ Linux (Ubuntu Server 24.04 LTS)** cài trong VirtualBox.

| Dịch vụ        | Mô tả                                                               |
| -------------- | ------------------------------------------------------------------- |
| SSH            | Đăng nhập và quản lý server từ xa                                   |
| SFTP           | Truyền file lên/xuống server qua SSH                                |
| SVN Server     | Quản lý mã nguồn tập trung (Apache + mod_dav_svn)                   |
| Docker         | Chạy web server (PHP/Apache) và database (MySQL) trong container    |
| ngrok          | Expose webhook server ra internet để nhận request từ GitHub Actions |
| GitHub Actions | Tự động deploy khi push code lên nhánh main                         |

---

## Cấu trúc thư mục

```
MoviePlex-Platform/
├── .github/
│   └── workflows/
│       └── deploy.yml      # CI/CD workflow
├── docker/
│   ├── mysql/
│   │   └── init.sql        # Schema và dữ liệu mẫu
│   └── php/
│       ├── Dockerfile
│       ├── apache.conf
│       └── php.ini
├── fe/                     # Frontend: pages, admin panel, components, assets
├── be/                     # Backend: config, routes, middleware, controllers, services, models, core
├── docker-compose.yml
├── composer.json
├── .env.example
└── README.md
```

---

## Yêu cầu môi trường

| Công cụ                        | Ghi chú                              |
| ------------------------------ | ------------------------------------ |
| VirtualBox                     | Chạy máy ảo Ubuntu Server            |
| Docker Engine + Docker Compose | Cài trên máy ảo Linux                |
| Git                            | Cài trên máy thật                    |
| TortoiseSVN                    | Cài trên máy thật (Windows)          |
| ngrok                          | Cài trên máy ảo Linux                |
| php-cli                        | Cài trên máy ảo Linux (chạy webhook) |

---

## Cài đặt lần đầu

### Bước 1 — SSH vào máy ảo từ máy thật

Mở CMD hoặc PowerShell trên Windows:

```cmd
ssh us@<IP_MÁY_ẢO>
```

Nhập mật khẩu khi được hỏi. Kiểm tra IP máy ảo bằng lệnh `hostname -I` trên máy ảo.

### Bước 2 — Clone project từ GitHub về máy ảo

```bash
cd ~
git clone https://github.com/DuyNam169/MoviePlex-Platform
cd MoviePlex-Platform
```

### Bước 3 — Tạo file `.env`

```bash
cp .env.example .env
```

Mở file `.env` và điền các giá trị:

```env
DB_HOST=mysql
DB_NAME=movieflex_db
DB_USER=movieplex_user
DB_PASSWORD=secret
MYSQL_ROOT_PASSWORD=rootsecret

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_FROM=your_email@gmail.com
```

### Bước 4 — Khởi động Docker

```bash
docker compose up -d --build
```

Lần đầu chạy sẽ mất vài phút để tải image và build. Docker tự động:

- Build PHP/Apache image từ `docker/php/Dockerfile`
- Khởi tạo database từ `docker/mysql/init.sql`
- Tạo volume `mysql_data` để lưu dữ liệu bền vững

### Bước 5 — Kiểm tra containers

```bash
docker compose ps
```

Ba container phải có trạng thái `running` / `healthy`:

```
movieplex_app   Up
movieplex_db    Up (healthy)
movieplex_pma   Up
```

### Bước 6 — Cài đặt webhook server (cho CI/CD)

```bash
sudo apt install php-cli -y
mkdir -p ~/webhook
cat > ~/webhook/deploy.php << 'EOF'
<?php
$secret = 'movieplex_secret_2026';
$sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');
if (!hash_equals('sha256=' . hash_hmac('sha256', $payload, $secret), $sig)) {
    http_response_code(403);
    exit('Forbidden');
}
shell_exec('cd /home/us/MoviePlex-Platform && git pull 2>&1 && docker compose restart php-apache 2>&1');
http_response_code(200);
echo 'Deployed!';
EOF
```

### Bước 7 — Cài đặt và cấu hình ngrok

```bash
# Cài ngrok
curl -sSL https://ngrok-agent.s3.amazonaws.com/ngrok.asc \
  | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null
echo "deb https://ngrok-agent.s3.amazonaws.com buster main" \
  | sudo tee /etc/apt/sources.list.d/ngrok.list
sudo apt update && sudo apt install ngrok -y

# Gắn authtoken (lấy tại https://dashboard.ngrok.com/your-authtoken)
ngrok config add-authtoken <YOUR_AUTHTOKEN>
```

### Bước 8 — Truy cập từ máy thật

| Dịch vụ    | URL                     |
| ---------- | ----------------------- |
| Ứng dụng   | http://<IP*MÁY*ẢO>:8080 |
| phpMyAdmin | http://<IP*MÁY*ẢO>:8081 |
| MySQL      | <IP*MÁY*ẢO>:3306        |

---

## Khởi động lại sau khi tắt máy ảo

Mỗi lần bật máy ảo lại, SSH vào rồi chạy 3 lệnh sau để mọi thứ hoạt động trở lại:

```bash
# 1. Khởi động Docker
cd ~/MoviePlex-Platform && docker compose up -d

# 2. Chạy webhook server nền
nohup php -S 0.0.0.0:9000 ~/webhook/deploy.php > ~/webhook/webhook.log 2>&1 &

# 3. Chạy ngrok tunnel (giữ terminal này mở)
ngrok http 9000 --domain unjogging-donnetta-orthographically.ngrok-free.dev
```

> ⚠️ Nếu dùng SVN, cần khởi động thêm Apache: `sudo systemctl start apache2`

---

## Workflow phát triển

GitHub là kho chính. SVN dùng để lưu trữ tập trung theo yêu cầu môn học. CI/CD tự động deploy lên máy ảo khi push lên nhánh `main`.

```
[Máy thật] Sửa code
     │
     ├─► git push → GitHub (nhánh main)
     │        │
     │        └─► GitHub Actions tự động chạy
     │                  │
     │                  └─► Gọi webhook → máy ảo tự git pull + restart Docker
     │
     └─► svn commit → SVN Server trên máy ảo (yêu cầu môn học)
```

---

## Cập nhật code lên GitHub

Thực hiện trên **máy thật**:

```bash
git add .
git commit -m "Mô tả thay đổi"
git push origin main
```

Sau khi push, GitHub Actions sẽ tự động:

1. Chạy workflow trong `.github/workflows/deploy.yml`
2. Gọi webhook đến máy ảo qua ngrok
3. Máy ảo tự `git pull` và `docker compose restart php-apache`

Kiểm tra kết quả tại: **GitHub repo → Actions**

---

## Cập nhật code lên SVN

> ⚠️ SVN không tự đồng bộ với GitHub. Phải thực hiện thủ công.

SSH vào máy ảo, rồi:

```bash
# Bước 1 — Khởi động Apache (bắt buộc mỗi lần dùng SVN)
sudo systemctl start apache2

# Bước 2 — Vào thư mục SVN đã checkout
cd ~/movieplex_svn

# Bước 3 — Cập nhật từ server về trước
svn update

# Bước 4 — Copy code mới từ GitHub vào thư mục SVN
cd ~
git clone https://github.com/DuyNam169/MoviePlex-Platform temp_new
cp -r temp_new/* ~/movieplex_svn/
rm -rf temp_new

# Bước 5 — Xem file nào thay đổi
cd ~/movieplex_svn
svn status

# Bước 6 — Commit lên SVN
svn commit --username sv01 -m "Mô tả thay đổi"
```

**Checkout SVN lần đầu** (nếu chưa có thư mục `movieplex_svn`):

```bash
svn checkout http://<IP_MÁY_ẢO>/svn/duan_phanmem/trunk movieplex_svn --username sv01
```

---

## Một số lệnh Docker hữu ích

```bash
# Xem container đang chạy
docker compose ps

# Xem log của web server
docker compose logs -f php-apache

# Truy cập shell bên trong container
docker exec -it movieplex_app bash

# Dừng tất cả container (giữ dữ liệu)
docker compose down

# Reset hoàn toàn — xóa cả dữ liệu database
docker compose down -v
docker compose up -d --build
```

---

## Truyền file lên server (SFTP)

SFTP chạy trên nền SSH, không cần cài thêm phần mềm. Mở CMD trên máy thật:

```cmd
sftp us@<IP_MÁY_ẢO>
```

Một số lệnh trong SFTP:

```
ls              # xem danh sách file trên server
put ten_file    # upload file từ máy thật lên server
get ten_file    # download file từ server về máy thật
exit            # thoát
```

---

## Quản lý SVN Server

| Thông tin | Giá trị                                   |
| --------- | ----------------------------------------- |
| SVN URL   | http://<IP*MÁY*ẢO>/svn/duan_phanmem/trunk |
| User sv01 | Quyền đọc/ghi toàn bộ repo                |
| User sv02 | Quyền đọc/ghi toàn bộ repo                |

---

## CI/CD — GitHub Actions

Flow tự động deploy khi push lên nhánh `main`:

```
Push code → GitHub Actions → Webhook (ngrok) → git pull + restart Docker
```

Cấu hình tại: `.github/workflows/deploy.yml`

Webhook server chạy trên máy ảo port `9000`, expose ra ngoài qua ngrok static domain.

Kiểm tra log webhook:

```bash
tail -f ~/webhook/webhook.log
```

---

## Thành viên nhóm
| Thành viên | Nhiệm vụ cụ thể | Mức độ hoàn thành |
|-------------|------------------------------|---------------------------------|
| **Bùi Duy Nam** (Trưởng nhóm) | Khảo sát yêu cầu, thiết kế CSDL tổng thể. Thiết lập Docker, luồng CI/CD (GitHub Actions, Webhook, ngrok). Phát triển các tính năng Đặt vé, Lịch chiếu, Xác nhận đặt vé. | 30% |
| **Trần Đức Phát** | Thiết kế UI và lập trình chức năng trang quản trị Admin (Quản lý phim, Quản lý rạp, Quản lý lịch chiếu, Quản lý tài khoản). | 25% |
| **Hoàng Văn Chương** | Phát triển chức năng cho khách hàng (Trang chủ, Chi tiết phim, Chọn ghế ngồi trực quan và Trang cá nhân). | 25% |
| **Nguyễn Vũ Đức** | Phát triển chức năng đăng nhập, đăng ký, quên mật khẩu, cấu hình gửi email bằng PHPMailer.| 20% |
---
## Ghi chú
<<<<<<< HEAD

anh nam

Dự án phục vụ mục đích học tập — nhóm môn học tại Trường Đại học Công nghệ Giao thông Vận tải (UTT).
=======


