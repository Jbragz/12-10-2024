<?php
session_start();
session_destroy();
header("Location: /waterordering/SIGNIN/login.php"); // Redirect to login page
exit();
?>
