#!/usr/bin/env python3
"""
Cloudflare Security Tool - Package Builder
Creates complete portable packages for all platforms
"""

import os
import sys
import shutil
import zipfile
import tarfile
import json
import time
from pathlib import Path

class ToolPackageBuilder:
    def __init__(self):
        self.script_dir = Path(__file__).parent
        self.output_dir = self.script_dir / 'packages'
        self.temp_dir = self.script_dir / 'temp_build'
        
        # Package info
        self.package_info = {
            'name': 'cloudflare-security-tool',
            'version': '1.0.0',
            'description': 'Portable Cloudflare Security Rules Management Tool',
            'author': 'Security Tools Team',
            'platforms': ['windows', 'macos', 'linux', 'portable']
        }
        
    def print_status(self, message, level="INFO"):
        icons = {"INFO": "ℹ️", "SUCCESS": "✅", "WARNING": "⚠️", "ERROR": "❌"}
        print(f"{icons.get(level, 'ℹ️')} {message}")
        
    def cleanup(self):
        """Clean up temporary files"""
        if self.temp_dir.exists():
            shutil.rmtree(self.temp_dir)
        self.temp_dir.mkdir(exist_ok=True)
        
        if not self.output_dir.exists():
            self.output_dir.mkdir(exist_ok=True)
            
    def create_base_files(self, target_dir):
        """Create base files required for all packages"""
        
        # Copy main tool HTML (will be sourced from the file we created earlier)
        src_tool = self.script_dir / 'cloudflare_security_tool.html'
        if src_tool.exists():
            shutil.copy2(src_tool, target_dir / 'cloudflare_security_tool.html')
        else:
            self.create_embedded_tool_html(target_dir)
            
        # Create config template
        self.create_config_template(target_dir)
        
        # Create documentation
        self.create_documentation(target_dir)
        
    def create_embedded_tool_html(self, target_dir):
        """Create embedded HTML tool with all assets inline"""
        html_content = '''<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛡️ Cloudflare Security Rules Tool</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>''' + self.get_embedded_css() + '''</style>
</head>
<body>''' + self.get_embedded_html_body() + '''
    <script>''' + self.get_embedded_js() + '''</script>
</body>
</html>'''
        
        with open(target_dir / 'cloudflare_security_tool.html', 'w', encoding='utf-8') as f:
            f.write(html_content)
            
    def get_embedded_css(self):
        """Get embedded CSS for the tool"""
        return '''
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
            color: #333;
        }
        .tool-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .tool-header {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .tool-header h1 { font-size: 2.5em; margin-bottom: 10px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .config-panel {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
        .tool-sections { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; }
        .tool-section {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .tool-section:hover { transform: translateY(-5px); }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .alert-success { background: linear-gradient(135deg, #dcfce7, #d1fae5); border-color: #22c55e; color: #166534; }
        .alert-error { background: linear-gradient(135deg, #fee2e2, #fecaca); border-color: #dc2626; color: #991b1b; }
        /* Additional responsive and styling rules would be here */
        '''
        
    def get_embedded_html_body(self):
        """Get embedded HTML body content"""
        return '''
    <div class="tool-container">
        <div class="tool-header">
            <h1><i class="fas fa-shield-alt"></i> Cloudflare Security Rules Tool</h1>
            <p>Portable Cross-Platform Security Management Tool</p>
        </div>
        
        <div class="config-panel">
            <h3><i class="fas fa-cog"></i> API Configuration</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <label>Cloudflare Email:</label>
                    <input type="email" id="cfEmail" class="form-control" placeholder="your-email@domain.com">
                </div>
                <div>
                    <label>API Key:</label>
                    <input type="password" id="cfApiKey" class="form-control" placeholder="Global API Key">
                </div>
                <div>
                    <label>Zone ID:</label>
                    <input type="text" id="cfZoneId" class="form-control" placeholder="Zone ID">
                </div>
                <div>
                    <button id="testConnection" class="btn">
                        <i class="fas fa-plug"></i> Test Connection
                    </button>
                </div>
            </div>
        </div>
        
        <div id="alertContainer"></div>
        
        <div class="tool-sections">
            <div class="tool-section">
                <h3><i class="fas fa-plus"></i> Create Security Rule</h3>
                <div style="margin-bottom: 15px;">
                    <label>Rule Description:</label>
                    <input type="text" id="ruleDescription" class="form-control" placeholder="e.g., Block SQL injection attacks">
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Expression:</label>
                    <textarea id="ruleExpression" class="form-control" rows="4" placeholder='(http.user_agent contains "bot")'></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Action:</label>
                    <select id="ruleAction" class="form-control">
                        <option value="block">Block</option>
                        <option value="challenge">Challenge</option>
                        <option value="allow">Allow</option>
                        <option value="log">Log</option>
                    </select>
                </div>
                <button id="createRule" class="btn">
                    <i class="fas fa-plus"></i> Create Rule
                </button>
            </div>
            
            <div class="tool-section">
                <h3><i class="fas fa-list"></i> Security Rules</h3>
                <button id="refreshRules" class="btn" style="margin-bottom: 15px;">
                    <i class="fas fa-sync"></i> Refresh
                </button>
                <div id="rulesContainer" style="min-height: 200px; background: #f8fafc; border-radius: 8px; padding: 15px;">
                    <p style="text-align: center; color: #6b7280;">Configure API and click Refresh to view rules</p>
                </div>
            </div>
        </div>
    </div>
    '''
    
    def get_embedded_js(self):
        """Get embedded JavaScript for the tool"""
        return '''
        class CloudflareSecurityTool {
            constructor() {
                this.apiBase = 'https://api.cloudflare.com/client/v4';
                this.config = { email: '', apiKey: '', zoneId: '' };
                this.init();
            }
            
            init() {
                this.loadConfig();
                this.bindEvents();
            }
            
            loadConfig() {
                const saved = localStorage.getItem('cloudflare_config');
                if (saved) {
                    this.config = JSON.parse(saved);
                    document.getElementById('cfEmail').value = this.config.email;
                    document.getElementById('cfApiKey').value = this.config.apiKey;
                    document.getElementById('cfZoneId').value = this.config.zoneId;
                }
            }
            
            bindEvents() {
                document.getElementById('testConnection').addEventListener('click', () => this.testConnection());
                document.getElementById('createRule').addEventListener('click', () => this.createRule());
                document.getElementById('refreshRules').addEventListener('click', () => this.loadRules());
                
                ['cfEmail', 'cfApiKey', 'cfZoneId'].forEach(id => {
                    document.getElementById(id).addEventListener('blur', () => this.saveConfig());
                });
            }
            
            saveConfig() {
                this.config = {
                    email: document.getElementById('cfEmail').value.trim(),
                    apiKey: document.getElementById('cfApiKey').value.trim(),
                    zoneId: document.getElementById('cfZoneId').value.trim()
                };
                localStorage.setItem('cloudflare_config', JSON.stringify(this.config));
            }
            
            async makeRequest(endpoint, method = 'GET', body = null) {
                const headers = {
                    'X-Auth-Email': this.config.email,
                    'X-Auth-Key': this.config.apiKey,
                    'Content-Type': 'application/json'
                };
                
                const response = await fetch(this.apiBase + endpoint, {
                    method, headers, body: body ? JSON.stringify(body) : null
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.errors?.[0]?.message || `HTTP ${response.status}`);
                }
                
                return await response.json();
            }
            
            async testConnection() {
                try {
                    this.saveConfig();
                    const response = await this.makeRequest('/zones');
                    if (response.success) {
                        this.showAlert('API connection successful!', 'success');
                    }
                } catch (error) {
                    this.showAlert('Connection error: ' + error.message, 'error');
                }
            }
            
            async createRule() {
                try {
                    const description = document.getElementById('ruleDescription').value.trim();
                    const expression = document.getElementById('ruleExpression').value.trim();
                    const action = document.getElementById('ruleAction').value;
                    
                    if (!description || !expression || !this.config.zoneId) {
                        this.showAlert('Please fill all fields and configure API', 'error');
                        return;
                    }
                    
                    const ruleData = { action, expression, description, enabled: true, priority: 5 };
                    const response = await this.makeRequest(`/zones/${this.config.zoneId}/firewall/rules`, 'POST', [ruleData]);
                    
                    if (response.success) {
                        this.showAlert('Rule created successfully!', 'success');
                        document.getElementById('ruleDescription').value = '';
                        document.getElementById('ruleExpression').value = '';
                        this.loadRules();
                    }
                } catch (error) {
                    this.showAlert('Error creating rule: ' + error.message, 'error');
                }
            }
            
            async loadRules() {
                try {
                    if (!this.config.zoneId) return;
                    
                    const response = await this.makeRequest(`/zones/${this.config.zoneId}/firewall/rules`);
                    if (response.success) {
                        this.renderRules(response.result);
                    }
                } catch (error) {
                    this.showAlert('Error loading rules: ' + error.message, 'error');
                }
            }
            
            renderRules(rules) {
                const container = document.getElementById('rulesContainer');
                if (rules.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #6b7280;">No rules found</p>';
                    return;
                }
                
                const rulesHTML = rules.map(rule => `
                    <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-bottom: 10px; background: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong>${rule.description || 'Unnamed Rule'}</strong>
                            <span style="padding: 4px 12px; background: ${rule.paused ? '#fee2e2' : '#dcfce7'}; color: ${rule.paused ? '#991b1b' : '#166534'}; border-radius: 20px; font-size: 12px;">
                                ${rule.paused ? 'Disabled' : 'Enabled'}
                            </span>
                        </div>
                        <div style="background: #f1f5f9; padding: 8px; border-radius: 6px; font-family: monospace; font-size: 13px; margin-bottom: 10px;">
                            ${rule.filter?.expression || 'N/A'}
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Action: <strong>${rule.action}</strong> | Priority: <strong>${rule.priority}</strong></span>
                            <button onclick="tool.deleteRule('${rule.id}')" style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                                Delete
                            </button>
                        </div>
                    </div>
                `).join('');
                
                container.innerHTML = rulesHTML;
            }
            
            async deleteRule(ruleId) {
                if (!confirm('Are you sure you want to delete this rule?')) return;
                
                try {
                    const response = await this.makeRequest(`/zones/${this.config.zoneId}/firewall/rules/${ruleId}`, 'DELETE');
                    if (response.success) {
                        this.showAlert('Rule deleted successfully!', 'success');
                        this.loadRules();
                    }
                } catch (error) {
                    this.showAlert('Error deleting rule: ' + error.message, 'error');
                }
            }
            
            showAlert(message, type) {
                const alertContainer = document.getElementById('alertContainer');
                const alertId = 'alert_' + Date.now();
                
                const alertHTML = `
                    <div id="${alertId}" class="alert alert-${type}">
                        ${message}
                    </div>
                `;
                
                alertContainer.insertAdjacentHTML('beforeend', alertHTML);
                setTimeout(() => document.getElementById(alertId)?.remove(), 5000);
            }
        }
        
        const tool = new CloudflareSecurityTool();
        '''
        
    def create_config_template(self, target_dir):
        """Create configuration template"""
        config = {
            "cloudflare": {
                "email": "",
                "api_key": "",
                "zone_id": ""
            },
            "tool": {
                "theme": "default",
                "language": "vi",
                "auto_refresh": True,
                "refresh_interval": 30,
                "port": 8080
            },
            "security": {
                "require_confirmation": True,
                "log_actions": True,
                "backup_before_delete": True
            }
        }
        
        with open(target_dir / 'config.json.template', 'w', encoding='utf-8') as f:
            json.dump(config, f, indent=4, ensure_ascii=False)
            
    def create_documentation(self, target_dir):
        """Create documentation files"""
        readme_content = f'''# 🛡️ Cloudflare Security Rules Tool v{self.package_info['version']}

{self.package_info['description']}

## 🚀 Quick Start

### Windows
- Double-click `start_tool.bat` or `start_tool.ps1`
- Or run: `python portable_launcher.py --start`

### macOS/Linux  
- Run: `./start_tool.sh`
- Or run: `python3 portable_launcher.py --start`

### Universal
- Run: `python portable_launcher.py` (interactive menu)
- Or open `cloudflare_security_tool.html` directly in browser

## ⚙️ Setup

1. Copy `config.json.template` to `config.json`
2. Edit with your Cloudflare API credentials:
   - Email address
   - Global API Key
   - Zone ID
3. Launch using one of the methods above

## 📋 Features

- ✅ Create/Edit/Delete Cloudflare security rules
- ✅ Pre-built security templates
- ✅ Expression validation
- ✅ Real-time rule management
- ✅ Cross-platform compatibility
- ✅ Portable - no installation required
- ✅ Local web interface

## 🔧 Requirements

- Modern web browser (Chrome, Firefox, Safari, Edge)
- Python 3.6+ (for local server, optional)
- Cloudflare account with API access

## 🛠️ API Setup

1. Go to Cloudflare Dashboard → My Profile → API Tokens
2. Use Global API Key OR create custom token
3. Get Zone ID from domain overview page
4. Configure in `config.json`

## 📞 Support

- 📚 Full documentation in tool interface
- 🐛 Check console for error messages
- ⚙️ Verify API credentials
- 🔧 Ensure Python is installed for server mode

## 🔐 Security Notes

- API keys are stored locally only
- HTTPS connections to Cloudflare API
- No data sent to third parties
- Local tool execution

---

**Happy securing your Cloudflare zones! 🛡️**
'''
        
        with open(target_dir / 'README.md', 'w', encoding='utf-8') as f:
            f.write(readme_content)
            
    def create_windows_package(self):
        """Create Windows package"""
        self.print_status("Creating Windows package...")
        
        win_dir = self.temp_dir / 'windows'
        win_dir.mkdir(exist_ok=True)
        
        # Base files
        self.create_base_files(win_dir)
        
        # Copy portable launcher
        shutil.copy2(self.script_dir / 'portable_launcher.py', win_dir)
        
        # Windows-specific launchers
        with open(win_dir / 'start_tool.bat', 'w', encoding='utf-8') as f:
            f.write('''@echo off
echo 🛡️ Cloudflare Security Tool
echo ==========================

python portable_launcher.py --start
if %errorlevel% neq 0 (
    echo.
    echo Python not found. Opening tool directly...
    start cloudflare_security_tool.html
)
pause
''')
            
        with open(win_dir / 'start_tool.ps1', 'w', encoding='utf-8') as f:
            f.write('''# Cloudflare Security Tool - PowerShell Launcher
Write-Host "🛡️ Cloudflare Security Tool" -ForegroundColor Cyan
Write-Host "=========================="

try {
    python portable_launcher.py --start
} catch {
    Write-Host "Python not found. Opening tool directly..." -ForegroundColor Yellow
    Start-Process "cloudflare_security_tool.html"
}
''')
            
        # Create ZIP package
        zip_path = self.output_dir / f'cloudflare-security-tool-windows-v{self.package_info["version"]}.zip'
        with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
            for file_path in win_dir.rglob('*'):
                if file_path.is_file():
                    arcname = file_path.relative_to(win_dir)
                    zipf.write(file_path, arcname)
                    
        self.print_status(f"Windows package created: {zip_path}", "SUCCESS")
        
    def create_macos_package(self):
        """Create macOS package"""
        self.print_status("Creating macOS package...")
        
        mac_dir = self.temp_dir / 'macos'
        mac_dir.mkdir(exist_ok=True)
        
        # Base files
        self.create_base_files(mac_dir)
        
        # Copy portable launcher
        shutil.copy2(self.script_dir / 'portable_launcher.py', mac_dir)
        
        # macOS-specific launcher
        with open(mac_dir / 'start_tool.sh', 'w', encoding='utf-8') as f:
            f.write('''#!/bin/bash
echo "🛡️ Cloudflare Security Tool"
echo "=========================="

if command -v python3 &> /dev/null; then
    python3 portable_launcher.py --start
elif command -v python &> /dev/null; then
    python portable_launcher.py --start
else
    echo "Python not found. Opening tool directly..."
    open cloudflare_security_tool.html
fi
''')
        
        os.chmod(mac_dir / 'start_tool.sh', 0o755)
        os.chmod(mac_dir / 'portable_launcher.py', 0o755)
        
        # Create TAR.GZ package
        tar_path = self.output_dir / f'cloudflare-security-tool-macos-v{self.package_info["version"]}.tar.gz'
        with tarfile.open(tar_path, 'w:gz') as tarf:
            tarf.add(mac_dir, arcname='cloudflare-security-tool')
            
        self.print_status(f"macOS package created: {tar_path}", "SUCCESS")
        
    def create_linux_package(self):
        """Create Linux package"""
        self.print_status("Creating Linux package...")
        
        linux_dir = self.temp_dir / 'linux'
        linux_dir.mkdir(exist_ok=True)
        
        # Base files
        self.create_base_files(linux_dir)
        
        # Copy portable launcher
        shutil.copy2(self.script_dir / 'portable_launcher.py', linux_dir)
        
        # Linux-specific launcher
        with open(linux_dir / 'start_tool.sh', 'w', encoding='utf-8') as f:
            f.write('''#!/bin/bash
echo "🛡️ Cloudflare Security Tool"
echo "=========================="

if command -v python3 &> /dev/null; then
    python3 portable_launcher.py --start
elif command -v python &> /dev/null; then
    python portable_launcher.py --start
else
    echo "Python not found. Opening tool directly..."
    xdg-open cloudflare_security_tool.html 2>/dev/null || firefox cloudflare_security_tool.html 2>/dev/null || echo "Please open cloudflare_security_tool.html in your browser"
fi
''')
        
        os.chmod(linux_dir / 'start_tool.sh', 0o755)
        os.chmod(linux_dir / 'portable_launcher.py', 0o755)
        
        # Create TAR.GZ package
        tar_path = self.output_dir / f'cloudflare-security-tool-linux-v{self.package_info["version"]}.tar.gz'
        with tarfile.open(tar_path, 'w:gz') as tarf:
            tarf.add(linux_dir, arcname='cloudflare-security-tool')
            
        self.print_status(f"Linux package created: {tar_path}", "SUCCESS")
        
    def create_portable_package(self):
        """Create universal portable package"""
        self.print_status("Creating portable universal package...")
        
        portable_dir = self.temp_dir / 'portable'
        portable_dir.mkdir(exist_ok=True)
        
        # Base files
        self.create_base_files(portable_dir)
        
        # Copy portable launcher
        shutil.copy2(self.script_dir / 'portable_launcher.py', portable_dir)
        
        # Create launchers for all platforms
        launchers = {
            'start_tool.bat': '''@echo off
python portable_launcher.py --start
pause''',
            'start_tool.sh': '''#!/bin/bash
python3 portable_launcher.py --start 2>/dev/null || python portable_launcher.py --start''',
            'start_tool.ps1': '''python portable_launcher.py --start'''
        }
        
        for filename, content in launchers.items():
            with open(portable_dir / filename, 'w', encoding='utf-8') as f:
                f.write(content)
                
        # Make shell scripts executable
        os.chmod(portable_dir / 'start_tool.sh', 0o755)
        os.chmod(portable_dir / 'portable_launcher.py', 0o755)
        
        # Create ZIP package
        zip_path = self.output_dir / f'cloudflare-security-tool-portable-v{self.package_info["version"]}.zip'
        with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
            for file_path in portable_dir.rglob('*'):
                if file_path.is_file():
                    arcname = file_path.relative_to(portable_dir)
                    zipf.write(file_path, arcname)
                    
        self.print_status(f"Portable package created: {zip_path}", "SUCCESS")
        
    def create_installer_packages(self):
        """Create installer packages"""
        # Copy installer scripts to output
        if (self.script_dir / 'install_security_tool.sh').exists():
            shutil.copy2(self.script_dir / 'install_security_tool.sh', self.output_dir)
            
        if (self.script_dir / 'install_security_tool.bat').exists():
            shutil.copy2(self.script_dir / 'install_security_tool.bat', self.output_dir)
            
        self.print_status("Installer scripts copied to packages directory", "SUCCESS")
        
    def build_all_packages(self):
        """Build all platform packages"""
        self.print_status("🏗️ Starting package build process...")
        print("=" * 50)
        
        start_time = time.time()
        
        # Cleanup and prepare
        self.cleanup()
        
        # Create platform-specific packages
        self.create_windows_package()
        self.create_macos_package()  
        self.create_linux_package()
        self.create_portable_package()
        self.create_installer_packages()
        
        # Create package manifest
        self.create_package_manifest()
        
        # Cleanup temp files
        shutil.rmtree(self.temp_dir)
        
        elapsed = time.time() - start_time
        
        print("=" * 50)
        self.print_status(f"✅ All packages built successfully in {elapsed:.2f}s", "SUCCESS")
        self.print_status(f"📁 Output directory: {self.output_dir}", "INFO")
        
        # List created packages
        print("\n📦 Created packages:")
        for package in self.output_dir.iterdir():
            if package.is_file():
                size_mb = package.stat().st_size / (1024 * 1024)
                print(f"   • {package.name} ({size_mb:.2f} MB)")
                
    def create_package_manifest(self):
        """Create package manifest"""
        manifest = {
            'name': self.package_info['name'],
            'version': self.package_info['version'],
            'description': self.package_info['description'],
            'build_time': time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime()),
            'packages': []
        }
        
        for package_file in self.output_dir.iterdir():
            if package_file.suffix in ['.zip', '.tar.gz']:
                manifest['packages'].append({
                    'filename': package_file.name,
                    'platform': 'windows' if 'windows' in package_file.name else 
                              'macos' if 'macos' in package_file.name else
                              'linux' if 'linux' in package_file.name else 'portable',
                    'size_bytes': package_file.stat().st_size
                })
                
        with open(self.output_dir / 'packages.json', 'w', encoding='utf-8') as f:
            json.dump(manifest, f, indent=2, ensure_ascii=False)
            
        self.print_status("Package manifest created", "SUCCESS")

def main():
    """Main entry point"""
    print("🏗️ Cloudflare Security Tool - Package Builder")
    print("=" * 50)
    
    builder = ToolPackageBuilder()
    
    try:
        builder.build_all_packages()
        print("\n🎉 Package build completed successfully!")
        
    except KeyboardInterrupt:
        print("\n⚠️ Build cancelled by user")
        sys.exit(1)
    except Exception as e:
        builder.print_status(f"Build failed: {e}", "ERROR")
        sys.exit(1)

if __name__ == "__main__":
    main()