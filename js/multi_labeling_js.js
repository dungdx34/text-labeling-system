/**
 * Multi-Document Labeling Interface JavaScript
 */

class MultiLabeling {
    constructor() {
        this.taskData = window.TASK_DATA || {};
        this.selectedSentences = {};
        this.autoSaveInterval = null;
        this.lastSaveData = null;
        this.isSubmitting = false;
        
        this.initializeSelectedSentences();
        this.setupEventListeners();
        this.updateProgress();
        this.startAutoSave();
    }
    
    initializeSelectedSentences() {
        // Initialize selected sentences from existing labeling data
        if (this.taskData.existing_labeling) {
            this.selectedSentences = { ...this.taskData.existing_labeling };
        } else {
            // Initialize empty structure
            this.taskData.documents.forEach(doc => {
                this.selectedSentences[doc.id] = [];
            });
        }
        
        // Apply existing selections to checkboxes
        this.applyExistingSelections();
    }
    
    applyExistingSelections() {
        Object.keys(this.selectedSentences).forEach(docId => {
            const selectedIds = this.selectedSentences[docId] || [];
            selectedIds.forEach(sentenceId => {
                const checkbox = document.getElementById(`sentence-${docId}-${sentenceId}`);
                if (checkbox) {
                    checkbox.checked = true;
                    this.updateSentenceAppearance(docId, sentenceId, true);
                }
            });
        });
    }
    
