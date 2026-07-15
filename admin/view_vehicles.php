<?php
include '../db_connection.php';
include '../session_check.php';

function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$driver_id = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
if(!$driver_id){
    header("Location: drivers.php");
    exit;
}

$stmt = $conn->prepare("SELECT full_name FROM users u JOIN drivers d ON u.user_id = d.user_id WHERE d.driver_id = ?");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$res = $stmt->get_result();
$driverRow = $res->fetch_assoc();
$driverName = $driverRow['full_name'] ?? '';
$stmt->close();
// Fetch vehicles
$sql = "SELECT v.vehicle_id, v.driver_id, v.make, v.model, v.year, v.plate_number, v.image_path, v.registration_doc, u.full_name as driver_name
        FROM vehicles v
        INNER JOIN users u ON v.driver_id = u.user_id
        WHERE v.driver_id = ?
        ORDER BY v.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$res = $stmt->get_result();
$vehicles = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();


// Fetch vehicle images prepared statement
$imgStmt = $conn->prepare("SELECT image_path FROM vehicle_images WHERE vehicle_id = ? ORDER BY uploaded_at ASC");

// Placeholder image
$placeholder = 'assets/vehicle-placeholder.png';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Vehicles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="../js/sidebar.js"></script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.vehicle-card { background:#fff; border-radius:10px; box-shadow:0 6px 18px rgba(0,0,0,0.04); margin-bottom:1rem; display:flex; gap:1rem; align-items:center; padding:1rem; }
.vehicle-img { width:180px; height:120px; flex-shrink:0; background:#f5f7f8; display:flex; align-items:center; justify-content:center; }
.vehicle-img img { width:100%; height:100%; object-fit:cover; border-radius:6px; }
.vehicle-info { flex:1; display:flex; flex-direction:column; gap:.5rem; }
.gallery-btn { border:1px solid #028a99; color:#028a99; background:transparent; padding:.45rem .65rem; border-radius:6px; width:max-content; }
.overlay-gallery { display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; }
.overlay-gallery.show { display:flex; }
.overlay-inner { position:relative; max-width:1200px; width:100%; max-height:90vh; display:flex; align-items:center; justify-content:center; }
.overlay-inner img { max-width:100%; max-height:90vh; object-fit:contain; display:block; margin:0 auto; }
.overlay-close { position:absolute; top:16px; right:16px; background:rgba(255,255,255,0.1); border:none; color:#fff; font-size:1.25rem; padding:.5rem .65rem; border-radius:6px; }
.overlay-arrow { position:absolute; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.08); border:none; color:#fff; font-size:1.5rem; padding:.6rem .8rem; border-radius:6px; }
.overlay-arrow.left { left:12px; }
.overlay-arrow.right { right:12px; }
.overlay-caption { position:absolute; bottom:18px; left:50%; transform:translateX(-50%); color:#ddd; font-size:.95rem; background:rgba(0,0,0,0.4); padding:.4rem .8rem; border-radius:6px; }

</style>
</head>
<body>
<div class="dashboard-container">
    <admin-sidebar></admin-sidebar>
    <div class="hamburger">
            <i class="fas fa-bars"></i>
    </div>
    <div class="main-content">
        <div class="dashboard-title d-flex align-items-center justify-content-between mb-3">
            <h2 class="mb-0">Vehicles for <?php echo h($driverName); ?></h2> 
            
            <a href="drivers.php" class="btn btn-outline-secondary btn-sm back">
                <i class="fas fa-arrow-left"></i> Back to Drivers
            </a>
        </div>

        <?php if(empty($vehicles)): ?>
            <div class="text-center text-muted py-5">No vehicles found.</div>
        <?php else: ?>
            <?php foreach($vehicles as $v):
                $vehicleId = (int)$v['vehicle_id'];
                $mainImg = $v['image_path'] ?: $placeholder;

                // Load gallery images
                $gallery = [];
                $imgStmt->bind_param("i",$vehicleId);
                $imgStmt->execute();
                $imgRes = $imgStmt->get_result();
                if($imgRes){
                    while($row = $imgRes->fetch_assoc()){
                        $gallery[] = $row['image_path'];
                    }
                }
                $imgStmt->free_result();
                $gallery_json = json_encode(array_values($gallery));
                $gallery_json_esc = h($gallery_json);
            ?>
            <div class="vehicle-card" data-gallery="<?php echo $gallery_json_esc; ?>" data-main="<?php echo h($mainImg); ?>">
                <div class="vehicle-img">
                    <img src="../<?php echo h($mainImg); ?>" alt="Vehicle image">
                </div>
                <div class="vehicle-info">
                    <strong><?php echo h($v['make'].' '.$v['model'].' ('.$v['year'].')'); ?></strong>
                    <span>Plate: <?php echo h($v['plate_number']); ?></span>
                    <button class="gallery-btn" type="button" onclick="openOverlayGallery(this)">
                        <i class="fas fa-images"></i> View Images
                    </button>

                    <?php if(!empty($v['registration_doc'])): ?>
                        <a class="gallery-btn" href="../<?php echo h($v['registration_doc']); ?>" target="_blank" style="text-decoration: none;">
                            <i class="fas fa-file-alt"></i> View Registration
                        </a>
                    <?php endif; ?>


                </div>
            </div>

            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Overlay gallery -->
        <div id="overlayGallery" class="overlay-gallery" aria-hidden="true">
            <div class="overlay-inner">
                <button class="overlay-arrow left" type="button" onclick="prevImage()">‹</button>
                <button class="overlay-arrow right" type="button" onclick="nextImage()">›</button>
                <button class="overlay-close" type="button" onclick="closeOverlay()">✕</button>
                <div id="overlayImageWrap" style="width:100%; text-align:center;"></div>
                <div id="overlayCaption" class="overlay-caption"></div>
            </div>
        </div>
    </div>
</div>

<script>
let currentGallery = [];
let currentIndex = 0;

function openOverlayGallery(btn){
    const card = btn.closest('.vehicle-card');
    if(!card) return;
    let gallery = [];
    const dataAttr = card.getAttribute('data-gallery');
    if(dataAttr) { try { gallery = JSON.parse(dataAttr); } catch(e){ gallery=[]; } }
    if(!gallery || gallery.length===0){
        const main = card.getAttribute('data-main');
        if(main) gallery=[main];
    }
    if(!gallery || gallery.length===0){ alert('No images available.'); return; }
    currentGallery = gallery;
    currentIndex = 0;
    showOverlayImage();
    document.getElementById('overlayGallery').classList.add('show');
    document.getElementById('overlayGallery').setAttribute('aria-hidden','false');
}

function showOverlayImage(){
    const wrap = document.getElementById('overlayImageWrap');
    const caption = document.getElementById('overlayCaption');
    wrap.innerHTML=''; caption.textContent='';
    if(currentGallery.length===0) return;
    const relPath = currentGallery[currentIndex];
    const src = '../'+relPath;
    const ext = relPath.split('.').pop().toLowerCase();
    if(['jpg','jpeg','png','gif','webp'].includes(ext)){
        const img = document.createElement('img'); img.src=src; img.alt='Vehicle image';
        wrap.appendChild(img);
    } else if(ext==='pdf'){
        const iframe = document.createElement('iframe'); iframe.src=src; iframe.style.width='100%'; iframe.style.height='85vh'; wrap.appendChild(iframe);
    } else { wrap.innerHTML='<div style="color:#fff;">Unsupported file type.</div>'; }
    caption.textContent=(currentIndex+1)+' / '+currentGallery.length;
}

function closeOverlay(){
    document.getElementById('overlayGallery').classList.remove('show');
    document.getElementById('overlayGallery').setAttribute('aria-hidden','true');
    currentGallery=[]; currentIndex=0;
    document.getElementById('overlayImageWrap').innerHTML='';
    document.getElementById('overlayCaption').textContent='';
}

function prevImage(){ if(currentGallery.length===0) return; currentIndex=(currentIndex-1+currentGallery.length)%currentGallery.length; showOverlayImage(); }
function nextImage(){ if(currentGallery.length===0) return; currentIndex=(currentIndex+1)%currentGallery.length; showOverlayImage(); }

document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeOverlay(); });
document.getElementById('overlayGallery').addEventListener('click', e=>{ if(e.target===document.getElementById('overlayGallery')) closeOverlay(); });
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
