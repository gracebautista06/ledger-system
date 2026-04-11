<?php

$page_title = 'Manage Staff';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');
include('../../includes/log_activity.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

$message = "";

// ── HANDLE POST ACTIONS ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = intval($_POST['user_id'] ?? 0);

    if ($action === 'reset' && $id > 0) {
        $default_pass = password_hash("Farm1234", PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$default_pass' WHERE user_id=$id AND role='Staff'");
        $message = "<div class='alert success'>🔑 Password reset to <strong>Farm1234</strong> for Staff ID #$id.</div>";

    } elseif ($action === 'delete' && $id > 0) {
        if ($id === (int)$_SESSION['user_id']) {
            $message = "<div class='alert error'>⚠️ You cannot delete your own account.</div>";
        } else {
            $conn->query("DELETE FROM users WHERE user_id=$id AND role='Staff'");
            $message = "<div class='alert warning'>🗑️ Staff account #$id has been removed.</div>";
        }

    } elseif ($action === 'add_staff') {
        $new_uname = trim($_POST['new_username'] ?? '');
        $new_pw    = $_POST['new_password'] ?? '';
        $errors    = [];

        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $new_uname)) {
            $errors[] = "Username must be 3–20 chars (letters, numbers, underscores).";
        }
        if (strlen($new_pw) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        }

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT user_id FROM users WHERE username=? LIMIT 1");
            $chk->bind_param("s", $new_uname);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) $errors[] = "That username is already taken.";
            $chk->close();
        }

        if (empty($errors)) {
            $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
            $role_s = 'Staff';
            $stmt   = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?,?,?)");
            $stmt->bind_param("sss", $new_uname, $hashed, $role_s);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>✅ Staff account <strong>" . htmlspecialchars($new_uname) . "</strong> created successfully.</div>";
            } else {
                $message = "<div class='alert error'>Database error: " . htmlspecialchars($conn->error) . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert error'>⚠️ " . implode("<br>⚠️ ", array_map('htmlspecialchars', $errors)) . "</div>";
        }
    }
}
// ── FETCH ALL STAFF ───────────────────────────────────────────
// Simplified query — now includes is_online for real-time status
$search = trim($_GET['q'] ?? '');
$where  = "WHERE role = 'Staff'";

if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND username LIKE '%$safe_search%'";
}

