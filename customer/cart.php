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

// Extract user details
$user_name = $user['user_name'] ?? 'No name available';
$user_address = $user['address'] ?? 'No address available';
$user_phone = $user['phone_number'] ?? 'No phone number available';


// Fetch the cart items for the user, including the station name
$stmt = $conn->prepare("
    SELECT 
        products.product_name, 
        products.description, 
        products.price, 
        products.image, 
        cart.quantity, 
        stations.station_name, 
        products.product_id, 
        products.availability_status
    FROM cart
    INNER JOIN products ON cart.product_id = products.product_id
    INNER JOIN stations ON products.station_id = stations.station_id
    WHERE cart.user_id = ?
");
if (!$stmt) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    exit();
}
$stmt->bind_param("s", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

$cart_items = [];
if ($cart_result && $cart_result->num_rows > 0) {
    while ($row = $cart_result->fetch_assoc()) {
        $cart_items[] = $row;
    }
} else {
    $cart_items = [];
}

// Group items by station name
$grouped_items = [];
foreach ($cart_items as $item) {
    $grouped_items[$item['station_name']][] = $item;
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

// Remove product from cart (AJAX request)
if (isset($_POST['remove_product']) && $_POST['remove_product'] == 'true') {
    if (isset($_POST['product_id'])) {
        $product_id = $_POST['product_id'];
        $user_id = $_SESSION['user_id']; // Assuming user is logged in

        // Prepare the delete query
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);

        // Execute the query
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Product removed from cart.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error removing product from cart.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Product ID is missing.']);
    }
    exit;
}

// Calculate grand total (example, adjust based on your logic)
$grand_total = 0;
foreach ($cart_items as $item) {
    $grand_total += $item['price'] * $item['quantity'];
}

// Group items by station name
$grouped_items = [];
foreach ($cart_items as $item) {
    $grouped_items[$item['station_name']][] = $item;
}

// Fetch the station ID dynamically (e.g., from session, user selection, or a fixed value for testing)
$station_id = 3; // Replace this with dynamic input

// Prepare the SQL query
$sql = "SELECT station_id, station_name, address, latitude, longitude 
        FROM stations 
        WHERE station_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $station_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch station details
if ($result->num_rows > 0) {
    $station = $result->fetch_assoc();
    $station_id = $station['station_id'];
    $station_name = $station['station_name'];
    $station_address = $station['address'];
    $phone_number = "Not Available"; // Add this field to your database if needed
} else {
    die("Station not found!");
}

$stmt->close();
$conn->close();
?>

<?php include 'customernavbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="cart.css" />
    <title>Cart Page</title>
</head>
<body>
    <div class="container">
        <div class="cart-container">
        <div class="cart-header">
    <h2>CART</h2>
    <div class="user-info" onclick="openEditModal()">
    <p><?php echo htmlspecialchars($user_address); ?></p>
    <p><?php echo htmlspecialchars($user_name); ?></p>
    <p><?php echo htmlspecialchars($user_phone); ?></p>
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


        <?php if (count($grouped_items) > 0): ?>
            <table>
                <tbody>
                    <?php foreach ($grouped_items as $station_name => $items): ?>
                        <tr><td colspan="5" class="station-name"><strong><?php echo htmlspecialchars($station_name); ?></strong></td></tr>
                        <?php foreach ($items as $item): 
                            $total_price = $item['price'] * $item['quantity'];
                            $grand_total += $total_price;
                            $image_path = "../owner/" . htmlspecialchars($item['image']);
                        ?>
                        <tr>
    <td class="products">
        <div class="checkbox-row">
            <!-- Checkbox -->
            <input type="checkbox" name="product[<?php echo $item['product_id']; ?>]" value="<?php echo $item['product_id']; ?>"
            id="product-<?php echo $item['product_id']; ?>"
                           <?php if ($item['availability_status'] == 'O') echo 'disabled'; ?> 
                           onchange="updateTotal()">
                </div>
            <!-- Product Image -->
            <?php if (!empty($item['image'])): ?>
                <img src="<?php echo "../owner/" . htmlspecialchars($item['image']); ?>" 
                     alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                     class="product-image" width="80" height="80"/>
            <?php else: ?>
                <p><em>No image available.</em></p>
            <?php endif; ?>

            <!-- Product Name and Description -->
            <div class="product-info">
                <strong><?php echo htmlspecialchars($item['product_name']); ?>(<?php echo nl2br(htmlspecialchars($item['description'])); ?>)</strong><br>
            </div>

           <!-- Quantity Controls -->
           <div class="quantity-container">
    <input type="number" id="quantity-<?php echo htmlspecialchars($item['product_id']); ?>"
           name="quantity[<?php echo htmlspecialchars($item['product_id']); ?>]"
           value="<?php echo $item['quantity']; ?>" min="1" max="99"
           class="quantity-input" onchange="updateTotal()">

    <div class="quantity-btn-group">
        <button type="button" class="quantity-btn decrement" data-product-id="<?php echo htmlspecialchars($item['product_id']); ?>"
                onclick="adjustQuantity(<?php echo $item['product_id']; ?>, -1)">-</button>
        <button type="button" class="quantity-btn increment" data-product-id="<?php echo htmlspecialchars($item['product_id']); ?>"
                onclick="adjustQuantity(<?php echo $item['product_id']; ?>, 1)">+</button>
    </div>
</div>


               <!-- Price -->
               <div class="price-container">
                <span class="price-label">Price: </span>
                <span class="price-value">₱<?php echo number_format($item['price'], 2); ?></span>
            </div>
        </div>

         <!-- Remove Word (Clickable) -->
         <span class="remove-link" onclick="showCustomConfirm(<?php echo htmlspecialchars($item['product_id']); ?>)">Remove</span>
        <div id="custom-confirm-modal" class="modal">
    <div class="modal-content">
        <p>Are you sure you want to remove this item from the cart?</p>
        <div class="modal-buttons">
            <button id="confirm-yes" class="modal-button">Yes</button>
            <button id="confirm-no" class="modal-button cancel">No</button>
        </div>
    </div>
</div>
    </td>
</tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                </tfoot>
            </table>
        <?php else: ?>
            <p>Your cart is empty.</p>
        <?php endif; ?>
        <!-- Buy Now Button -->
         <div class="buy-now-container">
         <div class="Total-Amount">
                        <td colspan="4">Total Amount: <span class="price-value" id="grand-total"> ₱<?php echo number_format($grand_total, 2); ?></td>
            </div>
<form method="POST" action="OrderDetails.php" id="buyNowForm">
    <input type="hidden" name="orderDetails" id="orderDetails" value="">
    <input type="hidden" name="station_id" value="<?php echo htmlspecialchars($station_id); ?>">
    <button type="submit" name="buy_now" class="buy-now" id="buyNowButton" disabled>CHECKOUT</button>
</form>
        </div>
    </div>

    <script>
// Get all checkbox elements
const checkboxes = document.querySelectorAll('input[name="select-product[]"]');

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

      
// Function to adjust quantity (increment or decrement)
function adjustQuantity(productId, change) {
    const quantityInput = document.querySelector(`#quantity-${productId}`);
    let quantity = parseInt(quantityInput.value) || 1;

    quantity += change; // Adjust by the change value (-1 or 1)

    if (quantity < 1) quantity = 1; // Minimum quantity is 1
    if (quantity > 99) quantity = 99; // Maximum quantity is 99

    // Update the input field with the new quantity
    quantityInput.value = quantity;

    // Send the updated quantity to the server via AJAX
    updateQuantityInDatabase(productId, quantity);

    // Update the grand total dynamically
    updateTotal();
}

// Function to update the total price dynamically
function updateTotal() {
    let grandTotal = 0;

    // Loop through all checkboxes and calculate total for selected items
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        if (checkbox.checked) {
            const productRow = checkbox.closest('tr');
            
            // Get the price value and remove the currency symbol and commas
            const priceText = productRow.querySelector('.price-value').textContent.trim();
            const price = parseFloat(priceText.replace('₱', '').replace(/,/g, '')); // Remove currency and commas

            // Get the quantity value
            const quantity = parseInt(productRow.querySelector('.quantity-input').value);

            // Calculate total price for the selected product
            grandTotal += price * quantity;
        }
    });

    // Update the grand total display
    document.querySelector('#grand-total').textContent = '₱' + grandTotal.toFixed(2);
}


