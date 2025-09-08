// Text Labeling System - Main JavaScript File

// Global variables
let labelingSystem;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeSystem();
});

// Main initialization function
function initializeSystem() {
    // Initialize labeling system if on labeling page
    if (document.getElementById('document-id')) {
        labelingSystem = new LabelingSystem();
    }
    
    // Initialize other components
    initializeTooltips();
    initializeKeyboardShortcuts();
    initializeNotifications();
    initializeAutoSave();
    
    // Hide page loader
    hidePageLoader();
}

// Text Labeling System Class
class LabelingSystem {
    constructor() {
        this.selectedSentences = [];
        this.selectedTextStyle = null;
        this.currentStep = 1;
        this.autoSaveInterval = null;
        this.hasUnsavedChanges = false;
        
        this.init();
    }
    
    init() {
        this.setupSentenceSelection();
        this.setupTextStyleSelection();
        this.setupStepNavigation();
        this.setupAutoSave();
        this.setupKeyboardShortcuts();
        this.loadExistingData();
    }
    
    setupSentenceSelection() {
        const sentences = document.querySelectorAll('.sentence-selectable');
        sentences.forEach((sentence, index) => {
            sentence.addEventListener('click', () => {
                this.toggleSentenceSelection(sentence, index);
            });
            
            // Add hover effects
            sentence.addEventListener('mouseenter', () => {
                if (!sentence.classList.contains('sentence-selected')) {
                    sentence.style.transform = 'translateX(3px)';
                }
            });
            
            sentence.addEventListener('mouseleave', () => {
                if (!sentence.classList.contains('sentence-selected')) {
                    sentence.style.transform = 'translateX(0)';
                }
            });
        });
    }
    
    toggleSentenceSelection(sentence, index) {
        if (sentence.classList.contains('sentence-selected')) {
            sentence.classList.remove('sentence-selected');
            sentence.style.transform = 'translateX(0)';
            this.selectedSentences = this.selectedSentences.filter(s => s !== index);
            
            // Add animation
            sentence.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => {
                sentence.style.animation = '';
            }, 300);
        } else {
            sentence.classList.add('sentence-selected');
            this.selectedSentences.push(index);
            
            // Add animation
            sentence.style.animation = 'bounceIn 0.5s ease-out';
            setTimeout(() => {
                sentence.style.animation = '';
            }, 500);
        }
        
