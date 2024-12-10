<?php
// Start the session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('dwos.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the logged-in customer's user_id
if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}
$user_id = $_SESSION['user_id'];

// Fetch Customer Details from the database
$stmt = $conn->prepare("SELECT user_name, image, address, phone_number FROM users WHERE user_id = ? AND user_type = 'C'");
if (!$stmt) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    exit();
}
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result && $user_result->num_rows > 0) {
    $user = $user_result->fetch_assoc();
} else {
    echo "Error fetching customer details: " . $conn->error;
    exit();
}

// Ensure station and date are passed
if (!isset($_GET['station']) || !isset($_GET['date'])) {
    die("Invalid tracking request.");
}

$station_name = urldecode($_GET['station']);
$order_date = urldecode($_GET['date']);

$product_details = [];

if (isset($_GET['products'])) {
    $product_ids = explode(',', $_GET['products']); // Convert back to an array
    $placeholders = implode(',', array_fill(0, count($product_ids), '?')); // Generate placeholders for SQL
    $sql = "SELECT o.order_id, o.tracking_number, p.product_name, p.description, p.image, p.price, o.quantity, 
            o.total_price, o.order_status, p.product_type, p.item_stock, o.payment_method
        FROM orders o
        JOIN products p ON o.product_id = p.product_id
        WHERE o.order_id IN ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids); // Bind all product IDs
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch results into an array for display
    while ($row = $result->fetch_assoc()) {
        $product_details[] = $row;
    }
    $stmt->close();
} 

// Assuming you have a database connection named $conn in dbconnect.php
// Adjust the table and column names based on your actual database schema
$query = "SELECT order_status FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($order_status);
$stmt->fetch();
$stmt->close();
?>

<?php include 'customernavbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking</title>
    <link rel="stylesheet" href="ordertracking.css" />
</head>
<body>
    <div class="tracking-container">
    <div class="tracking-header">
    <h1>Track Order</h1>
    <p><strong><span class="station-name"><?= htmlspecialchars($station_name); ?></p></strong></span>
    <p><strong><?= htmlspecialchars($order_date); ?></p></strong>
    <p><span class="tracking-number"><?= htmlspecialchars($product_details[0]['tracking_number'] ?? 'N/A'); ?></p></span>
</div>

<section class="step-wizard">
        <ul class="step-wizard-list">
            <li class="step-wizard-item <?php echo ($order_status == 'P') ? 'current-item' : ''; ?>">
                <span class="progress-count">1</span>
                <span class="progress-label">Pending</span>
            </li>
            <li class="step-wizard-item <?php echo ($order_status == 'A') ? 'current-item' : ''; ?>">
                <span class="progress-count">2</span>
                <span class="progress-label">Accepted</span>
            </li>
            <li class="step-wizard-item <?php echo ($order_status == 'F') ? 'current-item' : ''; ?>">
                <span class="progress-count">3</span>
                <span class="progress-label">For Pickup</span>
            </li>
            <li class="step-wizard-item <?php echo ($order_status == 'Q') ? 'current-item' : ''; ?>">
                <span class="progress-count">4</span>
                <span class="progress-label">Processing</span>
            </li>
            <li class="step-wizard-item <?php echo ($order_status == 'S') ? 'current-item' : ''; ?>">
                <span class="progress-count">5</span>
                <span class="progress-label">Shipping</span>
            </li>
            <li class="step-wizard-item <?php echo ($order_status == 'D') ? 'current-item' : ''; ?>">
                <span class="progress-count">6</span>
                <span class="progress-label">Delivered</span>
            </li>
        </ul>
    </section>

    <div class="tracking-item-container">
        <div class="tracking-item-list">
    <?php if (!empty($product_details)): ?>
        <?php foreach ($product_details as $product): ?>
            <div class="tracking-item">
                <img src="../owner/<?= htmlspecialchars($product['image']); ?>" alt="<?= htmlspecialchars($product['product_name']); ?>" class="product-image" />
                <div class="product-details">
                    <p><strong><?= htmlspecialchars($product['product_name']); ?></strong> (<strong><?= htmlspecialchars($product['description']); ?></strong>)</p>
                    <p><strong><span class="price">Price:</span></strong><span class="price-value"> ₱<?= number_format($product['price'], 2); ?></p></span>
                    <p><strong>Quantity: </strong><strong> <?= $product['quantity']; ?></p></strong>
                    <p><strong><span class="product-type"><?= $product['product_type'] === 'R' ? 'Refill' : 'Item'; ?></p></strong></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No orders found for this station and date.</p>
    <?php endif; ?>
    </div>
    <div class="payments">
    <p><strong>Payment Method:</strong><span class="payment-method"> <?= htmlspecialchars($product_details[0]['payment_method'] ?? 'N/A'); ?></p></span>
    <p><strong>Total Price:</strong><span class="total-price-value"> ₱<?= number_format($product['total_price'], 2); ?></p></span>
    </div>
</div>
<button onclick="window.location.href='../customer/customerorders.php'" class="done">Done</button>
</body>
</html>
