class RiderSidebar extends HTMLElement {
    connectedCallback() {
        const driverLink = isDriver ? '../driver/dashboard.php' : 'become_driver.php';
        const driverText = isDriver ? 'Driver Dashboard' : 'Become a Driver';

        this.innerHTML = `
            <div class="sidebar">
                <h4 style="margin-bottom: 50px; margin-top: 30px;">Rider Dashboard</h4>
                <ul class="sidebar-menu">
                    <li><a href="dashboard.php" ><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="available_trips.php" ><i class="fas fa-list-ul"></i> Reserve a Trip</a></li>
                    <li><a href="my_reservations.php" ><i class="fas fa-calendar-check"></i> My Reservations</a></li>
                    <li><a href="profile.php" ><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="${driverLink}" ><i class="fas fa-id-card"></i> ${driverText}</a></li>
                    <li><a href="../logout.php" ><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        `;
    }
}
customElements.define('rider-sidebar', RiderSidebar);

class DriverSidebar extends HTMLElement{
    connectedCallback() {
        this.innerHTML = `
        <div class="sidebar">
            <h4 style="margin-bottom: 50px; margin-top: 30px;">Driver Dashboard</h4>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="create_trip.php"><i class="fas fa-plus"></i> Create Trip</a></li>
                <li><a href="my_trips.php" class="active"><i class="fas fa-route"></i> My Trips</a></li>
                <li><a href="vehicles.php"><i class="fas fa-bus-alt"></i> My Vehicles</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../rider/dashboard.php"><i class="fas fa-user-graduate"></i> Rider Dashboard</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        `;
    } 
}
class AdminSidebar extends HTMLElement{
    connectedCallback() {
        this.innerHTML = `
        <div class="sidebar">
            <h4 style="margin-bottom: 50px; margin-top: 30px;">Admin Dashboard</h4>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-users"></i> Riders</a></li>
                <li><a href="drivers.php"><i class="fas fa-id-card"></i>Drivers</a></li>
                <!-- <li><a href="reservations.php"><i class="fas fa-route"></i> Manage Reservations</a></li> -->
                <li><a href="trips.php"><i class="fas fa-route"></i> Manage Trips</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        `;
    } 
}
customElements.define('driver-sidebar', DriverSidebar);
customElements.define('admin-sidebar', AdminSidebar);
