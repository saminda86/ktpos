<?php
// 1. Session එක ආරම්භ කරන්න
session_start();

// 2. සියලුම Session විචල්‍යයන් ඉවත් කරන්න
$_SESSION = array();

// 3. Session cookie විනාශ කරන්න
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Session එක විනාශ කරන්න
session_destroy();

// 5. පරිශීලකයා නැවත Login පිටුවට යොමු කරන්න
header("Location: login.php");
exit;
?>