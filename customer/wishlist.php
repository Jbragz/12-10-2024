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
$stmt = $conn->prepare("SELECT user_name, image FROM users WHERE user_id = ? AND user_type = 'C'");
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

// Fetch the wishlist items for the user, including the station details
$stmt = $conn->prepare("
    SELECT 
        products.product_name, 
        products.price, 
        products.image, 
        products.product_id, 
        products.availability_status,
        stations.station_id,
        stations.station_name
    FROM wishlist
    INNER JOIN products ON wishlist.product_id = products.product_id
    INNER JOIN stations ON products.station_id = stations.station_id
    WHERE wishlist.user_id = ?"
);
if (!$stmt) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    exit();
}
$stmt->bind_param("s", $user_id);
$stmt->execute();
$wishlist_result = $stmt->get_result();

// Group products by station
$stations = [];
while ($row = $wishlist_result->fetch_assoc()) {
    $stations[$row['station_name']][] = $row;
}

// Handle POST requests for actions like add_to_cart, remove, and buy_now
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart']) && $_POST['add_to_cart'] === 'true') {
        $product_ids = explode(',', $_POST['product_ids']);
        $quantities = json_decode($_POST['quantities'], true);

        foreach ($product_ids as $product_id) {
            $quantity = isset($quantities[$product_id]) ? $quantities[$product_id] : 1;

            // Insert into the cart table
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
            $stmt->bind_param("ssii", $user_id, $product_id, $quantity, $quantity);
            $stmt->execute();
        }

        echo json_encode(['status' => 'success', 'message' => 'Products added to cart.']);
        exit();
    }

    if (isset($_POST['remove']) && $_POST['remove'] === 'true') {
        $product_ids = explode(',', $_POST['product_ids']);

        foreach ($product_ids as $product_id) {
            $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ss", $user_id, $product_id);
            $stmt->execute();
        }

        echo json_encode(['status' => 'success', 'message' => 'Products removed from wishlist.']);
        exit();
    }

    if (isset($_POST['buy_now']) && $_POST['buy_now'] === 'true') {
        $product_ids = explode(',', $_POST['product_ids']);
        $quantities = json_decode($_POST['quantities'], true);

        foreach ($product_ids as $product_id) {
            $quantity = isset($quantities[$product_id]) ? $quantities[$product_id] : 1;

            // Process purchase (add logic based on your system)
        }

        echo json_encode(['status' => 'success', 'message' => 'Purchase successful.']);
        exit();
    }
}

// Handle AJAX request for updating quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity']) && $_POST['update_quantity'] == 'true') {
    // Log the incoming POST data for debugging
    var_dump($_POST); // Add this line to inspect the data

    // Check if the 'product_id' and 'new_quantity' are set and not empty
    if (isset($_POST['product_id']) && isset($_POST['new_quantity'])) {
        $product_id = (int)$_POST['product_id']; // Product ID
        $new_quantity = (int)$_POST['new_quantity']; // New quantity
        $user_id = $_SESSION['user_id']; // Assuming the user is logged in and their ID is stored in session

        // Ensure the quantity is within the allowed range
        if ($new_quantity < 1) {
            $new_quantity = 1;
        }
        if ($new_quantity > 99) {
            $new_quantity = 99; // Max quantity limit
        }

        // Update the cart in the database
        $updateStmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $updateStmt->bind_param("iii", $new_quantity, $user_id, $product_id);
        $updateStmt->execute();

        // Check if the update was successful
        if ($updateStmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Quantity updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating quantity.']);
        }

        $updateStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing product ID or new quantity.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity']) && $_POST['update_quantity'] === 'true') {
    if (isset($_POST['product_id']) && isset($_POST['new_quantity'])) {
        $product_id = (int)$_POST['product_id'];
        $new_quantity = (int)$_POST['new_quantity'];
        
        // Validate new quantity
        if ($new_quantity < 1) {
            $new_quantity = 1;
        }
        if ($new_quantity > 99) {
            $new_quantity = 99;
        }

        // Update wishlist or cart as required
        $stmt = $conn->prepare("UPDATE wishlist SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iis", $new_quantity, $user_id, $product_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Quantity updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update quantity.']);
        }
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
        exit();
    }
}
?>
<?php include 'customernavbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="wishlist.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>Wishlist Page</title>
</head>

