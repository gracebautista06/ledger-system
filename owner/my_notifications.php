<?php
/* ============================================================
   owner/my_notifications.php — Owner Notification Center

   Unified page showing ALL notifications to the owner:
   1. Staff edit/delete requests (pending + history)
   2. Sell-first alert statuses (unread = not seen, read = seen by staff, completed = sold out)

   Tabs: Pending | Edit/Delete Requests | Sell-First Alerts | All History
   ============================================================ */

$page_title = 'Notifications';

session_start();
include('../includes/db.php');
include('../includes/header.php');
include('../includes/notifications.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../portal/login.php"); exit();
}

$flash = "";

// ── Handle approve / reject of edit_requests ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['req_id'])) {
    $req_id = (int)($_POST['req_id'] ?? 0);
    $action = in_array($_POST['action'] ?? '', ['approve','reject']) ? $_POST['action'] : '';

    if ($req_id > 0 && $action) {
        $req_stmt = $conn->prepare("SELECT * FROM edit_requests WHERE request_id=? LIMIT 1");
        $req_stmt->bind_param("i", $req_id);
        $req_stmt->execute();
        $req = $req_stmt->get_result()->fetch_assoc();
        $req_stmt->close();

        if ($req) {
            $record_id   = (int)$req['record_id'];
            $record_type = $req['record_type'];
            $new_values  = json_decode($req['new_data'], true);
            $req_type    = $req['request_type'] ?? (isset($new_values['action']) && $new_values['action'] === 'delete' ? 'Delete' : 'Edit');
            $is_delete   = ($req_type === 'Delete' || ($new_values['action'] ?? '') === 'delete');

            if ($action === 'approve') {
                if ($is_delete) {
                    $table_map = [
                        'Harvest' => ['harvests',     'harvest_id'],
                        'Health'  => ['flock_health', 'report_id'],
                        'Sale'    => ['sales',         'sale_id'],
                    ];
                    if (isset($table_map[$record_type])) {
                        [$tbl, $col] = $table_map[$record_type];
                        $del = $conn->prepare("DELETE FROM $tbl WHERE $col=?");
                        $del->bind_param("i", $record_id); $del->execute(); $del->close();
                    }
                } else {
                    if ($record_type === 'Harvest') {
                        $total = (int)($new_values['total_eggs'] ?? 0);
                        $upd = $conn->prepare("UPDATE harvests SET total_eggs=? WHERE harvest_id=?");
                        $upd->bind_param("ii", $total, $record_id); $upd->execute(); $upd->close();
                    } elseif ($record_type === 'Health') {
                        $mort = (int)($new_values['mortality_count'] ?? 0);
                        $upd = $conn->prepare("UPDATE flock_health SET mortality_count=? WHERE report_id=?");
                        $upd->bind_param("ii", $mort, $record_id); $upd->execute(); $upd->close();
                    }
                }
                $upd_r = $conn->prepare("UPDATE edit_requests SET status='Approved', reviewed_at=NOW() WHERE request_id=?");
                $upd_r->bind_param("i", $req_id); $upd_r->execute(); $upd_r->close();
                $flash = "<div class='alert success'>✅ Request approved and record updated.</div>";

            } else {
                $rej = $conn->prepare("UPDATE edit_requests SET status='Rejected', reviewed_at=NOW() WHERE request_id=?");
                $rej->bind_param("i", $req_id); $rej->execute(); $rej->close();
                $flash = "<div class='alert warning'>❌ Request rejected and closed.</div>";
            }
        }
    }
}

if (!$flash && isset($_GET['msg'])) {
    $flash = $_GET['msg'] === 'approved'
        ? "<div class='alert success'>✅ Request approved and record updated.</div>"
        : "<div class='alert warning'>❌ Request rejected and closed.</div>";
}

// ── Active tab ────────────────────────────────────────────────
$allowed_tabs = ['pending', 'requests', 'sell_first', 'all'];
$tab = in_array($_GET['tab'] ?? '', $allowed_tabs) ? $_GET['tab'] : 'pending';

