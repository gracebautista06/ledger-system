<?php
/*  staff/log_sale.php — Record a New Egg Sale (Per-Size Breakdown)
 *
 *  CHANGES FROM PREVIOUS VERSION:
 *  - Staff now inputs trays per egg size instead of one bulk total
 *  - Prices are auto-loaded from the egg_prices table (set by Owner)
 *  - Each row auto-calculates subtotal; grand total sums everything
 *  - quantity_sold (total trays) is server-computed from size inputs
 *  - total_amount is server-computed; client total is display-only
 *  - Per-size quantities saved into qty_pw/qty_s/.../qty_j columns
 *    (requires running migration_sales_sizes.sql first)
 *  - Old stock notification check preserved and improved
 *
 *  DB COLUMNS NEEDED (run migration_sales_sizes.sql if not done):
 *    sales.qty_pw, qty_s, qty_m, qty_l, qty_xl, qty_j
 */

$page_title = 'Record Sale';

include('../includes/db.php');
include('../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Staff') {
    header("Location: ../portal/login.php"); exit();
}

$staff_id = (int) $_SESSION['user_id'];
$message  = "";

// ── Load prices set by Owner ──────────────────────────────────
// Keyed by size_code (PW, S, M, L, XL, J) → price_per_tray
$prices      = [];
$prices_q    = $conn->query("SELECT size_code, price_per_tray, price_per_piece FROM egg_prices");
if ($prices_q) {
    while ($row = $prices_q->fetch_assoc()) {
        $prices[$row['size_code']] = [
            'tray'  => (float) $row['price_per_tray'],
            'piece' => (float) $row['price_per_piece'],
        ];
    }
}

