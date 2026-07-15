<?php
include '../db_connection.php';
include '../session_check.php';

if ($userRole !== 1) {
    header("Location: ../login.php");
    exit;
}

function h($v)
{
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($user_id <= 0) {
  header("Location: students.php");
  exit;
}

$student_query = "
    SELECT u.user_id, u.full_name, u.email, u.phone, u.is_confirmed,
           d.driver_id, d.status as driver_status
    FROM users u
    LEFT JOIN drivers d ON u.user_id = d.user_id
    WHERE u.user_id = ?
";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student_result = $stmt->get_result();
$student = $student_result->fetch_assoc();
$stmt->close();

if (!$student) {
  header("Location: students.php");
  exit;
}

$reservations_query = "
    SELECT r.res_id, r.location, r.seats_reserved, r.status, r.reservation_date,
           t.trip_id, t.start_location, t.destination, t.trip_date, t.trip_time, 
           t.price, t.trip_status,
           u.full_name as driver_name
    FROM reservations r
    INNER JOIN trips t ON r.trip_id = t.trip_id
    INNER JOIN drivers d ON t.driver_id = d.driver_id
    INNER JOIN users u ON d.user_id = u.user_id
    WHERE r.user_id = ?
    ORDER BY r.reservation_date DESC
";
$stmt = $conn->prepare($reservations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reservations_result = $stmt->get_result();
$reservations = [];
while ($row = $reservations_result->fetch_assoc()) {
  $reservations[] = $row;
}
$stmt->close();

$reviews_query = "
    SELECT rv.review_id, rv.rating, rv.comment, rv.created_at,
           t.trip_id, t.start_location, t.destination, t.trip_date,
           u.full_name as driver_name
    FROM reviews rv
    INNER JOIN trips t ON rv.trip_id = t.trip_id
    INNER JOIN drivers d ON t.driver_id = d.driver_id
    INNER JOIN users u ON d.user_id = u.user_id
    WHERE rv.user_id = ?
    ORDER BY rv.created_at DESC
";
$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
$reviews = [];
while ($row = $reviews_result->fetch_assoc()) {
  $reviews[] = $row;
}
$stmt->close();

$total_spent = 0;
foreach ($reservations as $res) {
  if ($res['status'] === 'confirmed') {
    $total_spent += $res['seats_reserved'] * $res['price'];
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>View Student - <?php echo h($student['full_name']); ?> | UniRide Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .student-header {
      background: linear-gradient(135deg, #028a99 0%, #02a8b9 100%);
      color: white;
      padding: 2rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
    }

    .student-avatar {
      width: 80px;
      height: 80px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
    }

    .info-card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .info-card h5 {
      color: #028a99;
      border-bottom: 2px solid #028a99;
      padding-bottom: 0.5rem;
      margin-bottom: 1rem;
    }

    .stat-box {
      text-align: center;
      padding: 1rem;
      background: #f8f9fa;
      border-radius: 8px;
    }

    .stat-box .number {
      font-size: 1.8rem;
      font-weight: 700;
      color: #028a99;
    }

    .stat-box .label {
      color: #6c757d;
      font-size: 0.9rem;
    }

    .rating-stars {
      color: #ffc107;
    }

    .rating-stars .empty {
      color: #dee2e6;
    }

    .trip-route {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .route-arrow {
      color: #028a99;
    }

    .empty-state {
      text-align: center;
      padding: 2rem;
      color: #6c757d;
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }

    .back-btn {
      background: white;
      color: #028a99;
      border: 2px solid #028a99;
      padding: 0.5rem 1.5rem;
      text-decoration: none;
      transition: all 0.3s;
    }

    .back-btn:hover {
      background: #028a99;
      color: white;
    }
  </style>
    
</head>

<body>
  <script src="../js/sidebar.js"></script>
  <div class="dashboard-container">
    <admin-sidebar></admin-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>

    <div class="main-content">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="mb-0"><i class="fas fa-user"></i> Rider Details</h2>
        <a href="students.php" class="btn btn-outline-secondary btn-sm back">
                <i class="fas fa-arrow-left"></i> Back to Students
            </a>
      </div>

      <div class="student-header">
        <div class="d-flex align-items-center gap-4">
          <div class="student-avatar">
            <i class="fas fa-user"></i>
          </div>
          <div>
            <h3 class="mb-1"><?php echo h($student['full_name']); ?></h3>
            <p class="mb-1"><i class="fas fa-envelope"></i> <?php echo h($student['email']); ?></p>
            <p class="mb-0"><i class="fas fa-phone"></i> <?php echo h($student['phone']); ?></p>
          </div>
          <div class="ms-auto text-end">
            <?php if ((int) $student['is_confirmed'] === 1): ?>
              <span class="badge bg-success fs-6 back"><i class="fas fa-check-circle"></i> Email Confirmed</span>
            <?php else: ?>
              <span class="badge bg-warning fs-6 back"><i class="fas fa-clock"></i> Email Unconfirmed</span>
            <?php endif; ?>
            <?php if ($student['driver_id']): ?>
              <br>
              <?php
              $status = $student['driver_status'];
              $badge_class = 'secondary';
              if ($status === 'Approved')
                $badge_class = 'success';
              elseif ($status === 'Pending')
                $badge_class = 'warning';
              elseif ($status === 'Rejected')
                $badge_class = 'danger';
              ?>
              <span class="badge bg-<?php echo $badge_class; ?> fs-6 mt-2 back">
                <i class="fas fa-car"></i> Driver: <?php echo h($status); ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="row mb-4">
        <div class="col-md-4">
          <div class="stat-box">
            <div class="number"><?php echo count($reservations); ?></div>
            <div class="label">Total Reservations</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stat-box">
            <div class="number"><?php echo count($reviews); ?></div>
            <div class="label">Reviews Given</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stat-box">
            <div class="number">$<?php echo number_format($total_spent, 2); ?></div>
            <div class="label">Total Spent</div>
          </div>
        </div>
      </div>

      <div class="info-card">
        <h5><i class="fas fa-ticket-alt"></i> Reservations</h5>
        <?php if (count($reservations) > 0): ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Trip Route</th>
                  <th>Driver</th>
                  <th>Trip Date</th>
                  <th>Pickup Location</th>
                  <th>Seats</th>
                  <th>Price</th>
                  <th>Status</th>
                  <th>Booked On</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reservations as $index => $res): ?>
                  <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td>
                      <div class="trip-route">
                        <span><?php echo h($res['start_location']); ?></span>
                        <i class="fas fa-arrow-right route-arrow"></i>
                        <span><?php echo h($res['destination']); ?></span>
                      </div>
                    </td>
                    <td><i class="fas fa-user-tie"></i> <?php echo h($res['driver_name']); ?></td>
                    <td>
                      <?php echo date('M d, Y', strtotime($res['trip_date'])); ?>
                      <br>
                      <small class="text-muted"><?php echo date('h:i A', strtotime($res['trip_time'])); ?></small>
                    </td>
                    <td><i class="fas fa-map-marker-alt text-danger"></i> <?php echo h($res['location']); ?></td>
                    <td><span class="badge bg-primary"><?php echo h($res['seats_reserved']); ?></span></td>
                    <td><strong>$<?php echo number_format($res['price'] * $res['seats_reserved'], 2); ?></strong></td>
                    <td>
                      <?php if ($res['status'] === 'confirmed'): ?>
                        <span class="badge bg-success">Confirmed</span>
                      <?php else: ?>
                        <span class="badge bg-danger">Cancelled</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($res['reservation_date'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-ticket-alt"></i>
            <p>No reservations found for this student.</p>
          </div>
        <?php endif; ?>
      </div>

      <div class="info-card">
        <h5><i class="fas fa-star"></i> Reviews</h5>
        <?php if (count($reviews) > 0): ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Trip Route</th>
                  <th>Driver</th>
                  <th>Trip Date</th>
                  <th>Rating</th>
                  <th>Comment</th>
                  <th>Reviewed On</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reviews as $index => $review): ?>
                  <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td>
                      <div class="trip-route">
                        <span><?php echo h($review['start_location']); ?></span>
                        <i class="fas fa-arrow-right route-arrow"></i>
                        <span><?php echo h($review['destination']); ?></span>
                      </div>
                    </td>
                    <td><i class="fas fa-user-tie"></i> <?php echo h($review['driver_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($review['trip_date'])); ?></td>
                    <td>
                      <div class="rating-stars">
                        <?php
                        $rating = (float) $review['rating'];
                        for ($i = 1; $i <= 5; $i++):
                          if ($i <= $rating): ?>
                            <i class="fas fa-star"></i>
                          <?php elseif ($i - 0.5 <= $rating): ?>
                            <i class="fas fa-star-half-alt"></i>
                          <?php else: ?>
                            <i class="fas fa-star empty"></i>
                          <?php endif;
                        endfor; ?>
                        <span class="ms-1 text-dark">(<?php echo number_format($rating, 1); ?>)</span>
                      </div>
                    </td>
                    <td>
                      <?php if ($review['comment']): ?>
                        <span title="<?php echo h($review['comment']); ?>">
                          <?php echo h(strlen($review['comment']) > 50 ? substr($review['comment'], 0, 50) . '...' : $review['comment']); ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">No comment</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-star"></i>
            <p>No reviews found for this student.</p>
          </div>
        <?php endif; ?>
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