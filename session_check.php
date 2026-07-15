<?php
session_start();

// Must always have user_id
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$userRole = (int)($_SESSION['role_id'] ?? 0);

// Driver ID if user is approved driver or main driver
$driver_id = isset($_SESSION['driver_id']) ? (int)$_SESSION['driver_id'] : null;

// Extra flag: rider who is also a driver
$isDriver = isset($_SESSION['is_driver']) ? $_SESSION['is_driver'] : false;
?>
