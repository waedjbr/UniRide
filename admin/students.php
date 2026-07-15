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

$alerts = [];

$students_query = "
SELECT 
    u.user_id,
    u.full_name,
    u.email,
    u.phone,

    COUNT(DISTINCT r.res_id) AS total_reservations,

    d.status AS driver_status

FROM users u
INNER JOIN user_roles ur 
    ON ur.user_id = u.user_id AND ur.role_id = 2

LEFT JOIN reservations r 
    ON r.user_id = u.user_id

LEFT JOIN drivers d 
    ON d.user_id = u.user_id

GROUP BY u.user_id
ORDER BY u.full_name ASC
";

$students_result = $conn->query($students_query);

$students = [];
if ($students_result) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}
$totalStudents = count($students);
$totalReservations = 0;
$driversApproved = 0;
$driversPending = 0;
$driversRejected = 0;

foreach ($students as $s) {
    $totalReservations += (int)$s['total_reservations'];

    if ($s['driver_status'] === 'Approved') $driversApproved++;
    elseif ($s['driver_status'] === 'Pending') $driversPending++;
    elseif ($s['driver_status'] === 'Rejected') $driversRejected++;
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Riders - UniRide Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
    <style>
        .table-actions {
            white-space: nowrap;
        }
        .student-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .students-table {
            margin: 0;
            width: 100%;
        }

        .students-table thead {
            background: linear-gradient(135deg, #028a99 0%, #02a8b9 100%);
            color: white;
        }

        .students-table th {        
            padding: 1rem;
            font-weight: 600;
            border: none;
            white-space: nowrap;
        }

        .students-table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .students-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .students-table tbody tr:last-child td {        
            border-bottom: none;
        }

        .badge-driver {
            background-color: #17a2b8;
        }
        /* SEARCH */
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

/* STUDENT / DRIVER INFO */
.driver-info {
    display: flex;
    align-items: center;
    gap: 1rem;
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

.driver-name {
    font-weight: 600;
    color: #333;
}

/* CONTACT INFO */
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
    margin-right: 4px;
}

/* ACTION BUTTONS */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-view-license {
    background: #028a99;
    color: white;
    border: none;
    padding: 0.45rem 0.9rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.3s;
    white-space: nowrap;
}

.btn-view-license:hover {
    background: #026e7a;
}

.btn-action {
    padding: 0.45rem 0.9rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.85rem;
    transition: all 0.3s;
    white-space: nowrap;
}

.btn-reject {
    background: #dc3545;
    color: white;
}

.btn-reject:hover {
    background: #c82333;
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
                <h2 class="mb-0"><i class="fas fa-users"></i> Manage Riders</h2>
            </div>

            <?php foreach ($alerts as $a): ?>
                <div class="alert alert-<?php echo h($a['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo h($a['msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
             <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalStudents; ?></div>
                        <div class="stat-label">Total Riders</div>
                    </div>
                </div>
				<div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalReservations; ?></div>
                        <div class="stat-label">Total Reservations</div>
                    </div>
                </div>
                
				<div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $driversApproved; ?></div>
                        <div class="stat-label">Approved Drivers</div>
                    </div>
                </div>
				<div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $driversPending; ?></div>
                        <div class="stat-label">Pending Drivers</div>
                    </div>
                </div>
				<div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $driversRejected; ?></div>
                        <div class="stat-label">Rejected Drivers</div>
                    </div>
                </div>
            </div>

            <div class="search-container">
                <input type="text" id="searchInput" class="search-input"
                    placeholder="Search by name, email, or phone...">
            </div>

                   <div class="student-table-container table-scroll">
                        <table class="students-table" id="studentsTable">   
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Contact</th>
                                    <th>Total Reservations</th>
                                    <th>Driver Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($students)): ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <!-- Student -->
                                        <td>
                                            <div class="driver-info">
                                                <div class="driver-avatar">
                                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                                </div>
                                                <div class="driver-name">
                                                    <?php echo h($student['full_name']); ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Contact -->
                                        <td>
                                            <div class="contact-info">
                                                <div class="contact-item">
                                                    <i class="fas fa-envelope"></i>
                                                    <?php echo h($student['email']); ?>
                                                </div>
                                                <div class="contact-item">
                                                    <i class="fas fa-phone"></i>
                                                    <?php echo h($student['phone']); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem;vertical-align: middle; border-bottom: 1px solid #dee2e6;">
                                            <span class="text-primary " style="font-size: 1.1rem; font-weight: 700; color: #028a99;">
                                                <?php echo (int)$student['total_reservations']; ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($student['driver_status']): ?>
                                                <?php
                                                    $cls = 'secondary';
                                                    if ($student['driver_status'] === 'Approved') $cls = 'approved';
                                                    elseif ($student['driver_status'] === 'Pending') $cls = 'pending';
                                                    elseif ($student['driver_status'] === 'Rejected') $cls = 'cancelled';
                                                ?>
                                                <span class="badge-custom badge-<?php echo $cls; ?>">
                                                    <?php echo h($student['driver_status']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not a Driver</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions -->
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_student.php?id=<?php echo h($student['user_id']); ?>"
                                                class="btn-view-license">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </div> 
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No students found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
    <script>
    document.getElementById('searchInput')?.addEventListener('keyup', function () {
        const value = this.value.toLowerCase();
        document.querySelectorAll('#studentsTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
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