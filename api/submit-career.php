<?php
require_once __DIR__ . '/config.php';
require_same_origin_unsafe_request();
rate_limit('submit_career', 5, 900);

// Disable error display for cleaner JSON response, log instead
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Ensure the career_applications table exists
try {
    $table_sql = "CREATE TABLE IF NOT EXISTS `career_applications` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `full_name` VARCHAR(100) NOT NULL,
      `email` VARCHAR(100) NOT NULL,
      `mobile` VARCHAR(20) NOT NULL,
      `position` VARCHAR(50) NOT NULL,
      `experience` INT NOT NULL,
      `city` VARCHAR(100) NOT NULL,
      `expected_salary` DECIMAL(10,2) NOT NULL,
      `resume_path` VARCHAR(255) NOT NULL,
      `cover_letter` TEXT DEFAULT NULL,
      `status` ENUM('Pending', 'Reviewed', 'Shortlisted', 'Rejected') DEFAULT 'Pending',
      `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($table_sql);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    error_log('Career table init error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to accept applications right now. Please try again later.']);
    exit;
}

// Ensure it is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

// Get post parameters
$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$position = trim($_POST['position'] ?? '');
$experience = isset($_POST['experience']) ? intval($_POST['experience']) : -1;
$city = trim($_POST['city'] ?? '');
$expectedSalary = isset($_POST['expected_salary']) ? floatval($_POST['expected_salary']) : -1.0;
$coverLetter = trim($_POST['cover_letter'] ?? '');

// List of allowed positions matching specifications
$allowed_positions = [
    'Waiter',
    'Captain',
    'Head Chef',
    'Supervisor',
    'Cleaning Staff',
    'CDP (Chef de Partie)',
    'Barista',
    'Commis 1',
    'Commis 2'
];

// Server-side validation
if (empty($fullName) || strlen($fullName) < 2) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid Full Name (minimum 2 characters).']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid Email Address.']);
    exit;
}

if (empty($mobile) || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid Mobile Number (10 to 15 digits).']);
    exit;
}

if (!in_array($position, $allowed_positions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid position selected. Please choose from the available listings.']);
    exit;
}

if ($experience < 0) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid number of years of experience.']);
    exit;
}

if (empty($city)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your current city.']);
    exit;
}

if ($expectedSalary < 0) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid expected salary.']);
    exit;
}

// Secure resume upload processing
if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['resume']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errMsg = 'No resume uploaded or file transfer error.';
    if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
        $errMsg = 'The uploaded resume exceeds the maximum size limit of 5MB.';
    }
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

$fileSize = $_FILES['resume']['size'];
if ($fileSize > 5 * 1024 * 1024) { // 5MB limit
    echo json_encode(['success' => false, 'message' => 'Resume file size exceeds the 5MB limit. Please upload a smaller file.']);
    exit;
}

$fileName = $_FILES['resume']['name'];
$fileTmpPath = $_FILES['resume']['tmp_name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$allowed_extensions = ['pdf', 'doc', 'docx'];
if (!in_array($fileExtension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file format. Only PDF, DOC, and DOCX resumes are accepted.']);
    exit;
}

// Double verify MIME type for added security
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fileTmpPath);
finfo_close($finfo);

$allowed_mimes = [
    'application/pdf',
    'application/msword', // doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
    'application/octet-stream' // sometimes returned for doc/docx on certain servers
];

if (!in_array($mimeType, $allowed_mimes)) {
    // If mime type checks out as text/plain or executable, reject it
    if (strpos($mimeType, 'text/') === 0 || strpos($mimeType, 'application/x-') === 0 || strpos($mimeType, 'html') !== false) {
        echo json_encode(['success' => false, 'message' => 'MIME type verification failed. Please upload a valid PDF or Word document.']);
        exit;
    }
}

// Make sure target directory exists
$upload_dir = dirname(__DIR__) . '/uploads/resumes/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Server error: Unable to create uploads directory.']);
        exit;
    }
}

// Generate secure file name
$newFileName = 'resume_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
$dest_path = $upload_dir . $newFileName;

if (!move_uploaded_file($fileTmpPath, $dest_path)) {
    echo json_encode(['success' => false, 'message' => 'Server error: Failed to save the resume file.']);
    exit;
}

// Store in database
try {
    $stmt = $pdo->prepare("INSERT INTO career_applications (full_name, email, mobile, position, experience, city, expected_salary, resume_path, cover_letter, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([
        $fullName,
        $email,
        $mobile,
        $position,
        $experience,
        $city,
        $expectedSalary,
        'uploads/resumes/' . $newFileName,
        $coverLetter ?: null
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully! Our talent acquisition team will review your resume shortly.'
    ]);
} catch (PDOException $e) {
    // Clean up uploaded file if database insert fails
    if (file_exists($dest_path)) {
        unlink($dest_path);
    }
    error_log('Career application save error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to save application right now. Please try again later.']);
}
?>