        this.updateSelectedSentencesDisplay();
        this.markUnsavedChanges();
    }
    
    updateSelectedSentencesDisplay() {
        const count = document.getElementById('selected-count');
        if (count) {
            count.textContent = this.selectedSentences.length;
            
            // Add pulse animation
            count.classList.add('text-primary');
            count.style.animation = 'pulse 0.5s ease-out';
            setTimeout(() => {
                count.style.animation = '';
            }, 500);
        }
        
        // Update progress indicator
        const step1 = document.getElementById('step-indicator-1');
        if (step1 && this.selectedSentences.length > 0) {
            step1.classList.add('completed');
        }
    }
    
    setupTextStyleSelection() {
        const styleOptions = document.querySelectorAll('.text-style-option');
        styleOptions.forEach(option => {
            option.addEventListener('click', () => {
                this.selectTextStyle(option);
            });
            
            // Add hover effects
            option.addEventListener('mouseenter', () => {
                if (!option.classList.contains('selected')) {
                    option.style.transform = 'translateY(-2px)';
                }
            });
            
            option.addEventListener('mouseleave', () => {
                if (!option.classList.contains('selected')) {
                    option.style.transform = 'translateY(0)';
                }
            });
        });
    }
    
    selectTextStyle(option) {
        // Remove selection from all options
        document.querySelectorAll('.text-style-option').forEach(opt => {
            opt.classList.remove('selected');
            opt.style.transform = 'translateY(0)';
        });
        
        // Add selection to clicked option
        option.classList.add('selected');
        option.style.transform = 'scale(1.02)';
        this.selectedTextStyle = option.dataset.styleId;
        
        // Update progress indicator
        const step2 = document.getElementById('step-indicator-2');
        if (step2) {
            step2.classList.add('completed');
        }
        
        this.markUnsavedChanges();
        this.showToast('Đã chọn phong cách văn bản', 'success');
    }
    
    setupStepNavigation() {
        const nextBtns = document.querySelectorAll('.btn-next-step');
        const prevBtns = document.querySelectorAll('.btn-prev-step');
        
        nextBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                this.nextStep();
            });
        });
        
        prevBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                this.prevStep();
            });
        });
    }
    
    nextStep() {
        if (this.validateCurrentStep()) {
            this.currentStep++;
            this.showStep(this.currentStep);
            this.updateStepIndicator();
            this.autoSave();
        }
    }
    
    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
            this.updateStepIndicator();
        }
    }
    
    showStep(step) {
        // Hide all steps with animation
        document.querySelectorAll('.labeling-step').forEach(stepDiv => {
            stepDiv.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                stepDiv.style.display = 'none';
                stepDiv.style.animation = '';
            }, 300);
        });
        
        // Show current step with animation
        setTimeout(() => {
            const currentStepDiv = document.getElementById(`step-${step}`);
            if (currentStepDiv) {
                currentStepDiv.style.display = 'block';
                currentStepDiv.style.animation = 'slideInLeft 0.3s ease-out';
                setTimeout(() => {
                    currentStepDiv.style.animation = '';
                }, 300);
            }
        }, 300);
    }
    
    updateStepIndicator() {
        document.querySelectorAll('.step').forEach((step, index) => {
            step.classList.remove('active');
            if (index + 1 === this.currentStep) {
                step.classList.add('active');
                step.style.animation = 'bounceIn 0.5s ease-out';
                setTimeout(() => {
                    step.style.animation = '';
                }, 500);
            }
        });
    }
    
    validateCurrentStep() {
        switch (this.currentStep) {
            case 1:
                if (this.selectedSentences.length === 0) {
                    this.showAlert('Vui lòng chọn ít nhất một câu quan trọng!', 'warning');
                    return false;
                }
                break;
            case 2:
                if (!this.selectedTextStyle) {
                    this.showAlert('Vui lòng chọn phong cách văn bản!', 'warning');
                    return false;
                }
                break;
            case 3:
                const summary = document.getElementById('edited-summary');
                if (summary && summary.value.trim().length === 0) {
                    this.showAlert('Vui lòng chỉnh sửa bản tóm tắt!', 'warning');
                    return false;
                }
                break;
        }
        return true;
    }
    
    setupAutoSave() {
        // Auto save every 30 seconds
        this.autoSaveInterval = setInterval(() => {
            if (this.hasUnsavedChanges) {
                this.autoSave();
            }
        }, 30000);
        
        // Save on summary text change
        const summaryTextarea = document.getElementById('edited-summary');
        if (summaryTextarea) {
            summaryTextarea.addEventListener('input', () => {
                this.markUnsavedChanges();
                
                // Debounced auto save
                clearTimeout(this.summaryTimeout);
                this.summaryTimeout = setTimeout(() => {
                    this.autoSave();
                }, 2000);
            });
        }
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                this.autoSave();
            }
            
            // Tab to next step
            if (e.key === 'Tab' && !e.shiftKey && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                this.nextStep();
            }
            
            // Shift+Tab to previous step
            if (e.key === 'Tab' && e.shiftKey) {
                e.preventDefault();
                this.prevStep();
            }
            
            // Enter to finalize (only on step 3)
            if (e.key === 'Enter' && e.ctrlKey && this.currentStep === 3) {
                this.finalizeLabelingb();
            }
        });
    }
    
    loadExistingData() {
        // Load existing selected sentences
        const existingSentences = document.querySelectorAll('.sentence-selected');
        existingSentences.forEach((sentence, index) => {
            const sentenceIndex = parseInt(sentence.dataset.index);
            if (!isNaN(sentenceIndex)) {
                this.selectedSentences.push(sentenceIndex);
            }
        });
        
        // Load existing text style
        const selectedStyle = document.querySelector('.text-style-option.selected');
        if (selectedStyle) {
            this.selectedTextStyle = selectedStyle.dataset.styleId;
        }
        
        this.updateSelectedSentencesDisplay();
    }
    
    markUnsavedChanges() {
        this.hasUnsavedChanges = true;
        
        // Show unsaved indicator
        const indicator = document.getElementById('unsaved-indicator');
        if (indicator) {
            indicator.style.display = 'inline';
        }
    }
    
    markSaved() {
        this.hasUnsavedChanges = false;
        
        // Hide unsaved indicator
        const indicator = document.getElementById('unsaved-indicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
    
    autoSave() {
        const documentId = document.getElementById('document-id')?.value;
        if (!documentId) return;
        
        const data = {
            document_id: documentId,
            important_sentences: this.selectedSentences,
            text_style_id: this.selectedTextStyle,
            edited_summary: document.getElementById('edited-summary')?.value || '',
            action: 'auto_save'
        };
        
        // Show saving indicator
        this.showSavingIndicator();
        
        fetch('save_labeling.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            this.hideSavingIndicator();
            if (data.success) {
                this.markSaved();
                this.showToast('Đã lưu tự động', 'success', 2000);
            } else {
                this.showToast('Lỗi khi lưu: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            this.hideSavingIndicator();
            console.error('Auto save error:', error);
            this.showToast('Có lỗi xảy ra khi lưu tự động', 'warning');
        });
    }
    
    finalizeLabelingb() {
        if (!this.validateCurrentStep()) return;
        
        // Show confirmation
        if (!confirm('Bạn có chắc chắn muốn hoàn thành gán nhãn? Sau khi hoàn thành, bạn không thể chỉnh sửa nữa.')) {
            return;
        }
        
        const documentId = document.getElementById('document-id')?.value;
        const data = {
            document_id: documentId,
            important_sentences: this.selectedSentences,
            text_style_id: this.selectedTextStyle,
            edited_summary: document.getElementById('edited-summary')?.value || '',
            action: 'finalize'
        };
        
        // Disable finalize button
        const finalizeBtn = event.target;
        finalizeBtn.disabled = true;
        finalizeBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang hoàn thành...';
        
        fetch('save_labeling.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('🎉 Gán nhãn hoàn thành thành công!', 'success');
                
                // Show success animation
                document.body.style.animation = 'fadeOut 1s ease-in';
                
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 2000);
            } else {
                finalizeBtn.disabled = false;
                finalizeBtn.innerHTML = '<i class="fas fa-check me-2"></i>Hoàn thành gán nhãn';
                this.showAlert('Có lỗi xảy ra: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            finalizeBtn.disabled = false;
            finalizeBtn.innerHTML = '<i class="fas fa-check me-2"></i>Hoàn thành gán nhãn';
            this.showAlert('Có lỗi xảy ra khi lưu dữ liệu!', 'danger');
        });
    }
    
    showSavingIndicator() {
        const indicator = document.getElementById('saving-indicator') || this.createSavingIndicator();
        indicator.style.display = 'inline-flex';
    }
    
    hideSavingIndicator() {
        const indicator = document.getElementById('saving-indicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
    
    createSavingIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'saving-indicator';
        indicator.className = 'position-fixed top-0 end-0 m-3 bg-primary text-white px-3 py-2 rounded';
        indicator.style.zIndex = '1060';
        indicator.style.display = 'none';
        indicator.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang lưu...';
        document.body.appendChild(indicator);
        return indicator;
    }
    
    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.style.animation = 'slideInDown 0.3s ease-out';
        alertDiv.innerHTML = `
            <i class="fas fa-${this.getAlertIcon(type)} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.main-content') || document.body;
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.style.animation = 'slideOutUp 0.3s ease-in';
                setTimeout(() => {
                    alertDiv.remove();
                }, 300);
            }
        }, 5000);
    }
    
    getAlertIcon(type) {
        const icons = {
            'success': 'check-circle',
            'danger': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    showToast(message, type = 'info', duration = 4000) {
        const toastContainer = document.querySelector('.toast-container') || this.createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${this.getAlertIcon(type)} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: duration
        });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
    
    createToastContainer() {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1080';
        document.body.appendChild(container);
        return container;
    }
    
    // Cleanup when leaving page
    destroy() {
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval);
        }
        if (this.summaryTimeout) {
            clearTimeout(this.summaryTimeout);
        }
    }
}

// Utility Functions
function showPageLoader() {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        loader.style.display = 'flex';
    }
}

function hidePageLoader() {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        setTimeout(() => {
            loader.style.display = 'none';
        }, 500);
    }
}

function initializeTooltips() {
    // Initialize Bootstrap tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
}

function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Global shortcuts
        if (e.ctrlKey && e.key === 'h') {
            e.preventDefault();
            const helpModal = new bootstrap.Modal(document.getElementById('helpModal'));
            helpModal.show();
        }
    });
}

function initializeNotifications() {
    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

function initializeAutoSave() {
    // Warn before leaving if there are unsaved changes
    window.addEventListener('beforeunload', (e) => {
        if (labelingSystem && labelingSystem.hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc muốn rời khỏi trang?';
        }
    });
}

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

function confirmDelete(message = 'Bạn có chắc chắn muốn xóa?') {
    return confirm(message);
}

function handleFileUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        // Validate file size
        if (file.size > maxSize) {
            showToast('File quá lớn! Vui lòng chọn file nhỏ hơn 10MB.', 'danger');
            input.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['text/plain', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowedTypes.includes(file.type) && !file.name.endsWith('.txt') && !file.name.endsWith('.docx')) {
            showToast('File không được hỗ trợ! Chỉ chấp nhận file .txt và .docx', 'danger');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.getElementById('file-preview');
            if (preview) {
                preview.innerHTML = `
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-file-text me-3 fs-4"></i>
                        <div>
                            <div class="fw-bold">${file.name}</div>
                            <small class="text-muted">${formatFileSize(file.size)} • ${file.type || 'Unknown type'}</small>
                        </div>
                    </div>
                `;
            }
        };
        
        reader.onerror = function() {
            showToast('Có lỗi khi đọc file!', 'danger');
        };
        
        reader.readAsText(file);
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            
            searchTimeout = setTimeout(() => {
                const query = this.value.trim();
                if (query.length >= 2) {
                    performSearch(query);
                } else if (query.length === 0) {
                    clearSearchResults();
                }
            }, 300);
        });
    }
}

function performSearch(query) {
    showPageLoader();
    
    fetch(`search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            hidePageLoader();
            displaySearchResults(data.results);
        })
        .catch(error => {
            hidePageLoader();
            console.error('Search error:', error);
            showToast('Có lỗi khi tìm kiếm', 'danger');
        });
}

