<?php
include '../db_connection.php';
include '../session_check.php';
if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}
/* -----------------------------------------
   DELETE VEHICLE (POST)
----------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vehicle_id'])) {

    $vehicle_id = (int)$_POST['delete_vehicle_id'];

    // Ensure vehicle belongs to this driver
    $stmt = $conn->prepare("
        SELECT image_path 
        FROM vehicles 
        WHERE vehicle_id = ? AND driver_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $vehicle_id, $driver_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $vehicle = $res->fetch_assoc();
    $stmt->close();

    if ($vehicle) {

        // Delete vehicle images from disk (main image)
        if (!empty($vehicle['image_path'])) {
            $imgPath = '../' . $vehicle['image_path'];
            if (file_exists($imgPath)) {
                unlink($imgPath);
            }
        }

        $conn->query("DELETE FROM vehicle_images WHERE vehicle_id = $vehicle_id");

        // Delete vehicle record
        $del = $conn->prepare("
            DELETE FROM vehicles 
            WHERE vehicle_id = ? AND driver_id = ?
        ");
        $del->bind_param("ii", $vehicle_id, $driver_id);
        $del->execute();
        $del->close();
    }

    header("Location: vehicles.php");
    exit;
}


$stmt = $conn->prepare("
    SELECT vehicle_id, make, model, year, plate_number, image_path
    FROM vehicles
    WHERE driver_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$res = $stmt->get_result();

$vehicles = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Vehicles</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
<script src="../js/sidebar.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
<div class="dashboard-container">
    <driver-sidebar></driver-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>My Vehicles</h2>
            <a href="add_vehicle.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Vehicle</a>
        </div>

        <?php if (empty($vehicles)): ?>
            <div class="alert alert-info">You have no vehicles yet.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($vehicles as $v): ?>
                    <div class="col-md-4">
                        <div class="card h-100">
                            
                            <img src="../<?php echo $v['image_path'] ? htmlspecialchars($v['image_path']) : 'placeholder.jpg'; ?>" 
                                 class="card-img-top" 
                                 style="height:180px; object-fit:cover;">

                            <div class="card-body">
                                <h5><?php echo htmlspecialchars($v['make']." ".$v['model']); ?></h5>
                                <p><strong>Year:</strong> <?php echo $v['year']; ?></p>
                                <p><strong>Plate:</strong> <?php echo htmlspecialchars($v['plate_number']); ?></p>

                                <a href="vehicle_images.php?vehicle_id=<?php echo $v['vehicle_id']; ?>" 
                                   class="btn btn-outline-secondary btn-sm mt-2">
                                    <i class="fas fa-images"></i> Manage Images
                                </a>
                            </div>

                            <div class="card-footer d-flex justify-content-between">
                                <a href="edit_vehicle.php?vehicle_id=<?php echo $v['vehicle_id']; ?>"
                                   class="btn btn-sm btn-warning">
                                   <i class="fas fa-edit"></i> Edit
                                </a>

                                <form method="post"
                                    onsubmit="return confirm('Delete this vehicle and ALL its images?');">
                                    <input type="hidden" name="delete_vehicle_id"
                                        value="<?php echo $v['vehicle_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>

                        </div>
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
</body>
</html>
