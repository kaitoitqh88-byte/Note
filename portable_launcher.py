#!/usr/bin/env python3
"""
Cloudflare Security Tool - Portable Launcher
Cross-platform launcher that works on Windows, macOS, and Linux
"""

import os
import sys
import json
import socket
import webbrowser
import threading
import time
from pathlib import Path
import http.server
import socketserver

class CloudflareSecurityToolLauncher:
    def __init__(self):
        self.script_dir = Path(__file__).parent
        self.config_file = self.script_dir / 'config.json'
        self.tool_file = self.script_dir / 'cloudflare_security_tool.html'
        self.port = None
        self.server = None
        
    def print_header(self):
        print("=" * 50)
        print("🛡️  CLOUDFLARE SECURITY TOOL")
        print("    Portable Cross-Platform Launcher")
        print("=" * 50)
        print()
        
    def print_status(self, message, level="INFO"):
        icons = {
            "INFO": "ℹ️",
            "SUCCESS": "✅", 
            "WARNING": "⚠️",
            "ERROR": "❌"
        }
        print(f"{icons.get(level, 'ℹ️')} {message}")
        
    def find_free_port(self, start_port=8080):
        """Find a free port starting from start_port"""
        for port in range(start_port, start_port + 100):
            try:
                with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
                    s.bind(('', port))
                    return port
            except OSError:
                continue
        raise Exception("No free ports found")
        
    def check_files(self):
        """Check if required files exist"""
        if not self.tool_file.exists():
            self.print_status(f"Tool file not found: {self.tool_file}", "ERROR")
            return False
            
        if not self.config_file.exists():
            self.print_status("Config file not found, creating template...", "WARNING")
            self.create_config_template()
            
        return True
        
    def create_config_template(self):
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
        
        try:
            with open(self.config_file, 'w', encoding='utf-8') as f:
                json.dump(config, f, indent=4, ensure_ascii=False)
            self.print_status(f"Created config template: {self.config_file}", "SUCCESS")
        except Exception as e:
            self.print_status(f"Error creating config: {e}", "ERROR")
            
    def load_config(self):
        """Load configuration"""
        try:
            with open(self.config_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception as e:
            self.print_status(f"Error loading config: {e}", "WARNING")
            return {}
            
    def start_server(self):
        """Start HTTP server"""
        try:
            config = self.load_config()
            preferred_port = config.get('tool', {}).get('port', 8080)
            
            self.port = self.find_free_port(preferred_port)
            
            # Change to script directory
            os.chdir(self.script_dir)
            
            # Create server
            handler = http.server.SimpleHTTPRequestHandler
            self.server = socketserver.TCPServer(("", self.port), handler)
            
            self.print_status(f"Starting server on port {self.port}...", "INFO")
            self.print_status(f"Local URL: http://localhost:{self.port}", "INFO")
            
            # Start server in background thread
            server_thread = threading.Thread(target=self.server.serve_forever)
            server_thread.daemon = True
            server_thread.start()
            
            return True
            
        except Exception as e:
            self.print_status(f"Error starting server: {e}", "ERROR")
            return False
            
    def open_browser(self):
        """Open tool in browser"""
        try:
            url = f"http://localhost:{self.port}/cloudflare_security_tool.html"
            
            self.print_status("Opening browser...", "INFO")
            time.sleep(1)  # Give server time to start
            
            webbrowser.open(url)
            return True
            
        except Exception as e:
            self.print_status(f"Error opening browser: {e}", "WARNING")
            self.print_status(f"Please manually open: http://localhost:{self.port}/cloudflare_security_tool.html", "INFO")
            return False
            
    def show_menu(self):
        """Show interactive menu"""
        while True:
            print("\n" + "=" * 30)
            print("🛡️  TOOL OPTIONS")
            print("=" * 30)
            print("1. 🚀 Start Server & Open Browser")
            print("2. 🌐 Open in Browser (if server running)")
            print("3. ⚙️  Edit Configuration")
            print("4. 📁 Open Tool Directory") 
            print("5. ℹ️  Show Tool Info")
            print("6. 🛑 Stop Server & Exit")
            print()
            
            choice = input("Choose option (1-6): ").strip()
            
            if choice == '1':
                self.start_tool()
            elif choice == '2':
                if self.port:
                    self.open_browser()
                else:
                    self.print_status("Server not running. Choose option 1 first.", "WARNING")
            elif choice == '3':
                self.edit_config()
            elif choice == '4':
                self.open_directory()
            elif choice == '5':
                self.show_info()
            elif choice == '6':
                self.stop_and_exit()
                break
            else:
                self.print_status("Invalid option. Please choose 1-6.", "WARNING")
                
    def start_tool(self):
        """Start the tool"""
        if self.check_files():
            if self.start_server():
                self.open_browser()
                self.print_status("Tool started successfully!", "SUCCESS")
                self.print_status("Press Ctrl+C to stop the server", "INFO")
            else:
                self.print_status("Failed to start server", "ERROR")
        else:
            self.print_status("Cannot start tool - missing files", "ERROR")
            
    def edit_config(self):
        """Edit configuration"""
        self.print_status(f"Configuration file: {self.config_file}", "INFO")
        
        if sys.platform.startswith('win'):
            os.system(f'notepad "{self.config_file}"')
        elif sys.platform.startswith('darwin'):
            os.system(f'open -a TextEdit "{self.config_file}"')
        else:  # Linux
            editors = ['nano', 'vim', 'gedit', 'kate']
            for editor in editors:
                if os.system(f'which {editor} >/dev/null 2>&1') == 0:
                    os.system(f'{editor} "{self.config_file}"')
                    break
            else:
                self.print_status("No text editor found. Please edit manually:", "WARNING")
                print(f"  {self.config_file}")
                
    def open_directory(self):
        """Open tool directory"""
        if sys.platform.startswith('win'):
            os.system(f'explorer "{self.script_dir}"')
        elif sys.platform.startswith('darwin'):
            os.system(f'open "{self.script_dir}"')
        else:  # Linux
            os.system(f'xdg-open "{self.script_dir}"')
            
    def show_info(self):
        """Show tool information"""
        print("\n📋 TOOL INFORMATION")
        print("=" * 30)
        print(f"📁 Directory: {self.script_dir}")
        print(f"🌐 Tool File: {self.tool_file}")
        print(f"⚙️  Config File: {self.config_file}")
        print(f"🖥️  Platform: {sys.platform}")
        print(f"🐍 Python: {sys.version}")
        
        if self.port:
            print(f"🚀 Server: Running on port {self.port}")
            print(f"🔗 URL: http://localhost:{self.port}/cloudflare_security_tool.html")
        else:
            print("🛑 Server: Not running")
            
        # Show config status
        config = self.load_config()
        cf_email = config.get('cloudflare', {}).get('email', '')
        if cf_email:
            print(f"📧 Cloudflare Email: {cf_email}")
            print("✅ Configuration appears to be set up")
        else:
            print("⚠️  Cloudflare configuration not set up")
            
    def stop_and_exit(self):
        """Stop server and exit"""
        if self.server:
            self.print_status("Stopping server...", "INFO")
            self.server.shutdown()
            self.server.server_close()
            
        self.print_status("Goodbye! 👋", "SUCCESS")
        
    def run(self):
        """Run the launcher"""
        try:
            self.print_header()
            
            # Check if tool should start directly
            if len(sys.argv) > 1 and sys.argv[1] == '--start':
                self.start_tool()
                try:
                    while True:
                        time.sleep(1)
                except KeyboardInterrupt:
                    self.stop_and_exit()
            else:
                # Show interactive menu
                self.show_menu()
                
        except KeyboardInterrupt:
            print("\n")
            self.stop_and_exit()
        except Exception as e:
            self.print_status(f"Unexpected error: {e}", "ERROR")
            sys.exit(1)

def main():
    """Main entry point"""
    launcher = CloudflareSecurityToolLauncher()
    launcher.run()

if __name__ == "__main__":
    main()