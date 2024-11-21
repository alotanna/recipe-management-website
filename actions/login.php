<?php
// Start session first
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to include the config file
try {
    if (!file_exists('../db/config.php')) {
        throw new Exception('Database configuration file not found');
    }
    include '../db/config.php';
} catch (Exception $e) {
    die("Configuration Error: " . $e->getMessage());
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? "Database connection not established"));
}


// Sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitize_input($_POST["email"]);
    $password = sanitize_input($_POST["password"]);
    $errors = array();

    // Email validation
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }

    // Password validation
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (!preg_match("/^(?=.*[A-Z])(?=.*\d{3,})(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/", $password)) {
        $errors['password'] = "Password must meet all requirements.";
    }

// If there are no errors, proceed with login
if (empty($errors)) {
    // Check if user exists in the database
    $stmt = $conn->prepare("SELECT user_id, fname, lname, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hashedPassword = $row['password'];

        // Verify the password
        if (password_verify($password, $hashedPassword)) {
            // Set session variables
            $_SESSION['user_id'] = $row['user_id']; 
            $_SESSION['fname'] = $row['fname'];
            $_SESSION['lname'] = $row['lname'];
            $_SESSION['role'] = $row['role'];

            //echo "Welcome, " . $row['fname'] . " " . $row['lname'] . " (Role: " . $_SESSION['role'] . ")";  // Fixed to display role

            // Redirect to the appropriate dashboard based on the user role
            header("Location: ../view/admin/dashboard.php");
            exit();
        } else {
            $errors['login'] = "Invalid email or password.";
        }
    } else {
        $errors['login'] = "Invalid email or password.";
    }
    $stmt->close();
}

// If there are errors, display them using sequential JavaScript alerts
if (!empty($errors)) {
    echo "<script>";
    echo "let errors = " . json_encode(array_values($errors)) . ";";
    echo "
    let currentError = 0;
    
    function showNextError() {
        if (currentError < errors.length) {
            alert(errors[currentError]);
            currentError++;
            showNextError();
        } else {
            window.history.back();
        }
    }
    
    showNextError();
    ";
    echo "</script>";
    exit();
}
}

$conn->close();
?>