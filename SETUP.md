# 🚀 Hướng dẫn cài đặt và chạy dự án

## 📦 Cài đặt PHP trên Windows

### Cách 1: Sử dụng XAMPP (Khuyến nghị cho người mới)

1. **Download XAMPP:**
   - Truy cập: https://www.apachefriends.org/download.html
   - Tải phiên bản XAMPP với PHP 8.x

2. **Cài đặt XAMPP:**
   - Chạy file installer và follow hướng dẫn
   - Chọn cài đặt Apache, MySQL, PHP

3. **Chạy dự án:**
   ```cmd
   # Copy folder dự án vào C:\xampp\htdocs\cloudflare-project\
   # Khởi động Apache trong XAMPP Control Panel
   # Truy cập: http://localhost/cloudflare-project/
   ```

### Cách 2: Cài đặt PHP độc lập

1. **Download PHP:**
   - Truy cập: https://windows.php.net/download/
   - Tải PHP 8.x Thread Safe ZIP

2. **Cài đặt:**
   ```cmd
   # Giải nén vào C:\php\
   # Thêm C:\php vào System PATH
   # Copy php.ini-development thành php.ini
   # Enable extensions: curl, openssl, json
   ```

3. **Verify:** 
   ```cmd
   php --version
   ```

### Cách 3: Sử dụng Laragon (Khuyến nghị)

1. **Download Laragon:**
   - Truy cập: https://laragon.org/download/
   - Tải Laragon Full

2. **Cài đặt và sử dụng:**
   ```cmd
   # Cài đặt Laragon
   # Start Laragon
   # Copy project vào C:\laragon\www\cloudflare-project\
   # Truy cập: http://cloudflare-project.test/
   ```

## 🔧 Cấu hình dự án

### 1. Kiểm tra PHP Extensions
```cmd
php -m | findstr curl
php -m | findstr json
```

### 2. Cấu hình API Token
- Sửa file `token.txt`, đặt Cloudflare API token
- Sửa email в `config.php`

### 3. Test kết nối
```cmd
php example.php
```

## 🌐 Chạy dự án

### Với PHP built-in server:
```cmd
cd "d:\Note"
php -S localhost:8000
```

### Với Apache (XAMPP/Laragon):
- Copy project vào htdocs/www folder
- Truy cập qua browser

## 🔍 Test API

### Test cơ bản:
```cmd
curl "http://localhost:8000/?action=dashboard&api=1"
```

### Test với browser:
```
http://localhost:8000/
```

## ⚠️ Troubleshooting

### PHP not found:
- Cài đặt PHP theo hướng dẫn trên
- Kiểm tra PATH environment variable

### cURL extension missing:
```ini
# Trong php.ini, uncomment:
extension=curl
extension=openssl
```

### Permission denied:
```cmd
# Run as Administrator nếu cần
```

### Still having issues?
1. Restart computer sau khi cài PHP
2. Check Windows Defender/Firewall
3. Verify port 8000 không bị block

## 📱 Alternative: Online testing

Nếu không muốn cài đặt local, có thể:
1. Upload lên hosting có PHP
2. Sử dụng online PHP playground
3. Deploy lên Vercel/Netlify với PHP runtime