<?php
require_once 'config.php';

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    exit('Invalid request');
}

$candidate_id = $_GET['id'];
$file_type = $_GET['type']; // 'resume' or 'photo'

if ($file_type === 'resume') {
    $stmt = $conn->prepare("SELECT resume_data, resume_filename FROM candidates WHERE candidate_id = ?");
} else if ($file_type === 'photo') {
    $stmt = $conn->prepare("SELECT photo_data, photo_filename FROM candidates WHERE candidate_id = ?");
} else {
    exit('Invalid file type');
}

$stmt->execute([$candidate_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    exit('File not found');
}

$data = $file_type === 'resume' ? $file['resume_data'] : $file['photo_data'];
$filename = $file_type === 'resume' ? $file['resume_filename'] : $file['photo_filename'];

if (!$data) {
    exit('No file data');
}

// Set appropriate headers
if ($file_type === 'photo') {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
        case 'png':
            header('Content-Type: image/png');
            break;
        case 'gif':
            header('Content-Type: image/gif');
            break;
        default:
            header('Content-Type: image/jpeg');
    }
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
}

echo $data;
?>