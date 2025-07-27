<?php
// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for potential future use
session_start();

// Set content type
header('Content-Type: text/html; charset=utf-8');

// Database configuration - REPLACE WITH YOUR ACTUAL CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'marriage_biodata');

// File upload configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// HTML header template
function htmlHeader($title) {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { color: #2ecc71; background: #eafaf1; padding: 15px; border-radius: 5px; }
        .error { color: #e74c3c; background: #fdedec; padding: 15px; border-radius: 5px; }
        .btn { display: inline-block; padding: 10px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
HTML;
}

// HTML footer template
function htmlFooter() {
    return <<<HTML
</body>
</html>
HTML;
}

try {
    // Output header
    echo htmlHeader("Submission Result");

    // Verify form submission method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method. Please submit the form.");
    }

    // Validate required fields
    $required = ['name', 'dob', 'gender', 'marital_status', 'religion', 'height', 
                'blood_group', 'father_name', 'father_job', 'mother_name', 
                'mother_job', 'siblings', 'highest_degree', 'institute', 
                'graduation_year', 'hobbies', 'interests', 'favorite_food',
                'job', 'company', 'location', 'income', 'email', 'phone', 'address'];

    $missing = [];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        throw new Exception("Missing required fields: " . implode(', ', $missing));
    }

    // Validate email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address format.");
    }

    // Validate date format
    if (!DateTime::createFromFormat('Y-m-d', $_POST['dob'])) {
        throw new Exception("Invalid date format. Please use YYYY-MM-DD.");
    }

    // Process file upload
    $profile_pic_path = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $_FILES['profile_pic']['error']);
        }

        // Verify file size
        if ($_FILES['profile_pic']['size'] > MAX_FILE_SIZE) {
            throw new Exception("File too large. Maximum size allowed is " . (MAX_FILE_SIZE / 1024 / 1024) . "MB.");
        }

        // Verify file type
        $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, ALLOWED_TYPES)) {
            throw new Exception("Invalid file type. Allowed types: " . implode(', ', ALLOWED_TYPES));
        }

        // Create upload directory if it doesn't exist
        if (!file_exists(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0755, true)) {
                throw new Exception("Failed to create upload directory.");
            }
        }

        // Generate unique filename
        $filename = uniqid() . '.' . $file_ext;
        $destination = UPLOAD_DIR . $filename;

        // Move uploaded file
        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
            throw new Exception("Failed to move uploaded file.");
        }

        $profile_pic_path = $destination;
    }

    // Create database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Prepare SQL statement
    $sql = "INSERT INTO biodata (
        name, dob, gender, marital_status, religion, height, blood_group, profile_pic,
        father_name, father_job, mother_name, mother_job, siblings,
        highest_degree, institute, graduation_year,
        hobbies, interests, favorite_food,
        job, company, location, income,
        email, phone, address
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $bound = $stmt->bind_param(
        "ssssssssssssssssssssssssss", 
        $_POST['name'],
        $_POST['dob'],
        $_POST['gender'],
        $_POST['marital_status'],
        $_POST['religion'],
        $_POST['height'],
        $_POST['blood_group'],
        $profile_pic_path,
        $_POST['father_name'],
        $_POST['father_job'],
        $_POST['mother_name'],
        $_POST['mother_job'],
        $_POST['siblings'],
        $_POST['highest_degree'],
        $_POST['institute'],
        $_POST['graduation_year'],
        $_POST['hobbies'],
        $_POST['interests'],
        $_POST['favorite_food'],
        $_POST['job'],
        $_POST['company'],
        $_POST['location'],
        $_POST['income'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['address']
    );

    if (!$bound) {
        throw new Exception("Bind failed: " . $stmt->error);
    }

    // Execute the statement
    if ($stmt->execute()) {
        // Success message
        echo '<div class="success">';
        echo '<h1>Bio-data Submitted Successfully!</h1>';
        echo '<p>Thank you for submitting your information.</p>';
        
        // Show preview of submitted data (for demo purposes)
        echo '<h3>Your Submission:</h3>';
        echo '<p><strong>Name:</strong> ' . htmlspecialchars($_POST['name']) . '</p>';
        echo '<p><strong>Email:</strong> ' . htmlspecialchars($_POST['email']) . '</p>';
        echo '<p><strong>Phone:</strong> ' . htmlspecialchars($_POST['phone']) . '</p>';
        
        if ($profile_pic_path) {
            echo '<p><strong>Profile Picture:</strong> <img src="' . htmlspecialchars($profile_pic_path) . '" style="max-width: 200px;"></p>';
        }
        
        echo '</div>';
        echo '<a href="form.html" class="btn">Submit Another Form</a>';
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    // Close statement and connection
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // Error message
    echo '<div class="error">';
    echo '<h1>Error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
    echo '<a href="form.html" class="btn">Go Back to Form</a>';
    
    // Log the error
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . "\n", 3, 'error_log.txt');
}

// Output footer
echo htmlFooter();
?>