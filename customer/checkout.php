<?php
session_start();
include('dwos.php'); // Include your database connection

date_default_timezone_set('Asia/Manila'); // Set the timezone

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $station_id = $_POST['station_id'] ?? null; // Get station ID from the incoming data
    $orderDetails = isset($_POST['orderDetails']) ? json_decode($_POST['orderDetails'], true) : null; // Decode order details

    // Check if orderDetails is valid
    if (!is_array($orderDetails) || empty($orderDetails)) {
        echo json_encode(['success' => false, 'message' => 'Order details are missing or invalid.']);
        exit;
    }

    $total_price = 0;

    // Function to generate unique track number
    function generateTrackNumber($conn) {
        do {
            $track_number = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 12);
            $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM orders WHERE tracking_number = ?");
            $stmt->bind_param("s", $track_number);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
        } while ($data['count'] > 0);

        return $track_number;
    }

    // Generate a unique track number for the entire order
    $track_number = generateTrackNumber($conn);

    // Debug log
    error_log("Generated Tracking Number: $track_number");

    // Get the shipping fee from the incoming data
    $shipping_fee = $_POST['shipping_fee'] ?? 0; // Get shipping fee

    foreach ($orderDetails as $item) {
        $product_id = $item['productId'] ?? null;
        $quantity = $item['quantity'] ?? 0;

        // Fetch product price
        $stmt = $conn->prepare("SELECT price FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            $price = $product['price'];
            $item_total = $price * $quantity; // Double-check these values
            $total_price += $item_total;
 // Accumulate the total price for the order

        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found: ' . $product_id]);
            exit;
        }
    }

    // Add shipping fee to the total price
    $total_price += $shipping_fee; // Include shipping fee in total price

    // Insert the order into the orders table
    $insertStmt = $conn->prepare("INSERT INTO orders (user_id, station_id, product_id, quantity, total_price, shipping_fee, tracking_number, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

    // Loop through order details again to insert each item
    foreach ($orderDetails as $item) {
        $product_id = $item['productId'] ?? null;
        $quantity = $item['quantity'] ?? 0;

        // Insert each order item into the database
        $insertStmt->bind_param("iiidsss", $user_id, $station_id, $product_id, $quantity, $total_price, $shipping_fee, $track_number); // Include shipping fee in the binding

        // Debug log before insertion
        error_log("Inserting order with tracking number: $track_number");

        if (!$insertStmt->execute()) {
            error_log("SQL Error: " . $insertStmt->error); // Log SQL error
            echo json_encode(['success' => false, 'message' => 'Error inserting order: ' . $insertStmt->error]);
            exit;
        }
    }

      // Redirect to products.php after successful checkout
      header("Location: ./products.php?station_id=" . urlencode($station_id) . "&status=success");
      exit; // Ensure no further code is executed after the redirect
  } else {
      echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
