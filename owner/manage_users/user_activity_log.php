<?php
/* ============================================================
   owner/view_history.php — System Activity Log
   v3 improvements:
   - Separated tables per activity category (tabs)
   - Login / Harvest / Sale / Health / User Management tabs
   - Pagination preserved per tab
   - Export CSV per category
   - Uses btn-farm classes, dark theme vars throughout
   ============================================================ */

$page_title = 'System History';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

// ── Category tabs ─────────────────────────────────────────────
$categories = [
    'all'      => ['label' => 'All Activity',     'icon' => '📜'],
    'Login'    => ['label' => 'Logins',            'icon' => '🔐'],
    'Harvest'  => ['label' => 'Harvests',          'icon' => '🧺'],
    'Sale'     => ['label' => 'Sales',             'icon' => '💰'],
    'Health'   => ['label' => 'Health Reports',    'icon' => '🐔'],
    'UserMgmt' => ['label' => 'User Management',   'icon' => '👤'],
];

$active_tab = (isset($_GET['tab']) && array_key_exists($_GET['tab'], $categories))
              ? $_GET['tab'] : 'all';

// Role filter (secondary — kept for backwards compat)
$allowed_roles = ['all', 'Staff', 'Owner'];
$filter_role   = (isset($_GET['role']) && in_array($_GET['role'], $allowed_roles))
                 ? $_GET['role'] : 'all';

// Build WHERE
$conditions = [];
if ($active_tab !== 'all') {
    if ($active_tab === 'Login') {
        $conditions[] = "al.action_type LIKE 'Login%'";
    } elseif ($active_tab === 'UserMgmt') {
        $conditions[] = "al.action_type IN ('User Created','User Deleted','Password Changed','Role Changed')";
    } else {
        $escaped = $conn->real_escape_string($active_tab);
        $conditions[] = "al.action_type LIKE '{$escaped}%'";
    }
}
if ($filter_role !== 'all') {
    $escaped_role = $conn->real_escape_string($filter_role);
    $conditions[] = "al.user_role = '$escaped_role'";
}
$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Pagination
$per_page     = 50;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

$count_res   = $conn->query("SELECT COUNT(*) AS total FROM activity_logs al $where_clause");
$total_rows  = $count_res ? (int)$count_res->fetch_assoc()['total'] : 0;
$total_pages = max(1, ceil($total_rows / $per_page));

$logs = $conn->query("
    SELECT al.*, u.username
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    $where_clause
    ORDER BY al.timestamp DESC
    LIMIT $per_page OFFSET $offset
");

// Per-category counts for tab badges
$tab_counts = [];
foreach ($categories as $key => $_) {
    if ($key === 'all') {
        $r = $conn->query("SELECT COUNT(*) AS c FROM activity_logs");
    } elseif ($key === 'Login') {
        $r = $conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE action_type LIKE 'Login%'");
    } elseif ($key === 'UserMgmt') {
        $r = $conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE action_type IN ('User Created','User Deleted','Password Changed','Role Changed')");
    } else {
        $esc = $conn->real_escape_string($key);
        $r = $conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE action_type LIKE '{$esc}%'");
    }
    $tab_counts[$key] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

function build_url($page, $tab, $role) {
    return "?tab=$tab&role=$role&page=$page";
}

// Action type → color map
function action_color($type) {
    if (str_contains($type, 'Login'))    return 'var(--info)';
    if (str_contains($type, 'Harvest'))  return 'var(--gold)';
    if (str_contains($type, 'Sale'))     return 'var(--success)';
    if (str_contains($type, 'Health'))   return 'var(--terra-lt)';
    if (str_contains($type, 'Delete') || str_contains($type, 'Reject')) return 'var(--danger)';
    if (str_contains($type, 'Approve') || str_contains($type, 'User Created')) return 'var(--success)';
    return 'var(--text-muted)';
}
?>

<div style="max-width:1020px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>📜 System Activity Log</h2>
            <p><?php echo number_format($total_rows); ?> entries in current view.</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <!-- Role sub-filter -->
            <div class="filter-tabs">
                <a href="?tab=<?php echo $active_tab; ?>&role=all"
                   class="filter-tab <?php echo $filter_role==='all'   ? 'active-all'   : ''; ?>">All</a>
                <a href="?tab=<?php echo $active_tab; ?>&role=Staff"
                   class="filter-tab <?php echo $filter_role==='Staff' ? 'active-staff' : ''; ?>">Staff</a>
                <a href="?tab=<?php echo $active_tab; ?>&role=Owner"
                   class="filter-tab <?php echo $filter_role==='Owner' ? 'active-owner' : ''; ?>">Owner</a>
            </div>
            <a href="export_history.php?tab=<?php echo urlencode($active_tab); ?>&role=<?php echo urlencode($filter_role); ?>"
               class="btn-farm btn-dark btn-sm">⬇ Export CSV</a>
        </div>
    </div>

    <!-- ── Category Tabs ───────────────────────────────────── -->
    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:1.5rem;">
        <?php foreach ($categories as $key => $cat): ?>
        <a href="?tab=<?php echo $key; ?>&role=<?php echo $filter_role; ?>"
           style="display:flex; align-items:center; gap:6px;
                  padding:8px 14px; border-radius:var(--radius);
                  font-size:0.82rem; font-weight:700; text-decoration:none; transition:all 0.15s;
                  <?php echo $active_tab === $key
                      ? 'background:var(--gold); color:#1a1209;'
                      : 'background:var(--bg-wood); color:var(--text-secondary); border:1px solid var(--border-subtle);'; ?>">
            <?php echo $cat['icon']; ?> <?php echo $cat['label']; ?>
            <span style="background:<?php echo $active_tab === $key ? 'rgba(0,0,0,0.2)' : 'var(--bg-plank)'; ?>;
                         border-radius:999px; padding:1px 7px; font-size:0.7rem;">
                <?php echo number_format($tab_counts[$key]); ?>
            </span>
        </a>
        <?php endforeach; ?>
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
                            <span style="font-weight:700; color:<?php echo action_color($row['action_type']); ?>; font-size:0.85rem;">
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
                            <p>No activity logs found for this category.</p>
                        </div>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div style="display:flex; justify-content:center; gap:6px; padding:1.2rem; flex-wrap:wrap;">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo build_url($current_page-1, $active_tab, $filter_role); ?>"
                   class="btn-farm btn-dark btn-sm">← Prev</a>
            <?php endif; ?>
            <?php
            $start = max(1, $current_page - 2);
            $end   = min($total_pages, $current_page + 2);
            for ($p = $start; $p <= $end; $p++): ?>
                <a href="<?php echo build_url($p, $active_tab, $filter_role); ?>"
                   class="btn-farm btn-sm <?php echo $p === $current_page ? '' : 'btn-dark'; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo build_url($current_page+1, $active_tab, $filter_role); ?>"
                   class="btn-farm btn-dark btn-sm">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<?php include('../../includes/footer.php'); ?>