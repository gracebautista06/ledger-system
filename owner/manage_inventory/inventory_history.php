<?php
/* ============================================================
   owner/manage_inventory/inventory_history.php
   — Long-Term Inventory Archive

   LAYER:  🟡 HISTORY / ARCHIVE (Layer 3)
   PURPOSE: Permanent record of all batches that have been
            fully consumed, sold out, or manually closed.

   WHAT THIS SHOWS:
   - Every batch that has been retired / sold out
   - Total eggs harvested vs. total eggs sold per batch
   - Estimated revenue generated per batch
   - Days it was active (lifespan)
   - Final status when it was closed
   - Searchable, filterable, exportable

   DATA SOURCE:
   - batches table (status = 'Retired' OR remaining = 0)
   - harvests table (total produced per batch)
   - sales table (total sold after batch's arrival date)

   FLOW:
   new_harvest_distribution → inventory.php (active)
                                      ↓ (batch sold out / retired)
                              inventory_history.php ← YOU ARE HERE
   ============================================================ */

$page_title = 'Inventory History';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

// ── FILTERS ──────────────────────────────────────────────────────
$filter_status  = in_array($_GET['status'] ?? '', ['all', 'Retired', 'sold_out']) ? ($_GET['status'] ?? 'all') : 'all';
$filter_breed   = trim($_GET['breed'] ?? '');
$date_from      = !empty($_GET['from']) ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-d', strtotime('-12 months'));
$date_to        = !empty($_GET['to'])   ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-d');

// ── PAGINATION ────────────────────────────────────────────────────
$per_page     = 20;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

// ── FETCH DISTINCT BREEDS FOR FILTER DROPDOWN ─────────────────────
$breeds_q = $conn->query("SELECT DISTINCT breed FROM batches ORDER BY breed ASC");
$breeds   = [];
if ($breeds_q) while ($r = $breeds_q->fetch_assoc()) $breeds[] = $r['breed'];

// ── BUILD WHERE CLAUSE ────────────────────────────────────────────
// History = Retired batches + Active batches that are sold out
$where_parts = ["b.status = 'Retired'"];

if ($filter_breed !== '') {
    $safe_breed  = $conn->real_escape_string($filter_breed);
    $where_parts[] = "b.breed = '$safe_breed'";
}

$where = 'WHERE ' . implode(' AND ', $where_parts);

