<?php
include '../db_connection.php';
include '../session_check.php';
include '../send_email.php';
include '../update_trip_statuses.php';

if ($userRole !== 1) {
    header("Location: ../login.php");
    exit;
}

function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$alert = '';
$alertType = 'success';
$drivers = [];

if (isset($_GET['alert']) && isset($_GET['msg'])) {
    $alert = urldecode($_GET['msg']);
    $alertType = $_GET['alert'];
}

// Update driver status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_driver_status') {
    $driverId  = (int)$_POST['driver_id'];
    $newStatus = $_POST['status'];
    if (!in_array($newStatus, ['Approved', 'Rejected'])) {
        header("Location: drivers.php?alert=danger&msg=Invalid status");
        exit;
    }
    // Get user info
    $stmt = $conn->prepare("
        SELECT d.user_id, u.full_name, u.email
        FROM drivers d
        JOIN users u ON u.user_id = d.user_id
        WHERE d.driver_id = ?
    ");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        header("Location: drivers.php?alert=danger&msg=Driver not found");
        exit;
    }
    /*BLOCK REJECTION IF ACTIVE TRIPS*/
    if ($newStatus === 'Rejected') {
        $tripCheck = $conn->prepare("
            SELECT COUNT(*) AS active_trips
            FROM trips
            WHERE driver_id = ?
              AND trip_status IN ('Upcoming','Ongoing')
        ");
        $tripCheck->bind_param("i", $driverId);
        $tripCheck->execute();
        $tripResult = $tripCheck->get_result()->fetch_assoc();
        $tripCheck->close();
        if ($tripResult['active_trips'] > 0) {
            header("Location: drivers.php?alert=danger&msg=" .
                urlencode("Cannot reject driver. Driver has active trips."));
            exit;
        }
    }
    $conn->begin_transaction();
    try {
        // Update driver status
        $stmt = $conn->prepare("
            UPDATE drivers SET status = ? WHERE driver_id = ?
        ");
        $stmt->bind_param("si", $newStatus, $driverId);
        $stmt->execute();
        $stmt->close();
        /*ROLE MANAGEMENT*/
        if ($newStatus === 'Approved') {
            $check = $conn->prepare("
                SELECT 1 FROM user_roles
                WHERE user_id = ? AND role_id = 3
            ");
            $check->bind_param("i", $user['user_id']);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            if (!$exists) {
                $insert = $conn->prepare("
                    INSERT INTO user_roles (user_id, role_id)
                    VALUES (?, 3)
                ");
                $insert->bind_param("i", $user['user_id']);
                $insert->execute();
                $insert->close();
            }
        } else { // Rejected
            $delete = $conn->prepare("
                DELETE FROM user_roles
                WHERE user_id = ? AND role_id = 3
            ");
            $delete->bind_param("i", $user['user_id']);
            $delete->execute();
            $delete->close();
        }
        /*NOTIFICATION*/
        $title = $newStatus === 'Approved'
            ? 'Driver Application Approved'
            : 'Driver Application Rejected';

        $message = $newStatus === 'Approved'
            ? 'Congratulations! Your request to become a driver has been approved.'
            : 'Unfortunately, your request to become a driver has been rejected. For more details, please contact the UniRide support team.';

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, for_role)
            VALUES (?, ?, ?, 'Admin', 'Rider')
        ");
        $stmt->bind_param("iss", $user['user_id'], $title, $message);
        $stmt->execute();
        $stmt->close();
        /*EMAIL*/
        sendEmail(
            $user['email'],
            $user['full_name'],
            $title,
            "<p>Dear {$user['full_name']},</p> <p>{$message}</p> <p><strong>UniRide Team</strong></p>"
        );
        $conn->commit();
        header("Location: drivers.php?alert=success&msg=" .
            urlencode("Driver {$newStatus} successfully."));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: drivers.php?alert=danger&msg=" .
            urlencode("Error: " . $e->getMessage()));
        exit;
    }
}
// Fetch drivers with stats
$query = "
    SELECT 
        d.driver_id,
        d.user_id,
        u.full_name,
        u.email,
        u.phone,
        d.driving_license_file,
        d.official_doc,
        d.status,
        GROUP_CONCAT(DISTINCT CONCAT(v.make, ' ', v.model, ' (', v.plate_number, ')') SEPARATOR ', ') as vehicle_details,
        COUNT(DISTINCT t.trip_id) as total_trips,
        COUNT(DISTINCT CASE WHEN t.trip_status = 'Upcoming' THEN t.trip_id END) as upcoming_trips,
        COUNT(DISTINCT CASE WHEN t.trip_status = 'Ongoing' THEN t.trip_id END) as ongoing_trips,
        COUNT(DISTINCT CASE WHEN t.trip_status = 'Completed' THEN t.trip_id END) as completed_trips,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(DISTINCT r.review_id) as total_reviews
    FROM drivers d
    INNER JOIN users u ON d.user_id = u.user_id
    LEFT JOIN vehicles v ON d.driver_id = v.driver_id
    LEFT JOIN trips t ON d.driver_id = t.driver_id
    LEFT JOIN reviews r ON t.trip_id = r.trip_id
    GROUP BY d.driver_id, d.user_id, u.full_name, u.email, u.phone, d.driving_license_file, d.official_doc, d.status
    ORDER BY
    CASE d.status
            WHEN 'Pending' THEN 1
            WHEN 'Approved'  THEN 2
            WHEN 'Rejected' THEN 3
    END,
    u.full_name ASC
";

$result = $conn->query($query);
$drivers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $drivers[] = $row;
    }
}

