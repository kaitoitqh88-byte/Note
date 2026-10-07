# 🔐 API Key Management - Hướng Dẫn Sử Dụng

API Key Management đã được tích hợp vào **menu chính** của Cloudflare Security Tool PHP Launcher. Tính năng này cho phép quản lý khóa bảo mật cho WordPress Scanner một cách dễ dàng.

## 🚀 Cách Truy Cập

### Từ Menu Chính:
```
🛡️ TOOL OPTIONS
==============================
1. 🚀 Start Server & Open Browser
2. 🌐 Open in Browser (if server running)
3. ⚙️ Edit Configuration
4. 📁 Open Tool Directory
5. ℹ️ Show Tool Info
6. 🔐 API Key Management      ← CHỌN TÙY CHỌN NÀY
7. 🛑 Stop Server & Exit

Choose option (1-7): 6
```

## 🔧 Menu Quản Lý API Key

Sau khi chọn option 6, bạn sẽ thấy menu con:

```
🔐 API KEY MANAGEMENT
========================================
1. 📋 List All API Keys
2. 🔑 Generate New API Key
3. ❌ Revoke API Key
4. 📊 View Key Usage Stats
5. 🔧 Key Settings
6. 🔙 Back to Main Menu

Choose option (1-6):
```

## 📋 Các Tính Năng Chi Tiết

### 1. 📋 List All API Keys
- Hiển thị tất cả API keys hiện có
- Thông tin bao gồm: tên, trạng thái, ngày tạo, hạn sử dụng, số lần sử dụng
- Permissions được cấp cho mỗi key

**Ví dụ output:**
```
🔑 Key: ...abc12345
   📝 Name: WordPress Scanner Key
   📊 Status: 🟢 Active
   📅 Created: 2026-03-13 10:30:15
   ⏰ Expires: Never
   🔢 Usage: 15 times
   🛡️ Permissions: scan, backup
```

### 2. 🔑 Generate New API Key
- Tạo API key mới với tên tùy chỉnh
- Chọn permissions: `scan`, `backup`, `manage`, `admin`
- Thiết lập thời gian hết hạn (tùy chọn)

**Quy trình:**
1. Nhập tên cho key
2. Chọn permissions (cách nhau bởi dấu phẩy)
3. Thiết lập thời gian hết hạn (số ngày, hoặc Enter để không hết hạn)
4. Key được tạo với định dạng: `wpsk_[64_characters]`

### 3. ❌ Revoke API Key
- Vô hiệu hóa API key không còn sử dụng
- Hiển thị danh sách keys hiện có
- Yêu cầu xác nhận trước khi revoke

**Cách sử dụng:**
1. Xem danh sách keys có thể revoke
2. Nhập 8 ký tự cuối của key
3. Xác nhận thao tác

### 4. 📊 View Key Usage Stats
Hiển thị thống kê tổng quan:
- 📈 Total Keys: Tổng số keys
- 🟢 Active Keys: Keys đang hoạt động  
- 🔴 Inactive Keys: Keys đã bị vô hiệu hóa
- ⚡ Total API Calls: Tổng số lệnh gọi API
- 🏆 Most Used Key: Key được sử dụng nhiều nhất
- 📋 Recent Access: 5 lệnh truy cập gần nhất

### 5. 🔧 Key Settings
Cài đặt hệ thống API key:
- **Require API Key**: Bật/tắt yêu cầu API key
- **Session Timeout**: Thời gian timeout session (giây)
- **Max Login Attempts**: Số lần đăng nhập tối đa
- **Lockout Time**: Thời gian khóa sau khi vượt quá số lần thử

## 📁 File Lưu Trữ

API keys được lưu trong: `Data_Config/api_keys.json`

**Cấu trúc file:**
```json
{
    "api_keys": {
        "wpsk_abc123...": {
            "key": "wpsk_abc123...",
            "name": "WordPress Scanner Key",
            "permissions": ["scan", "backup"],
            "created": "2026-03-13 10:30:15",
            "expires": null,
            "last_used": "2026-03-13 14:25:30",
            "usage_count": 15,
            "active": true
        }
    },
    "access_logs": [...],
    "failed_attempts": [...],
    "settings": {...}
}
```

## 🔒 Bảo Mật

### Quyền File:
- File `api_keys.json` được set quyền `0600` (chỉ owner đọc/ghi)
- Thư mục `Data_Config` được tạo với quyền `0755`

### Tính Năng Bảo Mật:
- ✅ **Rate Limiting**: Giới hạn số lần thử đăng nhập
- ✅ **IP Lockout**: Khóa IP sau số lần thử vượt quá
- ✅ **Session Management**: Quản lý session timeout
- ✅ **Access Logging**: Ghi log tất cả truy cập
- ✅ **Key Expiration**: Hỗ trợ keys có thời hạn
- ✅ **Permission System**: Phân quyền theo chức năng

## 🎯 Permissions Available

| Permission | Mô Tả |
|------------|-------|
| `scan` | Quyền scan website, tìm malware |
| `backup` | Quyền backup dữ liệu |
| `manage` | Quyền quản lý cài đặt |
| `admin` | Quyền admin full access |

## 🚀 Quick Start

1. **Khởi động Tool:**
   ```bash
   php portable_launcher.php
   ```

2. **Vào Menu API Key:**
   - Chọn option `6` từ menu chính

3. **Tạo Key Đầu Tiên:**
   - Chọn option `2` (Generate New API Key)
   - Nhập tên: `WordPress Scanner`
   - Permissions: `scan,backup`
   - Hạn sử dụng: Enter (không hạn)

4. **Sử Dụng Key:**
   - Sao chép key được tạo
   - Sử dụng trong các API calls của WordPress Scanner

## ⚠️ Lưu Ý Quan Trọng

- **Backup Keys**: Lưu copy của API keys ở nơi an toàn
- **Rotate Keys**: Định kỳ tạo keys mới và revoke keys cũ
- **Monitor Usage**: Thường xuyên kiểm tra usage stats
- **Secure Storage**: File `api_keys.json` chứa thông tin nhạy cảm
- **Access Control**: Chỉ admin có quyền truy cập tool này

## 🔧 Integration

API Key Manager có thể được tích hợp với:
- WordPress Scanner tools
- Backup systems
- Security monitoring tools
- Custom applications

Sử dụng class `APISecretKeyManager` để validate keys trong các applications khác.

---

**🎉 API Key Management đã được tích hợp hoàn toàn vào Cloudflare Security Tool!**

*Truy cập qua Menu Chính → Option 6 → Quản lý API Keys dễ dàng*