// Initialize file upload functionality
document.addEventListener('DOMContentLoaded', function() {
    const fileUpload = document.getElementById('fileUpload');
    const fileInput = document.getElementById('fileInput');
    
    if (fileUpload && fileInput) {
        // Handle click on upload area
        fileUpload.addEventListener('click', function() {
            fileInput.click();
        });
        
        // Handle file selection
        fileInput.addEventListener('change', function(e) {
            handleFiles(e.target.files);
        });
        
        // Handle drag and drop
        fileUpload.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('dragover');
        });
        
        fileUpload.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
        });
        
        fileUpload.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
            
            const dt = e.dataTransfer;
            const files = dt.files;
            
            handleFiles(files);
        });
    }
});

// Handle file upload
function handleFiles(files) {
    const formData = new FormData();
    let hasValidFiles = false;
    
    Array.from(files).forEach(file => {
        if (file.size <= 5 * 1024 * 1024) { // 5MB limit
            if (file.type.startsWith('image/') || file.type === 'application/pdf') {
                formData.append('memo_files[]', file);
                hasValidFiles = true;
            }
        }
    });
    
    if (!hasValidFiles) {
        alert('Please select valid files (PDF, JPG, PNG, GIF) under 5MB.');
        return;
    }
    
    // Add memo ID to the form data
    const memoId = document.querySelector('[data-memo-id]').dataset.memoId;
    formData.append('memo_id', memoId);
    
    // Upload files
    fetch('memo.php?action=upload_files', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the files display
            loadMemoFiles(memoId);
        } else {
            alert(data.error || 'Failed to upload files.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to upload files.');
    });
}

// Load memo files
function loadMemoFiles(memoId) {
    fetch(`memo.php?action=get_memo_files&memo_id=${memoId}`)
        .then(response => response.json())
        .then(files => {
            displayMemoFiles(files);
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Display memo files
function displayMemoFiles(files) {
    const filesContainer = document.getElementById('memoFiles');
    if (!filesContainer) return;
    
    filesContainer.innerHTML = '';
    
    files.forEach(file => {
        const fileElement = document.createElement('div');
        fileElement.className = `content-modal-file ${file.file_type === 'image' ? 'image' : 'pdf'}`;
        
        if (file.file_type === 'image') {
            fileElement.innerHTML = `
                <img src="${file.file_path}" alt="${file.file_name}">
                <div class="file-actions">
                    <button class="file-action" onclick="downloadFile(${file.file_id}, 'image')" title="Download">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div class="file-name">${file.file_name}</div>
            `;
        } else {
            fileElement.innerHTML = `
                <div class="file-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="file-actions">
                    <button class="file-action" onclick="previewFile(${file.file_id}, '${file.file_type}')" title="Preview">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="file-action" onclick="downloadFile(${file.file_id}, 'file')" title="Download">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div class="file-name">${file.file_name}</div>
            `;
        }
        
        filesContainer.appendChild(fileElement);
    });
}

// PDF viewer functions
function openPdfModal(fileId) {
    const modal = document.getElementById('pdfModal');
    const viewer = document.getElementById('pdfViewer');
    if (modal && viewer) {
        viewer.src = `memo.php?action=view_pdf&file_id=${fileId}`;
        modal.classList.add('active');
    }
}

function closePdfModal() {
    const modal = document.getElementById('pdfModal');
    const viewer = document.getElementById('pdfViewer');
    if (modal && viewer) {
        viewer.src = '';
        modal.classList.remove('active');
    }
}

// Handle file preview
function previewFile(fileId, fileType) {
    if (fileType === 'application/pdf') {
        openPdfModal(fileId);
    }
}

// Handle file download
function downloadFile(fileId, fileType) {
    window.location.href = `memo.php?action=download&file_id=${fileId}&type=${fileType}`;
}

// Export functions for global use
window.handleFiles = handleFiles;
window.loadMemoFiles = loadMemoFiles;
window.displayMemoFiles = displayMemoFiles;
window.openPdfModal = openPdfModal;
window.closePdfModal = closePdfModal;
window.previewFile = previewFile;
window.downloadFile = downloadFile; 