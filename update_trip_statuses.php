<?php
if (!isset($conn)) {
    return;
}

$stmt = $conn->prepare("
    UPDATE trips
    SET trip_status = CASE
        WHEN trip_status IN ('Cancelled', 'Deleted') THEN trip_status
        WHEN TIMESTAMP(trip_date, trip_time) > NOW() THEN 'Upcoming'
        WHEN NOW() BETWEEN TIMESTAMP(trip_date, trip_time)
             AND TIMESTAMP(trip_date, trip_time) + INTERVAL 45 MINUTE
             THEN 'Ongoing'
        ELSE 'Completed'
    END
    WHERE trip_status NOT IN ('Cancelled', 'Deleted')
");

$stmt->execute();
$stmt->close();
?>