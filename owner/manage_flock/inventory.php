<?php
/* ============================================================
   owner/manage_flock/inventory.php — Real-time Egg Inventory
   + Old Stock Detection & Sell-First Notification System

   NEW FEATURES:
   - Batches sorted by arrival_date ASC — oldest is always first
   - Oldest batch with remaining stock flagged as "OLD STOCK"
   - Owner clicks "📢 Sell First" → modal opens to set target trays
     and optional custom message → inserts into notifications table
   - Active sell-first alert shown at top if one is already live
   - Staff will see the notification on their dashboard
   - When fully sold, owner dashboard shows completion alert
   ============================================================ */

$page_title = 'Egg Inventory';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

$message = '';

// ── HANDLE: Send "Sell First" notification ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'notify_sell_first') {
    $batch_id     = intval($_POST['batch_id']);
    $target_trays = max(1, intval($_POST['target_trays']));
    $custom_msg   = trim($_POST['custom_msg'] ?? '');
    $breed_raw    = trim($_POST['breed'] ?? 'this batch');
    $breed        = $conn->real_escape_string($breed_raw);

    $msg = !empty($custom_msg)
        ? $conn->real_escape_string($custom_msg)
        : "Please prioritize selling Batch #{$batch_id} ({$breed_raw}) — this is the oldest stock on hand. Target: {$target_trays} tray(s).";

    // Supersede any existing active notification for this batch
    $conn->query("UPDATE notifications
                  SET status='completed'
                  WHERE batch_id=$batch_id AND status IN ('unread','read')");

    // Insert new notification
    $conn->query("INSERT INTO notifications (sender_role, batch_id, target_trays, message, status)
                  VALUES ('Owner', $batch_id, $target_trays, '$msg', 'unread')");

    $message = "<div class='alert success'>✅ Notification sent! Staff will see it on their dashboard.</div>";
}

// ── TOTALS BY EGG SIZE (all batches combined) ─────────────────
$harvested = $conn->query("
    SELECT
        COALESCE(SUM(size_pw), 0) AS pw,
        COALESCE(SUM(size_s),  0) AS s,
        COALESCE(SUM(size_m),  0) AS m,
        COALESCE(SUM(size_l),  0) AS l,
        COALESCE(SUM(size_xl), 0) AS xl,
        COALESCE(SUM(size_j),  0) AS j,
        COALESCE(SUM(total_eggs), 0) AS total
    FROM harvests
");
$h = $harvested->fetch_assoc();

$sold_q     = $conn->query("SELECT COALESCE(SUM(quantity_sold * 30), 0) AS total_sold FROM sales");
$total_sold = $sold_q ? (int)$sold_q->fetch_assoc()['total_sold'] : 0;

$today_q   = $conn->query("SELECT COALESCE(SUM(total_eggs),0) AS today FROM harvests WHERE DATE(date_logged)=CURDATE()");
$today     = (int)$today_q->fetch_assoc()['today'];
$week_q    = $conn->query("SELECT COALESCE(SUM(total_eggs),0) AS week FROM harvests WHERE date_logged >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$this_week = (int)$week_q->fetch_assoc()['week'];

$total_harvested = (int)$h['total'];
$current_stock   = max(0, $total_harvested - $total_sold);

// Prices
$prices   = [];
$prices_q = $conn->query("SELECT size_code, price_per_piece FROM egg_prices");
if ($prices_q) {
    while ($p = $prices_q->fetch_assoc()) {
        $prices[$p['size_code']] = (float)$p['price_per_piece'];
    }
}

$sizes = [
    'PW' => ['label' => 'Peewee',      'count' => (int)$h['pw'],  'color' => '#adb5bd'],
    'S'  => ['label' => 'Small',        'count' => (int)$h['s'],   'color' => '#74c0fc'],
    'M'  => ['label' => 'Medium',       'count' => (int)$h['m'],   'color' => '#51cf66'],
    'L'  => ['label' => 'Large',        'count' => (int)$h['l'],   'color' => '#fcc419'],
    'XL' => ['label' => 'Extra Large',  'count' => (int)$h['xl'],  'color' => '#ff922b'],
    'J'  => ['label' => 'Jumbo',        'count' => (int)$h['j'],   'color' => '#f03e3e'],
];

// ── PER-BATCH STOCK LEVELS (oldest first) ────────────────────
// Approximates sales per batch by date range — good for most farm setups.
$batch_stock = $conn->query("
    SELECT
        b.batch_id,
        b.breed,
        COALESCE(b.initial_count, b.quantity, 0)    AS bird_count,
        b.arrival_date,
        b.expected_replacement,
        b.status,
        COALESCE(SUM(h.total_eggs), 0)               AS eggs_harvested,
        FLOOR(COALESCE(SUM(h.total_eggs), 0) / 30)  AS trays_harvested,
        (
            SELECT COALESCE(SUM(s2.quantity_sold * 30), 0)
            FROM sales s2
            WHERE s2.date_sold >= COALESCE(b.arrival_date, '2000-01-01')
              AND (b.expected_replacement IS NULL OR s2.date_sold <= b.expected_replacement)
        ) AS eggs_sold_approx
    FROM batches b
    LEFT JOIN harvests h ON h.batch_id = b.batch_id
    WHERE b.status = 'Active'
    GROUP BY b.batch_id
    ORDER BY b.arrival_date ASC, b.batch_id ASC
");

$batches_list    = [];
$oldest_batch_id = null;

if ($batch_stock) {
    while ($row = $batch_stock->fetch_assoc()) {
        $remaining              = max(0, (int)$row['eggs_harvested'] - (int)$row['eggs_sold_approx']);
        $row['remaining_eggs']  = $remaining;
        $row['remaining_trays'] = (int)floor($remaining / 30);
        $batches_list[]         = $row;
    }
    // First batch (oldest arrival) that still has stock = the old stock
    foreach ($batches_list as $bl) {
        if ($bl['remaining_trays'] > 0) {
            $oldest_batch_id = $bl['batch_id'];
            break;
        }
    }
}

// ── ACTIVE SELL-FIRST NOTIFICATION ───────────────────────────
$active_notif_q = $conn->query("
    SELECT n.*, b.breed
    FROM notifications n
    JOIN batches b ON n.batch_id = b.batch_id
    WHERE n.status IN ('unread','read')
    ORDER BY n.created_at DESC
    LIMIT 1
");
$notif = ($active_notif_q && $active_notif_q->num_rows > 0) ? $active_notif_q->fetch_assoc() : null;
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="max-width:1060px; margin:2rem auto;">

    <!-- Page header -->
    <div class="page-header">
        <div>
            <h2>🥚 Real-time Egg Inventory</h2>
            <p>Live stock levels · Oldest batch detection · Sell-first alerts</p>
        </div>
        <span style="font-size:0.82rem; color:var(--text-muted); font-weight:600;">
            <?php echo date('M d, Y — g:i A'); ?>
        </span>
    </div>

    <?php echo $message; ?>

    <!-- ── ACTIVE SELL-FIRST ALERT ────────────────────────── -->
    <?php if ($notif): ?>
    <div style="background:var(--warning-bg); border:1px solid rgba(212,144,10,0.35);
                border-left:5px solid var(--warning); border-radius:var(--radius);
                padding:14px 20px; margin-bottom:1.5rem;
                display:flex; justify-content:space-between; align-items:flex-start;
                flex-wrap:wrap; gap:12px;">
        <div>
            <div style="font-size:0.68rem; font-weight:700; color:var(--warning);
                        text-transform:uppercase; letter-spacing:0.7px; margin-bottom:6px;">
                📢 Active Sell-First Alert — Staff can see this
            </div>
            <div style="font-weight:700; font-size:0.92rem; color:var(--text-primary); margin-bottom:4px;">
                Batch #<?php echo $notif['batch_id']; ?> — <?php echo htmlspecialchars($notif['breed']); ?>
            </div>
            <div style="font-size:0.83rem; color:var(--text-secondary); margin-bottom:6px;">
                <?php echo htmlspecialchars($notif['message']); ?>
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted);">
                Sent <?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?>
                &nbsp;·&nbsp;
                <span class="badge badge-<?php echo $notif['status'] === 'unread' ? 'warning' : 'pending'; ?>">
                    <?php echo $notif['status'] === 'unread' ? 'Not yet seen' : 'Seen by staff'; ?>
                </span>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:0.68rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.6px;">Target</div>
            <div style="font-size:1.6rem; font-weight:800; font-family:'Playfair Display',serif; color:var(--gold);">
                <?php echo number_format($notif['target_trays']); ?>
            </div>
            <div style="font-size:0.72rem; color:var(--text-muted);">trays to prioritize</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── STAT CARDS ─────────────────────────────────────── -->
    <div style="display:flex; gap:16px; margin-bottom:2rem; flex-wrap:wrap;">
        <div class="stat-card" style="border-top:3px solid var(--gold);">
            <div class="stat-label">Today's Harvest</div>
            <div class="stat-value"><?php echo number_format($today); ?></div>
            <div class="stat-sub">eggs collected today</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--terra-lt);">
            <div class="stat-label">This Week</div>
            <div class="stat-value"><?php echo number_format($this_week); ?></div>
            <div class="stat-sub">last 7 days</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--success);">
            <div class="stat-label">Est. Stock on Hand</div>
            <div class="stat-value"><?php echo number_format($current_stock); ?></div>
            <div class="stat-sub">eggs (all batches)</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--danger);">
            <div class="stat-label">Total Sold</div>
            <div class="stat-value"><?php echo number_format($total_sold); ?></div>
            <div class="stat-sub">eggs all-time</div>
        </div>
    </div>

    <!-- ── PER-BATCH STOCK TABLE ──────────────────────────── -->
    <div class="card" style="padding:0; overflow:hidden; margin-bottom:24px;">
        <div style="padding:1.2rem 1.6rem; border-bottom:1px solid var(--border-subtle);
                    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div>
                <h3 style="margin:0; font-size:0.95rem;">📦 Stock by Batch — Oldest First</h3>
                <p style="font-size:0.78rem; color:var(--text-muted); margin-top:3px;">
                    The oldest batch with remaining stock is flagged as
                    <span class="badge badge-critical" style="font-size:0.58rem; vertical-align:middle;">OLD STOCK</span>
                    — click <em>Sell First</em> to alert staff.
                </p>
            </div>
        </div>

        <?php if (!empty($batches_list)): ?>
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Breed</th>
                        <th>Arrived</th>
                        <th>Harvested</th>
                        <th>Sold (est.)</th>
                        <th>Remaining</th>
                        <th>Age</th>
                        <th style="text-align:center; min-width:120px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches_list as $bl):
                        $is_old   = ($bl['batch_id'] === $oldest_batch_id && $bl['remaining_trays'] > 0);
                        $days_old = $bl['arrival_date']
                                    ? (int) floor((time() - strtotime($bl['arrival_date'])) / 86400)
                                    : null;
                        $age_color = $days_old === null ? 'var(--text-muted)'
                                   : ($days_old > 30 ? 'var(--danger)' : ($days_old > 14 ? 'var(--warning)' : 'var(--success)'));
                    ?>
                    <tr style="<?php echo $is_old ? 'background:rgba(194,58,58,0.07);' : ''; ?>">
                        <td>
                            <strong style="color:var(--text-primary);">#<?php echo $bl['batch_id']; ?></strong>
                            <?php if ($is_old): ?>
                                <br><span class="badge badge-critical" style="font-size:0.58rem; margin-top:4px; display:inline-block;">
                                    🔴 OLD STOCK
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-secondary);">
                            <?php echo htmlspecialchars($bl['breed']); ?>
                        </td>
                        <td style="font-size:0.8rem; color:var(--text-muted);">
                            <?php echo $bl['arrival_date'] ? date('M d, Y', strtotime($bl['arrival_date'])) : '—'; ?>
                        </td>
                        <td>
                            <strong style="color:var(--text-primary);">
                                <?php echo number_format($bl['trays_harvested']); ?>
                            </strong>
                            <span style="color:var(--text-muted); font-size:0.8rem;"> trays</span><br>
                            <span style="font-size:0.72rem; color:var(--text-muted);">
                                <?php echo number_format($bl['eggs_harvested']); ?> eggs
                            </span>
                        </td>
                        <td style="color:var(--text-muted); font-size:0.85rem;">
                            <?php echo number_format(floor($bl['eggs_sold_approx'] / 30)); ?> trays
                        </td>
                        <td>
                            <?php if ($bl['remaining_trays'] > 0): ?>
                                <strong style="color:var(--gold); font-size:1rem;">
                                    <?php echo number_format($bl['remaining_trays']); ?>
                                </strong>
                                <span style="color:var(--text-muted); font-size:0.8rem;"> trays</span><br>
                                <span style="font-size:0.72rem; color:var(--text-muted);">
                                    <?php echo number_format($bl['remaining_eggs']); ?> eggs
                                </span>
                            <?php else: ?>
                                <span class="badge badge-approved">Sold Out</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($days_old !== null): ?>
                                <span style="color:<?php echo $age_color; ?>; font-weight:700; font-size:0.88rem;">
                                    <?php echo $days_old; ?> days
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($is_old && $bl['remaining_trays'] > 0): ?>
                                <button class="btn-farm btn-danger btn-sm"
                                        onclick="openNotifModal(
                                            <?php echo $bl['batch_id']; ?>,
                                            '<?php echo addslashes(htmlspecialchars($bl['breed'], ENT_QUOTES)); ?>',
                                            <?php echo $bl['remaining_trays']; ?>
                                        )"
                                        style="font-size:0.75rem; padding:6px 10px; white-space:nowrap;">
                                    📢 Sell First
                                </button>
                            <?php elseif ($bl['remaining_trays'] > 0): ?>
                                <span style="font-size:0.75rem; color:var(--text-muted);">In stock</span>
                            <?php else: ?>
                                <span class="badge badge-approved" style="font-size:0.6rem;">Cleared</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:2.5rem;">
            <span class="empty-icon">🐔</span>
            <p>No active batches found.</p>
            <small>Add batches in <a href="batches.php" style="color:var(--gold);">Flock Batches</a> first.</small>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── SIZE BREAKDOWN + DOUGHNUT CHART ────────────────── -->
    <div style="display:grid; grid-template-columns:3fr 2fr; gap:24px; align-items:start;">

        <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding:1.2rem 1.6rem; border-bottom:1px solid var(--border-subtle);">
                <h3 style="margin:0; font-size:0.92rem;">📊 Overall Stock by Egg Size</h3>
            </div>
            <div class="table-wrapper" style="border:none; border-radius:0;">
                <table class="table-farm">
                    <thead>
                        <tr>
                            <th>Size</th><th>Count</th><th>Trays</th>
                            <th>Est. Value</th><th>Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sizes as $code => $info):
                            $trays    = $info['count'] > 0 ? floor($info['count'] / 30) : 0;
                            $price_pc = $prices[$code] ?? 0;
                            $value    = $info['count'] * $price_pc;
                            $share    = $total_harvested > 0
                                        ? round(($info['count'] / $total_harvested) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <span style="display:inline-block; width:11px; height:11px; border-radius:50%;
                                             background:<?php echo $info['color']; ?>;
                                             box-shadow:0 0 6px <?php echo $info['color']; ?>88;
                                             margin-right:9px; vertical-align:middle;"></span>
                                <strong style="color:var(--text-primary);"><?php echo $info['label']; ?></strong>
                                <small style="color:var(--text-muted); margin-left:4px;">(<?php echo $code; ?>)</small>
                            </td>
                            <td style="font-weight:700; color:var(--text-primary);"><?php echo number_format($info['count']); ?></td>
                            <td style="color:var(--text-secondary);"><?php echo number_format($trays); ?></td>
                            <td style="color:var(--success);">
                                <?php echo $price_pc > 0
                                    ? '₱'.number_format($value, 2)
                                    : '<span style="color:var(--text-muted)">N/A</span>'; ?>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="flex:1; background:var(--bg-plank); border-radius:4px; height:7px;">
                                        <div style="width:<?php echo $share; ?>%; background:<?php echo $info['color']; ?>; height:7px; border-radius:4px;"></div>
                                    </div>
                                    <span style="font-size:0.78rem; color:var(--text-muted); min-width:36px;"><?php echo $share; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>TOTAL</td>
                            <td><?php echo number_format($total_harvested); ?></td>
                            <td><?php echo number_format(floor($total_harvested / 30)); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card" style="text-align:center; padding:1.4rem 1.6rem;">
            <h3 style="margin-bottom:1rem; font-size:0.92rem;">Harvest Distribution</h3>
            <canvas id="stockChart" style="max-height:260px;"></canvas>
        </div>
    </div>

    <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<!-- ── SELL-FIRST MODAL ───────────────────────────────────── -->
<div id="notif-overlay" onclick="closeNotifModal()"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.72); z-index:999;">
</div>

