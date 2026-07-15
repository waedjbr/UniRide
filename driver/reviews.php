<?php
include '../db_connection.php';
include '../session_check.php';
if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}


function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$reviews = [];

// Fetch all reviews for all trips of this driver
$stmt = $conn->prepare("
    SELECT 
        r.rating,
        r.comment,
        r.created_at,
        t.start_location,
        t.destination,
        t.trip_date,
        t.trip_time
    FROM reviews r
    JOIN trips t ON r.trip_id = t.trip_id
    WHERE t.driver_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Driver Reviews</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <script src="../js/sidebar.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body { background-color: #f8f9fa; }
        .dashboard-title { display: flex; align-items: center; justify-content: space-between; }
        .review-card { border-left: 5px solid #ffc107; }
        .stars i { color: #ffc107; }
        .no-reviews { padding: 40px; text-align: center; color: #777; }
        
    </style>
</head>

<body>

<div class="dashboard-container">

    <driver-sidebar></driver-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>

    <div class="main-content">
        <div class="dashboard-title mb-3">
            <h2 class="mb-3">My Reviews</h2>
            <div>
                <a href="profile.php" class="btn btn-outline-secondary btn-sm back">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
            </div>
        </div>

        <?php if (empty($reviews)): ?>

            <div class="card no-reviews">
                <h5>No reviews yet.</h5>
                <p>Reviews will appear when riders start rating your trips.</p>
            </div>

        <?php else: ?>

            <?php foreach ($reviews as $r): ?>
                <div class="card mb-3 review-card shadow-sm">
                    <div class="card-body">

                        <!-- Trip Info -->
                        <div class="mb-2">
                            <strong><?php echo h($r['start_location']); ?></strong>
                            →
                            <strong><?php echo h($r['destination']); ?></strong>
                        </div>

                        <div class="text-muted mb-3">
                            Date: <?php echo h($r['trip_date']); ?> |
                            Time: <?php echo h($r['trip_time']); ?>
                        </div>

                        <!-- Rating Stars -->
                        <div class="stars mb-2">
                            <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo ($i <= $r['rating'])
                                        ? '<i class="fas fa-star"></i> '
                                        : '<i class="far fa-star"></i> ';
                                }
                            ?>
                        </div>

                        <!-- Comment -->
                        <p class="mb-2"><?php echo nl2br(h($r['comment'])); ?></p>

                        <!-- Review Date -->
                        <div class="text-muted small">
                            Reviewed on: <?php echo h(date("M d, Y - h:i A", strtotime($r['created_at']))); ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>

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
