<?php
include '../db_connection.php';
include '../session_check.php';
include '../send_email.php';
include '../update_trip_statuses.php';

/* ---------------------------------------
   ADMIN ACCESS ONLY
--------------------------------------- */
if ($userRole !== 1) {
    header("Location: ../login.php");
    exit;
}

function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------
   VALIDATE TRIP ID
--------------------------------------- */
$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;

if ($tripId <= 0) {
    header("Location: trips.php?alert=Invalid+trip+ID&type=danger");
    exit;
}

/* ---------------------------------------
   FETCH TRIP + DRIVER
--------------------------------------- */
$stmt = $conn->prepare("
    SELECT 
        t.trip_id, t.trip_status, t.start_location, t.destination, 
        t.trip_date, t.trip_time,
        u.user_id AS driver_user_id, u.email AS driver_email, u.full_name AS driver_name
    FROM trips t
    JOIN drivers d ON t.driver_id = d.driver_id
    JOIN users u ON d.user_id = u.user_id
    WHERE t.trip_id = ?
");
$stmt->bind_param("i", $tripId);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trip || $trip['trip_status'] !== 'Upcoming') {
    header("Location: trips.php?alert=Only+upcoming+trips+can+be+cancelled&type=warning");
    exit;
}

/*PREPARE DETAILS*/
$tripFrom = $trip['start_location'];
$tripTo   = $trip['destination'];
$tripDate = date('M d, Y', strtotime($trip['trip_date']));
$tripTime = date('h:i A', strtotime($trip['trip_time']));

/* ---------------------------------------
   START TRANSACTION
--------------------------------------- */
$conn->begin_transaction();

try {

    /* ---- Cancel Trip ---- */
    $updTrip = $conn->prepare("
        UPDATE trips 
        SET trip_status = 'Cancelled' 
        WHERE trip_id = ?
    ");
    $updTrip->bind_param("i", $tripId);
    $updTrip->execute();
    $updTrip->close();

    /* ---- Notify Driver ---- */
    $driverNotifTitle = "Trip Cancelled by Admin";
    $driverNotifMsg = "Your trip from {$tripFrom} to {$tripTo} on {$tripDate} at {$tripTime} has been cancelled by the admin.";

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, for_role)
        VALUES (?, ?, ?, 'Trip', 'Driver')
    ");
    $stmt->bind_param(
        "iss",
        $trip['driver_user_id'],
        $driverNotifTitle,
        $driverNotifMsg
    );
    $stmt->execute();
    $stmt->close();
    /* ---- Fetch Confirmed Riders BEFORE cancelling ---- */
    $ridersStmt = $conn->prepare("
        SELECT u.user_id, u.email, u.full_name
        FROM reservations r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.trip_id = ? AND r.status = 'confirmed'
    ");
    $ridersStmt->bind_param("i", $tripId);
    $ridersStmt->execute();
    $ridersRes = $ridersStmt->get_result();

    $riders = [];
    while ($row = $ridersRes->fetch_assoc()) {
        $riders[] = $row;
    }
    $ridersStmt->close();


    /* ---- Notify Riders ---- */
    foreach ($riders as $r) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, for_role)
            VALUES (?, ?, ?, 'Trip', 'Rider')
        ");
        $title = "Trip Cancelled";
        $msg = "Your trip from {$tripFrom} to {$tripTo} on {$tripDate} at {$tripTime} has been cancelled by the admin.";
        $stmt->bind_param("iss", $r['user_id'], $title, $msg);
        $stmt->execute();
        $stmt->close();
    }
    /* ---- Cancel ALL Reservations on This Trip ---- */
    $cancelResStmt = $conn->prepare("
        UPDATE reservations
        SET status = 'cancelled'
        WHERE trip_id = ?
        AND status = 'confirmed'
    ");
    $cancelResStmt->bind_param("i", $tripId);
    $cancelResStmt->execute();
    $cancelResStmt->close();

    /* ---- Commit DB Changes ---- */
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    header("Location: trips.php?alert=Failed+to+cancel+trip&type=danger");
    exit;
}

/* ---------------------------------------
   SEND EMAIL (AFTER COMMIT)
--------------------------------------- */

/* Driver Email */
sendEmail(
    $trip['driver_email'],
    $trip['driver_name'],
    'Trip Cancelled by Admin',
    "
    <p>Dear <strong>{$trip['driver_name']}</strong>,</p>
    <p>Your trip <strong>{$tripFrom} → {$tripTo}</strong><br>
    on <strong>{$tripDate}</strong> at <strong>{$tripTime}</strong>
    has been <span style='color:red;'>cancelled by the admin</span>.</p>
    <p>Please check your UniRide dashboard.</p>
    <br>
    <p>Regards,<br><strong>UniRide Admin</strong></p>
    "
);

/* Rider Emails */
foreach ($riders as $r) {
    sendEmail(
        $r['email'],
        $r['full_name'],
        'Trip Cancelled',
        "
        <p>Dear <strong>{$r['full_name']}</strong>,</p>
        <p>Your reservation for the trip
        <strong>{$tripFrom} → {$tripTo}</strong><br>
        on <strong>{$tripDate}</strong> at <strong>{$tripTime}</strong>
        has been <span style='color:red;'>cancelled by the admin</span>.</p>
        <p>Please check your UniRide dashboard.</p>
        <br>
        <p>Regards,<br><strong>UniRide Team</strong></p>
        "
    );
}

/* ---------------------------------------
   REDIRECT BACK
--------------------------------------- */
header("Location: trips.php?alert=Trip+cancelled+successfully&type=success");
exit;
?>