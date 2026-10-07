// Common JavaScript - Cloudflare Management System
// Shared utilities and functions across all pages

// Utility functions
const Utils = {
    // Show loading spinner
    showLoading: (element) => {
        if (element) {
            element.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
    },

    // Hide loading spinner
    hideLoading: (element, originalContent) => {
        if (element) {
            element.innerHTML = originalContent;
        }
    },

    // Show success message
    showSuccess: (message) => {
        console.log('Success:', message);
        // Could integrate with toast notifications later
    },

    // Show error message
    showError: (message) => {
        console.error('Error:', message);
        // Could integrate with toast notifications later
    },

    // Format date to Vietnamese locale
    formatDate: (dateString) => {
        return new Date(dateString).toLocaleDateString('vi-VN');
    },

    // Debounce function for search inputs
    debounce: (func, wait) => {
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
};

// Global event listeners and initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('Common JavaScript loaded');
    
    // Initialize Bootstrap tooltips if present
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if (typeof bootstrap !== 'undefined') {
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});

// Export utilities for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Utils;
}