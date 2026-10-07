# Dashboard Cache Management

Hệ thống quản lý cache cho Dashboard được tích hợp vào trang dashboard chính với các tính năng sau:

## 🎯 Tính năng chính

### 1. Cache Management Panel
- **Thống kê cache**: Hiển thị số domain đã cache, hit rate, kích thước cache, thời gian cập nhật cuối
- **Warm Cache**: Tạo cache cho tất cả domains hiện có
- **Clear Cache**: Xóa toàn bộ cache domains
- **Cài đặt cache**: Bật/tắt cache, TTL settings

### 2. Cache Management Controls
- **Cache Domain Info**: Lưu cache cho các domains đang hiển thị
- **View Cached Domains**: Xem chi tiết cache trong tab mới
- **Export Cache Data**: Xuất dữ liệu cache thành JSON file
- **Import Cache Data**: Import cache data từ JSON file

### 3. Progress Tracking
- Progress indicator cho warm cache operations
- Real-time success/fail counters
- Current domain being processed

## 🔧 Cài đặt

### Files được thêm/cập nhật:

1. **dashboard.php** - Thêm Cache Management UI
   - Cache stats panel
   - Management controls
   - JavaScript functions

2. **DashboardHandler.php** - Thêm cache operations
   - `getCacheStatistics()`
   - `clearDomainCache()`
   - `warmDomainCache()`
   - `cacheCurrentDomains()`
   - `viewCacheDetails()`
   - `exportCacheData()`
   - `importCacheData()`

3. **index.php** - Thêm action routing
   - `get_cache_stats`
   - `clear_domain_cache`
   - `warm_domain_cache`
   - `cache_current_domains`
   - `view_cache_details`
   - `export_cache_data`
   - `import_cache_data`

## 📋 API Endpoints

### GET ?action=get_cache_stats
```json
{
  "success": true,
  "stats": {
    "totalCount": 15,
    "hitRate": 87,
    "size": "2.3 MB",
    "lastUpdated": "14:30:25 05/12/2024"
  }
}
```

### POST ?action=cache_current_domains
```json
{
  "success": true,
  "message": "Đã lưu cache cho 15 domains",
  "count": 15
}
```

### POST ?action=clear_domain_cache
```json
{
  "success": true,
  "message": "Đã xóa 15 file cache",
  "count": 15
}
```

### POST ?action=warm_domain_cache
Body:
```json
{
  "domain": "example.com",
  "zone_id": "abc123"
}
```

Response:
```json
{
  "success": true,
  "message": "Đã warm cache cho domain example.com",
  "domain": "example.com"
}
```

## 💡 Cách sử dụng

### 1. Truy cập Dashboard
```
?action=dashboard
```

### 2. Cache Management Panel
- Panel hiển thị ở trên danh sách domains
- Real-time statistics về cache usage
- Quick actions: Warm Cache, Clear Cache

### 3. Cache Controls trong Search Bar
- Dropdown "Cache" bên cạnh search controls
- Các options: Cache Domain Info, View Cache, Export/Import

### 4. Test Cache Management
```
test_cache_management.html
```
File test để kiểm tra tất cả cache operations

## ⚙️ Cấu hình

### Cache Settings Panel
- **Enable Domain Caching**: Bật/tắt cache system
- **Auto-cache on Domain Actions**: Tự động cache khi thao tác
- **Cache TTL**: Thời gian sống cache (phút)

### Cache Directory
```
cache/ (theo CloudflareCache.php configuration)
```

## 🔄 Workflow

### Cache-first Strategy
1. Check local cache first
2. If cache miss or expired, fetch from API
3. Save to cache with TTL
4. Return results

### Warm Cache Process
1. Get all zones from API
2. Cache each zone info individually
3. Show progress with success/fail counters
4. Update cache statistics

### Cache Statistics
- **Total Count**: Number of cached domains
- **Hit Rate**: Estimated cache effectiveness
- **Cache Size**: Total size of cache files
- **Last Updated**: Most recent cache update

## 📊 Performance Benefits

- **Faster Dashboard Loading**: Cache-first approach
- **Reduced API Calls**: Local cache hits
- **Bulk Operations**: Batch cache/clear operations
- **Progress Tracking**: User-friendly feedback

## 🧪 Testing

### Automated Testing
- Visit `test_cache_management.html` để test APIs
- Test các scenarios: stats, clear, warm, export/import

### Manual Testing
1. Load dashboard với cache management
2. Test warm cache for all domains
3. Verify cache statistics update
4. Test clear cache functionality
5. Export/Import cache data

## 🚀 Next Steps

1. **Cache Optimization**: Implement smarter TTL management
2. **Cache Analytics**: More detailed hit/miss statistics
3. **Cache Partitioning**: Separate cache for different data types
4. **Background Sync**: Auto-refresh expired cache

## ⭐ Key Features

- ✅ Complete cache management UI integrated
- ✅ Real-time cache statistics
- ✅ Bulk cache operations with progress tracking
- ✅ Export/Import cache functionality
- ✅ Cache settings configuration
- ✅ API endpoint documentation
- ✅ Comprehensive testing interface

Dashboard hiện đã có đầy đủ tính năng cache management tương tự như search page!