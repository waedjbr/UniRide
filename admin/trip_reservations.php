<?php
include '../db_connection.php';
include '../session_check.php';
include '../send_email.php';
include '../update_trip_statuses.php';

if ($userRole !== 1) {
    header("Location: ../login.php");
    exit;
}

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$alerts = [];

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
    $trip = $tstmt->get_result()->fetch_assoc();
    $tstmt->close();

    if (!$trip) {
        $alerts[] = ['type' => 'warning', 'message' => 'Trip not found.'];
    }

    /* ---------------------------------------------------------
       CANCEL RESERVATION (ADMIN)
    --------------------------------------------------------- */
    if (
        $trip &&
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        ($_POST['action'] ?? '') === 'cancel_reservation'
    ) {

        $resId = (int)($_POST['res_id'] ?? 0);

        if ($trip['trip_status'] !== 'Upcoming') {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Reservations can only be cancelled for upcoming trips.'
            ];
        } else {

            // Fetch reservation + rider
            $stmt = $conn->prepare("
                SELECT 
                    r.res_id,
                    r.seats_reserved,
                    r.status,
                    r.location,
                    u.user_id,
                    u.full_name,
                    u.email
                FROM reservations r
                JOIN users u ON u.user_id = r.user_id
                WHERE r.res_id = ? AND r.trip_id = ?
            ");

            $stmt->bind_param("ii", $resId, $tripId);
            $stmt->execute();
            $resData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Fetch driver info
            $dstmt = $conn->prepare("
                SELECT u.user_id, u.full_name, u.email
                FROM trips t
                JOIN drivers d ON d.driver_id = t.driver_id
                JOIN users u ON u.user_id = d.user_id
                WHERE t.trip_id = ?
            ");
            $dstmt->bind_param("i", $tripId);
            $dstmt->execute();
            $driver = $dstmt->get_result()->fetch_assoc();
            $dstmt->close();

            if (!$resData) {
                $alerts[] = ['type' => 'warning', 'message' => 'Reservation not found.'];
            } elseif ($resData['status'] === 'cancelled') {
                $alerts[] = ['type' => 'warning', 'message' => 'Reservation already cancelled.'];
            } else {
                $title = "Reservation Cancelled by Admin";
                $message =
                    "Your reservation from {$trip['start_location']} to {$trip['destination']} "
                    . "on {$trip['trip_date']} at {$trip['trip_time']} "
                    . "has been cancelled by UniRide administration.";

                $driverTitle = "Reservation Cancelled by Admin";
                $driverMessage =
                    "A reservation on your trip from {$trip['start_location']} to {$trip['destination']} "
                    . "on {$trip['trip_date']} at {$trip['trip_time']} "
                    . "has been cancelled by UniRide administration.";

                $conn->begin_transaction();
                try {
                    // Cancel reservation
                    $stmt = $conn->prepare("
                        UPDATE reservations
                        SET status = 'cancelled'
                        WHERE res_id = ?
                    ");
                    $stmt->bind_param("i", $resId);
                    $stmt->execute();
                    $stmt->close();


                    // Restore seats
                    $stmt = $conn->prepare("
                        UPDATE trips
                        SET available_seats = available_seats + ?
                        WHERE trip_id = ?
                    ");
                    $stmt->bind_param("ii", $resData['seats_reserved'], $tripId);
                    $stmt->execute();
                    $stmt->close();


                    // Rider notification
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, for_role)
                        VALUES (?, ?, ?, 'Trip', 'Rider')
                    ");
                    $stmt->bind_param("iss", $resData['user_id'], $title, $message);
                    $stmt->execute();
                    $stmt->close();


                    // Driver notification
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, for_role)
                        VALUES (?, ?, ?, 'Trip', 'Driver')
                    ");
                    $stmt->bind_param("iss", $driver['user_id'], $driverTitle, $driverMessage);
                    $stmt->execute();
                    $stmt->close();

                    //Commit DB changes
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
                    $resData['email'],
                    $resData['full_name'],
                    'Your Trip Reservation Was Cancelled',
                    "
                    <p>Dear <strong>{$resData['full_name']}</strong>,</p>

                    <p>
                        Your reservation for the trip
                        <strong>{$trip['start_location']} → {$trip['destination']}</strong><br>
                        on <strong>{$trip['trip_date']}</strong> at <strong>{$trip['trip_time']}</strong>
                        has been <span style='color:red;'>cancelled</span> by the UniRide administration.
                    </p>

                    <p>If you have questions, please contact support.</p>

                    <p>Regards,<br><strong>UniRide Admin Team</strong></p>
                    "
                );
                //driver email
                sendEmail(
                    $driver['email'],
                    $driver['full_name'],
                    'Reservation Cancelled by Admin',
                    "
                    <p>Dear <strong>{$driver['full_name']}</strong>,</p>

                    <p>
                        A reservation for your trip
                        <strong>{$trip['start_location']} → {$trip['destination']}</strong><br>
                        on <strong>{$trip['trip_date']}</strong> at <strong>{$trip['trip_time']}</strong>
                        has been cancelled by the UniRide administration.
                    </p>

                    <p>No action is required from you.</p>

                    <p>Regards,<br><strong>UniRide Admin Team</strong></p>
                    "
                );

                $alerts[] = [
                    'type' => 'success',
                    'message' => 'Reservation cancelled successfully. Rider notified.'
                ];
            }
        }
    }

    // Fetch reservations
    if ($trip) {
        $stmt = $conn->prepare("
            SELECT 
                r.res_id,
                r.seats_reserved,
                r.status,
                r.location,
                u.user_id,
                u.full_name AS rider_name,
                u.email AS rider_email
            FROM reservations r
            JOIN users u ON u.user_id = r.user_id
            WHERE r.trip_id = ?
            ORDER BY r.reservation_date DESC
        ");

        $stmt->bind_param("i", $tripId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $reservations[] = $row;
        }
        $stmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Trip Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="../js/sidebar.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
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

    <admin-sidebar></admin-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>

    <div class="main-content">
        <div class="dashboard-title d-flex align-items-center justify-content-between mb-3">
            <h2 class="mb-0">Trip Reservations</h2> 

            <a href="trips.php" class="btn btn-outline-secondary btn-sm back">
                <i class="fas fa-arrow-left"></i> Back to Trips
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
                            <thead >
                            <tr>
                                <th>ID</th>
                                <th>Rider</th>
                                <th>Email</th>
                                <th>Seats</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Action</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($reservations as $r): ?>
                                <tr>
                                    <td><?php echo h($r['res_id']); ?></td>
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
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <?php if ($trip['trip_status'] === 'Upcoming' && $r['status'] === 'confirmed'): ?>
                                                <form method="post" onsubmit="return confirm('Cancel this reservation?');" class="m-0">
                                                    <input type="hidden" name="action" value="cancel_reservation">
                                                    <input type="hidden" name="res_id" value="<?php echo $r['res_id']; ?>">
                                                    <button class="btn btn-danger btn-sm">Cancel</button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- View Profile Button -->
                                            <a href="view_student.php?id=<?php echo h($r['user_id']); ?>" class="btn btn-primary btn-sm">
                                                View Profile
                                            </a>
                                        </div>
                                    </td>
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
