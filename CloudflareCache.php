<?php
/**
 * Cloudflare Cache Manager
 * Hệ thống cache tối ưu cho Cloudflare API để giảm API calls và tăng hiệu suất
 */

class CloudflareCache {
    private $cacheDir;
    private $defaultTTL;
    private $maxCacheSize;
    private $enableCompression;
    
    // Cache TTL cho các loại dữ liệu khác nhau (tính theo giây)
    const CACHE_TTL = [
        'zones_list' => 300,      // 5 phút - zones list ít thay đổi
        'zone_details' => 180,    // 3 phút - thông tin chi tiết zone
        'dns_records' => 120,     // 2 phút - DNS records có thể thay đổi thường xuyên  
        'ssl_info' => 600,        // 10 phút - SSL info ít thay đổi
        'analytics' => 60,        // 1 phút - analytics data cần fresh
        'search_results' => 240,  // 4 phút - kết quả tìm kiếm
        'bulk_operations' => 30   // 30 giây - bulk operations cần fresh
    ];
    
    public function __construct($cacheDir = null, $defaultTTL = 300, $maxCacheSize = 100) {
        $this->cacheDir = $cacheDir ?: __DIR__ . '/cache';
        $this->defaultTTL = $defaultTTL;
        $this->maxCacheSize = $maxCacheSize * 1024 * 1024; // Convert MB to bytes
        $this->enableCompression = extension_loaded('zlib');
        
        $this->initCacheDirectory();
        $this->cleanupOldCache();
    }
    
    /**
     * Khởi tạo thư mục cache
     */
    private function initCacheDirectory() {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Tạo file .htaccess để bảo vệ cache directory
        $htaccessFile = $this->cacheDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Deny from all\n");
        }
        
