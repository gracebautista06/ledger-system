<?php
/* ============================================================
   owner/manage_users.php — Staff Account Management

   IMPROVEMENTS v2:
   - Fixed old CSS vars: --dark-nest → --bg-plank, --accent-orange → --terra-lt
   - "Add New Staff" now opens an inline modal panel instead of
     redirecting to a separate register page (better UX)
   - Added inline search/filter by username
   - Shows "last login" column (reads from activity_logs if available)
   - POST-based reset/delete actions preserved from v1
   ============================================================ */

$page_title = 'Manage Staff';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

$message = "";

// --- HANDLE POST ACTIONS ---
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
        // Inline add staff (replaces add_user.php redirect)
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
            // Check duplicate
            $chk = $conn->prepare("SELECT user_id FROM users WHERE username=? LIMIT 1");
            $chk->bind_param("s", $new_uname);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $errors[] = "That username is already taken.";
            }
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

// --- FETCH ALL STAFF (with optional last-login from activity_logs) ---
$search = trim($_GET['q'] ?? '');
$where  = "WHERE u.role = 'Staff'";
if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND u.username LIKE '%$safe_search%'";
}

$result = $conn->query("
    SELECT u.user_id, u.username, u.role, u.created_at,
           MAX(al.timestamp) AS last_login
    FROM users u
    LEFT JOIN activity_logs al ON al.user_id = u.user_id AND al.action_type = 'LOGIN'
    $where
    GROUP BY u.user_id
    ORDER BY u.username ASC
");
$staff_count = $result ? $result->num_rows : 0;
?>

<div style="max-width:980px; margin:2rem auto;">

    <!-- Page header -->
    <div class="page-header">
        <div>
            <h2>👥 Staff Management</h2>
            <p><?php echo $staff_count; ?> staff member<?php echo $staff_count !== 1 ? 's' : ''; ?>
               <?php echo $search ? "matching <em>\"" . htmlspecialchars($search) . "\"</em>" : 'registered'; ?>.</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <!-- Inline search -->
            <form method="GET" style="display:flex; gap:8px; align-items:center;">
                <input type="text" name="q" class="form-input" placeholder="Search username…"
                       value="<?php echo htmlspecialchars($search); ?>"
                       style="width:190px; padding:8px 12px; font-size:0.85rem;">
                <button type="submit" class="btn-farm btn-sm">🔍</button>
                <?php if ($search): ?>
                    <a href="users.php" class="btn-farm btn-dark btn-sm">✕</a>
                <?php endif; ?>
            </form>
            <!-- Toggle add panel -->
            <button class="btn-farm btn-orange btn-sm" onclick="toggleAddPanel()">+ Add Staff</button>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- ── INLINE ADD STAFF PANEL ─────────────────────────── -->
    <div id="add-panel" style="display:none; margin-bottom:1.5rem;">
        <div class="card" style="border:1px solid var(--border-mid); border-top:3px solid var(--terra-lt); padding:1.4rem 1.8rem;">
            <h4 style="color:var(--gold); margin-bottom:1.2rem; font-family:'Playfair Display',serif;">➕ Add New Staff Account</h4>
            <form method="POST" style="display:grid; grid-template-columns:1fr 1fr auto; gap:14px; align-items:flex-end;">
                <input type="hidden" name="action" value="add_staff">
                <div class="form-group" style="margin:0;">
                    <label>Username</label>
                    <input type="text" name="new_username" class="form-input"
                           placeholder="3–20 chars" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Password</label>
                    <input type="password" name="new_password" class="form-input"
                           placeholder="Min 8 characters" required>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn-farm btn-green" style="white-space:nowrap;">Create ✅</button>
                    <button type="button" class="btn-farm btn-dark" onclick="toggleAddPanel()">Cancel</button>
                </div>
            </form>
            <p style="font-size:0.75rem; color:var(--text-muted); margin-top:10px;">
                💡 Staff will log in with these credentials. They can change their password later.
                Default is always <strong>Staff</strong> role.
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
                        <th>Last Login</th>
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
                        <td style="font-size:0.8rem; color:var(--text-muted);">
                            <?php echo $row['last_login'] ? date('M d, g:i A', strtotime($row['last_login'])) : 'Never'; ?>
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
// Auto-open if there was a validation error on add_staff
<?php if (strpos($message, 'alert error') !== false && isset($_POST['action']) && $_POST['action'] === 'add_staff'): ?>
document.addEventListener('DOMContentLoaded', () => toggleAddPanel());
<?php endif; ?>
</script>

<?php include('../../includes/footer.php'); ?>