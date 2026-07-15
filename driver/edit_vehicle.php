<?php
include '../db_connection.php';
include '../session_check.php';
if (!$driver_id || $userRole !== 3 || !$isDriver) {
    header("Location: ../rider/dashboard.php");
    exit;
}


$vehicle_id = intval($_GET['vehicle_id'] ?? 0);

// Verify vehicle belongs to driver
$stmt = $conn->prepare("SELECT * FROM vehicles WHERE vehicle_id=? AND driver_id=?");
$stmt->bind_param("ii", $vehicle_id, $driver_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<h3>Unauthorized or vehicle does not exist.</h3>");
}

$vehicle = $result->fetch_assoc();
$stmt->close();

// Folder path
$vehicleFolder = "uploads/drivers/driver_" . $driver_id . "/vehicle_" . $vehicle_id . "/";
$galleryFolder = $vehicleFolder . "gallery/";

// Ensure folders exist
if (!is_dir("../" . $vehicleFolder)) mkdir("../" . $vehicleFolder, 0775, true);
if (!is_dir("../" . $galleryFolder)) mkdir("../" . $galleryFolder, 0775, true);

// Helper function
function uploadFile($fileKey, $folder, $allowed = ['jpg','jpeg','png','pdf'])
{
    if (!isset($_FILES[$fileKey]) || !is_uploaded_file($_FILES[$fileKey]['tmp_name']))
        return null;

    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return null;

    $filename = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
    $newPath = $folder . $filename;

    move_uploaded_file($_FILES[$fileKey]['tmp_name'], "../" . $newPath);

    return $newPath;
}

$alerts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $year = intval($_POST['year']);
    $plate = trim($_POST['plate_number']);

    if ($make === '' || $model === '' || $year === 0 || $plate === '') {
        $alerts[] = ['type'=>'danger','msg'=>'All fields are required.'];
    } else {

        // Upload new main image (optional)
        $imagePath = $vehicle['image_path'];
        $newImage = uploadFile("image", $vehicleFolder);

        if ($newImage) {
            if (!empty($imagePath) && file_exists("../".$imagePath)) {
                unlink("../" . $imagePath);
            }
            $imagePath = $newImage;
        }

        // Upload new registration doc (optional)
        $docPath = $vehicle['registration_doc'];
        $newDoc = uploadFile("registration_doc", $vehicleFolder);

        if ($newDoc) {
            if (!empty($docPath) && file_exists("../".$docPath)) {
                unlink("../" . $docPath);
            }
            $docPath = $newDoc;
        }

        // Update DB
        $stmt = $conn->prepare("
            UPDATE vehicles
            SET make=?, model=?, year=?, plate_number=?, image_path=?, registration_doc=?
            WHERE vehicle_id=? AND driver_id=?
        ");

        $stmt->bind_param(
            "ssisssii",
            $make, $model, $year, $plate, $imagePath, $docPath,
            $vehicle_id, $driver_id
        );

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
<title>Edit Vehicle</title>
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

        <h2>Edit Vehicle</h2>

        <?php foreach($alerts as $a): ?>
            <div class="alert alert-<?php echo $a['type']; ?>"><?php echo $a['msg']; ?></div>
        <?php endforeach; ?>

        <form method="POST" enctype="multipart/form-data" class="mt-3">

            <div class="mb-3">
                <label class="form-label">Make</label>
                <input type="text" name="make" class="form-control" value="<?php echo htmlspecialchars($vehicle['make']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Model</label>
                <input type="text" name="model" class="form-control" value="<?php echo htmlspecialchars($vehicle['model']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Year</label>
                <input type="number" name="year" min="1990" max="2050" class="form-control" value="<?php echo $vehicle['year']; ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Plate Number</label>
                <input type="text" name="plate_number" class="form-control" value="<?php echo htmlspecialchars($vehicle['plate_number']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Current Main Image</label><br>
                <?php if ($vehicle['image_path']): ?>
                    <img src="../<?php echo htmlspecialchars($vehicle['image_path']); ?>" class="img-fluid mb-2" style="max-width:200px;">
                <?php else: ?>
                    <div class="text-muted">No image</div>
                <?php endif; ?>
                <input type="file" name="image" class="form-control mt-2">
            </div>

            <div class="mb-3">
                <label class="form-label">Registration Document</label><br>
                <?php if ($vehicle['registration_doc']): ?>
                    <a href="../<?php echo htmlspecialchars($vehicle['registration_doc']); ?>" target="_blank">View Document</a>
                <?php else: ?>
                    <div class="text-muted">No document</div>
                <?php endif; ?>
                <input type="file" name="registration_doc" class="form-control mt-2">
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>

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
