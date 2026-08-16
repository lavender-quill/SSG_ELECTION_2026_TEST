<?php
/**
 * Upload multiple gallery images for a candidate/party
 * Stores images in a JSON file indexed by candidate ID
 */
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/includes/admin-guard.php';
requireAdminCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$candidateId = trim($_POST['candidate_id'] ?? '');
$galleryFile = DATA_DIR . '/candidate_gallery.json';

if ($candidateId === '') {
    echo json_encode(['success' => false, 'error' => 'Candidate ID is required.']);
    exit;
}

// Load existing gallery
$gallery = file_exists($galleryFile) ? (json_decode(file_get_contents($galleryFile), true) ?: []) : [];
if (!isset($gallery[$candidateId])) {
    $gallery[$candidateId] = [];
}

// Get uploaded files
$uploadedImages = [];
$errors = [];

if (!empty($_FILES['gallery_images']['tmp_name'])) {
    $files = $_FILES['gallery_images'];
    $fileCount = is_array($files['tmp_name']) ? count($files['tmp_name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $tmpFile = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error for $fileName";
            continue;
        }
        
        if ($fileSize > 5_000_000) {
            $errors[] = "$fileName is too large (max 5 MB)";
            continue;
        }
        
        $fileData = @file_get_contents($tmpFile);
        if ($fileData === false) {
            $errors[] = "Could not read $fileName";
            continue;
        }
        
        // Validate it's an image
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpFile);
        finfo_close($finfo);
        
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            $errors[] = "$fileName is not a valid image (got $mimeType)";
            continue;
        }
        
        $base64 = base64_encode($fileData);
        $uploadedImages[] = [
            'data' => $base64,
            'mime' => $mimeType,
            'name' => $fileName,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if (empty($uploadedImages) && !empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode('; ', $errors)]);
    exit;
}

if (!empty($uploadedImages)) {
    // Append new images to existing gallery
    $gallery[$candidateId] = array_merge($gallery[$candidateId], $uploadedImages);
    
    // Keep only last 10 images per candidate
    if (count($gallery[$candidateId]) > 10) {
        $gallery[$candidateId] = array_slice($gallery[$candidateId], -10);
    }
    
    file_put_contents($galleryFile, json_encode($gallery, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

$msg = count($uploadedImages) . ' image' . (count($uploadedImages) !== 1 ? 's' : '') . ' uploaded';
if (!empty($errors)) {
    $msg .= '; Errors: ' . implode('; ', $errors);
}

echo json_encode([
    'success' => true,
    'message' => $msg,
    'images_count' => count($gallery[$candidateId] ?? []),
    'errors' => $errors,
]);
