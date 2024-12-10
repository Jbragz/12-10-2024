<?php
// Start session to access order details
session_start();

// Simulate order details (replace with your actual logic)
$orderDetails = isset($_SESSION['orderDetails']) ? $_SESSION['orderDetails'] : [];

// Display order summary and payment options
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
</head>
<body>
    <h1>Confirm Your Payment</h1>
    <p>Order Summary:</p>
    <ul>
        <?php foreach ($orderDetails as $item): ?>
            <li><?php echo htmlspecialchars($item['name']); ?> - $<?php echo number_format($item['price'], 2); ?></li>
        <?php endforeach; ?>
    </ul>
    
    <form action="paymentconfirmation.php" method="POST">
    <!-- Include hidden inputs for order details if needed -->
    <input type="hidden" name="order_id" value="12345">
    <button type="submit" id="confirmCheckout">Proceed to Checkout</button>
</form>
</body>
</html>