// Function to send updated quantity to the server via AJAX
function updateQuantityInDatabase(productId, newQuantity) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'cart.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    // Prepare data for the request
    const data = `update_quantity=true&product_id=${productId}&new_quantity=${newQuantity}`;

    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            const response = JSON.parse(xhr.responseText);
            if (!response.success) {
                alert('Error: ' + response.message); // Show error if update fails
            }
        }
    };

    // Send the request
    xhr.send(data);
}

// Initialize total calculation when the page loads
window.onload = function () {
    // Bind event listeners to checkboxes
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateTotal); // Update total on selection change
    });

    // Calculate total when the page loads
    updateTotal();
};

// Remove product from cart
function removeProduct(productId) {
    if (confirm('Are you sure you want to remove this item from the cart?')) {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'cart.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        // Prepare data for the request
        const data = `remove_product=true&product_id=${productId}`;

        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Successfully removed the product, update the page
                    alert(response.message);
                    // You may want to remove the product row from the DOM here
                    const productRow = document.querySelector(`#product-${productId}`).closest('tr');
                    if (productRow) productRow.remove();
                    // Recalculate the total after removal
                    updateTotal();
                } else {
                    alert('Error: ' + response.message);
                }
            }
        };

        // Send the request
        xhr.send(data);
    }
}

