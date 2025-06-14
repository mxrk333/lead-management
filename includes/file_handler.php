<?php
// File handling functions

function handleFileUpload($files, $memo_id, $conn, $allowed_mime_types) {
    $response = array();
    
    try {
        $conn->begin_transaction();
        
        $upload_dir = 'uploads/memo_files/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_count = count($files['name']);
        $uploaded_files = array();
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $files['tmp_name'][$i];
                $name = $files['name'][$i];
                $type = $files['type'][$i];
                $size = $files['size'][$i];
                
                // Validate file size
                if ($size > 5 * 1024 * 1024) { // 5MB limit
                    throw new Exception("File too large: $name. Maximum size is 5MB.");
                }
                
                // Validate file type
                if (!in_array($type, $allowed_mime_types)) {
                    throw new Exception("Invalid file type: $name. Only PDF and images are allowed.");
                }
                
                // Generate unique filename
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $unique_name = uniqid('memo_') . '_' . time() . '.' . $extension;
                $file_path = $upload_dir . $unique_name;
                
                // Move file
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $file_type_category = getFileTypeCategory($type);
                    if ($file_type_category === 'image') {
                        // Insert into memo_images
                        $sql = "INSERT INTO memo_images (memo_id, image_path, image_name, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iss", $memo_id, $file_path, $name);
                        $stmt->execute();
                    } else {
                        // Insert into memo_files
                        $sql = "INSERT INTO memo_files (memo_id, file_path, file_name, file_type, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("isss", $memo_id, $file_path, $name, $type);
                        $stmt->execute();
                    }
                    $uploaded_files[] = array(
                        'name' => $name,
                        'path' => $file_path,
                        'type' => $type
                    );
                } else {
                    throw new Exception("Failed to upload file: $name");
                }
            }
        }
        
        $conn->commit();
        $response['success'] = true;
        $response['files'] = $uploaded_files;
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['error'] = $e->getMessage();
    }
    
    return $response;
}

function getMemoFiles($memo_id, $conn) {
    $files = array();
    
    $sql = "SELECT file_id, memo_id, file_path, file_name, file_type, created_at 
            FROM memo_files 
            WHERE memo_id = ? 
            UNION 
            SELECT image_id as file_id, memo_id, image_path as file_path, 
                   image_name as file_name, 'image' as file_type, created_at 
            FROM memo_images 
            WHERE memo_id = ? 
            ORDER BY file_type DESC, created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $memo_id, $memo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $files[] = $row;
    }
    
    return $files;
}

function getFile($file_id, $type, $conn) {
    if ($type === 'image') {
        $sql = "SELECT image_path as file_path, image_name as file_name FROM memo_images WHERE image_id = ?";
    } else {
        $sql = "SELECT file_path, file_name, file_type FROM memo_files WHERE file_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function getPdfFile($file_id, $conn) {
    $sql = "SELECT file_path, file_name, file_type FROM memo_files WHERE file_id = ? AND file_type = 'application/pdf'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function outputFile($file_path, $file_name, $download = false) {
    if (!file_exists($file_path)) {
        die('File not found.');
    }
    
    $mime_type = mime_content_type($file_path);
    
    if ($download) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
    } else {
        header('Content-Type: ' . $mime_type);
    }
    
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache');
    
    readfile($file_path);
    exit();
} 