<?php
$station_id = isset($_GET['station_id']) ? $_GET['station_id'] : null;

if ($station_id === null) {
    echo "Station ID is missing.";
    exit();
}
// Start session if not started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the database connection
include('dwos.php'); // Ensure this path is correct

// Ensure the connection variable ($conn) is correctly used
if (!$conn) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get the logged-in customer's user_id
$user_id = $_SESSION['user_id'];

// Fetch Customer Details from the database using prepared statements
$stmt = $conn->prepare("SELECT user_name, image, password FROM users WHERE user_id = ? AND user_type = 'C'");
if (!$stmt) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    exit();
}
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    echo "Error fetching customer details: " . $conn->error; 
    exit();
}

// Initialize variables
$station_name = "Unknown Station";
$station_address = ""; // Initialize address
$products = [];

// Check if station_id is provided in the URL
if (isset($_GET['station_id'])) {
    $station_id = $_GET['station_id'];

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
        $station_address = $station['station_address']; // Store the station owner's address
        $phone_number = $station['phone_number']; // Store the phone number    }
    $stationStmt->close();
    }

    // Fetch products related to the selected station
    $stmt = $conn->prepare("SELECT product_id, product_name, description, price, image, availability_status 
                            FROM products WHERE station_id = ?");
    $stmt->bind_param("i", $station_id); // Use station_id to filter products
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $stmt->close();
} else {
    echo "Station ID not provided.";
}

// Initialize cart if not already done
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_POST['add_to_cart'])) {
    // Handle Add to Cart logic here
} elseif (isset($_POST['buy_now'])) {
    // Handle Buy Now logic here (redirect or process order)
}


// Handle form submission for adding to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_products = $_POST['select-product'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    foreach ($selected_products as $product_id) {
        $quantity = isset($quantities[$product_id]) ? (int)$quantities[$product_id] : 1;

        // Ensure the quantity is within the allowed range
        if ($quantity > 99) {
            $quantity = 99; // Limit to a maximum of 99
        }

        // Check if the product already exists in the cart for the current user and station
        $checkStmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? AND station_id = ?");
        if ($checkStmt) {
            $checkStmt->bind_param("iii", $user_id, $product_id, $station_id);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                // Product already exists, update the quantity
                $checkStmt->bind_result($existing_quantity);
                $checkStmt->fetch();
                $new_quantity = min($existing_quantity + $quantity, 99); // Ensure the total doesn't exceed 99

                if ($new_quantity == 99) {
                    // Notify the user if quantity reaches the maximum
                    echo "<script>alert('Product quantity has reached the maximum limit of 99 in your cart.');</script>";
                }

                $updateStmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ? AND station_id = ?");
                $updateStmt->bind_param("iiii", $new_quantity, $user_id, $product_id, $station_id);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                // Product does not exist, insert a new record
                $insertStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, station_id) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param("iiii", $user_id, $product_id, $quantity, $station_id);
                $insertStmt->execute();
                $insertStmt->close();
            }
            $checkStmt->close();

            // Set a success flag to trigger the popup
            $_SESSION['success_message'] = 'Item added to cart successfully!';
        } else {
            echo "Error checking cart: " . $conn->error;
        }
    }
}

// heart-icon insert on wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_wishlist') {
    $user_id = $_SESSION['user_id']; // Assumes user_id is stored in session
    $product_id = $_POST['product_id'];
    $station_id = $_POST['station_id'];

    if (!empty($user_id) && !empty($product_id) && !empty($station_id)) {
        // Check if the product already exists in the wishlist
        $checkSql = "SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ? AND station_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('iii', $user_id, $product_id, $station_id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            // Product is already in the wishlist, do not insert
            echo "Product already in wishlist.";
        } else {
            // Insert the product into the wishlist
            $sql = "INSERT INTO wishlist (user_id, product_id, station_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iii', $user_id, $product_id, $station_id);

            if ($stmt->execute()) {
                echo "success";
            } else {
                echo "Database error: " . $stmt->error;
            }
            $stmt->close();
        }

        $checkStmt->close();
    } else {
        echo "Invalid data.";
    }
    exit; // Prevents further HTML output
}

// Fetch wishlist data for the logged-in user
$wishlist = [];
$wishlistStmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
$wishlistStmt->bind_param("i", $user_id);
$wishlistStmt->execute();
$wishlistResult = $wishlistStmt->get_result();

while ($row = $wishlistResult->fetch_assoc()) {
    $wishlist[] = $row['product_id'];
}
$wishlistStmt->close();


// Check for the status query parameter
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    echo "<script>
        window.onload = function() {
            alert('Placed Order Successfully');
        }
    </script>";
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
    <link rel="stylesheet" href="products.css" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <title>Products for Station: <?php echo htmlspecialchars($station_name); ?></title>