<body>
    <div class="container">
        <div class="wishlist-header">
            <h1>WISHLIST</h1>

            <!-- Remove Button Container aligned with the "Wishlist" title -->
            <div class="remove-btn-container">
                <button id="remove-btn" onclick="removeSelectedProducts()" disabled>Remove</button>
            </div>
        </div>
             <!-- Button Container with Total Price before Add to Cart -->
        <div class="button-container">
    <!-- Total Price Display -->
    <div class="total-price-action-container">
    <div class="total-price-container">
        Total Amount: ₱<span id="total-price">0.00</span>
    </div>

    <!-- Buttons in their own div -->
    <div class="action-buttons">
        <!-- Add to Cart Button -->
        <button class="add-to-cart no-hover" id="add-to-cart-btn" onclick="addToCart()" disabled>
            ADD TO CART
        </button>

        <!-- Buy Now Button -->
        <button class="buy-now no-hover" id="buy-now-btn" onclick="buyNow()" disabled>
            BUY NOW
        </button>
    </div>
</div>
</div>
        <div class="product-container">
        <div class="wishlist-item">
        <div class="wishlist-item-container">
        <?php if (!empty($stations)): ?>
            <?php foreach ($stations as $station_name => $products): ?>
                <h2 class="station-name"><?php echo htmlspecialchars($station_name); ?></h2> <!-- Display Station Name -->
                <div class="wishlist-list">
                    <?php foreach ($products as $item): 
                        $image_path = "../owner/" . htmlspecialchars($item['image']);
                    ?>
                        <!-- Wrapping product image, details, and checkbox inside wishlist-item-box -->
                        <div class="wishlist-item-box">
    <div class="wishlist-item">
        <!-- Product Image -->
        <div class="wishlist-image">
            <?php if (!empty($item['image'])): ?>
                <img src="<?php echo $image_path; ?>" 
                     alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                     class="product-image" width="150" height="150"/>
            <?php else: ?>
                <p><em>No image available.</em></p>
            <?php endif; ?>
        </div>

        <!-- Product Details -->
        <div class="wishlist-details">
            <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
            <strong>
                Price: <span style="color: green;">₱<?php echo number_format($item['price'], 2); ?></span>
            </strong>

            <!-- Quantity input with Increment and Decrement buttons -->
            <div class="quantity-container">
                <div class="quantity-controls">
                    <label for="quantity-<?php echo htmlspecialchars($item['product_id']); ?>">Quantity:</label>
                    <input type="number" id="quantity-<?php echo htmlspecialchars($item['product_id']); ?>" 
                           name="quantity[<?php echo htmlspecialchars($item['product_id']); ?>]" 
                           class="quantity-input" value="1" min="1" max="99"
                           onchange="updateQuantity(<?php echo $item['product_id']; ?>)">
                    <button type="button" class="quantity-btn decrement" 
                            data-product-id="<?php echo htmlspecialchars($item['product_id']); ?>"
                            onclick="decrementQuantity(<?php echo $item['product_id']; ?>)" 
                            <?php if ($item['availability_status'] == 'O') echo 'disabled'; ?>>−</button>
                    <button type="button" class="quantity-btn increment" 
                            data-product-id="<?php echo htmlspecialchars($item['product_id']); ?>"
                            onclick="incrementQuantity(<?php echo $item['product_id']; ?>)" 
                            <?php if ($item['availability_status'] == 'O') echo 'disabled'; ?>>+</button>
                </div>
            </div>
            
            <!-- Checkbox for Selection -->
            <label>
                <input type="checkbox" class="product-checkbox" value="<?php echo $item['price']; ?>" 
                       data-id="<?php echo $item['product_id']; ?>" onchange="updateTotal()">
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Your wishlist is empty.</p>
        <?php endif; ?>
        </div>
