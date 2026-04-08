<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
        header("Location: ../portal/login.php");
        exit();
    }

    // Fetch the count of pending edit requests
    $request_query = $conn->query("SELECT COUNT(*) as total FROM edit_requests WHERE status = 'Pending'");
    $request_data = $request_query->fetch_assoc();
    $pending_count = $request_data['total'];
?>

<style>
    /* Pulsing Red Dot Animation */
    .red-dot {
        height: 10px;
        width: 10px;
        background-color: #ff4d4d;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
        box-shadow: 0 0 0 0 rgba(255, 77, 77, 0.7);
        animation: pulse-red 2s infinite;
    }

    @keyframes pulse-red {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 77, 77, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(255, 77, 77, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 77, 77, 0); }
    }

    .sidebar-dot {
        height: 8px;
        width: 8px;
        background-color: #ff4d4d;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="owner-header" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
    <h1 style="color: var(--barn-red);">Strategic Management Dashboard 📊</h1>
    <span style="font-size: 0.9rem; color: #666; font-weight: bold;">Welcome, Owner</span>
</div>

<?php if ($pending_count > 0): ?>
    <div class="card" style="background: #fff9db; border: 1px solid #fab005; border-left: 8px solid #fab005; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; padding: 15px 20px;">
        <div>
            <h4 style="margin: 0; color: #856404; display: flex; align-items: center;">
                <span class="red-dot"></span> Action Required: Staff Requests
            </h4>
            <p style="margin: 5px 0 0; font-size: 0.9rem; color: #665c33;">
                There are <strong><?php echo $pending_count; ?></strong> staff members requesting to correct their logs.
            </p>
        </div>
        <a href="review_requests.php" class="btn-farm" style="background: #fab005; color: #000; padding: 10px 20px; text-decoration: none; font-weight: bold; border-radius: 5px; box-shadow: 0 3px 0 #c49000;">
            Review Requests →
        </a>
    </div>
<?php endif; ?>

<div style="display: flex; gap: 20px; margin-bottom: 2rem; flex-wrap: wrap;">
    <div class="card" style="flex: 1; border-top: 5px solid #28a745;">
        <small style="font-weight: bold; color: #999;">REVENUE (THIS MONTH)</small>
        <h2 style="margin-top: 10px;">₱ 45,200.00</h2>
    </div>
    <div class="card" style="flex: 1; border-top: 5px solid var(--accent-orange);">
        <small style="font-weight: bold; color: #999;">ACTIVE BATCH</small>
        <h2 style="margin-top: 10px;">Batch #24 - Lohmann</h2>
    </div>
    <div class="card" style="flex: 1; border-top: 5px solid var(--primary-yolk);">
        <small style="font-weight: bold; color: #999;">AVG. DAILY YIELD</small>
        <h2 style="margin-top: 10px;">88% Production</h2>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
    <div class="card">
        <h3>Production Trends (Weekly)</h3>
        <canvas id="productionChart" style="width:100%; max-height:300px;"></canvas>
    </div>

    <div class="controls">
        <h3 style="margin-bottom: 1rem;">Admin Actions</h3>
        
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="manage_batches.php" class="btn-farm" style="text-align:center; background: var(--dark-nest);">📦 Manage Flock Batches</a>
            <a href="manage_users.php" class="btn-farm" style="text-align:center; background: #555;">👥 Manage Farm Staff</a>
            <a href="manage_prices.php" class="btn-farm" style="text-align:center; background: var(--accent-orange);">🏷️ Set Unit Pricing</a>
            <a href="view_inventory.php" class="btn-farm" style="text-align:center; background: var(--barn-red);">🥚 Real-time Inventory</a>
            <a href="view_history.php" class="btn-farm" style="text-align:center; background: #607d8b; border-bottom: 4px solid #455a64;">📜 View System History</a>
            
            <a href="review_requests.php" class="btn-farm" style="text-align:center; background: #f1f3f5; color: #495057; border: 1px solid #dee2e6; display: flex; align-items: center; justify-content: center;">
                <?php if ($pending_count > 0): ?>
                    <span class="sidebar-dot"></span>
                <?php endif; ?>
                Review Edit Requests (<?php echo $pending_count; ?>)
            </a>
        </div>

        <div class="card" style="margin-top: 20px; border-left: 5px solid var(--accent-orange); background: #fffcf9;">
            <p style="font-size: 0.85rem;"><strong>Next Replacement:</strong><br>
            Batch #24 is scheduled for replacement on <strong>Oct 2026</strong>.</p>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('productionChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            label: 'Eggs Harvested',
            data: [450, 480, 430, 500, 490, 510, 485],
            borderColor: '#FF6B35',
            backgroundColor: 'rgba(255, 107, 53, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: { 
        responsive: true,
        plugins: {
            legend: { display: false }
        }
    }
});
</script>

<?php include('../includes/footer.php'); ?>