        // Tạo index.php để bảo vệ
        $indexFile = $this->cacheDir . '/index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php\n// Cloudflare Cache Directory\n// Access Denied\nexit;\n");
        }
    }
    
    /**
     * Get cache directory path
     */
    public function getCacheDir() {
        return $this->cacheDir;
    }
    
    /**
     * Tạo cache key từ endpoint và parameters
     */
    private function generateCacheKey($endpoint, $params = [], $cacheType = 'default') {
        $dataString = $endpoint . serialize($params) . $cacheType;
        return $cacheType . '_' . md5($dataString);
    }
    
    /**
     * Lấy đường dẫn file cache
     */
    private function getCacheFilePath($cacheKey) {
        return $this->cacheDir . '/' . $cacheKey . '.cache';
    }
    
    /**
     * Lưu dữ liệu vào cache
     */
    public function set($endpoint, $data, $params = [], $cacheType = 'default', $customTTL = null) {
        $cacheKey = $this->generateCacheKey($endpoint, $params, $cacheType);
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        $ttl = $customTTL ?: (self::CACHE_TTL[$cacheType] ?? $this->defaultTTL);
        $expiresAt = time() + $ttl;
        
        $cacheData = [
            'data' => $data,
            'expires_at' => $expiresAt,
            'created_at' => time(),
            'cache_type' => $cacheType,
            'endpoint' => $endpoint,
            'params' => $params
        ];
        
        $serializedData = serialize($cacheData);
        
        // Nén dữ liệu nếu có thể
        if ($this->enableCompression) {
            $serializedData = gzcompress($serializedData, 6);
        }
        
        $result = file_put_contents($cacheFile, $serializedData, LOCK_EX);
        
        // Cleanup cache nếu cần
        $this->maintainCacheSize();
        
        return $result !== false;
    }
    
    /**
     * Lấy dữ liệu từ cache
     */
    public function get($endpoint, $params = [], $cacheType = 'default') {
        $cacheKey = $this->generateCacheKey($endpoint, $params, $cacheType);
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $fileContent = file_get_contents($cacheFile);
        if ($fileContent === false) {
            return null;
        }
        
        // Giải nén nếu cần
        if ($this->enableCompression) {
            $fileContent = gzuncompress($fileContent);
            if ($fileContent === false) {
                // Nếu giải nén thất bại, xóa file cache
                unlink($cacheFile);
                return null;
            }
        }
        
        $cacheData = unserialize($fileContent);
        if ($cacheData === false) {
            // Nếu unserialize thất bại, xóa file cache
            unlink($cacheFile);
            return null;
        }
        
        // Kiểm tra expiry
        if (time() > $cacheData['expires_at']) {
            unlink($cacheFile);
            return null;
        }
        
        return $cacheData['data'];
    }
    
    /**
     * Kiểm tra xem cache có tồn tại và còn valid không
     */
    public function has($endpoint, $params = [], $cacheType = 'default') {
        return $this->get($endpoint, $params, $cacheType) !== null;
    }
    
    /**
     * Xóa cache cho endpoint cụ thể
     */
    public function delete($endpoint, $params = [], $cacheType = 'default') {
        $cacheKey = $this->generateCacheKey($endpoint, $params, $cacheType);
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }
    
    /**
     * Xóa tất cả cache của một loại
     */
    public function deleteByCacheType($cacheType) {
        $pattern = $this->cacheDir . '/' . $cacheType . '_*.cache';
        $files = glob($pattern);
        
        $deleted = 0;
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Xóa toàn bộ cache
     */
    public function clear() {
        $files = glob($this->cacheDir . '/*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Lấy thông tin cache statistics
     */
    public function getStats() {
        $files = glob($this->cacheDir . '/*.cache');
        $totalFiles = count($files);
        $totalSize = 0;
        $validFiles = 0;
        $expiredFiles = 0;
        $cacheTypes = [];
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            // Đọc file để check expiry
            $fileContent = file_get_contents($file);
            if ($fileContent !== false) {
                if ($this->enableCompression) {
                    $fileContent = gzuncompress($fileContent);
                }
                
                if ($fileContent !== false) {
                    $cacheData = unserialize($fileContent);
                    if ($cacheData !== false) {
                        $cacheType = $cacheData['cache_type'];
                        if (!isset($cacheTypes[$cacheType])) {
                            $cacheTypes[$cacheType] = 0;
                        }
                        $cacheTypes[$cacheType]++;
                        
                        if (time() <= $cacheData['expires_at']) {
                            $validFiles++;
                        } else {
                            $expiredFiles++;
                        }
                    }
                }
            }
        }
        
        return [
            'total_files' => $totalFiles,
            'valid_files' => $validFiles,
            'expired_files' => $expiredFiles,
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'cache_types' => $cacheTypes,
            'compression_enabled' => $this->enableCompression,
            'max_cache_size_mb' => round($this->maxCacheSize / 1024 / 1024, 2)
        ];
    }
    
    /**
     * Dọn dẹp cache cũ
     */
    private function cleanupOldCache() {
        $files = glob($this->cacheDir . '/*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            $fileContent = file_get_contents($file);
            if ($fileContent === false) {
                unlink($file);
                $deleted++;
                continue;
            }
            
            if ($this->enableCompression) {
                $fileContent = gzuncompress($fileContent);
                if ($fileContent === false) {
                    unlink($file);
                    $deleted++;
                    continue;
                }
            }
            
            $cacheData = unserialize($fileContent);
            if ($cacheData === false || time() > $cacheData['expires_at']) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Maintain cache size trong giới hạn
     */
    private function maintainCacheSize() {
        $totalSize = 0;
        $files = [];
        
        foreach (glob($this->cacheDir . '/*.cache') as $file) {
            $size = filesize($file);
            $mtime = filemtime($file);
            $totalSize += $size;
            $files[] = ['path' => $file, 'size' => $size, 'mtime' => $mtime];
        }
        
        if ($totalSize <= $this->maxCacheSize) {
            return;
        }
        
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return $a['mtime'] - $b['mtime'];
        });
        
        // Xóa files cũ nhất cho đến khi kích thước về dưới giới hạn
        $deleted = 0;
        foreach ($files as $file) {
            if ($totalSize <= $this->maxCacheSize * 0.8) { // Giữ 80% để tránh cleanup liên tục
                break;
            }
            
            if (unlink($file['path'])) {
                $totalSize -= $file['size'];
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Get cache info for debugging
     */
    public function getCacheInfo($endpoint, $params = [], $cacheType = 'default') {
        $cacheKey = $this->generateCacheKey($endpoint, $params, $cacheType);
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $fileContent = file_get_contents($cacheFile);
        if ($this->enableCompression) {
            $fileContent = gzuncompress($fileContent);
        }
        
        $cacheData = unserialize($fileContent);
        if ($cacheData === false) {
            return null;
        }
        
        return [
            'cache_key' => $cacheKey,
            'file_path' => $cacheFile,
            'created_at' => date('Y-m-d H:i:s', $cacheData['created_at']),
            'expires_at' => date('Y-m-d H:i:s', $cacheData['expires_at']),
            'ttl_remaining' => max(0, $cacheData['expires_at'] - time()),
            'is_valid' => time() <= $cacheData['expires_at'],
            'file_size' => filesize($cacheFile),
            'cache_type' => $cacheData['cache_type'],
            'endpoint' => $cacheData['endpoint']
        ];
    }
}