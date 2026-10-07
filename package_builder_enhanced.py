#!/usr/bin/env python3
"""
Enhanced Cloudflare Security Tool Package Builder
Supports both Python and PHP launchers with flexible packaging options
"""

import os
import sys
import json
import zipfile
import tarfile
import shutil
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Set, Optional

class CloudflareToolPackageBuilder:
    """Enhanced package builder supporting multiple languages and deployment options."""
    
    def __init__(self):
        self.tool_name = "cloudflare_security_tool"
        self.version = "2.0.0"
        self.current_dir = Path.cwd()
        self.output_dir = self.current_dir / "packages"
        self.temp_dir = self.current_dir / "temp_package"
        
        # Define file sets for different package types
        self.file_sets = {
            'core': [
                'cloudflare_security_tool.html',
                'README.md',
                'LICENSE',
                'SECURITY_TOOL_GUIDE.md'
            ],
            'python': [
                'portable_launcher.py',
                'start_tool.py',
                'start_tool.bat',
                'start_tool.sh',
                'install_security_tool.sh',
                'install_security_tool.bat'
            ],
            'php': [
                'portable_launcher.php',
                'start_tool_php.bat', 
                'start_tool_php.sh',
                'install_security_tool_php.sh',
                'install_security_tool_php.bat'
            ],
            'docs': [
                'API_SETUP_GUIDE.md',
                'DEPLOYMENT_OPTIONS.md',
                'TROUBLESHOOTING.md'
            ]
        }
        
        # Package configurations
        self.package_configs = {
            'standalone': {
                'name': 'standalone_html',
                'description': 'HTML-only version (no launcher)',
                'files': ['core'],
                'readme_suffix': 'standalone'
            },
            'python_complete': {
                'name': 'python_complete', 
                'description': 'Complete Python package with launcher',
                'files': ['core', 'python', 'docs'],
                'readme_suffix': 'python'
            },
            'php_complete': {
                'name': 'php_complete',
                'description': 'Complete PHP package with launcher', 
                'files': ['core', 'php', 'docs'],
                'readme_suffix': 'php'
            },
            'dual_language': {
                'name': 'dual_language',
                'description': 'Full package with both Python and PHP support',
                'files': ['core', 'python', 'php', 'docs'],
                'readme_suffix': 'dual'
            },
            'minimal': {
                'name': 'minimal',
                'description': 'Minimal package with HTML tool only',
                'files': ['core'],
                'readme_suffix': 'minimal'
            }
        }

    def create_output_dirs(self) -> None:
        """Create necessary output directories."""
        self.output_dir.mkdir(exist_ok=True)
        print(f"📁 Output directory: {self.output_dir}")

    def clean_temp_dir(self) -> None:
        """Clean and recreate temporary directory."""
        if self.temp_dir.exists():
            shutil.rmtree(self.temp_dir)
        self.temp_dir.mkdir()

    def get_file_list(self, file_groups: List[str]) -> List[str]:
        """Get list of files for given groups."""
        files = []
        for group in file_groups:
            if group in self.file_sets:
                files.extend(self.file_sets[group])
        return list(set(files))  # Remove duplicates

    def check_file_exists(self, filepath: str) -> bool:
        """Check if file exists and warn if missing."""
        if not Path(filepath).exists():
            print(f"⚠️  Missing file: {filepath}")
            return False
        return True

    def create_package_readme(self, package_type: str, package_config: Dict) -> str:
        """Create package-specific README content."""
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        
        content = f"""# 🛡️ Cloudflare Security Tool - {package_config['description']}

**Version:** {self.version}
**Package Type:** {package_type}
**Build Date:** {timestamp}

## 📋 Package Contents

This package contains the {package_config['description'].lower()}.

### Included Files:
"""
        
        files = self.get_file_list(package_config['files'])
        for file in sorted(files):
            if self.check_file_exists(file):
                content += f"- ✅ {file}\n"
            else:
                content += f"- ❌ {file} (missing)\n"

        # Add specific instructions based on package type
        if 'python' in package_config['files']:
            content += self._get_python_instructions()
        
        if 'php' in package_config['files']:
            content += self._get_php_instructions()
            
        if package_type == 'standalone' or package_type == 'minimal':
            content += self._get_standalone_instructions()
            
        if package_type == 'dual_language':
            content += self._get_dual_language_instructions()

        content += self._get_common_footer()
        
        return content

    def _get_python_instructions(self) -> str:
        """Get Python-specific instructions."""
        return """
## 🐍 Python Version Setup

### Requirements:
- Python 3.6 or higher
- No additional dependencies required

### Quick Start:
1. **Automatic Installation:**
   - Windows: Run `install_security_tool.bat`
   - Unix/Linux/macOS: Run `./install_security_tool.sh`

2. **Manual Start:**
   - Windows: Run `start_tool.bat` 
   - Unix/Linux/macOS: Run `./start_tool.sh`
   - Direct: `python portable_launcher.py`

### Features:
- ✅ Cross-platform compatibility
- ✅ Built-in web server
- ✅ Automatic browser opening
- ✅ Interactive configuration
- ✅ No external dependencies
"""

    def _get_php_instructions(self) -> str:
        """Get PHP-specific instructions."""
        return """
## 🔧 PHP Version Setup

### Requirements:
- PHP 7.4 or higher (8.0+ recommended)
- PHP CLI enabled
- No additional extensions required

### Quick Start:
1. **Automatic Installation:**
   - Windows: Run `install_security_tool_php.bat`
   - Unix/Linux/macOS: Run `./install_security_tool_php.sh`

2. **Manual Start:**
   - Windows: Run `start_tool_php.bat`
   - Unix/Linux/macOS: Run `./start_tool_php.sh`
   - Direct: `php portable_launcher.php`

### Features:
- ✅ Native PHP web server
- ✅ Cross-platform compatibility  
- ✅ Automatic browser opening
- ✅ Interactive menu system
- ✅ JSON configuration management
"""

    def _get_standalone_instructions(self) -> str:
        """Get standalone version instructions."""
        return """
## 🚀 Standalone HTML Version

### Requirements:
- Modern web browser (Chrome, Firefox, Safari, Edge)
- No server or programming language required

### Quick Start:
1. Open `cloudflare_security_tool.html` in your web browser
2. Enter your Cloudflare API credentials
3. Start managing your security rules

### Features:
- ✅ Zero installation required
- ✅ Works offline (after initial load)
- ✅ No server dependencies
- ✅ Portable - runs from any location
- ✅ Cross-platform compatible
"""

    def _get_dual_language_instructions(self) -> str:
        """Get dual language package instructions.""" 
        return """
## 🎯 Dual Language Package

This package includes both Python and PHP launchers. Choose the one that best fits your environment:

### Choose Your Launch Method:

**Python Users:**
- Use `start_tool.bat` (Windows) or `start_tool.sh` (Unix)
- Or run directly: `python portable_launcher.py`

**PHP Users:**  
- Use `start_tool_php.bat` (Windows) or `start_tool_php.sh` (Unix)
- Or run directly: `php portable_launcher.php`

**No Programming Language:**
- Open `cloudflare_security_tool.html` directly in browser

Both launchers provide identical functionality - choose based on what you have installed.
"""

    def _get_common_footer(self) -> str:
        """Get common footer content."""
        return """
## ⚙️ Configuration

### First Time Setup:
1. Copy `config_template.json` to `config.json` (if using launchers)
2. Edit `config.json` with your Cloudflare credentials:
   ```json
   {
       "apiKey": "your-global-api-key",
       "email": "your-cloudflare-email",
       "zoneId": "your-zone-id"
   }
   ```

### Getting Cloudflare Credentials:
1. **API Key:** Cloudflare Dashboard → My Profile → API Tokens → Global API Key
2. **Zone ID:** Cloudflare Dashboard → Select Domain → Overview → Zone ID (right sidebar)

## 🛠️ Usage

1. Start the tool using your preferred method
2. Open the web interface in your browser
3. The tool will load your current security rules
4. Add, edit, or delete rules as needed
5. Changes are applied immediately to Cloudflare

## 📞 Support

- 📚 See included documentation files for detailed guides
- 🐛 Report issues with detailed error messages
- 💡 Check browser console for debugging information

## 📄 License

This tool is provided as-is for managing Cloudflare security rules. Use responsibly and ensure you have proper authorization for the domains you manage.

---
*Generated by Enhanced Package Builder v{self.version}*
""".format(self.version)

    def copy_files_to_temp(self, files: List[str]) -> int:
        """Copy specified files to temporary directory."""
        copied = 0
        missing = []
        
        for file in files:
            source = Path(file) 
            if source.exists():
                dest = self.temp_dir / file
                dest.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(source, dest)
                copied += 1
                print(f"  📄 {file}")
            else:
                missing.append(file)
                
        if missing:
            print(f"⚠️  Missing files: {', '.join(missing)}")
            
        return copied

    def create_package_json(self, package_type: str, package_config: Dict) -> None:
        """Create package metadata JSON."""
        metadata = {
            "name": f"{self.tool_name}_{package_config['name']}",
            "version": self.version,
            "package_type": package_type,
            "description": package_config['description'],
            "build_date": datetime.now().isoformat(),
            "files_included": self.get_file_list(package_config['files']),
            "supported_platforms": ["Windows", "macOS", "Linux"],
            "requirements": self._get_package_requirements(package_config)
        }
        
        with open(self.temp_dir / "package.json", "w", encoding="utf-8") as f:
            json.dump(metadata, f, indent=2)

    def _get_package_requirements(self, package_config: Dict) -> Dict:
        """Get requirements for package type."""
        requirements = {"browser": "Modern web browser"}
        
        if 'python' in package_config['files']:
            requirements["python"] = "Python 3.6+"
            
        if 'php' in package_config['files']:
            requirements["php"] = "PHP 7.4+"
            
        return requirements

    def create_zip_package(self, package_name: str) -> Path:
        """Create ZIP package from temporary directory."""
        zip_path = self.output_dir / f"{package_name}.zip"
        
        with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
            for file_path in self.temp_dir.rglob('*'):
                if file_path.is_file():
                    arc_path = file_path.relative_to(self.temp_dir)
                    zipf.write(file_path, arc_path)
                    
        return zip_path

    def create_tar_package(self, package_name: str) -> Path:
        """Create TAR.GZ package from temporary directory."""
        tar_path = self.output_dir / f"{package_name}.tar.gz"
        
        with tarfile.open(tar_path, 'w:gz') as tarf:
            tarf.add(self.temp_dir, arcname=package_name)
            
        return tar_path

    def build_package(self, package_type: str) -> None:
        """Build a specific package type."""
        if package_type not in self.package_configs:
            print(f"❌ Unknown package type: {package_type}")
            return
            
        package_config = self.package_configs[package_type]
        package_name = f"{self.tool_name}_{package_config['name']}_v{self.version}"
        
        print(f"\n📦 Building package: {package_type}")
        print(f"   Description: {package_config['description']}")
        
        # Clean and prepare temp directory
        self.clean_temp_dir()
        
        # Get files to include
        files = self.get_file_list(package_config['files'])
        print(f"   Files to include: {len(files)}")
        
        # Copy files
        copied = self.copy_files_to_temp(files)
        if copied == 0:
            print(f"❌ No files copied for {package_type}")
            return
            
        # Create package-specific README
        readme_content = self.create_package_readme(package_type, package_config)
        with open(self.temp_dir / "README.md", "w", encoding="utf-8") as f:
            f.write(readme_content)
        print(f"  📄 README.md (generated)")
        
        # Create package metadata
        self.create_package_json(package_type, package_config)
        print(f"  📄 package.json (generated)")
        
        # Create config template if launchers are included
        if 'python' in package_config['files'] or 'php' in package_config['files']:
            config_template = {
                "apiKey": "YOUR_CLOUDFLARE_API_KEY",
                "email": "your-email@example.com",
                "zoneId": "your-zone-id", 
                "serverPort": 8080,
                "autoOpenBrowser": True,
                "debug": False
            }
            with open(self.temp_dir / "config_template.json", "w", encoding="utf-8") as f:
                json.dump(config_template, f, indent=2)
            print(f"  📄 config_template.json (generated)")
        
        # Create packages
        zip_path = self.create_zip_package(package_name)
        tar_path = self.create_tar_package(package_name)
        
        # Get file sizes
        zip_size = zip_path.stat().st_size / 1024
        tar_size = tar_path.stat().st_size / 1024
        
        print(f"  ✅ ZIP: {zip_path.name} ({zip_size:.1f} KB)")
        print(f"  ✅ TAR: {tar_path.name} ({tar_size:.1f} KB)")
        
        # Clean up
        self.clean_temp_dir()

    def build_all_packages(self) -> None:
        """Build all package types."""
        print(f"🏗️  Building all packages for {self.tool_name} v{self.version}")
        self.create_output_dirs()
        
        total_packages = 0
        for package_type in self.package_configs:
            try:
                self.build_package(package_type)
                total_packages += 2  # ZIP and TAR
            except Exception as e:
                print(f"❌ Error building {package_type}: {e}")
                
        print(f"\n🎉 Build complete! Generated {total_packages} packages in {self.output_dir}")

    def list_packages(self) -> None:
        """List available package configurations."""
        print(f"📋 Available package types for {self.tool_name}:")
        print()
        for pkg_type, config in self.package_configs.items():
            print(f"  🔸 {pkg_type}")
            print(f"     {config['description']}")
            print(f"     Includes: {', '.join(config['files'])}")
            print()

    def show_help(self) -> None:
        """Show help message."""
        print(f"""
🛡️ Enhanced Cloudflare Security Tool Package Builder v{self.version}

USAGE:
  python package_builder_enhanced.py [command] [options]

COMMANDS:
  build [TYPE]     Build specific package type or 'all' for everything
  list            List all available package types  
  help            Show this help message

PACKAGE TYPES:
  standalone      HTML-only version (no programming language required)
  python_complete Complete Python package with launcher and installer
  php_complete    Complete PHP package with launcher and installer
  dual_language   Full package supporting both Python and PHP
  minimal         Minimal HTML-only package

EXAMPLES:
  python package_builder_enhanced.py build all
  python package_builder_enhanced.py build php_complete
  python package_builder_enhanced.py list
  python package_builder_enhanced.py help

OUTPUT:
  All packages are created in the 'packages' directory as both ZIP and TAR.GZ files.
""")

def main():
    """Main entry point."""
    builder = CloudflareToolPackageBuilder()
    
    if len(sys.argv) < 2:
        builder.show_help()
        return
        
    command = sys.argv[1].lower()
    
    if command == "help":
        builder.show_help()
    elif command == "list": 
        builder.list_packages()
    elif command == "build":
        if len(sys.argv) < 3:
            print("❌ Please specify package type or 'all'")
            print("   Use 'python package_builder_enhanced.py list' to see available types")
            return
            
        package_type = sys.argv[2].lower()
        
        if package_type == "all":
            builder.build_all_packages()
        elif package_type in builder.package_configs:
            builder.create_output_dirs()
            builder.build_package(package_type)
        else:
            print(f"❌ Unknown package type: {package_type}")
            print("   Use 'python package_builder_enhanced.py list' to see available types")
    else:
        print(f"❌ Unknown command: {command}")
        builder.show_help()

if __name__ == "__main__":
    main()