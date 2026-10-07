# Domain Redirect Manager

Hệ thống quản lý redirect từ nhiều domain đến một domain đích với giao diện web thân thiện.

## Tính năng

- ✅ Redirect nhiều domain đến 1 domain đích
- ✅ Hỗ trợ các loại redirect: 301, 302, 307, 308
- ✅ Redirect subdomain (tùy chọn)
- ✅ Xử lý path: preserve, root, custom
- ✅ Giao diện web quản lý
- ✅ Thống kê số lượt redirect
- ✅ Export/Import cấu hình  
- ✅ Tạo file .htaccess tự động
- ✅ Hệ thống authentication

## Cài đặt

### 1. Upload files

Upload các files sau lên server của bạn:
- `RedirectHandler.php` - Class xử lý redirect
- `redirect_manager.php` - Giao diện quản lý
- `redirect.php` - File xử lý redirect (cho domain nguồn)

### 2. Phân quyền

Đảm bảo Apache có thể ghi file `redirect_config.json`:
```bash
chmod 666 redirect_config.json
```

### 3. Cấu hình Authentication

Hệ thống sử dụng `APISecretKeyManager` cho authentication. Đảm bảo bạn có:
- `APISecretKeyManager.php`
- `APIKeyAuthInterface.php`
- Đã cấu hình API keys

## Sử dụng

### 1. Truy cập giao diện quản lý

Mở trình duyệt và truy cập: `http://yourdomain.com/redirect_manager.php`

### 2. Thêm redirect rule

1. Nhấn "Add New Redirect"
2. Nhập source domains (một domain mỗi dòng)
3. Nhập target domain
4. Chọn loại redirect
5. Cấu hình xử lý path
6. Nhấn "Save Redirect"

### 3. Áp dụng redirect

#### Cách 1: Sử dụng .htaccess (Recommended)

1. Sinh file .htaccess từ giao diện quản lý:
   - Nhấn "Generate .htaccess"
   - Download file .htaccess
   
2. Upload file .htaccess lên root của các domain cần redirect

#### Cách 2: Integrate vào code

Thêm vào đầu file `index.php` của domain cần redirect:

```php
<?php
require_once 'path/to/redirect.php';
// Phần còn lại của code...
?>
```

#### Cách 3: Sử dụng redirect.php trực tiếp

1. Upload `redirect.php` và `RedirectHandler.php` lên domain cần redirect
2. Tạo `.htaccess` để route mọi request về `redirect.php`:

```apache
RewriteEngine On
RewriteRule ^(.*)$ redirect.php [QSA,L]
```

## Ví dụ cấu hình

### Redirect basic

**Source domains:**
```
old-site.com
legacy.com
```

**Target domain:** `new-site.com`

**Kết quả:**
- `old-site.com` → `new-site.com`
- `old-site.com/page` → `new-site.com/page`
- `legacy.com/about` → `new-site.com/about`

### Redirect với subdomain

**Source domains:**
```
example.com
```

**Target domain:** `newdomain.com`  
**Include Subdomain:** ✅

**Kết quả:**
- `example.com` → `newdomain.com`
- `www.example.com` → `newdomain.com`
- `blog.example.com` → `newdomain.com`

### Redirect về custom path

**Source domains:**
```
old-shop.com
```

**Target domain:** `new-shop.com`  
**Path handling:** Custom  
**Custom path:** `/welcome`

**Kết quả:**
- `old-shop.com` → `new-shop.com/welcome`
- `old-shop.com/any-page` → `new-shop.com/welcome`

## Loại Redirect

| Code | Tên | Mô tả | Sử dụng khi |
|------|-----|-------|-------------|
| 301 | Permanent Redirect | Search engines sẽ transfer ranking | Site chuyển domain vĩnh viễn |
| 302 | Temporary Redirect | Search engines giữ nguyên ranking cho old domain | Maintenance tạm thời |
| 307 | Temporary (Preserve Method) | Giống 302 nhưng preserve HTTP method | API endpoints |
| 308 | Permanent (Preserve Method) | Giống 301 nhưng preserve HTTP method | API endpoints di chuyển vĩnh viễn |

## File cấu hình

Cấu hình được lưu trong `redirect_config.json`:

```json
{
    "redirects": [
        {
            "id": "redirect_xxxxx",
            "source_domains": ["old-site.com", "legacy.com"],
            "target_domain": "new-site.com",
            "redirect_type": "301",
            "include_subdomain": false,
            "path_handling": "preserve",
            "custom_path": "",
            "created_at": "2024-01-01 10:00:00",
            "enabled": true,
            "hits": 1250
        }
    ],
    "settings": {
        "redirect_type": "301",
        "enable_wildcard": true,
        "enable_https_redirect": true
    }
}
```

## API EndPoints

### Thêm redirect
```php
POST redirect_manager.php
action=add_redirect&source_domains=old1.com%0Aold2.com&target_domain=new.com&redirect_type=301
```

### Xóa redirect
```php
POST redirect_manager.php
action=delete_redirect&id=redirect_xxxxx
```

### Toggle redirect
```php
POST redirect_manager.php  
action=toggle_redirect&id=redirect_xxxxx
```

### Export cấu hình
```php
GET redirect_manager.php?ajax=export_config
```

## Bảo mật

- Hệ thống yêu cầu authentication qua `APISecretKeyManager`
- Validate domain formats
- Escape output để tránh XSS
- CSRF protection trong forms

## Debug

Để debug redirect, bạn có thể:

1. **Kiểm tra file log:** Redirect được log trong server access logs
2. **Sử dụng browser developer tools:** Xem response headers
3. **Test với curl:**
```bash
curl -I http://old-domain.com
```
4. **Kiểm tra file config:** Xem `redirect_config.json`

## Performance

- File config được cache trong memory
- Không có database queries
- Lightweight processing
- Chỉ load config khi cần thiết

## Requirements

- PHP 7.4+
- Apache with mod_rewrite
- File system write permissions
- Authentication system (APISecretKeyManager)

## Troubleshooting

### Redirect không hoạt động
1. Kiểm tra file permissions
2. Verify .htaccess syntax  
3. Check Apache mod_rewrite enabled
4. Verify domain trong config

### Authentication error
1. Kiểm tra APISecretKeyManager setup
2. Verify session configuration
3. Check file permissions

### Performance issues
1. Optimize .htaccess rules
2. Use CDN for static assets  
3. Enable caching

## License

Free to use for personal and commercial projects.

## Support

Liên hệ để được hỗ trợ hoặc báo lỗi.