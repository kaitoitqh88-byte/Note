# Cloudflare Bulk Redirect Manager - Hướng dẫn sử dụng

## Tổng quan
**Cloudflare Bulk Redirect Manager** là công cụ tiên tiến để quản lý hàng loạt redirect 301/302 sử dụng **Cloudflare Rulesets API**. Được thiết kế với giao diện trực quan, tiến trình real-time và khả năng xử lý nhiều domain cùng lúc.

## Đặc điểm nổi bật

### 🚀 Công nghệ tiên tiến
- **Rulesets API**: Sử dụng API mới nhất của Cloudflare  
- **Real-time Progress**: Theo dõi tiến trình xử lý trực tiếp
- **Live Console**: Giao diện dòng lệnh thời gian thực
- **Concurrent Processing**: Xử lý đồng thời nhiều domain

### ⚡ Luồng xử lý tự động
1. **Lấy Zone ID** từ domain name
2. **Liệt kê Rulesets** hiện có để tìm quy tắc cũ  
3. **Xóa Rules cũ** (tùy chọn) để tránh xung đột
4. **Tạo Ruleset mới** với cấu hình 301/302 redirect

### 💎 Tính năng core
- ✅ Bulk Operations (hàng loạt)
- ✅ Smart Validation (xác thực thông minh)
- ✅ Auto Clean Old Rules (tự động dọn dẹp)
- ✅ Path & Query Preservation (giữ nguyên đường dẫn)
- ✅ Export Results (xuất kết quả CSV)
- ✅ Error Handling (xử lý lỗi chi tiết)

## Hướng dẫn sử dụng

### 1. Layout giao diện

#### **Input Area**
- **Domains Input**: Nhập danh sách domain (mỗi domain một dòng)
- **Target URL**: URL đích cho redirect  
- **Settings**: Các tùy chọn cấu hình

#### **Action Bar** 
```
[Kiểm tra Zones] [Check Rules cũ] [Tạo 301 Redirect] [Xóa Rules cũ] [Xuất kết quả]
```

#### **Progress Section**
- Thanh tiến trình với phần trăm hoàn thành
- Thống kê real-time: Tổng domain, Đã xử lý, Thành công, Lỗi

#### **Live Console**
- Log từng bước thực thi
- Màu sắc phân biệt: Success (xanh), Error (đỏ), Warning (vàng), Info (xanh dương)
- Timestamp cho mỗi action

#### **Results Table**
- Hiển thị kết quả chi tiết cho từng domain
- Columns: Domain, Zone ID, Rules cũ xóa, Rule mới, Trạng thái, Chi tiết

### 2. Quy trình thực hiện 

#### **Bước 1: Kiểm tra Domain**
```
Input: danh sách domains
Action: Click [Kiểm tra Zones]
Output: 
✅ domain1.com → Zone ID: abc123def456
❌ domain2.com → Zone không tìm thấy
```

#### **Bước 2: Kiểm tra Rules hiện có (tùy chọn)**
```
Action: Click [Check Rules cũ]
Output:
🔍 domain1.com có 2 redirect ruleset(s):
   → Old Redirect Rules (ID: xyz789) - 3 rule(s)
   → Legacy Rules (ID: def456) - 1 rule(s)
✅ domain2.com không có redirect ruleset nào
```

#### **Bước 3: Tạo Bulk Redirects**
```
Input: 
- Domain list
- Target URL: https://newsite.com  
- Status: 301 Permanent
- Options: Preserve path ✓, Preserve query ✓, Delete old ✓

Action: Click [Tạo 301 Redirect]
Process:
1. Getting zone ID...
2. Looking for old redirect rulesets...
3. Deleting 2 old ruleset(s)...
4. Creating new redirect ruleset...
✅ Ruleset created: rst_abc123

Results:
📊 Tổng kết:
   • Tổng domain: 50
   • Thành công: 48  
   • Thất bại: 2
   • Xóa rulesets cũ: 15
   • Tạo rulesets mới: 48
   • Thời gian xử lý: 12.5s
```

### 3. Tùy chọn cấu hình

#### **Status Codes**
- `301` - Permanent Redirect (SEO-friendly)
- `302` - Temporary Redirect  
- `307` - Temporary (Method Preserved)
- `308` - Permanent (Method Preserved)

#### **Path & Query Handling**
- **Preserve Path**: Giữ nguyên đường dẫn
  - `domain.com/page1` → `newsite.com/page1` ✓
  - `domain.com/page1` → `newsite.com/` ✗
- **Preserve Query**: Giữ nguyên tham số URL
  - `domain.com?utm=source` → `newsite.com?utm=source` ✓

#### **Auto Clean Rules**
- Tự động tìm và xóa redirect rulesets cũ trước khi tạo mới
- Tránh xung đột và đảm bảo performance

## API Integration

### Cloudflare Rulesets API Endpoints
```php
// Get all rulesets
GET /zones/{zone_id}/rulesets

// Create new ruleset  
POST /zones/{zone_id}/rulesets
{
  "name": "Redirect Ruleset - domain.com",
  "description": "Automatic redirect to newsite.com", 
  "kind": "zone",
  "phase": "http_request_dynamic_redirect",
  "rules": [...]
}

// Delete ruleset
DELETE /zones/{zone_id}/rulesets/{ruleset_id}
```

