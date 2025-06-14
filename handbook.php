<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get database connection
$conn = getDbConnection();
if (!$conn) {
    die("Database connection failed.");
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$is_admin = isset($user['role']) && $user['role'] === 'admin';

// Handle handbook upload/edit (admin only)
$message = '';
$message_type = '';

if ($is_admin && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_handbook') {
        // Create new handbook
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $description = mysqli_real_escape_string($conn, isset($_POST['description']) ? $_POST['description'] : '');
        
        // Fix for the category error - check if it exists and provide default if not
        $category = isset($_POST['category']) ? mysqli_real_escape_string($conn, $_POST['category']) : 'Uncategorized';
        
        // Handle cover image upload
        $target_dir = "uploads/handbook_covers/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $cover_image = '';
        if (isset($_FILES["cover_image"]) && $_FILES["cover_image"]["error"] == 0) {
            $file_name = time() . '_' . basename($_FILES["cover_image"]["name"]);
            $target_file = $target_dir . $file_name;
            
            // Check if image file is a actual image
            $check = getimagesize($_FILES["cover_image"]["tmp_name"]);
            if ($check !== false) {
                if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $target_file)) {
                    $cover_image = $target_file;
                }
            }
        }
        
        // Handle PDF upload
        $target_pdf_dir = "uploads/handbook_pdfs/";
        if (!file_exists($target_pdf_dir)) {
            mkdir($target_pdf_dir, 0777, true);
        }
        
        $pdf_file = '';
        if (isset($_FILES["pdf_file"]) && $_FILES["pdf_file"]["error"] == 0) {
            $pdf_name = time() . '_' . basename($_FILES["pdf_file"]["name"]);
            $target_pdf = $target_pdf_dir . $pdf_name;
            
            // Check if file is a PDF
            $file_type = strtolower(pathinfo($target_pdf, PATHINFO_EXTENSION));
            if ($file_type == "pdf") {
                if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target_pdf)) {
                    $pdf_file = $target_pdf;
                }
            }
        }
        
        if (!empty($cover_image) && !empty($pdf_file)) {
            $query = "INSERT INTO handbooks (title, description, cover_image, pdf_file, category, created_by) 
                      VALUES ('$title', '$description', '$cover_image', '$pdf_file', '$category', $user_id)";
            
            if (mysqli_query($conn, $query)) {
                $message = "Handbook created successfully!";
                $message_type = "success";
            } else {
                $message = "Error creating handbook: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {
            $message = "Error uploading files. Please ensure you've selected both a cover image and a PDF file.";
            $message_type = "error";
        }
    } elseif ($_POST['action'] === 'delete_handbook') {
        // Delete handbook
        $handbook_id = (int)$_POST['handbook_id'];
        
        // Get file paths before deleting
        $query = "SELECT cover_image, pdf_file FROM handbooks WHERE id = $handbook_id";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $handbook = mysqli_fetch_assoc($result);
            
            // Delete files from server
            if (file_exists($handbook['cover_image'])) {
                unlink($handbook['cover_image']);
            }
            if (file_exists($handbook['pdf_file'])) {
                unlink($handbook['pdf_file']);
            }
        }
        
        // Delete from database
        $query = "DELETE FROM handbooks WHERE id = $handbook_id";
        if (mysqli_query($conn, $query)) {
            $message = "Handbook deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting handbook: " . mysqli_error($conn);
            $message_type = "error";
        }
    } elseif ($_POST['action'] === 'update_handbook') {
        // Update handbook details
        $handbook_id = (int)$_POST['handbook_id'];
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $description = mysqli_real_escape_string($conn, isset($_POST['description']) ? $_POST['description'] : '');
        $category = isset($_POST['category']) ? mysqli_real_escape_string($conn, $_POST['category']) : 'Uncategorized';
        
        $query = "UPDATE handbooks SET title = '$title', description = '$description', category = '$category' WHERE id = $handbook_id";
        if (mysqli_query($conn, $query)) {
            $message = "Handbook updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating handbook: " . mysqli_error($conn);
            $message_type = "error";
        }
    } elseif ($_POST['action'] === 'update_cover') {
        // Update cover image
        $handbook_id = (int)$_POST['handbook_id'];
        $target_dir = "uploads/handbook_covers/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Get old cover image path
        $query = "SELECT cover_image FROM handbooks WHERE id = $handbook_id";
        $result = mysqli_query($conn, $query);
        $old_cover = '';
        if ($result && mysqli_num_rows($result) > 0) {
            $handbook = mysqli_fetch_assoc($result);
            $old_cover = $handbook['cover_image'];
        }
        
        $cover_image = '';
        if (isset($_FILES["cover_image"]) && $_FILES["cover_image"]["error"] == 0) {
            $file_name = time() . '_' . basename($_FILES["cover_image"]["name"]);
            $target_file = $target_dir . $file_name;
            
            // Check if image file is a actual image
            $check = getimagesize($_FILES["cover_image"]["tmp_name"]);
            if ($check !== false) {
                if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $target_file)) {
                    $cover_image = $target_file;
                    
                    // Delete old cover image
                    if (!empty($old_cover) && file_exists($old_cover)) {
                        unlink($old_cover);
                    }
                }
            }
        }
        
        if (!empty($cover_image)) {
            $query = "UPDATE handbooks SET cover_image = '$cover_image' WHERE id = $handbook_id";
            if (mysqli_query($conn, $query)) {
                $message = "Cover image updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating cover image: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {
            $message = "Error uploading cover image.";
            $message_type = "error";
        }
    } elseif ($_POST['action'] === 'update_pdf') {
        // Update PDF file
        $handbook_id = (int)$_POST['handbook_id'];
        $target_dir = "uploads/handbook_pdfs/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Get old PDF path
        $query = "SELECT pdf_file FROM handbooks WHERE id = $handbook_id";
        $result = mysqli_query($conn, $query);
        $old_pdf = '';
        if ($result && mysqli_num_rows($result) > 0) {
            $handbook = mysqli_fetch_assoc($result);
            $old_pdf = $handbook['pdf_file'];
        }
        
        $pdf_file = '';
        if (isset($_FILES["pdf_file"]) && $_FILES["pdf_file"]["error"] == 0) {
            $file_name = time() . '_' . basename($_FILES["pdf_file"]["name"]);
            $target_file = $target_dir . $file_name;
            
            // Check if file is a PDF
            $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            if ($file_type == "pdf") {
                if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target_file)) {
                    $pdf_file = $target_file;
                    
                    // Delete old PDF file
                    if (!empty($old_pdf) && file_exists($old_pdf)) {
                        unlink($old_pdf);
                    }
                }
            }
        }
        
        if (!empty($pdf_file)) {
            $query = "UPDATE handbooks SET pdf_file = '$pdf_file' WHERE id = $handbook_id";
            if (mysqli_query($conn, $query)) {
                $message = "PDF file updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating PDF file: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {
            $message = "Error uploading PDF file.";
            $message_type = "error";
        }
    }
}