    setupEventListeners() {
        // Sentence checkbox listeners
        document.querySelectorAll('.sentence-check').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const docId = parseInt(e.target.dataset.documentId);
                const sentenceId = parseInt(e.target.dataset.sentenceId);
                this.toggleSentence(docId, sentenceId, e.target.checked);
            });
        });
        
        // Summary textarea listener
        const summaryTextarea = document.getElementById('edited-summary');
        if (summaryTextarea) {
            summaryTextarea.addEventListener('input', () => {
                this.updateSummaryStats();
            });
            
            // Initialize summary stats
            this.updateSummaryStats();
        }
        
        // Tab change listener
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                this.updateTabCounts();
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 's') {
                    e.preventDefault();
                    this.saveProgress();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    this.submitLabeling();
                }
            }
        });
    }
    
    toggleSentence(docId, sentenceId, isSelected) {
        if (!this.selectedSentences[docId]) {
            this.selectedSentences[docId] = [];
        }
        
        if (isSelected) {
            if (!this.selectedSentences[docId].includes(sentenceId)) {
                this.selectedSentences[docId].push(sentenceId);
            }
        } else {
            this.selectedSentences[docId] = this.selectedSentences[docId].filter(id => id !== sentenceId);
        }
        
        this.updateSentenceAppearance(docId, sentenceId, isSelected);
        this.updateProgress();
        this.updateSelectedPreview();
        this.updateTabCounts();
    }
    
    updateSentenceAppearance(docId, sentenceId, isSelected) {
        const sentenceItem = document.querySelector(
            `.sentence-item[data-document-id="${docId}"][data-sentence-id="${sentenceId}"]`
        );
        
        if (sentenceItem) {
            if (isSelected) {
                sentenceItem.classList.add('selected');
            } else {
                sentenceItem.classList.remove('selected');
            }
        }
    }
    
    updateProgress() {
        let totalSelected = 0;
        let totalSentences = 0;
        
        // Count total sentences and selected sentences
        this.taskData.documents.forEach(doc => {
            totalSentences += doc.sentences.length;
            totalSelected += this.selectedSentences[doc.id] ? this.selectedSentences[doc.id].length : 0;
        });
        
        const percentage = totalSentences > 0 ? Math.round((totalSelected / totalSentences) * 100) : 0;
        
        // Update progress circle
        const progressCircle = document.querySelector('.progress-circle');
        if (progressCircle) {
            progressCircle.style.background = `conic-gradient(#28a745 ${percentage * 3.6}deg, #e9ecef ${percentage * 3.6}deg)`;
        }
        
        // Update progress text
        const progressText = document.getElementById('progress-percentage');
        if (progressText) {
            progressText.textContent = `${percentage}%`;
        }
        
        // Update counters
        const selectedCounter = document.getElementById('selected-sentences');
        if (selectedCounter) {
            selectedCounter.textContent = totalSelected;
        }
        
        const totalCounter = document.getElementById('total-sentences');
        if (totalCounter) {
            totalCounter.textContent = totalSentences;
        }
    }
    
    updateTabCounts() {
        Object.keys(this.selectedSentences).forEach(docId => {
            const count = this.selectedSentences[docId] ? this.selectedSentences[docId].length : 0;
            const badge = document.getElementById(`doc-${docId}-count`);
            if (badge) {
                badge.textContent = count;
                badge.className = `badge ms-2 ${count > 0 ? 'bg-success' : 'bg-secondary'}`;
            }
        });
    }
    
    updateSelectedPreview() {
        const previewContainer = document.getElementById('selected-sentences-preview');
        if (!previewContainer) return;
        
        let previewHtml = '';
        let totalSelected = 0;
        
        this.taskData.documents.forEach(doc => {
            const selectedIds = this.selectedSentences[doc.id] || [];
            if (selectedIds.length > 0) {
                selectedIds.forEach(sentenceId => {
                    const sentence = doc.sentences.find(s => s.id === sentenceId);
                    if (sentence) {
                        previewHtml += `
                            <div class="selected-sentence-preview fade-in">
                                <span class="document-label">${doc.title}</span>
                                ${sentence.text}
                            </div>
                        `;
                        totalSelected++;
                    }
                });
            }
        });
        
        if (totalSelected === 0) {
            previewHtml = '<p class="text-muted text-center">Chưa có câu nào được chọn</p>';
        }
        
        previewContainer.innerHTML = previewHtml;
    }
    
    updateSummaryStats() {
        const textarea = document.getElementById('edited-summary');
        if (!textarea) return;
        
        const text = textarea.value;
        const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
        const charCount = text.length;
        
        const wordCounter = document.getElementById('summary-words');
        if (wordCounter) {
            wordCounter.textContent = wordCount;
        }
        
        const charCounter = document.getElementById('summary-chars');
        if (charCounter) {
            charCounter.textContent = charCount;
        }
    }
    
    selectAllSentences(docId) {
        const doc = this.taskData.documents.find(d => d.id === docId);
        if (!doc) return;
        
        doc.sentences.forEach(sentence => {
            const checkbox = document.getElementById(`sentence-${docId}-${sentence.id}`);
            if (checkbox && !checkbox.checked) {
                checkbox.checked = true;
                this.toggleSentence(docId, sentence.id, true);
            }
        });
        
        this.showNotification('Đã chọn tất cả câu trong văn bản này', 'success');
    }
    
    deselectAllSentences(docId) {
        const doc = this.taskData.documents.find(d => d.id === docId);
        if (!doc) return;
        
        doc.sentences.forEach(sentence => {
            const checkbox = document.getElementById(`sentence-${docId}-${sentence.id}`);
            if (checkbox && checkbox.checked) {
                checkbox.checked = false;
                this.toggleSentence(docId, sentence.id, false);
            }
        });
        
        this.showNotification('Đã bỏ chọn tất cả câu trong văn bản này', 'info');
    }
    
    highlightSentence(docId, sentenceId) {
        const sentenceItem = document.querySelector(
            `.sentence-item[data-document-id="${docId}"][data-sentence-id="${sentenceId}"]`
        );
        
        if (sentenceItem) {
            sentenceItem.classList.add('highlighted');
            setTimeout(() => {
                sentenceItem.classList.remove('highlighted');
            }, 2000);
        }
    }
    
    startAutoSave() {
        // Auto-save every 30 seconds
        this.autoSaveInterval = setInterval(() => {
            this.autoSave();
        }, 30000);
    }
    
    stopAutoSave() {
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval);
            this.autoSaveInterval = null;
        }
    }
    
    autoSave() {
        if (this.isSubmitting) return;
        
        const currentData = this.getCurrentLabelingData();
        
        // Only save if data has changed
        if (JSON.stringify(currentData) === this.lastSaveData) {
            return;
        }
        
        fetch('save_multi_labeling.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                group_id: this.taskData.group_id,
                action: 'auto_save',
                document_sentences: currentData.document_sentences,
                edited_summary: currentData.edited_summary
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.lastSaveData = JSON.stringify(currentData);
                this.showAutoSaveNotification();
            }
        })
        .catch(error => {
            console.log('Auto-save error:', error);
        });
    }
    
    saveProgress() {
        const saveData = this.getCurrentLabelingData();
        
        fetch('save_multi_labeling.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                group_id: this.taskData.group_id,
                action: 'save_progress',
                document_sentences: saveData.document_sentences,
                edited_summary: saveData.edited_summary
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Đã lưu tiến độ thành công!', 'success');
                this.lastSaveData = JSON.stringify(saveData);
            } else {
                this.showNotification('Lỗi lưu tiến độ: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Save error:', error);
            this.showNotification('Lỗi kết nối khi lưu dữ liệu', 'error');
        });
    }
    
    submitLabeling() {
        // Show confirmation modal
        const modal = new bootstrap.Modal(document.getElementById('submitModal'));
        
        // Update modal statistics
        let totalSelected = 0;
        Object.values(this.selectedSentences).forEach(sentences => {
            totalSelected += sentences.length;
        });
        
        document.getElementById('modal-selected-count').textContent = totalSelected;
        
        const summaryLength = document.getElementById('edited-summary').value.length;
        document.getElementById('modal-summary-length').textContent = summaryLength;
        
        modal.show();
    }
    
    confirmSubmit() {
        this.isSubmitting = true;
        this.stopAutoSave();
        
        const submitData = this.getCurrentLabelingData();
        
        // Validation
        const totalSelected = Object.values(submitData.document_sentences).reduce((sum, sentences) => sum + sentences.length, 0);
        
        if (totalSelected === 0) {
            this.showNotification('Cần chọn ít nhất một câu để hoàn thành!', 'error');
            this.isSubmitting = false;
            this.startAutoSave();
            bootstrap.Modal.getInstance(document.getElementById('submitModal')).hide();
            return;
        }
        
        if (submitData.edited_summary.trim().length < 10) {
            this.showNotification('Bản tóm tắt quá ngắn! Cần ít nhất 10 ký tự.', 'error');
            this.isSubmitting = false;
            this.startAutoSave();
            bootstrap.Modal.getInstance(document.getElementById('submitModal')).hide();
            return;
        }
        
        fetch('save_multi_labeling.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                group_id: this.taskData.group_id,
                action: 'submit_completed',
                document_sentences: submitData.document_sentences,
                edited_summary: submitData.edited_summary
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Hoàn thành gán nhãn thành công!', 'success');
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 2000);
            } else {
                this.showNotification('Lỗi hoàn thành: ' + data.message, 'error');
                this.isSubmitting = false;
                this.startAutoSave();
            }
            bootstrap.Modal.getInstance(document.getElementById('submitModal')).hide();
        })
        .catch(error => {
            console.error('Submit error:', error);
            this.showNotification('Lỗi kết nối khi gửi dữ liệu', 'error');
            this.isSubmitting = false;
            this.startAutoSave();
            bootstrap.Modal.getInstance(document.getElementById('submitModal')).hide();
        });
    }
    
    getCurrentLabelingData() {
        const editedSummary = document.getElementById('edited-summary').value;
        
        return {
            document_sentences: { ...this.selectedSentences },
            edited_summary: editedSummary
        };
    }
    
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
    
    showAutoSaveNotification() {
        const notification = document.getElementById('auto-save-notification');
        if (notification) {
            notification.classList.add('show');
            setTimeout(() => {
                notification.classList.remove('show');
            }, 2000);
        }
    }
}

// Global functions for onclick handlers
function selectAllSentences(docId) {
    if (window.multiLabeling) {
        window.multiLabeling.selectAllSentences(docId);
    }
}

function deselectAllSentences(docId) {
    if (window.multiLabeling) {
        window.multiLabeling.deselectAllSentences(docId);
    }
}

function highlightSentence(docId, sentenceId) {
    if (window.multiLabeling) {
        window.multiLabeling.highlightSentence(docId, sentenceId);
    }
}

function saveLabeling() {
    if (window.multiLabeling) {
        window.multiLabeling.saveProgress();
    }
}

function submitLabeling() {
    if (window.multiLabeling) {
        window.multiLabeling.submitLabeling();
    }
}

function confirmSubmit() {
    if (window.multiLabeling) {
        window.multiLabeling.confirmSubmit();
    }
}

// Initialize when page loads
function initMultiLabeling() {
    window.multiLabeling = new MultiLabeling();
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.multiLabeling) {
        window.multiLabeling.stopAutoSave();
    }
});