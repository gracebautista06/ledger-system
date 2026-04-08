<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    // SECURITY CHECK: If not logged in or not staff, kick them out
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Staff') {
        header("Location: ../portal/login.php");
        exit();
    }

    $username = $_SESSION['username'];
?>

<div class="staff-header" style="margin-bottom: 2rem; animation: slideUp 0.5s ease-out;">
    <h1 style="color: var(--barn-red);">Good Morning, <?php echo htmlspecialchars($username); ?>! 👩‍🌾</h1>
    <p style="color: #666;">Ready to log today's farm activities?</p>
</div>

<div style="display: flex; gap: 20px; margin-bottom: 3rem; flex-wrap: wrap;">
    <div class="card" style="flex: 1; min-width: 250px; border-left: 8px solid var(--primary-yolk);">
        <h4 style="color: #888; text-transform: uppercase; font-size: 0.8rem;">Today's Goal</h4>
        <p style="font-size: 1.5rem; font-weight: 700; color: var(--dark-nest);">500 Eggs</p>
    </div>
    <div class="card" style="flex: 1; min-width: 250px; border-left: 8px solid var(--accent-orange);">
        <h4 style="color: #888; text-transform: uppercase; font-size: 0.8rem;">Logged Today</h4>
        <p style="font-size: 1.5rem; font-weight: 700; color: var(--dark-nest);">0 Eggs</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
    
    <div class="card" style="text-align: center; padding: 2.5rem;">
        <div class="floating-icon" style="font-size: 3.5rem;">🧺</div>
        <h3 style="margin: 1rem 0;">New Harvest</h3>
        <p style="color: #777; margin-bottom: 1.5rem; font-size: 0.9rem;">Record quantity, egg sizes (S, M, L, XL), and collection time.</p>
        <a href="log_harvest.php" class="btn-farm" style="width: 100%;">Record Eggs</a>
    </div>

    <div class="card" style="text-align: center; padding: 2.5rem; border-top: 5px solid #28a745;">
        <div class="floating-icon" style="font-size: 3.5rem;">💰</div>
        <h3 style="margin: 1rem 0;">Record Sale</h3>
        <p style="color: #777; margin-bottom: 1.5rem; font-size: 0.9rem;">Log a new sale, customer details, and total payment received.</p>
        <a href="log_sale.php" class="btn-farm" style="width: 100%; background-color: #28a745;">New Sale</a>
    </div>

    <div class="card" style="text-align: center; padding: 2.5rem;">
        <div class="floating-icon" style="font-size: 3.5rem; animation-delay: 0.3s;">🐔</div>
        <h3 style="margin: 1rem 0;">Flock Status</h3>
        <p style="color: #777; margin-bottom: 1.5rem; font-size: 0.9rem;">Report feed levels, health issues, or mortality rates.</p>
        <a href="log_health.php" class="btn-farm" style="width: 100%; background-color: var(--dark-nest);">Status Update</a>
    </div>

    <div class="card" style="text-align: center; padding: 2.5rem;">
        <div class="floating-icon" style="font-size: 3.5rem; animation-delay: 0.6s;">📦</div>
        <h3 style="margin: 1rem 0;">Stock View</h3>
        <p style="color: #777; margin-bottom: 1.5rem; font-size: 0.9rem;">View recent entries and ensure all data is correctly synced.</p>
        <a href="view_logs.php" class="btn-farm" style="width: 100%; background-color: var(--accent-orange);">View Logs</a>
    </div>

</div>

<div style="margin-top: 3rem; text-align: right;">
    <a href="../portal/logout.php" style="color: #888; font-size: 0.9rem; text-decoration: none;">🚪 Secure Logout</a>
</div>

<?php include('../includes/footer.php'); ?>