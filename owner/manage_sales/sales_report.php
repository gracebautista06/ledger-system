<?php
/* ============================================================
   owner/view_sales.php — Full Sales History
   - Filter by date range and payment method
   - Summary stats (total revenue, total trays, avg per sale)
   - Pagination (20 per page)
   - Link to export
   ============================================================ */

$page_title = 'Sales History';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

// --- FILTERS ---
$allowed_methods = ['all', 'Cash', 'GCash', 'Bank Transfer'];
$filter_method   = (isset($_GET['method']) && in_array($_GET['method'], $allowed_methods))
                   ? $_GET['method'] : 'all';

$date_from = !empty($_GET['from']) ? $_GET['from'] : date('Y-m-01');      // First of current month
$date_to   = !empty($_GET['to'])   ? $_GET['to']   : date('Y-m-d');       // Today

// Sanitize dates
$date_from = date('Y-m-d', strtotime($date_from));
$date_to   = date('Y-m-d', strtotime($date_to));

// Build WHERE
$where_parts = ["DATE(s.date_sold) BETWEEN '$date_from' AND '$date_to'"];
if ($filter_method !== 'all') {
    $safe_method  = $conn->real_escape_string($filter_method);
    $where_parts[] = "s.payment_method = '$safe_method'";
}
$where = 'WHERE ' . implode(' AND ', $where_parts);

