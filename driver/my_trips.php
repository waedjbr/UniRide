<?php
include '../db_connection.php';
include '../session_check.php';
include '../send_email.php';
include '../update_trip_statuses.php';


if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
$alerts = [];
/*DELETE or CANCEL LOGIC + NOTIFICATIONS + EMAIL*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_or_cancel') {
    $trip_id = (int)($_POST['trip_id'] ?? 0);
    /* Fetch trip info */
    $stmt = $conn->prepare("
        SELECT start_location, destination, trip_date, trip_time 
        FROM trips 
        WHERE trip_id = ? AND driver_id = ?
    ");
    $stmt->bind_param("ii", $trip_id, $driver_id);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$trip) {
        $alerts[] = ['type' => 'danger', 'message' => 'Trip not found.'];
        return;
    }
    /* Fetch CONFIRMED riders FIRST */
    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.email
        FROM reservations r
        JOIN users u ON u.user_id = r.user_id
        WHERE r.trip_id = ? AND r.status = 'confirmed'
    ");
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $riders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $hasReservations = count($riders) > 0;
    /* ---------- TRANSACTION ---------- */
    $conn->begin_transaction();
    try {
        if ($hasReservations) {
            /* Cancel trip */
            $stmt = $conn->prepare("
                UPDATE trips 
                SET trip_status = 'Cancelled' 
                WHERE trip_id = ?
            ");
            $stmt->bind_param("i", $trip_id);
            $stmt->execute();
            $stmt->close();

            /* Cancel reservations */
            $stmt = $conn->prepare("
                UPDATE reservations 
                SET status = 'cancelled' 
                WHERE trip_id = ?
            ");
            $stmt->bind_param("i", $trip_id);
            $stmt->execute();
            $stmt->close();

            /* Notify riders */
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, for_role)
                VALUES (?, ?, ?, 'Trip', 'Rider')
            ");

            foreach ($riders as $r) {
                $title = 'Trip Cancelled';
                $message = "Your trip from {$trip['start_location']} to {$trip['destination']} on {$trip['trip_date']} at {$trip['trip_time']} has been cancelled.";

                $notifStmt->bind_param("iss", $r['user_id'], $title, $message);
                $notifStmt->execute();
            }

            $notifStmt->close();

            $conn->commit();

            /* SEND EMAIL AFTER COMMIT */
            foreach ($riders as $r) {
                sendEmail(
                    $r['email'],
                    $r['full_name'],
                    'Trip Cancelled',
                    "
                    <p>Hello <strong>{$r['full_name']}</strong>,</p>
                    <p>Your trip from <strong>{$trip['start_location']} → {$trip['destination']}</strong> has been cancelled.</p>
                    <p>Date: {$trip['trip_date']}<br>Time: {$trip['trip_time']}</p>
                    <p>Please check your UniRide dashboard.</p>
                    "
                );
            }

            $alerts[] = [
                'type' => 'warning',
                'message' => 'Trip cancelled. Riders notified and emailed.'
            ];
        } else {

            /* No reservations → soft delete */
            $stmt = $conn->prepare("
                UPDATE trips 
                SET trip_status = 'Deleted' 
                WHERE trip_id = ?
            ");
            $stmt->bind_param("i", $trip_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $alerts[] = [
                'type' => 'success',
                'message' => 'Trip deleted successfully.'
            ];
        }

    } catch (Exception $e) {
        $conn->rollback();
        $alerts[] = [
            'type' => 'danger',
            'message' => 'Operation failed. Please try again.'
        ];
    }
}

/* FETCH TRIPS */
$sql = "
SELECT t.*, 
       v.image_path,
       (t.max_seats - t.available_seats) AS reserved_seats,
       COALESCE(rv.avg_rating, NULL) AS avg_rating,
       COALESCE(rv.total_reviews, 0) AS total_reviews
FROM trips t
LEFT JOIN vehicles v ON v.vehicle_id = t.vehicle_id
LEFT JOIN (
    SELECT trip_id, ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total_reviews
    FROM reviews
    GROUP BY trip_id
) rv ON rv.trip_id = t.trip_id
WHERE t.driver_id = ? AND t.trip_status <> 'Deleted'
ORDER BY
    CASE t.trip_status
        WHEN 'Ongoing' THEN 1
        WHEN 'Upcoming'  THEN 2
        WHEN 'Completed' THEN 3
        WHEN 'Cancelled' THEN 4
    END,
    t.trip_date ASC,
    t.trip_time ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();

$trips = [];
while ($row = $result->fetch_assoc()) {
    $trips[] = $row;
}
$stmt->close();

/* ---------------------------------------------------------
   RENDER FUNCTION
--------------------------------------------------------- */
function renderTripCard($t)
{
    $reserved = (int)$t['reserved_seats'];
    $available = (int)$t['available_seats'];
    $earned = $reserved * (float)$t['price'];
    $tripDate = date('M d, Y', strtotime($t['trip_date']));
    $tripTime = date('h:i A', strtotime($t['trip_time']));

    ob_start(); ?>
    <div class="card trip-card shadow-sm h-100">
        <img src="../<?php echo h($t['image_path']); ?>" alt="Vehicle">

        <div class="card-body">
            <h5><?php echo h($t['start_location']); ?> → <?php echo h($t['destination']); ?></h5>

            <p><strong>Date:</strong> <?php echo h($tripDate); ?></p>   
            <p><strong>Time:</strong> <?php echo h($tripTime); ?></p>
            <p><strong>Price:</strong> $<?php echo h($t['price']); ?></p>

            <?php if (in_array($t['trip_status'], ['Upcoming','Ongoing'])): ?>
                <p><strong>Reserved:</strong> <?php echo $reserved; ?></p>
                <p><strong>Available:</strong> <?php echo $available; ?></p>
            <?php endif; ?>

            <?php if ($t['trip_status'] === 'Completed'): ?>
                <p><strong>Total Earned:</strong> $<?php echo number_format($earned,2); ?></p>
                <p><strong>Rating:</strong>
                    <?php echo $t['total_reviews'] > 0
                        ? '⭐ '.$t['avg_rating'].' ('.$t['total_reviews'].' reviews)'
                        : 'No reviews'; ?>
                </p>
            <?php endif; ?>

            <p><strong>Status:</strong> <?php echo h($t['trip_status']); ?></p>

            <a href="trip_reservations.php?trip_id=<?php echo $t['trip_id']; ?>" class="btn btn-primary w-100 mb-2">
                <i class="fas fa-users"></i> View Reservations
            </a>

            <?php if ($t['trip_status'] === 'Completed'): ?>
                <a href="trip_reviews.php?trip_id=<?php echo $t['trip_id']; ?>" class="btn btn-warning w-100 mb-2">
                    <i class="fas fa-comment"></i> View Reviews
                </a>
            <?php endif; ?>

            <?php if (!in_array($t['trip_status'], ['Completed','Cancelled'])): ?>
                <form method="post">
                    <input type="hidden" name="trip_id" value="<?php echo $t['trip_id']; ?>">
                    <input type="hidden" name="action" value="delete_or_cancel">
                    <button class="btn btn-danger w-100"><i class="fas fa-trash"></i> Delete / <i class="fas fa-ban"></i> Cancel</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Trips | UniRide</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="../js/sidebar.js"></script>
<style>
.trip-card img { height:160px; object-fit:cover; }
.filter-tabs { background:#fff; padding:1rem; border-radius:10px; display:flex; gap:.75rem; flex-wrap:wrap; box-shadow:0 0 20px rgba(0,0,0,.05); margin-bottom:1.5rem;}
.filter-btn { border:2px solid #dee2e6; padding:.6rem 1.5rem; border-radius:25px; background:#fff; cursor:pointer; font-weight:500; color:#6c757d;}
.filter-btn.active { background:#028a99; color:#fff; border-color:#028a99;}
.search-input { padding:.75rem; border-radius:10px; border:1px solid #dee2e6; max-width:400px; width:100%; }

</style>
</head>

<body>
<div class="dashboard-container">
    <driver-sidebar></driver-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>
    <div class="main-content">
        <h2 class="mb-3">My Trips</h2>

        <?php foreach ($alerts as $a): ?>
            <div class="alert alert-<?php echo $a['type']; ?> alert-dismissible fade show">
                <?php echo h($a['message']); ?>
                <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <button class="filter-btn active" data-filter="all"><i class="fas fa-list"></i> All</button>
            <button class="filter-btn" data-filter="Upcoming"><i class="fas fa-calendar"></i></i> Upcoming</button>
            <button class="filter-btn" data-filter="Ongoing"><i class="fas fa-spinner"></i> Ongoing</button>
            <button class="filter-btn" data-filter="Completed"><i class="fas fa-check-circle"></i> Completed</button>
            <button class="filter-btn" data-filter="Cancelled"><i class="fas fa-times-circle"></i> Cancelled</button>
        </div>

        <input type="text" id="searchInput" class="search-input mb-4" placeholder="Search trips...">

        <div class="row" id="tripList">
            <?php foreach ($trips as $t): ?>
                <div class="col-md-4 mb-3 trip-item" data-status="<?php echo h($t['trip_status']); ?>">
                    <?php echo renderTripCard($t); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
const buttons = document.querySelectorAll('.filter-btn');
const items = document.querySelectorAll('.trip-item');

buttons.forEach(btn => {
    btn.onclick = () => {
        buttons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const f = btn.dataset.filter;
        items.forEach(i => {
            i.style.display = (f === 'all' || i.dataset.status === f) ? '' : 'none';
        });
    };
});

document.getElementById('searchInput').addEventListener('keyup', function () {
    const v = this.value.toLowerCase();
    items.forEach(i => {
        i.style.display = i.textContent.toLowerCase().includes(v) ? '' : 'none';
    });
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
