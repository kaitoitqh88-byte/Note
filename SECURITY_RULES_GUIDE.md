# 🛡️ Cloudflare Security Rule Manager - Hướng Dẫn Complete

Công cụ tạo **custom security rules với Expression** cho Cloudflare. Cho phép nhập domain và Expression để tạo rules bảo mật tùy chỉnh.

## 🚀 Cách Truy Cập

### Option 1: Từ Menu Chính (Khuyến Nghị)
```bash
# Khởi động PHP launcher
php portable_launcher.php

# Chọn option 7: Security Rule Manager
```

**Menu chính giờ có 8 options:**
```
🛡️ TOOL OPTIONS
==============================
1. 🚀 Start Server & Open Browser
2. 🌐 Open in Browser (if server running)
3. ⚙️ Edit Configuration
4. 📁 Open Tool Directory
5. ℹ️ Show Tool Info
6. 🔐 API Key Management
7. 🛡️ Security Rule Manager    ← MỚI!
8. 🛑 Stop Server & Exit
```

### Option 2: Chạy Riêng Biệt
```bash
# Windows
start_security_rules.bat

# macOS/Linux
./start_security_rules.sh

# Hoặc direct
php CloudflareSecurityRuleManager.php
```

## 🎮 Menu Security Rule Manager

```
🛡️ CLOUDFLARE SECURITY RULE MANAGER
==================================================
1. 🔧 Create Custom Rule
2. 📋 List Existing Rules
3. 🧪 Test Expression
4. 📚 View Rule Templates
5. ⚙️ Check Configuration
6. 🔙 Back to Main Menu

Choose option (1-6):
```

## 🔧 Tính Năng Chi Tiết

### 1. 🔧 Create Custom Rule
**Tạo rule tùy chỉnh với Expression:**

**Input workflow:**
1. **Domain (tùy chọn)**: Enter domain để target cụ thể
2. **Rule name**: Tên mô tả cho rule
3. **Expression**: Custom expression (có templates)
4. **Action**: Block, Challenge, JS Challenge, Allow, hoặc Log
5. **Priority**: 1-1000 (mặc định: 100)

**Ví dụ interactive session:**
```
Enter domain (optional): example.com
Enter rule name: Block China Bot Traffic  
Enter custom expression: (ip.geoip.country eq "CN" and http.user_agent contains "bot")
Select action: 1 (Block)
Enter priority: 200

📋 RULE SUMMARY
===============
Name: Block China Bot Traffic
Target Domain: example.com
Expression: (http.host eq "example.com") and (ip.geoip.country eq "CN" and http.user_agent contains "bot")
Action: block
Priority: 200

Create this rule? (y/N): y
```

### 2. 📋 List Existing Rules
**Hiển thị tất cả security rules hiện có:**

**Output example:**
```
🛡️ Block Malicious Bots
   ID: abc123-def456-ghi789
   Status: ✅ Active
   Action: block
   Priority: 100
   Expression: (http.user_agent contains "malbot")

🛡️ Rate Limit API
   ID: xyz789-uvw123-rst456
   Status: ❌ Disabled
   Action: challenge
   Priority: 200
   Expression: (http.request.uri.path matches "^/api" and rate(1m) > 100)
```

### 3. 🧪 Test Expression
**Validate expression syntax trước khi tạo rule:**

```
Enter expression to test: (ip.geoip.country eq "CN" and http.host eq "example.com")

✅ Expression syntax is valid!

📖 Expression Analysis:
Expression: (ip.geoip.country eq "CN" and http.host eq "example.com")
✓ Contains country-based filtering
✓ Contains domain-based filtering
```

### 4. 📚 View Rule Templates
**7+ templates có sẵn với examples:**

## 📚 Rule Templates & Examples

### 🌍 **Country-based Filtering**
```
Expression: (ip.geoip.country eq "CN")
Usage: Block traffic từ Trung Quốc
Modify: Thay "CN" với country code khác
```

### 🎯 **IP Range Blocking**  
```
Expression: (ip.src in {192.168.1.0/24 10.0.0.0/8})
Usage: Block specific IP ranges
Modify: Thay với IP ranges cần block
```

### 🤖 **User Agent Filtering**
```
Expression: (http.user_agent contains "badbot" or http.user_agent contains "scraper")
Usage: Block malicious bots
Modify: Thay với user agent strings cần block
```

### 🚫 **Path Protection**
```
Expression: (http.request.uri.path contains "/admin" and ip.geoip.country ne "US")
Usage: Protect admin paths từ non-US countries
Modify: Thay "/admin" và country code
```

### ⚡ **Rate Limiting**
```
Expression: (rate(1m) > 100)
Usage: Block IPs với >100 requests/minute  
Modify: Thay 100 với rate limit khác
```

### 🛡️ **SQL Injection Protection**
```
Expression: (http.request.uri.query contains "union" or http.request.uri.query contains "select" or http.request.uri.query contains "drop")
Usage: Basic SQL injection protection
Modify: Thêm các SQL keywords khác
```

### 🌐 **Domain-specific Rules**
```
Expression: (http.host eq "api.example.com" and http.request.method eq "POST" and not ip.src in {192.168.1.0/24})
Usage: Protect API domain từ external POST requests
Modify: Thay domain và logic
```

### 🔒 **Advanced Combinations**
```
Expression: ((ip.geoip.country in {"CN" "RU" "KP"}) and (http.user_agent contains "bot" or http.user_agent eq "")) and not (ip.src in {1.2.3.4/32})
Usage: Block bot traffic từ specific countries, exclude whitelist IPs
Modify: Customize countries, bot patterns, whitelist IPs
```

## ⚙️ Configuration Setup

