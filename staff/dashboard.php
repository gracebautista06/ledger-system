<?php
/*  staff/dashboard.php — Staff Operations Hub  */
$page_title = 'Staff Dashboard';

include('../includes/db.php');
include('../includes/header.php');
include('../includes/log_activity.php');
include('../includes/notifications.php');

// Auth check — session_start() is handled inside header.php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Staff') {
    header("Location: ../portal/login.php"); exit();
}

$username = $_SESSION['username'];
$staff_id = (int) $_SESSION['user_id'];

// Time-of-day greeting
$hour     = (int) date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

// Today's harvest count
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_eggs),0) AS logged_today FROM harvests WHERE staff_id=? AND DATE(date_logged)=CURDATE()");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$logged_today = (int) $stmt->get_result()->fetch_assoc()['logged_today'];
$stmt->close();

// Pending edit requests
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM edit_requests WHERE staff_id=? AND status='Pending'");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$my_pending = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Today's sales stats
$sales_stmt = $conn->prepare("SELECT COALESCE(SUM(quantity_sold),0) AS sold_today, COALESCE(SUM(total_amount),0) AS revenue_today FROM sales WHERE staff_id=? AND DATE(date_sold)=CURDATE()");
$sales_stmt->bind_param("i", $staff_id);
$sales_stmt->execute();
$sales_today   = $sales_stmt->get_result()->fetch_assoc();
$sales_stmt->close();
$trays_today   = (int) $sales_today['sold_today'];
$revenue_today = (float) $sales_today['revenue_today'];

// sell_first_alert is intentionally NOT shown inline here anymore.
// Staff sees it by clicking the bell → my_notifications.php
?>

<!-- Page heading with notification bell -->
<div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap;
            gap:12px; margin-bottom:2rem; animation:slideUp 0.5s ease-out;">
    <div>
        <h1 style="color:var(--gold); font-family:'Playfair Display',serif; font-size:1.9rem;">
            <?php echo $greeting; ?>, <?php echo htmlspecialchars($username); ?>! 👩‍🌾
        </h1>
        <p style="color:var(--text-muted);">Ready to log today's farm activities? — <?php echo date('l, F j, Y'); ?></p>
    </div>
    <div style="display:flex; align-items:center; gap:10px; margin-top:4px;">
        <?php render_notification_bell($conn, 'Staff'); ?>
    </div>
</div>

<?php render_notification_panel($conn, 'Staff'); ?>

<?php if ($my_pending > 0): ?>
<div class="alert warning" style="margin-bottom:1.5rem;">
    ⏳ You have <strong><?php echo $my_pending; ?></strong> edit request<?php echo $my_pending > 1 ? 's' : ''; ?> awaiting Owner review.
    <a href="view_logs.php" style="color:var(--warning); font-weight:700; margin-left:8px;">View Logs →</a>
</div>
<?php endif; ?>

<!-- Today's stats with progress bar -->
<div style="display:flex; gap:20px; margin-bottom:2.5rem; flex-wrap:wrap;">
    <div class="stat-card" style="border-left:5px solid var(--gold-dim); flex:1; min-width:180px;">
        <div class="stat-label">Daily Goal</div>
        <div class="stat-value">500 eggs</div>
        <div class="stat-sub">Farm production target</div>
    </div>
    <div class="stat-card" style="border-left:5px solid var(--terra-lt); flex:1; min-width:180px;">
        <div class="stat-label">You've Logged Today</div>
        <div class="stat-value"><?php echo number_format($logged_today); ?></div>
        <div class="stat-sub"><?php echo $logged_today >= 500 ? '🎉 Goal reached!' : number_format(max(0, 500 - $logged_today)) . ' to go'; ?></div>
        <div style="margin-top:10px; background:var(--bg-plank); border-radius:4px; height:6px; overflow:hidden;">
            <div style="width:<?php echo min(100, round(($logged_today / 500) * 100)); ?>%; background:var(--terra-lt); height:6px; border-radius:4px; transition:width 0.5s;"></div>
        </div>
    </div>
    <div class="stat-card" style="border-left:5px solid var(--success); flex:1; min-width:180px;">
        <div class="stat-label">Today's Sales</div>
        <div class="stat-value"><?php echo number_format($trays_today); ?></div>
        <div class="stat-sub">
            <?php echo $trays_today > 0
                ? 'Revenue: ₱' . number_format($revenue_today, 2)
                : 'No sales logged yet'; ?>
        </div>
    </div>
</div>

<!-- Action Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px;">

    <div class="card" style="text-align:center; padding:2.2rem; border-top:4px solid var(--gold);">
        <div style="font-size:3rem; animation:float 3s ease-in-out infinite; display:inline-block;">🧺</div>
        <h3 style="margin:1rem 0 0.5rem; color:var(--gold); font-family:'Playfair Display',serif;">New Harvest</h3>
        <p style="color:var(--text-muted); margin-bottom:1.5rem; font-size:0.9rem;">Record egg counts by size (PW, S, M, L, XL, J).</p>
        <a href="log_harvest.php" class="btn-farm btn-full">Record Eggs 🧺</a>
    </div>

    <div class="card" style="text-align:center; padding:2.2rem; border-top:4px solid var(--success);">
        <div style="font-size:3rem; animation:float 3s ease-in-out infinite; animation-delay:0.2s; display:inline-block;">💰</div>
        <h3 style="margin:1rem 0 0.5rem; color:var(--gold); font-family:'Playfair Display',serif;">Record Sale</h3>
        <p style="color:var(--text-muted); margin-bottom:1.5rem; font-size:0.9rem;">Log a sale with customer, quantity, and payment.</p>
        <a href="log_sale.php" class="btn-farm btn-green btn-full">New Sale 💰</a>
    </div>

    <div class="card" style="text-align:center; padding:2.2rem; border-top:4px solid var(--terra-lt);">
        <div style="font-size:3rem; animation:float 3s ease-in-out infinite; animation-delay:0.4s; display:inline-block;">🐔</div>
        <h3 style="margin:1rem 0 0.5rem; color:var(--gold); font-family:'Playfair Display',serif;">Flock Status</h3>
        <p style="color:var(--text-muted); margin-bottom:1.5rem; font-size:0.9rem;">Report feed, health observations, or deaths.</p>
        <a href="log_health.php" class="btn-farm btn-orange btn-full">Status Update 🐔</a>
    </div>

    <div class="card" style="text-align:center; padding:2.2rem; border-top:4px solid var(--info);">
        <div style="font-size:3rem; animation:float 3s ease-in-out infinite; animation-delay:0.6s; display:inline-block;">📋</div>
        <h3 style="margin:1rem 0 0.5rem; color:var(--gold); font-family:'Playfair Display',serif;">My Log History</h3>
        <p style="color:var(--text-muted); margin-bottom:1.5rem; font-size:0.9rem;">View your entries and submit correction requests.</p>
        <a href="view_logs.php" class="btn-farm btn-dark btn-full">View Logs 📋</a>
    </div>

</div>

<div style="margin-top:2.5rem; text-align:right;">
    <a href="../portal/logout.php" style="color:var(--text-muted); font-size:0.85rem; text-decoration:none;">🚪 Secure Logout</a>
</div>

<?php include('../includes/footer.php'); ?>