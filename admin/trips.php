<?php
include '../db_connection.php';
include '../session_check.php';
include '../update_trip_statuses.php';

if ($userRole !== 1) {
    header("Location: ../login.php");
    exit;
}

function h($v)
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$alert = '';
$alertType = 'success';
$trips = [];
/* -------------------------------------------------
   DELETE TRIP (SOFT DELETE)
   Allowed ONLY for Completed / Cancelled
------------------------------------------------- */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_trip') 
{
    $tripId = (int)$_POST['trip_id'];

    // Fetch current status
    $stmt = $conn->prepare("
        SELECT trip_status 
        FROM trips 
        WHERE trip_id = ?
    ");
    $stmt->bind_param("i", $tripId);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trip) {
        $alert = "Trip not found.";
        $alertType = 'danger';
    }
    elseif (!in_array($trip['trip_status'], ['Completed', 'Cancelled'])) {
        $alert = "Only completed or cancelled trips can be deleted.";
        $alertType = 'warning';
    }
    else {
    $conn->begin_transaction();

    try {
        //Soft delete trip
        $stmt = $conn->prepare("
            UPDATE trips 
            SET trip_status = 'Deleted'
            WHERE trip_id = ?
        ");
        $stmt->bind_param("i", $tripId);
        $stmt->execute();
        $stmt->close();

        //delete all reservations on this trip
        $stmt = $conn->prepare("
            UPDATE reservations
            SET status = 'deleted'
            WHERE trip_id = ?
            AND status != 'deleted'
        ");
        $stmt->bind_param("i", $tripId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        $alert = "Trip deleted successfully. All reservations were cancelled.";
        $alertType = 'success';

        } catch (Exception $e) {
            $conn->rollback();

            $alert = "Failed to delete trip.";
            $alertType = 'danger';
        }
    }
}
$query = "
    SELECT 
        t.trip_id,
        t.driver_id,
        t.start_location,
        t.destination,
        t.trip_date,
        t.trip_time,
        t.price,
        t.max_seats,
        t.available_seats,
        t.trip_status,
        t.created_at,
        u.full_name as driver_name,
        u.phone as driver_phone,
        u.email as driver_email,
        COUNT(DISTINCT r.res_id) as total_reservations,
        COUNT(DISTINCT CASE WHEN r.status = 'confirmed' THEN r.res_id END) as confirmed_reservations
    FROM trips t
    INNER JOIN drivers d ON t.driver_id = d.driver_id
    INNER JOIN users u ON d.user_id = u.user_id
    LEFT JOIN reservations r ON t.trip_id = r.trip_id
    WHERE t.trip_status != 'Deleted'
    GROUP BY t.trip_id, t.driver_id, t.start_location, t.destination, t.trip_date, 
                t.trip_time, t.price, t.max_seats, t.available_seats, t.trip_status, 
                t.created_at, u.full_name, u.phone, u.email
    ORDER BY 
        CASE 
            WHEN t.trip_status = 'Upcoming' THEN 1
            WHEN t.trip_status = 'Ongoing' THEN 2
            WHEN t.trip_status = 'Completed' THEN 3
            WHEN t.trip_status = 'Cancelled' THEN 4
            WHEN t.trip_status = 'Deleted' THEN 5
        END,
        t.trip_date, t.trip_time 
";

$result = $conn->query($query);
$trips = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $trips[] = $row;
    }
}


$totalTrips = count($trips);
$upcomingTrips = 0;
$ongoingTrips = 0;
$completedTrips = 0;
$cancelledTrips = 0;
$deletedTrips = 0;
$totalRevenue = 0;

foreach ($trips as $trip) {
    if ($trip['trip_status'] == 'Upcoming') {
        $upcomingTrips++;
    } elseif ($trip['trip_status'] == 'Ongoing') {
        $ongoingTrips++;
    } elseif ($trip['trip_status'] == 'Completed') {
        $completedTrips++;
    } elseif ($trip['trip_status'] == 'Cancelled') {
        $cancelledTrips++;
    } elseif ($trip['trip_status'] == 'Deleted') {
        $deletedTrips++;
    }

    $seatsBooked = $trip['max_seats'] - $trip['available_seats'];
    $totalRevenue += ($seatsBooked * $trip['price']);
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Trips | UniRide Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="../js/sidebar.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <style>
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
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

        .filter-tabs {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.6rem 1.5rem;
            border: 2px solid #dee2e6;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            color: #6c757d;
        }

        .filter-btn:hover {
            border-color: #028a99;
            color: #028a99;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #028a99 0%, #02a8b9 100%);
            color: white;
            border-color: #028a99;
        }

        .trips-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .trips-table {
            margin: 0;
            width: 100%;
        }

        .trips-table thead {
            background: linear-gradient(135deg, #028a99 0%, #02a8b9 100%);
            color: white;
        }

        .trips-table th {
            padding: 1rem;
            font-weight: 600;
            border: none;
            white-space: nowrap;
        }

        .trips-table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .trips-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .trips-table tbody tr:last-child td {
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

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-upcoming {
            background: #cfe2ff;
            color: #084298;
        }

        .badge-ongoing {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-completed {
            background: #e2e3e5;
            color: #383d41;
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

        .trip-route {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .location {
            font-weight: 600;
            color: #333;
        }

        .route-arrow {
            color: #028a99;
            font-size: 1.2rem;
        }

        .driver-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .driver-name {
            font-weight: 600;
            color: #333;
        }

        .driver-contact {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .trip-datetime {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .trip-date {
            font-weight: 600;
            color: #333;
        }

        .trip-time {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-view-reservations {
            background: #17a2b8;
            color: white;
        }

        .btn-view-reservations:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .seats-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .seats-available {
            font-weight: 600;
            color: #28a745;
        }

        .seats-total {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .price-tag {
            font-size: 1.1rem;
            font-weight: 700;
            color: #028a99;
        }

        .reservations-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.9rem;
        }

        .reservation-count {
            font-weight: 600;
            color: #333;
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
                <h2 class="mb-0"><i class="fas fa-route"></i> Manage Trips</h2>
            </div>

            <?php if (!empty($alert)): ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                    <?php echo h($alert); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($trips)): ?>
                <div class="row g-3 mb-4">
                	<div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $totalTrips; ?></div>
                            <div class="stat-label">Total Trips</div>
                        </div>
                     </div>
                     <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $upcomingTrips; ?></div>
                            <div class="stat-label">Upcoming</div>
                        </div>
                     </div>
                     <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $ongoingTrips; ?></div>
                            <div class="stat-label">Ongoing</div>
                        </div>
                     </div>
                     <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $cancelledTrips; ?></div>
                            <div class="stat-label">Cancelled</div>
                        </div>
                     </div>
                     <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $completedTrips; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                     </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-value">$<?php echo number_format($totalRevenue, 2); ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button class="filter-btn active" data-filter="all">
                        <i class="fas fa-list"></i> All Trips
                    </button>
                    <button class="filter-btn" data-filter="Upcoming">
                        <i class="fas fa-calendar"></i> Upcoming (<?php echo $upcomingTrips; ?>)
                    </button>
                    <button class="filter-btn" data-filter="Ongoing">
                        <i class="fas fa-spinner"></i> Ongoing (<?php echo $ongoingTrips; ?>)
                    </button>
                    <button class="filter-btn" data-filter="Completed">
                        <i class="fas fa-check-circle"></i> Completed (<?php echo $completedTrips; ?>)
                    </button>
                    <button class="filter-btn" data-filter="Cancelled">
                        <i class="fas fa-times-circle"></i> Cancelled (<?php echo $cancelledTrips; ?>)
                    </button>
                </div>

                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input"
                        placeholder="Search by location, driver, or trip ID...">
                </div>

                <div class="trips-table-container table-scroll">
                    <table class="trips-table" id="tripsTable">
                        <thead>
                            <tr>
                                <th>Trip ID</th>
                                <th>Route</th>
                                <th>Driver</th>
                                <th>Date & Time</th>
                                <th>Price</th>
                                <th>Seats</th>
                                <th>Status</th>
                                <th>Reservations</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): ?>
                                <tr data-status="<?php echo h($trip['trip_status']); ?>">
                                    <td><strong>#<?php echo h($trip['trip_id']); ?></strong></td>
                                    <td>
                                        <div class="trip-route">
                                            <span class="location"><?php echo h($trip['start_location']); ?></span>
                                            <i class="fas fa-arrow-right route-arrow"></i>
                                            <span class="location"><?php echo h($trip['destination']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="driver-info">
                                            <span class="driver-name">
                                                <i class="fas fa-user-tie"></i> <?php echo h($trip['driver_name']); ?>
                                            </span>
                                            <span class="driver-contact">
                                                <i class="fas fa-phone"></i> <?php echo h($trip['driver_phone']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="trip-datetime">
                                            <span class="trip-date">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('M d, Y', strtotime($trip['trip_date'])); ?>
                                            </span>
                                            <span class="trip-time">
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('h:i A', strtotime($trip['trip_time'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="price-tag">$<?php echo number_format($trip['price'], 2); ?></span>
                                    </td>
                                    <td>
                                        <div class="seats-info">
                                            <span class="seats-available">
                                                <?php echo h($trip['available_seats']); ?> available
                                            </span>
                                            <span class="seats-total">
                                                of <?php echo h($trip['max_seats']); ?> total
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $tripStatusClass = 'badge-' . strtolower($trip['trip_status']);
                                        ?>
                                        <span class="badge-custom <?php echo $tripStatusClass; ?>">
                                            <?php
                                            $statusIcons = [
                                                'upcoming' => 'fa-calendar',
                                                'ongoing' => 'fa-spinner',
                                                'completed' => 'fa-check-circle',
                                                'cancelled' => 'fa-times-circle',
                                                'deleted' => 'fa-trash'
                                            ];
                                            $icon = $statusIcons[strtolower($trip['trip_status'])] ?? 'fa-question-circle';
                                            ?>
                                            <i class="fas <?php echo $icon; ?>"></i>
                                            <?php echo h($trip['trip_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="reservations-info">
                                            <span class="reservation-count">
                                                <i class="fas fa-users"></i>
                                                <?php echo h($trip['total_reservations']); ?> total
                                            </span>
                                            <span class="text-success">
                                                <i class="fas fa-check"></i>
                                                <?php echo h($trip['confirmed_reservations']); ?> confirmed
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">

                                            <!-- View -->
                                            <a href="trip_reservations.php?trip_id=<?php echo h($trip['trip_id']); ?>"
                                            class="btn-action btn-view-reservations" style="text-decoration:none">
                                                <i class="fas fa-eye"></i> View
                                            </a>

                                            <!-- Edit (Upcoming only) -->
                                            <?php if ($trip['trip_status'] === 'Upcoming'): ?>
                                                <a href="edit_trip.php?trip_id=<?php echo h($trip['trip_id']); ?>"
                                                class="btn-action btn-approve" style="text-decoration:none">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>

                                                <a href="cancel_trip.php?trip_id=<?php echo h($trip['trip_id']); ?>"
                                                class="btn-action btn-cancel"
                                                onclick="return confirm('Cancel this trip? This will notify the driver and riders.')"
                                                style="text-decoration:none">
                                                    <i class="fas fa-ban"></i> Cancel
                                                </a>
                                            <?php endif; ?>
                                            <!-- Delete (Completed or Cancelled) -->
                                            <?php if (in_array($trip['trip_status'], ['Completed', 'Cancelled'])): ?>
                                                <form method="post" style="display:inline"
                                                    onsubmit="return confirm('Delete this trip permanently from listings?');">
                                                    <input type="hidden" name="action" value="delete_trip">
                                                    <input type="hidden" name="trip_id" value="<?php echo h($trip['trip_id']); ?>">
                                                    <button type="submit" class="btn-action btn-cancel">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
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
                    <i class="fas fa-route"></i>
                    <h4>No Trips Found</h4>
                    <p>There are no trips in the system yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchInput')?.addEventListener('keyup', function () {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#tripsTable tbody tr');

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        const filterButtons = document.querySelectorAll('.filter-btn');
        const tableRows = document.querySelectorAll('#tripsTable tbody tr');

        filterButtons.forEach(button => {
            button.addEventListener('click', function () {
                filterButtons.forEach(btn => btn.classList.remove('active'));

                this.classList.add('active');

                const filterValue = this.getAttribute('data-filter');

                tableRows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status');

                    if (filterValue === 'all') {
                        row.style.display = '';
                    } else if (rowStatus === filterValue) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        setTimeout(function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);  
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