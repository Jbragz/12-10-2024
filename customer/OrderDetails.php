<?php
$station_id = isset($_POST['station_id']) ? $_POST['station_id'] : null;

if ($station_id === null) {
    echo "Station ID is missing.";
    exit();
}
// Start the session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('dwos.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch station details (if station_id is available)
$station_id = $_POST['station_id'] ?? $_GET['station_id'] ?? null;

// Generate a unique tracking number
$tracking_number = uniqid('TRACK-', true); // e.g., TRACK-5f2e4b1e2c3a7.12345678

// Get the station_id from the POST request
$station_id = isset($_POST['station_id']) ? $_POST['station_id'] : null;

// Ensure to validate and sanitize the station_id as needed
if ($station_id === null) {
    // Handle the error accordingly
    echo "Station ID is missing.";
    exit();
}

if (isset($_SESSION['selected_products'])) {
    $selectedProducts = $_SESSION['selected_products'];
    foreach ($selectedProducts as $productId) {
        echo "Selected Product ID: " . htmlspecialchars($productId) . "<br>";
    }
}

// Get the logged-in owner's user_id
$user_id = $_SESSION['user_id'];

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch data from the POST request
$orderDetails = isset($_POST['orderDetails']) ? $_POST['orderDetails'] : null;
$stationId = isset($_POST['station_id']) ? $_POST['station_id'] : null;
$stationName = isset($_POST['station_name']) ? $_POST['station_name'] : null;
$stationAddress = isset($_POST['station_address']) ? $_POST['station_address'] : null;
$stationPhone = isset($_POST['station_phone']) ? $_POST['station_phone'] : null;

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

// Extract user details
$user_name = $user['user_name'] ?? 'No name available';
$user_address = $user['address'] ?? 'No address available';
$user_phone = $user['phone_number'] ?? 'No phone number available';

// Initialize variables for order details
$orderDetails = [];
$totalPrice = 0;

// Check if orderDetails is set and not empty
if (isset($_POST['orderDetails']) && !empty($_POST['orderDetails'])) {
    $orderDetails = json_decode($_POST['orderDetails'], true);
}

$stmt = $conn->prepare("
    SELECT p.product_name, p.description, p.price, p.image, s.station_name 
    FROM products p 
    JOIN stations s ON p.station_id = s.station_id 
    WHERE p.product_id = ?
");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

// Check if any result was returned
if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
} else {
    $product = null; // Handle case where no product is found
}

// Initialize variables
$station_name = "Unknown Station";
$station_address = ""; // Initialize address
$products = [];



