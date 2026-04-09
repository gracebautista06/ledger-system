<?php
/*  staff/log_sale.php — Record a New Egg Sale  */
$page_title = 'Record Sale';

include('../includes/db.php');
include('../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Staff') {
    header("Location: ../portal/login.php"); exit();
}

$staff_id = (int) $_SESSION['user_id'];
$message  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer       = trim($_POST['customer_name'] ?? '');
    $quantity       = max(1, (int) ($_POST['quantity_sold'] ?? 0));
    $unit_price     = max(0, (float) ($_POST['unit_price'] ?? 0));
    $total_amount   = round($quantity * $unit_price, 2); // Server-calculated; ignore posted total
    $notes          = trim($_POST['notes'] ?? '');

    $allowed_payments = ['Cash', 'GCash', 'Bank Transfer'];
    $payment_method   = in_array($_POST['payment_method'] ?? '', $allowed_payments)
                        ? $_POST['payment_method'] : 'Cash';

    if (empty($customer)) {
        $message = "<div class='alert error'>⚠️ Please enter the customer name.</div>";
    } elseif ($quantity <= 0 || $unit_price <= 0) {
        $message = "<div class='alert error'>⚠️ Quantity and unit price must be greater than zero.</div>";
    } else {
        // FIX: Full prepared statement insert
        $ins = $conn->prepare("INSERT INTO sales (staff_id, customer_name, quantity_sold, unit_price, total_amount, payment_method, notes) VALUES (?,?,?,?,?,?,?)");
        $ins->bind_param("isiddss", $staff_id, $customer, $quantity, $unit_price, $total_amount, $payment_method, $notes);

        if ($ins->execute()) {
            $ins->close();

            // FIX: Old-stock check — scope sales by batch_id properly
            $notif_q = $conn->query("SELECT n.*, b.arrival_date FROM notifications n JOIN batches b ON n.batch_id=b.batch_id WHERE n.status IN ('unread','read') ORDER BY n.created_at DESC LIMIT 1");
            if ($notif_q && $notif_q->num_rows > 0) {
                $notif    = $notif_q->fetch_assoc();
                $notif_id = (int) $notif['notif_id'];
                $bid      = (int) $notif['batch_id'];

                // FIX: filter harvests AND sales by batch_id so multi-batch farms stay accurate
                $h_stmt = $conn->prepare("SELECT COALESCE(SUM(total_eggs),0) AS total FROM harvests WHERE batch_id=?");
                $h_stmt->bind_param("i", $bid);
                $h_stmt->execute();
                $harvested = (int) $h_stmt->get_result()->fetch_assoc()['total'];
                $h_stmt->close();

                $s_stmt = $conn->prepare("SELECT COALESCE(SUM(quantity_sold*30),0) AS total FROM sales WHERE staff_id IN (SELECT user_id FROM users WHERE role='Staff') AND date_sold >= (SELECT COALESCE(arrival_date,'2000-01-01') FROM batches WHERE batch_id=?)");
                $s_stmt->bind_param("i", $bid);
                $s_stmt->execute();
                $sold = (int) $s_stmt->get_result()->fetch_assoc()['total'];
                $s_stmt->close();

                if (max(0, $harvested - $sold) <= 0) {
                    $conn->query("UPDATE notifications SET status='completed', completed_at=NOW() WHERE notif_id=$notif_id");
                }
            }

            header("Location: view_logs.php?sale_saved=1"); exit();
        } else {
            $message = "<div class='alert error'>Database error. Please try again.</div>";
            $ins->close();
        }
    }
}
?>

<div class="card" style="max-width:600px; margin:2rem auto; border-top:5px solid var(--success);">
    <h2 style="color:var(--gold); font-family:'Playfair Display',serif;">💰 Record New Sale</h2>
    <p style="color:var(--text-muted); margin-bottom:2rem; font-size:0.88rem;">Log a completed egg sale and payment details.</p>

    <?php echo $message; ?>

    <form method="POST" id="saleForm">
        <div class="form-group">
            <label>Customer Name <span style="color:var(--danger);">*</span></label>
            <input type="text" name="customer_name" class="form-input"
                   placeholder="Customer or business name" required
                   value="<?php echo isset($_POST['customer_name']) ? htmlspecialchars(trim($_POST['customer_name'])) : ''; ?>">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div class="form-group">
                <label>Trays Sold <span style="color:var(--danger);">*</span></label>
                <input type="number" name="quantity_sold" id="quantity_sold" class="form-input"
                       placeholder="0" min="1" required oninput="updateTotal()"
                       value="<?php echo isset($_POST['quantity_sold']) ? (int)$_POST['quantity_sold'] : ''; ?>">
            </div>
            <div class="form-group">
                <label>Price per Tray (₱) <span style="color:var(--danger);">*</span></label>
                <input type="number" name="unit_price" id="unit_price" class="form-input"
                       placeholder="0.00" min="0" step="0.01" required oninput="updateTotal()"
                       value="<?php echo isset($_POST['unit_price']) ? floatval($_POST['unit_price']) : ''; ?>">
            </div>
        </div>

        <div class="form-group" style="background:var(--bg-plank); padding:14px; border-radius:var(--radius); text-align:center; margin-bottom:1.5rem; border:1px solid var(--border-mid);">
            <label style="color:var(--text-muted); display:block; margin-bottom:5px; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.8px;">Total Sale Amount</label>
            <div id="total_display" style="font-size:2rem; font-weight:800; color:var(--gold); font-family:'Playfair Display',serif;">₱ 0.00</div>
        </div>

        <div class="form-group">
            <label>Payment Method</label>
            <select name="payment_method" class="form-input">
                <option value="Cash">💵 Cash</option>
                <option value="GCash">📱 GCash</option>
                <option value="Bank Transfer">🏦 Bank Transfer</option>
            </select>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-input" rows="2" placeholder="Optional: bulk order, regular customer…"></textarea>
        </div>

        <button type="submit" class="btn-farm btn-green btn-full" style="padding:15px;">Record Sale ✅</button>
        <a href="dashboard.php" id="backBtn" class="back-link" style="display:block; text-align:center; margin-top:1rem;">← Back to Dashboard</a>
    </form>
</div>

<script>
let isDirty = false;
const saleForm = document.getElementById('saleForm');

function updateTotal() {
    const qty   = parseFloat(document.getElementById('quantity_sold').value) || 0;
    const price = parseFloat(document.getElementById('unit_price').value)    || 0;
    document.getElementById('total_display').textContent =
        '₱ ' + (qty * price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

saleForm.addEventListener('input', () => isDirty = true);
saleForm.addEventListener('submit', () => isDirty = false);
window.addEventListener('beforeunload', e => { if (isDirty) { e.preventDefault(); e.returnValue = ''; } });
document.getElementById('backBtn').addEventListener('click', e => {
    if (isDirty && !confirm("Discard unsaved sale data?")) e.preventDefault();
});
</script>

<?php include('../includes/footer.php'); ?>