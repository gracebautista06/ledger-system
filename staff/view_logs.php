<?php
/*  staff/view_logs.php — Staff Log History (Harvests + Health + Sales)  */
$page_title = 'My Logs';

include('../includes/db.php');
include('../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Staff') {
    header("Location: ../portal/login.php"); exit();
}

$staff_id = (int) $_SESSION['user_id'];

// FIX: Handle all three flash message keys including sale_saved
$flash = "";
if (isset($_GET['harvest_saved'])) {
    $flash = "<div class='alert success'>✅ Harvest logged successfully!</div>";
} elseif (isset($_GET['health_saved'])) {
    $flash = "<div class='alert success'>✅ Health report submitted!</div>";
} elseif (isset($_GET['sale_saved'])) {
    $flash = "<div class='alert success'>✅ Sale recorded successfully!</div>";
} elseif (isset($_GET['request_sent'])) {
    $flash = "<div class='alert info'>📤 Edit request sent to the Owner for review.</div>";
}

// Fetch Harvests (with pending edit request flag)
$h_stmt = $conn->prepare("
    SELECT h.*, b.breed, er.status AS request_status
    FROM harvests h
    JOIN batches b ON h.batch_id = b.batch_id
    LEFT JOIN edit_requests er ON er.record_id = h.harvest_id AND er.record_type = 'Harvest' AND er.status = 'Pending'
    WHERE h.staff_id = ?
    ORDER BY h.date_logged DESC LIMIT 10");
$h_stmt->bind_param("i", $staff_id);
$h_stmt->execute();
$harvest_logs = $h_stmt->get_result();
$h_stmt->close();

// Fetch Health Reports
$fh_stmt = $conn->prepare("
    SELECT fh.*, b.breed, er.status AS request_status
    FROM flock_health fh
    JOIN batches b ON fh.batch_id = b.batch_id
    LEFT JOIN edit_requests er ON er.record_id = fh.report_id AND er.record_type = 'Health' AND er.status = 'Pending'
    WHERE fh.staff_id = ?
    ORDER BY fh.date_reported DESC LIMIT 5");
$fh_stmt->bind_param("i", $staff_id);
$fh_stmt->execute();
$health_logs = $fh_stmt->get_result();
$fh_stmt->close();

// NEW: Fetch Sales
$s_stmt = $conn->prepare("
    SELECT * FROM sales WHERE staff_id = ? ORDER BY date_sold DESC LIMIT 10");
$s_stmt->bind_param("i", $staff_id);
$s_stmt->execute();
$sale_logs = $s_stmt->get_result();
$s_stmt->close();
?>

<div style="max-width:1100px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>📋 Your Recent Activity</h2>
            <p>Last 10 harvests, 10 sales, and 5 health reports.</p>
        </div>
        <a href="dashboard.php" class="back-link" style="margin:0;">← Back to Menu</a>
    </div>

    <?php echo $flash; ?>

    <!-- Harvest Logs -->
    <div class="card" style="border-top:5px solid var(--gold); margin-bottom:2rem; padding:0; overflow:hidden;">
        <div style="padding:1.4rem 1.8rem 1rem; border-bottom:1px solid var(--border-subtle);">
            <h3 style="margin:0;">🧺 Recent Harvests</h3>
        </div>
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr><th>Date & Time</th><th>Batch</th><th>Total</th><th>Breakdown (PW-S-M-L-XL-J)</th><th>Notes</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if ($harvest_logs && $harvest_logs->num_rows > 0):
                        while ($row = $harvest_logs->fetch_assoc()): ?>
                    <tr>
                        <td style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;"><?php echo date('M d, g:i A', strtotime($row['date_logged'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($row['breed']); ?></strong></td>
                        <td style="font-weight:700; color:var(--gold);"><?php echo number_format($row['total_eggs']); ?></td>
                        <td style="font-family:monospace; font-size:0.8rem; white-space:nowrap;"><?php echo "{$row['size_pw']}-{$row['size_s']}-{$row['size_m']}-{$row['size_l']}-{$row['size_xl']}-{$row['size_j']}"; ?></td>
                        <td style="font-size:0.85rem; max-width:180px;"><?php echo htmlspecialchars($row['notes'] ?: '—'); ?></td>
                        <td style="white-space:nowrap;">
                            <?php if ($row['request_status'] === 'Pending'): ?>
                                <span class="badge badge-pending">⏳ Pending</span>
                            <?php else: ?>
                                <a href="request_edit.php?type=Harvest&id=<?php echo $row['harvest_id']; ?>" class="btn-farm btn-outline btn-sm">✏️ Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6"><div class="empty-state"><span class="empty-icon">🧺</span><p>No harvest logs yet.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sales Logs — NEW -->
    <div class="card" style="border-top:5px solid var(--success); margin-bottom:2rem; padding:0; overflow:hidden;">
        <div style="padding:1.4rem 1.8rem 1rem; border-bottom:1px solid var(--border-subtle);">
            <h3 style="margin:0;">💰 Recent Sales</h3>
        </div>
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr><th>Date</th><th>Customer</th><th>Trays</th><th>Unit Price</th><th>Total</th><th>Payment</th><th>Notes</th></tr>
                </thead>
                <tbody>
                    <?php if ($sale_logs && $sale_logs->num_rows > 0):
                        while ($row = $sale_logs->fetch_assoc()): ?>
                    <tr>
                        <td style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;"><?php echo date('M d, g:i A', strtotime($row['date_sold'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                        <td style="text-align:center; font-weight:700;"><?php echo number_format($row['quantity_sold']); ?></td>
                        <td>₱<?php echo number_format((float)$row['unit_price'], 2); ?></td>
                        <td style="font-weight:700; color:var(--success);">₱<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                        <td style="font-size:0.82rem;"><?php echo htmlspecialchars($row['payment_method']); ?></td>
                        <td style="font-size:0.82rem; color:var(--text-muted); max-width:150px;"><?php echo htmlspecialchars($row['notes'] ?: '—'); ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7"><div class="empty-state"><span class="empty-icon">💰</span><p>No sales recorded yet.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Health Logs -->
    <div class="card" style="border-top:5px solid var(--terra-lt); padding:0; overflow:hidden;">
        <div style="padding:1.4rem 1.8rem 1rem; border-bottom:1px solid var(--border-subtle);">
            <h3 style="margin:0;">🐔 Health Reports</h3>
        </div>
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr><th>Date</th><th>Batch</th><th>Status</th><th>Mortality</th><th>Observations</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if ($health_logs && $health_logs->num_rows > 0):
                        while ($row = $health_logs->fetch_assoc()): ?>
                    <tr>
                        <td style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;"><?php echo date('M d, g:i A', strtotime($row['date_reported'])); ?></td>
                        <td><?php echo htmlspecialchars($row['breed']); ?></td>
                        <td>
                            <span class="badge <?php echo match($row['status_level']) { 'Healthy'=>'badge-healthy','Warning'=>'badge-warning','Critical'=>'badge-critical',default=>'' }; ?>">
                                <?php echo $row['status_level']; ?>
                            </span>
                        </td>
                        <td style="text-align:center; font-weight:700;"><?php echo $row['mortality_count']; ?></td>
                        <td style="font-size:0.85rem; max-width:200px;"><?php echo htmlspecialchars($row['symptoms'] ?: '—'); ?></td>
                        <td style="white-space:nowrap;">
                            <?php if ($row['request_status'] === 'Pending'): ?>
                                <span class="badge badge-pending">⏳ Pending</span>
                            <?php else: ?>
                                <a href="request_edit.php?type=Health&id=<?php echo $row['report_id']; ?>" class="btn-farm btn-outline btn-sm">✏️ Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6"><div class="empty-state"><span class="empty-icon">🐔</span><p>No health reports found.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>