</head>
<body>
<div class="container">
    <h1><?php echo htmlspecialchars($station_name); ?></h1>
    <p class="address"><strong><i class="ri-map-pin-fill"></i></strong> <?php echo htmlspecialchars($station_address); ?><span class="contact"><strong><i class="ri-phone-fill"></i></strong> 63+ <?php echo htmlspecialchars($phone_number); ?></p>
    <h1 class="products">PRODUCTS</h1>

    <!-- Form for adding products to the cart -->
    <form method="POST" action="products.php?station_id=<?php echo htmlspecialchars($station_id); ?>">
        <?php if (count($products) > 0): ?>
            <ul class="product-list">
            <?php foreach ($products as $product): ?>
                <li class="product-item">
    <label>
        <div class="product-info">
            <!-- Checkbox for selection -->
            <div class="checkbox-container">
                <input type="checkbox" name="select-product[]" value="<?php echo $product['product_id']; ?>" 
                       id="product-<?php echo $product['product_id']; ?>"
                       <?php if ($product['availability_status'] == 'O') echo 'disabled'; ?> 
                       onchange="updateTotal()">
            </div>


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
                </div>

        <!-- Product Details -->
        <h2><?php echo htmlspecialchars($product['product_name']); ?> (<?php echo nl2br(htmlspecialchars($product['description'])); ?>)</h2>
        <p><span class="price-label">Price:</span> <span class="price-value">₱<?php echo htmlspecialchars($product['price']); ?></span></p>

        <!-- Availability Status -->
        <p class="availability-status">
            <strong>Availability:</strong> 
            <?php 
            if ($product['availability_status'] == 'A') {
                echo 'Available';
            } else {
                echo 'Out of Stock';
            }
            ?>
        </p>

        <!-- Quantity input -->
        <div class="quantity-container">
            <div class="quantity-controls">
                <label for="quantity-<?php echo htmlspecialchars($product['product_id']); ?>">Quantity:</label>
                <input type="number" id="quantity-<?php echo htmlspecialchars($product['product_id']); ?>" 
                       name="quantity[<?php echo htmlspecialchars($product['product_id']); ?>]" 
                       class="quantity-input" value="1" min="1" max="99"
                       onchange="updateTotal()">
                <button type="button" class="quantity-btn decrement" data-product-id="<?php echo htmlspecialchars($product['product_id']); ?>"
                        onclick="decrementQuantity(<?php echo $product['product_id']; ?>)" 
                        <?php if ($product['availability_status'] == 'O') echo 'disabled'; ?>>-</button>
                <button type="button" class="quantity-btn increment" data-product-id="<?php echo htmlspecialchars($product['product_id']); ?>"
                        onclick="incrementQuantity(<?php echo $product['product_id']; ?>)" 
                        <?php if ($product['availability_status'] == 'O') echo 'disabled'; ?>>+</button>
            </div>
        </div>
    </label>
      <!-- Render the heart icon -->
      <i class="heart-icon <?php echo in_array($product['product_id'], $wishlist) ? 'ri-heart-3-fill' : 'ri-heart-3-line'; ?>" 
                   data-product-id="<?php echo $product['product_id']; ?>" 
                   data-station-id="<?php echo $station_id; ?>" 
                   onclick="toggleWishlist(this)">
                </i>

<!-- Custom Alert/wishlist add/remove message Container -->
<div id="custom-alert" class="alert hidden">
    <span id="alert-message"></span>
</div>

</li>

<?php endforeach; ?>
            </ul>

        <?php else: ?>
            <p>No products available for this station.</p>
        <?php endif; ?>

        <div class="total-container">
    <p id="overall-total">Total Price: ₱0.00</p>
</div>

       <!-- Buttons -->
<!-- Add to Cart Button -->
<div class="button-container">
<form method="POST" action="cart.php=<?php echo htmlspecialchars($station_id); ?>">
    <input type="hidden" name="station_id" value="<?php echo htmlspecialchars($station_id); ?>">
    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
    <input type="hidden" name="quantity" value="1">
    <button type="submit" name="add_to_cart" class="add-to-cart" disabled>Add to Cart</button>
</form>

<!-- Buy Now Button -->
<form method="POST" action="OrderDetails.php" id="buyNowForm">
    <input type="hidden" name="orderDetails" id="orderDetails" value="">
    <input type="hidden" name="station_id" value="<?php echo htmlspecialchars($station_id); ?>"> <!-- Add this line -->
    <button type="submit" name="buy_now" class="buy-now" disabled>Buy Now</button>
</form>
</div>

<div class="success-popup">
    <p>Added to cart successfully!</p>
</div>
<div class="success-overlay"></div>

