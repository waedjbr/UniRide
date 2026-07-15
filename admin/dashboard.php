<?php
include '../db_connection.php';
include '../session_check.php';
include '../update_trip_statuses.php';

if ($userRole !== 1) {
    header("Location: ../login.php");
    exit;
}

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$alert = '';
$totalUsers = 0;
$totalDrivers = 0;
$totalTrips = 0;
$totalReservations = 0;
$pendingApplications = 0;

$tripsOverTime = [];
$tripStatusData = [];
$reservationsOverTime = [];
$driverStatusData = [];

if ($userRole == 1) {
    $stmtUsers = $conn->prepare("SELECT COUNT(*) AS total_users FROM users");
    $stmtUsers->execute();
    $resUsers = $stmtUsers->get_result();
    if ($resUsers && $row = $resUsers->fetch_assoc()) {
        $totalUsers = (int)$row['total_users'];
    }
    $stmtUsers->close();

    $stmtDrivers = $conn->prepare("SELECT COUNT(*) AS total_drivers FROM drivers");
    $stmtDrivers->execute();
    $resDrivers = $stmtDrivers->get_result();
    if ($resDrivers && $row = $resDrivers->fetch_assoc()) {
        $totalDrivers = (int)$row['total_drivers'];
    }
    $stmtDrivers->close();

    $stmtTrips = $conn->prepare("SELECT COUNT(*) AS total_trips FROM trips");
    $stmtTrips->execute();
    $resTrips = $stmtTrips->get_result();
    if ($resTrips && $row = $resTrips->fetch_assoc()) {
        $totalTrips = (int)$row['total_trips'];
    }
    $stmtTrips->close();

    $stmtRes = $conn->prepare("SELECT COUNT(*) AS total_res FROM reservations");
    $stmtRes->execute();
    $resRes = $stmtRes->get_result();
    if ($resRes && $row = $resRes->fetch_assoc()) {
        $totalReservations = (int)$row['total_res'];
    }
    $stmtRes->close();

    $stmtPending = $conn->prepare("SELECT COUNT(*) AS pending_apps FROM drivers WHERE status = 'pending'");
    $stmtPending->execute();
    $resPending = $stmtPending->get_result();
    if ($resPending && $row = $resPending->fetch_assoc()) {
        $pendingApplications = (int)$row['pending_apps'];
    }
    $stmtPending->close();

    $stmtTripsTime = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM trips 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmtTripsTime->execute();
    $resTripsTime = $stmtTripsTime->get_result();
    while ($row = $resTripsTime->fetch_assoc()) {
        $tripsOverTime[] = [
            'date' => $row['date'],
            'count' => (int)$row['count']
        ];
    }
    $stmtTripsTime->close();

    $stmtTripStatus = $conn->prepare("
        SELECT trip_status, COUNT(*) as count 
        FROM trips 
        WHERE trip_status != 'Deleted'
        GROUP BY trip_status
    ");
    $stmtTripStatus->execute();
    $resTripStatus = $stmtTripStatus->get_result();
    while ($row = $resTripStatus->fetch_assoc()) {
        $tripStatusData[] = [
            'status' => $row['trip_status'],
            'count' => (int)$row['count']
        ];
    }
    $stmtTripStatus->close();

    $stmtResTime = $conn->prepare("
        SELECT DATE(reservation_date) as date, COUNT(*) as count 
        FROM reservations 
        WHERE reservation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(reservation_date)
        ORDER BY date ASC
    ");
    $stmtResTime->execute();
    $resResTime = $stmtResTime->get_result();
    while ($row = $resResTime->fetch_assoc()) {
        $reservationsOverTime[] = [
            'date' => $row['date'],
            'count' => (int)$row['count']
        ];
    }
    $stmtResTime->close();

    $stmtDriverStatus = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM drivers 
        GROUP BY status
    ");
    $stmtDriverStatus->execute();
    $resDriverStatus = $stmtDriverStatus->get_result();
    while ($row = $resDriverStatus->fetch_assoc()) {
        $driverStatusData[] = [
            'status' => $row['status'],
            'count' => (int)$row['count']
        ];
    }
    $stmtDriverStatus->close();

}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard | UniRide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <style>
        .stat-card {
            display: flex; align-items: center; gap: 1rem;
            padding: 1.25rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        .stat-icon {
            width: 56px; height: 56px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(2,138,153,0.12); color: var(--primary-color);
            font-size: 1.5rem;
        }
        .stat-value { font-size: 1.75rem; font-weight: 700; }
        .stat-label { margin: 0; color: #6c757d; }
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .chart-container.pie-chart {
            height: 350px;
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
            <h2 class="mb-0">Admin Dashboard</h2>
        </div>
        <?php if (!empty($alert)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo h($alert); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row g-3 mt-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <h5 class="chart-title"><i class="fas fa-chart-line me-2"></i>Trips Created Over Time (Last 30 Days)</h5>
                    <div class="chart-container">
                        <canvas id="tripsOverTimeChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5 class="chart-title"><i class="fas fa-chart-pie me-2"></i>Trip Status Distribution</h5>
                    <div class="chart-container pie-chart">
                        <canvas id="tripStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="chart-card">
                    <h5 class="chart-title"><i class="fas fa-chart-bar me-2"></i>Reservations Over Time (Last 30 Days)</h5>
                    <div class="chart-container">
                        <canvas id="reservationsOverTimeChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5 class="chart-title"><i class="fas fa-chart-pie me-2"></i>Driver Application Status</h5>
                    <div class="chart-container pie-chart">
                        <canvas id="driverStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div> 
</div> 

<script>
const tripsOverTimeData = <?php echo json_encode($tripsOverTime); ?>;
const tripStatusData = <?php echo json_encode($tripStatusData); ?>;
const reservationsOverTimeData = <?php echo json_encode($reservationsOverTime); ?>;
const driverStatusData = <?php echo json_encode($driverStatusData); ?>;

function generateLast30Days() {
    const days = [];
    for (let i = 29; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        days.push(date.toISOString().split('T')[0]);
    }
    return days;
}

function fillMissingDates(data, allDates) {
    const dataMap = {};
    data.forEach(item => {
        dataMap[item.date] = item.count;
    });
    return allDates.map(date => dataMap[date] || 0);
}

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top',
        },
        tooltip: {
            enabled: true,
        }
    }
};

const allDates = generateLast30Days();
const tripsCounts = fillMissingDates(tripsOverTimeData, allDates);
const tripsLabels = allDates.map(date => {
    const d = new Date(date);
    return (d.getMonth() + 1) + '/' + d.getDate();
});

new Chart(document.getElementById('tripsOverTimeChart'), {
    type: 'line',
    data: {
        labels: tripsLabels,
        datasets: [{
            label: 'Trips Created',
            data: tripsCounts,
            borderColor: 'rgb(2, 138, 153)',
            backgroundColor: 'rgba(2, 138, 153, 0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 5
        }]
    },
    options: {
        ...chartOptions,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

const tripStatusLabels = tripStatusData.map(item => item.status);
const tripStatusCounts = tripStatusData.map(item => item.count);
const tripStatusColors = [
    'rgba(40, 167, 69, 0.8)',   // green -> completed
    'rgba(0, 123, 255, 0.8)',   // blue -> upcoming
    'rgba(255, 193, 7, 0.8)',   // yellow -> ongoing
    'rgba(220, 53, 69, 0.8)',   // red -> cancelled
    'rgba(108, 117, 125, 0.8)'  // grey -> others
];

new Chart(document.getElementById('tripStatusChart'), {
    type: 'doughnut',
    data: {
        labels: tripStatusLabels,
        datasets: [{
            data: tripStatusCounts,
            backgroundColor: tripStatusColors.slice(0, tripStatusLabels.length),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        ...chartOptions,
        plugins: {
            ...chartOptions.plugins,
            legend: {
                ...chartOptions.plugins.legend,
                position: 'bottom'
            }
        }
    }
});

const reservationsCounts = fillMissingDates(reservationsOverTimeData, allDates);

new Chart(document.getElementById('reservationsOverTimeChart'), {
    type: 'bar',
    data: {
        labels: tripsLabels,
        datasets: [{
            label: 'Reservations',
            data: reservationsCounts,
            backgroundColor: 'rgba(2, 138, 153, 0.7)',
            borderColor: 'rgb(2, 138, 153)',
            borderWidth: 1
        }]
    },
    options: {
        ...chartOptions,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

const driverStatusLabels = driverStatusData.map(item => item.status);
const driverStatusCounts = driverStatusData.map(item => item.count);
const driverStatusColors = [
    'rgba(40, 167, 69, 0.8)',   // green -> approved
    'rgba(255, 193, 7, 0.8)',   // yellow -> pending
    'rgba(220, 53, 69, 0.8)'    // red -> rejected
];

new Chart(document.getElementById('driverStatusChart'), {
    type: 'pie',
    data: {
        labels: driverStatusLabels,
        datasets: [{
            data: driverStatusCounts,
            backgroundColor: driverStatusColors.slice(0, driverStatusLabels.length),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        ...chartOptions,
        plugins: {
            ...chartOptions.plugins,
            legend: {
                ...chartOptions.plugins.legend,
                position: 'bottom'
            }
        }
    }
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