function displaySearchResults(results) {
    const container = document.getElementById('searchResults');
    if (!container) return;
    
    if (results.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-search fs-1 text-muted mb-3"></i>
                <p class="text-muted">Không tìm thấy kết quả nào</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = results.map(result => `
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title">${highlightText(result.title, query)}</h6>
                <p class="card-text">${highlightText(result.excerpt, query)}</p>
                <small class="text-muted">
                    <i class="fas fa-calendar me-1"></i>${formatDate(result.date)}
                </small>
            </div>
        </div>
    `).join('');
}

function highlightText(text, query) {
    if (!query) return text;
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

function clearSearchResults() {
    const container = document.getElementById('searchResults');
    if (container) {
        container.innerHTML = '';
    }
}

// Date formatting
function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) return 'Hôm qua';
    if (diffDays < 7) return `${diffDays} ngày trước`;
    if (diffDays < 30) return `${Math.ceil(diffDays / 7)} tuần trước`;
    
    return date.toLocaleDateString('vi-VN');
}

// Statistics and Charts
function renderStatisticsChart(data) {
    const ctx = document.getElementById('statisticsChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Hoàn thành', 'Đang thực hiện', 'Chờ xử lý'],
            datasets: [{
                data: [data.completed, data.in_progress, data.pending],
                backgroundColor: ['#28a745', '#ffc107', '#6c757d'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Progress tracking
function updateProgress(current, total) {
    const progressBar = document.querySelector('.progress-bar');
    if (progressBar) {
        const percentage = Math.round((current / total) * 100);
        progressBar.style.width = `${percentage}%`;
        progressBar.setAttribute('aria-valuenow', percentage);
        progressBar.textContent = `${percentage}%`;
    }
}

// Table utilities
function sortTable(columnIndex, table) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        
        // Check if numeric
        if (!isNaN(aText) && !isNaN(bText)) {
            return parseFloat(aText) - parseFloat(bText);
        }
        
        return aText.localeCompare(bText, 'vi');
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Export functionality
function exportData(format) {
    showPageLoader();
    
    fetch(`export.php?format=${format}`)
        .then(response => response.blob())
        .then(blob => {
            hidePageLoader();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `labeling_data.${format}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showToast(`Đã xuất dữ liệu định dạng ${format.toUpperCase()}`, 'success');
        })
        .catch(error => {
            hidePageLoader();
            console.error('Export error:', error);
            showToast('Có lỗi khi xuất dữ liệu', 'danger');
        });
}

// Form validation
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        const value = field.value.trim();
        
        if (!value) {
            showFieldError(field, 'Trường này là bắt buộc');
            isValid = false;
        } else {
            clearFieldError(field);
            
            // Additional validation based on field type
            if (field.type === 'email' && !isValidEmail(value)) {
                showFieldError(field, 'Email không hợp lệ');
                isValid = false;
            }
            
            if (field.name === 'password' && value.length < 6) {
                showFieldError(field, 'Mật khẩu phải có ít nhất 6 ký tự');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    
    field.classList.add('is-invalid');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.classList.remove('is-invalid');
    
    const errorDiv = field.parentNode.querySelector('.invalid-feedback');
    if (errorDiv) {
        errorDiv.remove();
    }
}

function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// Modal utilities
function showModal(modalId, data = {}) {
    const modal = document.getElementById(modalId);
    if (modal) {
        // Populate modal with data
        Object.keys(data).forEach(key => {
            const element = modal.querySelector(`[data-field="${key}"]`);
            if (element) {
                if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                    element.value = data[key];
                } else {
                    element.textContent = data[key];
                }
            }
        });
        
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
}

// Notification system
function showNotification(title, message, type = 'info') {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, {
            body: message,
            icon: '/assets/favicon.ico'
        });
    }
    
    // Fallback to toast
    showToast(`${title}: ${message}`, type);
}

// Dark mode toggle
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
    
    showToast(`Đã ${isDark ? 'bật' : 'tắt'} chế độ tối`, 'info');
}

// Initialize dark mode from localStorage
function initializeDarkMode() {
    const isDark = localStorage.getItem('darkMode') === 'true';
    if (isDark) {
        document.body.classList.add('dark-mode');
    }
}

// Clipboard utilities
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Đã sao chép vào clipboard', 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Đã sao chép vào clipboard', 'success');
    });
}

// Auto-resize textareas
function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

// Initialize auto-resize for all textareas
function initializeAutoResize() {
    const textareas = document.querySelectorAll('textarea[data-auto-resize]');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', () => autoResizeTextarea(textarea));
        // Initial resize
        autoResizeTextarea(textarea);
    });
}

// Performance monitoring
function measurePerformance(name, fn) {
    const start = performance.now();
    const result = fn();
    const end = performance.now();
    console.log(`${name} took ${end - start} milliseconds`);
    return result;
}

// Error handling
window.addEventListener('error', (e) => {
    console.error('Global error:', e.error);
    showToast('Đã xảy ra lỗi không mong muốn', 'danger');
});

window.addEventListener('unhandledrejection', (e) => {
    console.error('Unhandled promise rejection:', e.reason);
    showToast('Đã xảy ra lỗi khi xử lý dữ liệu', 'danger');
});

// Global toast function for external use
function showToast(message, type = 'info', duration = 4000) {
    if (labelingSystem && labelingSystem.showToast) {
        labelingSystem.showToast(message, type, duration);
    } else {
        // Fallback implementation
        console.log(`Toast: ${message} (${type})`);
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (labelingSystem && labelingSystem.destroy) {
        labelingSystem.destroy();
    }
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInLeft {
        from { opacity: 0; transform: translateX(-30px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(30px); }
    }
    
    @keyframes slideInDown {
        from { opacity: 0; transform: translateY(-30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes slideOutUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-30px); }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
`;
document.head.appendChild(style);

// Export for global access
window.LabelingSystem = LabelingSystem;
window.showToast = showToast;
window.showPageLoader = showPageLoader;
window.hidePageLoader = hidePageLoader;