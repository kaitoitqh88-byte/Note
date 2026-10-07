# 🔍 Hướng dẫn kiểm tra chức năng lấy danh sách domain

## 📋 Tổng quan
Hệ thống Cloudflare domain management của bạn đã được cài đặt với các tính năng:
- ✅ Pagination (phân trang) cho danh sách domain
- ✅ Search (tìm kiếm) domain
- ✅ HTTPS status checking
- ✅ API endpoints với JSON responses

## 🔧 Cách 1: Cài đặt PHP để kiểm tra đầy đủ

### Option 1A: Download PHP Portable
1. Tải PHP từ: https://windows.php.net/download/
2. Giải nén vào `C:\php\`
3. Thêm `C:\php\` vào Windows PATH
4. Khởi động server:
   ```bash
   cd "D:\Note"
   php -S localhost:8000
   ```

### Option 1B: Sử dụng XAMPP (Khuyến nghị)
1. Tải XAMPP: https://www.apachefriends.org/
2. Cài đặt và khởi động Apache
3. Copy thư mục `D:\Note\` vào `C:\xampp\htdocs\`
4. Truy cập: http://localhost/Note/

## 🧪 Cách 2: Kiểm tra nhanh với test files

### Bước 1: Mở test_api.html
```bash
# Mở file trong trình duyệt
start test_api.html
```

### Bước 2: Kiểm tra từng chức năng
1. **Server Status**: Kiểm tra PHP server có hoạt động không
2. **API Test**: Test pagination với các tham số khác nhau
3. **Search Test**: Test tính năng tìm kiếm domain
4. **Dashboard**: Mở dashboard chính

## 📊 Cách 3: Kiểm tra trực tiếp qua Command Line
```bash
# Nếu đã có PHP, chạy test script
php test_domain_list.php
```

## 🌐 API Endpoints đã có sẵn

### 1. Lấy danh sách domains với pagination
```
GET /index.php?action=zones&page=1&per_page=20
```

**Tham số:**
- `page`: Số trang (từ 1)
- `per_page`: Số domain per trang (5-100)

**Response:**
```json
{
  "success": true,
  "data": {
    "result": [domains...],
    "result_info": {
      "page": 1,
      "per_page": 20,
      "total_pages": 5,
      "total_count": 87
    }
  }
}
```

### 2. Tìm kiếm domains
```
GET /index.php?action=search&query=example&page=1&per_page=20
```

### 3. Kiểm tra HTTPS status
```
GET /index.php?action=https-check&zone_id=ZONE_ID
```

## 🎯 Danh sách kiểm tra (Checklist)

### ✅ Backend API
- [x] CloudflareAPI class với pagination support
- [x] listZones() method với page/perPage parameters
- [x] searchZones() method
- [x] Proper error handling và JSON responses
- [x] Rate limiting và safety limits

### ✅ Frontend Dashboard
- [x] Pagination UI controls
- [x] Search functionality
- [x] Table/Card view switching
- [x] HTTPS status display
- [x] Loading states và error handling

### ✅ Configuration
- [x] API token configured trong token.txt
- [x] Email configured trong config.php
- [x] CORS headers for AJAX calls

## 🔍 Các test cases được implement

### Test 1: Basic Connection
- Kết nối với Cloudflare API
- Validate API token và email
- Kiểm tra response format

### Test 2: Pagination
- Load trang 1 với 20 domains
- Navigation giữa các trang
- Thay đổi số items per page
- Validation của page parameters

### Test 3: Search
- Tìm kiếm partial domain name
- Search với pagination
- Clear search results

### Test 4: Error Handling
- Invalid API credentials
- Network errors
- Malformed JSON responses
- Rate limiting

## 🚀 Để bắt đầu kiểm tra:

1. **Nhanh nhất**: Mở `test_api.html` trong browser
2. **Đầy đủ nhất**: Cài PHP và chạy `php -S localhost:8000`
3. **Command line**: Chạy `php test_domain_list.php`

## 📝 Notes quan trọng:

- ⚠️ Cần API token hợp lệ trong `token.txt`
- ⚠️ Cần email chính xác trong `config.php` 
- ⚠️ Cloudflare API có rate limit - script đã có built-in delays
- ⚠️ Maximum 100 domains per page (Cloudflare limitation)

## 🔧 Troubleshooting:

**Lỗi: "php command not found"**
- Cài đặt PHP hoặc sử dụng XAMPP

**Lỗi: "API token invalid"**
- Kiểm tra token trong token.txt
- Đảm bảo token có quyền Zone:Read

**Lỗi: "No domains found"**
- Kiểm tra email trong config.php
- Đảm bảo account có domains

**Lỗi: "JSON parse error"**
- Kiểm tra PHP errors
- Đảm bảo proper Content-Type headers