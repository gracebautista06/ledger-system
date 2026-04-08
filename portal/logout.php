<?php
    // 1. Start the session to gain access to the data we want to delete
    session_start();

    // 2. Unset all session variables (clears user_id, username, role)
    $_SESSION = array();

    // 3. Destroy the session entirely from the server
    session_destroy();

    // 4. Redirect the user back to the login page with a "logged out" message
    header("Location: login.php?message=logged_out");
    exit();
?>