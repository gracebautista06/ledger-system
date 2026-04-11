<?php
/* ============================================================
   owner/view_sales.php — Today's Sales Dashboard
   - Shows ONLY today's transactions
   - End-of-day snapshot: revenue, trays, transactions, avg
   - vs-yesterday comparison on revenue
   - Payment method breakdown for the day
   - Per-staff sales summary for the day
   - Full today transaction list with export buttons
   - Link to sales_report.php for full history & exports
   ============================================================ */

$page_title = "Today's Sales";

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

$today     = date('Y-m-d');
$today_fmt = date('l, F j, Y');

// ── TODAY'S SUMMARY ──────────────────────────────────────────────
$stats_q = $conn->query("
    SELECT
        COUNT(*)                          AS total_transactions,
        COALESCE(SUM(quantity_sold),   0) AS total_trays,
        COALESCE(SUM(quantity_sold*30),0) AS total_eggs,
        COALESCE(SUM(total_amount),    0) AS total_revenue,
        COALESCE(AVG(total_amount),    0) AS avg_per_sale,
        COALESCE(MAX(total_amount),    0) AS biggest_sale
    FROM sales
    WHERE DATE(date_sold) = '$today'
");
$stats     = $stats_q ? $stats_q->fetch_assoc() : [];
$today_rev = (float)($stats['total_revenue'] ?? 0);
$total_tx  = (int)($stats['total_transactions'] ?? 0);

// ── YESTERDAY COMPARISON ─────────────────────────────────────────
$yest_q   = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS rev FROM sales WHERE DATE(date_sold)=DATE_SUB('$today',INTERVAL 1 DAY)");
$yest_rev = $yest_q ? (float)$yest_q->fetch_assoc()['rev'] : 0;
$rev_diff = $today_rev - $yest_rev;
$rev_pct  = $yest_rev > 0 ? round(($rev_diff / $yest_rev) * 100, 1) : null;

// ── PAYMENT METHOD BREAKDOWN ─────────────────────────────────────
$payment_q = $conn->query("
    SELECT payment_method, COUNT(*) AS cnt,
           SUM(total_amount) AS total, SUM(quantity_sold) AS trays
    FROM sales
    WHERE DATE(date_sold)='$today'
    GROUP BY payment_method ORDER BY total DESC
");
$payment_rows = [];
if ($payment_q) while ($r = $payment_q->fetch_assoc()) $payment_rows[] = $r;

// ── PER-STAFF SUMMARY ────────────────────────────────────────────
$staff_q = $conn->query("
    SELECT u.username, COUNT(s.sale_id) AS transactions,
           SUM(s.quantity_sold) AS trays, SUM(s.total_amount) AS revenue
    FROM sales s JOIN users u ON s.staff_id=u.user_id
    WHERE DATE(s.date_sold)='$today'
    GROUP BY s.staff_id, u.username ORDER BY revenue DESC
");
$staff_rows = [];
if ($staff_q) while ($r = $staff_q->fetch_assoc()) $staff_rows[] = $r;

// ── TODAY'S TRANSACTIONS ─────────────────────────────────────────
$sales_q = $conn->query("
    SELECT s.*, u.username AS staff_name
    FROM sales s LEFT JOIN users u ON s.staff_id=u.user_id
    WHERE DATE(s.date_sold)='$today'
    ORDER BY s.date_sold DESC
");
?>

<div style="max-width:1080px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>💰 Today's Sales</h2>
            <p><?php echo $today_fmt; ?> — end-of-day snapshot</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <a href="sales_report.php" class="btn-farm btn-dark btn-sm">📊 Full History & Reports →</a>
            <a href="../dashboard.php" class="back-link" style="margin:0;">← Dashboard</a>
        </div>
    </div>

    <!-- ── SNAPSHOT CARDS ───────────────────────────────────────── -->
    <div style="display:flex; gap:16px; margin-bottom:2rem; flex-wrap:wrap;">

        <div class="stat-card" style="border-top:4px solid var(--success); flex:2; min-width:200px;">
            <div class="stat-label">Today's Revenue</div>
            <div class="stat-value" style="font-size:2.3rem; color:var(--success);">
                ₱<?php echo number_format($today_rev, 2); ?>
            </div>
            <div class="stat-sub" style="margin-top:8px;">
                <?php if ($rev_pct !== null): ?>
                    <span style="color:<?php echo $rev_diff>=0?'var(--success)':'var(--danger)'; ?>; font-weight:700;">
                        <?php echo $rev_diff>=0?'▲':'▼'; ?> <?php echo abs($rev_pct); ?>%
                    </span>
                    vs yesterday &nbsp;(₱<?php echo number_format($yest_rev,2); ?>)
                <?php elseif ($yest_rev == 0 && $today_rev > 0): ?>
                    <span style="color:var(--text-muted);">No sales yesterday to compare</span>
                <?php else: ?>
                    <span style="color:var(--text-muted);">No sales recorded today yet</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat-card" style="border-top:4px solid var(--gold);">
            <div class="stat-label">Transactions</div>
            <div class="stat-value"><?php echo number_format($total_tx); ?></div>
            <div class="stat-sub">sales today</div>
        </div>

        <div class="stat-card" style="border-top:4px solid var(--terra-lt);">
            <div class="stat-label">Trays Sold</div>
            <div class="stat-value"><?php echo number_format((int)($stats['total_trays']??0)); ?></div>
            <div class="stat-sub"><?php echo number_format((int)($stats['total_eggs']??0)); ?> eggs</div>
        </div>

        <div class="stat-card" style="border-top:4px solid var(--info);">
            <div class="stat-label">Avg. Per Sale</div>
            <div class="stat-value">₱<?php echo number_format((float)($stats['avg_per_sale']??0),2); ?></div>
            <div class="stat-sub">
                Biggest: ₱<?php echo number_format((float)($stats['biggest_sale']??0),2); ?>
            </div>
        </div>

    </div>

    <?php if ($total_tx === 0): ?>
    <!-- EMPTY STATE -->
    <div class="card" style="text-align:center; padding:4rem 2rem;">
        <div style="font-size:3.5rem; margin-bottom:1rem; opacity:0.3;">💰</div>
        <h3 style="color:var(--text-secondary); margin-bottom:0.5rem;">No sales recorded today yet.</h3>
        <p style="color:var(--text-muted); font-size:0.9rem;">
            Once staff log sales for <?php echo date('F j'); ?>, they'll appear here instantly.
        </p>
        <a href="sales_report.php" class="btn-farm btn-dark btn-sm" style="display:inline-block; margin-top:1.5rem;">
            View Past Sales →
        </a>
    </div>

    <?php else: ?>

    <!-- ── BREAKDOWN: PAYMENT + STAFF ──────────────────────────── -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">

        <!-- Payment Method -->
        <div class="card" style="padding:1.4rem 1.6rem;">
            <h3 style="margin-bottom:1.2rem; font-size:0.92rem;">💳 By Payment Method</h3>
            <?php
            $method_colors = ['Cash'=>'var(--success)','GCash'=>'var(--info)','Bank Transfer'=>'var(--gold)'];
            foreach ($payment_rows as $pm):
                $pct   = $today_rev>0 ? round(((float)$pm['total']/$today_rev)*100,1) : 0;
                $color = $method_colors[$pm['payment_method']] ?? 'var(--text-muted)';
                $icon  = match($pm['payment_method']) { 'GCash'=>'📱','Bank Transfer'=>'🏦',default=>'💵' };
            ?>
            <div style="margin-bottom:1.1rem;">
                <div style="display:flex; justify-content:space-between; font-size:0.84rem; margin-bottom:5px;">
                    <span style="font-weight:600; color:var(--text-primary);">
                        <?php echo $icon; ?> <?php echo htmlspecialchars($pm['payment_method']); ?>
                    </span>
                    <span style="color:var(--text-muted);">
                        <?php echo $pm['cnt']; ?> sale<?php echo $pm['cnt']>1?'s':''; ?>
                        &nbsp;·&nbsp;
                        <strong style="color:var(--success);">₱<?php echo number_format((float)$pm['total'],2); ?></strong>
                    </span>
                </div>
                <div style="background:var(--bg-wood); border-radius:4px; height:8px;">
                    <div style="width:<?php echo $pct; ?>%; background:<?php echo $color; ?>; height:8px; border-radius:4px;"></div>
                </div>
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:3px;">
                    <?php echo $pct; ?>% of today's revenue
                    &nbsp;·&nbsp; <?php echo number_format((int)$pm['trays']); ?> tray<?php echo $pm['trays']>1?'s':''; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Per-Staff Summary -->
        <div class="card" style="padding:1.4rem 1.6rem;">
            <h3 style="margin-bottom:1.2rem; font-size:0.92rem;">👤 Sales by Staff</h3>
            <div class="table-wrapper" style="border:none;">
                <table class="table-farm" style="font-size:0.84rem;">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th style="text-align:center;">Sales</th>
                            <th style="text-align:center;">Trays</th>
                            <th style="text-align:right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_rows as $sr): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sr['username']); ?></strong></td>
                            <td style="text-align:center; color:var(--text-muted);"><?php echo $sr['transactions']; ?></td>
                            <td style="text-align:center; color:var(--text-muted);"><?php echo number_format((int)$sr['trays']); ?></td>
                            <td style="text-align:right; font-weight:700; color:var(--success);">
                                ₱<?php echo number_format((float)$sr['revenue'],2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right;">Total</td>
                            <td style="text-align:right; color:var(--gold);">₱<?php echo number_format($today_rev,2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>

    <!-- ── TODAY'S TRANSACTION LIST ─────────────────────────────── -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div style="padding:1.2rem 1.6rem; border-bottom:1px solid var(--border-subtle);
                    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <h3 style="margin:0;">
                📋 All Transactions Today
                <span style="font-size:0.78rem; font-weight:500; color:var(--text-muted); margin-left:8px;">
                    <?php echo $total_tx; ?> record<?php echo $total_tx!==1?'s':''; ?>
                </span>
            </h3>
        </div>
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Time</th>
                        <th>Staff</th>
                        <th>Customer</th>
                        <th style="text-align:center;">Trays</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                        <th>Payment</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $sales_q->fetch_assoc()):
                        $icon = match($row['payment_method']) { 'GCash'=>'📱','Bank Transfer'=>'🏦',default=>'💵' };
                    ?>
                    <tr>
                        <td style="color:var(--text-muted); font-size:0.75rem;">#<?php echo $row['sale_id']; ?></td>
                        <td style="font-size:0.82rem; color:var(--text-muted); white-space:nowrap;">
                            <?php echo date('g:i A', strtotime($row['date_sold'])); ?>
                        </td>
                        <td style="font-size:0.84rem; color:var(--text-secondary);">
                            <?php echo htmlspecialchars($row['staff_name'] ?? '—'); ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                        <td style="text-align:center; font-weight:700;"><?php echo number_format($row['quantity_sold']); ?></td>
                        <td style="text-align:right; color:var(--text-muted); font-size:0.85rem;">
                            ₱<?php echo number_format((float)$row['unit_price'],2); ?>
                        </td>
                        <td style="text-align:right; font-weight:700; color:var(--success);">
                            ₱<?php echo number_format((float)$row['total_amount'],2); ?>
                        </td>
                        <td style="font-size:0.82rem; white-space:nowrap;">
                            <?php echo $icon; ?> <?php echo htmlspecialchars($row['payment_method']); ?>
                        </td>
                        <td style="font-size:0.78rem; color:var(--text-muted); max-width:150px;">
                            <?php echo htmlspecialchars($row['notes'] ?: '—'); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right; font-size:0.8rem; color:var(--text-muted);">
                            Day Total
                        </td>
                        <td style="text-align:center; font-weight:700; color:var(--gold);">
                            <?php echo number_format((int)$stats['total_trays']); ?> trays
                        </td>
                        <td></td>
                        <td style="text-align:right; font-weight:800; color:var(--gold);">
                            ₱<?php echo number_format($today_rev, 2); ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php endif; ?>

    <div style="margin-top:1.5rem; display:flex; justify-content:space-between;
                align-items:center; flex-wrap:wrap; gap:10px;">
        <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
        <a href="sales_report.php"
           style="font-size:0.84rem; color:var(--gold); text-decoration:none; font-weight:600;">
            Need full history? → Sales Report & Export
        </a>
    </div>

</div>

<?php include('../../includes/footer.php'); ?>