// Check if station_id is provided in the URL
if (isset($_GET['station_id'])) {
    $station_id = $_GET['station_id'];
    echo "Station ID: " . $station_id; // This will help you see if the station ID is passed correctly
} else { // If there's no station ID in the URL, this message will show

    // Fetch station name and address from the stations table
$stationStmt = $conn->prepare("SELECT s.station_name, u.address AS station_address, u.phone_number 
FROM stations s
JOIN users u ON s.owner_id = u.user_id
WHERE s.station_id = ?");
$stationStmt->bind_param("i", $station_id);
$stationStmt->execute();
$stationResult = $stationStmt->get_result();

if ($stationResult->num_rows > 0) {
$station = $stationResult->fetch_assoc();
$station_name = $station['station_name'];
$station_address = $station['station_address'];
$phone_number = $station['phone_number'];
}

// Fetch the shipping fee for the selected station
$shippingFeeStmt = $conn->prepare("SELECT shipping_fee FROM shipping_fees WHERE station_id = ?");
$shippingFeeStmt->bind_param("i", $station_id);
$shippingFeeStmt->execute();
$shippingFeeResult = $shippingFeeStmt->get_result();

$shipping_fee = 0; // Default shipping fee
if ($shippingFeeResult->num_rows > 0) {
$shipping_fee_row = $shippingFeeResult->fetch_assoc();
$shipping_fee = $shipping_fee_row['shipping_fee'];
}

// Initialize total price
$totalPrice = 0;

// ... (existing code for calculating total price)

// Update total price to include shipping fee
$totalPrice += $shipping_fee; // Include shipping fee in total
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? '';
    $product_name = $_POST['product_name'] ?? '';
    $product_price = $_POST['product_price'] ?? '';
} elseif (isset($_GET['product_id'])) {
    $product_id = $_GET['product_id'];
    $product_name = $_GET['product_name'];
    $product_price = $_GET['product_price'];
} 

// Close the database connection
$conn->close();
?>

<?php include 'customernavbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="OrderDetails.css" />
    <title>Order Details</title>
</head>
<body>
<div class="container">
<div class="order-container">
    <div class="order-header">
    <h2>Order Details</h2>
    <div class="user-info" onclick="openEditModal()">
    <p><?php echo htmlspecialchars($user_address); ?></p>
    <p><?php echo htmlspecialchars($user_name); ?></p>
    <p>63+ <?php echo htmlspecialchars($user_phone); ?></p>
</div>
</div>
<!-- Edit Modal -->
<div id="edit-modal" class="modal">
    <div class="modal-container">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
            <h2 class="modal-title">EDIT ADDRESS</h2>
            <form id="edit-form" method="post" action="update_user_details.php">
    <div class="form-group-container">
        <!-- User Info Section -->
        <div class="form-group-section form-group-user-info">
            <label class="username-title">Contact Information</label>
            <!-- User Name -->
            <div class="form-group-username">
                <label for="new-user-name">Name:</label>
                <input type="text" id="new-user-name" name="new_user_name" 
                    value="<?php echo htmlspecialchars($user_name); ?>" required />
            </div>

            <!-- Phone Number -->
            <div class="form-group-phonenum">
                <label for="new-phone">Phone Number:</label>
                <input type="tel" id="new-phone" name="new_phone" 
                    value="<?php echo htmlspecialchars($user_phone); ?>" placeholder="Enter new phone number" 
                    pattern="[0-9]{10}" title="Enter a 10-digit phone number" required />
            </div>
        </div>

        <!-- Address Section -->
        <div class="form-group-section form-group-address-container">
            <label class="address-title">Address Information</label>
            <div class="form-group-address">
                <label for="new-address">Provide the complete Address(Street, Purok, Municipality,Region).</label>
                <textarea id="new-address" name="new_address" required><?php echo htmlspecialchars($user_address); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Save Button -->
    <div class="form-group">
        <button type="submit" class="save-btn">Done</button>
    </div>
</form>
        </div>
    </div>
</div>

    <?php if (!empty($orderDetails)): ?>
        <div id="order-details" class="order-details">
            <div class="station-info">
        <h1  class="station-name"><?php echo htmlspecialchars($station_name); ?></h1>
        <p class="station-address"><strong><i class="ri-map-pin-fill"></i></strong><?php echo htmlspecialchars($station_address); ?></p>
        <p class="station-phonenum"><strong><i class="ri-phone-fill"></i></strong><?php echo htmlspecialchars($phone_number); ?></p>
    </div>

    
    <?php foreach ($orderDetails as $item): ?>
    <?php
    $productId = $item['productId'];
    $quantity = $item['quantity'];

    // Fetch product details from the database
    $stmt = $conn->prepare("SELECT product_name, description, price, image FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if ($product) {
        $totalPrice += $product['price'] * $quantity; // Calculate total price
    ?>
    <div class="order-detail-wrapper" id="product-<?php echo $productId; ?>">
        <?php if ($product): ?>
            <?php 
            // Construct the image path
            $image_path = "../owner/" . htmlspecialchars($product['image']); 
            ?>
            
            <!-- Product Image -->
            <?php if (!empty($product['image'])): ?>
                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-image" />
            <?php else: ?>
                <p><em>No image available.</em></p>
            <?php endif; ?>
            
            <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
            <p><?php echo htmlspecialchars($product['description']); ?></p>
            <p>Quantity: 
            <button type="button" onclick="decrementQuantity(<?php echo $productId; ?>)">-</button>
            <input type="number" id="quantity-<?php echo $productId; ?>" value="<?php echo $quantity; ?>" min="1" max="99" class="quantity-input">
            <button type="button" onclick="incrementQuantity(<?php echo $productId; ?>)">+</button>
        </p>
        <p class="price-value">₱<?php echo number_format($product['price'], 2); ?></p>
    <?php else: ?>
            <p><em>Product not found.</em></p> <!-- Handle case where product is not found -->
        <?php endif; }?>
    
    </div>
<?php endforeach; ?>

        </div>
    <?php else: ?>
        <p>No products selected!</p>
    <?php endif; ?>

    <!-- Display Total Price -->
    <div id="overall-total">
    <div id="shipping-fee">
    <p><strong>Shipping Fee:</strong> ₱<?php echo number_format($shipping_fee, 2); ?></p>
        <p><strong>Total Price:</strong> ₱<?php echo number_format($totalPrice, 2); ?></p>
    </div>
    </div>
    
<!-- Buy Now Button -->
<div class="buy-now-container">
    <form action="checkout.php" method="post">
        <input type="hidden" name="station_id" value="<?php echo htmlspecialchars($station_id); ?>">
        <input type="hidden" name="quantity" value="<?php echo $quantity; ?>" min="1" max="99">
        <input type="hidden" name="orderDetails" value='<?php echo htmlspecialchars(json_encode($orderDetails)); ?>'>
        <input type="hidden" name="tracking_number" value="<?php echo htmlspecialchars($tracking_number); ?>">
        <input type="hidden" name="shipping_fee" value="<?php echo htmlspecialchars($shipping_fee); ?>">
        <button type="submit" class="buy-now-btn">Place Order</button>
    </form>
</div>
</div>

<!-- Include JavaScript for Quantity & Total Price Update -->
<script>
// Function to open edit address modal
function openEditModal() {
    document.getElementById('edit-modal').style.display = 'block';
}

// Function to close the modal
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('edit-modal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
};

function incrementQuantity(productId) {
    const quantityInput = document.querySelector(`#quantity-${productId}`);
    let quantity = parseInt(quantityInput.value) || 1;
    if (quantity < 99) {
        quantityInput.value = quantity + 1;
        updateOrderDetails(productId, quantity + 1); // Update the quantity in the order details
    }
    updateTotal();  // Refresh the total price
}

function decrementQuantity(productId) {
    const quantityInput = document.querySelector(`#quantity-${productId}`);
    let quantity = parseInt(quantityInput.value) || 1;
    if (quantity > 1) {
        quantityInput.value = quantity - 1;
        updateOrderDetails(productId, quantity - 1); // Update the quantity in the order details
    }
    updateTotal();  // Refresh the total price
}

function onQuantityChange(productId) {
    const quantityInput = document.querySelector(`#quantity-${productId}`);
    let quantity = parseInt(quantityInput.value) || 1;
    if (quantity < 1) {
        quantity = 1;
        quantityInput.value = quantity; // Reset to minimum if invalid input
    } else if (quantity > 99) {
        quantity = 99;
        quantityInput.value = quantity; // Reset to maximum if exceeded
    }
    updateOrderDetails(productId, quantity); // Update the quantity in the order details
    updateTotal();  // Refresh the total price
}

function updateOrderDetails(productId, newQuantity) {
    // Parse the current orderDetails stored in the hidden input
    const orderDetailsInput = document.querySelector('input[name="orderDetails"]');
    let orderDetails = JSON.parse(orderDetailsInput.value || '[]');

    // Update the quantity for the specific productId
    const productIndex = orderDetails.findIndex(item => item.productId === productId);
    if (productIndex > -1) {
        orderDetails[productIndex].quantity = newQuantity; // Update the quantity
    } else {
        orderDetails.push({ productId, quantity: newQuantity }); // Add new product if not already present
    }

    // Update the hidden input value with the modified order details
    orderDetailsInput.value = JSON.stringify(orderDetails);
    console.log("Updated Order Details:", orderDetails);
}

function updateTotal() {
    let totalPrice = 0;
    const quantityInputs = document.querySelectorAll('.quantity-input');

    // Loop through all quantity inputs and calculate the total price
    quantityInputs.forEach(input => {
        const productId = input.id.replace('quantity-', '');
        const quantity = parseInt(input.value) || 1;

        // Get the price for the specific product
        const priceElement = document.querySelector(`#product-${productId} .price-value`);
        const price = parseFloat(priceElement.textContent.replace('₱', '').replace(',', '').trim());

        // Calculate total price for products
        totalPrice += quantity * price;
    });

    // Get the shipping fee from the displayed element
    const shippingFeeElement = document.querySelector('#shipping-fee p strong');
    const shippingFee = parseFloat(shippingFeeElement.nextSibling.nodeValue.replace('₱', '').replace(',', '').trim()) || 0;

    // Add the shipping fee to the total price
    totalPrice += shippingFee;

    // Update the overall total in the DOM
    document.querySelector('#overall-total').innerHTML = `
        <p><strong>Shipping Fee:</strong> ₱${shippingFee.toFixed(2)}</p>
        <p><strong>Total Price:</strong> ₱${totalPrice.toFixed(2)}</p>
    `;
    console.log(`Final Total Price: ₱${totalPrice.toFixed(2)}`);
}

// Ensure event listeners are added for quantity inputs
document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('change', () => onQuantityChange(input.id.replace('quantity-', '')));
});

</script>

</body>
</html>
