<?php
include '../db_connection.php';
include '../session_check.php';

if ($userRole !== 1) {
    header("Location: ../login.php");
    exit;
}

function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$driver_id = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
if (!$driver_id) {
    header("Location: drivers.php");
    exit;
}

// Fetch driver info
$stmt = $conn->prepare("
    SELECT u.full_name
    FROM drivers d
    INNER JOIN users u ON d.user_id = u.user_id
    WHERE d.driver_id = ?
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$driverInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch reviews for this driver
$stmt = $conn->prepare("
    SELECT r.review_id, r.rating, r.comment, r.created_at, u.full_name AS reviewer_name, t.start_location, t.destination, t.trip_date, t.trip_time
    FROM reviews r
    INNER JOIN trips t ON r.trip_id = t.trip_id
    INNER JOIN users u ON r.user_id = u.user_id
    WHERE t.driver_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Driver Reviews | UniRide Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="../js/sidebar.js"></script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">

</head>
<body>
<div class="dashboard-container">
    <admin-sidebar></admin-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Reviews for <?php echo h($driverInfo['full_name']); ?></h2>
            <a href="drivers.php" class="btn btn-outline-secondary btn-sm back"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <?php if(empty($reviews)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-comment-slash" style="font-size:3rem;"></i>
                <p>No reviews found for this driver.</p>
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach($reviews as $rev):
                    $fullStars = floor($rev['rating']);
                    $halfStar = ($rev['rating'] - $fullStars) >= 0.5 ? 1 : 0;
                    $emptyStars = 5 - $fullStars - $halfStar;
                ?>
                <div class="list-group-item mb-2 shadow-sm rounded">
                    <!-- Trip Info -->
                        <div class="mb-2">
                            <strong><?php echo h($rev['start_location']); ?></strong>
                            →
                            <strong><?php echo h($rev['destination']); ?></strong>
                        </div>

                        <div class="text-muted mb-3">
                            Date: <?php echo h($rev['trip_date']); ?> |
                            Time: <?php echo h($rev['trip_time']); ?>
                        </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong><?php echo h($rev['reviewer_name']); ?></strong>
                        <small class="text-muted"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></small>
                    </div>
                    <div class="mb-1">
                        <?php
                        for ($i=0;$i<$fullStars;$i++) echo '<i class="fas fa-star text-warning"></i>';
                        if($halfStar) echo '<i class="fas fa-star-half-alt text-warning"></i>';
                        for ($i=0;$i<$emptyStars;$i++) echo '<i class="far fa-star text-warning"></i>';
                        ?>
                    </div>
                    <p class="mb-0"><?php echo h($rev['comment']); ?></p>
                </div>
                <?php endforeach; ?>
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
