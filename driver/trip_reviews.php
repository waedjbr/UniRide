<?php
include '../db_connection.php';
include '../session_check.php';

if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$alerts = [];

$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
$trip = null;
$reviews = [];
$avgRating = null;
$totalReviews = 0;

if ($tripId <= 0) {
    $alerts[] = ['type' => 'danger', 'message' => 'Invalid trip ID.'];
} else {
    // Fetch trip details (basic info shown at the top)
    $tstmt = $conn->prepare("SELECT trip_id, start_location, destination, trip_date, trip_time FROM trips WHERE trip_id = ?");
    $tstmt->bind_param("i", $tripId);
    $tstmt->execute();
    $tres = $tstmt->get_result();
    if ($tres && $tres->num_rows > 0) {
        $trip = $tres->fetch_assoc();
    } else {
        $alerts[] = ['type' => 'warning', 'message' => 'Trip not found.'];
    }
    $tstmt->close();

    if ($trip) {
        // Average rating + count
        $avgStmt = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE trip_id = ?");
        $avgStmt->bind_param("i", $tripId);
        $avgStmt->execute();
        $avgRes = $avgStmt->get_result();
        if ($avgRes && $avgRes->num_rows > 0) {
            $avgRow = $avgRes->fetch_assoc();
            $avgRating = $avgRow['avg_rating'] !== null ? (float)$avgRow['avg_rating'] : null;
            $totalReviews = (int)$avgRow['total_reviews'];
        }
        $avgStmt->close();

        // Fetch all reviews for the trip
        $rstmt = $conn->prepare("
            SELECT r.review_id, r.rating, r.comment, r.created_at, u.full_name
            FROM reviews r
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE r.trip_id = ?
            ORDER BY r.created_at DESC
        ");
        $rstmt->bind_param("i", $tripId);
        $rstmt->execute();
        $rres = $rstmt->get_result();
        if ($rres) {
            while ($row = $rres->fetch_assoc()) {
                $reviews[] = $row;
            }
        }
        $rstmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Trip Reviews</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <script src="../js/sidebar.js"> </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body { background-color: #f8f9fa; }
        .dashboard-title { display: flex; align-items: center; justify-content: space-between; }
        .rating-stars i { color: #ffc107; } 
        .review-card { border: 1px solid #e9ecef; border-radius: .5rem; padding: 1rem; background: #fff; }
        .review-meta { color: #6c757d; font-size: .875rem; }
        </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <driver-sidebar></driver-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-title mb-3">
            <h2 class="mb-0">Trip Reviews</h2>
            <div>
                <a href="my_trips.php" class="btn btn-outline-secondary btn-sm back">
                    <i class="fas fa-arrow-left"></i> Back to My Trips
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php foreach ($alerts as $a): ?>
            <div class="alert alert-<?php echo h($a['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo h($a['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>

        <!-- Trip Summary -->
        <?php if ($trip): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <strong>Trip ID:</strong><div><?php echo h($trip['trip_id']); ?></div>
                        </div>
                        <div class="col-md-3">
                            <strong>Start Location:</strong><div><?php echo h($trip['start_location']); ?></div>
                        </div>
                        <div class="col-md-3">
                            <strong>Destination:</strong><div><?php echo h($trip['destination']); ?></div>
                        </div>
                        <div class="col-md-2">
                            <strong>Date:</strong><div><?php echo h($trip['trip_date']); ?></div>
                        </div>
                        <div class="col-md-2">
                            <strong>Time:</strong><div><?php echo h($trip['trip_time']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Average Rating -->
        <?php if ($trip): ?>
            <div class="mb-3">
                <?php if ($totalReviews > 0 && $avgRating !== null): ?>
                    <div class="h6 mb-0">Average Rating: <span class="rating-stars">⭐</span> <?php echo h(number_format($avgRating, 1)); ?> / 5 (<?php echo h($totalReviews); ?> reviews)</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Reviews List -->
        <div class="card">
            <div class="card-body">
                <?php if ($trip && empty($reviews)): ?>
                    <div class="text-center text-muted">No reviews have been submitted for this trip yet.</div>
                <?php elseif ($trip): ?>
                    <div class="list-group">
                        <?php foreach ($reviews as $rev): ?>
                            <?php
                            $rating = max(1, min(5, (int)$rev['rating']));
                            $created = $rev['created_at'] ? date('M j, Y H:i', strtotime($rev['created_at'])) : '';
                            $fullName = trim($rev['full_name'] ?? '') !== '' ? $rev['full_name'] : 'Anonymous';
                            ?>
                            <div class="list-group-item review-card mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $rating): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="review-meta"><?php echo h($created); ?></div>
                                </div>
                                <div class="mt-2">
                                    <?php echo nl2br(h($rev['comment'] ?? '')); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">Unable to load trip information.</div>
                <?php endif; ?>
            </div>
        </div>
    </div> <!-- /.main-content -->
</div> <!-- /.dashboard-container -->
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