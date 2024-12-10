<?php
session_start();

if (!isset($_SESSION['user_data']['payment_method'])) {
    header('Location: premiumpayment.php');
    exit();
}

include('dwos.php');

// Ensure the plan is set in session
if (!isset($_SESSION['user_data']['plan'])) {
    header('Location: Premiums.php'); // Redirect if no plan is set
    exit();
}

// Access the plan and payment method from session
$plan = $_SESSION['user_data']['plan'];
$payment_method = $_SESSION['user_data']['payment_method'];

// Handle payment confirmation
if (isset($_POST['confirm_payment'])) {
    // Retrieve user data from session
    $name = $_SESSION['user_data']['user_name'] ?? '';
    $email = $_SESSION['user_data']['email'] ?? '';
    $user_type = $_SESSION['user_data']['user_type'] ?? ''; // 'C' for customer
    $user_id = $_SESSION['user_data']['user_id'] ?? '';

    if (!isset($_SESSION['user_data']['user_type']) || strtoupper($_SESSION['user_data']['user_type']) !== 'C') {
        echo 'Invalid user type.';
        exit();
    }        

    // Determine the subscription_type for customer
    $subscription_type = 'C';

    // Use prepared statements for security
    $stmt_subscription = $conn->prepare("INSERT INTO subscriptions (user_id, membership_id, subscription_type, start_date, end_date, payment_method) 
                                          VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?)");
    $stmt_subscription->bind_param("sisss", $user_id, $plan['membership_id'], $subscription_type, $plan['duration_in_days'], $payment_method);

    // Execute the insert and check success
    $subscription_success = $stmt_subscription->execute();

    if ($subscription_success) {
        // Reassign session variables before redirecting
        $_SESSION['user_id'] = $user_id; // Reassign user_id to session
        $_SESSION['user_data'] = [
            'user_id' => $user_id,
            'user_name' => $name,
            'email' => $email,
            'user_type' => $user_type,
        ];
        
        header('Location: /waterordering/SIGNIN/signup/Page/customer/customerpage.php');
        exit();
    }    

    // Close the prepared statement
    $stmt_subscription->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'customernavbar.php'; ?>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Payment</title>
    <link rel="stylesheet" href="premiumconfirmation.css"> <!-- Add your CSS file here -->
</head>
<body>
    <div class="confirmation-container">
        <h3>Confirm Your Payment</h3>
        <div class="plan-details-container">
            <p>You have selected the <?php echo htmlspecialchars($plan['duration_in_days']); ?> days plan for ₱<?php echo htmlspecialchars($plan['price']); ?>.</p>
        </div>
        <p>Payment Method: <?php echo htmlspecialchars($payment_method); ?></p>
        <form action="" method="post">
            <div class="button-container">
                <input type="submit" name="confirm_payment" value="Confirm Payment" class="form-btn">
            </div>
        </form>
    </div>
</body>
</html>