// ── Fetch edit/delete requests ────────────────────────────────
$req_q = $conn->query("
    SELECT er.*, u.username
    FROM edit_requests er
    JOIN users u ON er.staff_id = u.user_id
    ORDER BY
        FIELD(er.status, 'Pending', 'Approved', 'Rejected'),
        er.created_at DESC
    LIMIT 80
");
$all_requests   = [];
$pending_req_count = 0;
if ($req_q) {
    while ($r = $req_q->fetch_assoc()) {
        $all_requests[] = $r;
        if ($r['status'] === 'Pending') $pending_req_count++;
    }
}

// ── Fetch sell-first notifications ───────────────────────────
$notif_q = $conn->query("
    SELECT n.*, b.breed
    FROM notifications n
    JOIN batches b ON n.batch_id = b.batch_id
    ORDER BY
        FIELD(n.status, 'unread', 'read', 'completed'),
        n.created_at DESC
    LIMIT 40
");
$all_notifs     = [];
$unseen_notif_count = 0;
if ($notif_q) {
    while ($n = $notif_q->fetch_assoc()) {
        $all_notifs[] = $n;
        if ($n['status'] === 'unread') $unseen_notif_count++; // Staff hasn't seen it yet
    }
}

// Total pending badge for owner bell
$total_pending = $pending_req_count + $unseen_notif_count;

// ── Filter per tab ────────────────────────────────────────────
$show_requests = in_array($tab, ['pending', 'requests', 'all']);
$show_notifs   = in_array($tab, ['pending', 'sell_first', 'all']);

$filtered_requests = match($tab) {
    'pending'    => array_filter($all_requests, fn($r) => $r['status'] === 'Pending'),
    'requests'   => $all_requests,
    'all'        => $all_requests,
    default      => [],
};
$filtered_notifs = match($tab) {
    'pending'    => array_filter($all_notifs, fn($n) => $n['status'] === 'unread'),
    'sell_first' => $all_notifs,
    'all'        => $all_notifs,
    default      => [],
};
?>

<div style="max-width:900px; margin:2rem auto;">

    <!-- Page header -->
    <div class="page-header">
        <div>
            <h2>🔔 Notifications</h2>
            <p>
                <?php if ($pending_req_count > 0): ?>
                    <span style="color:var(--danger); font-weight:700;"><?php echo $pending_req_count; ?></span>
                    pending staff request<?php echo $pending_req_count !== 1 ? 's' : ''; ?><?php echo $unseen_notif_count > 0 ? ' · ' : ''; ?>
                <?php endif; ?>
                <?php if ($unseen_notif_count > 0): ?>
                    <span style="color:var(--warning); font-weight:700;"><?php echo $unseen_notif_count; ?></span>
                    sell-first alert<?php echo $unseen_notif_count !== 1 ? 's' : ''; ?> not yet seen by staff
                <?php endif; ?>
                <?php if ($pending_req_count === 0 && $unseen_notif_count === 0): ?>
                    All caught up — nothing pending.
                <?php endif; ?>
            </p>
        </div>
        <a href="dashboard.php" class="back-link" style="margin:0;">← Dashboard</a>
    </div>

    <?php echo $flash; ?>

    <!-- ── Tabs ──────────────────────────────────────────────── -->
    <div class="filter-tabs" style="margin-bottom:1.8rem; flex-wrap:wrap; display:flex; gap:6px;">
        <?php
        $tabs = [
            'pending'    => ['label' => 'Pending',             'badge' => $total_pending,       'color' => 'var(--danger)'],
            'requests'   => ['label' => 'Edit / Delete Requests', 'badge' => $pending_req_count, 'color' => 'var(--terra-lt)'],
            'sell_first' => ['label' => 'Sell-First Alerts',   'badge' => $unseen_notif_count,  'color' => 'var(--warning)'],
            'all'        => ['label' => 'All History',         'badge' => 0,                    'color' => ''],
        ];
        foreach ($tabs as $key => $t):
            $is_active = ($tab === $key);
        ?>
        <a href="?tab=<?php echo $key; ?>"
           style="display:inline-flex; align-items:center; gap:6px;
                  padding:8px 14px; border-radius:var(--radius);
                  font-size:0.82rem; font-weight:700; text-decoration:none; transition:all 0.15s;
                  <?php echo $is_active
                      ? 'background:var(--gold); color:#1a1209;'
                      : 'background:var(--bg-wood); color:var(--text-secondary); border:1px solid var(--border-subtle);'; ?>">
            <?php echo $t['label']; ?>
            <?php if ($t['badge'] > 0): ?>
                <span style="background:<?php echo $is_active ? 'rgba(0,0,0,0.2)' : $t['color']; ?>;
                             color:<?php echo $is_active ? '#1a1209' : '#fff'; ?>;
                             border-radius:999px; padding:1px 7px; font-size:0.68rem; font-weight:800;">
                    <?php echo $t['badge']; ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════ -->
    <!-- SECTION 1: Edit / Delete Requests                      -->
    <!-- ══════════════════════════════════════════════════════ -->
    <?php if ($show_requests && !empty($filtered_requests)): ?>

    <?php if ($tab === 'all' || $tab === 'pending'): ?>
    <div style="font-size:0.72rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;
                letter-spacing:0.8px; margin-bottom:10px;">
        ✏️ Edit / Delete Requests
    </div>
    <?php endif; ?>

    <?php foreach ($filtered_requests as $r):
        $data      = json_decode($r['new_data'], true);
        $req_type  = $r['request_type'] ?? ((($data['action'] ?? '') === 'delete') ? 'Delete' : 'Edit');
        $is_delete = ($req_type === 'Delete' || ($data['action'] ?? '') === 'delete');
        $is_done   = in_array($r['status'], ['Approved','Rejected']);

        $border = $is_delete ? 'var(--danger)' : 'var(--terra-lt)';
        if ($r['status'] === 'Approved') $border = 'var(--success)';
        if ($r['status'] === 'Rejected') $border = 'var(--text-muted)';

        // Original vs proposed (edit only)
        $original_val = '—';
        $proposed_val = '—';
        if (!$is_delete) {
            if ($r['record_type'] === 'Harvest') {
                $os = $conn->prepare("SELECT total_eggs FROM harvests WHERE harvest_id=?");
                $os->bind_param("i", $r['record_id']); $os->execute();
                $ow = $os->get_result()->fetch_assoc(); $os->close();
                if ($ow) $original_val = number_format((int)$ow['total_eggs']) . ' eggs';
                $proposed_val = number_format((int)($data['total_eggs'] ?? 0)) . ' eggs';
            } elseif ($r['record_type'] === 'Health') {
                $os = $conn->prepare("SELECT mortality_count FROM flock_health WHERE report_id=?");
                $os->bind_param("i", $r['record_id']); $os->execute();
                $ow = $os->get_result()->fetch_assoc(); $os->close();
                if ($ow) $original_val = number_format((int)$ow['mortality_count']) . ' birds';
                $proposed_val = number_format((int)($data['mortality_count'] ?? 0)) . ' birds';
            }
        }
    ?>
    <div class="card" style="margin-bottom:1.1rem;
                             border-left:4px solid <?php echo $border; ?>;
                             padding:1.3rem 1.5rem;
                             opacity:<?php echo $is_done ? '0.72' : '1'; ?>;">

        <!-- Top row -->
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:11px;">
            <div>
                <div style="display:flex; align-items:center; gap:7px; flex-wrap:wrap; margin-bottom:6px;">
                    <?php if ($r['status'] === 'Pending'): ?>
                        <span class="badge badge-pending" style="font-size:0.6rem;">● PENDING</span>
                    <?php elseif ($r['status'] === 'Approved'): ?>
                        <span class="badge badge-approved" style="font-size:0.6rem;">✓ APPROVED</span>
                    <?php else: ?>
                        <span class="badge badge-rejected" style="font-size:0.6rem;">✕ REJECTED</span>
                    <?php endif; ?>
                    <?php if ($is_delete): ?>
                        <span class="badge badge-critical" style="font-size:0.6rem;">🗑️ DELETE</span>
                    <?php else: ?>
                        <span class="badge badge-staff" style="font-size:0.6rem;">✏️ EDIT</span>
                    <?php endif; ?>
                    <span style="font-size:0.78rem; font-weight:700; color:var(--text-muted);">
                        <?php echo $r['record_type']; ?> #<?php echo $r['record_id']; ?>
                    </span>
                </div>
                <div style="font-weight:700; font-size:0.93rem; color:var(--text-primary);">
                    <?php echo htmlspecialchars($r['username']); ?>
                    <span style="font-weight:400; color:var(--text-muted); font-size:0.83rem;"> · Staff</span>
                </div>
                <div style="color:var(--text-secondary); font-size:0.83rem; margin-top:3px;">
                    <strong style="color:var(--text-muted);">Reason:</strong>
                    "<?php echo htmlspecialchars($r['reason']); ?>"
                </div>
            </div>
            <div style="text-align:right; font-size:0.73rem; color:var(--text-muted);">
                <div>Submitted <?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                <div><?php echo date('g:i A', strtotime($r['created_at'])); ?></div>
                <?php if ($r['reviewed_at']): ?>
                <div style="margin-top:3px;">Reviewed <?php echo date('M d', strtotime($r['reviewed_at'])); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delete warning or edit comparison -->
        <?php if ($is_delete): ?>
        <div style="background:rgba(194,58,58,0.07); border:1px solid rgba(194,58,58,0.25);
                    border-radius:var(--radius-sm); padding:12px 15px; margin-bottom:11px;">
            <div style="font-size:0.7rem; font-weight:700; color:var(--danger); text-transform:uppercase;
                        letter-spacing:0.6px; margin-bottom:4px;">⚠️ Permanent Deletion Requested</div>
            <div style="font-size:0.86rem; color:var(--text-secondary);">
                Approving will <strong style="color:var(--danger);">permanently delete</strong>
                <?php echo strtolower($r['record_type']); ?> record #<?php echo $r['record_id']; ?>.
                This cannot be undone.
            </div>
        </div>
        <?php else: ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:11px;">
            <div style="background:var(--bg-wood); padding:10px 14px; border-radius:var(--radius-sm); border:1px solid var(--border-subtle);">
                <div style="font-size:0.67rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.6px; margin-bottom:4px;">Current Value</div>
                <div style="font-size:1.05rem; font-weight:700; color:var(--text-secondary);"><?php echo $original_val; ?></div>
            </div>
            <div style="background:var(--bg-wood); padding:10px 14px; border-radius:var(--radius-sm); border:1px solid var(--border-mid);">
                <div style="font-size:0.67rem; font-weight:700; color:var(--gold-muted); text-transform:uppercase; letter-spacing:0.6px; margin-bottom:4px;">Proposed Change</div>
                <div style="font-size:1.05rem; font-weight:700; color:var(--gold);"><?php echo $proposed_val; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action buttons (pending only) -->
        <?php if ($r['status'] === 'Pending'): ?>
        <div style="display:flex; gap:10px;">
            <form method="POST" style="flex:1;"
                  onsubmit="return confirm('<?php echo $is_delete
                      ? 'PERMANENTLY DELETE this record? Cannot be undone.'
                      : 'Approve this change and update the record?'; ?>')">
                <input type="hidden" name="req_id" value="<?php echo $r['request_id']; ?>">
                <input type="hidden" name="action"  value="approve">
                <button type="submit" class="btn-farm <?php echo $is_delete ? 'btn-danger' : 'btn-green'; ?> btn-full">
                    <?php echo $is_delete ? '🗑️ Approve & Delete' : '✅ Approve & Update'; ?>
                </button>
            </form>
            <form method="POST" style="flex:1;" onsubmit="return confirm('Reject this request?')">
                <input type="hidden" name="req_id" value="<?php echo $r['request_id']; ?>">
                <input type="hidden" name="action"  value="reject">
                <button type="submit" class="btn-farm btn-outline btn-full">❌ Reject</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php elseif ($show_requests && $tab !== 'sell_first'): ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="empty-state">
            <span class="empty-icon">✏️</span>
            <p>No <?php echo $tab === 'pending' ? 'pending ' : ''; ?>edit/delete requests.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════ -->
    <!-- SECTION 2: Sell-First Alert Statuses                   -->
    <!-- ══════════════════════════════════════════════════════ -->
    <?php if ($show_notifs && !empty($filtered_notifs)): ?>

    <?php if ($tab === 'all' || $tab === 'pending'): ?>
    <div style="font-size:0.72rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;
                letter-spacing:0.8px; margin:<?php echo $tab==='all' ? '1.8rem' : '0'; ?> 0 10px;">
        📢 Sell-First Alert Status
    </div>
    <?php endif; ?>

    <?php foreach ($filtered_notifs as $n):
        $status_color = match($n['status']) {
            'unread'    => 'var(--danger)',
            'read'      => 'var(--warning)',
            'completed' => 'var(--success)',
            default     => 'var(--text-muted)',
        };
        $status_label = match($n['status']) {
            'unread'    => '🔴 Not Seen Yet',
            'read'      => '👁️ Seen by Staff',
            'completed' => '✅ Completed',
            default     => $n['status'],
        };
    ?>
    <div class="card" style="margin-bottom:1.1rem;
                             border-left:4px solid <?php echo $status_color; ?>;
                             padding:1.3rem 1.5rem;
                             opacity:<?php echo $n['status'] === 'completed' ? '0.72' : '1'; ?>;">

        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:10px;">
            <div>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <span class="badge" style="font-size:0.6rem; background:<?php echo $status_color; ?>; color:#fff; border-radius:4px; padding:2px 7px;">
                        <?php echo $status_label; ?>
                    </span>
                    <span style="font-size:0.78rem; font-weight:700; color:var(--text-muted);">
                        📢 Sell-First Alert — Batch #<?php echo $n['batch_id']; ?> — <?php echo htmlspecialchars($n['breed']); ?>
                    </span>
                </div>
                <div style="font-size:0.85rem; color:var(--text-secondary); line-height:1.5; margin-bottom:4px;">
                    <?php echo htmlspecialchars($n['message']); ?>
                </div>
            </div>
            <div style="text-align:right; font-size:0.73rem; color:var(--text-muted);">
                <div>Sent <?php echo date('M d, Y', strtotime($n['created_at'])); ?></div>
                <div><?php echo date('g:i A', strtotime($n['created_at'])); ?></div>
                <?php if ($n['read_at']): ?>
                <div style="margin-top:3px; color:var(--warning);">
                    Seen <?php echo date('M d g:i A', strtotime($n['read_at'])); ?>
                </div>
                <?php endif; ?>
                <?php if ($n['completed_at']): ?>
                <div style="margin-top:3px; color:var(--success);">
                    Done <?php echo date('M d g:i A', strtotime($n['completed_at'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <div style="background:var(--bg-wood); border-radius:var(--radius-sm); padding:8px 14px;
                        border:1px solid var(--border-mid); text-align:center; min-width:80px;">
                <div style="font-size:0.6rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Target</div>
                <div style="font-size:1.3rem; font-weight:800; color:var(--danger); font-family:'Playfair Display',serif;">
                    <?php echo number_format($n['target_trays']); ?>
                </div>
                <div style="font-size:0.65rem; color:var(--text-muted);">trays</div>
            </div>

            <?php if ($n['status'] === 'unread'): ?>
            <div style="font-size:0.83rem; color:var(--danger); font-weight:700; display:flex; align-items:center; gap:6px;">
                ⏳ Staff has not seen this alert yet.
            </div>
            <?php elseif ($n['status'] === 'read'): ?>
            <div style="font-size:0.83rem; color:var(--warning); font-weight:700; display:flex; align-items:center; gap:6px;">
                👁️ Staff acknowledged — sales in progress.
            </div>
            <?php else: ?>
            <div style="font-size:0.83rem; color:var(--success); font-weight:700; display:flex; align-items:center; gap:6px;">
                🎉 Sell target completed!
            </div>
            <?php endif; ?>

            <div style="margin-left:auto;">
                <a href="manage_flock/inventory.php"
                   class="btn-farm btn-dark btn-sm" style="font-size:0.8rem;">
                    📦 View Inventory
                </a>
            </div>
        </div>

    </div>
    <?php endforeach; ?>

    <?php elseif ($show_notifs && $tab !== 'requests'): ?>
    <div class="card">
        <div class="empty-state">
            <span class="empty-icon">📢</span>
            <p>No <?php echo $tab === 'pending' ? 'unseen ' : ''; ?>sell-first alerts.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Completely empty state -->
    <?php if (empty($filtered_requests) && empty($filtered_notifs) && $tab === 'pending'): ?>
    <div class="card">
        <div class="empty-state">
            <span class="empty-icon">✅</span>
            <p>All caught up!</p>
            <small>No pending requests or unseen alerts.</small>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include('../includes/footer.php'); ?>