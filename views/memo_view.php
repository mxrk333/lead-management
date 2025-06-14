<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memo System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* File display and upload styles */
        .memo-files {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .content-modal-file {
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            background-color: #f8fafc;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .content-modal-file:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-md);
        }

        .content-modal-file.image img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .content-modal-file.pdf {
            padding: 1.5rem;
            text-align: center;
        }

        .content-modal-file.pdf .file-icon {
            font-size: 3rem;
            color: #dc2626;
            margin-bottom: 0.75rem;
        }

        .content-modal-file .file-name {
            font-size: 0.875rem;
            color: var(--gray-dark);
            word-break: break-word;
        }

        .content-modal-file .file-actions {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            display: flex;
            gap: 0.5rem;
        }

        .content-modal-file .file-action {
            width: 2rem;
            height: 2rem;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--box-shadow);
            border: none;
        }

        .content-modal-file .file-action:hover {
            background-color: var(--primary);
            color: white;
        }

        .file-upload-container {
            border: 2px dashed var(--gray-light);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 2rem;
        }

        .file-upload-container:hover {
            border-color: var(--primary-light);
            background-color: rgba(14, 165, 233, 0.05);
        }

        .file-upload-container.dragover {
            border-color: var(--primary);
            background-color: rgba(14, 165, 233, 0.1);
        }

        .file-upload-icon {
            font-size: 2rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }

        .file-upload-text {
            color: var(--gray-dark);
            margin-bottom: 0.5rem;
        }

        .file-upload-subtext {
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* PDF viewer modal styles */
        .pdf-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.75);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .pdf-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .pdf-modal {
            width: 95%;
            max-width: 1200px;
            height: 90vh;
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-lg);
            display: flex;
            flex-direction: column;
        }

        .pdf-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .pdf-modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .pdf-modal-close {
            background: none;
            border: none;
            color: var(--gray);
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .pdf-modal-close:hover {
            color: var(--danger);
        }

        .pdf-modal-body {
            flex: 1;
            padding: 0;
            position: relative;
        }

        #pdfViewer {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="memo-content">
            <!-- Memo details section -->
            <div class="memo-details">
                <h2><?php echo htmlspecialchars($memo['title']); ?></h2>
                <div class="memo-meta">
                    <span>Created by: <?php echo htmlspecialchars($memo['creator_name']); ?></span>
                    <span>Date: <?php echo date('F j, Y', strtotime($memo['created_at'])); ?></span>
                </div>
                <div class="memo-body">
                    <?php echo nl2br(htmlspecialchars($memo['content'])); ?>
                </div>
            </div>

            <!-- File display section -->
            <div class="memo-files" id="memoFiles" data-memo-id="<?php echo $memo['memo_id']; ?>">
                <!-- Files will be dynamically added here -->
            </div>

            <!-- File upload section (admin only) -->
            <?php if ($user['role'] == 'admin'): ?>
            <div class="file-upload-container" id="fileUpload">
                <div class="file-upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="file-upload-text">
                    Drag and drop files here or click to upload
                </div>
                <div class="file-upload-subtext">
                    Supported formats: PDF, JPG, PNG, GIF (Max size: 5MB)
                </div>
                <input type="file" id="fileInput" multiple accept=".pdf,image/*" style="display: none;">
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PDF viewer modal -->
    <div class="pdf-modal-overlay" id="pdfModal">
        <div class="pdf-modal">
            <div class="pdf-modal-header">
                <h3 class="pdf-modal-title">View PDF</h3>
                <button class="pdf-modal-close" onclick="closePdfModal()">×</button>
            </div>
            <div class="pdf-modal-body">
                <iframe id="pdfViewer" src=""></iframe>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/memo.js"></script>
    <script>
        // Load memo files when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            const memoId = document.getElementById('memoFiles').dataset.memoId;
            loadMemoFiles(memoId);
        });
    </script>
</body>
</html> 