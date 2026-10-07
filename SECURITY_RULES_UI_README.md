# 🛡️ Security Rules Management UI - Hướng Dẫn Sử Dụng

## 📋 Tổng Quan

Giao diện quản lý Security Rules cho Cloudflare với đầy đủ chức năng tạo, sửa, xóa và quản lý các quy tắc bảo mật thông qua Expression Engine của Cloudflare.

## 🚀 Tính Năng Chính

### 1. **Dashboard** 📊
- Thống kê tổng quan về rules
- Biểu đồ phân tích hoạt động
- Thông tin requests được xử lý
- Trạng thái hệ thống real-time

### 2. **Tạo Rules** ➕
- Form tạo rule với validation real-time
- Expression Builder trực quan
- Template sử dụng sẵn
- Preview và test expression

### 3. **Templates Gallery** 📋
- Thư viện templates được định nghĩa sẵn
- Phân loại theo mục đích sử dụng
- One-click apply template
- Custom templates

### 4. **Quản Lý Rules** 📝
- Danh sách tất cả rules
- Enable/Disable rules
- Chỉnh sửa và xóa rules
- Sắp xếp theo priority

### 5. **Test Tools** 🔍
- Test expression với sample data
- Validate syntax
- Preview rule behavior
- Debug tools

### 6. **Bulk Operations** ⚡
- Tạo nhiều rules cùng lúc
- Import/Export rules
- Batch enable/disable
- Mass delete operations

## 🎨 Cấu Trúc Giao Diện

### **Sidebar Menu**
```
🏠 Dashboard          → Trang chủ với thống kê
➕ Tạo Rules         → Form tạo rule mới
📋 Templates         → Thư viện templates
📝 Quản Lý Rules     → Danh sách và quản lý rules
🔍 Test Tools        → Công cụ test và debug
⚡ Bulk Operations    → Thao tác hàng loạt
```

### **Zone Selector**
- Dropdown chọn zone Cloudflare
- Auto-refresh data khi chuyển zone
- Sync với API Cloudflare

## 📁 File Structure

```
📦 Security Rules UI
├── 📄 security_manager.php        → Main application file
├── 📁 assets/
│   ├── 📁 css/
│   │   └── 📊 security-manager.css → Styles tối ưu
│   └── 📁 js/
│       └── ⚡ security-manager.js  → JavaScript logic
├── 📄 CloudflareAPI.php          → API integration
├── 📄 config.php                 → Configuration
└── 📖 SECURITY_RULES_UI_README.md → Documentation này
```

## 🔧 Cài Đặt và Sử Dụng

### **1. Setup Files**
```bash
# Copy files to web directory
cp security_manager.php /your/web/directory/
cp -r assets/ /your/web/directory/assets/
```

### **2. Cấu Hình**
```php
// Trong config.php - cập nhật thông tin Cloudflare
define('CLOUDFLARE_EMAIL', 'your-email@domain.com');
define('CLOUDFLARE_API_KEY', 'your-global-api-key');
define('CLOUDFLARE_ZONE_ID', 'your-zone-id');
```

### **3. Truy Cập UI**
```
http://yourdomain.com/security_manager.php
```

## 🎯 Hướng Dẫn Sử Dụng Chi Tiết

### **Dashboard Usage**
1. Xem thống kê tổng quan
2. Kiểm tra rules đang hoạt động
3. Theo dõi traffic được xử lý
4. Monitor performance

### **Tạo Rule Mới**
1. **Chọn "Tạo Rules"** trong menu
2. **Nhập thông tin cơ bản:**
   - Mô tả rule
   - Priority (1-10)
   - Action (block, challenge, allow, etc.)

3. **Tạo Expression:**
   - Sử dụng Expression Builder (visual)
   - Hoặc nhập trực tiếp expression
   - Validate real-time

4. **Submit để tạo rule**

### **Sử Dụng Templates**
1. **Vào "Templates"** trong menu
2. **Browse categories:** Security, Performance, Geo-blocking, etc.
3. **Click template** để preview
4. **Click "Sử dụng"** để apply
5. **Customize** nếu cần và save

### **Quản Lý Rules**
1. **Vào "Quản Lý Rules"**
2. **View tất cả rules** trong table
3. **Actions available:**
   - ✏️ Edit rule
   - 🗑️ Delete rule
   - 🔄 Enable/Disable
   - 📊 View stats

### **Test Tools**
1. **Vào "Test Tools"**
2. **Nhập expression** cần test
3. **Provide sample data** (optional)
4. **Click "Test"** để see results
5. **Debug** nếu có lỗi

