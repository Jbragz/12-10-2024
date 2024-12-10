<?php
session_start();
include('dwos.php'); // Database connection

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details to populate the form
$query = "SELECT * FROM users WHERE user_id = '$user_id' AND user_type = 'C'";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
} else {
    echo "Error fetching Customer details";
    exit();
}

// Fetch customer membership plans (IDs 4, 5, 6) from the database
$plans_query = "SELECT * FROM memberships WHERE membership_id IN (4, 5, 6)";
$plans_result = mysqli_query($conn, $plans_query);

// Handle form submission
if (isset($_POST['avail'])) {
    $selected_plan_id = $_POST['plan']; // Get selected plan ID from form

    // Get the plan details from the database based on the selected plan ID
    $plan_query = "SELECT * FROM memberships WHERE membership_id = $selected_plan_id";
    $plan_result = mysqli_query($conn, $plan_query);

    if (mysqli_num_rows($plan_result) > 0) {
        $plan = mysqli_fetch_assoc($plan_result);

        // Store selected plan in session for later use
        $_SESSION['user_data']['plan'] = $plan;

        // Redirect to the payment page
        header('Location: premiumpayment.php');
        exit();
    } else {
        echo "Selected plan not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'customernavbar.php'; ?>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Premiums.css">
    <title>Premium Membership</title>
</head>
<body>
    <div class="subscription-container">
        <h3>PREMIUM MEMBERSHIP</h3>
        <p>Purchasing premium subscription provides extra service and benefits for the customer that he/she can use for his/her everyday access in the system. <a href="#" id="discoverMore">Discover more.</a></p>
        <form action="" method="post">
            <?php while ($plan = mysqli_fetch_assoc($plans_result)) { ?>
                <div class="plan">
                    <input type="radio" id="plan_<?php echo $plan['membership_id']; ?>" name="plan" value="<?php echo $plan['membership_id']; ?>" required>
                    <label for="plan_<?php echo $plan['membership_id']; ?>">
                        Buy premium for <?php echo round($plan['duration_in_days'] / 30); ?> months for only ₱<?php echo $plan['price']; ?> only
                    </label>
                </div>
            <?php } ?>
            <div class="button-container">
                <input type="submit" name="avail" value="AVAIL" class="form-btn">
            </div>
        </form>
    </div>

    <!-- Modal for Discover More -->
    <div id="benefitsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="modal-body">
                <h4>Benefits of Premium Membership</h4>
                <ul>
                    <p>Reliable and faster service that whould benefits them if they have bigger establishments that needed faster and larger need of drinking water.</p>
                    <p>Flexible subscription and customization options, promotes health benefits, and appeals to environmentally conscious customers through sustainable packaging, ensuring excellent customer service.</p>
                    <p>Ensures excellent customer support and offers affordability through competitive pricing and discounts.</p>
                    <p>Easier and more efficient ordering through online or mobile platforms.</p>
                    <p>Exclusive Discounts on Shipping Fees.</p>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Get the modal
        var modal = document.getElementById("benefitsModal");

        // Get the button that opens the modal
        var discoverMore = document.getElementById("discoverMore");

        // Get the <span> element that closes the modal
        var span = document.getElementsByClassName("close")[0];

        // When the user clicks the "Discover More" link, open the modal
        discoverMore.onclick = function() {
            modal.style.display = "block";
        }

        // When the user clicks the close button, close the modal
        span.onclick = function() {
            modal.style.display = "none";
        }

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>