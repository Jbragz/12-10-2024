<?php
// Start the session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('dwos.php');

function getLoggedInUserInfo($conn) {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];

        // Prepare and execute the query to fetch the username and image
        $stmt = $conn->prepare("SELECT user_name, image FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc(); // Return the user's info
        }

        $stmt->close();
    }
    return null; // Return null if no user is logged in
}

// Fetch the logged-in user's info
$user_info = getLoggedInUserInfo($conn);

// Determine the profile image to display
$profileImage = !empty($user_info['image']) ? 'image/' . $user_info['image'] : './image/default.jpg'; 
$user_name = htmlspecialchars($user_info['user_name'] ?? ''); // Fallback to empty string if not set

// Get the current script name (e.g., cart.php)
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="customernavbar.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet"/>
    <title>Customer Navbar</title>
    <style>
        .profile { 
            display: inline-block; 
            position: relative; 
        }

        .toggleDropdown {
            background: #03F8F8;
        }

        .dropdown-content { 
            display: none; 
            position: absolute; 
            background-color: #fff;
            width: 220px; /* Fixed width for the dropdown */
            box-shadow: 0 4px 10px rgba(36, 36, 36, 0.5); 
            border-radius: 10px; 
            margin: 10px 0; 
            left: -10%; /* Center it horizontally */
            transform: translateX(-60%); /* Shift left by half its width */
            z-index: 1000; 
        }

        .dropdown-content a { 
            font-family: 'Fredoka', sans-serif;
            font-weight: bold;
            padding: 15px; /* Fixed padding for consistent height */
            border-radius: 30px; /* Oblong shape */
            text-decoration: none; /* Remove underline */
            text-align: center; /* Center text */
            color: #0093c1; /* Text color */
            background-color: #daf7ff; /* Set a background color */
            display: block; 
            margin: 10px 0; /* Adjust margin to space out links */
            transition: background-color 0.3s; /* Smooth transition for hover effect */
        }

        .username-display{
            text-align: center;
        }

        /* Optional: Change background on hover */
        .dropdown-content a:hover {
            background-color: #003f67; /* Change background on hover */
            color: #ffffff;
        }

        .show { 
            display: block; 
        }
    </style>
</head>
<body>
<nav>
        <div class="nav__logo">
            <a href=""><img src=".\image\dwoslogo.png" alt="Water Ordering System Logo" /></a>
        </div> 
        <div class="nav__menu">
            <ul class="nav__links">
                <li class="<?php echo $current_page == 'customerpage.php' ? 'active' : ''; ?>"><a href="customerpage.php">HOME</a></li>
                <li class="<?php echo $current_page == 'customerstation.php' ? 'active' : ''; ?>"><a href="customerstation.php">STATIONS</a></li>
                <li class="<?php echo $current_page == 'customerorders.php' ? 'active' : ''; ?>"><a href="customerorders.php">ORDERS</a></li>
            </ul>
            <div class="burger-menu" onclick="toggleNavbar()">
                <i class="fas fa-bars"></i>
            </div>
            <a href="wishlist.php" class="wishlist-icon <?php echo $current_page == 'wishlist.php' ? 'active' : ''; ?>">
                <i class="<?php echo $current_page == 'wishlist.php' ? 'ri-heart-fill' : 'ri-heart-line'; ?>"></i></a>
            <a href="cart.php" class="cart-icon">
                <i class="<?php echo $current_page == 'cart.php' ? 'ri-shopping-cart-fill' : 'ri-shopping-cart-line'; ?>"></i></a>
            <div class="profile">
                <div class="profile-dropdown">
                    <img src="<?php echo $profileImage; ?>" alt="Profile Pic" onclick="toggleDropdown()" class="profile-pic" style="width: 50px; height: 50px;" />
                    <div class="dropdown-content" id="myDropdown">
                        <?php if ($user_name): ?>
                            <div class="username-display">
                                <strong><?php echo htmlspecialchars($user_name); ?></strong>
                            </div>
                        <?php endif; ?>
                        <a href="manageaccount.php">Manage Account</a>
                        <a href="customersettings.php">Settings</a>
                        <a href="Premiums.php">Premiums</a>
                        <a href="logout.php">Log Out</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <script>
        function toggleNavbar() {
            const navLinks = document.querySelector(".nav__links"); // Select by class
            navLinks.classList.toggle("active");
        }
        function toggleDropdown() {
            document.getElementById("myDropdown").classList.toggle("show");
        }
        window.onclick = function(event) {
            if (!event.target.matches('.profile img')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>

<script>
    function setCartActive(event) {
        event.preventDefault(); // Prevent immediate navigation

        const cartIcon = document.getElementById('cart-icon');
        
        // Set the active icon
        if (cartIcon.classList.contains('ri-shopping-cart-line')) {
            cartIcon.classList.remove('ri-shopping-cart-line');
            cartIcon.classList.add('ri-shopping-cart-fill');
        }

        // Redirect to the cart page after the activation
        window.location.href = 'cart.php';
    }
</script>
</body>
</html>