<?php
session_start();
include('dwos.php'); // Database connection

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $new_user_name = htmlspecialchars(trim($_POST['new_user_name']));
    $new_address = htmlspecialchars(trim($_POST['new_address']));
    $new_phone = htmlspecialchars(trim($_POST['new_phone']));

    // Update user details in the database
    $stmt = $conn->prepare("UPDATE users SET user_name = ?, address = ?, phone_number = ? WHERE user_id = ?");
    $stmt->bind_param('sssi', $new_user_name, $new_address, $new_phone, $user_id);

    if ($stmt->execute()) {
        header('Location: cart.php'); // Redirect back to cart page
        exit();
    } else {
        echo "Error updating details: " . $conn->error;
    }

    $stmt->close();
}
?>
