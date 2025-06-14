// Memo Management JavaScript

class MemoManager {
    constructor() {
        this.initializeElements();
        this.attachEventListeners();
    }

    initializeElements() {
        // Modals
        this.createModal = document.getElementById('createMemoModal');
        this.editModal = document.getElementById('editMemoModal');
        this.imageModal = document.getElementById('imageModal');

        // Buttons
        this.createButton = document.getElementById('btnCreateMemo');
        this.closeCreateModalBtn = document.getElementById('closeCreateModal');
        this.closeEditModalBtn = document.getElementById('closeEditModal');
        this.closeImageModalBtn = document.getElementById('closeImageModal');

        // Forms
        this.createForm = document.getElementById('createMemoForm');
        this.editForm = document.getElementById('editMemoForm');

        // File upload elements
        this.fileUploadContainers = document.querySelectorAll('.file-upload-container');
    }

    attachEventListeners() {
        // Modal controls
        this.createButton?.addEventListener('click', () => this.openModal(this.createModal));
        this.closeCreateModalBtn?.addEventListener('click', () => this.closeModal(this.createModal));
        this.closeEditModalBtn?.addEventListener('click', () => this.closeModal(this.editModal));
        this.closeImageModalBtn?.addEventListener('click', () => this.closeModal(this.imageModal));

        // Form submissions
        this.createForm?.addEventListener('submit', (e) => this.handleFormSubmit(e, 'create'));
        this.editForm?.addEventListener('submit', (e) => this.handleFormSubmit(e, 'edit'));

        // File upload handling
        this.fileUploadContainers.forEach(container => {
            this.initializeFileUpload(container);
        });

        // Edit buttons
        document.querySelectorAll('.btn-icon.edit').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleEdit(e));
        });

        // Delete buttons
        document.querySelectorAll('.btn-icon.delete').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleDelete(e));
        });

        // Image preview
        document.querySelectorAll('.memo-image').forEach(img => {
            img.addEventListener('click', (e) => this.handleImagePreview(e));
        });
    }

    openModal(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    closeModal(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        if (modal === this.createModal) {
            this.createForm.reset();
            this.clearFilePreview(this.createForm);
        }
    }

    async handleFormSubmit(e, type) {
        e.preventDefault();
        
        // Validate form
        if (!this.validateForm(e.target)) {
            return;
        }

        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('api/memos.php', {
                method: type === 'create' ? 'POST' : 'PUT',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Success!', data.message, 'success');
                window.location.reload();
            } else {
                this.showNotification('Error', data.message, 'error');
            }
        } catch (error) {
            this.showNotification('Error', 'An unexpected error occurred', 'error');
        }
    }

    validateForm(form) {
        const title = form.querySelector('[name="title"]').value.trim();
        const content = form.querySelector('[name="content"]').value.trim();
        const visibleTo = form.querySelectorAll('[name="visible_to[]"]:checked');

        if (!title) {
            this.showNotification('Error', 'Please enter a title', 'error');
            return false;
        }

        if (!content) {
            this.showNotification('Error', 'Please enter content', 'error');
            return false;
        }

        if (visibleTo.length === 0) {
            this.showNotification('Error', 'Please select at least one role', 'error');
            return false;
        }

        return true;
    }

    async handleEdit(e) {
        const memoId = e.currentTarget.dataset.memoId;
        
        try {
            const response = await fetch(`api/memos.php?id=${memoId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateEditForm(data.memo);
                this.openModal(this.editModal);
            } else {
                this.showNotification('Error', data.message, 'error');
            }
        } catch (error) {
            this.showNotification('Error', 'Failed to load memo data', 'error');
        }
    }

    async handleDelete(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this memo?')) {
            return;
        }

        const memoId = e.currentTarget.dataset.memoId;
        
        try {
            const response = await fetch(`api/memos.php?id=${memoId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Success!', data.message, 'success');
                window.location.reload();
            } else {
                this.showNotification('Error', data.message, 'error');
            }
        } catch (error) {
            this.showNotification('Error', 'Failed to delete memo', 'error');
        }
    }

    initializeFileUpload(container) {
        const input = container.querySelector('.file-upload-input');
        const preview = container.closest('.file-upload').querySelector('.file-preview');

        // Drag and drop handling
        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            container.classList.add('dragover');
        });

        container.addEventListener('dragleave', () => {
            container.classList.remove('dragover');
        });

        container.addEventListener('drop', (e) => {
            e.preventDefault();
            container.classList.remove('dragover');
            this.handleFiles(Array.from(e.dataTransfer.files), preview);
        });

        // Click to upload
        container.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            this.handleFiles(Array.from(input.files), preview);
        });
    }

    handleFiles(files, preview) {
        const maxFiles = 5;
        if (files.length > maxFiles) {
            this.showNotification('Error', `Maximum ${maxFiles} files allowed`, 'error');
            return;
        }

        files.forEach(file => {
            if (!file.type.startsWith('image/')) {
                this.showNotification('Error', `${file.name} is not an image`, 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => this.createPreviewItem(e.target.result, preview);
            reader.readAsDataURL(file);
        });
    }

    createPreviewItem(src, preview) {
        const item = document.createElement('div');
        item.className = 'file-preview-item';
        
        const img = document.createElement('img');
        img.src = src;
        
        const removeBtn = document.createElement('button');
        removeBtn.className = 'file-preview-remove';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.onclick = () => item.remove();
        
        item.appendChild(img);
        item.appendChild(removeBtn);
        preview.appendChild(item);
    }

    clearFilePreview(form) {
        const preview = form.querySelector('.file-preview');
        if (preview) {
            preview.innerHTML = '';
        }
    }

    populateEditForm(memo) {
        const form = this.editForm;
        form.querySelector('[name="memo_id"]').value = memo.id;
        form.querySelector('[name="title"]').value = memo.title;
        form.querySelector('[name="content"]').value = memo.content;
        
        // Set visibility checkboxes
        const roles = memo.visible_roles.split(',');
        roles.forEach(role => {
            const checkbox = form.querySelector(`[name="visible_to[]"][value="${role.trim()}"]`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });

        // Clear and populate image preview
        this.clearFilePreview(form);
        const preview = form.querySelector('.file-preview');
        memo.images.forEach(image => {
            this.createPreviewItem(image.path, preview);
        });
    }

    showNotification(title, message, type) {
        // You can implement your preferred notification system here
        alert(`${title}: ${message}`);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new MemoManager();
}); 