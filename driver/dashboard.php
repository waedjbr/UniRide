<?php
include '../db_connection.php';
include '../session_check.php';
include '../update_trip_statuses.php';

function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}

// Fetch driver info 
$stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?"); 
$stmt->bind_param("i", $user_id); $stmt->execute(); 
$stmt->bind_result($full_name); 
$stmt->fetch(); 
$stmt->close();

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) FROM trips WHERE driver_id = ?");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$stmt->bind_result($total_trips_created);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM reservations r JOIN trips t ON r.trip_id = t.trip_id WHERE t.driver_id = ?");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$stmt->bind_result($total_reservations);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM trips WHERE driver_id = ? AND trip_status = 'completed'");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$stmt->bind_result($completed_trips);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM reservations r JOIN trips t ON r.trip_id = t.trip_id WHERE t.driver_id = ? AND r.status = 'pending'");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$stmt->bind_result($pending_reservations);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM trips 
    WHERE driver_id = ? AND trip_status = 'cancelled'
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$stmt->bind_result($cancelled_trips);
$stmt->fetch();
$stmt->close();

// Notification
$stmt = $conn->prepare("
    SELECT notification_id, title, message, created_at
    FROM notifications
    WHERE user_id = ? AND is_read = 0 AND (for_role = ? OR for_role = 'Both')
    ORDER BY created_at DESC
    LIMIT 5
");
$role = $isDriver ? 'Driver' : 'Rider';
$stmt->bind_param("is", $user_id, $role);
$stmt->execute();
$stmt->bind_result($nt_id, $nt_title, $nt_message, $nt_time);

$notifications = [];
while ($stmt->fetch()) {
    $notifications[] = [
        'id' => $nt_id,
        'title' => $nt_title,
        'message' => $nt_message,
        'time' => $nt_time
    ];
}
$stmt->close();


// Mark notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['read_notification_id'])) {
    $nid = (int)$_POST['read_notification_id'];

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $nid, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: dashboard.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Driver Dashboard - UniRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <script src="../js/sidebar.js"> </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --primary-color: #028a99;
    }

    .stat-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0,0,0,0.05);
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(2,138,153,0.12);
        color: var(--primary-color);
        font-size: 1.5rem;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
    }

    .stat-label {
        margin: 0;
        color: #6c757d;
    }

    .notification-header {
        background-color: #028a99;
        color: #fff;
    }

    </style>

</head>
<body>

<div class="dashboard-container">

    <!-- Sidebar -->
    <driver-sidebar></driver-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>
    <div class="main-content">

    <h2 class="mb-4">Welcome, <?php echo h($full_name); ?></h2>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">

        <div class="col-md-6 col-lg-3">
            <div class="stat-card bg-white">
                <div class="stat-icon">
                    <i class="fas fa-route"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo h($total_trips_created); ?></div>
                    <p class="stat-label">Trips Created</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="stat-card bg-white">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo h($total_reservations); ?></div>
                    <p class="stat-label">Total Reservations</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="stat-card bg-white">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo h($completed_trips); ?></div>
                    <p class="stat-label">Completed Trips</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="stat-card bg-white">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo h($cancelled_trips); ?></div>
                    <p class="stat-label">Cancelled Trips</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Notifications -->
    <div class="card">
        <div class="card-header notification-header">
            <strong>Notifications</strong>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php if (empty($notifications)): ?>
                    <li class="list-group-item text-center py-3">
                        No notifications yet.
                    </li>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?php echo h($n['title']); ?></strong><br>
                            <span><?php echo h($n['message']); ?></span><br>
                            <small class="text-muted">
                                <?php echo date("M d, Y h:i A", strtotime($n['time'])); ?>
                            </small>
                        </div>

                        <form method="post" class="ms-2">
                            <input type="hidden" name="read_notification_id" value="<?php echo $n['id']; ?>">
                            <button type="submit"
                                    class="btn btn-sm btn-light text-danger"
                                    title="Mark as read">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
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
