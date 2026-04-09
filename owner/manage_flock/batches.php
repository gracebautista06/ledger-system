<?php
/* ============================================================
   owner/manage_batches.php — Flock Batch Management

   IMPROVEMENTS v2:
   - Fixed old CSS vars: --barn-red → --gold, --dark-nest → --bg-plank
   - Added expected_replacement date field back (was removed in previous version)
   - Replacement countdown badge — shows "X days left" / "Overdue!" in red
   - Fixed inline styles that used light (#fafafa, #ddd) backgrounds
   - Improved "Add New Batch" form layout
   ============================================================ */

$page_title = 'Manage Batches';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $breed       = $conn->real_escape_string(trim($_POST['breed'] ?? ''));
        $quantity    = max(1, intval($_POST['quantity']));
        $acquired    = $conn->real_escape_string($_POST['date_acquired']  ?? '');
        $replacement = $conn->real_escape_string($_POST['expected_replacement'] ?? '');
        $notes       = $conn->real_escape_string(trim($_POST['notes'] ?? ''));
        $repl_sql    = $replacement ? "'$replacement'" : 'NULL';

        if (empty($breed)) {
            $message = "<div class='alert error'>⚠️ Breed name is required.</div>";
        } else {
            // Try both column name variants (initial_count OR quantity)
            $sql = "INSERT INTO batches (breed, initial_count, status, date_acquired, expected_replacement, notes)
                    VALUES ('$breed', $quantity, 'Active', '$acquired', $repl_sql, '$notes')";
            if ($conn->query($sql)) {
                $message = "<div class='alert success'>✅ Batch added successfully.</div>";
            } else {
                // Fallback: try 'quantity' column name
                $sql2 = "INSERT INTO batches (breed, quantity, status, date_acquired, expected_replacement, notes)
                         VALUES ('$breed', $quantity, 'Active', '$acquired', $repl_sql, '$notes')";
                if ($conn->query($sql2)) {
                    $message = "<div class='alert success'>✅ Batch added successfully.</div>";
                } else {
                    $message = "<div class='alert error'>Database Error: " . htmlspecialchars($conn->error) . "</div>";
                }
            }
        }

    } elseif ($action === 'retire') {
        $id = intval($_POST['batch_id']);
        $conn->query("UPDATE batches SET status='Retired' WHERE batch_id=$id");
        $message = "<div class='alert warning'>📦 Batch #$id marked as Retired.</div>";

    } elseif ($action === 'delete') {
        $id    = intval($_POST['batch_id']);
        $check = $conn->query("SELECT status FROM batches WHERE batch_id=$id LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $b = $check->fetch_assoc();
            if ($b['status'] === 'Active') {
                $message = "<div class='alert error'>⚠️ Cannot delete an Active batch. Retire it first.</div>";
            } else {
                $conn->query("DELETE FROM batches WHERE batch_id=$id");
                $message = "<div class='alert warning'>🗑️ Batch #$id deleted.</div>";
            }
        }
    }
}

// Fetch all batches — try both column variants
$batches = $conn->query("SELECT * FROM batches ORDER BY FIELD(status,'Active','Retired','Sold'), batch_id DESC");
$total   = $batches ? $batches->num_rows : 0;
?>