// ✅ CHANGED: added `is_online` column
$result = $conn->query("
    SELECT user_id, username, role, created_at, last_seen, is_online 
    FROM users 
    $where 
    ORDER BY username ASC
");

$staff_count = $result ? $result->num_rows : 0;


// ── Helper: how many staff are online right now ───────────────

// ❌ OLD: based on last_seen time
// ✅ NEW: based on real is_online status
$online_q = $conn->query("
    SELECT COUNT(*) AS n 
    FROM users 
    WHERE role='Staff' AND is_online = 1
");

$online_count = $online_q ? (int)$online_q->fetch_assoc()['n'] : 0;


// ── Helper function: format status into human-readable UI ──

// ✅ CHANGED: added $is_online parameter
function staff_status_html(?string $last_seen, int $is_online): string {

    // ✅ OPTIONAL SAFETY: auto-offline if inactive (prevents stuck online users)
    if ($is_online === 1 && $last_seen && (time() - strtotime($last_seen)) > 120) {
        $is_online = 0;
    }

    // ── Case 1: REAL ONLINE STATUS ──
    // ✅ CHANGED: now uses is_online instead of time diff
    if ($is_online === 1) {
    $diff = $last_seen ? time() - strtotime($last_seen) : 0;

    if ($diff < 60) {
        $label = "Active now";
    } else {
        $label = "Idle";
    }

    return '
        <div style="display:flex; align-items:center; gap:7px;">
            <span style="width:9px; height:9px; border-radius:50%;
                         background:var(--success);
                         animation:pulse-online 2s infinite;"></span>
            <span style="font-size:0.82rem; font-weight:700; color:var(--success);">
                ' . $label . '
            </span>
        </div>';
}

    // ── Case 2: Never Seen ──
    if (!$last_seen) {
        return '<span style="font-size:0.78rem; color:var(--text-muted); font-style:italic;">Never seen</span>';
    }

    // ── Case 3: Offline — show last seen ──
    $diff = time() - strtotime($last_seen);

    if ($diff < 3600) {
        $mins = max(1, (int)floor($diff / 60));
        $ago  = $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hrs  = (int)floor($diff / 3600);
        $ago  = $hrs . ' hr' . ($hrs > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400 * 7) {
        $days = (int)floor($diff / 86400);
        $ago  = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        $ago = date('M d, Y', strtotime($last_seen));
    }

    return '
        <div style="display:flex; align-items:center; gap:7px;">
            <span style="width:9px; height:9px; border-radius:50%;
                         background:var(--text-muted); flex-shrink:0;"></span>
            <span style="font-size:0.8rem; color:var(--text-muted);">' . htmlspecialchars($ago) . '</span>
        </div>';
}
?>

<!-- Pulse animation for Online dot -->
<style>
@keyframes pulse-online {
    0%   { box-shadow: 0 0 0 0 rgba(78,155,91,0.7); }
    70%  { box-shadow: 0 0 0 6px rgba(78,155,91,0); }
    100% { box-shadow: 0 0 0 0 rgba(78,155,91,0); }
}
</style>

<div style="max-width:980px; margin:2rem auto;">

    <!-- Page header -->
    <div class="page-header">
        <div>
            <h2>👥 Staff Management</h2>
            <p>
                <?php echo $staff_count; ?> staff member<?php echo $staff_count !== 1 ? 's' : ''; ?>
                <?php echo $search ? "matching <em>\"" . htmlspecialchars($search) . "\"</em>" : 'registered'; ?>.

                <?php if ($online_count > 0): ?>
                    &nbsp;·&nbsp;
                    <span style="color:var(--success); font-weight:600;">
                        <?php echo $online_count; ?> online now
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <!-- Search -->
            <form method="GET" style="display:flex; gap:8px; align-items:center;">
                <input type="text" name="q" class="form-input" placeholder="Search username…"
                       value="<?php echo htmlspecialchars($search); ?>"
                       style="width:190px; padding:8px 12px; font-size:0.85rem;">
                <button type="submit" class="btn-farm btn-sm">🔍</button>
                <?php if ($search): ?>
                    <a href="users.php" class="btn-farm btn-dark btn-sm">✕</a>
                <?php endif; ?>
            </form>
            <button class="btn-farm btn-orange btn-sm" onclick="toggleAddPanel()">+ Add Staff</button>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- ── ADD STAFF PANEL ─────────────────────────────────── -->
    <div id="add-panel" style="display:none; margin-bottom:1.5rem;">
        <div class="card" style="border:1px solid var(--border-mid); border-top:3px solid var(--terra-lt); padding:1.4rem 1.8rem;">
            <h4 style="color:var(--gold); margin-bottom:1.2rem; font-family:'Playfair Display',serif;">➕ Add New Staff Account</h4>
            <form method="POST" style="display:grid; grid-template-columns:1fr 1fr auto; gap:14px; align-items:flex-end;">
                <input type="hidden" name="action" value="add_staff">
                <div class="form-group" style="margin:0;">
                    <label>Username</label>
                    <input type="text" name="new_username" class="form-input" placeholder="3–20 chars" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Password</label>
                    <input type="password" name="new_password" class="form-input" placeholder="Min 8 characters" required>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn-farm btn-green" style="white-space:nowrap;">Create ✅</button>
                    <button type="button" class="btn-farm btn-dark" onclick="toggleAddPanel()">Cancel</button>
                </div>
            </form>
            <p style="font-size:0.75rem; color:var(--text-muted); margin-top:10px;">
                💡 Staff will log in with these credentials. Default role is always <strong>Staff</strong>.
            </p>
        </div>
    </div>

    <!-- ── STAFF TABLE ────────────────────────────────────── -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Registered</th>
                        <th>Status</th><!-- NEW: replaces Last Login -->
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($staff_count > 0):
                        while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="color:var(--text-muted); font-size:0.8rem;">#<?php echo $row['user_id']; ?></td>
                        <td>
                            <span style="font-weight:600; color:var(--text-primary);">
                                <?php echo htmlspecialchars($row['username']); ?>
                            </span>
                        </td>
                        <td><span class="badge badge-staff"><?php echo $row['role']; ?></span></td>
                        <td style="font-size:0.8rem; color:var(--text-muted);">
                            <?php echo $row['created_at'] ? date('M d, Y', strtotime($row['created_at'])) : '—'; ?>
                        </td>
                        <!-- Status column -->
                        <td id="status-<?php echo $row['user_id']; ?>">
                            <?php echo staff_status_html($row['last_seen'], (int)$row['is_online']); ?>
                        </td>       
                        <td style="text-align:center; white-space:nowrap;">
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Reset password to Farm1234 for <?php echo htmlspecialchars($row['username']); ?>?')">
                                <input type="hidden" name="action"  value="reset">
                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                <button type="submit" class="btn-farm btn-outline btn-sm"
                                        style="margin-right:6px; color:var(--terra-lt); border-color:var(--terra-lt);">
                                    🔑 Reset
                                </button>
                            </form>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Permanently delete <?php echo htmlspecialchars($row['username']); ?>? This cannot be undone.')">
                                <input type="hidden" name="action"  value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                <button type="submit" class="btn-farm btn-danger btn-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile;
                    else: ?>
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <span class="empty-icon">👤</span>
                            <p>No staff members found<?php echo $search ? ' matching your search' : ''; ?>.</p>
                            <small>Click "+ Add Staff" to create a new account.</small>
                        </div>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div style="padding:10px 16px; border-top:1px solid var(--border-subtle);
                    display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
            <span style="font-size:0.72rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                Status legend:
            </span>
            <span style="display:flex; align-items:center; gap:6px; font-size:0.78rem; color:var(--text-secondary);">
                <span style="width:8px; height:8px; border-radius:50%; background:var(--success); display:inline-block;"></span>
                Online — currently logged in
            </span>
            <span style="display:flex; align-items:center; gap:6px; font-size:0.78rem; color:var(--text-secondary);">
                <span style="width:8px; height:8px; border-radius:50%; background:var(--text-muted); display:inline-block;"></span>
                Offline — time since last activity shown
            </span>
        </div>
    </div>

    <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
</div>
<script> 
function toggleAddPanel() { 
    const p = document.getElementById('add-panel'); 
    p.style.display = p.style.display === 'none' ? 'block' : 'none'; 
    if (p.style.display === 'block') { 
        p.querySelector('input[name="new_username"]').focus();
    } 
} 
<?php if (strpos($message, 'alert error') !== false && isset($_POST['action']) && $_POST['action'] === 'add_staff'): ?> 
    document.addEventListener('DOMContentLoaded', () => toggleAddPanel()); 
<?php endif; ?> 
// Auto-refresh every 30 seconds so status stays current without manual reload 
setTimeout(() => location.reload(), 30000); </script>
<?php include('../../includes/footer.php'); ?>