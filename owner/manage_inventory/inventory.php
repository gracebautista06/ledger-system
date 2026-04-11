<?php
/* ============================================================
   owner/inventory.php — Real-time Egg Inventory
   
   HOW STOCK IS CALCULATED:
   - Harvested per size  = SUM(size_pw/s/m/l/xl/j) from harvests
   - Sold per size       = proportional from total quantity_sold
     (If sales table gets per-size columns later, swap to exact)
   - Remaining per size  = harvested - sold (per size)
   - Remaining trays     = floor(remaining / 30) per size
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

// ── HANDLE: Send Sell-First notification ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'notify_sell_first') {
    $batch_id     = intval($_POST['batch_id']);
    $target_trays = max(1, intval($_POST['target_trays']));
    $sizes_raw    = trim($_POST['sizes_to_sell'] ?? '');
    $breed_raw    = trim($_POST['breed'] ?? 'this batch');
    $breed_esc    = $conn->real_escape_string($breed_raw);
    $sizes_esc    = $conn->real_escape_string($sizes_raw);

    $custom_raw  = trim($_POST['custom_msg'] ?? '');
    $default_msg = "Priority sell: Batch #{$batch_id} ({$breed_raw}) — oldest stock on hand. "
                 . "Target: {$target_trays} tray(s)."
                 . ($sizes_raw ? " Sizes: {$sizes_raw}." : "");
    $msg_esc = $conn->real_escape_string($custom_raw ?: $default_msg);

    $conn->query("UPDATE notifications SET status='completed'
                  WHERE batch_id=$batch_id AND status IN ('unread','read')");
    $conn->query("INSERT INTO notifications
                    (sender_role, batch_id, target_trays, sizes_to_sell, message, status)
                  VALUES ('Owner', $batch_id, $target_trays, '$sizes_esc', '$msg_esc', 'unread')");

    $message = "<div class='alert success'>✅ Sell-first alert sent to all staff.</div>";
}

// ── TOTALS HARVESTED PER SIZE ────────────────────────────────────
$h_q = $conn->query("
    SELECT
        COALESCE(SUM(size_pw),0)    AS pw,
        COALESCE(SUM(size_s),0)     AS s,
        COALESCE(SUM(size_m),0)     AS m,
        COALESCE(SUM(size_l),0)     AS l,
        COALESCE(SUM(size_xl),0)    AS xl,
        COALESCE(SUM(size_j),0)     AS j,
        COALESCE(SUM(total_eggs),0) AS total
    FROM harvests
");
$h = $h_q->fetch_assoc();
$total_harvested = (int)$h['total'];

// ── TOTAL SOLD (eggs) ────────────────────────────────────────────
$s_q = $conn->query("
    SELECT
        COALESCE(SUM(qty_pw*30),0) AS pw,
        COALESCE(SUM(qty_s*30),0)  AS s,
        COALESCE(SUM(qty_m*30),0)  AS m,
        COALESCE(SUM(qty_l*30),0)  AS l,
        COALESCE(SUM(qty_xl*30),0) AS xl,
        COALESCE(SUM(qty_j*30),0)  AS j
    FROM sales
");

$sold_sizes = $s_q->fetch_assoc();

// total sold (for summary only)
$total_sold = array_sum($sold_sizes);

// ── DISTRIBUTE SOLD PROPORTIONALLY BY SIZE ───────────────────────
$sizes_meta = [
    'pw' => ['label'=>'Peewee',      'code'=>'PW', 'color'=>'#adb5bd'],
    's'  => ['label'=>'Small',       'code'=>'S',  'color'=>'#74c0fc'],
    'm'  => ['label'=>'Medium',      'code'=>'M',  'color'=>'#51cf66'],
    'l'  => ['label'=>'Large',       'code'=>'L',  'color'=>'#fcc419'],
    'xl' => ['label'=>'Extra Large', 'code'=>'XL', 'color'=>'#ff922b'],
    'j'  => ['label'=>'Jumbo',       'code'=>'J',  'color'=>'#f03e3e'],
];

$stock = [];
$total_remaining = 0;

foreach ($sizes_meta as $key => $meta) {
    $harv      = (int)$h[$key];
    $sold_sz = (int)$sold_sizes[$key];
    $remaining = max(0, $harv - $sold_sz);
    $total_remaining += $remaining;
    $stock[$key] = [
        'label'      => $meta['label'],
        'code'       => $meta['code'],
        'color'      => $meta['color'],
        'harvested'  => $harv,
        'sold'       => $sold_sz,
        'remaining'  => $remaining,
        'trays_harv' => (int)floor($harv / 30),
        'trays_rem'  => (int)floor($remaining / 30),
    ];
}

// ── PRICES — now breed-specific from breed_prices table ──────────
// $prices[$breed][$size_code] = ['price_per_tray'=>..., 'price_per_piece'=>...]
$prices = [];
$pq = $conn->query("SELECT breed, size_code, price_per_tray, price_per_piece FROM breed_prices");
if ($pq) {
    while ($p = $pq->fetch_assoc()) {
        $prices[$p['breed']][$p['size_code']] = $p;
    }
}

// Flat prices (average per size across breeds) for the size breakdown table
$prices_flat = [];
$pfq = $conn->query("SELECT size_code, AVG(price_per_tray) AS price_per_tray, AVG(price_per_piece) AS price_per_piece FROM breed_prices GROUP BY size_code");
if ($pfq) {
    while ($pf = $pfq->fetch_assoc()) $prices_flat[$pf['size_code']] = $pf;
}

// ── TODAY / WEEK ──────────────────────────────────────────────────
$today_q   = $conn->query("SELECT COALESCE(SUM(total_eggs),0) AS v FROM harvests WHERE DATE(date_logged)=CURDATE()");
$today     = (int)$today_q->fetch_assoc()['v'];
$week_q    = $conn->query("SELECT COALESCE(SUM(total_eggs),0) AS v FROM harvests WHERE date_logged>=DATE_SUB(NOW(),INTERVAL 7 DAY)");
$this_week = (int)$week_q->fetch_assoc()['v'];

// ── PER-BATCH DATA ────────────────────────────────────────────────
$batch_q = $conn->query("
    SELECT
        b.batch_id, b.breed, b.arrival_date, b.status,
        COALESCE(SUM(h.total_eggs),0)  AS eggs_harvested,
        COALESCE(SUM(h.size_pw),0)     AS bpw,
        COALESCE(SUM(h.size_s),0)      AS bs,
        COALESCE(SUM(h.size_m),0)      AS bm,
        COALESCE(SUM(h.size_l),0)      AS bl,
        COALESCE(SUM(h.size_xl),0)     AS bxl,
        COALESCE(SUM(h.size_j),0)      AS bj,
        (SELECT u.username FROM harvests h2
         JOIN users u ON h2.staff_id=u.user_id
         WHERE h2.batch_id=b.batch_id
         ORDER BY h2.date_logged DESC LIMIT 1) AS last_harvester,
        (SELECT h2.date_logged FROM harvests h2
         WHERE h2.batch_id=b.batch_id
         ORDER BY h2.date_logged DESC LIMIT 1) AS last_harvest_date
    FROM batches b
    LEFT JOIN harvests h ON h.batch_id=b.batch_id
    WHERE b.status='Active'
    GROUP BY b.batch_id
    ORDER BY b.arrival_date ASC, b.batch_id ASC
");

$batches_list    = [];
$oldest_batch_id = null;

if ($batch_q) {
    while ($row = $batch_q->fetch_assoc()) {
        $arrival    = $row['arrival_date'] ?? '2000-01-01';
        // Approximate: sales after this batch's arrival date belong to this batch
        $sold_q2    = $conn->query("SELECT COALESCE(SUM(quantity_sold*30),0) AS sold FROM sales WHERE DATE(date_sold)>='$arrival'");
        $batch_sold = $sold_q2 ? (int)$sold_q2->fetch_assoc()['sold'] : 0;

        $harv_total  = (int)$row['eggs_harvested'];
        $remaining_eggs = max(0, $harv_total - $batch_sold);

        // Per-size remaining for this batch
        $size_rem = [];
        $sz_keys  = ['pw'=>'bpw','s'=>'bs','m'=>'bm','l'=>'bl','xl'=>'bxl','j'=>'bj'];
        foreach ($sz_keys as $sz => $col) {
            $h_sz   = (int)$row[$col];
            $s_sz   = ($harv_total > 0 && $batch_sold > 0)
                      ? (int)round(($h_sz / $harv_total) * $batch_sold) : 0;
            $r_sz   = max(0, $h_sz - $s_sz);
            $code   = $sizes_meta[$sz]['code'];
            $size_rem[$sz] = ['eggs'=>$r_sz, 'trays'=>(int)floor($r_sz/30), 'code'=>$code];
        }

        $row['remaining_eggs']  = $remaining_eggs;
        $row['remaining_trays'] = (int)floor($remaining_eggs / 30);
        $row['size_rem']        = $size_rem;
        $batches_list[] = $row;
    }
    foreach ($batches_list as $bl) {
        if ($bl['remaining_trays'] > 0) { $oldest_batch_id = $bl['batch_id']; break; }
    }
}

// ── OLD STOCK BATCHES (>7 days, still have stock) ────────────────
$old_stock_batches = array_filter($batches_list, function($bl) {
    $days = $bl['arrival_date']
            ? (int)floor((time()-strtotime($bl['arrival_date']))/86400) : 0;
        return $bl['remaining_trays'] > 0 && $days > 7;
});


//  ___AUTO NOTIFICATION
foreach ($old_stock_batches as $bl) {
    $batch_id = $bl['batch_id'];

    $check = $conn->query("SELECT 1 FROM notifications 
                           WHERE batch_id=$batch_id AND status IN ('unread','read')
                           LIMIT 1");

    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO notifications
            (sender_role, batch_id, target_trays, message, status)
            VALUES (
                'Owner',
                $batch_id,
                {$bl['remaining_trays']},
                'Auto: Old stock needs priority selling.',
                'unread'
            )");
    }
}

// ── ACTIVE NOTIFICATION ───────────────────────────────────────────
$notif_q = $conn->query("
    SELECT n.*, b.breed FROM notifications n
    JOIN batches b ON n.batch_id=b.batch_id
    WHERE n.status IN ('unread','read')
    ORDER BY n.created_at DESC LIMIT 1
");
$notif = ($notif_q && $notif_q->num_rows > 0) ? $notif_q->fetch_assoc() : null;

// Get live remaining for notified batch
$notif_remaining = 0;
$notif_sizes_left = '';
if ($notif) {
    foreach ($batches_list as $bl) {
        if ($bl['batch_id'] == $notif['batch_id']) {
            $notif_remaining = $bl['remaining_trays'];
            $sz_parts = [];
            foreach ($bl['size_rem'] as $sz => $info) {
                if ($info['trays'] > 0) $sz_parts[] = $info['code'].': '.$info['trays'].'T';
            }
            $notif_sizes_left = implode(', ', $sz_parts);
            break;
        }
    }
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="max-width:1080px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>🥚 Real-time Egg Inventory</h2>
            <p>Current stock on hand · Per-size tray counts · Sell-first alerts</p>
        </div>
        <span style="font-size:0.82rem; color:var(--text-muted);"><?php echo date('M d, Y — g:i A'); ?></span>
    </div>

    <?php echo $message; ?>

    <!-- ── ACTIVE SELL-FIRST ALERT BANNER ──────────────────────── -->
    <?php if ($notif): ?>
    <div style="background:var(--warning-bg); border:1px solid rgba(212,144,10,0.35);
                border-left:5px solid var(--warning); border-radius:var(--radius);
                padding:16px 20px; margin-bottom:1.5rem;
                display:flex; justify-content:space-between; align-items:flex-start;
                flex-wrap:wrap; gap:14px;">
        <div style="flex:1;">
            <div style="font-size:0.68rem; font-weight:700; color:var(--warning);
                        text-transform:uppercase; letter-spacing:0.7px; margin-bottom:6px;">
                📢 Active Sell-First Alert — Visible to all staff
            </div>
            <div style="font-weight:700; font-size:0.95rem; color:var(--text-primary); margin-bottom:4px;">
                Batch #<?php echo $notif['batch_id']; ?> — <?php echo htmlspecialchars($notif['breed']); ?>
            </div>
            <?php if (!empty($notif['sizes_to_sell'])): ?>
            <div style="font-size:0.82rem; color:var(--gold); margin-bottom:4px;">
                Sizes to sell: <strong><?php echo htmlspecialchars($notif['sizes_to_sell']); ?></strong>
            </div>
            <?php endif; ?>
            <div style="font-size:0.8rem; color:var(--text-secondary);">
                <?php echo htmlspecialchars($notif['message']); ?>
            </div>
            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:6px;">
                Sent <?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?>
                &nbsp;·&nbsp;
                <span class="badge badge-<?php echo $notif['status']==='unread'?'warning':'pending'; ?>">
                    <?php echo $notif['status']==='unread'?'Not yet seen by staff':'Seen by staff'; ?>
                </span>
            </div>
        </div>
        <div style="text-align:right; flex-shrink:0; min-width:110px;">
            <div style="font-size:0.65rem; font-weight:700; color:var(--text-muted);
                        text-transform:uppercase; letter-spacing:0.6px;">Target</div>
            <div style="font-size:2.2rem; font-weight:800; font-family:'Playfair Display',serif;
                        color:var(--gold); line-height:1.1;">
                <?php echo number_format($notif['target_trays']); ?>
            </div>
            <div style="font-size:0.7rem; color:var(--text-muted);">trays to sell</div>
            <div style="margin-top:8px; font-size:0.72rem; font-weight:700; color:var(--terra-lt);">
                <?php echo number_format($notif_remaining); ?> trays still on hand
            </div>
            <?php if ($notif_sizes_left): ?>
            <div style="font-size:0.68rem; color:var(--text-muted); margin-top:2px;">
                <?php echo $notif_sizes_left; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── STAT CARDS ───────────────────────────────────────────── -->
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
            <div class="stat-label">Total On Hand</div>
            <div class="stat-value"><?php echo number_format($total_remaining); ?></div>
            <div class="stat-sub"><?php echo number_format(floor($total_remaining/30)); ?> trays remaining</div>
        </div>
        <div class="stat-card" style="border-top:3px solid var(--danger);">
            <div class="stat-label">Total Sold</div>
            <div class="stat-value"><?php echo number_format($total_sold); ?></div>
            <div class="stat-sub"><?php echo number_format(floor($total_sold/30)); ?> trays sold</div>
        </div>
    </div>

    <!-- ── NEW: HARVEST DISTRIBUTION TABLE ───────────────────── -->
<div class="card" style="padding:0; overflow:hidden; margin-bottom:24px;">
    <div style="padding:1.2rem 1.6rem; border-bottom:1px solid var(--border-subtle);">
        <h3 style="margin:0;">🥚 Harvest Distribution — By Egg Size</h3>
        <p style="font-size:0.78rem; color:var(--text-muted); margin-top:4px;">
            Total eggs collected and their proportion per size.
        </p>
    </div>

    <div class="table-wrapper" style="border:none; border-radius:0;">
        <table class="table-farm">
            <thead>
                <tr>
                    <th>Egg Size</th>
                    <th style="text-align:center;">Harvested (eggs)</th>
                    <th style="text-align:center;">Harvested (trays)</th>
                    <th style="text-align:center;">Share of Harvest</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stock as $key => $info):
                    $share = $total_harvested > 0
                        ? round(($info['harvested'] / $total_harvested) * 100, 1)
                        : 0;
                ?>
                <tr>
                    <td>
                        <span style="display:inline-block; width:11px; height:11px;
                                     border-radius:50%;
                                     background:<?php echo $info['color']; ?>;
                                     margin-right:8px;"></span>
                        <strong><?php echo $info['label']; ?></strong>
                        <small style="color:var(--text-muted);">
                            (<?php echo $info['code']; ?>)
                        </small>
                    </td>

                    <td style="text-align:center; font-weight:600;">
                        <?php echo number_format($info['harvested']); ?>
                    </td>

                    <td style="text-align:center;">
                        <?php echo number_format($info['trays_harv']); ?> trays
                    </td>

                    <td style="min-width:140px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="flex:1; background:var(--bg-plank); height:7px; border-radius:4px;">
                                <div style="width:<?php echo $share; ?>%;
                                            background:<?php echo $info['color']; ?>;
                                            height:7px; border-radius:4px;"></div>
                            </div>
                            <span style="font-size:0.75rem; color:var(--text-muted);">
                                <?php echo $share; ?>%
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>

            <tfoot>
                <tr>
                    <td><strong>TOTAL</strong></td>
                    <td style="text-align:center; font-weight:800;">
                        <?php echo number_format($total_harvested); ?>
                    </td>
                    <td style="text-align:center;">
                        <?php echo number_format(floor($total_harvested / 30)); ?> trays
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

    <!-- ── TABLE 2: CURRENT STOCK BY EGG SIZE ───────────────────── -->
    <div class="card" style="padding:0; overflow:hidden; margin-bottom:24px;">
        <div style="padding:1.2rem 1.6rem; border-bottom:1px solid var(--border-subtle);">
            <h3 style="margin:0;">📦 Current Stock on Hand — By Egg Size</h3>
            <p style="font-size:0.78rem; color:var(--text-muted); margin-top:4px;">
                Remaining = Total Harvested − Estimated Sold. Trays = every 30 eggs.
            </p>
        </div>
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>Egg Size</th>
                        <th style="text-align: center;">Sold (eggs)</th>
                        <th style="text-align:center;">Remaining (eggs)</th>
                        <th style="text-align:center;">Remaining (trays)</th>
                        <th style="text-align:center;">Est. Value if Sold</th>
                        <th>Share of Stock</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stock as $key => $info):
                        $price_tray = isset($prices_flat[$info['code']]) ? (float)$prices_flat[$info['code']]['price_per_tray'] : 0;
                        $est_value  = $info['trays_rem'] * $price_tray;
                        $share      = $total_remaining > 0
                                      ? round(($info['remaining'] / $total_remaining) * 100, 1) : 0;
                    ?>
                    <tr style="<?php echo $info['remaining']===0 ? 'opacity:0.4;' : ''; ?>">
                        <td>
                            <span style="display:inline-block; width:11px; height:11px; border-radius:50%;
                                         background:<?php echo $info['color']; ?>;
                                         box-shadow:0 0 6px <?php echo $info['color']; ?>88;
                                         margin-right:8px; vertical-align:middle;"></span>
                            <strong style="color:var(--text-primary);"><?php echo $info['label']; ?></strong>
                            <small style="color:var(--text-muted); margin-left:4px;">(<?php echo $info['code']; ?>)</small>
                        </td>
                        <td style="text-align:center; color:var(--danger); font-size:0.85rem;">
                             −<?php echo number_format($info['sold']); ?>
                        </td>
                        <td style="text-align:center; font-weight:700; color:var(--text-primary);">
                            <?php echo number_format($info['remaining']); ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($info['trays_rem'] > 0): ?>
                                <strong style="color:var(--gold); font-size:1.1rem;">
                                    <?php echo number_format($info['trays_rem']); ?>
                                </strong>
                                <span style="font-size:0.75rem; color:var(--text-muted);"> trays</span>
                            <?php else: ?>
                                <span class="badge badge-approved" style="font-size:0.65rem;">Sold Out</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; color:var(--success); font-size:0.88rem;">
                            <?php echo ($price_tray > 0 && $info['trays_rem'] > 0)
                                ? '₱'.number_format($est_value, 2)
                                : '<span style="color:var(--text-muted)">—</span>'; ?>
                        </td>
                        <td style="min-width:130px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="flex:1; background:var(--bg-plank); border-radius:4px; height:7px;">
                                    <div style="width:<?php echo $share; ?>%;
                                                background:<?php echo $info['color']; ?>;
                                                height:7px; border-radius:4px;"></div>
                                </div>
                                <span style="font-size:0.75rem; color:var(--text-muted); min-width:36px;">
                                    <?php echo $share; ?>%
                                </span>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($info['trays_rem'] == 0): ?>
                                <span class="badge badge-approved">Out</span>
                            <?php elseif ($info['trays_rem'] < 10): ?>
                                <span class="badge badge-warning">Low</span>
                            <?php else: ?>
                                <span class="badge badge-healthy">Good</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>TOTAL</strong></td>
                        <td style="text-align:center; color:var(--danger);">
                            −<?php echo number_format($total_sold); ?>
                        </td>
                        <td style="text-align:center; font-weight:800; color:var(--gold);">
                            <?php echo number_format($total_remaining); ?>
                        </td>
                        <td style="text-align:center; font-weight:800; color:var(--gold);">
                            <?php echo number_format(floor($total_remaining/30)); ?> trays
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ── TABLE 3: OLD STOCK / SELL-FIRST ──────────────────────── -->
    <?php if (true): ?>
    <div class="card" style="padding:0; overflow:hidden; margin-bottom:24px;
                              border-left:4px solid var(--danger);">
        <div style="padding:1.2rem 1.6rem; border-bottom:1px solid var(--border-subtle);
                    background:rgba(194,58,58,0.05);">
            <h3 style="margin:0; color:var(--terra-lt);">🔴 Old Stock — Priority Sell</h3>
            <p style="font-size:0.78rem; color:var(--text-muted); margin-top:4px;">
                Batches in stock for <strong>more than 7 days</strong> with remaining trays.
                The <strong>Target</strong> in the notification = total remaining trays of this batch,
                broken down by size so staff know exactly what to sell.
            </p>
        </div>
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Breed</th>
                        <th>Days Old</th>
                        <th>Last Harvested By</th>
                        <!-- Per-size columns -->
                        <th style="text-align:center; border-left:1px solid var(--border-subtle);">PW</th>
                        <th style="text-align:center;">S</th>
                        <th style="text-align:center;">M</th>
                        <th style="text-align:center;">L</th>
                        <th style="text-align:center;">XL</th>
                        <th style="text-align:center; border-right:1px solid var(--border-subtle);">J</th>
                        <th style="text-align:center;">Total Trays</th>
                        <th style="text-align:center;">Est. Value</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                    <tr style="background:var(--bg-plank);">
                        <th colspan="4" style="font-size:0.65rem; color:var(--text-muted); padding:5px 16px;">
                            ← batch info
                        </th>
                        <th colspan="6" style="text-align:center; font-size:0.65rem; color:var(--text-muted);
                                               border-left:1px solid var(--border-subtle); padding:5px 8px;">
                            Remaining Trays by Size →
                        </th>
                        <th colspan="3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($old_stock_batches as $bl):
                        $days_old  = $bl['arrival_date']
                                     ? (int)floor((time()-strtotime($bl['arrival_date']))/86400) : '?';
                        $age_color = is_int($days_old) && $days_old > 30
                                     ? 'var(--danger)' : 'var(--warning)';
                        $est_val   = 0;
                        $sz_sell   = [];
                        foreach ($bl['size_rem'] as $sz => $info) {
                            $code  = $info['code'];
                            $breed_k = $bl['breed'];
                            $pt    = isset($prices[$breed_k][$code]) ? (float)$prices[$breed_k][$code]['price_per_tray']
                                   : (isset($prices_flat[$code]) ? (float)$prices_flat[$code]['price_per_tray'] : 0);
                            $est_val += $info['trays'] * $pt;
                            if ($info['trays'] > 0) $sz_sell[] = $code;
                        }
                    ?>
                    <tr style="background:rgba(194,58,58,0.04);">
                        <td>
                            <strong>#<?php echo $bl['batch_id']; ?></strong>
                            <?php if ($bl['batch_id']===$oldest_batch_id): ?>
                            <br><span class="badge badge-critical" style="font-size:0.55rem; margin-top:3px; display:inline-block;">OLDEST</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-secondary);"><?php echo htmlspecialchars($bl['breed']); ?></td>
                        <td><strong style="color:<?php echo $age_color; ?>;"><?php echo $days_old; ?> days</strong></td>
                        <td style="font-size:0.82rem;">
                            <?php if ($bl['last_harvester']): ?>
                                <strong><?php echo htmlspecialchars($bl['last_harvester']); ?></strong><br>
                                <span style="font-size:0.72rem; color:var(--text-muted);">
                                    <?php echo date('M d, Y', strtotime($bl['last_harvest_date'])); ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <?php $sz_order = ['pw','s','m','l','xl','j'];
                        foreach ($sz_order as $i => $sz):
                            $info = $bl['size_rem'][$sz];
                            $border = $i===0 ? 'border-left:1px solid var(--border-subtle);' : '';
                            $borderR = $i===5 ? 'border-right:1px solid var(--border-subtle);' : '';
                        ?>
                        <td style="text-align:center; <?php echo $border.$borderR; ?>">
                            <?php if ($info['trays'] > 0): ?>
                                <strong style="color:var(--gold);"><?php echo $info['trays']; ?></strong>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.78rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td style="text-align:center;">
                            <strong style="color:var(--terra-lt); font-size:1rem;">
                                <?php echo number_format($bl['remaining_trays']); ?>
                            </strong>
                            <span style="font-size:0.72rem; color:var(--text-muted);"> trays</span>
                        </td>
                        <td style="text-align:center; color:var(--success); font-size:0.88rem;">
                            <?php echo $est_val > 0 ? '₱'.number_format($est_val,2) : '<span style="color:var(--text-muted)">—</span>'; ?>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn-farm btn-danger btn-sm"
                                    onclick="openNotifModal(
                                        <?php echo $bl['batch_id']; ?>,
                                        '<?php echo addslashes(htmlspecialchars($bl['breed'],ENT_QUOTES)); ?>',
                                        <?php echo $bl['remaining_trays']; ?>,
                                        '<?php echo implode(', ',$sz_sell); ?>'
                                    )"
                                    style="font-size:0.75rem; padding:6px 10px; white-space:nowrap;">
                                📢 Sell First
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── TABLE 4: ALL ACTIVE BATCHES OVERVIEW ─────────────────── -->
    <div class="card" style="padding:0; overflow:hidden; margin-bottom:24px;">
        <div style="padding:1.2rem 1.6rem; border-bottom:1px solid var(--border-subtle);">
            <h3 style="margin:0; font-size:0.95rem;">📋 All Active Batches — Overview</h3>
            <p style="font-size:0.78rem; color:var(--text-muted); margin-top:3px;">Sorted oldest first.</p>
        </div>
        <?php if (!empty($batches_list)): ?>
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Breed</th>
                        <th>Arrived</th>
                        <th>Last Harvested By</th>
                        <th style="text-align:center;">Harvested Trays</th>
                        <th style="text-align:center;">Remaining Trays</th>
                        <th style="text-align:center;">Age</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches_list as $bl):
                        $is_old   = ($bl['batch_id']===$oldest_batch_id && $bl['remaining_trays']>0);
                        $days_old = $bl['arrival_date']
                                    ? (int)floor((time()-strtotime($bl['arrival_date']))/86400) : null;
                        $age_col  = $days_old===null ? 'var(--text-muted)'
                                  : ($days_old>30?'var(--danger)':($days_old>14?'var(--warning)':'var(--success)'));
                    ?>
                    <tr style="<?php echo $is_old?'background:rgba(194,58,58,0.05);':''; ?>">
                        <td>
                            <strong>#<?php echo $bl['batch_id']; ?></strong>
                            <?php if ($is_old): ?>
                            <br><span class="badge badge-critical" style="font-size:0.55rem; margin-top:3px; display:inline-block;">🔴 OLD STOCK</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-secondary);"><?php echo htmlspecialchars($bl['breed']); ?></td>
                        <td style="font-size:0.8rem; color:var(--text-muted);">
                            <?php echo $bl['arrival_date']?date('M d, Y',strtotime($bl['arrival_date'])):'—'; ?>
                        </td>
                        <td style="font-size:0.82rem;">
                            <?php if ($bl['last_harvester']): ?>
                                <strong><?php echo htmlspecialchars($bl['last_harvester']); ?></strong>
                                <span style="color:var(--text-muted); font-size:0.72rem; margin-left:4px;">
                                    <?php echo date('M d', strtotime($bl['last_harvest_date'])); ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">No harvests yet</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; color:var(--text-muted);">
                            <?php echo number_format((int)floor($bl['eggs_harvested']/30)); ?> trays
                        </td>
                        <td style="text-align:center;">
                            <?php if ($bl['remaining_trays']>0): ?>
                                <strong style="color:var(--gold);"><?php echo number_format($bl['remaining_trays']); ?></strong>
                                <span style="font-size:0.72rem; color:var(--text-muted);"> trays</span>
                            <?php else: ?>
                                <span class="badge badge-approved">Sold Out</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($days_old!==null): ?>
                                <span style="color:<?php echo $age_col; ?>; font-weight:700; font-size:0.88rem;">
                                    <?php echo $days_old; ?> days
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
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
            <small>Add batches in <a href="../manage_batches.php" style="color:var(--gold);">Manage Batches</a> first.</small>
        </div>
        <?php endif; ?>
    </div>


    <!-- ── HARVEST DISTRIBUTION CHART ──────────────────────────── -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
        <div class="card" style="padding:1.4rem 1.6rem; text-align:center;">
            <h3 style="font-size:0.92rem; margin-bottom:1rem;">🍩 Harvest Distribution by Egg Size</h3>
            <p style="font-size:0.75rem; color:var(--text-muted); margin-bottom:1rem;">
                Shows what percentage of total harvested eggs belong to each size.
                Useful to understand your flock's natural size output.
            </p>
            <canvas id="harvestDist" style="max-height:240px;"></canvas>
        </div>
        <div class="card" style="padding:1.4rem 1.6rem; text-align:center;">
            <h3 style="font-size:0.92rem; margin-bottom:1rem;">📊 Stock Remaining by Egg Size</h3>
            <p style="font-size:0.75rem; color:var(--text-muted); margin-bottom:1rem;">
                Shows the share of remaining (unsold) stock per size.
                Helps identify which sizes need to be sold faster.
            </p>
            <canvas id="stockDist" style="max-height:240px;"></canvas>
        </div>
    </div>

    <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<!-- ── SELL-FIRST MODAL ──────────────────────────────────────── -->
<div id="notif-overlay" onclick="closeNotifModal()"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:999;"></div>

<div id="notif-modal"
     style="display:none; position:fixed; top:50%; left:50%;
            transform:translate(-50%,-50%); z-index:1000;
            width:min(540px,95vw);
            background:var(--bg-soil); border:1px solid var(--border-mid);
            border-top:4px solid var(--danger); border-radius:var(--radius-lg);
            padding:1.8rem; box-shadow:var(--shadow-raised);">
    <h3 style="color:var(--gold); font-family:'Playfair Display',serif; margin-bottom:0.3rem;">
        📢 Notify Staff: Sell First
    </h3>
    <p style="font-size:0.83rem; color:var(--text-muted); margin-bottom:1.4rem; line-height:1.6;">
        Sends a priority alert to all staff. Target = total remaining old-stock trays.
    </p>
    <form method="POST">
        <input type="hidden" name="action"        value="notify_sell_first">
        <input type="hidden" name="batch_id"      id="modal-batch-id"    value="">
        <input type="hidden" name="breed"         id="modal-breed"       value="">
        <input type="hidden" name="sizes_to_sell" id="modal-sizes-input" value="">

        <div style="background:var(--bg-wood); border-radius:var(--radius);
                    padding:13px 16px; border-left:4px solid var(--danger); margin-bottom:1.2rem;">
            <div style="font-size:0.65rem; font-weight:700; color:var(--text-muted);
                        text-transform:uppercase; letter-spacing:0.6px; margin-bottom:4px;">Batch</div>
            <div id="modal-batch-label" style="font-size:1rem; font-weight:700; color:var(--text-primary);">—</div>
            <div id="modal-stock-label" style="font-size:0.8rem; color:var(--text-muted); margin-top:3px;">—</div>
            <div id="modal-sizes-label" style="font-size:0.82rem; color:var(--gold); margin-top:4px;"></div>
        </div>

        <div class="form-group">
            <label>Target — Total trays to sell first</label>
            <input type="number" name="target_trays" id="modal-target"
                   class="form-input" min="1" required placeholder="e.g., 20">
            <small style="color:var(--text-muted); font-size:0.75rem; display:block; margin-top:4px;">
                Pre-filled with total remaining old-stock trays. Staff will see this number as their sell target.
            </small>
        </div>
        <div class="form-group">
            <label>Custom Message <span style="font-weight:400; color:var(--text-muted);">(optional)</span></label>
            <textarea name="custom_msg" class="form-input" rows="2"
                      placeholder="Leave blank to use auto-generated message."></textarea>
        </div>
        <div style="display:flex; gap:10px; margin-top:0.5rem;">
            <button type="submit" class="btn-farm btn-danger" style="flex:1; padding:13px;">
                📢 Send to All Staff
            </button>
            <button type="button" class="btn-farm btn-dark" onclick="closeNotifModal()"
                    style="padding:13px; min-width:90px;">Cancel</button>
        </div>
    </form>
</div>

<script>
function openNotifModal(batchId, breed, trays, sizes) {
    document.getElementById('modal-batch-id').value    = batchId;
    document.getElementById('modal-breed').value       = breed;
    document.getElementById('modal-target').value      = trays;
    document.getElementById('modal-sizes-input').value = sizes || '';
    document.getElementById('modal-batch-label').textContent = 'Batch #' + batchId + ' — ' + breed;
    document.getElementById('modal-stock-label').textContent = trays + ' tray(s) remaining on this batch';
    document.getElementById('modal-sizes-label').textContent = sizes ? 'Sizes with stock: ' + sizes : '';
    document.getElementById('notif-modal').style.display   = 'block';
    document.getElementById('notif-overlay').style.display = 'block';
}
function closeNotifModal() {
    document.getElementById('notif-modal').style.display   = 'none';
    document.getElementById('notif-overlay').style.display = 'none';
}

// ── HARVEST DISTRIBUTION CHARTS ──────────────────────────────
const chartColors = <?php echo json_encode(array_map(fn($s) => $s['color'], array_values($sizes_meta))); ?>;
const sizeLabels  = <?php echo json_encode(array_map(fn($s) => $s['label'], array_values($sizes_meta))); ?>;
const harvestData = <?php echo json_encode(array_map(fn($s) => $s['harvested'], array_values($stock))); ?>;
const remainData  = <?php echo json_encode(array_map(fn($s) => $s['remaining'], array_values($stock))); ?>;

const sharedOpts = {
    responsive: true,
    plugins: {
        legend: { position:'bottom', labels:{ color:'#B8A88A', font:{size:11}, padding:12 } },
        tooltip: { backgroundColor:'#2E2720', titleColor:'#F2EAD8', bodyColor:'#B8A88A' }
    },
    cutout: '55%'
};

new Chart(document.getElementById('harvestDist').getContext('2d'), {
    type: 'doughnut',
    data: { labels: sizeLabels, datasets: [{ data: harvestData, backgroundColor: chartColors, borderWidth: 3, borderColor: '#231E18' }] },
    options: sharedOpts
});

new Chart(document.getElementById('stockDist').getContext('2d'), {
    type: 'doughnut',
    data: { labels: sizeLabels, datasets: [{ data: remainData, backgroundColor: chartColors, borderWidth: 3, borderColor: '#231E18' }] },
    options: sharedOpts
});

</script>

<?php include('../../includes/footer.php'); ?>