### Expression Examples
```javascript
// Domain redirect với preserve path
(http.host eq "olddomain.com") or (http.host eq "www.olddomain.com")

// Exact URL redirect
(http.request.full_uri eq "https://olddomain.com/") or 
(http.request.full_uri eq "http://olddomain.com/")
```

## Troubleshooting

### Lỗi thường gặp

#### **Zone not found**
```
❌ domain.com → Zone không tìm thấy
```
**Nguyên nhân**: Domain chưa được add vào Cloudflare account
**Giải pháp**: Add domain vào Cloudflare trước khi redirect

#### **API Permission Error**  
```
❌ HTTP Error 403 - Forbidden. Check your API token permissions.
```
**Nguyên nhân**: API token thiếu quyền Zone:Edit
**Giải pháp**: Cập nhật API token với quyền đầy đủ

#### **Rate Limit**
```
❌ Too many requests. Please wait before retrying.
```  
**Nguyên nhân**: Vượt giới hạn API calls
**Giải pháp**: Tool tự động delay, chờ và thử lại

#### **Invalid Expression**
```
❌ Invalid expression: Syntax errors: Unmatched parentheses
```
**Nguyên nhân**: Lỗi cú pháp trong expression
**Giải pháp**: Tool tự động validate, kiểm tra input

### Performance Tips

#### **Bulk Processing**
- Xử lý tối đa 50 domains/lần để đảm bảo performance
- Rate limiting tự động: 0.5s delay giữa các requests

#### **Caching**
- Zone information được cache để tăng tốc
- Cache tự động clear sau operations

#### **Error Recovery** 
- Tự động retry cho failed requests
- Detailed error logging để debug

## Workflow Examples

### Case Study 1: Migrate 100 domains
```
Scenario: Di chuyển 100 domains từ old hosting sang new domain

Input:
- 100 domains trong file text
- Target: https://newbrand.com
- Requirements: Preserve paths, 301 redirects

Process:
1. Copy/paste domain list → Domain count: 100
2. Set target URL → https://newbrand.com  
3. Check [Preserve path] ✓
4. Click [Kiểm tra Zones] → 95 found, 5 not found
5. Click [Tạo 301 Redirect] → Processing...

Results:
✅ 95 domains: Successfully redirected  
❌ 5 domains: Zone not found
📄 Export CSV with detailed results
⏱️ Processing time: 45.2s
```

### Case Study 2: Emergency redirect
```
Scenario: Website down, cần redirect khẩn cấp tất cả traffic

Input:
- 1 main domain + 3 subdomains
- Target: https://backup-site.com
- Requirements: Fast deployment

Process:
1. Input domains → 4 total
2. Target → https://backup-site.com
3. Uncheck [Delete old] để giữ rules cũ
4. Click [Tạo 301 Redirect] → Instant deploy

Results:
🚀 All traffic redirected in 3.1s
📊 4/4 domains successful
```

## Integration với Security Manager

### Navigation
Truy cập từ **Security Manager** → **Redirect Rules** tab → **[Mở Bulk Manager]** 

### Compatibility  
- Hoạt động song song với legacy redirect system
- Shared API credentials và cache layer
- Consistent với security workflow

## Best Practices

### 🏆 SEO-friendly redirects
- Sử dụng 301 cho permanent moves
- Preserve paths để giữ link structure
- Minimize redirect chains (direct A→C thay vì A→B→C)

### ⚡ Performance optimization
- Delete old rules để tránh conflicts
- Batch processing thay vì single requests  
- Monitor rate limits

### 🔒 Security considerations
- Validate tất cả input domains
- Log operations để audit  
- API token với quyền tối thiểu cần thiết

### 📊 Monitoring & maintenance
- Export results để backup
- Regular cleanup của unused rules
- Monitor redirect performance

---

## Changelog

### Version 2.0.0 (Current)
- ✨ **NEW**: Sử dụng Cloudflare Rulesets API thay vì Page Rules
- ✨ **NEW**: Real-time progress tracking với live console
- ✨ **NEW**: Bulk operations với concurrent processing
- ✨ **NEW**: Smart validation và auto-cleanup
- ✨ **NEW**: Export CSV functionality
- 🔧 **IMPROVED**: Modern responsive UI với glass morphism design
- 🔧 **IMPROVED**: Better error handling và user feedback
- 🔧 **IMPROVED**: Performance optimization với rate limiting

### Previous Versions
- v1.x: Legacy Page Rules implementation
- Basic redirect functionality

---

**📞 Support**: Liên hệ admin nếu cần hỗ trợ kỹ thuật hoặc gặp lỗi không mong muốn.

**🔗 Links**: 
- [Cloudflare Rulesets API Documentation](https://developers.cloudflare.com/ruleset-engine/)
- [Expression Reference](https://developers.cloudflare.com/ruleset-engine/rules-language/)
- [Security Manager Dashboard](security_manager.php)