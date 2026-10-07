// Homepage JavaScript - Cloudflare Management System

// Load stats and recent domains when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRecentDomains();
});

async function loadStats() {
    try {
        const response = await fetch('/?action=home&api=1');
        const data = await response.json();
        
        if (data.success && data.stats) {
            // Hide loading spinners and show stats
            document.querySelectorAll('.loading-spinner').forEach(el => {
                el.style.display = 'none';
            });
            
            // Update stats
            document.getElementById('total-domains').textContent = data.stats.total_zones;
            document.getElementById('active-domains').textContent = data.stats.active_zones;
            document.getElementById('ssl-enabled').textContent = data.stats.ssl_enabled;
            document.getElementById('dns-only').textContent = data.stats.dns_only;
            
            // Show the stats numbers
            document.querySelectorAll('#total-domains, #active-domains, #ssl-enabled, #dns-only').forEach(el => {
                el.style.display = 'block';
            });
            
            // Animate numbers
            animateNumbers();
        }
    } catch (error) {
        console.error('Error loading stats:', error);
        // Hide loading spinners
        document.querySelectorAll('.loading-spinner').forEach(el => {
            el.style.display = 'none';
        });
        // Show error message
        document.querySelectorAll('#total-domains, #active-domains, #ssl-enabled, #dns-only').forEach(el => {
            el.textContent = '!';
            el.style.display = 'block';
        });
    }
}

async function loadRecentDomains() {
    try {
        const response = await fetch('/?action=home&api=1');
        const data = await response.json();
        
        if (data.success && data.recent_zones) {
            const container = document.getElementById('recent-domains-list');
            
            if (data.recent_zones.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Chưa có domains nào</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            data.recent_zones.forEach(zone => {
                const statusClass = zone.status === 'active' ? 'bg-success' : 'bg-warning';
                html += `
                    <div class="domain-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${zone.name}</h6>
                                <small class="text-muted">
                                    <i class="fas fa-calendar-alt"></i> 
                                    ${new Date(zone.created_on).toLocaleDateString('vi-VN')}
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="domain-status ${statusClass} text-white">
                                    ${zone.status}
                                </span>
                                <br>
                                <small class="text-muted">
                                    Plan: ${zone.plan.name || 'Free'}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading recent domains:', error);
        document.getElementById('recent-domains-list').innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-3"></i>
                <p class="text-muted">Không thể tải danh sách domains</p>
            </div>
        `;
    }
}

function refreshStats() {
    // Show loading spinners
    document.querySelectorAll('.loading-spinner').forEach(el => {
        el.style.display = 'block';
    });
    document.querySelectorAll('#total-domains, #active-domains, #ssl-enabled, #dns-only').forEach(el => {
        el.style.display = 'none';
    });
    
    // Reload stats
    loadStats();
    loadRecentDomains();
}

function animateNumbers() {
    // Animate the numbers counting up
    document.querySelectorAll('#total-domains, #active-domains, #ssl-enabled, #dns-only').forEach(el => {
        const target = parseInt(el.textContent);
        let current = 0;
        const increment = target / 20;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                el.textContent = target;
                clearInterval(timer);
            } else {
                el.textContent = Math.floor(current);
            }
        }, 50);
    });
}