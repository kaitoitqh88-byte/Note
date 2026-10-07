# Cloudflare PHP Management Project 🌐

Dự án PHP để quản lý Cloudflare thông qua API, bao gồm quản lý DNS records, analytics, cache và các tính năng khác.

## 🚀 Tính năng

- ✅ Quản lý Zones
- ✅ CRUD DNS Records (A, AAAA, CNAME, MX, TXT, SRV)
- ✅ **Search API** - Tìm kiếm domains qua API với filters
- ✅ Real-time Frontend Search với debouncing
- ✅ **Always Use HTTPS** - Enable HTTPS redirect với 1-click
- ✅ SSL Mode Configuration (Flexible, Full, Strict)
- ✅ Xem Analytics và thống kê
- ✅ Purge Cache
- ✅ Giao diện web thân thiện với Table/Card view
- ✅ API RESTful hoàn chỉnh
- ✅ Development Mode
- ✅ Keyboard shortcuts và UX tối ưu

## 📋 Yêu cầu

- PHP >= 7.4
- Extension: curl, json
- Cloudflare API Token

## ⚡ Cài đặt nhanh

1. **Clone hoặc download project**
2. **Cấu hình API Token:**
   - Đặt Cloudflare API token vào file `token.txt`
   - Cập nhật email trong `config.php`

3. **Chạy project:**
   ```bash
   php -S localhost:8000
   ```

4. **Truy cập:** http://localhost:8000

## 🔧 Cấu hình

### 1. API Token
Tạo API Token tại Cloudflare Dashboard:
- Vào **My Profile** > **API Tokens**
- **Create Token** > **Custom token**
- Permissions: `Zone:Read`, `DNS:Edit`
- Đặt token vào file `token.txt`

### 2. Email
Sửa email trong `config.php`:
```php
define('CLOUDFLARE_EMAIL', 'your-email@example.com');
```

## 📖 API Endpoints

### Zones
- `GET /?action=zones` - Danh sách zones
- `GET /?action=zones&zone_id={id}` - Thông tin zone

### Search (NEW!)
- `GET /?action=search&q={query}` - Tìm kiếm domains
- `GET /?action=search&q={query}&status=active` - Tìm với filter status
- `GET /?action=search&q={query}&plan=Free` - Tìm với filter plan
- `GET /?action=search&page=1&per_page=10` - Tìm với pagination

### DNS Records
- `GET /?action=dns&zone_id={id}` - Danh sách DNS records
- `POST /?action=dns&zone_id={id}` - Tạo DNS record
- `PUT /?action=dns&zone_id={id}&record_id={id}` - Cập nhật DNS record
- `DELETE /?action=dns&zone_id={id}&record_id={id}` - Xóa DNS record

### SSL/HTTPS (NEW!)
- `GET /?action=ssl&zone_id={id}` - Lấy SSL settings
- `POST /?action=ssl` - Enable Always Use HTTPS
- `POST /?action=ssl&action_type=ssl_mode&mode=flexible` - Set SSL mode

### Analytics
- `GET /?action=analytics&zone_id={id}` - Thống kê zone

### Cache
- `POST /?action=cache` - Purge cache

## 🎯 Sử dụng
ìm kiếm Domains (NEW!)
```bash
# Tìm kiếm cơ bản
curl "http://localhost:8000/?action=search&q=example"

# Tìm với filters
curl "http://localhost:8000/?action=search&q=com&status=active&plan=Free"

# Tìm với pagination
curl "http://localhost:8000/?action=search&q=domain&page=1&per_page=10"
```

#### Always Use HTTPS (NEW!)
```bash
# Enable HTTPS redirect
curl -X POST "http://localhost:8000/?action=ssl" \
  -d "zone_id=YOUR_ZONE_ID" \
  -d "action_type=always_use_https" \
  -d "enabled=true"

# Set SSL Mode  
curl -X POST "http://localhost:8000/?action=ssl" \
  -d "zone_id=YOUR_ZONE_ID" \
  -d "action_type=ssl_mode" \
  -d "mode=flexible"
```

#### Tạo DNS Record
```bash
curl -X POST "http://localhost:8000/?action=dns&zone_id=YOUR_ZONE_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "A",
    "name": "example.com",
    "content": "192.168.1.1",
    "ttl": 1
  }'
```

#### Lấy danh sách DNS
```bash
curl "http://localhost:8000/?action=dns&zone_id=YOUR_ZONE_ID"
```

#### Purge Cache
```bash
curl -X POST "http://localhost:8000/?action=cache" \
  -d "zone_id=YOUR_ZONE_ID"
```

## 📁 Cấu trúc Project

```
/
├── index.php           # File chính, xử lý routing
├── config.php          # Cấu hình dự án
├── CloudflareAPI.php   # Class API Cloudflare
├── dashboard.php       # Giao diện web
├── token.txt          # Cloudflare API token
├── composer.json      # Dependencies
└── README.md          # Tài liệu
```

## 🔐 Bảo mật

- ✅ API Token được lưu trong file riêng
- ✅ Validate input data
- ✅ Error handling
- ✅ CORS headers config
- ⚠️ **Lưu ý:** Không commit file `token.txt` lên git

## 🐛 Troubleshooting

### Error: cURL Error
```bash
# Kiểm tra curl extension
php -m | grep curl

# Enable curl trong php.ini
extension=curl
```

### Error: Invalid API Token
- Kiểm tra token trong `token.txt`
- Verify permissions của token
- Kiểm tra email trong `config.php`

### Error: Zone ID not found
- Lấy Zone ID từ Cloudflare Dashboard
- Hoặc dùng API để list zones

## 📝 Changelog

### v1.0.0 (2026-02-11)
- ✅ Initial release
- ✅ Basic Zone management
- ✅ DNS CRUD operations
- ✅ Analytics integration
- ✅ Cache purge
- ✅ Web dashboard

## 📞 Hỗ trợ

Nếu gặp vấn đề, hãy:
1. Kiểm tra log lỗi PHP
2. Verify API token và permissions
3. Đảm bảo curl extension được enable

## 📄 License

MIT License - Tự do sử dụng cho dự án cá nhân và thương mại.