<div style="max-width:1040px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>📦 Flock Batch Management</h2>
            <p><?php echo $total; ?> batch<?php echo $total !== 1 ? 'es' : ''; ?> on record.</p>
        </div>
        <button class="btn-farm btn-green btn-sm" onclick="toggleAddPanel()">➕ Add New Batch</button>
    </div>

    <?php echo $message; ?>

    <!-- ── ADD BATCH PANEL ─────────────────────────────── -->
    <div id="add-panel" style="display:none; margin-bottom:1.5rem;">
        <div class="card" style="border:1px solid var(--border-mid); border-top:3px solid var(--success); padding:1.4rem 1.8rem;">
            <h4 style="color:var(--gold); margin-bottom:1.2rem; font-family:'Playfair Display',serif;">➕ Add New Batch</h4>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:14px;">
                    <div class="form-group" style="margin:0;">
                        <label>Breed / Variety *</label>
                        <input type="text" name="breed" class="form-input" placeholder="e.g., Lohmann Brown" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Number of Birds *</label>
                        <input type="number" name="quantity" class="form-input" placeholder="e.g., 500" min="1" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Date Acquired</label>
                        <input type="date" name="date_acquired" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Expected Replacement</label>
                        <input type="date" name="expected_replacement" class="form-input"
                               value="<?php echo date('Y-m-d', strtotime('+18 months')); ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-top:14px; margin-bottom:0;">
                    <label>Notes</label>
                    <input type="text" name="notes" class="form-input" placeholder="Optional notes about this batch">
                </div>
                <div style="display:flex; gap:10px; margin-top:14px;">
                    <button type="submit" class="btn-farm btn-green">Add Batch ✅</button>
                    <button type="button" class="btn-farm btn-dark" onclick="toggleAddPanel()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── BATCHES TABLE ───────────────────────────────── -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Breed</th>
                        <th>Birds</th>
                        <th>Status</th>
                        <th>Acquired</th>
                        <th>Replacement Due</th>
                        <th>Notes</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total > 0):
                        while ($row = $batches->fetch_assoc()):
                            // Handle both column name variants
                            $bird_count  = $row['initial_count'] ?? $row['quantity'] ?? 0;
                            $acq_date    = $row['arrival_date']  ?? $row['date_acquired'] ?? null;
                            $repl_date   = $row['expected_replacement'] ?? null;
                            $days_left   = $repl_date ? (int) ceil((strtotime($repl_date) - time()) / 86400) : null;
                    ?>
                    <tr>
                        <td style="color:var(--text-muted); font-size:0.8rem;">#<?php echo $row['batch_id']; ?></td>
                        <td><strong style="color:var(--text-primary);"><?php echo htmlspecialchars($row['breed']); ?></strong></td>
                        <td style="color:var(--text-secondary);"><?php echo number_format($bird_count); ?></td>
                        <td>
                            <span class="badge <?php echo $row['status'] === 'Active' ? 'badge-healthy' : 'badge-rejected'; ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                        <td style="font-size:0.82rem; color:var(--text-muted);">
                            <?php echo $acq_date ? date('M d, Y', strtotime($acq_date)) : '—'; ?>
                        </td>
                        <td style="font-size:0.82rem;">
                            <?php if ($repl_date): ?>
                                <div style="color:<?php echo ($days_left !== null && $days_left <= 30) ? 'var(--danger)' : 'var(--text-secondary)'; ?>; font-weight:600;">
                                    <?php echo date('M d, Y', strtotime($repl_date)); ?>
                                </div>
                                <?php if ($days_left !== null): ?>
                                <div style="margin-top:3px;">
                                    <?php if ($days_left <= 0): ?>
                                        <span class="badge badge-critical">Overdue!</span>
                                    <?php elseif ($days_left <= 30): ?>
                                        <span class="badge badge-warning"><?php echo $days_left; ?>d left</span>
                                    <?php else: ?>
                                        <span style="font-size:0.72rem; color:var(--text-muted);"><?php echo $days_left; ?> days</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.82rem; color:var(--text-muted); max-width:160px;">
                            <?php echo htmlspecialchars($row['notes'] ?: '—'); ?>
                        </td>
                        <td style="text-align:center; white-space:nowrap;">
                            <?php if ($row['status'] === 'Active'): ?>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Retire Batch #<?php echo $row['batch_id']; ?>?')">
                                    <input type="hidden" name="action"   value="retire">
                                    <input type="hidden" name="batch_id" value="<?php echo $row['batch_id']; ?>">
                                    <button type="submit" class="btn-farm btn-sm btn-amber">Retire</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Delete Batch #<?php echo $row['batch_id']; ?> permanently?')">
                                    <input type="hidden" name="action"   value="delete">
                                    <input type="hidden" name="batch_id" value="<?php echo $row['batch_id']; ?>">
                                    <button type="submit" class="btn-farm btn-sm btn-danger">🗑️</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile;
                    else: ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <span class="empty-icon">🐔</span>
                            <p>No batches yet.</p>
                            <small>Click "Add New Batch" above to get started.</small>
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
    if (p.style.display === 'block') p.scrollIntoView({behavior:'smooth', block:'start'});
}
</script>

<?php include('../../includes/footer.php'); ?>