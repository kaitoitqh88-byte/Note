// IDN Converter JavaScript - Cloudflare Management System

// Handle format changes
document.querySelectorAll('input[name="output_format"]').forEach(radio => {
    radio.addEventListener('change', function() {
        updateResultsDisplay(this.value);
    });
});

function updateResultsDisplay(format) {
    const resultItems = document.querySelectorAll('#results-display .result-item');
    
    resultItems.forEach((item, index) => {
        const spans = item.querySelectorAll('span[class^="result-text"] span');
        spans.forEach(span => span.classList.add('d-none'));
        
        const resultText = item.querySelector('.result-text');
        const copyBtn = item.querySelector('.copy-btn');
        
        switch(format) {
            case 'converted_only':
                item.querySelector('.converted').classList.remove('d-none');
                copyBtn.setAttribute('data-text', resultsData[index].converted);
                break;
            case 'paired':
                item.querySelector('.paired').classList.remove('d-none');
                copyBtn.setAttribute('data-text', resultsData[index].original + ' → ' + resultsData[index].converted);
                break;
            case 'numbered':
                item.querySelector('.numbered').classList.remove('d-none');
                copyBtn.setAttribute('data-text', (index + 1) + '. ' + resultsData[index].converted);
                break;
        }
    });
}

function copyText(button) {
    const text = button.getAttribute('data-text');
    navigator.clipboard.writeText(text).then(() => {
        const icon = button.querySelector('i');
        const originalClass = icon.className;
        icon.className = 'fas fa-check';
        button.classList.add('btn-success');
        button.classList.remove('copy-btn');
        
        setTimeout(() => {
            icon.className = originalClass;
            button.classList.remove('btn-success');
            button.classList.add('copy-btn');
        }, 1500);
    }).catch(err => {
        console.error('Failed to copy text:', err);
        alert('Không thể copy text. Vui lòng thử lại.');
    });
}

function copyAllResults() {
    const format = document.querySelector('input[name="output_format"]:checked').value;
    let allText = '';
    
    resultsData.forEach((result, index) => {
        switch(format) {
            case 'converted_only':
                allText += result.converted + '\n';
                break;
            case 'paired':
                allText += result.original + ' → ' + result.converted + '\n';
                break;
            case 'numbered':
                allText += (index + 1) + '. ' + result.converted + '\n';
                break;
        }
    });
    
    navigator.clipboard.writeText(allText.trim()).then(() => {
        const button = document.querySelector('button[onclick="copyAllResults()"]');
        const icon = button.querySelector('i');
        const originalText = button.innerHTML;
        
        button.innerHTML = '<i class="fas fa-check"></i> Đã copy!';
        button.classList.add('btn-success');
        button.classList.remove('copy-btn');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('copy-btn');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy all text:', err);
        alert('Không thể copy tất cả text. Vui lòng thử lại.');
    });
}