<?php
session_start();
include 'db_connection.php';
include 'send_email.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$alerts = [];
$hasSuccess = false;

// Sticky fields
$fullName = $phone = $email = '';
$selectedType = 'rider';

// Upload config
$MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB
$ALLOWED_MIMES = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'application/pdf' => 'pdf'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selectedType = $_POST['type'] ?? 'rider';
    $fullName     = trim($_POST['full_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';
    /* ---------------- VALIDATION ---------------- */
    if ($fullName === '' || $phone === '' || $email === '' || $password === '' || $confirm === '') {$alerts[] = ['type'=>'danger','msg'=>'All required fields must be filled.'];}
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {$alerts[] = ['type'=>'danger','msg'=>'Invalid email address.'];}
    if (!preg_match('/^03\d{6}$|^7\d{7}$/', $phone)) { $alerts[] = ['type'=>'danger','msg'=>'Enter a valid Lebanese phone number.'];}
    if (strlen($password) < 6) {$alerts[] = ['type'=>'danger','msg'=>'Password must be at least 6 characters.'];}
    if ($password !== $confirm) {$alerts[] = ['type'=>'danger','msg'=>'Passwords do not match.'];}

    $isApplyingDriver = ($selectedType === 'driver');
    if ($isApplyingDriver) {
        if (!isset($_FILES['driving_license']) || $_FILES['driving_license']['error'] !== UPLOAD_ERR_OK) {
            $alerts[] = ['type'=>'danger','msg'=>'Driving license is required.'];
        }
        if (!isset($_FILES['official_doc']) || $_FILES['official_doc']['error'] !== UPLOAD_ERR_OK) {
            $alerts[] = ['type'=>'danger','msg'=>'Official document is required.'];
        }
    }
    if (!empty(array_filter($alerts, fn($a) => $a['type'] === 'danger'))) {
        goto render;
    }
    /* ---------------- DUPLICATE EMAIL ---------------- */
    $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $alerts[] = ['type'=>'danger','msg'=>'This email is already registered.'];
        $chk->close();
        goto render;
    }
    $chk->close();

    /* ---------------- TRANSACTION ---------------- */
    $createdDriverApp = false;
    $driver_id = null;
    $savedDrivingLicenseRel = null;
    $savedOfficialDocRel = null;

    $conn->begin_transaction();
    try {
        /* USERS */
        $token = bin2hex(random_bytes(32));
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO users (full_name, phone, email, password, is_confirmed, email_token)
            VALUES (?, ?, ?, ?, 0, ?)
        ");
        $stmt->bind_param("sssss", $fullName, $phone, $email, $hashed, $token);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();

        /* ROLE: RIDER */
        $roleStmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, 2)");
        $roleStmt->bind_param("i", $user_id);
        $roleStmt->execute();
        $roleStmt->close();

        /* DRIVER APPLICATION */
        if ($isApplyingDriver) {
            $stmt = $conn->prepare("
                INSERT INTO drivers (user_id, status)
                VALUES (?, 'Pending')
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $driver_id = $conn->insert_id;
            $stmt->close();

            $createdDriverApp = true;
            $driverDir = __DIR__ . "/uploads/drivers/driver_$driver_id/";
            if (!is_dir($driverDir) && !mkdir($driverDir, 0777, true)) {
                throw new Exception("Upload directory error");
            }

            foreach (['driving_license','official_doc'] as $field) {
                $file = $_FILES[$field];

                if ($file['size'] > $MAX_FILE_BYTES) {
                    throw new Exception("File too large");
                }

                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (!isset($ALLOWED_MIMES[$mime])) {
                    throw new Exception("Invalid file type");
                }

                $ext = $ALLOWED_MIMES[$mime];
                $name = $field.'_'.bin2hex(random_bytes(6)).".$ext";
                $abs  = $driverDir.$name;
                $rel  = "uploads/drivers/driver_$driver_id/$name";

                if (!move_uploaded_file($file['tmp_name'], $abs)) {
                    throw new Exception("File upload failed");
                }

                if ($field === 'driving_license') $savedDrivingLicenseRel = $rel;
                if ($field === 'official_doc')    $savedOfficialDocRel = $rel;
            }

            $stmt = $conn->prepare("
                UPDATE drivers
                SET driving_license_file = ?, official_doc = ?
                WHERE driver_id = ?
            ");
            $stmt->bind_param("ssi", $savedDrivingLicenseRel, $savedOfficialDocRel, $driver_id);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        /* EMAIL */
        try {
            $link = "http://localhost/uniridee/confirm_email.php?token=" . urlencode($token);
            $message = "
            <p>Click the link below to confirm your email:</p>
            <p>
                <a href='$link'>$link</a>
            </p>
            ";

            sendEmail(
                $email,
                $fullName,
                "Confirm Your UniRide Email",
                $message
            );

        } catch (Throwable $e) {
            $alerts[] = ['type'=>'warning','msg'=>'Account created, but email could not be sent.'];
        }

        $alerts[] = ['type'=>'success','msg'=>'Account created. Please check your email to confirm.'];
        if ($isApplyingDriver) {
            $alerts[] = ['type'=>'info','msg'=>'Driver application submitted for review.'];
        }

        $hasSuccess = true;
        $fullName = $phone = $email = '';
    } catch (Throwable $e) {
        $conn->rollback();
        if ($createdDriverApp && $driver_id) {
            if ($savedDrivingLicenseRel) @unlink(__DIR__.'/'.$savedDrivingLicenseRel);
            if ($savedOfficialDocRel) @unlink(__DIR__.'/'.$savedOfficialDocRel);
            @rmdir(__DIR__."/uploads/drivers/driver_$driver_id/");
        }
        $alerts[] = ['type'=>'danger','msg'=>'Registration failed. Please try again.'];
    }
}

render:
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register - UniRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .hidden { display: none; }
        .card { max-width: 720px; margin: 24px auto; }
        .btn-primary { background-color: #028a99; border-color: #028a99; }
    </style>
    <script>
        function toggleDriverFields() {
            const type = document.getElementById('type').value;
            const df = document.getElementById('driverFields');
            df.style.display = (type === 'driver') ? 'block' : 'none';
        }
        window.addEventListener('DOMContentLoaded', () => {
            toggleDriverFields();
        });
    </script>
</head>
<body class="bg-light">
<div class="container">
    <div class="card shadow">
        <div class="card-body">
            <h3 class="text-center mb-3">Create Account</h3>

            <?php foreach ($alerts as $a): ?>
                <div class="alert alert-<?php echo h($a['type']); ?> alert-dismissible fade show">
                    <?php echo h($a['msg']); ?>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>

            <form method="post" enctype="multipart/form-data" novalidate>
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input id="full_name" name="full_name" class="form-control" required value="<?php echo h($fullName); ?>">
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input id="phone" name="phone" class="form-control" required inputmode="numeric" pattern="\d+" value="<?php echo h($phone); ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input id="email" name="email" type="email" class="form-control" required value="<?php echo h($email); ?>">
                </div>

                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" name="password" type="password" class="form-control" required minlength="6">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" class="form-control" required minlength="6">
                    </div>
                </div>
                 <div class="mb-3">
                    <label class="form-label">Register As</label>
                    <select id="type" name="type" class="form-select" onchange="toggleDriverFields()">
                        <option value="rider" <?php echo $selectedType === 'rider' ? 'selected' : ''; ?>>Rider</option>
                        <option value="driver" <?php echo $selectedType === 'driver' ? 'selected' : ''; ?>>Driver (apply)</option>
                    </select>
                    <div class="form-text">Selecting Driver submits a driver application. Role 'driver' will be given after admin approval.</div>
                </div>

                <div id="driverFields" style="display:none; border-top:1px solid #eee; padding-top:16px; margin-top:12px;">
                    <h5>Driver Application Details</h5>
                    <div class="mb-3">
                        <label for="driving_license" class="form-label">Driving License Document (jpg/png/pdf)</label>
                        <input id="driving_license" name="driving_license" type="file" class="form-control" accept="image/jpeg,image/png,application/pdf">
                    </div>

                    <div class="mb-3">
                        <label for="official_doc" class="form-label">Official Document (ID / Passport) (jpg/png/pdf)</label>
                        <input id="official_doc" name="official_doc" type="file" class="form-control" accept="image/jpeg,image/png,application/pdf">
                    </div>
                </div>

                <div class="d-grid mt-3">
                    <button class="btn btn-primary">Create Account</button>
                </div>
            </form>

            <div class="mt-3 text-center">
                Already have an account? <a href="login.php">Login here</a>.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