<?php
if (isset($_GET['success']) && $_GET['success'] === 'true') {
    $total_price = $_GET['total_price'] ?? 0;
    echo "<p>Checkout successful!" . htmlspecialchars($total_price) . "</p>";
}
?>

<script>
// Get all checkbox elements
const checkboxes = document.querySelectorAll('input[name="select-product[]"]');

// Get the buttons
const addToCartButton = document.querySelector('.add-to-cart');
const buyNowButton = document.querySelector('.buy-now');

// Function to check if any checkbox is selected
function updateButtonState() {
    let anyChecked = false;

    // Loop through checkboxes to check if any are selected
    checkboxes.forEach(function(checkbox) {
        if (checkbox.checked) {
            anyChecked = true;
        }
    });

    // Enable or disable buttons based on whether any checkbox is checked
    addToCartButton.disabled = !anyChecked;
    buyNowButton.disabled = !anyChecked;

    // Disable hover effect when buttons are disabled
    if (!anyChecked) {
        addToCartButton.classList.add('no-hover');
        buyNowButton.classList.add('no-hover');
    } else {
        addToCartButton.classList.remove('no-hover');
        buyNowButton.classList.remove('no-hover');
    }
}

// Add event listeners to each checkbox to update button state on change
checkboxes.forEach(function(checkbox) {
    checkbox.addEventListener('change', updateButtonState);
});

// Initialize button state on page load
updateButtonState();
</script>
    </form>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            showSuccessPopup();
            // Hide after 3 seconds
            setTimeout(hideSuccessPopup, 1000);
        });
    </script>
    <?php unset($_SESSION['success_message']); // Clear the session flag ?>
<?php endif; ?>

<?php
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    echo "<script>
        window.onload = function() {
            Swal.fire({
                icon: 'success',
                title: 'Order Placed',
                text: 'Placed Order Successfully',
                confirmButtonText: 'Okay'
            });
        }
    </script>";
}
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Hide success popup when the page reloads or is loaded
    if (sessionStorage.getItem('popupShown')) {
        // Success popup already shown, so hide it
        document.querySelector('.success-popup').style.display = 'none';
        document.querySelector('.success-overlay').style.display = 'none';
    } else if (<?php echo isset($_SESSION['success_message']) ? 'true' : 'false'; ?>) {
        // Show success popup if the session message exists
        showSuccessPopup();
        // Set sessionStorage flag that the popup has been shown
        sessionStorage.setItem('popupShown', 'true');
        // Hide after 3 seconds
        setTimeout(hideSuccessPopup, 1000);
    }
}
)

   // Function to handle incrementing quantity
function incrementQuantity(productId) {
    // Get the quantity input field for the specific product by ID
    const quantityInput = document.querySelector(`#quantity-${productId}`);
    
    // Parse the current value of the quantity or default to 1 if invalid
    let quantity = parseInt(quantityInput.value) || 1;
    
    // Check if quantity is less than 99 (or any other maximum limit you set)
    if (quantity < 99) {
        // Increment the quantity by 1
        quantityInput.value = quantity + 1;
    }
    
    // Call the function to update the total price after quantity change
    updateTotal();
}

   // Function to handle decrementing quantity
function decrementQuantity(productId) {
    // Get the quantity input field for the specific product by ID
    const quantityInput = document.querySelector(`#quantity-${productId}`);
    
    // Parse the current value of the quantity or default to 1 if invalid
    let quantity = parseInt(quantityInput.value) || 1;
    
    // Prevent decrementing below 1
    if (quantity > 1) {
        quantityInput.value = quantity - 1;
    }

    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('input', function () {
            const productId = this.getAttribute('id').replace('quantity-', '');
            const checkbox = document.querySelector(`#product-${productId}`);
            if (parseInt(this.value) > 0) {
                checkbox.checked = true;
            } else {
                checkbox.checked = false;
            }
        });
    });
};

function showSuccessPopup() {
    document.querySelector('.success-popup').style.display = 'block';
    document.querySelector('.success-overlay').style.display = 'block';
}

function hideSuccessPopup() {
    document.querySelector('.success-popup').style.display = 'none';
    document.querySelector('.success-overlay').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    if (sessionStorage.getItem('popupShown')) {
        // Success popup already shown, so hide it
        document.querySelector('.success-popup').style.display = 'none';
        document.querySelector('.success-overlay').style.display = 'none';
    } else if (<?php echo isset($_SESSION['success_message']) ? 'true' : 'false'; ?>) {
        // Show success popup if the session message exists
        showSuccessPopup();
        // Set sessionStorage flag that the popup has been shown
        sessionStorage.setItem('popupShown', 'true');
        // Hide after 3 seconds
        setTimeout(hideSuccessPopup, 3000);
    }

});
</script>

<script>
       // Heart icon transition
