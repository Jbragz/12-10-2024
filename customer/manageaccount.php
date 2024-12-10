<?php
session_start();
include('dwos.php');

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

// Update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $response = array('status' => 'error', 'message' => 'Error updating profile');

    $user_name = $_POST['user_name'];
    $address = $_POST['address'];
    $phone_number = $_POST['phone_number'];

    if (!empty($_FILES['profile_image']['name'])) {
        $target_dir = "image/";
        $profile_image = time() . '_' . basename($_FILES['profile_image']['name']);
        $target_file = $target_dir . $profile_image;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $profile_image = $profile_image;
        } else {
            $profile_image = $user['image'];
        }
    } else {
        $profile_image = $user['image'];
    }

    $update_query = "UPDATE users SET 
                        user_name = '$user_name', 
                        address = '$address', 
                        phone_number = '$phone_number', 
                        image = '$profile_image' 
                    WHERE user_id = '$user_id' AND user_type = 'C'";

    if (mysqli_query($conn, $update_query)) {
        $_SESSION['profile_image'] = $profile_image;
        $response['status'] = 'success';
        $response['message'] = 'Profile updated successfully';
    }

    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'customernavbar.php'; ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Account</title>
    <link rel="stylesheet" href="customermanageaccount.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $("form").submit(function(e) {
                e.preventDefault();
                var formData = new FormData(this);

                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        console.log(response); // Debug: log the response
                        var data = JSON.parse(response);
                        if (data.status === 'success') {
                            showPopup('success', data.message);
                            setTimeout(() => location.reload(), 1500); // Reload after showing popup
                        } else {
                            showPopup('error', data.message);
                        }
                    }
                });
            });
        });

        function showPopup(type, message) {
            var popup = $('#popup');
            var popupMessage = $('#popup-message');
            popupMessage.text(message);

            if (type === 'success') {
                popup.css('background-color', '#4CAF50');
            } else {
                popup.css('background-color', '#f44336');
            }

            popup.fadeIn();
            setTimeout(function() {
                popup.fadeOut();
            }, 1000);
        }

        function previewImage() {
            const file = document.getElementById('profile_image').files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
</head>
<body>
    <div class="profile-container">
        <h2>Manage Account</h2>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="profile-pic-container">
                <img id="preview" src="image/<?php echo $user['image']; ?>?v=<?php echo time(); ?>" alt="Profile Pic" class="profile-pic">
                <label for="profile_image" class="custom-file-upload">
                    <span class="plus-sign">+</span>
                    <input type="file" name="profile_image" id="profile_image" onchange="previewImage()" style="display: none;">
                </label>
                <span class="update-text">Update Profile Pic</span>
            </div>

            <div class="profile-info">
                <label for="user_name">Name:</label>
                <input type="text" name="user_name" value="<?php echo $user['user_name']; ?>" required>

                <label for="address">Address:</label>
                <input type="text" name="address" value="<?php echo $user['address']; ?>" required>

                <label for="phone_number">Mobile Number:</label>
                <input type="text" name="phone_number" value="<?php echo $user['phone_number']; ?>" required>
            </div>

            <button type="submit" class="update-btn">UPDATE</button>
        </form>
    </div>

    <div id="popup" class="popup">
        <p id="popup-message"></p>
    </div>
</body>
</html>