function showCustomConfirm(productId) {
    const modal = document.getElementById('custom-confirm-modal');
    modal.style.display = 'block'; // Show the modal

    // Handle "Yes" button click
    document.getElementById('confirm-yes').onclick = function () {
        modal.style.display = 'none'; // Hide the modal
        removeProduct(productId); // Call removeProduct function with productId
    };

    // Handle "No" button click
    document.getElementById('confirm-no').onclick = function () {
        modal.style.display = 'none'; // Close the modal without removing the product
    };
}
  // Function to check the state of checkboxes and enable/disable the Buy Now button
  function toggleBuyNowButton() {
    const checkboxes = document.querySelectorAll('input[type="checkbox"]:not([disabled])'); // Only non-disabled checkboxes
    const buyNowButton = document.getElementById('buyNowButton');

    // Check if at least one checkbox is selected
    const isAnyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);

    // Enable or disable the button based on selection
    buyNowButton.disabled = !isAnyChecked;
}

// Add event listeners to checkboxes to toggle the Buy Now button
document.querySelectorAll('input[type="checkbox"]:not([disabled])').forEach(checkbox => {
    checkbox.addEventListener('change', toggleBuyNowButton);
});
</script>
<script>
//proceed to checkout
function proceedToCheckout() {
    const checkboxes = document.querySelectorAll('input[name="select-product[]"]:checked');
    const orderDetails = [];

    // Collect selected products and their quantities
    checkboxes.forEach(checkbox => {
        const productId = checkbox.value;
        const quantity = parseInt(document.querySelector(`#quantity-${productId}`).value) || 1;
        orderDetails.push({ productId, quantity });
    });

    // Check if any products were selected
    if (orderDetails.length === 0) {
        alert("No products selected for checkout.");
        return;
    }

    // Get additional required parameters
    const stationId = new URLSearchParams(window.location.search).get('station_id'); // Ensure station_id is passed
    const userId = sessionStorage.getItem('user_id'); // Example: Get user ID from session storage

    // Prepare the data to send to the server
    const checkoutData = {
        orderDetails: orderDetails,
        stationId: stationId,
        userId: userId
    };

    // Send the order details to the server
    fetch('OrderDetails.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(checkoutData) // Send all necessary data
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Order placed successfully!");
        } else {
            alert("Error placing order: " + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert("An error occurred while placing the order.");
    });
}

document.querySelector('[name="buy_now"]').addEventListener('click', function(e) {
    const stationId = document.querySelector('input[name="station_id"]').value;
    console.log("Station ID:", stationId); // Log the station ID
});

document.querySelectorAll(".buy-now").forEach(button => {
    button.disabled = false;
});

document.querySelector('.buy-now').addEventListener('click', function(e) {
    const selectedProducts = [];
    const quantityInputs = document.querySelectorAll('.quantity-input');
    

    quantityInputs.forEach(input => {
        const productId = input.id.replace('quantity-', '');
        const quantity = parseInt(input.value) || 1;
        const checkbox = document.querySelector(`#product-${productId}`);

        if (checkbox.checked) {
            selectedProducts.push({ productId: productId, quantity: quantity });
        }
    });

    document.getElementById('orderDetails').value = JSON.stringify(selectedProducts);
});

</script>
<script>
// Function to gather selected product IDs and their quantities
document.querySelector('.buy-now').addEventListener('click', function(e) {
    const selectedProducts = [];
    const quantityInputs = document.querySelectorAll('.quantity-input');

    quantityInputs.forEach(input => {
        const productId = input.id.replace('quantity-', '');
        const quantity = parseInt(input.value) || 1;
        const checkbox = document.querySelector(`#product-${productId}`);

        if (checkbox.checked) {
            selectedProducts.push({ productId: productId, quantity: quantity });
        }
    });

     // Get the station_id from the URL
     const stationId = new URLSearchParams(window.location.search).get('station_id');

    // Check if any products were selected
    if (selectedProducts.length === 0) {
        alert("No products selected! Please select at least one product.");
        e.preventDefault(); // Prevent form submission
    } else {
        // Create an object to send to the server
        const orderDetails = {
            products: selectedProducts,
            station_id: stationId
        };
    } else {
        document.getElementById('orderDetails').value = JSON.stringify(selectedProducts);
    }
});
</script>
</body>
</html>
