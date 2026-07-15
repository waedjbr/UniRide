<?php
include '../db_connection.php';
include '../session_check.php';
if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}

$vehicle_id = intval($_GET['vehicle_id'] ?? 0);

// Check ownership
$check = $conn->prepare("SELECT driver_id FROM vehicles WHERE vehicle_id=? AND driver_id=?");
$check->bind_param("ii", $vehicle_id, $driver_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    die("<h3>Unauthorized access</h3>");
}
$check->close();

$vehicleFolder = "uploads/drivers/driver_" . $driver_id . "/vehicle_" . $vehicle_id . "/";
$galleryFolder = $vehicleFolder . "gallery/";
if (!is_dir("../" . $galleryFolder)) {
    mkdir("../" . $galleryFolder, 0775, true);
}

// Upload new images
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
        if (!is_uploaded_file($tmp)) continue;

        $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png'])) continue;

        $newName = $galleryFolder . time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
        move_uploaded_file($tmp, "../" . $newName);

        $ins = $conn->prepare("INSERT INTO vehicle_images (vehicle_id, image_path) VALUES (?, ?)");
        $ins->bind_param("is", $vehicle_id, $newName);
        $ins->execute();
        $ins->close();
    }
}

// Delete image
if (isset($_GET['delete'])) {
    $img_id = intval($_GET['delete']);

    $stmt = $conn->prepare("SELECT image_path FROM vehicle_images WHERE image_id=? AND vehicle_id=?");
    $stmt->bind_param("ii", $img_id, $vehicle_id);
    $stmt->execute();
    $img = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($img) unlink("../" . $img['image_path']);

    $del = $conn->prepare("DELETE FROM vehicle_images WHERE image_id=? AND vehicle_id=?");
    $del->bind_param("ii", $img_id, $vehicle_id);
    $del->execute();
    $del->close();

    header("Location: vehicle_images.php?vehicle_id=$vehicle_id");
    exit;
}


// Fetch images
$stmt = $conn->prepare("SELECT image_id, image_path FROM vehicle_images WHERE vehicle_id=?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Vehicle Images</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
<script src="../js/sidebar.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    .dashboard-title { display: flex; align-items: center; justify-content: space-between; }
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
            <h2 class="mb-0">Vehicle Images</h2>
            <div>
                <a href="vehicles.php" class="btn btn-outline-secondary btn-sm back">
                    <i class="fas fa-arrow-left"></i> Back to Vehicles
                </a>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="my-3">
            <label class="form-label">Upload More Images</label>
            <input type="file" name="images[]" multiple class="form-control mb-2">
            <button class="btn btn-primary">Upload</button>
        </form>

        <div class="row g-3">
            <?php foreach ($images as $img): ?>
                <div class="col-md-3">
                    <div class="card">
                        <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" 
                             class="card-img-top"
                             style="height:150px; object-fit:cover;">

                        <div class="card-footer text-center">
                            <a href="vehicle_images.php?vehicle_id=<?php echo $vehicle_id; ?>&delete=<?php echo $img['image_id']; ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this image?');">
                               Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
</body>
</html>
