<?php
session_start();   // Start or resume the current session (needed to access session data)
session_destroy(); // Destroy all session data (logs the user out)
header('Location: index.php'); // Redirect user back to login page (index.php)
exit;              // Stop script execution after redirect
?>