// Stats
$totalDrivers = count($drivers);
$approvedDrivers = 0;
$pendingDrivers = 0;
$rejectedDrivers = 0;
$totalTrips = 0;

foreach ($drivers as $driver) {
    if ($driver['status'] == 'Approved') $approvedDrivers++;
    elseif ($driver['status'] == 'Pending') $pendingDrivers++;
    elseif ($driver['status'] == 'Rejected') $rejectedDrivers++;
    $totalTrips += $driver['total_trips'];
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Drivers | UniRide Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="../js/sidebar.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <style>
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #028a99;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .drivers-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .drivers-table {
            margin: 0;
            width: 100%;
        }

        .drivers-table thead {
            background: linear-gradient(135deg, #028a99 0%, #02a8b9 100%);
            color: white;
        }

        .drivers-table th {
            padding: 1rem;
            font-weight: 600;
            border: none;
            white-space: nowrap;
        }

        .drivers-table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .drivers-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .drivers-table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge-custom {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .search-container {
            margin-bottom: 1.5rem;
        }

        .search-input {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
        }

        .search-input:focus {
            outline: none;
            border-color: #028a99;
            box-shadow: 0 0 0 0.2rem rgba(2, 138, 153, 0.25);
        }

        .driver-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #028a99;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .driver-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .driver-name {
            font-weight: 600;
            color: #333;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .contact-item {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .contact-item i {
            width: 16px;
            text-align: center;
        }

        .btn-view-license {
            background: #028a99;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-view-license:hover {
            background: #026e7a;
            transform: translateY(-2px);
        }

        .btn-view-license:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            max-width: 280px;
        }

        .drivers-table th:last-child,
        .drivers-table td:last-child {
            width: 300px;
            max-width: 300px;
        }

        .btn-view-license,
        .btn-action {
            flex: 0 0 calc(50% - 0.25rem);
            min-width: 0;
            box-sizing: border-box;
        }

        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-pending {
            background: #ffc107;
            color: #212529;
        }

        .btn-pending:hover {
            background: #e0a800;
        }

        .stats-mini {
            display: flex;
            gap: 0.75rem;
            font-size: 0.85rem;
        }

        .stat-mini-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: #6c757d;
        }

        .stat-mini-value {
            font-weight: 600;
            color: #028a99;
        }

        .rating-stars {
            display: flex;
            gap: 0.15rem;
            font-size: 1rem;
            align-items: center;
        }

        .star {
            color: #ffc107;
        }

        .star.empty {
            color: #dee2e6;
        }

        .rating-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .rating-value {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.8);
            animation: fadeIn 0.3s;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 0;
            border-radius: 10px;
            max-width: 90%;
            max-height: 90vh;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s;
        }

        .modal-header {
            background: linear-gradient(135deg, #028a99 0%, #02a8b9 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 2rem;
            text-align: center;
            max-height: 70vh;
            overflow: auto;
        }

        .modal-body img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .no-document {
            padding: 3rem;
            color: #6c757d;
        }

        .no-document i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .btn-vehicles {
            background: #6f42c1 !important;
        }

        .btn-vehicles:hover {
            background: #5a32a3 !important;
        }

        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .vehicles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .vehicle-card {
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .vehicle-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #dee2e6;
        }

        .vehicle-image-placeholder {
            width: 100%;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dee2e6 0%, #adb5bd 100%);
            color: #6c757d;
        }

        .vehicle-image-placeholder i {
            font-size: 3rem;
        }

        .vehicle-details {
            padding: 1rem;
        }

        .vehicle-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .vehicle-info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.35rem;
        }

        .vehicle-info-row i {
            width: 18px;
            text-align: center;
            color: #028a99;
        }

        .vehicle-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #dee2e6;
        }

        .vehicle-btn {
            flex: 1;
            padding: 0.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .vehicle-btn-image {
            background: #028a99;
            color: white;
        }

        .vehicle-btn-image:hover {
            background: #026e7a;
        }

        .vehicle-btn-doc {
            background: #6c757d;
            color: white;
        }

        .vehicle-btn-doc:hover {
            background: #5a6268;
        }

        .no-vehicles {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .no-vehicles i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
    .table-scroll {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch; /* smooth scrolling on mobile */
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
                <h2 class="mb-0"><i class="fas fa-id-card"></i> All Drivers</h2>
            </div>

            <?php if (!empty($alert)): ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                    <?php echo h($alert); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($drivers)): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $totalDrivers; ?></div>
                            <div class="stat-label">Total Drivers</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $approvedDrivers; ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $pendingDrivers; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $rejectedDrivers; ?></div>
                            <div class="stat-label">Rejected</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $totalTrips; ?></div>
                            <div class="stat-label">Total Trips</div>
                        </div>
                    </div>
                </div>
            

                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input"
                        placeholder="Search by name, email, phone, or license...">
                </div>

                <div class="drivers-table-container table-scroll">
                    <table class="drivers-table" id="driversTable">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Contact</th>
                                <th>Vehicle</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Trip Stats</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($drivers as $driver): ?>
                            <tr>
                                <td>
                                    <div class="driver-info">
                                        <div class="driver-avatar"><?php echo strtoupper(substr($driver['full_name'], 0, 1)); ?></div>
                                        <div class="driver-name"><?php echo h($driver['full_name']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <div class="contact-item"><i class="fas fa-envelope"></i> <?php echo h($driver['email']); ?></div>
                                        <div class="contact-item"><i class="fas fa-phone"></i> <?php echo h($driver['phone']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted"><?php echo h($driver['vehicle_details'] ?: 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="badge-custom badge-<?php echo strtolower($driver['status']); ?>"><?php echo h($driver['status']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $avgRating = round($driver['avg_rating'], 1);
                                    $fullStars = floor($avgRating);
                                    $halfStar = ($avgRating - $fullStars) >= 0.5 ? 1 : 0;
                                    $emptyStars = 5 - $fullStars - $halfStar;
                                    ?>
                                    <div class="rating-info">
                                        <div class="rating-stars">
                                            <?php
                                            for ($i = 0; $i < $fullStars; $i++) echo '<i class="fas fa-star star"></i>';
                                            if ($halfStar) echo '<i class="fas fa-star-half-alt star"></i>';
                                            for ($i = 0; $i < $emptyStars; $i++) echo '<i class="far fa-star star empty"></i>';
                                            ?>
                                        </div>
                                        <div class="rating-value">
                                            <?php echo $driver['total_reviews'] > 0 ? h(number_format($avgRating,1)) . " ({$driver['total_reviews']} review" . ($driver['total_reviews'] != 1 ? 's' : '') . ")" : '<span class="text-muted">No reviews</span>'; ?>
                                        </div>
                                    </div>
                                    <?php if($driver['total_reviews'] > 0): ?>
                                        <a href="view_reviews.php?driver_id=<?php echo h($driver['driver_id']); ?>" class="btn btn-sm btn-warning mt-1">
                                            View Reviews
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="stats-mini">
                                        <div class="stat-mini-item" title="Total Trips"><i class="fas fa-route"></i> <span class="stat-mini-value"><?php echo h($driver['total_trips']); ?></span></div>
                                        <div class="stat-mini-item" title="Upcoming Trips"><i class="fas fa-calendar text-info"></i> <span class="stat-mini-value"><?php echo h($driver['upcoming_trips']); ?></span></div>
                                        <div class="stat-mini-item" title="Ongoing Trips"><i class="fas fa-spinner text-warning"></i> <span class="stat-mini-value"><?php echo h($driver['ongoing_trips']); ?></span></div>
                                        <div class="stat-mini-item" title="Completed Trips"><i class="fas fa-flag-checkered"></i> <span class="stat-mini-value"><?php echo h($driver['completed_trips']); ?></span></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if (!empty($driver['driving_license_file'])): ?>
                                            <a href="../<?php echo h($driver['driving_license_file']); ?>" target="_blank" class="btn-view-license"><i class="fas fa-id-card"></i> License</a>
                                        <?php else: ?>
                                            <button class="btn-view-license" disabled><i class="fas fa-ban"></i> No License</button>
                                        <?php endif; ?>

                                        <?php if (!empty($driver['official_doc'])): ?>
                                            <a href="../<?php echo h($driver['official_doc']); ?>" target="_blank" class="btn-view-license"><i class="fas fa-file-alt"></i> Official Doc</a>
                                        <?php else: ?>
                                            <button class="btn-view-license" disabled><i class="fas fa-ban"></i> No Doc</button>
                                        <?php endif; ?>

                                        <?php if ($driver['status'] == 'Approved'): ?>
                                            <!-- Vehicles link -->
                                            <a href="view_vehicles.php?driver_id=<?php echo h($driver['driver_id']); ?>" class="btn-view-license btn-vehicles">
                                                <i class="fas fa-car"></i> Vehicles
                                            </a>
                                            
                                        <?php endif; ?>

                                        <?php if ($driver['status'] !== 'Approved'): ?>
                                            <form method="POST" style="display:inline;" 
                                                onsubmit="return confirmAction('approve', '<?php echo h($driver['full_name']); ?>');">
                                                <input type="hidden" name="action" value="update_driver_status">
                                                <input type="hidden" name="driver_id" value="<?php echo h($driver['driver_id']); ?>">
                                                <input type="hidden" name="status" value="Approved">
                                                <button type="submit" class="btn-action btn-approve">Approve</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($driver['status'] !== 'Rejected'): ?>
                                            
                                            <form method="POST" style="display:inline;"
                                                onsubmit="return confirmAction('reject', '<?php echo h($driver['full_name']); ?>');">
                                                <input type="hidden" name="action" value="update_driver_status">
                                                <input type="hidden" name="driver_id" value="<?php echo h($driver['driver_id']); ?>">
                                                <input type="hidden" name="status" value="Rejected">
                                                <button type="submit" class="btn-action btn-reject">Reject</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h4>No Drivers Found</h4>
                    <p>There are no drivers registered in the system yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const value = this.value.toLowerCase();
            document.querySelectorAll('#driversTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
            });
        });
    </script>
    <script>
    function confirmAction(type, name) {
        return confirm(
            type === 'approve'
            ? `Are you sure you want to APPROVE ${name} as a driver?`
            : `Are you sure you want to REJECT ${name} as a driver?`
        );
    }
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
