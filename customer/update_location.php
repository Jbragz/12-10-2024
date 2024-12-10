<?php
// update_location.php
session_start();
include('dwos.php'); // Include your database connection

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $data['userId'];
    $latitude = $data['latitude'];
    $longitude = $data['longitude'];

    // Prepare and execute the SQL statement to update the user's location
    $stmt = $conn->prepare("UPDATE users SET latitude = ?, longitude = ? WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ddi", $latitude, $longitude, $userId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No changes made.']);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
