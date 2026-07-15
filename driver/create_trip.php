<?php
include '../db_connection.php';
include '../session_check.php';
if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}
function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
$alerts = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_location = trim($_POST['start_location']);
    $destination = trim($_POST['destination']);
    $trip_date = $_POST['trip_date'];
    $trip_time = $_POST['trip_time'];
    $price = floatval($_POST['price']);
    $max_seats = intval($_POST['seats']);
    $trip_type = $_POST['trip_type'];
    $vehicle_id = intval($_POST['vehicle_id']);
    $success = false;

    if ($trip_type === 'one-time') {
        $trip_date_generated = $trip_date;
        $sql = "INSERT INTO trips (
                    driver_id, vehicle_id, user_id, start_location, destination, trip_date, trip_time,
                    price, max_seats, available_seats,  trip_status, created_at
                ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'upcoming', NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssdii", $driver_id, $vehicle_id, $start_location, $destination, $trip_date_generated, $trip_time, $price, $max_seats, $max_seats);
        $success = $stmt->execute();
        $stmt->close();

    } else {
        $start_date = $_POST['repeat_start_date'] ?? '';
        $end_date = $_POST['repeat_end_date'] ?? '';
        $days = isset($_POST['days']) ? array_map('intval', $_POST['days']) : [];

        if ($start_date !== '' && $end_date !== '' && !empty($days)) {
            $from = new DateTime($start_date);
            $to = new DateTime($end_date);

            if ($from <= $to) {
                $current = clone $from;
                while ($current <= $to) {
                    $weekday = (int)$current->format('w');
                    if (in_array($weekday, $days, true)) {
                        $trip_date_generated = $current->format('Y-m-d');

                        $sql = "INSERT INTO trips (
                                    driver_id, vehicle_id, user_id, start_location, destination, trip_date, trip_time,
                                    price, max_seats, available_seats, trip_status, created_at
                                ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'upcoming', NOW())";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iissssdii", $driver_id, $vehicle_id, $start_location, $destination, $trip_date_generated, $trip_time, $price, $max_seats, $max_seats);
                        $success = $stmt->execute();
                        $stmt->close();
                    }
                    $current->modify('+1 day');
                }
            }
        }
    }

    if ($success) {
        $message = "Trip(s) created successfully!";
    }
}

// Fetch vehicles for the logged-in driver
$vehicles = [];
$vstmt = $conn->prepare("SELECT vehicle_id, make, model, year, plate_number FROM vehicles WHERE driver_id = ?");
$vstmt->bind_param("i", $driver_id);
$vstmt->execute();
$vres = $vstmt->get_result();
while ($v = $vres->fetch_assoc()) {
    $vehicles[] = $v;
}
$vstmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Trip - UniRide Driver Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <script src="../js/sidebar.js"></script>
    
    </style>
</head>
<body>
<div class="dashboard-container">
    <driver-sidebar></driver-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>
    <div class="main-content">
        <h2 class="mb-4">Create New Trip</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo h($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" id="tripForm">
                <div class="mb-3">
                    <label class="form-label">Start Location</label>
                    <input type="text" class="form-control" name="start_location" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Destination</label>
                    <input type="text" class="form-control" name="destination" required placeholder="e.g., MUBS, LIU">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Trip Date</label>
                        <input type="date" class="form-control" name="trip_date" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Departure Time</label>
                        <input type="time" class="form-control" name="trip_time" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Price ($)</label>
                        <input type="number" class="form-control" name="price" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Available Seats</label>
                        <input type="number" class="form-control" name="seats" required>
                    </div>
                </div>

                <!-- Vehicle Selection -->
                <div class="mb-3">
                    <label class="form-label">Select Vehicle</label>
                    <select class="form-select" name="vehicle_id" required>
                        <option value="">-- Choose Vehicle --</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo $v['vehicle_id']; ?>">
                                <?php echo h("{$v['make']} {$v['model']} ({$v['year']}) - {$v['plate_number']}"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Trip Type</label>
                    <select class="form-select" name="trip_type" id="tripType">
                        <option value="one-time">One-Time Trip</option>
                        <option value="weekly">Weekly Trip</option>
                    </select>
                </div>

                <div id="weeklyOptions" style="display: none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date (From)</label>
                            <input type="date" class="form-control" name="repeat_start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date (To)</label>
                            <input type="date" class="form-control" name="repeat_end_date">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Days of Week</label>
                        <?php
                        $days = [
                            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'
                        ];
                        foreach ($days as $val => $label):
                        ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="days[]" value="<?php echo $val; ?>">
                                <label class="form-check-label"><?php echo $label; ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Create Trip</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('tripType').addEventListener('change', function() {
    document.getElementById('weeklyOptions').style.display =
        this.value === 'weekly' ? 'block' : 'none';
});
</script>
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
</body>
</html>
