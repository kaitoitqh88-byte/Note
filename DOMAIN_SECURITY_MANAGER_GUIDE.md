# 🌐 Cloudflare Domain Security Manager

## 📋 Tổng quan

**Cloudflare Domain Security Manager** là công cụ chuyên dụng để tạo và quản lý các rule bảo mật cho Cloudflare với khả năng nhắm mục tiêu theo domain cụ thể và tạo expression tùy chỉnh.

## 🎯 Tính năng chính

### 🌐 **Quản lý Domain**
- Thêm/xóa domains
- Cấu hình Zone ID cho từng domain
- Theo dõi số lượng rules cho mỗi domain

### 📝 **Tạo Rule tùy chỉnh**
- Expression builder với validation
- Domain-specific targeting
- Các action: block, challenge, js_challenge, allow, log
- Mô tả và ghi chú cho mỗi rule

### 📋 **Thư viện Template**
- **Domain Protection**: Block bots, country blocking, rate limiting
- **Advanced Security**: SQL injection, XSS protection, DDoS protection  
- **Custom Rules**: API rate limiting, mobile app protection

### ✅ **Validation & Testing**
- Kiểm tra cú pháp expression
- Test expression với dữ liệu mẫu
- Phân tích độ phức tạp của expression

## 🚀 Cách sử dụng

### Method 1: Direct Access
```bash
php CloudflareDomainSecurityManager.php
```

### Method 2: Via Portable Launcher
```bash
php portable_launcher.php
# → Chọn option 8: Domain Security Manager
```

### Method 3: Via Tools Menu
```bash
php tools_menu.php
# → Chọn option 3: Domain Security Manager
```

### Method 4: Windows Launcher
```cmd
start_domain_security_manager.bat
```

### Method 5: Unix/Linux Launcher
```bash
./start_domain_security_manager.sh
```

## 📖 Hướng dẫn sử dụng từng chức năng

### 🌐 **1. Quản lý Domains**

**Thêm Domain mới:**
1. Chọn option 1 → Manage Domains
2. Chọn option 1 → Add Domain  
3. Nhập domain (ví dụ: `example.com`)
4. Nhập Zone ID (optional)
5. Nhập mô tả (optional)

**Xem danh sách Domains:**
- Option 2 → List Domains
- Hiển thị tất cả domains với thông tin chi tiết

### 📝 **2. Tạo Custom Rule**

**Bước 1:** Chọn domain target
**Bước 2:** Nhập tên rule
**Bước 3:** Tạo expression với các pattern:

```javascript
// Block IP cụ thể
ip.src eq 1.2.3.4

// Block theo quốc gia  
(http.host eq "{domain}") and (ip.geoip.country in {"CN" "RU"})

// Block bots
(http.host eq "{domain}") and (http.user_agent contains "bot")

// Rate limiting
(http.host eq "{domain}") and (rate(ip.src, 1m) gt 30)

// Path protection
(http.host eq "{domain}") and (http.request.uri.path matches "^/admin")
```

**Bước 4:** Chọn action (block/challenge/allow/log)
**Bước 5:** Thêm mô tả

### 📋 **3. Sử dụng Templates**

**Available Categories:**
- **Domain Protection**: Basic security rules
- **Advanced Security**: SQL injection, XSS protection
- **Custom Rules**: API protection, mobile-specific rules

**Cách sử dụng:**
1. Chọn option 3 → Use Template
2. Chọn category
3. Chọn template cụ thể  
4. Chọn domain target
5. Review và confirm

### ✅ **4. Validation & Testing**

**Validate Expression:**
- Option 4 → Validate Expression
- Nhập expression để kiểm tra cú pháp
- Xem phân tích chi tiết

**Test Expression:**
- Option 5 → Test Expression  
- Nhập test data (IP, domain, user agent, path)
- Xem kết quả match/no match

## 💡 **Expression Examples**

### Basic Patterns
```javascript
// Exact domain match
http.host eq "example.com"

// IP range blocking
ip.src in {192.168.1.0/24}

// User agent contains
http.user_agent contains "bot"

// Path matching
http.request.uri.path matches "^/api/"

// Country blocking
ip.geoip.country in {"CN" "RU" "KP"}
```

### Composite Rules
```javascript
// Domain + Country + Rate limiting
(http.host eq "example.com") and 
(ip.geoip.country in {"CN" "RU"}) and 
(rate(ip.src, 1m) gt 50)

// Admin protection
(http.host eq "example.com") and 
(http.request.uri.path matches "^/(admin|wp-admin)") and 
not (ip.src in {192.168.1.0/24})
```

## ⚙️ **Configuration**

### Config File Format (`config.json`)
```json
{
  "cloudflare": {
    "email": "your-email@domain.com",
    "api_key": "your-api-key", 
    "zone_id": "default-zone-id"
  },
  "domains": {
    "example.com": {
      "zone_id": "specific-zone-id",
      "description": "Main website",
      "added_date": "2026-03-13 10:00:00",
      "rules": []
    }
  }
}
```

## 🔧 **Troubleshooting**

### Common Issues

**"Expression validation failed"**
- Kiểm tra cú pháp expression
- Đảm bảo có đủ dấu ngoặc đơn
- Sử dụng đúng operators (eq, contains, in, etc.)

**"No domains configured"**  
- Thêm domain trước khi tạo rule
- Sử dụng option 1 → Manage Domains

**"API deployment failed"**
- Kiểm tra API credentials trong config
- Đảm bảo có quyền trên Zone

### Field Reference
- `http.host` - Domain name
- `ip.src` - Source IP
- `http.user_agent` - User agent string  
- `http.request.uri.path` - URL path
- `ip.geoip.country` - Country code
- `rate(field, period)` - Rate limiting

## 🔗 **Integration**

Tool này tích hợp với:
- **Portable Launcher** (option 8)
- **Tools Menu** (option 3)
- **Web Interface** (coming soon)

## 📞 **Support**

Sử dụng built-in help và validation để debug expressions. Tool cung cấp real-time feedback về syntax và complexity analysis.

---

**🛡️ Advanced Domain-Based Security Management for Cloudflare**