<script>
       function updateTotal() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    let totalPrice = 0;

    checkboxes.forEach(function(checkbox) {
        const productId = checkbox.getAttribute('data-id');
        const quantity = document.getElementById(`quantity-${productId}`).value;
        const price = parseFloat(checkbox.value);

        // Multiply the price by the quantity and add it to the total price
        totalPrice += price * quantity;
    });

    document.getElementById('total-price').textContent = totalPrice.toFixed(2);

    // Enable the "Add to Cart" button if at least one checkbox is selected
    document.getElementById('add-to-cart-btn').disabled = checkboxes.length === 0;
    document.getElementById('buy-now-btn').disabled = checkboxes.length === 0;
    document.getElementById('remove-btn').disabled = checkboxes.length === 0;
}
function showNotification(type, title, message) {
    Swal.fire({
        icon: type, // 'success', 'error', 'warning', 'info', 'question'
        title: title,
        text: message,
        confirmButtonColor: '#3085d6',
        confirmButtonText: 'OK'
    });
}

function addToCart() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const productIds = [];
    const quantities = {};

    checkboxes.forEach(function(checkbox) {
        const productId = checkbox.getAttribute('data-id');
        const quantity = document.getElementById(`quantity-${productId}`).value;

        productIds.push(productId);
        quantities[productId] = quantity;
    });

    if (productIds.length > 0) {
        sendRequest('add_to_cart=true', productIds, quantities, 'Products added to cart!');
    }
}

function removeSelectedProducts() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const productIds = [];
    checkboxes.forEach(function(checkbox) {
        const productId = checkbox.getAttribute('data-id');
        productIds.push(productId);
    });

    if (productIds.length > 0) {
        sendRequest('remove=true', productIds, null, 'Products removed from wishlist.');
    }
}

function buyNow() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const productIds = [];
    const quantities = {};

    checkboxes.forEach(function(checkbox) {
        const productId = checkbox.getAttribute('data-id');
        const quantity = document.getElementById(`quantity-${productId}`).value;

        productIds.push(productId);
        quantities[productId] = quantity;
    });

    if (productIds.length > 0) {
        sendRequest('buy_now=true', productIds, quantities, 'Purchase successful!');
    }
}

function sendRequest(action, productIds, quantities, successMessage) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'wishlist.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
        const response = JSON.parse(xhr.responseText);
        if (response.status === 'success') {
            showNotification('success', 'Success', successMessage);
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('error', 'Error', response.message);
        }
    };

    const payload = action + '&product_ids=' + productIds.join(',') + (quantities ? '&quantities=' + JSON.stringify(quantities) : '');
    xhr.send(payload);
}

        // Increment and Decrement functions for quantity
function incrementQuantity(productId) {
    const input = document.getElementById(`quantity-${productId}`);
    const max = parseInt(input.max, 10);
    if (input.value < max) {
        input.value = parseInt(input.value, 10) + 1;
        updateQuantity(productId);  // Update the quantity on the server
        updateTotal();  // Recalculate the total price
    }
}

function decrementQuantity(productId) {
    const input = document.getElementById(`quantity-${productId}`);
    const min = parseInt(input.min, 10);
    if (input.value > min) {
        input.value = parseInt(input.value, 10) - 1;
        updateQuantity(productId);  // Update the quantity on the server
        updateTotal();  // Recalculate the total price
    }
}
        function updateQuantity(productId) {
            const input = document.getElementById(`quantity-${productId}`);
            const newQuantity = parseInt(input.value, 10);

            // Send the new quantity to the server via AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'wishlist.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function() {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert(response.message); // Optional: Provide feedback
                    updateTotal(); // Recalculate the total price
                } else {
                    alert('Error updating quantity: ' + response.message);
                }
            };

            xhr.send(`update_quantity=true&product_id=${productId}&new_quantity=${newQuantity}`);
        }
    </script>
</body>
</html>