function toggleHeart(element) {
    if (element.classList.contains('ri-heart-3-line')) {
        element.classList.remove('ri-heart-3-line');
        element.classList.add('ri-heart-3-fill');
    } else {
        element.classList.remove('ri-heart-3-fill');
        element.classList.add('ri-heart-3-line');
    }

    // Add the "pumping" animation
    element.classList.add('pumping');

    // Remove the animation class after it completes (to allow re-triggering on the next click)
    setTimeout(() => {
        element.classList.remove('pumping');
    }, 500); // Duration should match the animation duration in CSS
}

// AJAX request to add/remove item from wishlist
function toggleWishlist(icon) {
    const productId = icon.getAttribute('data-product-id');
    const stationId = icon.getAttribute('data-station-id');

    // Make an AJAX request to the same page
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'wishlist_action.php', true); // Make the request to the PHP file
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
    const alertMessage = document.getElementById('alert-message');
    const customAlert = document.getElementById('custom-alert');
    
    if (xhr.status === 200) {
        const response = xhr.responseText.trim();
        
        if (response === 'added') {
            icon.classList.remove('ri-heart-3-line');
            icon.classList.add('ri-heart-3-fill');
            
            alertMessage.textContent = 'Item added to wishlist!';
            customAlert.classList.add('success'); // Add success class
            showAlert();
        } else if (response === 'removed') {
            icon.classList.remove('ri-heart-3-fill');
            icon.classList.add('ri-heart-3-line');
            
            alertMessage.textContent = 'Item removed from wishlist!';
            customAlert.classList.add('info'); // Add info class
            showAlert();
        } else {
            alertMessage.textContent = 'Error: ' + response;
            customAlert.classList.add('error'); // Add error class
            showAlert();
        }
    } else {
        alertMessage.textContent = 'Request failed with status ' + xhr.status;
        customAlert.classList.add('error'); // Add error class
        showAlert();
    }
};

function showAlert() {
    const customAlert = document.getElementById('custom-alert');
    customAlert.classList.remove('hidden');
    
    // Hide alert after 3 seconds
    setTimeout(closeAlert, 3000);
}

function closeAlert() {
    const customAlert = document.getElementById('custom-alert');
    customAlert.classList.add('hidden');
}

    xhr.send(`action=toggle_wishlist&product_id=${productId}&station_id=${stationId}`);
}
</script>

<script>
    // Function to update the total price
    function updateTotal() {
        let totalPrice = 0;
        
        // Get all checkboxes
        const checkboxes = document.querySelectorAll('input[name="select-product[]"]:checked');
        
        checkboxes.forEach(checkbox => {
            const productId = checkbox.value;
            const quantity = parseInt(document.querySelector(`#quantity-${productId}`).value) || 1;
            const price = parseFloat(document.querySelector(`#product-${productId}`).closest('.product-item').querySelector('.price-value').textContent.replace('₱', '').trim());

            totalPrice += quantity * price;
        });
        
        // Update overall total
        document.querySelector('#overall-total').textContent = `Total Price: ₱${totalPrice.toFixed(2)}`;
    }

    // Increment and decrement functions to adjust quantity
    function incrementQuantity(productId) {
        const quantityInput = document.querySelector(`#quantity-${productId}`);
        let quantity = parseInt(quantityInput.value) || 1;
        if (quantity < 99) {
            quantityInput.value = quantity + 1;
        }
        updateTotal();
    }

    function decrementQuantity(productId) {
        const quantityInput = document.querySelector(`#quantity-${productId}`);
        let quantity = parseInt(quantityInput.value) || 1;
        if (quantity > 1) {
            quantityInput.value = quantity - 1;
        }
        updateTotal();
    }

    // Initialize the total on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateTotal(); // Call this to initialize the totals
    });

</script>
<script>

// Function to show the success popup
function showSuccessPopup() {
    const popup = document.querySelector('.success-popup');
    popup.style.display = 'flex'; // Show the pop-up when triggered

    // Optional: Hide the pop-up after a certain time (e.g., 3 seconds)
    setTimeout(() => {
        popup.style.display = 'none'; // Hide after 3 seconds
    }, 3000); // Adjust the time as needed
}
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const popup = document.querySelector('.success-popup');
            const overlay = document.querySelector('.success-overlay');
            
            // Show the pop-up and overlay
            popup.style.display = 'block';
            overlay.style.display = 'block';

            console.log("Popup shown!"); // Debug message

// Hide the pop-up after 1.5 seconds (1500 ms)
setTimeout(() => {
    console.log("Hiding popup after delay."); // Debug message
    popup.style.display = 'none';
    overlay.style.display = 'none';
}, 1500); // 1.5 seconds
        });
    });

    document.querySelector('[name="add_to_cart"]').addEventListener('click', function(e) {
    // Add to cart logic
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
