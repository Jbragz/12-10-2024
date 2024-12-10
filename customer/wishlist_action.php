<?php
session_start();
include('dwos.php'); // Include your database connection file

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the necessary data from POST
    $user_id = $_SESSION['user_id']; // Assuming user_id is stored in the session
    $product_id = $_POST['product_id'];
    $station_id = $_POST['station_id'];
    $action = $_POST['action']; // The action should be 'toggle_wishlist'

    // Prepare SQL to check if the item is already in the wishlist
    $checkSql = "SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ? AND station_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('iii', $user_id, $product_id, $station_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($action == 'toggle_wishlist') {
        if ($checkStmt->num_rows === 0) {
            // Item is not in the wishlist, so we add it
            $insertSql = "INSERT INTO wishlist (user_id, product_id, station_id) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param('iii', $user_id, $product_id, $station_id);
            if ($insertStmt->execute()) {
                echo "added"; // Respond with 'added' to notify success
            } else {
                echo "Error adding product to wishlist.";
            }
            $insertStmt->close();
        } else {
            // Item is in the wishlist, so we remove it
            $deleteSql = "DELETE FROM wishlist WHERE user_id = ? AND product_id = ? AND station_id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param('iii', $user_id, $product_id, $station_id);
            if ($deleteStmt->execute()) {
                echo "removed"; // Respond with 'removed' to notify success
            } else {
                echo "Error removing product from wishlist.";
            }
            $deleteStmt->close();
        }
    } else {
        echo "Invalid action.";
    }

    $checkStmt->close();
    $conn->close();
} else {
    echo "Invalid request method.";
}
?>