<?php
include '../db_connection.php';
include '../session_check.php';
if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}


$alerts = [];
// Function to upload a single file
function uploadFile($fileKey, $folder)
{
    if (!isset($_FILES[$fileKey]) || !is_uploaded_file($_FILES[$fileKey]['tmp_name']))
        return null;

    $allowed = ['jpg','jpeg','png','pdf'];
    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed))
        return null;

    if (!is_dir("../" . $folder)) {
        mkdir("../" . $folder, 0775, true);
    }

    $newName = $folder . time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;

    move_uploaded_file($_FILES[$fileKey]['tmp_name'], "../" . $newName);

    return $newName;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $year = intval($_POST['year']);
    $plate = trim($_POST['plate_number']);

    if ($make === '' || $model === '' || $year === 0 || $plate === '') {
        $alerts[] = ['type'=>'danger','msg'=>'All fields are required.'];
    } else {

        // 1) Insert vehicle first (without images)
        $stmt = $conn->prepare("
            INSERT INTO vehicles (driver_id, make, model, year, plate_number)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issis", $driver_id, $make, $model, $year, $plate);
        $stmt->execute();
        $vehicle_id = $conn->insert_id;
        $stmt->close();

        // 2) Create folder structure
        $vehicleFolder = "uploads/drivers/driver_" . $driver_id . "/vehicle_" . $vehicle_id . "/";
        mkdir("../" . $vehicleFolder, 0775, true);
        mkdir("../" . $vehicleFolder . "gallery/", 0775, true);

        // 3) Upload main image + registration doc
        $mainImage = uploadFile("image", $vehicleFolder);
        $doc = uploadFile("registration_doc", $vehicleFolder);

        // 4) Save them in DB
        $stmt = $conn->prepare("
            UPDATE vehicles 
            SET image_path=?, registration_doc=?
            WHERE vehicle_id=?
        ");
        $stmt->bind_param("ssi", $mainImage, $doc, $vehicle_id);
        $stmt->execute();
        $stmt->close();

        header("Location: vehicles.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Add Vehicle</title>
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
        <h2>Add Vehicle</h2>

        <?php foreach($alerts as $a): ?>
            <div class="alert alert-<?php echo $a['type']; ?>"><?php echo $a['msg']; ?></div>
        <?php endforeach; ?>

        <form method="POST" enctype="multipart/form-data" class="mt-3">

            <div class="mb-3">
                <label class="form-label">Make</label>
                <input type="text" name="make" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Model</label>
                <input type="text" name="model" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Year</label>
                <input type="number" name="year" min="1990" max="2050" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Plate Number</label>
                <input type="text" name="plate_number" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Main Vehicle Image</label>
                <input type="file" name="image" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Registration Document</label>
                <input type="file" name="registration_doc" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary">Add Vehicle</button>

        </form>
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
