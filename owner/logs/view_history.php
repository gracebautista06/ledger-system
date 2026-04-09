<?php
/* ============================================================
   owner/view_history.php — System Activity Log

   IMPROVEMENTS v2:
   - Fixed pagination: old inline styles used light #eee bg → btn-farm classes
   - Fixed action_type color: --barn-red → var(--terra-lt)
   - Added "Export to CSV" for audit trail download
   ============================================================ */

$page_title = 'System History';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

// Whitelisted filter
$allowed_filters = ['all', 'Staff', 'Owner'];
$filter = (isset($_GET['role']) && in_array($_GET['role'], $allowed_filters))
          ? $_GET['role'] : 'all';

$where_clause = ($filter !== 'all')
    ? "WHERE al.user_role = '" . $conn->real_escape_string($filter) . "'"
    : "";

// Pagination
$per_page     = 50;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

$count_res  = $conn->query("SELECT COUNT(*) AS total FROM activity_logs al $where_clause");
$total_rows = $count_res ? (int)$count_res->fetch_assoc()['total'] : 0;
$total_pages = max(1, ceil($total_rows / $per_page));

$logs = $conn->query("
    SELECT al.*, u.username
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    $where_clause
    ORDER BY al.timestamp DESC
    LIMIT $per_page OFFSET $offset
");

function build_url($page, $role) {
    return "?role=$role&page=$page";
}
?>

<div style="max-width:1020px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>📜 System Activity Log</h2>
            <p><?php echo number_format($total_rows); ?> total entries.</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <div class="filter-tabs">
                <a href="?role=all"   class="filter-tab <?php echo $filter==='all'   ? 'active-all'   : ''; ?>">All</a>
                <a href="?role=Staff" class="filter-tab <?php echo $filter==='Staff' ? 'active-staff' : ''; ?>">Staff</a>
                <a href="?role=Owner" class="filter-tab <?php echo $filter==='Owner' ? 'active-owner' : ''; ?>">Owner</a>
            </div>
            <!-- Export as CSV -->
            <a href="export_history.php?role=<?php echo urlencode($filter); ?>"
               class="btn-farm btn-dark btn-sm">⬇ Export CSV</a>
        </div>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0):
                        while ($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td style="font-size:0.78rem; color:var(--text-muted); white-space:nowrap;">
                            <?php echo date('M d, Y', strtotime($row['timestamp'])); ?><br>
                            <span style="font-size:0.72rem;"><?php echo date('g:i A', strtotime($row['timestamp'])); ?></span>
                        </td>
                        <td>
                            <strong style="color:var(--text-primary);">
                                <?php echo htmlspecialchars($row['username'] ?? 'Deleted User'); ?>
                            </strong><br>
                            <span class="badge <?php echo $row['user_role']==='Owner' ? 'badge-owner' : 'badge-staff'; ?>"
                                  style="margin-top:3px; display:inline-block;">
                                <?php echo htmlspecialchars($row['user_role']); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight:700; color:var(--terra-lt); font-size:0.85rem;">
                                <?php echo htmlspecialchars($row['action_type']); ?>
                            </span>
                        </td>
                        <td style="font-size:0.85rem; color:var(--text-secondary);">
                            <?php echo htmlspecialchars($row['description']); ?>
                        </td>
                    </tr>
                    <?php endwhile;
                    else: ?>
                    <tr><td colspan="4">
                        <div class="empty-state">
                            <span class="empty-icon">📋</span>
                            <p>No activity logs found.</p>
                        </div>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination using btn-farm classes -->
        <?php if ($total_pages > 1): ?>
        <div style="display:flex; justify-content:center; gap:6px; padding:1.2rem; flex-wrap:wrap;">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo build_url($current_page-1, $filter); ?>" class="btn-farm btn-dark btn-sm">← Prev</a>
            <?php endif; ?>
            <?php
            $start = max(1, $current_page - 2);
            $end   = min($total_pages, $current_page + 2);
            for ($p = $start; $p <= $end; $p++): ?>
                <a href="<?php echo build_url($p, $filter); ?>"
                   class="btn-farm btn-sm <?php echo $p === $current_page ? '' : 'btn-dark'; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo build_url($current_page+1, $filter); ?>" class="btn-farm btn-dark btn-sm">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<?php include('../../includes/footer.php'); ?>