### config.json format:
```json
{
    "cloudflare": {
        "email": "your-email@domain.com",
        "api_key": "your-global-api-key",
        "zone_id": "your-zone-id"
    }
}
```

### Lấy thông tin Cloudflare:
1. **API Key**: Dashboard → My Profile → API Tokens → Global API Key
2. **Zone ID**: Dashboard → Select Domain → Overview → Zone ID (sidebar)
3. **Email**: Email đăng nhập Cloudflare

## 🎯 Actions Available

| Action | Mô Tả | Use Case |
|---------|-------|----------|
| `block` | Chặn hoàn toàn | Malicious traffic |
| `challenge` | CAPTCHA challenge | Suspicious traffic |
| `js_challenge` | JavaScript challenge | Bot detection |
| `allow` | Cho phép qua | Whitelist traffic |
| `log` | Chỉ log, không action | Monitoring |

## 🔍 Expression Operators

### IP và Geography
- `ip.src` - Source IP
- `ip.geoip.country` - Country code
- `ip.geoip.continent` - Continent
- `in` - IP trong range: `ip.src in {1.2.3.0/24}`
- `eq`, `ne` - Equal, not equal

### HTTP Request
- `http.host` - Domain
- `http.request.method` - GET, POST, etc.
- `http.request.uri.path` - URL path
- `http.request.uri.query` - Query parameters
- `http.user_agent` - User agent string
- `contains`, `matches` - String operations

### Logical Operators
- `and`, `or`, `not` - Logic operations
- `()` - Grouping
- `{}` - Lists/arrays

### Functions
- `rate(period)` - Rate limiting
- `lower()` - Lowercase conversion

## 💡 Use Cases & Examples

### 🛡️ **Basic Protection**
```bash
# Block specific countries
Domain: example.com
Expression: (ip.geoip.country in {"CN" "RU"})
Action: block

# Protect admin area
Domain: mysite.com  
Expression: (http.request.uri.path contains "/wp-admin")
Action: challenge
```

### 🤖 **Bot Protection**
```bash
# Block known bad bots
Expression: (http.user_agent contains "sqlmap" or http.user_agent contains "nikto")
Action: block

# Challenge suspicious bots
Expression: (http.user_agent contains "bot" and rate(1m) > 10)
Action: js_challenge
```

### 🚀 **API Protection**  
```bash
# Rate limit API
Domain: api.mysite.com
Expression: (rate(1m) > 100)
Action: challenge

# Protect sensitive endpoints
Expression: (http.request.uri.path matches "^/api/admin" and ip.geoip.country ne "US")
Action: block
```

### ⚡ **Performance Protection**
```bash
# Block heavy scrapers
Expression: (rate(10s) > 20 and http.user_agent eq "")
Action: block

# Challenge high frequency users  
Expression: (rate(1h) > 500)
Action: challenge
```

## 🔧 Advanced Features

### 📊 **Rule Priority**
- **1-50**: Highest priority (block/allow rules)
- **51-200**: Medium priority (challenge rules)  
- **201-1000**: Lower priority (log rules)

### 🎯 **Expression Testing**
- Validate trước khi tạo rule
- Check syntax errors
- Analyze expression components

### 📋 **Rule Management**
- List tất cả rules hiện có
- See rule status và usage
- Identify conflicts

## 🚨 Best Practices

### ✅ **Do's**
- ✅ Test expressions trước khi deploy
- ✅ Use descriptive rule names
- ✅ Set appropriate priorities
- ✅ Monitor rule effectiveness
- ✅ Use whitelist IPs cho trusted sources

### ❌ **Don'ts**
- ❌ Block toàn bộ countries without reason
- ❌ Set quá nhiều rules với same priority
- ❌ Use overly complex expressions
- ❌ Forget về false positives
- ❌ Block without logging first

### 🎯 **Workflow Recommendations**
1. **Test Phase**: Tạo rule với action `log` trước
2. **Monitor**: Xem logs trong vài ngày
3. **Adjust**: Fine-tune expression nếu cần
4. **Deploy**: Change action thành `block`/`challenge`
5. **Monitor**: Continue monitoring performance

## 🔍 Troubleshooting

### ⚠️ **Common Issues**

**Expression Validation Failed:**
- Check syntax cẩn thận
- Use correct operators (`eq` not `==`)
- Proper quotes cho strings
- Valid field names

**API Connection Failed:**
- Check API key và email
- Verify zone ID
- Check internet connection
- Validate permissions

**Rule Not Working:**
- Check rule priority
- Verify expression logic
- Test với expression tester
- Check Cloudflare dashboard

## 📞 Support

### 🛠️ **Debug Steps**
1. Use **Expression Tester** (option 3)
2. Check **Configuration Status** (option 5)
3. Verify Cloudflare dashboard
4. Test với simple expressions trước

### 📚 **Resources**
- [Cloudflare Rules Language](https://developers.cloudflare.com/firewall/cf-firewall-language/)
- [Expression Examples](https://developers.cloudflare.com/firewall/cf-firewall-language/expressions/)
- [Field Reference](https://developers.cloudflare.com/firewall/cf-firewall-language/fields/)

---

## 🎉 Quick Start Summary

1. **Setup**: Ensure config.json có Cloudflare credentials
2. **Access**: `php portable_launcher.php` → Option 7
3. **Create Rule**: Option 1 → Follow prompts
4. **Test**: Option 3 để validate expressions
5. **Monitor**: Option 2 để xem existing rules

**🛡️ Security Rule Manager giờ là một phần tích hợp của Cloudflare Security Tool!**

*Transform your Cloudflare security với custom expressions and domain-specific rules!* 🚀