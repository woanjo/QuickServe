<?php
session_start();   // Start or resume the current session 
session_destroy(); // log outs the user
header('Location: index.php'); // Redirect user back to login page
exit;             
?>