<div id="notif-modal"
     style="display:none; position:fixed; top:50%; left:50%;
            transform:translate(-50%,-50%); z-index:1000;
            width:min(520px,94vw);
            background:var(--bg-soil); border:1px solid var(--border-mid);
            border-top:4px solid var(--danger); border-radius:var(--radius-lg);
            padding:1.6rem 1.8rem; box-shadow:var(--shadow-raised);">

    <h3 style="color:var(--gold); font-family:'Playfair Display',serif; margin-bottom:0.4rem;">
        📢 Notify Staff: Sell First
    </h3>
    <p style="font-size:0.84rem; color:var(--text-muted); margin-bottom:1.4rem; line-height:1.6;">
        This sends a priority alert to all staff on their dashboard. They cannot delete it until they complete the target.
    </p>

    <form method="POST">
        <input type="hidden" name="action"   value="notify_sell_first">
        <input type="hidden" name="batch_id" id="modal-batch-id" value="">
        <input type="hidden" name="breed"    id="modal-breed"    value="">

        <!-- Selected batch summary -->
        <div style="background:var(--bg-wood); border-radius:var(--radius);
                    padding:12px 16px; border-left:4px solid var(--danger); margin-bottom:1.2rem;">
            <div style="font-size:0.68rem; font-weight:700; color:var(--text-muted);
                        text-transform:uppercase; letter-spacing:0.6px; margin-bottom:4px;">
                Selected Batch
            </div>
            <div id="modal-batch-label"
                 style="font-size:1rem; font-weight:700; color:var(--text-primary);">—</div>
            <div id="modal-stock-label"
                 style="font-size:0.8rem; color:var(--text-muted); margin-top:3px;">—</div>
        </div>

        <div class="form-group">
            <label>Target — How many trays should staff sell first?</label>
            <input type="number" name="target_trays" id="modal-target"
                   class="form-input" min="1" required placeholder="e.g., 20">
            <small style="color:var(--text-muted); font-size:0.75rem; display:block; margin-top:5px;">
                Staff will see this number as their priority sell target. You can track progress in the dashboard.
            </small>
        </div>

        <div class="form-group">
            <label>Custom Message
                <span style="font-weight:400; color:var(--text-muted);">(optional — leave blank for default)</span>
            </label>
            <textarea name="custom_msg" class="form-input" rows="2"
                      placeholder="e.g., Please sell these eggs before the new batch arrives Friday."></textarea>
        </div>

        <div style="display:flex; gap:10px; margin-top:0.5rem;">
            <button type="submit" class="btn-farm btn-danger" style="flex:1; padding:13px;">
                📢 Send to All Staff
            </button>
            <button type="button" class="btn-farm btn-dark" onclick="closeNotifModal()"
                    style="padding:13px; min-width:90px;">
                Cancel
            </button>
        </div>
    </form>
