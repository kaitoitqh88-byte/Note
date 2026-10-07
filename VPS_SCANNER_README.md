# WordPress VPS Scanner - System Requirements

## PHP Extensions Required:

### For SSH Connection:
- php-ssh2 (Required for SSH functionality)
- To install on Ubuntu/Debian: `sudo apt-get install php-ssh2`
- To install on CentOS/RHEL: `sudo yum install php-ssh2`
- For Windows XAMPP: Download php_ssh2.dll and add to extensions

### For FTP Connection:
- php-ftp (Usually enabled by default)

### General Requirements:
- PHP 7.4+
- openssl extension (for secure connections)
- curl extension (for HTTP requests)

## Installation Commands:

### Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install php-ssh2 php-ftp php-curl
sudo systemctl restart apache2
```

### CentOS/RHEL:
```bash
sudo yum install php-ssh2 php-ftp php-curl
sudo systemctl restart httpd
```

### Check if extensions are loaded:
```php
<?php
// Check SSH2
if (extension_loaded('ssh2')) {
    echo "SSH2 extension is loaded\n";
} else {
    echo "SSH2 extension is NOT loaded\n";
}

// Check FTP
if (extension_loaded('ftp')) {
    echo "FTP extension is loaded\n";
} else {
    echo "FTP extension is NOT loaded\n";
}
?>
```

## VPS Scanner Features:

✅ **SSH Connection Support**
- SSH key-based authentication
- Password authentication  
- Command execution on remote server
- File download via SFTP

✅ **FTP Connection Support** 
- Standard FTP connection
- Passive mode support
- File transfer capabilities

✅ **WordPress Scanning Capabilities**
- Remote WordPress detection
- Core file integrity check
- Security vulnerability scan
- Plugin/theme analysis
- System information gathering

✅ **Backup & Download**
- Create WordPress backup on VPS
- Compress files (tar.gz format)
- Download backup to local server
- Automatic cleanup options

✅ **Security Features**
- No credential storage
- Secure connection handling
- Temporary file management
- Access control for downloads

## Usage Examples:

### Test VPS Connection:
1. Go to WordPress Scanner
2. Select "VPS Remote" tab
3. Enter VPS credentials
4. Click "Test Connection"

### Scan WordPress on VPS:
1. Fill VPS connection details
2. Enter WordPress path on VPS
3. Click "Quét VPS WordPress"
4. Review detailed scan results

### Backup from VPS:
1. After successful scan
2. Click "VPS Backup & Download"
3. Re-enter password for security
4. Backup will be created and downloaded

## Security Notes:

⚠️ **Important Security Considerations:**
- VPS credentials are never stored
- All connections use secure protocols
- Temporary files are cleaned up automatically
- Downloads folder has restricted access
- Always use strong passwords and SSH keys when possible