### **Bulk Operations**
1. **Vào "Bulk Operations"**
2. **Chọn operation type:**
   - Create multiple rules
   - Import from CSV/JSON
   - Batch enable/disable
   - Mass delete
3. **Upload file** hoặc paste data
4. **Review changes** và execute

## 🔍 Expression Examples

### **Block Bad Bots**
```javascript
(http.user_agent contains "bot" and not http.user_agent contains "Googlebot")
```

### **Geo-blocking**
```javascript
(ip.geoip.country ne "VN" and http.request.uri.path contains "/admin")
```

### **Rate Limiting**
```javascript
(http.request.uri.path contains "/api/" and rate.exceed(100, 60s))
```

### **Advanced Security**
```javascript
(
  (http.user_agent eq "" or 
   http.user_agent contains "sqlmap" or
   http.request.uri.query contains "union select") and
  ip.geoip.country ne "VN"
)
```

## 🎨 UI Features

### **Responsive Design**
- ✅ Desktop optimized
- ✅ Tablet friendly
- ✅ Mobile responsive
- ✅ Touch-friendly controls

### **Real-time Features**
- 🔄 Auto-refresh stats
- ⚡ Live validation
- 🔔 Push notifications
- 📊 Dynamic charts

### **User Experience**
- 🌈 Beautiful gradients
- ✨ Smooth animations
- 🎯 Intuitive navigation
- 🚀 Fast loading

### **Accessibility**
- ♿ Keyboard navigation
- 🔍 Screen reader friendly
- 🎨 High contrast support
- 📱 Mobile accessibility

## 🔧 Customization

### **Colors và Themes**
```css
/* Trong security-manager.css */
:root {
  --primary-color: #3b82f6;
  --success-color: #22c55e;
  --danger-color: #ef4444;
  --warning-color: #f59e0b;
}
```

### **Menu Items**
```javascript
// Trong security-manager.js
const menuItems = [
  { id: 'dashboard', icon: 'home', text: 'Dashboard' },
  { id: 'create', icon: 'plus', text: 'Tạo Rules' },
  // Add your custom items
];
```

### **Templates**
```php
// Trong security_manager.php
private function getSecurityTemplates() {
    return [
        // Add your custom templates
        [
            'id' => 'custom_template',
            'name' => 'Custom Security Rule',
            'category' => 'Custom',
            'expression' => '(your custom expression)',
            'action' => 'block'
        ]
    ];
}
```

## 🚨 Troubleshooting

### **Common Issues**

**1. API Connection Failed**
```
Solution: Check Cloudflare API credentials in config.php
```

**2. Expression Validation Error**
```
Solution: Use Expression Builder or check Cloudflare docs
```

**3. UI Not Loading**
```
Solution: Check CSS/JS file paths and permissions
```

**4. Zone Not Found**
```
Solution: Verify zone ID and API permissions
```

### **Debug Mode**
```php
// Trong config.php
define('DEBUG_MODE', true);
```

### **Console Debugging**
```javascript
// Trong browser console
securityManager.debug = true;
```

## 📊 Performance Tips

### **Optimization**
- 🔄 Use browser caching for assets
- ⚡ Minify CSS/JS in production
- 📦 Enable gzip compression
- 🌐 Use CDN for static assets

### **Best Practices**
- 📝 Test expressions before applying
- 🔍 Monitor rule performance
- 📊 Regular review of active rules
- 🔄 Keep expressions simple

## 🔐 Security Considerations

### **Production Setup**
```php
// Secure configuration
$allowedIPs = ['your.admin.ip'];
$requireAuth = true;
$sessionTimeout = 1800; // 30 phút
```

### **Access Control**
- 🔒 Password protect admin panel
- 🌐 IP whitelist for access
- 🔑 Session management
- 🛡️ CSRF protection

## 📞 Support

### **Resources**
- 📚 [Cloudflare Expression Docs](https://developers.cloudflare.com/ruleset-engine/rules-language/)
- 🔧 [API Reference](https://developers.cloudflare.com/api/)
- 💬 [Community Support](https://community.cloudflare.com/)

### **Contact**
- 📧 Email support
- 💬 Live chat
- 🐛 GitHub issues
- 📖 Documentation updates

---

## 🎉 Conclusion

Giao diện Security Rules Management UI cung cấp một solution hoàn chỉnh cho việc quản lý bảo mật Cloudflare với:

- ✅ **Easy-to-use interface** với Vietnamese language support
- ⚡ **Real-time operations** và validation
- 🎨 **Professional design** với responsive layout
- 🔧 **Full functionality** từ basic đến advanced
- 📊 **Comprehensive monitoring** và analytics

Enjoy building secure and performant websites! 🚀🛡️