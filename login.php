<?php
session_start();
include 'db_connection.php';
function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
$alerts = [];
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {$alerts[] = ['type' => 'danger', 'msg' => 'Please enter email and password.'];}
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {$alerts[] = ['type' => 'danger', 'msg' => 'Please enter a valid email address.'];}
    if (empty($alerts)) {
        $stmt = $conn->prepare("
            SELECT user_id, password, is_confirmed 
            FROM users 
            WHERE email = ? LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$res || $res->num_rows === 0) {
            $alerts[] = ['type' => 'danger', 'msg' => 'Invalid email or password.'];
        } else {
            $user = $res->fetch_assoc();
            if ((int)$user['is_confirmed'] === 0) {
                $alerts[] = ['type' => 'warning', 'msg' => 'Please confirm your email.'];
            } else {
                if (!password_verify($password, $user['password'])) {
                    $alerts[] = ['type' => 'danger', 'msg' => 'Invalid email or password.'];
                } else {
                    $_SESSION['user_id'] = (int)$user['user_id'];
                    $role_stmt = $conn->prepare("
                        SELECT role_id 
                        FROM user_roles 
                        WHERE user_id = ? 
                        ORDER BY role_id DESC
                        LIMIT 1
                    ");
                    $role_stmt->bind_param("i", $_SESSION['user_id']);
                    $role_stmt->execute();
                    $role_res = $role_stmt->get_result();
                    if ($role_res->num_rows === 0) {
                        $alerts[] = ['type' => 'danger', 'msg' => 'No role assigned to this account.'];
                    } else {
                        $role = (int)$role_res->fetch_assoc()['role_id'];
                        $_SESSION['role_id'] = $role;
                        // Check if user is also an approved driver
                        $ds = $conn->prepare("
                            SELECT driver_id 
                            FROM drivers 
                            WHERE user_id = ? AND status = 'approved'
                            LIMIT 1
                        ");
                        $ds->bind_param("i", $_SESSION['user_id']);
                        $ds->execute();
                        $dr = $ds->get_result();

                        if ($dr && $dr->num_rows > 0) {
                            $_SESSION['driver_id'] = (int)$dr->fetch_assoc()['driver_id'];
                            $_SESSION['is_driver'] = true; // rider who is also a driver
                        } else {
                            $_SESSION['is_driver'] = false;
                        }
                        // Redirect based on primary role
                        if ($role === 1) {
                            header("Location: admin/dashboard.php");
                            exit;
                        }
                        if ($role === 3) { // main driver account
                            header("Location: driver/dashboard.php");
                            exit;
                        }
                        if ($role === 2) { // rider
                            header("Location: rider/dashboard.php");
                            exit;
                        }                       
                    }
                }
            }
        }
        $stmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login - UniRide</title>
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
            <h3 class="mb-3 text-center">Login</h3>

            <?php foreach ($alerts as $a): ?>
                <div class="alert alert-<?php echo h($a['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo h($a['msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>

            <form method="post" novalidate>
                <input type="hidden" name="action" value="login">

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

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control"
                           required
                           placeholder="Enter your password">
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            
            <div class="mt-3 text-center">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>
            <div class="mt-3 text-center">
                Don't have an account? <a href="register.php">Register here</a>.
            </div>


        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
