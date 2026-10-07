 
class SecurityManager {
    constructor() {
        this.currentSection = 'dashboard';
        this.templates = [];
        this.selectedTemplate = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadDashboard();
        this.loadTemplates();
        this.setupExpressionBuilder();
        this.setupFormValidation();
        this.setupNotifications();
    }

    // Event Listeners Setup
    setupEventListeners() {
        // Menu navigation
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const section = item.getAttribute('data-section');
                this.showSection(section);
                this.setActiveMenuItem(item);
            });
        });

        // Zone selector
        const zoneSelector = document.getElementById('zoneSelect');
        if (zoneSelector) {
            zoneSelector.addEventListener('change', () => {
                this.onZoneChange();
            });
        }

        // Form submissions
        const createForm = document.getElementById('createRuleForm');
        if (createForm) {
            createForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createRule();
            });
        }

        // Template selection
        document.addEventListener('click', (e) => {
            if (e.target.closest('.template-card')) {
                this.selectTemplate(e.target.closest('.template-card'));
            }
        });

        // Expression validation
        const expressionInput = document.getElementById('expression');
        if (expressionInput) {
            expressionInput.addEventListener('input', this.debounce(() => {
                this.validateExpression();
            }, 500));
        }

        // Bulk operations
        document.querySelectorAll('[data-bulk-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.getAttribute('data-bulk-action');
                this.handleBulkAction(action);
            });
        });

        // Modal controls
        this.setupModalControls();
    }

    // Navigation và UI
    showSection(section) {
        // Ẩn tất cả sections
        document.querySelectorAll('.section').forEach(sec => {
            sec.classList.add('hidden');
        });

        // Hiện section được chọn
        const targetSection = document.getElementById(section);
        if (targetSection) {
            targetSection.classList.remove('hidden');
            this.currentSection = section;
        }

        // Load dữ liệu cho section
        switch (section) {
            case 'dashboard':
                this.loadDashboard();
                break;
            case 'create':
                this.loadCreateForm();
                break;
            case 'templates':
                this.loadTemplates();
                break;
            case 'rules':
                this.loadRules();
                break;
            case 'test':
                this.loadTestTools();
                break;
            case 'bulk':
                this.loadBulkOperations();
                break;
        }
    }

    setActiveMenuItem(activeItem) {
        document.querySelectorAll('.menu-item').forEach(item => {
            item.classList.remove('active');
        });
        activeItem.classList.add('active');
    }

    // Dashboard
    async loadDashboard() {
        this.showLoading('dashboard-stats');
        
        try {
            const response = await this.apiCall('get_dashboard_stats');
            if (response.success) {
                this.updateDashboardStats(response.data);
            }
        } catch (error) {
            this.showNotification('Lỗi khi tải dashboard: ' + error.message, 'error');
        } finally {
            this.hideLoading('dashboard-stats');
        }
    }

    updateDashboardStats(stats) {
        const elements = {
            totalRules: document.getElementById('totalRules'),
            enabledRules: document.getElementById('enabledRules'),
            bypassedRequests: document.getElementById('bypassedRequests'),
            challengedRequests: document.getElementById('challengedRequests')
        };

        if (elements.totalRules) elements.totalRules.textContent = stats.total_rules || 0;
        if (elements.enabledRules) elements.enabledRules.textContent = stats.enabled_rules || 0;
        if (elements.bypassedRequests) elements.bypassedRequests.textContent = stats.bypassed_requests || 0;
        if (elements.challengedRequests) elements.challengedRequests.textContent = stats.challenged_requests || 0;

        // Cập nhật biểu đồ
        this.updateCharts(stats);
    }

    updateCharts(stats) {
        // Chart.js implementation cho biểu đồ dashboard
        if (typeof Chart !== 'undefined') {
            this.createRulesChart(stats);
            this.createActivityChart(stats);
        }
    }

    // Templates
    async loadTemplates() {
        this.showLoading('templates-grid');
        
        try {
            const response = await this.apiCall('get_templates');
            if (response.success) {
                this.templates = response.data;
                this.renderTemplates();
            }
        } catch (error) {
            this.showNotification('Lỗi khi tải templates: ' + error.message, 'error');
        } finally {
            this.hideLoading('templates-grid');
        }
    }

    renderTemplates() {
        const container = document.getElementById('templatesGrid');
        if (!container) return;

        container.innerHTML = this.templates.map(template => `
            <div class="template-card" data-template-id="${template.id}">
                <div class="category">${template.category}</div>
                <span class="action-badge ${template.action}">${template.action.toUpperCase()}</span>
                <h4>${template.name}</h4>
                <p class="description">${template.description}</p>
                <div class="expression">${template.expression}</div>
                <div style="margin-top: 15px;">
                    <button class="btn btn-primary btn-sm" onclick="securityManager.useTemplate('${template.id}')">
                        <i class="fas fa-plus"></i> Sử dụng
                    </button>
                </div>
            </div>
        `).join('');
    }

    selectTemplate(card) {
        // Remove selection từ tất cả cards
        document.querySelectorAll('.template-card').forEach(c => {
            c.classList.remove('selected');
        });

        // Add selection cho card được chọn
        card.classList.add('selected');
        
        const templateId = card.getAttribute('data-template-id');
        this.selectedTemplate = this.templates.find(t => t.id === templateId);
    }

    useTemplate(templateId) {
        const template = this.templates.find(t => t.id === templateId);
        if (template) {
            // Chuyển đến trang tạo rule và điền form
            this.showSection('create');
            this.populateFormFromTemplate(template);
            this.showNotification(`Đã áp dụng template: ${template.name}`, 'success');
        }
    }

    populateFormFromTemplate(template) {
        const form = document.getElementById('createRuleForm');
        if (!form) return;

        // Điền thông tin từ template
        const fields = {
            description: template.name,
            expression: template.expression,
            action: template.action,
            priority: '5'
        };

        Object.keys(fields).forEach(field => {
            const element = form.querySelector(`[name="${field}"]`);
            if (element) {
                element.value = fields[field];
            }
        });

        // Validate expression
        this.validateExpression();
    }

    // Create Rule
    async loadCreateForm() {
        // Load zone info và populate form nếu cần
        const zoneSelect = document.getElementById('zoneSelect');
        if (zoneSelect && zoneSelect.value) {
            this.updateFormForZone(zoneSelect.value);
        }
    }

    async createRule() {
        const form = document.getElementById('createRuleForm');
        if (!form) return;

        const formData = new FormData(form);
        const ruleData = {
            description: formData.get('description'),
            expression: formData.get('expression'),
            action: formData.get('action'),
            priority: parseInt(formData.get('priority')) || 5,
            enabled: formData.get('enabled') === 'on'
        };

        // Validate
        if (!this.validateRuleData(ruleData)) {
            return;
        }

        this.showLoading('create-form');
        
        try {
            const response = await this.apiCall('create_rule', ruleData);
            if (response.success) {
                this.showNotification('Tạo rule thành công!', 'success');
                form.reset();
                this.clearValidationResults();
                
                // Refresh rules list nếu đang xem
                if (this.currentSection === 'rules') {
                    this.loadRules();
                }
            } else {
                this.showNotification('Lỗi: ' + response.message, 'error');
            }
        } catch (error) {
            this.showNotification('Lỗi khi tạo rule: ' + error.message, 'error');
        } finally {
            this.hideLoading('create-form');
        }
    }

    validateRuleData(data) {
        let isValid = true;
        const errors = [];

        if (!data.description || data.description.trim().length < 3) {
            errors.push('Mô tả phải có ít nhất 3 ký tự');
            isValid = false;
        }

        if (!data.expression || data.expression.trim().length === 0) {
            errors.push('Expression không được để trống');
            isValid = false;
        }

        if (!data.action) {
            errors.push('Phải chọn action');
            isValid = false;
        }

        if (errors.length > 0) {
            this.showNotification(errors.join('<br>'), 'error');
        }

        return isValid;
    }

    // Expression Validation
    async validateExpression() {
        const expressionInput = document.getElementById('expression');
        const resultContainer = document.getElementById('validationResult');
        
        if (!expressionInput || !resultContainer) return;

        const expression = expressionInput.value.trim();
        if (expression.length === 0) {
            resultContainer.innerHTML = '';
            return;
        }

        try {
            const response = await this.apiCall('validate_expression', { expression });
            
            if (response.success) {
                resultContainer.innerHTML = `
                    <div class="validation-result valid">
                        <i class="fas fa-check"></i> Expression hợp lệ
                    </div>
                `;
            } else {
                resultContainer.innerHTML = `
                    <div class="validation-result invalid">
                        <i class="fas fa-times"></i> Lỗi: ${response.message}
                    </div>
                `;
            }
        } catch (error) {
            resultContainer.innerHTML = `
                <div class="validation-result invalid">
                    <i class="fas fa-times"></i> Lỗi validate: ${error.message}
                </div>
            `;
        }
    }

    clearValidationResults() {
        const resultContainer = document.getElementById('validationResult');
        if (resultContainer) {
            resultContainer.innerHTML = '';
        }
    }

    // Expression Builder
    setupExpressionBuilder() {
        const builder = document.getElementById('expressionBuilder');
        if (!builder) return;

        // Predefined expression parts
        const fields = ['http.host', 'http.request.uri', 'http.user_agent', 'ip.src', 'http.referer'];
        const operators = ['eq', 'ne', 'contains', 'matches', 'in'];
        const actions = ['and', 'or', 'not'];

        // Populate builder selects
        this.populateSelect('builderField', fields);
        this.populateSelect('builderOperator', operators);

        // Add expression builder handlers
        document.getElementById('addCondition')?.addEventListener('click', () => {
            this.addBuilderCondition();
        });

        document.getElementById('clearBuilder')?.addEventListener('click', () => {
            this.clearExpressionBuilder();
        });
    }

    populateSelect(selectId, options) {
        const select = document.getElementById(selectId);
        if (!select) return;

        select.innerHTML = options.map(option => 
            `<option value="${option}">${option}</option>`
        ).join('');
    }

    addBuilderCondition() {
        const field = document.getElementById('builderField')?.value;
        const operator = document.getElementById('builderOperator')?.value;
        const value = document.getElementById('builderValue')?.value;
        const logicOperator = document.getElementById('builderLogic')?.value || 'and';

        if (!field || !operator || !value) {
            this.showNotification('Vui lòng điền đầy đủ thông tin', 'warning');
            return;
        }

        const expressionInput = document.getElementById('expression');
        if (!expressionInput) return;

        let newCondition = `(${field} ${operator} "${value}")`;
        
        if (expressionInput.value.trim()) {
            newCondition = ` ${logicOperator} ${newCondition}`;
        }

        expressionInput.value += newCondition;
        this.validateExpression();

        // Clear builder fields
        document.getElementById('builderValue').value = '';
    }

    clearExpressionBuilder() {
        const expressionInput = document.getElementById('expression');
        if (expressionInput) {
            expressionInput.value = '';
            this.clearValidationResults();
        }
    }

    // Rules Management
    async loadRules() {
        this.showLoading('rules-table');
        
        try {
            const response = await this.apiCall('get_rules');
            if (response.success) {
                this.renderRulesTable(response.data);
            }
        } catch (error) {
            this.showNotification('Lỗi khi tải rules: ' + error.message, 'error');
        } finally {
            this.hideLoading('rules-table');
        }
    }

    renderRulesTable(rules) {
        const container = document.getElementById('rulesTableContainer');
        if (!container) return;

        if (rules.length === 0) {
            container.innerHTML = '<p class="text-center">Chưa có rules nào.</p>';
            return;
        }

        const tableHTML = `
            <table class="rules-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mô tả</th>
                        <th>Action</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${rules.map(rule => `
                        <tr>
                            <td>${rule.id}</td>
                            <td>${rule.description}</td>
                            <td>
                                <span class="action-badge ${rule.action}">${rule.action}</span>
                            </td>
                            <td>${rule.priority}</td>
                            <td>
                                <span class="status-badge ${rule.paused ? 'disabled' : 'enabled'}">
                                    ${rule.paused ? 'Disabled' : 'Enabled'}
                                </span>
                            </td>
                            <td>${new Date(rule.created_on).toLocaleDateString('vi-VN')}</td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="securityManager.editRule('${rule.id}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="securityManager.deleteRule('${rule.id}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="securityManager.toggleRule('${rule.id}', ${!rule.paused})">
                                    <i class="fas fa-power-off"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;

        container.innerHTML = tableHTML;
    }

    async deleteRule(ruleId) {
        if (!confirm('Bạn có chắc chắn muốn xóa rule này?')) {
            return;
        }

        try {
            const response = await this.apiCall('delete_rule', { rule_id: ruleId });
            if (response.success) {
                this.showNotification('Đã xóa rule thành công!', 'success');
                this.loadRules(); // Reload table
            } else {
                this.showNotification('Lỗi: ' + response.message, 'error');
            }
        } catch (error) {
            this.showNotification('Lỗi khi xóa rule: ' + error.message, 'error');
        }
    }

    async toggleRule(ruleId, pause) {
        try {
            const response = await this.apiCall('toggle_rule', { 
                rule_id: ruleId, 
                paused: pause 
            });
            
            if (response.success) {
                const action = pause ? 'tắt' : 'bật';
                this.showNotification(`Đã ${action} rule thành công!`, 'success');
                this.loadRules(); // Reload table
            } else {
                this.showNotification('Lỗi: ' + response.message, 'error');
            }
        } catch (error) {
            this.showNotification('Lỗi khi toggle rule: ' + error.message, 'error');
        }
    }

    // Test Tools
    loadTestTools() {
        // Load test tools UI
        console.log('Loading test tools...');
    }

    // Bulk Operations
    loadBulkOperations() {
        // Load bulk operations UI
        console.log('Loading bulk operations...');
    }

    handleBulkAction(action) {
        console.log('Bulk action:', action);
        // Implement bulk actions
    }

    // Utility Functions
    async apiCall(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        const response = await fetch('security_manager.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    showLoading(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            const loading = container.querySelector('.loading') || this.createLoadingElement();
            loading.classList.add('active');
            if (!container.querySelector('.loading')) {
                container.appendChild(loading);
            }
        }
    }

    hideLoading(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            const loading = container.querySelector('.loading');
            if (loading) {
                loading.classList.remove('active');
            }
        }
    }

    createLoadingElement() {
        const loading = document.createElement('div');
        loading.className = 'loading';
        loading.innerHTML = `
            <div class="spinner"></div>
            <p>Đang tải...</p>
        `;
        return loading;
    }

    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelectorAll('.notification');
        existing.forEach(n => n.remove());

        // Create notification
        const notification = document.createElement('div');
        notification.className = `notification alert alert-${type}`;
        notification.innerHTML = message;
        
        // Style notification
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: '9999',
            maxWidth: '400px',
            animation: 'slideIn 0.3s ease'
        });

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 5000);

        // Click to remove
        notification.addEventListener('click', () => {
            notification.remove();
        });
    }

    onZoneChange() {
        const selectedZone = document.getElementById('zoneSelect')?.value;
        if (selectedZone) {
            // Reload current section data for new zone
            this.showSection(this.currentSection);
            this.showNotification(`Đã chuyển sang zone: ${selectedZone}`, 'info');
        }
    }

    setupFormValidation() {
        // Real-time validation cho forms
        document.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('blur', () => {
                this.validateField(field);
            });
        });
    }

    validateField(field) {
        const value = field.value.trim();
        const fieldName = field.getAttribute('name');
        let isValid = true;
        let message = '';

        // Validation rules
        switch (fieldName) {
            case 'description':
                if (value.length < 3) {
                    isValid = false;
                    message = 'Mô tả phải có ít nhất 3 ký tự';
                }
                break;
            case 'expression':
                if (value.length === 0) {
                    isValid = false;
                    message = 'Expression không được để trống';
                }
                break;
            case 'priority':
                const priority = parseInt(value);
                if (isNaN(priority) || priority < 1 || priority > 10) {
                    isValid = false;
                    message = 'Priority phải từ 1-10';
                }
                break;
        }

        // Update field UI
        if (isValid) {
            field.style.borderColor = '#22c55e';
        } else {
            field.style.borderColor = '#ef4444';
            if (message) {
                this.showNotification(message, 'warning');
            }
        }

        return isValid;
    }

    setupModalControls() {
        // Modal controls for rule editing, bulk operations, etc.
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModals();
            }
        });
    }

    closeModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }

    setupNotifications() {
        // CSS for notifications animation
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }

    // Utility: Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Auto-initialize khi DOM loaded
document.addEventListener('DOMContentLoaded', () => {
    window.securityManager = new SecurityManager();
});

// Export cho sử dụng global
window.SecurityManager = SecurityManager;