// ── SUMMARY STATS ─────────────────────────────────────────────────
$summary_q = $conn->query("
    SELECT
        COUNT(b.batch_id)                        AS total_batches,
        COALESCE(SUM(h_agg.total_harvested), 0)  AS grand_harvested,
        COALESCE(SUM(s_agg.total_sold_eggs), 0)  AS grand_sold
    FROM batches b
    LEFT JOIN (
        SELECT batch_id, SUM(total_eggs) AS total_harvested
        FROM harvests GROUP BY batch_id
    ) h_agg ON h_agg.batch_id = b.batch_id
    LEFT JOIN (
        SELECT
            b2.batch_id,
            COALESCE(SUM(s.quantity_sold * 30), 0) AS total_sold_eggs
        FROM batches b2
        LEFT JOIN sales s ON DATE(s.date_sold) >= COALESCE(b2.arrival_date, b2.date_acquired, '2000-01-01')
        GROUP BY b2.batch_id
    ) s_agg ON s_agg.batch_id = b.batch_id
    $where
");
$summary = $summary_q ? $summary_q->fetch_assoc() : ['total_batches'=>0,'grand_harvested'=>0,'grand_sold'=>0];

// Revenue from retired batches
$rev_q = $conn->query("
    SELECT COALESCE(SUM(s.total_amount), 0) AS total_revenue
    FROM sales s
    JOIN batches b ON DATE(s.date_sold) >= COALESCE(b.arrival_date, b.date_acquired, '2000-01-01')
    $where
");
$total_revenue = $rev_q ? (float)$rev_q->fetch_assoc()['total_revenue'] : 0;

// ── COUNT FOR PAGINATION ──────────────────────────────────────────
$count_q    = $conn->query("SELECT COUNT(*) AS c FROM batches b $where");
$total_rows = $count_q ? (int)$count_q->fetch_assoc()['c'] : 0;
$total_pages = max(1, ceil($total_rows / $per_page));

// ── MAIN QUERY ───────────────────────────────────────────────────
$history_q = $conn->query("
    SELECT
        b.batch_id,
        b.breed,
        b.status,
        COALESCE(b.arrival_date, b.date_acquired)   AS arrival_date,
        b.expected_replacement,
        b.notes,
        COALESCE(h_agg.total_harvested, 0)           AS total_harvested,
        COALESCE(h_agg.harvest_sessions, 0)          AS harvest_sessions,
        COALESCE(h_agg.first_harvest, NULL)          AS first_harvest,
        COALESCE(h_agg.last_harvest,  NULL)          AS last_harvest,
        COALESCE(s_agg.total_sold_eggs, 0)           AS total_sold_eggs,
        COALESCE(s_agg.total_revenue,  0)            AS total_revenue,
        COALESCE(s_agg.sale_count,     0)            AS sale_count,
        COALESCE(b.initial_count, b.quantity, 0)     AS bird_count
    FROM batches b

    LEFT JOIN (
        SELECT
            batch_id,
            SUM(total_eggs)     AS total_harvested,
            COUNT(*)            AS harvest_sessions,
            MIN(date_logged)    AS first_harvest,
            MAX(date_logged)    AS last_harvest
        FROM harvests
        GROUP BY batch_id
    ) h_agg ON h_agg.batch_id = b.batch_id

    LEFT JOIN (
        SELECT
            b2.batch_id,
            COALESCE(SUM(s.quantity_sold * 30), 0) AS total_sold_eggs,
            COALESCE(SUM(s.total_amount),      0) AS total_revenue,
            COUNT(s.sale_id)                       AS sale_count
        FROM batches b2
        LEFT JOIN sales s
            ON DATE(s.date_sold) >= COALESCE(b2.arrival_date, b2.date_acquired, '2000-01-01')
        GROUP BY b2.batch_id
    ) s_agg ON s_agg.batch_id = b.batch_id

    $where
    ORDER BY b.batch_id DESC
    LIMIT $per_page OFFSET $offset
");

function page_url_inv($p, $params) {
    $params['page'] = $p;
    return '?' . http_build_query($params);
}
$filter_params = ['status'=>$filter_status, 'breed'=>$filter_breed, 'from'=>$date_from, 'to'=>$date_to];
?>

<div style="max-width:1080px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>🗃️ Inventory History</h2>
            <p>Permanent archive of all retired batches — full production &amp; revenue records.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <a href="inventory.php"      class="btn-farm btn-dark btn-sm">📦 Active Inventory</a>
            <a href="../dashboard.php"   class="back-link" style="margin:0;">← Dashboard</a>
        </div>
    </div>

    <!-- ── FILTER PANEL ─────────────────────────────────────────── -->
    <div class="card" style="padding:1.2rem 1.6rem; margin-bottom:1.5rem;">
        <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div class="form-group" style="margin:0; min-width:160px; flex:1;">
                <label>Breed</label>
                <select name="breed" class="form-input">
                    <option value="">All Breeds</option>
                    <?php foreach ($breeds as $b): ?>
                        <option value="<?php echo htmlspecialchars($b); ?>"
                            <?php echo $filter_breed === $b ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="padding-bottom:1px; display:flex; gap:8px;">
                <button type="submit" class="btn-farm btn-sm">🔍 Filter</button>
                <a href="inventory_history.php" class="btn-farm btn-dark btn-sm">✕ Reset</a>
            </div>
        </form>
    </div>

    <!-- ── SUMMARY CARDS ────────────────────────────────────────── -->
    <div style="display:flex; gap:16px; margin-bottom:1.8rem; flex-wrap:wrap;">
        <div class="stat-card" style="border-top:4px solid var(--gold);">
            <div class="stat-label">Archived Batches</div>
            <div class="stat-value"><?php echo number_format((int)$summary['total_batches']); ?></div>
            <div class="stat-sub">retired / closed</div>
        </div>
        <div class="stat-card" style="border-top:4px solid var(--terra-lt);">
            <div class="stat-label">Total Eggs Produced</div>
            <div class="stat-value"><?php echo number_format((int)$summary['grand_harvested']); ?></div>
            <div class="stat-sub"><?php echo number_format(floor((int)$summary['grand_harvested']/30)); ?> trays</div>
        </div>
        <div class="stat-card" style="border-top:4px solid var(--info);">
            <div class="stat-label">Total Eggs Sold</div>
            <div class="stat-value"><?php echo number_format((int)$summary['grand_sold']); ?></div>
            <div class="stat-sub"><?php echo number_format(floor((int)$summary['grand_sold']/30)); ?> trays</div>
        </div>
        <div class="stat-card" style="border-top:4px solid var(--success);">
            <div class="stat-label">Total Revenue Generated</div>
            <div class="stat-value">₱<?php echo number_format($total_revenue, 2); ?></div>
            <div class="stat-sub">from archived batches</div>
        </div>
    </div>

    <!-- ── HISTORY TABLE ────────────────────────────────────────── -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div style="padding:1.2rem 1.6rem; border-bottom:1px solid var(--border-subtle);
                    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <h3 style="margin:0;">
                📋 Archived Batch Records
                <span style="font-size:0.78rem; font-weight:500; color:var(--text-muted); margin-left:8px;">
                    <?php echo number_format($total_rows); ?> batch<?php echo $total_rows !== 1 ? 'es' : ''; ?> on record
                </span>
            </h3>
        </div>

        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Breed</th>
                        <th>Birds</th>
                        <th>Status</th>
                        <th>Acquired</th>
                        <th>Active Period</th>
                        <th>Eggs Produced</th>
                        <th>Eggs Sold</th>
                        <th>Revenue</th>
                        <th>Efficiency</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($history_q && $history_q->num_rows > 0):
                    while ($row = $history_q->fetch_assoc()):
                        $arrival      = $row['arrival_date'];
                        $last_harvest = $row['last_harvest'];
                        $harvested    = (int)$row['total_harvested'];
                        $sold_eggs    = (int)$row['total_sold_eggs'];
                        $remaining    = max(0, $harvested - $sold_eggs);
                        $efficiency   = $harvested > 0 ? round(($sold_eggs / $harvested) * 100, 1) : 0;
                        $eff_color    = $efficiency >= 90 ? 'var(--success)'
                                      : ($efficiency >= 70 ? 'var(--gold)' : 'var(--danger)');

                        // Lifespan in days
                        $lifespan_days = null;
                        if ($arrival && $last_harvest) {
                            $lifespan_days = (int)ceil((strtotime($last_harvest) - strtotime($arrival)) / 86400);
                        }
                ?>
                <tr>
                    <td style="color:var(--text-muted); font-size:0.8rem; font-weight:700;">
                        #<?php echo $row['batch_id']; ?>
                    </td>
                    <td>
                        <strong style="color:var(--text-primary);">
                            <?php echo htmlspecialchars($row['breed']); ?>
                        </strong>
                    </td>
                    <td style="color:var(--text-secondary); font-size:0.85rem;">
                        <?php echo number_format((int)$row['bird_count']); ?>
                    </td>
                    <td>
                        <span class="badge badge-rejected">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;">
                        <?php echo $arrival ? date('M d, Y', strtotime($arrival)) : '—'; ?>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted);">
                        <?php if ($arrival && $last_harvest): ?>
                            <div><?php echo date('M d, Y', strtotime($arrival)); ?></div>
                            <div style="color:var(--text-muted); font-size:0.73rem;">→ <?php echo date('M d, Y', strtotime($last_harvest)); ?></div>
                            <?php if ($lifespan_days !== null): ?>
                            <div style="margin-top:3px;">
                                <span style="font-size:0.72rem; font-weight:700; color:var(--info);">
                                    <?php echo $lifespan_days; ?> days active
                                </span>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <strong style="color:var(--text-primary);">
                            <?php echo number_format($harvested); ?>
                        </strong>
                        <div style="font-size:0.72rem; color:var(--text-muted);">
                            <?php echo number_format(floor($harvested/30)); ?> trays
                        </div>
                        <?php if ($row['harvest_sessions'] > 0): ?>
                        <div style="font-size:0.7rem; color:var(--text-muted);">
                            <?php echo $row['harvest_sessions']; ?> sessions
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <strong style="color:var(--success);">
                            <?php echo number_format($sold_eggs); ?>
                        </strong>
                        <div style="font-size:0.72rem; color:var(--text-muted);">
                            <?php echo number_format(floor($sold_eggs/30)); ?> trays
                        </div>
                        <?php if ($remaining > 0): ?>
                        <div style="font-size:0.7rem; color:var(--warning); margin-top:2px;">
                            <?php echo number_format($remaining); ?> unsold
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:700; color:var(--success);">
                        ₱<?php echo number_format((float)$row['total_revenue'], 2); ?>
                        <div style="font-size:0.7rem; font-weight:400; color:var(--text-muted);">
                            <?php echo $row['sale_count']; ?> sale<?php echo $row['sale_count'] != 1 ? 's' : ''; ?>
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <div style="font-weight:700; font-size:0.95rem; color:<?php echo $eff_color; ?>;">
                            <?php echo $efficiency; ?>%
                        </div>
                        <div style="background:var(--bg-wood); border-radius:3px; height:5px; margin-top:4px; min-width:60px;">
                            <div style="width:<?php echo min(100,$efficiency); ?>%;
                                        background:<?php echo $eff_color; ?>;
                                        height:5px; border-radius:3px;"></div>
                        </div>
                        <div style="font-size:0.65rem; color:var(--text-muted); margin-top:2px;">sold/harvested</div>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted); max-width:140px;">
                        <?php echo htmlspecialchars($row['notes'] ?: '—'); ?>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr><td colspan="11">
                    <div class="empty-state">
                        <span class="empty-icon">🗃️</span>
                        <p>No retired batches in the archive yet.</p>
                        <small>
                            When a batch is marked as Retired in
                            <a href="../../manage_flocks/batches.php" style="color:var(--gold);">Manage Flocks</a>,
                            it will appear here with its full production history.
                        </small>
                    </div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div style="display:flex; justify-content:center; gap:6px; padding:1.2rem; flex-wrap:wrap;">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo page_url_inv($current_page - 1, $filter_params); ?>"
                   class="btn-farm btn-dark btn-sm">← Prev</a>
            <?php endif; ?>
            <?php
            $start = max(1, $current_page - 2);
            $end   = min($total_pages, $current_page + 2);
            for ($p = $start; $p <= $end; $p++): ?>
                <a href="<?php echo page_url_inv($p, $filter_params); ?>"
                   class="btn-farm btn-sm <?php echo $p === $current_page ? '' : 'btn-dark'; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo page_url_inv($current_page + 1, $filter_params); ?>"
                   class="btn-farm btn-dark btn-sm">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- INFO NOTE -->
    <div style="background:var(--info-bg); padding:12px 16px; border-radius:var(--radius-sm);
                font-size:0.82rem; color:#6AABDE; border-left:4px solid var(--info);
                margin-top:1.4rem;">
        💡 <strong>How data flows:</strong>
        Active batches live in <strong>inventory.php</strong>.
        Once a batch is <strong>Retired</strong> in Manage Flocks,
        it moves here permanently with its full production &amp; sales record.
        This data is never deleted — it's your audit trail.
    </div>

    <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<?php include('../../includes/footer.php'); ?>