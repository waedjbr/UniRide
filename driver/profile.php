<?php
include '../db_connection.php';
include '../session_check.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}

$alerts = [];
$full_name = '';
$email = '';
$phone = '';
$driving_license = '';
$official_doc = '';
$status = 'pending';

// Fetch user + driver info
$stmt = $conn->prepare("
    SELECT u.full_name, u.email, u.phone, d.driving_license_file, d.official_doc, d.status
    FROM drivers d
    JOIN users u ON u.user_id = d.user_id
    WHERE d.driver_id = ? AND d.user_id = ?
");
$stmt->bind_param("ii", $driver_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    $d = $res->fetch_assoc();
    $full_name = $d['full_name'];
    $email = $d['email'];
    $phone = $d['phone'];
    $driving_license = $d['driving_license_file'];
    $official_doc = $d['official_doc'];
    $status = strtolower($d['status']);
}
$stmt->close();

// Handle form (ONLY if approved)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $status === 'approved') {
    $new_full_name = trim($_POST['full_name']);
    $new_email = trim($_POST['email']);
    $new_phone = trim($_POST['phone']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_new_password']);

    if ($new_full_name === '' || $new_email === '' || $new_phone === '') {
        $alerts[] = ['type' => 'danger', 'msg' => 'Please fill all required fields.'];
    } else {
        // Check email uniqueness
        $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ?");
        $chk->bind_param("si", $new_email, $user_id);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();

        if ($exists) {
            $alerts[] = ['type' => 'warning', 'msg' => 'Email already exists.'];
        } else {

            $upUser = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE user_id=?");
            $upUser->bind_param("sssi", $new_full_name, $new_email, $new_phone, $userId);
            $upUser->execute();
            $upUser->close();

            $full_name = $new_full_name;
            $email = $new_email;
            $phone = $new_phone;

            $alerts[] = ['type' => 'success', 'msg' => 'Profile updated successfully.'];

            // Password update
            if ($new_password !== '' && $confirm_password !== '') {
                if ($new_password !== $confirm_password) {
                    $alerts[] = ['type' => 'danger', 'msg' => 'Passwords do not match.'];
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $up = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
                    $up->bind_param("si", $hashed, $user_id);
                    $up->execute();
                    $up->close();
                }
            }
        }
    }
}
// FETCH DRIVER AVERAGE RATING
$avg_rating = 0;

$rateStmt = $conn->prepare("
    SELECT AVG(r.rating) AS avg_rate
    FROM reviews r
    JOIN trips t ON r.trip_id = t.trip_id
    WHERE t.driver_id = ?
");
$rateStmt->bind_param("i", $driver_id);
$rateStmt->execute();
$rateResult = $rateStmt->get_result();

if ($rateResult && $rateResult->num_rows > 0) {
    $row = $rateResult->fetch_assoc();
    $avg_rating = round($row['avg_rate'], 2);
}
$rateStmt->close();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Driver Profile | UniRide</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
<script src="../js/sidebar.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>.info-label{color:#6c757d}
   
</style>
</head>
<body>

<div class="dashboard-container">
    <driver-sidebar></driver-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>

    <div class="main-content">
        <h2 class="mb-3">My Profile</h2>

        <?php foreach ($alerts as $a): ?>
            <div class="alert alert-<?php echo h($a['type']); ?> alert-dismissible fade show">
                <?php echo h($a['msg']); ?>
                <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Account Details</h5>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="info-label">Full Name</div>
                        <div><?php echo h($full_name); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">Email</div>
                        <div><?php echo h($email); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">Phone</div>
                        <div><?php echo h($phone); ?></div>
                    </div>

                    <div class="col-md-4">
                        <div class="info-label">Driving License File</div>
                        <?php if ($driving_license): ?>
                            <a href="../<?php echo h($driving_license); ?>" target="_blank"><i class="fas fa-file"></i> View File</a>
                        <?php else: ?>
                            <span class="text-muted">Not uploaded</span>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <div class="info-label">Official Document</div>
                        <?php if ($official_doc): ?>
                            <a href="../<?php echo h($official_doc); ?>" target="_blank"><i class="fas fa-file"></i> View Document</a>
                        <?php else: ?>
                            <span class="text-muted">Not uploaded</span>
                        <?php endif; ?>
                    </div>
                

                    <div class="col-md-4">
                        <div class="info-label">Average Rating</div>
                        <div>
                            <?php if ($avg_rating > 0): ?>
                                <span class="badge bg-warning text-dark">
                                    ★ <?php echo $avg_rating; ?> / 5
                                </span>
                            <?php else: ?>
                                <span class="text-muted">No ratings yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
    
                    <div class="col-md-4">
                        <a href="reviews.php" class="btn btn-warning">
                            <i class="fas fa-star"></i> View All Reviews
                        </a>
                    </div>

                    <!-- New button to go to Vehicles page -->
                    <div class="col-md-4">
                        <a href="vehicles.php" class="btn btn-secondary">
                            <i class="fas fa-car"></i> Manage My Vehicles
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <!-- Editable form -->
        <div class="card">
            <div class="card-body">

                <form method="post">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control"
                                   value="<?php echo h($full_name); ?>"
                                   <?php echo $status === 'approved' ? 'required' : 'readonly'; ?>>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?php echo h($phone); ?>"
                                   <?php echo $status === 'approved' ? 'required' : 'readonly'; ?>>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo h($email); ?>"
                                   <?php echo $status === 'approved' ? 'required' : 'readonly'; ?>>
                        </div>
                        <div class="col-md-6"></div>
                        <div class="col-md-6">
                            <label class="form-label">New Password (optional)</label>
                            <input type="password" name="new_password" class="form-control"
                                   <?php echo $status === 'approved' ? '' : 'readonly'; ?>>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_new_password" class="form-control"
                                   <?php echo $status === 'approved' ? '' : 'readonly'; ?>>
                        </div>

                        <?php if ($status === 'approved'): ?>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        <?php endif; ?>

                    </div>
                </form>

            </div>
        </div>

    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const hamburger = document.querySelector('.hamburger');
        const navLinks = document.querySelector('.sidebar');

        hamburger.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });

        // Close mobile menu when clicking a link
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
            });
        });
    });
    </script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