// Check if we're in edit mode for a specific handbook
$edit_mode = false;
$current_handbook = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit']) && $is_admin) {
    $edit_mode = true;
    $handbook_id = (int)$_GET['edit'];
    
    // Get handbook details
    $query = "SELECT * FROM handbooks WHERE id = $handbook_id";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $current_handbook = mysqli_fetch_assoc($result);
    } else {
        // Handbook not found, redirect back to main page
        header("Location: handbook.php");
        exit();
    }
}

// Get all handbooks
$query = "SELECT h.*, u.name as creator_name FROM handbooks h 
          LEFT JOIN users u ON h.created_by = u.id 
          ORDER BY h.created_at DESC";
$result = mysqli_query($conn, $query);
$handbooks = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $handbooks[] = $row;
    }
}

// Get all categories for filter
$categories = [];
$query = "SELECT DISTINCT category FROM handbooks ORDER BY category";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['category'])) {
            $categories[] = $row['category'];
        }
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_mode ? 'Edit Handbook' : 'Handbooks'; ?> - Inner SPARC Realty</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- PDF.js for better PDF viewing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: rgba(67, 97, 238, 0.1);
            --primary-dark: #3a56d4;
            --secondary: #f8f9fc;
            --success: #10b981;
            --success-light: rgba(16, 185, 129, 0.1);
            --danger: #ef4444;
            --danger-light: rgba(239, 68, 68, 0.1);
            --warning: #f59e0b;
            --warning-light: rgba(245, 158, 11, 0.1);
            --info: #3b82f6;
            --info-light: rgba(59, 130, 246, 0.1);
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-sm: 0.25rem;
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --transition: all 0.2s ease-in-out;
        }

        /* General Styles */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f3f4f6;
            color: var(--dark);
            line-height: 1.5;
            margin: 0;
        }

        /* Handbook Page */
        .handbook-page {
            flex: 1;
            padding: 1.5rem;
            width: 100%;
            margin: 0;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .page-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-title i {
            color: var(--primary);
        }

        /* Handbook Grid */
        .handbook-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Book Card Styling - Enhanced 3D Book Effect */
        .book-wrapper {
            position: relative;
            perspective: 1200px;
            width: 100%;
            height: 320px;
            margin-bottom: 1rem;
            cursor: pointer;
        }

        .book-container {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            transform: rotateY(-25deg);
            transition: transform 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer !important; /* Force cursor pointer */
            z-index: 5; /* Ensure it's clickable */
        }

        .book-container:hover {
            transform: rotateY(-15deg) translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .book-front {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            transform-style: preserve-3d;
            border-radius: 4px;
            box-shadow: 
                5px 5px 20px rgba(0, 0, 0, 0.3),
                1px 1px 5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            z-index: 10; /* Ensure it's above other elements */
        }

        .book-cover {
            position: absolute;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            border-radius: 4px;
            overflow: hidden;
            cursor: pointer !important; /* Force cursor pointer */
            z-index: 15; /* Ensure it's above other elements */
            pointer-events: none; /* Let clicks pass through to container */
        }

        .book-spine {
            position: absolute;
            width: 30px;
            height: 100%;
            transform: rotateY(90deg) translateZ(-15px);
            background-color: var(--primary-dark);
            left: -15px;
            top: 0;
            border-radius: 2px 0 0 2px;
            box-shadow: -2px 0 5px rgba(0, 0, 0, 0.2);
        }

        .book-side {
            position: absolute;
            width: 40px;
            height: 100%;
            transform: rotateY(90deg) translateZ(-20px) translateX(10px);
            background: linear-gradient(to right, #f9f9f9, #eaeaea);
            left: -15px;
            top: 0;
        }

        .book-pages {
            position: absolute;
            width: calc(100% - 10px);
            height: calc(100% - 10px);
            top: 5px;
            left: 5px;
            background-color: white;
            border-radius: 2px;
            transform: translateZ(-1px);
        }

        .book-details {
            background-color: var(--white);
            border-radius: var(--radius);
            padding: 1rem;
            margin-top: 0.5rem;
            box-shadow: var(--shadow);
        }

        .book-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            color: var(--dark);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 2.5rem;
        }

        .book-category {
            display: inline-block;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: var(--radius-sm);
            margin-bottom: 0.5rem;
        }

        .book-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
            width: 100%;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--gray-light);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background-color: #d1d5db;
        }

        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Featured Handbook */
        .featured-handbook {
            background-color: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .featured-handbook::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary), var(--info));
        }

        .featured-content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .featured-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .featured-icon {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .featured-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .featured-description {
            font-size: 1.125rem;
            color: var(--gray);
            max-width: 600px;
        }

        .featured-actions {
            display: flex;
            gap: 1rem;
        }

        .featured-actions .btn {
            width: auto;
        }

        /* Search */
        .search-container {
            margin-bottom: 2rem;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-light);
        }

        /* Categories */
        .category-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            -webkit-overflow-scrolling: touch;
        }

        .category-tab {
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            background-color: var(--secondary);
            color: var(--gray);
            border: none;
        }

        .category-tab.active {
            background-color: var(--primary);
            color: var(--white);
        }

        .category-tab:hover:not(.active) {
            background-color: var(--gray-light);
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 3rem;
            background-color: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
        }

        .no-results i {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }

        .no-results h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            color: var(--dark);
        }

        .no-results p {
            margin: 0;
            color: var(--gray);
        }

        /* Admin Badge */
        .admin-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            background-color: var(--warning-light);
            color: var(--warning);
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: auto;
        }

        /* Admin Controls */
        .admin-controls {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background-color: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .admin-controls h2 {
            margin-top: 0;
            margin-bottom: 1rem;
            font-size: 1.25rem;
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-light);
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: var(--radius);
            font-weight: 500;
        }

        .alert-success {
            background-color: var(--success-light);
            color: var(--success);
        }

        .alert-error {
            background-color: var(--danger-light);
            color: var(--danger);
        }

        /* Enhanced PDF Viewer Modal */
        .pdf-viewer {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 10000; /* Ensure it's above everything */
            overflow: hidden;
        }

        .pdf-container {
            position: relative;
            width: 95%;
            height: 90%;
            max-width: 1200px;
            margin: 2% auto;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
        }

        .pdf-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .pdf-title {
            font-weight: 600;
            font-size: 1rem;
            color: #333;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 80%;
        }

        .pdf-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pdf-control-btn {
            background-color: transparent;
            border: none;
            color: #555;
            font-size: 1rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .pdf-control-btn:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }

        .pdf-viewer-content {
            flex: 1;
            overflow: auto;
            position: relative;
        }

        #pdf-canvas-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            min-height: 100%;
        }

        .pdf-canvas {
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .pdf-navigation {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 1rem;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 10001; /* Ensure it's above other elements in the PDF viewer */
        }

        .pdf-page-info {
            font-size: 0.875rem;
            color: #333;
            margin: 0 1rem;
        }

        .pdf-nav-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .pdf-nav-btn:hover {
            background-color: var(--primary-dark);
        }

        .pdf-nav-btn:disabled {
            background-color: var(--gray-light);
            color: var(--gray);
            cursor: not-allowed;
        }

        /* Edit Mode Styles */
        .handbook-details {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background-color: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .handbook-cover-preview {
            width: 200px;
            height: 300px;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            flex-shrink: 0;
        }

        .handbook-cover-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .handbook-info {
            flex: 1;
        }

        .handbook-info h2 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
            color: var(--dark);
        }

        .handbook-info p {
            margin-bottom: 1rem;
            color: var(--gray);
        }

        .handbook-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-light);
            margin-bottom: 1.5rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            border-bottom: 2px solid transparent;
            transition: var(--transition);
            white-space: nowrap;
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .book-wrapper {
            animation: fadeIn 0.3s ease-out forwards;
        }

        .book-wrapper:nth-child(1) { animation-delay: 0.1s; }
        .book-wrapper:nth-child(2) { animation-delay: 0.2s; }
        .book-wrapper:nth-child(3) { animation-delay: 0.3s; }
        .book-wrapper:nth-child(4) { animation-delay: 0.4s; }
        .book-wrapper:nth-child(5) { animation-delay: 0.5s; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background-color: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            max-width: 700px;
            width: 100%;
            margin: 30px auto;
            position: relative;
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .modal-footer .btn {
            width: auto;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .form-actions .btn {
            width: auto;
        }

        /* Admin Actions */
        .admin-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
            z-index: 10;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .handbook-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }

            .featured-content {
                flex-direction: column;
            }

            .featured-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .handbook-details {
                flex-direction: column;
            }

            .handbook-cover-preview {
                width: 150px;
                height: 225px;
                margin: 0 auto 1.5rem;
            }
            
            .handbook-actions {
                flex-direction: column;
            }
            
            .handbook-actions .btn {
                width: 100%;
            }
            
            .modal-content {
                margin: 10px;
                width: calc(100% - 20px);
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
            }

            .book-wrapper {
                height: 280px;
            }
        }

        @media (max-width: 480px) {
            .handbook-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .page-actions {
                width: 100%;
            }
            
            .page-actions .btn {
                width: 100%;
            }

            .book-wrapper {
                height: 240px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="handbook-page">
                <?php if ($edit_mode): ?>
                <!-- EDIT MODE -->
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-book-open"></i> Edit Handbook
                    </h1>
                    <div class="page-actions">
                        <a href="handbook.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Handbooks
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <!-- Handbook Details -->
                <div class="handbook-details">
                    <div class="handbook-cover-preview">
                        <img src="<?php echo htmlspecialchars($current_handbook['cover_image']); ?>" alt="<?php echo htmlspecialchars($current_handbook['title']); ?>">
                    </div>
                    <div class="handbook-info">
                        <h2><?php echo htmlspecialchars($current_handbook['title']); ?></h2>
                        <p><?php echo htmlspecialchars($current_handbook['description'] ?? 'No description provided.'); ?></p>
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($current_handbook['category'] ?? 'Uncategorized'); ?></p>
                        <p><strong>Created:</strong> <?php echo date('F j, Y', strtotime($current_handbook['created_at'])); ?></p>
                        <p><strong>PDF File:</strong> <?php echo basename($current_handbook['pdf_file']); ?></p>
                        
                        <div class="handbook-actions">
                            <button class="btn btn-primary" id="editHandbookBtn">
                                <i class="fas fa-edit"></i> Edit Handbook Details
                            </button>
                            <button class="btn btn-secondary" id="editCoverBtn">
                                <i class="fas fa-image"></i> Change Cover Image
                            </button>
                            <button class="btn btn-secondary" id="editPdfBtn">
                                <i class="fas fa-file-pdf"></i> Change PDF File
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Handbook Form (Hidden by default) -->
                <div class="admin-controls" id="editHandbookForm" style="display: none;">
                    <h2>Edit Handbook Details</h2>
                    <form action="handbook.php?edit=<?php echo $current_handbook['id']; ?>" method="post">
                        <input type="hidden" name="action" value="update_handbook">
                        <input type="hidden" name="handbook_id" value="<?php echo $current_handbook['id']; ?>">
                        
                        <div class="form-group">
                            <label for="title">Handbook Title</label>
                            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($current_handbook['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($current_handbook['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" class="form-control" value="<?php echo htmlspecialchars($current_handbook['category'] ?? ''); ?>" list="category-list">
                            <datalist id="category-list">
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancelEditBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Edit Cover Form (Hidden by default) -->
                <div class="admin-controls" id="editCoverForm" style="display: none;">
                    <h2>Change Cover Image</h2>
                    <form action="handbook.php?edit=<?php echo $current_handbook['id']; ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_cover">
                        <input type="hidden" name="handbook_id" value="<?php echo $current_handbook['id']; ?>">
                        
                        <div class="form-group">
                            <label for="cover_image">New Cover Image</label>
                            <input type="file" id="cover_image" name="cover_image" class="form-control" accept="image/*" required>
                            <small>Recommended size: 400x600 pixels</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Cover
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancelCoverBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Edit PDF Form (Hidden by default) -->
                <div class="admin-controls" id="editPdfForm" style="display: none;">
                    <h2>Change PDF File</h2>
                    <form action="handbook.php?edit=<?php echo $current_handbook['id']; ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_pdf">
                        <input type="hidden" name="handbook_id" value="<?php echo $current_handbook['id']; ?>">
                        
                        <div class="form-group">
                            <label for="pdf_file">New PDF File</label>
                            <input type="file" id="pdf_file" name="pdf_file" class="form-control" accept="application/pdf" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update PDF
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancelPdfBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php else: ?>
                <!-- MAIN VIEW (Handbook List) -->
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-book"></i> Handbooks
                    </h1>
                    <?php if ($is_admin): ?>
                    <div class="page-actions">
                        <button class="btn btn-primary" id="openCreateModalBtn">
                            <i class="fas fa-plus"></i> Create New Handbook
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <!-- Featured Handbook -->
                <div class="featured-handbook">
                    <div class="featured-content">
                        <div class="featured-header">
                            <div class="featured-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <h2 class="featured-title">Welcome to Inner SPARC Realty Handbooks</h2>
                        </div>
                        <p class="featured-description">
                            Access all the important handbooks and documentation you need for your work at Inner SPARC Realty. 
                            Bookmark this page for quick access to all essential resources.
                        </p>
                        <div class="featured-actions">
                            <a href="#all-handbooks" class="btn btn-primary">
                                <i class="fas fa-book"></i> View All Handbooks
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Search -->
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search for handbooks..." onkeyup="filterHandbooks()">
                </div>
                
                <!-- Categories -->
                <div class="category-tabs">
                    <button class="category-tab active" onclick="filterCategory('all')">All</button>
                    <?php foreach ($categories as $category): ?>
                    <button class="category-tab" onclick="filterCategory('<?php echo htmlspecialchars($category); ?>')"><?php echo htmlspecialchars($category); ?></button>
                    <?php endforeach; ?>
                </div>
                
                <!-- Handbooks Grid -->
                
<div id="all-handbooks" class="handbook-grid">
    <?php foreach ($handbooks as $handbook): ?>
    <?php 
        // Generate a random color for the handbook icon
        $colors = ['#4361ee', '#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
        $color = $colors[array_rand($colors)];
    ?>
    <div class="book-wrapper" data-category="<?php echo htmlspecialchars($handbook['category'] ?? ''); ?>">
        <!-- Book 3D Effect with direct onclick -->
        <div class="book-container" onclick="openPdfViewer('<?php echo htmlspecialchars($handbook['pdf_file']); ?>', '<?php echo htmlspecialchars($handbook['title']); ?>');">
            <div class="book-front">
                <div class="book-cover" style="background-image: url('<?php echo htmlspecialchars($handbook['cover_image']); ?>')"></div>
            </div>
            <div class="book-spine" style="background-color: <?php echo $color; ?>"></div>
            <div class="book-side"></div>
            <div class="book-pages"></div>
        </div>
        
        <!-- Book Info -->
        <div class="book-details">
            <h3 class="book-title"><?php echo htmlspecialchars($handbook['title']); ?></h3>
            <span class="book-category"><?php echo htmlspecialchars($handbook['category'] ?? 'Uncategorized'); ?></span>
            <div class="book-actions">
                <button onclick="openPdfViewer('<?php echo htmlspecialchars($handbook['pdf_file']); ?>', '<?php echo htmlspecialchars($handbook['title']); ?>')" class="btn btn-primary" style="background-color: <?php echo $color; ?>">
                    <i class="fas fa-book-open"></i> Read
                </button>
            </div>
        </div>
        
        <?php if ($is_admin): ?>
        <div class="admin-actions">
            <a href="handbook.php?edit=<?php echo $handbook['id']; ?>" class="btn btn-secondary btn-sm" title="Edit">
                <i class="fas fa-edit"></i>
            </a>
            <form action="handbook.php" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this handbook?');">
                <input type="hidden" name="action" value="delete_handbook">
                <input type="hidden" name="handbook_id" value="<?php echo $handbook['id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($handbooks)): ?>
    <div id="noResults" class="no-results">
        <i class="fas fa-book"></i>
        <h3>No Handbooks Available</h3>
        <p>No handbooks have been added yet.</p>
        <?php if ($is_admin): ?>
        <p>Click "Create New Handbook" to add your first handbook.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

                
                <!-- No Results (hidden by default) -->
                <div id="noResultsSearch" class="no-results" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h3>No Handbooks Found</h3>
                    <p>We couldn't find any handbooks matching your search. Try different keywords or clear your search.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Enhanced PDF Viewer Modal -->
    <div class="pdf-viewer" id="pdfViewer">
        <div class="pdf-container">
            <div class="pdf-header">
                <h3 class="pdf-title" id="pdfTitle">Handbook</h3>
                <div class="pdf-controls">
                    <button class="pdf-control-btn" id="zoomInBtn" title="Zoom In">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button class="pdf-control-btn" id="zoomOutBtn" title="Zoom Out">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button class="pdf-control-btn" id="downloadBtn" title="Download">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="pdf-control-btn" id="closeViewer" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="pdf-viewer-content">
                <div id="pdf-canvas-container"></div>
                <div class="pdf-navigation">
                    <button class="pdf-nav-btn" id="prevPage" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="pdf-page-info" id="pageInfo">Page 1 of 1</span>
                    <button class="pdf-nav-btn" id="nextPage">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Handbook Modal -->
    <?php if ($is_admin): ?>
    <div class="modal" id="createHandbookModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Create New Handbook</h2>
                <button type="button" class="modal-close" id="closeCreateModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createHandbookForm" action="handbook.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_handbook">
                    
                    <div class="form-group">
                        <label for="title">Handbook Title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" class="form-control" list="category-list">
                        <datalist id="category-list">
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-group">
                        <label for="cover_image">Cover Image</label>
                        <input type="file" id="cover_image" name="cover_image" class="form-control" accept="image/*" required>
                        <small>Recommended size: 400x600 pixels</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="pdf_file">PDF File</label>
                        <input type="file" id="pdf_file" name="pdf_file" class="form-control" accept="application/pdf" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelCreateBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="submitCreateBtn">
                    <i class="fas fa-save"></i> Create Handbook
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const createHandbookModal = document.getElementById('createHandbookModal');
            const openCreateModalBtn = document.getElementById('openCreateModalBtn');
            const closeCreateModal = document.getElementById('closeCreateModal');
            const cancelCreateBtn = document.getElementById('cancelCreateBtn');
            const submitCreateBtn = document.getElementById('submitCreateBtn');
            const createHandbookForm = document.getElementById('createHandbookForm');
            
            if (openCreateModalBtn) {
                openCreateModalBtn.addEventListener('click', function() {
                    createHandbookModal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                });
            }
            
            if (closeCreateModal) {
                closeCreateModal.addEventListener('click', function() {
                    createHandbookModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                });
            }
            
            if (cancelCreateBtn) {
                cancelCreateBtn.addEventListener('click', function() {
                    createHandbookModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                });
            }
            
            if (submitCreateBtn) {
                submitCreateBtn.addEventListener('click', function() {
                    // Validate form
                    const titleInput = document.querySelector('#createHandbookForm #title');
                    const coverInput = document.querySelector('#createHandbookForm #cover_image');
                    const pdfInput = document.querySelector('#createHandbookForm #pdf_file');
                    
                    if (!titleInput.value.trim()) {
                        alert('Please enter a handbook title.');
                        titleInput.focus();
                        return;
                    }
                    
                    if (!coverInput.files.length) {
                        alert('Please select a cover image.');
                        coverInput.focus();
                        return;
                    }
                    
                    if (!pdfInput.files.length) {
                        alert('Please select a PDF file.');
                        pdfInput.focus();
                        return;
                    }
                    
                    // Submit the form
                    createHandbookForm.submit();
                });
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === createHandbookModal) {
                    createHandbookModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
            
            // Edit Mode: Edit Handbook Form Toggle
            const editHandbookBtn = document.getElementById('editHandbookBtn');
            const editHandbookForm = document.getElementById('editHandbookForm');
            const cancelEditBtn = document.getElementById('cancelEditBtn');
            
            if (editHandbookBtn) {
                editHandbookBtn.addEventListener('click', function() {
                    editHandbookForm.style.display = 'block';
                    editCoverForm.style.display = 'none';
                    editPdfForm.style.display = 'none';
                    window.scrollTo({
                        top: editHandbookForm.offsetTop - 20,
                        behavior: 'smooth'
                    });
                });
            }
            
            if (cancelEditBtn) {
                cancelEditBtn.addEventListener('click', function() {
                    editHandbookForm.style.display = 'none';
                });
            }
            
            // Edit Mode: Edit Cover Form Toggle
            const editCoverBtn = document.getElementById('editCoverBtn');
            const editCoverForm = document.getElementById('editCoverForm');
            const cancelCoverBtn = document.getElementById('cancelCoverBtn');
            
            if (editCoverBtn) {
                editCoverBtn.addEventListener('click', function() {
                    editCoverForm.style.display = 'block';
                    editHandbookForm.style.display = 'none';
                    editPdfForm.style.display = 'none';
                    window.scrollTo({
                        top: editCoverForm.offsetTop - 20,
                        behavior: 'smooth'
                    });
                });
            }
            
            if (cancelCoverBtn) {
                cancelCoverBtn.addEventListener('click', function() {
                    editCoverForm.style.display = 'none';
                });
            }
            
            // Edit Mode: Edit PDF Form Toggle
            const editPdfBtn = document.getElementById('editPdfBtn');
            const editPdfForm = document.getElementById('editPdfForm');
            const cancelPdfBtn = document.getElementById('cancelPdfBtn');
            
            if (editPdfBtn) {
                editPdfBtn.addEventListener('click', function() {
                    editPdfForm.style.display = 'block';
                    editHandbookForm.style.display = 'none';
                    editCoverForm.style.display = 'none';
                    window.scrollTo({
                        top: editPdfForm.offsetTop - 20,
                        behavior: 'smooth'
                    });
                });
            }
            
            if (cancelPdfBtn) {
                cancelPdfBtn.addEventListener('click', function() {
                    editPdfForm.style.display = 'none';
                });
            }
        });
        
        // Function to filter handbooks based on search input
        function filterHandbooks() {
            const searchInput = document.getElementById('searchInput');
            const filter = searchInput.value.toLowerCase();
            const handbookCards = document.querySelectorAll('.book-wrapper');
            const noResults = document.getElementById('noResultsSearch');
            
            let resultsFound = false;
            
            handbookCards.forEach(card => {
                const title = card.querySelector('.book-title').textContent.toLowerCase();
                
                if (title.includes(filter)) {
                    card.style.display = '';
                    resultsFound = true;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (noResults) {
                noResults.style.display = resultsFound ? 'none' : 'block';
                
                // Hide the default no results if we're showing the search no results
                const defaultNoResults = document.getElementById('noResults');
                if (defaultNoResults) {
                    defaultNoResults.style.display = 'none';
                }
            }
        }
        
        // Function to filter handbooks by category
        function filterCategory(category) {
            // Update active tab
            const tabs = document.querySelectorAll('.category-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.textContent.toLowerCase() === category.toLowerCase() || 
                    (category === 'all' && tab.textContent.toLowerCase() === 'all')) {
                    tab.classList.add('active');
                }
            });
            
            // Filter cards
            const handbookCards = document.querySelectorAll('.book-wrapper');
            const noResults = document.getElementById('noResultsSearch');
            
            let resultsFound = false;
            
            handbookCards.forEach(card => {
                if (category === 'all' || card.dataset.category.toLowerCase() === category.toLowerCase()) {
                    card.style.display = '';
                    resultsFound = true;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (noResults) {
                noResults.style.display = resultsFound ? 'none' : 'block';
                
                // Hide the default no results if we're showing the search no results
                const defaultNoResults = document.getElementById('noResults');
                if (defaultNoResults) {
                    defaultNoResults.style.display = 'none';
                }
            }
            
            // Clear search input
            document.getElementById('searchInput').value = '';
        }

        // PDF.js viewer implementation
        // Add this at the beginning of your script section
        if (typeof pdfjsLib === 'undefined') {
            console.error('PDF.js library not loaded!');
            // Fallback to simple iframe viewer if PDF.js is not available
            window.openPdfViewer = function(pdfUrl, title) {
                const pdfViewer = document.getElementById('pdfViewer');
                const pdfTitle = document.getElementById('pdfTitle');
                
                if (!pdfViewer || !pdfTitle) {
                    console.error("PDF viewer elements not found");
                    return;
                }
                
                pdfTitle.textContent = title || 'Handbook';
                
                // Create iframe for fallback
                const container = document.querySelector('.pdf-viewer-content');
                container.innerHTML = `<iframe src="${pdfUrl}" style="width:100%;height:100%;border:none;" title="PDF Viewer"></iframe>`;
                
                pdfViewer.style.display = 'block';
                document.body.style.overflow = 'hidden';
            };
        } else {
            // Initialize PDF.js
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
        }

        // Open PDF viewer with enhanced functionality
        function openPdfViewer(pdfUrl, title) {
            console.log("Opening PDF viewer:", pdfUrl, title); // Debug log
            
            const pdfViewer = document.getElementById('pdfViewer');
            const pdfTitle = document.getElementById('pdfTitle');
            const canvasContainer = document.getElementById('pdf-canvas-container');
            
            if (!pdfViewer || !pdfTitle || !canvasContainer) {
                console.error("PDF viewer elements not found");
                alert("Error: PDF viewer elements not found");
                return;
            }
            
            // Clear previous PDF
            canvasContainer.innerHTML = '';
            
            // Set title and show viewer
            pdfTitle.textContent = title || 'Handbook';
            pdfViewer.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Store current PDF URL for download
            window.currentPdfUrl = pdfUrl;
            
            // Reset page number and scale
            window.pageNum = 1;
            window.scale = 1.0;
            window.pageRendering = false;
            window.pageNumPending = null;
            
            // Simple fallback - just use iframe
            canvasContainer.innerHTML = `<iframe src="${pdfUrl}" style="width:100%;height:100%;border:none;" title="PDF Viewer"></iframe>`;
            
            // Try to load with PDF.js if available
            if (typeof pdfjsLib !== 'undefined') {
                try {
                    loadPdf(pdfUrl);
                } catch (error) {
                    console.error("Error loading PDF with PDF.js:", error);
                    // Fallback already in place
                }
            }
        }

        // Load PDF document
        function loadPdf(url) {
            try {
                pdfjsLib.getDocument(url).promise.then(function(pdf) {
                    pdfDoc = pdf;
                    document.getElementById('pageInfo').textContent = `Page ${pageNum} of ${pdfDoc.numPages}`;
                    document.getElementById('prevPage').disabled = pageNum <= 1;
                    document.getElementById('nextPage').disabled = pageNum >= pdfDoc.numPages;
                    
                    // Render first page
                    renderPage(pageNum);
                }).catch(function(error) {
                    console.error('Error loading PDF:', error);
                    const canvasContainer = document.getElementById('pdf-canvas-container');
                    canvasContainer.innerHTML = `
                        <div style="padding: 20px; color: red;">
                            <p>Error loading PDF: ${error.message}</p>
                            <p>Try opening directly: <a href="${url}" target="_blank" style="color: blue; text-decoration: underline;">Open PDF</a></p>
                        </div>`;
                });
            } catch (error) {
                console.error('Error in loadPdf function:', error);
                const canvasContainer = document.getElementById('pdf-canvas-container');
                canvasContainer.innerHTML = `
                    <div style="padding: 20px; color: red;">
                        <p>Error loading PDF: ${error.message}</p>
                        <p>Try opening directly: <a href="${url}" target="_blank" style="color: blue; text-decoration: underline;">Open PDF</a></p>
                    </div>`;
            }
        }

        // Render a specific page
        function renderPage(num) {
            pageRendering = true;
            
            try {
                // Get the page
                pdfDoc.getPage(num).then(function(page) {
                    const viewport = page.getViewport({ scale: scale });
                    
                    // Create canvas for this page
                    const canvas = document.createElement('canvas');
                    canvas.className = 'pdf-canvas';
                    canvas.id = `page-${num}`;
                    const ctx = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    // Add canvas to container
                    const canvasContainer = document.getElementById('pdf-canvas-container');
                    
                    // Clear container if we're re-rendering the current page
                    if (document.getElementById(`page-${num}`)) {
                        document.getElementById(`page-${num}`).remove();
                    }
                    
                    canvasContainer.appendChild(canvas);
                    
                    // Render PDF page
                    const renderContext = {
                        canvasContext: ctx,
                        viewport: viewport
                    };
                    
                    const renderTask = page.render(renderContext);
                    
                    // Wait for rendering to finish
                    renderTask.promise.then(function() {
                        pageRendering = false;
                        
                        // Update page info
                        document.getElementById('pageInfo').textContent = `Page ${num} of ${pdfDoc.numPages}`;
                        document.getElementById('prevPage').disabled = num <= 1;
                        document.getElementById('nextPage').disabled = num >= pdfDoc.numPages;
                        
                        // Scroll to the newly rendered page
                        canvas.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        
                        // If another page rendering is pending, render that page
                        if (pageNumPending !== null) {
                            renderPage(pageNumPending);
                            pageNumPending = null;
                        }
                    }).catch(function(error) {
                        console.error('Error rendering page:', error);
                        pageRendering = false;
                    });
                }).catch(function(error) {
                    console.error('Error getting page:', error);
                    pageRendering = false;
                });
            } catch (error) {
                console.error('Error in renderPage function:', error);
                pageRendering = false;
            }
        }

        // Go to previous page
        function prevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }

        // Go to next page
        function nextPage() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }

        // Queue rendering of a page
        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        // Zoom in function
        function zoomIn() {
            if (scale >= 3.0) return; // Max zoom
            scale += 0.25;
            if (pdfDoc) {
                renderPage(pageNum);
            }
        }

        // Zoom out function
        function zoomOut() {
            if (scale <= 0.5) return; // Min zoom
            scale -= 0.25;
            if (pdfDoc) {
                renderPage(pageNum);
            }
        }

        // Set up PDF viewer controls
        document.addEventListener('DOMContentLoaded', function() {
            const pdfViewer = document.getElementById('pdfViewer');
            const closeViewer = document.getElementById('closeViewer');
            const prevPageBtn = document.getElementById('prevPage');
            const nextPageBtn = document.getElementById('nextPage');
            const zoomInBtn = document.getElementById('zoomInBtn');
            const zoomOutBtn = document.getElementById('zoomOutBtn');
            const downloadBtn = document.getElementById('downloadBtn');
            
            // Close PDF viewer
            if (closeViewer) {
                closeViewer.addEventListener('click', function() {
                    pdfViewer.style.display = 'none';
                    document.body.style.overflow = 'auto';
                    
                    // Clear PDF document
                    pdfDoc = null;
                    const canvasContainer = document.getElementById('pdf-canvas-container');
                    canvasContainer.innerHTML = '';
                });
            }
            
            // Previous page button
            if (prevPageBtn) {
                prevPageBtn.addEventListener('click', prevPage);
            }
            
            // Next page button
            if (nextPageBtn) {
                nextPageBtn.addEventListener('click', nextPage);
            }
            
            // Zoom in button
            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', zoomIn);
            }
            
            // Zoom out button
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', zoomOut);
            }
            
            // Download button
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    if (currentPdfUrl) {
                        const a = document.createElement('a');
                        a.href = currentPdfUrl;
                        a.download = document.getElementById('pdfTitle').textContent + '.pdf';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    }
                });
            }
            
            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (pdfViewer.style.display === 'block') {
                    if (e.key === 'Escape') {
                        closeViewer.click();
                    } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                        prevPage();
                    } else if (e.key === 'ArrowRight' || e.key === 'PageDown') {
                        nextPage();
                    } else if (e.key === '+') {
                        zoomIn();
                    } else if (e.key === '-') {
                        zoomOut();
                    }
                }
                
                if (createHandbookModal && createHandbookModal.style.display === 'block' && e.key === 'Escape') {
                    createHandbookModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        });
    </script>
</body>
</html>
