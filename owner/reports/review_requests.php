<?php
/* ============================================================
   owner/review_requests.php — Review Staff Edit Requests

   IMPROVEMENTS v2:
   - Fixed: proposed change box used #fff9f5 (light bg) → var(--bg-wood)
   - Fixed: --accent-orange → --terra-lt border on card
   - Fixed: --barn-red text → var(--gold) on proposed change label
   - Added: shows original record value alongside the proposed change
     so the Owner can compare before approving
   ============================================================ */

$page_title = 'Review Edit Requests';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req_id = intval($_POST['req_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($req_id > 0 && in_array($action, ['approve', 'reject'])) {
        if ($action === 'approve') {
            $req_q = $conn->query("SELECT * FROM edit_requests WHERE request_id=$req_id");
            if ($req_q && $req_q->num_rows > 0) {
                $req        = $req_q->fetch_assoc();
                $new_values = json_decode($req['new_data'], true);
                $record_id  = intval($req['record_id']);

                if ($req['record_type'] === 'Harvest') {
                    $total = intval($new_values['total_eggs']);
                    $conn->query("UPDATE harvests SET total_eggs=$total WHERE harvest_id=$record_id");
                } elseif ($req['record_type'] === 'Health') {
                    $mortality = intval($new_values['mortality_count']);
                    $conn->query("UPDATE flock_health SET mortality_count=$mortality WHERE report_id=$record_id");
                }
                $conn->query("UPDATE edit_requests SET status='Approved', reviewed_at=NOW() WHERE request_id=$req_id");
                header("Location: reports/review_requests.php?msg=approved");
                exit();
            }
        } else {
            $conn->query("UPDATE edit_requests SET status='Rejected', reviewed_at=NOW() WHERE request_id=$req_id");
            header("Location: reports/review_requests.php?msg=rejected");
            exit();
        }
    }
}

$flash = "";
if (isset($_GET['msg'])) {
    $flash = $_GET['msg'] === 'approved'
        ? "<div class='alert success'>✅ Request approved. The record has been updated.</div>"
        : "<div class='alert error'>❌ Request rejected and marked as closed.</div>";
}

$requests = $conn->query("
    SELECT er.*, u.username
    FROM edit_requests er
    JOIN users u ON er.staff_id = u.user_id
    WHERE er.status = 'Pending'
    ORDER BY er.created_at DESC
");
$total_pending = $requests ? $requests->num_rows : 0;
?>

<div style="max-width:860px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>🛠️ Review Edit Requests</h2>
            <p><?php echo $total_pending; ?> pending request<?php echo $total_pending !== 1 ? 's' : ''; ?>.</p>
        </div>
        <a href="../dashboard.php" class="back-link" style="margin:0;">← Dashboard</a>
    </div>

    <?php echo $flash; ?>

    <?php if ($total_pending > 0):
        while ($r = $requests->fetch_assoc()):
            $data = json_decode($r['new_data'], true);

            // Fetch original value for comparison
            $original_val = '—';
            if ($r['record_type'] === 'Harvest') {
                $orig_q = $conn->query("SELECT total_eggs FROM harvests WHERE harvest_id=" . intval($r['record_id']));
                if ($orig_q && $orig_q->num_rows > 0) {
                    $original_val = number_format((int)$orig_q->fetch_assoc()['total_eggs']) . ' eggs';
                }
                $proposed_val = number_format(intval($data['total_eggs'])) . ' eggs';
            } else {
                $orig_q = $conn->query("SELECT mortality_count FROM flock_health WHERE report_id=" . intval($r['record_id']));
                if ($orig_q && $orig_q->num_rows > 0) {
                    $original_val = number_format((int)$orig_q->fetch_assoc()['mortality_count']) . ' birds';
                }
                $proposed_val = number_format(intval($data['mortality_count'])) . ' birds';
            }
    ?>
    <div class="card" style="margin-bottom:1.4rem; border-left:4px solid var(--terra-lt); padding:1.4rem 1.6rem;">

        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
            <div>
                <span class="badge badge-pending" style="margin-bottom:8px; display:inline-block;">
                    <?php echo strtoupper($r['record_type']); ?> #<?php echo $r['record_id']; ?>
                </span>
                <div style="font-weight:700; font-size:0.95rem; color:var(--text-primary);">
                    Staff: <?php echo htmlspecialchars($r['username']); ?>
                </div>
                <div style="color:var(--text-secondary); font-size:0.85rem; margin-top:4px;">
                    <strong style="color:var(--text-muted);">Reason:</strong>
                    "<?php echo htmlspecialchars($r['reason']); ?>"
                </div>
            </div>
            <div style="text-align:right; font-size:0.78rem; color:var(--text-muted);">
                Submitted: <?php echo date('M d, Y — g:i A', strtotime($r['created_at'])); ?>
            </div>
        </div>

        <!-- Comparison: Original vs Proposed -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:1.2rem;">
            <div style="background:var(--bg-wood); padding:12px 16px; border-radius:var(--radius-sm);
                        border:1px solid var(--border-subtle);">
                <div style="font-size:0.7rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.6px; margin-bottom:5px;">
                    Current Value
                </div>
                <div style="font-size:1.1rem; font-weight:700; color:var(--text-secondary);">
                    <?php echo $original_val; ?>
                </div>
            </div>
            <div style="background:var(--bg-wood); padding:12px 16px; border-radius:var(--radius-sm);
                        border:1px solid var(--border-mid);">
                <div style="font-size:0.7rem; font-weight:700; color:var(--gold-muted); text-transform:uppercase; letter-spacing:0.6px; margin-bottom:5px;">
                    Proposed Change
                </div>
                <div style="font-size:1.1rem; font-weight:700; color:var(--gold);">
                    <?php echo $proposed_val; ?>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px;">
            <form method="POST" style="flex:1;"
                  onsubmit="return confirm('Approve this change and update the official record?')">
                <input type="hidden" name="req_id" value="<?php echo $r['request_id']; ?>">
                <input type="hidden" name="action"  value="approve">
                <button type="submit" class="btn-farm btn-green btn-full">✅ Approve & Update</button>
            </form>
            <form method="POST" style="flex:1;"
                  onsubmit="return confirm('Reject this request?')">
                <input type="hidden" name="req_id" value="<?php echo $r['request_id']; ?>">
                <input type="hidden" name="action"  value="reject">
                <button type="submit" class="btn-farm btn-danger btn-full">❌ Reject</button>
            </form>
        </div>

    </div>
    <?php endwhile;
    else: ?>
    <div class="card">
        <div class="empty-state">
            <span class="empty-icon">🥚</span>
            <p>No pending requests.</p>
            <small>All staff log data is up to date!</small>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include('../../includes/footer.php'); ?>