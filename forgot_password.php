<?php
session_start();
include 'db_connection.php';
include 'send_email.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$alerts = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alerts[] = ['type' => 'danger', 'msg' => 'Please enter a valid email address.'];
    } else {
        // Look up user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        $user_id = null;
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $user_id = (int)$row['user_id'];
            // Generate token and expiry (1 hour)
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            // Update token on user
            $up = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
            $up->bind_param("ssi", $token, $expires, $user_id);
            $up->execute();
            $up->close();
            // Send Email
            $resetLink = 'http://localhost/uniridee/reset_password.php?token=' . urlencode($token);
            sendEmail(
                $email,
                Null,
                'Reset Your Password',
                'Click the link below to reset your password:<br><br>'
                                 . '<a href="' . $resetLink . '">' . $resetLink . '</a>'
            );  
        }
        $stmt->close();
        $alerts[] = ['type' => 'success', 'msg' => 'If this email is registered, a password reset link has been sent.'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Forgot Password - UniRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { max-width: 420px; width: 100%; }
        .btn-primary{
            background-color: #028a99;
        }
    </style>
</head>
<body>
<div class="min-vh-100 d-flex align-items-center justify-content-center">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="mb-3 text-center">Forgot Password</h3>

            <?php foreach ($alerts as $a): ?>
                <div class="alert alert-<?php echo h($a['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo h($a['msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>

            <form method="post" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-control"
                           required
                           value="<?php echo h($email); ?>"
                           placeholder="Enter your email">
                </div>
                <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
            </form>

            <div class="mt-3 text-center">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>