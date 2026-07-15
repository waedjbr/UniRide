<?php
include '../db_connection.php';
include '../session_check.php';
include '../send_email.php';
include '../update_trip_statuses.php';

if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}

$alerts = [];

function h($v) { 
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); 
}

// Trip ID
$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
$trip = null;
$reservations = [];

if ($tripId <= 0) {
    $alerts[] = ['type' => 'danger', 'message' => 'Invalid trip ID.'];
} else {

    // Fetch trip
    $tstmt = $conn->prepare("
        SELECT trip_id, start_location, destination, trip_date, trip_time, 
               max_seats, available_seats, trip_status
        FROM trips
        WHERE trip_id = ?
    ");
    $tstmt->bind_param("i", $tripId);
    $tstmt->execute();
    $tres = $tstmt->get_result();

    if ($tres && $tres->num_rows > 0) {
        $trip = $tres->fetch_assoc();
    } else {
        $alerts[] = ['type' => 'warning', 'message' => 'Trip not found.'];
    }
    $tstmt->close();

    //--------------------------------------------------------
    // CANCEL RESERVATION LOGIC 
    if ( $trip && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_reservation')
    {
        $resId = (int)($_POST['res_id'] ?? 0);
        if (strtolower($trip['trip_status']) !== 'upcoming') {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'This reservation cannot be cancelled because the trip is not Upcoming.'
            ];
        } else {
            // Fetch reservation + rider info
            $selStmt = $conn->prepare("
                SELECT r.seats_reserved, r.status, u.user_id, u.email, u.full_name
                FROM reservations r
                JOIN users u ON u.user_id = r.user_id
                WHERE r.res_id = ? AND r.trip_id = ?
            ");
            $selStmt->bind_param("ii", $resId, $tripId);
            $selStmt->execute();
            $resData = $selStmt->get_result()->fetch_assoc();
            $selStmt->close();
            if (!$resData) {
                $alerts[] = ['type' => 'warning', 'message' => 'Reservation not found.'];
            } elseif ($resData['status'] === 'cancelled') {
                $alerts[] = ['type' => 'warning', 'message' => 'This reservation is already cancelled.'];
            } else {
                $seatsReserved = (int)$resData['seats_reserved'];
                $riderId   = (int)$resData['user_id'];
                $riderEmail = $resData['email'];
                $riderName  = $resData['full_name'];
                // Trip details
                $tripFrom = $trip['start_location'];
                $tripTo   = $trip['destination'];
                $tripDate = date('M d, Y', strtotime($trip['trip_date']));
                $tripTime = date('h:i A', strtotime($trip['trip_time']));
                $conn->begin_transaction();
                try {
                    // Cancel reservation
                    $updRes = $conn->prepare("
                        UPDATE reservations
                        SET status = 'cancelled'
                        WHERE res_id = ?
                    ");
                    $updRes->bind_param("i", $resId);
                    $updRes->execute();
                    $updRes->close();
                    // Restore seats
                    $updSeats = $conn->prepare("
                        UPDATE trips
                        SET available_seats = available_seats + ?
                        WHERE trip_id = ?
                    ");
                    $updSeats->bind_param("ii", $seatsReserved, $tripId);
                    $updSeats->execute();
                    $updSeats->close();
                    // Notification
                    $notifTitle = "Trip Reservation Cancelled";
                    $notifMessage = "Your reservation from {$tripFrom} to {$tripTo} on {$tripDate} at {$tripTime} has been cancelled by the driver.";
                    $notifStmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, for_role)
                        VALUES (?, ?, ?, 'Trip', 'Rider')
                    ");
                    $notifStmt->bind_param("iss", $riderId, $notifTitle, $notifMessage);
                    $notifStmt->execute();
                    $notifStmt->close();
                    $conn->commit();  
                } catch (Exception $e) {
                    //Rollback everything on failure
                    $conn->rollback();
                    $alerts[] = [
                        'type' => 'danger',
                        'message' => 'Failed to cancel reservation. Please try again.'
                    ];
                    return;
                }
                    // Email
                    sendEmail(
                        $riderEmail,
                        $riderName,
                        'Trip Reservation Cancelled',
                        "
                        <p>Dear <strong>{$riderName}</strong>,</p>

                        <p>
                            Your reservation for the trip
                            <strong>{$tripFrom} → {$tripTo}</strong><br>
                            on <strong>{$tripDate}</strong> at <strong>{$tripTime}</strong>
                            has been <span style='color:red;'>cancelled</span> by the driver.
                        </p>

                        <p>Please check your UniRide dashboard for more details.</p>

                        <br>
                        <p>Regards,<br><strong>UniRide Support</strong></p>
                        "
                    );
                    $alerts[] = [
                        'type' => 'success',
                        'message' => 'Reservation cancelled successfully. Rider notified.'
                    ];
                    $trip['available_seats'] += $seatsReserved;

            }
        }
    }
    //--------------------------------------------------------
    // GET ALL RESERVATIONS
    //--------------------------------------------------------
    if ($trip) {
        $rstmt = $conn->prepare("
            SELECT r.res_id, u.full_name AS rider_name, u.email AS rider_email, 
                   r.location, r.status, r.seats_reserved, r.reservation_date
            FROM reservations r
            JOIN users u ON u.user_id = r.user_id
            WHERE r.trip_id = ?
            ORDER BY r.reservation_date DESC
        ");
        $rstmt->bind_param("i", $tripId);
        $rstmt->execute();
        $rres = $rstmt->get_result();

        while ($row = $rres->fetch_assoc()) {
            $reservations[] = $row;
        }

        $rstmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Trip Reservations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <script src="../js/sidebar.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body { background-color: #f8f9fa; }
        .dashboard-title { display: flex; align-items: center; justify-content: space-between; }
        .trip-summary .item { margin-bottom: .5rem; }
        .reservations-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .reservations-table {
            margin: 0;
            width: 100%;
        }
        .reservations-table thead {
            background: linear-gradient(135deg, #028a99 0%, #02a8b9 100%) ;
            color: white;
        }
        .reservations-table th {
            padding: 1rem;
            font-weight: 600;
            border: none;
            white-space: nowrap;
        }
        .reservations-table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .reservations-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .reservations-table tbody tr:last-child td {
            border-bottom: none;
        }
        @media (max-width: 768px) {
        .reservations-table th {
            padding: 0.5rem;

        }
        .reservations-table td {
            padding: 0.5rem;

        }
    }
    .table-scroll {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch; /* smooth scrolling on mobile */
}
    </style>
</head>

<body>
<div class="dashboard-container">

    <driver-sidebar></driver-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>

    <div class="main-content">
        <div class="dashboard-title d-flex align-items-center justify-content-between mb-3">
            <h2 class="mb-0">Trip Reservations</h2> 

            <a href="my_trips.php" class="btn btn-outline-secondary btn-sm back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>


        <!-- Alerts -->
        <?php foreach ($alerts as $a): ?>
            <div class="alert alert-<?php echo h($a['type']); ?> alert-dismissible fade show">
                <?php echo h($a['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <!-- Trip Summary -->
        <?php if ($trip): ?>
            <div class="card mb-3">
                <div class="card-body trip-summary">
                    <div class="row">
                        <div class="col-md-2 item"><strong>Trip ID:</strong><br><?php echo h($trip['trip_id']); ?></div>
                        <div class="col-md-2 item"><strong>Start:</strong><br><?php echo h($trip['start_location']); ?></div>
                        <div class="col-md-2 item"><strong>Destination:</strong><br><?php echo h($trip['destination']); ?></div>
                        <div class="col-md-2 item"><strong>Date:</strong><br><?php echo h($trip['trip_date']); ?></div>
                        <div class="col-md-2 item"><strong>Time:</strong><br><?php echo h($trip['trip_time']); ?></div>
                        <div class="col-md-2 item">
                            <strong>Status:</strong><br>
                            <?php
                                // Convert status to lowercase for class matching
                                $statusClass = 'badge-' . strtolower($trip['trip_status']); 
                            ?>
                            <span class="badge-custom <?php echo $statusClass; ?>">
                                <?php echo h($trip['trip_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Reservations -->

                <?php if ($trip && empty($reservations)): ?>
                    <p class="text-center text-muted">No reservations yet.</p>

                <?php else: ?>
                    <div class="reservations-table-container table-scroll">
                        <table class="table-hover align-middle reservations-table">
                            <thead class="table-light">
                            <tr>
                                <th>Rider</th>
                                <th>Email</th>
                                <th>Seats</th>
                                <th>Status</th>
                                <th>Location</th>
                                <?php if ($trip['trip_status'] === 'Upcoming'): ?>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($reservations as $r): ?>
                                <tr>
                                    <td><?php echo h($r['rider_name']); ?></td>
                                    <td><a href="mailto:<?php echo h($r['rider_email']); ?>"><?php echo h($r['rider_email']); ?></a></td>
                                    <td><?php echo h($r['seats_reserved']); ?></td>

                                    <td>
                                        <?php
                                            $riderBadgeClass = ($r['status'] === 'confirmed') ? 'badge-approved' : 'badge-cancelled';
                                        ?>
                                        <span class="badge-custom <?php echo $riderBadgeClass; ?>">
                                            <?php echo ucfirst($r['status']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (trim($r['location'])): ?>
                                            <a href="<?php echo h($r['location']); ?>" target="_blank">Open Map</a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>

                                    <?php if ($trip['trip_status'] === 'Upcoming'): ?>
                                        <td>
                                            <?php if ($r['status'] === 'confirmed'): ?>
                                                <form method="post" onsubmit="return confirm('Cancel this reservation?');">
                                                    <input type="hidden" name="action" value="cancel_reservation">
                                                    <input type="hidden" name="res_id" value="<?php echo $r['res_id']; ?>">
                                                    <button class="btn btn-danger btn-sm"><i class="fas fa-ban"></i> Cancel</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">No action</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>

                        </table>
                    </div>
                <?php endif; ?>

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
