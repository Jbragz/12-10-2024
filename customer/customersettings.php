<?php
session_start();

include('dwos.php'); // Include your database connection

// Check if the customer is logged in by verifying user_id in the session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get the logged-in customer's user_id
$user_id = $_SESSION['user_id'];

// Fetch Customer Details from the database using prepared statements
$stmt = $conn->prepare("SELECT user_name, image, password FROM users WHERE user_id = ? AND user_type = 'C'");
if (!$stmt) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    exit();
}
$stmt->bind_param("s", $user_id); // Changed to "s" assuming user_id is a string
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    echo "Error fetching user details: " . $conn->error; 
    exit();
}

// Handle form submission for changing password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // Get form inputs and trim whitespace
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Check if the current password matches (no hashing, plain text comparison)
    if ($current_password === $user['password']) {
        // Check if the new password is different from the current password
        if ($new_password === $current_password) {
            echo json_encode(['success' => false, 'message' => 'New password cannot be the same as the current password']);
            exit();
        }

        // Check if new password and confirm password match
        if ($new_password === $confirm_password) {
            // Update query to change password (no hashing)
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ? AND user_type = 'C'");
            if (!$update_stmt) {
                echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
                exit();
            }
            $update_stmt->bind_param("ss", $new_password, $user_id); // Store plain text password

            // Execute the update and check for success
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating password: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    }

    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <link rel="stylesheet" href="settings.css">
</head>
<body>
<?php include 'customernavbar.php'; ?>

<div class="main-container">
    <div class="profile-container">
        <h2>Account Settings</h2>

        <div class="profile-info">
            <div class="profile-header">
                <?php if (!empty($user['image'])): ?>
                    <img class="profile-pic" src="image/<?php echo htmlspecialchars($user['image']); ?>" alt="Profile Picture">
                <?php else: ?>
                    <img class="profile-pic" src="image/profile-placeholder.png" alt="Profile Picture">
                <?php endif; ?>
                <label for="username" class="username-label"></label>
                <p><?php echo htmlspecialchars($user['user_name']); ?></p>
            </div>
        </div>

        <div class="passwordForm">
            <h3>Change Password</h3>
            <form id="passwordForm">
                <label for="current_password">Current Password:</label>
                <input type="password" name="current_password" required>

                <label for="new_password">New Password:</label>
                <input type="password" name="new_password" required>

                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" name="confirm_password" required>

                <div class="button-container">
                    <button type="submit" class="update-btn">UPDATE</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Terms and Privacy Section -->
    <div class="terms-container">
        <p>By using this website, you agree to the following terms and conditions. Your information will be kept confidential and will not be shared with third parties. You are responsible for 
            maintaining the confidentiality of your password and account. We reserve the right to update our terms and privacy policy at any time.</p>
        <div class="terms-checkbox">
            <input type="checkbox" id="acceptTerms" required>
            <label for="acceptTerms">I ACCEPT TERMS AND PRIVACY</label>
        </div>
    </div>
</div>

<!-- Modal Structure -->
<div id="successModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <p id="modalMessage"></p> <!-- Message will be inserted here -->
    </div>
</div>

<script>
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent form from submitting normally

        var formData = new FormData(this);
        formData.append('change_password', true); // Add extra field for password change

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log(data); // Log the server response
            showModal(data.message, data.success); // Trigger modal
        })
        .catch(error => console.error('Error:', error));
    });

    function showModal(message, success) {
        var modal = document.getElementById('successModal');
        var modalMessage = document.getElementById('modalMessage');
        modalMessage.textContent = message; // Set the modal message
        modalMessage.style.color = success ? 'black' : 'red'; // Set text color based on success
        modal.style.display = 'block'; // Show the modal
    }

    // Close modal when clicking on the close button
    document.querySelector('.close').onclick = function() {
        document.getElementById('successModal').style.display = 'none';
    }

    // Close modal when clicking outside of the modal
    window.onclick = function(event) {
        var modal = document.getElementById('successModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>