// --- SUMMARY STATS for the filtered period ---
$stats_q = $conn->query("
    SELECT
        COUNT(*)                    AS total_transactions,
        COALESCE(SUM(s.quantity_sold * 30), 0) AS total_eggs_sold,
        COALESCE(SUM(s.total_amount), 0)       AS total_revenue,
        COALESCE(AVG(s.total_amount), 0)       AS avg_sale,
        COALESCE(SUM(s.quantity_sold), 0)      AS total_trays
    FROM sales s
    $where
");
$stats = $stats_q ? $stats_q->fetch_assoc() : [];

// --- PAGINATION ---
$per_page     = 20;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

$count_q    = $conn->query("SELECT COUNT(*) AS total FROM sales s $where");
$total_rows = $count_q ? (int)$count_q->fetch_assoc()['total'] : 0;
$total_pages = max(1, ceil($total_rows / $per_page));

// --- MAIN QUERY ---
$sales = $conn->query("
    SELECT s.*, u.username AS staff_name
    FROM sales s
    LEFT JOIN users u ON s.staff_id = u.user_id
    $where
    ORDER BY s.date_sold DESC
    LIMIT $per_page OFFSET $offset
");

// Build pagination URL helper
function page_url($p, $from, $to, $method) {
    return "?page=$p&from=" . urlencode($from) . "&to=" . urlencode($to) . "&method=" . urlencode($method);
}
?>

<div style="max-width:1100px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>💰 Sales History</h2>
            <p>All recorded egg sales — filter by date range and payment method.</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <a href="export_sales.php?from=<?php echo urlencode($date_from); ?>&to=<?php echo urlencode($date_to); ?>&method=<?php echo urlencode($filter_method); ?>&format=excel"
               class="btn-farm btn-green btn-sm">⬇ Export Excel</a>
            <a href="export_sales.php?from=<?php echo urlencode($date_from); ?>&to=<?php echo urlencode($date_to); ?>&method=<?php echo urlencode($filter_method); ?>&format=pdf"
               class="btn-farm btn-danger btn-sm">⬇ Export PDF</a>
            <a href="../dashboard.php" class="back-link" style="margin:0;">← Dashboard</a>
        </div>
    </div>

    <!-- FILTER FORM -->
    <div class="card" style="margin-bottom:1.5rem; padding:1.4rem 1.8rem;">
        <form method="GET" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
            <div class="form-group" style="margin:0; flex:1; min-width:140px;">
                <label>From</label>
                <input type="date" name="from" class="form-input" value="<?php echo $date_from; ?>">
            </div>
            <div class="form-group" style="margin:0; flex:1; min-width:140px;">
                <label>To</label>
                <input type="date" name="to" class="form-input" value="<?php echo $date_to; ?>">
            </div>
            <div class="form-group" style="margin:0; flex:1; min-width:160px;">
                <label>Payment Method</label>
                <select name="method" class="form-input">
                    <?php foreach (['all' => 'All Methods', 'Cash' => '💵 Cash', 'GCash' => '📱 GCash', 'Bank Transfer' => '🏦 Bank Transfer'] as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $filter_method === $val ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="padding-bottom:1px;">
                <button type="submit" class="btn-farm btn-sm">🔍 Filter</button>
                <a href="view_sales.php" class="btn-farm btn-dark btn-sm" style="margin-left:6px;">✕ Reset</a>
            </div>
        </form>
    </div>

    <!-- SUMMARY STAT CARDS -->
    <div style="display:flex; gap:16px; margin-bottom:1.8rem; flex-wrap:wrap;">
        <div class="stat-card" style="border-top:4px solid var(--success);">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">₱<?php echo number_format((float)$stats['total_revenue'], 2); ?></div>
            <div class="stat-sub"><?php echo date('M d', strtotime($date_from)); ?> – <?php echo date('M d', strtotime($date_to)); ?></div>
        </div>
        <div class="stat-card" style="border-top:4px solid var(--gold);">
            <div class="stat-label">Transactions</div>
            <div class="stat-value"><?php echo number_format((int)$stats['total_transactions']); ?></div>
            <div class="stat-sub">sales recorded</div>
        </div>
        <div class="stat-card" style="border-top:4px solid var(--terra-lt);">
            <div class="stat-label">Trays Sold</div>
            <div class="stat-value"><?php echo number_format((int)$stats['total_trays']); ?></div>
            <div class="stat-sub"><?php echo number_format((int)$stats['total_eggs_sold']); ?> eggs total</div>
        </div>
        <div class="stat-card" style="border-top:4px solid var(--info);">
            <div class="stat-label">Avg. Per Sale</div>
            <div class="stat-value">₱<?php echo number_format((float)$stats['avg_sale'], 2); ?></div>
            <div class="stat-sub">average transaction</div>
        </div>
    </div>

    <!-- SALES TABLE -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div style="padding:1.4rem 1.8rem 1rem; border-bottom:1px solid var(--border-subtle);">
            <h3 style="margin:0;">Transaction Log
                <span style="font-size:0.78rem; font-weight:500; color:var(--text-muted); margin-left:10px;">
                    <?php echo number_format($total_rows); ?> record<?php echo $total_rows !== 1 ? 's' : ''; ?> found
                </span>
            </h3>
        </div>
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date & Time</th>
                        <th>Staff</th>
                        <th>Customer</th>
                        <th>Trays</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sales && $sales->num_rows > 0):
                        while ($row = $sales->fetch_assoc()):
                            $method_icon = match($row['payment_method']) {
                                'GCash'        => '📱',
                                'Bank Transfer'=> '🏦',
                                default        => '💵',
                            };
                    ?>
                    <tr>
                        <td style="color:var(--text-muted); font-size:0.78rem;">#<?php echo $row['sale_id']; ?></td>
                        <td style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;">
                            <?php echo date('M d, Y', strtotime($row['date_sold'])); ?><br>
                            <span style="font-size:0.73rem;"><?php echo date('g:i A', strtotime($row['date_sold'])); ?></span>
                        </td>
                        <td style="font-size:0.84rem;"><?php echo htmlspecialchars($row['staff_name'] ?? '—'); ?></td>
                        <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                        <td style="text-align:center; font-weight:700;"><?php echo number_format($row['quantity_sold']); ?></td>
                        <td>₱<?php echo number_format((float)$row['unit_price'], 2); ?></td>
                        <td style="font-weight:700; color:var(--success);">
                            ₱<?php echo number_format((float)$row['total_amount'], 2); ?>
                        </td>
                        <td>
                            <span style="font-size:0.82rem;"><?php echo $method_icon; ?> <?php echo htmlspecialchars($row['payment_method']); ?></span>
                        </td>
                        <td style="font-size:0.8rem; color:var(--text-muted); max-width:160px;">
                            <?php echo htmlspecialchars($row['notes'] ?: '—'); ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="9">
                        <div class="empty-state">
                            <span class="empty-icon">💰</span>
                            <p>No sales found for this period.</p>
                            <small>Try adjusting your date range or filters.</small>
                        </div>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($total_rows > 0): ?>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align:right; font-size:0.8rem;">Period Total</td>
                        <td style="color:var(--gold);">₱<?php echo number_format((float)$stats['total_revenue'], 2); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div style="display:flex; justify-content:center; gap:6px; padding:1.2rem; flex-wrap:wrap;">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo page_url($current_page - 1, $date_from, $date_to, $filter_method); ?>"
                   class="btn-farm btn-dark btn-sm">← Prev</a>
            <?php endif; ?>
            <?php
            $start = max(1, $current_page - 2);
            $end   = min($total_pages, $current_page + 2);
            for ($p = $start; $p <= $end; $p++): ?>
                <a href="<?php echo page_url($p, $date_from, $date_to, $filter_method); ?>"
                   class="btn-farm btn-sm <?php echo $p === $current_page ? '' : 'btn-dark'; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo page_url($current_page + 1, $date_from, $date_to, $filter_method); ?>"
                   class="btn-farm btn-dark btn-sm">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<?php include('../../includes/footer.php'); ?>