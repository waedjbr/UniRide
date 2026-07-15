<?php
session_start();
include 'db_connection.php';
function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
$alerts = [];
$token = trim($_GET['token'] ?? '');
$validToken = false;
$showForm = false;
$user_id = null;

if ($token === '') {
    $alerts[] = ['type' => 'danger', 'msg' => 'Invalid token'];
} else {
    $stmt = $conn->prepare("SELECT user_id, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        $alerts[] = ['type' => 'danger', 'msg' => 'Invalid token'];
    } else {
        $row = $res->fetch_assoc();
        $user_id = (int)$row['user_id'];
        $expiresAt = $row['reset_expires'];
        if (empty($expiresAt) || strtotime($expiresAt) < time()) {
            $alerts[] = ['type' => 'warning', 'msg' => 'Token expired'];
        } else {
            $validToken = true;
            $showForm = true;
        }
    }
    $stmt->close();
}

if ($validToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $alerts[] = ['type' => 'danger', 'msg' => 'Password mismatch'];
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $up = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
        $up->bind_param("si", $hash, $user_id);
        if ($up->execute()) {
            $alerts[] = ['type' => 'success', 'msg' => 'Password updated successfully.'];
            $showForm = false;
        }
        $up->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset Password - UniRide</title>
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
            <h3 class="mb-3 text-center">Reset Password</h3>

            <?php foreach ($alerts as $a): ?>
                <div class="alert alert-<?php echo h($a['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo h($a['msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>

            <?php if ($showForm): ?>
                <form method="post" action="?token=<?php echo urlencode($token); ?>" novalidate>
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm" class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm" name="confirm" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                </form>
            <?php else: ?>
                <div class="mt-3 text-center">
                    <a href="login.php">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