// ── Size definitions (display order) ─────────────────────────
$size_defs = [
    'PW' => 'Peewee',
    'S'  => 'Small',
    'M'  => 'Medium',
    'L'  => 'Large',
    'XL' => 'Extra Large',
    'J'  => 'Jumbo',
];
$size_colors = [
    'PW' => '#adb5bd', 'S' => '#74c0fc', 'M' => '#51cf66',
    'L'  => '#fcc419', 'XL'=> '#ff922b', 'J' => '#f03e3e',
];

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customer       = trim($_POST['customer_name'] ?? '');
    $payment_method = in_array($_POST['payment_method'] ?? '', ['Cash','GCash','Bank Transfer'])
                      ? $_POST['payment_method'] : 'Cash';
    $notes          = trim($_POST['notes'] ?? '');

    // Read per-size quantities (trays) — always integers, min 0
    $qty = [];
    foreach ($size_defs as $code => $_) {
        $qty[$code] = max(0, (int)($_POST['qty_' . strtolower($code)] ?? 0));
    }

    // Server-computed totals (never trust client-side total)
    $total_trays  = array_sum($qty);
    $total_amount = 0.0;
    foreach ($size_defs as $code => $_) {
        $total_amount += $qty[$code] * ($prices[$code]['tray'] ?? 0);
    }
    $total_amount = round($total_amount, 2);

    // Use average unit_price for backwards compat with reports
    $unit_price = $total_trays > 0 ? round($total_amount / $total_trays, 2) : 0;

    // Validation
    if (empty($customer)) {
        $message = "<div class='alert error'>⚠️ Please enter the customer name.</div>";
    } elseif ($total_trays <= 0) {
        $message = "<div class='alert error'>⚠️ Please enter at least one tray quantity.</div>";
    } elseif ($total_amount <= 0 && !empty($prices)) {
        $message = "<div class='alert error'>⚠️ Total amount is zero. Make sure prices are set by the Owner first.</div>";
    } else {
        // Insert with per-size columns
        $ins = $conn->prepare("
            INSERT INTO sales
                (staff_id, customer_name, quantity_sold, unit_price, total_amount,
                 payment_method, notes, qty_pw, qty_s, qty_m, qty_l, qty_xl, qty_j)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        // FIX: type string corrected — unit_price=d, total_amount=d, payment_method=s, notes=s
        $ins->bind_param(
            "isiddssiiiiii",
            $staff_id, $customer, $total_trays, $unit_price, $total_amount,
            $payment_method, $notes,
            $qty['PW'], $qty['S'], $qty['M'], $qty['L'], $qty['XL'], $qty['J']
        );

        if ($ins->execute()) {
            $ins->close();

            // ── Old-stock notification check (unchanged logic) ──
            $notif_q = $conn->query("
                SELECT n.*, b.arrival_date
                FROM notifications n
                JOIN batches b ON n.batch_id = b.batch_id
                WHERE n.status IN ('unread','read')
                ORDER BY n.created_at DESC LIMIT 1
            ");
            if ($notif_q && $notif_q->num_rows > 0) {
                $notif    = $notif_q->fetch_assoc();
                $notif_id = (int)$notif['notif_id'];
                $bid      = (int)$notif['batch_id'];

                $h_stmt = $conn->prepare("SELECT COALESCE(SUM(total_eggs),0) AS total FROM harvests WHERE batch_id=?");
                $h_stmt->bind_param("i", $bid);
                $h_stmt->execute();
                $harvested = (int)$h_stmt->get_result()->fetch_assoc()['total'];
                $h_stmt->close();

                $s_stmt = $conn->prepare("
                    SELECT COALESCE(SUM(quantity_sold*30),0) AS total FROM sales
                    WHERE date_sold >= (SELECT COALESCE(arrival_date,'2000-01-01') FROM batches WHERE batch_id=?)
                ");
                $s_stmt->bind_param("i", $bid);
                $s_stmt->execute();
                $sold = (int)$s_stmt->get_result()->fetch_assoc()['total'];
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

// Repopulate quantities after failed submit
$post_qty = [];
foreach ($size_defs as $code => $_) {
    $post_qty[$code] = isset($_POST['qty_' . strtolower($code)])
                       ? max(0, (int)$_POST['qty_' . strtolower($code)])
                       : 0;
}

$prices_set = !empty($prices);
?>

<div class="card" style="max-width:700px; margin:2rem auto; border-top:5px solid var(--success);">

    <h2 style="color:var(--gold); font-family:'Playfair Display',serif; margin-bottom:0.3rem;">
        💰 Record New Sale
    </h2>
    <p style="color:var(--text-muted); margin-bottom:1.6rem; font-size:0.88rem;">
        Enter the number of trays per egg size. Prices are loaded from Owner settings.
    </p>

    <?php echo $message; ?>

    <?php if (!$prices_set): ?>
    <div class="alert warning" style="margin-bottom:1.5rem;">
        ⚠️ No egg prices have been set yet. Ask the Owner to set prices in
        <strong>Pricing Settings</strong> before logging a sale.
    </div>
    <?php endif; ?>

    <form method="POST" id="saleForm">

        <!-- Customer & payment info -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:1.2rem;">
            <div class="form-group" style="margin:0;">
                <label>Customer Name <span style="color:var(--danger);">*</span></label>
                <input type="text" name="customer_name" class="form-input"
                       placeholder="Customer or business name" required
                       value="<?php echo isset($_POST['customer_name']) ? htmlspecialchars(trim($_POST['customer_name'])) : ''; ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Payment Method</label>
                <select name="payment_method" class="form-input">
                    <option value="Cash">💵 Cash</option>
                    <option value="GCash">📱 GCash</option>
                    <option value="Bank Transfer">🏦 Bank Transfer</option>
                </select>
            </div>
        </div>

        <!-- Per-size table -->
        <div style="margin-bottom:1.4rem;">
            <div style="font-size:0.7rem; font-weight:700; color:var(--text-muted);
                        text-transform:uppercase; letter-spacing:0.8px; margin-bottom:10px;">
                Egg Size Breakdown
            </div>

            <div style="background:var(--bg-wood); border-radius:var(--radius);
                        border:1px solid var(--border-subtle); overflow:hidden;">

                <!-- Table header -->
                <div style="display:grid; grid-template-columns:140px 1fr 110px 110px;
                            background:var(--bg-plank); padding:10px 14px;
                            font-size:0.7rem; font-weight:700; color:var(--gold-muted);
                            text-transform:uppercase; letter-spacing:0.6px; gap:10px;">
                    <span>Size</span>
                    <span>Trays to Sell</span>
                    <span style="text-align:right;">Price / Tray</span>
                    <span style="text-align:right;">Subtotal</span>
                </div>

                <!-- Size rows -->
                <?php foreach ($size_defs as $code => $label):
                    $price_tray = $prices[$code]['tray'] ?? 0;
                    $field_name = 'qty_' . strtolower($code);
                    $cur_qty    = $post_qty[$code];
                    $subtotal   = $cur_qty * $price_tray;
                ?>
                <div class="size-row"
                     style="display:grid; grid-template-columns:140px 1fr 110px 110px;
                            padding:12px 14px; gap:10px; align-items:center;
                            border-top:1px solid var(--border-subtle);"
                     data-code="<?php echo $code; ?>"
                     data-price="<?php echo $price_tray; ?>">

                    <!-- Size label with color dot -->
                    <div style="display:flex; align-items:center; gap:9px;">
                        <span style="width:10px; height:10px; border-radius:50%; flex-shrink:0;
                                     background:<?php echo $size_colors[$code]; ?>;
                                     box-shadow:0 0 5px <?php echo $size_colors[$code]; ?>88;
                                     display:inline-block;"></span>
                        <div>
                            <div style="font-weight:700; font-size:0.88rem; color:var(--text-primary);">
                                <?php echo $label; ?>
                            </div>
                            <div style="font-size:0.68rem; color:var(--text-muted); font-weight:700;">
                                <?php echo $code; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quantity input -->
                    <input type="number"
                           name="<?php echo $field_name; ?>"
                           class="form-input size-qty"
                           min="0" value="<?php echo $cur_qty; ?>"
                           placeholder="0"
                           style="padding:10px 12px; font-size:0.95rem; font-weight:600;"
                           oninput="recalc()">

                    <!-- Price per tray (read-only display) -->
                    <div style="text-align:right; font-size:0.88rem;">
                        <?php if ($price_tray > 0): ?>
                            <span style="color:var(--text-secondary);">
                                ₱<?php echo number_format($price_tray, 2); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-muted); font-style:italic;">Not set</span>
                        <?php endif; ?>
                    </div>

                    <!-- Subtotal (JS-computed) -->
                    <div style="text-align:right; font-weight:700; font-size:0.92rem;"
                         id="sub_<?php echo $code; ?>">
                        <?php echo $subtotal > 0 ? '₱' . number_format($subtotal, 2) : '—'; ?>
                    </div>

                </div>
                <?php endforeach; ?>

                <!-- Grand total row -->
                <div style="display:grid; grid-template-columns:140px 1fr 110px 110px;
                            padding:14px 14px; gap:10px; align-items:center;
                            background:var(--bg-plank); border-top:2px solid var(--border-mid);">
                    <div style="font-size:0.72rem; font-weight:700; color:var(--text-muted);
                                text-transform:uppercase; letter-spacing:0.6px;">
                        TOTAL
                    </div>
                    <div id="total_trays_display"
                         style="font-size:1rem; font-weight:700; color:var(--text-secondary);">
                        0 trays
                    </div>
                    <div></div>
                    <div id="grand_total_display"
                         style="text-align:right; font-size:1.2rem; font-weight:800;
                                color:var(--gold); font-family:'Playfair Display',serif;">
                        ₱ 0.00
                    </div>
                </div>

            </div>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-input" rows="2"
                      placeholder="Optional: bulk order, regular customer, delivery notes…"></textarea>
        </div>

        <button type="submit" class="btn-farm btn-green btn-full" style="padding:15px; font-size:1rem;">
            Record Sale ✅
        </button>
        <a href="dashboard.php" id="backBtn" class="back-link"
           style="display:block; text-align:center; margin-top:1rem;">
            ← Back to Dashboard
        </a>

    </form>
</div>

<script>
// Prices from PHP — used for JS live calculation
const PRICES = <?php echo json_encode(array_map(fn($p) => $p['tray'], $prices)); ?>;

function recalc() {
    let totalTrays  = 0;
    let grandTotal  = 0;

    document.querySelectorAll('.size-row').forEach(row => {
        const code  = row.dataset.code;
        const price = parseFloat(row.dataset.price) || 0;
        const qty   = parseInt(row.querySelector('.size-qty').value) || 0;
        const sub   = qty * price;

        totalTrays += qty;
        grandTotal += sub;

        const subEl = document.getElementById('sub_' + code);
        subEl.textContent = sub > 0 ? '₱' + sub.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '—';
        subEl.style.color = sub > 0 ? 'var(--gold)' : 'var(--text-muted)';
    });

    document.getElementById('total_trays_display').textContent =
        totalTrays + ' tray' + (totalTrays !== 1 ? 's' : '');
    document.getElementById('grand_total_display').textContent =
        '₱ ' + grandTotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Dirty-state guard
let isDirty = false;
const saleForm = document.getElementById('saleForm');
saleForm.addEventListener('input',  () => isDirty = true);
saleForm.addEventListener('submit', () => isDirty = false);
window.addEventListener('beforeunload', e => {
    if (isDirty) { e.preventDefault(); e.returnValue = ''; }
});
document.getElementById('backBtn').addEventListener('click', e => {
    if (isDirty && !confirm("Discard unsaved sale data?")) e.preventDefault();
});

// Run on load to populate from any repopulated values
recalc();
</script>

<?php include('../includes/footer.php'); ?>