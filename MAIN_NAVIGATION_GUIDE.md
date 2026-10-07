# Main Navigation Component - Hướng dẫn sử dụng

## 📍 File: `includes/main_navigation.php`

### Mục đích
- **Tách menu chính thành file riêng** để dễ quản lý và cập nhật
- **Đồng bộ numbering** trên tất cả trang
- **Auto-highlight menu active** dựa trên trang hiện tại

### Cách sử dụng

#### 1. Include vào file PHP của bạn
```php
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Your Page Title</title>
    <!-- CSS includes -->
</head>
<body>
    <?php 
    // Set current page for navigation highlighting
    $currentPage = 'your_page_name';
    include 'includes/main_navigation.php'; 
    ?>
    
    <!-- Your page content -->
</body>
</html>
```

#### 2. Định nghĩa $currentPage
Để menu highlight đúng, set `$currentPage` với tên file (không cần .php):
- `homepage` → Highlight "1. Trang Chủ"
- `dashboard` → Highlight "2. Dashboard"
- `security_manager` → Highlight "3. Security Manager"
- `bulk_redirect_manager` → Highlight "4. Bulk Redirect Manager"
- `domain_status_checker` → Highlight "5. Domain Status Checker"

#### 3. CSS Dependencies
Đảm bảo include CSS navigation:
```html
<link href="assets/css/navigation.css" rel="stylesheet">
```

### Menu Structure
```
1. Trang Chủ
2. Dashboard
3. Security Manager  
4. Bulk Redirect Manager
5. Domain Status Checker
6. Tools (2-Column Dropdown)
   ├── Cột 1: DNS & Domain Tools + Cache & Redirect
   │   ├── DNS Manager (A + CNAME + Bulk), DNS Tools Overview
   │   ├── DNS Simple, DNS Bulk Update
   │   ├── Domain Extractor, IDN Converter
   │   ├── Cache Manager, Cache TTL Bulk Update
   │   ├── Redirect Manager, 301 Chain Checker
   └── Cột 2: Server Management + Debug & Analytics
       ├── VPS Login Checker
       ├── aaPanel Manager, aaPanel WP Sites
       ├── aaPanel Account Checker, aaPanel API Config
       ├── Cloudflare Debug, API Statistics, Analytics
7. Admin (Dropdown)
   ├── DNS Manager
   ├── Background Tasks
   ├── Settings
   └── Logout

### Benefits

- ✅ **Centralized Management** - Chỉ cần sửa 1 file để update toàn bộ menu
- ✅ **Consistent Numbering** - Đảm bảo numbering đồng bộ trên tất cả trang
- ✅ **Auto Path Detection** - Tự động adjust cho subdirectories
- ✅ **Dynamic Active State** - Auto highlight menu đang active
- ✅ **Easy Maintenance** - Thêm/sửa menu trong 1 chỗ
- ✅ **2-Column Tools Layout** - Tổ chức menu Tools thành 2 cột cho UX tốt hơn và compact

### Tools Menu 2-Column Layout

#### Desktop Layout (>900px)
```
[DNS & Domain + Cache]             [Server Management + Analytics]
- DNS Manager (A + CNAME + Bulk)   - VPS Login Checker
- DNS Tools Overview               - aaPanel Manager  
- DNS Simple                       - aaPanel WP Sites
- DNS Bulk Update                  - aaPanel Account Checker
- Domain Extractor                 - aaPanel API Config
- IDN Converter                    
─ Cache & Redirect ─               ─ Debug & Analytics ─
- Cache Manager                    - Cloudflare Debug
- Cache TTL Bulk Update           - API Statistics  
- Redirect Manager                 - Analytics
- 301 Chain Checker               
```

#### Responsive Behavior
- **900px+**: 2 cột ngang, width 700px
- **768-900px**: 2 cột ngang, width 500px  
- **<768px**: Chuyển thành layout dọc, width 350px

#### CSS Classes
- `.dropdown-menu-2col` - Main container
- `.dropdown-column` - Individual columns with borders
- `.dropdown-header` - Section headers with blue styling
- Responsive CSS với media queries cho mobile/tablet

### Files Đã Áp Dụng Navigation System
✅ **includes/main_navigation.php** - Core component
✅ **bulk_redirect_manager.php** - Updated to use main navigation
✅ **security_manager.php** - Updated navigation + header cleanup  
✅ **homepage.php** - Using centralized navigation
✅ **dashboard.php** - Using centralized navigation
✅ **dns_cf_manager.php** - Enhanced DNS management tool (A + CNAME Records + Bulk Domain Processing)
✅ **MAIN_NAVIGATION_GUIDE.md** - Documentation updated

### Migration Checklist for Other Files

1. **Backup** file gốc
2. **Add include** phần navigation:
   ```php
   <?php 
   $currentPage = 'your_page_name'; 
   include 'includes/main_navigation.php'; 
   ?>
   ```
3. **Remove** hardcoded navigation cũ
4. **Test** để đảm bảo highlight đúng menu
5. **Check responsive** trên mobile/tablet

---

**📝 Note:** File `includes/navigation.php` cũ vẫn tồn tại với cấu trúc khác phục vụ cho những pages có nhu cầu đặc biệt.