</div>

<script>
// Doughnut chart
new Chart(document.getElementById('stockChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(fn($s) => $s['label'], $sizes)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map(fn($s) => $s['count'], $sizes)); ?>,
            backgroundColor: <?php echo json_encode(array_map(fn($s) => $s['color'], $sizes)); ?>,
            borderWidth: 3,
            borderColor: '#231E18'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position:'bottom', labels:{ color:'#B8A88A', font:{size:12}, padding:16 } },
            tooltip: { backgroundColor:'#2E2720', titleColor:'#F2EAD8', bodyColor:'#B8A88A' }
        },
        cutout: '60%'
    }
});

// Modal helpers
function openNotifModal(batchId, breed, trays) {
    document.getElementById('modal-batch-id').value    = batchId;
    document.getElementById('modal-breed').value       = breed;
    document.getElementById('modal-batch-label').textContent =
        'Batch #' + batchId + ' — ' + breed;
    document.getElementById('modal-stock-label').textContent =
        trays + ' tray(s) remaining — flagged as old stock';
    document.getElementById('modal-target').value = trays;
    document.getElementById('notif-modal').style.display   = 'flex';
    document.getElementById('notif-overlay').style.display = 'block';
    document.getElementById('notif-modal').style.flexDirection = 'column';
    // Reset to block for the form layout
    document.getElementById('notif-modal').style.display = 'block';
}
function closeNotifModal() {
    document.getElementById('notif-modal').style.display   = 'none';
    document.getElementById('notif-overlay').style.display = 'none';
}
</script>

<?php include('../../includes/footer.php'); ?>