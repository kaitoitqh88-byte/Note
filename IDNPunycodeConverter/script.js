// Copy textarea content to clipboard
function copyTextarea() {
    const textarea = document.getElementById('results-output');
    textarea.select();
    document.execCommand('copy');
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '✅ Đã sao chép!';
    btn.style.background = '#4CAF50';
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = '#2196F3';
    }, 2000);
}

// Select all text in textarea
function selectAllText() {
    const textarea = document.getElementById('results-output');
    textarea.focus();
    textarea.select();
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '✅ Đã chọn!';
    btn.style.background = '#4CAF50';
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = '#FF9800';
    }, 1500);
}

// Update output format based on radio selection
function updateOutputFormat() {
    const format = document.querySelector('input[name="output_format"]:checked').value;
    const textarea = document.getElementById('results-output');
    let output = '';
    
    if (typeof resultsData !== 'undefined') {
        resultsData.forEach((result, index) => {
            switch(format) {
                case 'converted_only':
                    output += result.converted + '\n';
                    break;
                case 'paired':
                    output += result.original + ' → ' + result.converted + '\n';
                    break;
                case 'numbered':
                    output += (index + 1) + '. ' + result.original + ' → ' + result.converted + '\n';
                    break;
            }
        });
        
        textarea.value = output.trim();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto focus on input field
    const inputField = document.getElementById('input');
    if (inputField) {
        inputField.focus();
    }
    
    // Add event listeners for format radio buttons
    const formatRadios = document.querySelectorAll('input[name="output_format"]');
    formatRadios.forEach(radio => {
        radio.addEventListener('change', updateOutputFormat);
    });
    
    // Ctrl+Enter to submit form in textarea
    if (inputField) {
        inputField.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                const submitBtn = document.querySelector('.btn');
                if (submitBtn) {
                    submitBtn.click();
                }
            }
        });
        
        // Show tooltip with keyboard shortcut
        inputField.addEventListener('focus', function() {
            if (!this.hasAttribute('data-tooltip-shown')) {
                setTimeout(() => {
                    const tooltip = document.createElement('div');
                    tooltip.style.cssText = `
                        position: absolute;
                        background: #333;
                        color: white;
                        padding: 8px 12px;
                        border-radius: 4px;
                        font-size: 0.9em;
                        top: -35px;
                        right: 10px;
                        opacity: 0.9;
                        pointer-events: none;
                        z-index: 1000;
                    `;
                    tooltip.textContent = 'Tip: Ctrl+Enter để chuyển đổi nhanh';
                    
                    this.parentNode.style.position = 'relative';
                    this.parentNode.appendChild(tooltip);
                    
                    setTimeout(() => {
                        if (tooltip.parentNode) {
                            tooltip.parentNode.removeChild(tooltip);
                        }
                    }, 3000);
                    
                    this.setAttribute('data-tooltip-shown', 'true');
                }, 1000);
            }
        });
    }
});