<?php
include 'db_connection.php';

if (!isset($_GET['token'])) {
    die("<h2>Invalid verification link.</h2>");
}

$token = $_GET['token'];

$stmt = $conn->prepare("SELECT user_id, is_confirmed FROM users WHERE email_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if ($user) {
    
    if ($user['is_confirmed'] == 1) {
        echo "<h2>Your email is already verified.</h2>";
        echo "<p>You can <a href='login.php'>login here</a>.</p>";
    } else {
        $stmt2 = $conn->prepare("UPDATE users SET is_confirmed = 1, email_token = NULL WHERE user_id = ?");
        $stmt2->bind_param("i", $user['user_id']);
        $stmt2->execute();

        echo "<h2>Email verified successfully!</h2>";
        echo "<p>You can now <a href='login.php'>login</a>.</p>";
        echo "<script>
                setTimeout(function(){
                    window.location.href = 'login.php';
                }, 000);
              </script>";
    }

} else {
    echo "<h2>Invalid or expired verification link.</h2>";
    echo "<p>Please register again or contact support.</p>";
}
?>
