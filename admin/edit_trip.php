<?php
include '../db_connection.php';
include '../session_check.php';
include '../update_trip_statuses.php';

if ($userRole !== 1) {
    header("Location: ../login.php");
    exit;
}

function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/*VALIDATE TRIP ID*/
$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;

if ($tripId <= 0) {
    header("Location: trips.php?alert=Invalid+trip+ID&type=danger");
    exit;
}

/*FETCH TRIP*/
$stmt = $conn->prepare("
    SELECT *
    FROM trips
    WHERE trip_id = ?
");
$stmt->bind_param("i", $tripId);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trip) {
    header("Location: trips.php?alert=Trip+not+found&type=danger");
    exit;
}

/* ALLOW EDIT ONLY FOR UPCOMING */
if ($trip['trip_status'] !== 'Upcoming') {
    header("Location: trips.php?alert=Only+upcoming+trips+can+be+edited&type=warning");
    exit;
}

$alert = '';
$alertType = 'success';

/* HANDLE UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $start = trim($_POST['start_location']);
    $dest  = trim($_POST['destination']);
    $date  = $_POST['trip_date'];
    $time  = $_POST['trip_time'];
    $price = (float)$_POST['price'];
    $maxSeats = (int)$_POST['max_seats'];

    if (
        empty($start) || empty($dest) ||
        empty($date) || empty($time) ||
        $price <= 0 || $maxSeats <= 0
    ) {
        $alert = "All fields are required and must be valid.";
        $alertType = 'danger';
    } else {

        /* Prevent reducing seats below booked seats */
        $bookedSeats = $trip['max_seats'] - $trip['available_seats'];

        if ($maxSeats < $bookedSeats) {
            $alert = "Max seats cannot be less than already booked seats ($bookedSeats).";
            $alertType = 'danger';
        } else {

            $newAvailableSeats = $maxSeats - $bookedSeats;

            $stmt = $conn->prepare("
                UPDATE trips
                SET 
                    start_location = ?,
                    destination = ?,
                    trip_date = ?,
                    trip_time = ?,
                    price = ?,
                    max_seats = ?,
                    available_seats = ?
                WHERE trip_id = ?
            ");
            $stmt->bind_param(
                "ssssdiis",
                $start,
                $dest,
                $date,
                $time,
                $price,
                $maxSeats,
                $newAvailableSeats,
                $tripId
            );

            if ($stmt->execute()) {
                header("Location: trips.php?alert=Trip+updated+successfully&type=success");
                exit;
            } else {
                $alert = "Failed to update trip.";
                $alertType = 'danger';
            }
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit Trip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <script src="../js/sidebar.js"></script>
    
</head>

<body>
<div class="dashboard-container">
    <admin-sidebar></admin-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>

    <div class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2>Edit Trip</h2>
            <a href="trips.php" class="btn btn-outline-secondary btn-sm back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <?php if ($alert): ?>
            <div class="alert alert-<?php echo h($alertType); ?>">
                <?php echo h($alert); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="post">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Location</label>
                            <input type="text" name="start_location" class="form-control"
                                   value="<?php echo h($trip['start_location']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Destination</label>
                            <input type="text" name="destination" class="form-control"
                                   value="<?php echo h($trip['destination']); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Trip Date</label>
                            <input type="date" name="trip_date" class="form-control"
                                   value="<?php echo h($trip['trip_date']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Trip Time</label>
                            <input type="time" name="trip_time" class="form-control"
                                   value="<?php echo h($trip['trip_time']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" name="price" class="form-control"
                                   value="<?php echo h($trip['price']); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Max Seats</label>
                            <input type="number" name="max_seats" class="form-control"
                                   value="<?php echo h($trip['max_seats']); ?>" required>
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <small class="text-muted">
                                Already booked seats: <?php echo ($trip['max_seats'] - $trip['available_seats']); ?>
                            </small>
                        </div>
                    </div>

                    <button class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>

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
