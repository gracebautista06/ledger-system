<?php
/* ============================================================
   owner/manage_prices.php — Egg Unit Pricing Management

   IMPROVEMENTS v2:
   - Fixed old inline styles (#fafafa, #eee, #555) → dark theme vars
   - Fixed --accent-orange, --info-blue → --terra-lt, --info
   - Added color-coded size dots matching inventory page
   - Added "Last Updated" timestamp display per size
   - Added live per-piece preview that updates as you type
   ============================================================ */

$page_title = 'Manage Prices';

session_start();
include('../../includes/db.php');
include('../../includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../../portal/login.php");
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed_sizes = ['PW', 'S', 'M', 'L', 'XL', 'J'];
    $updated = 0;

    foreach ($allowed_sizes as $size) {
        if (isset($_POST["price_$size"])) {
            $price_tray  = max(0, floatval($_POST["price_$size"]));
            $price_piece = round($price_tray / 30, 4);

            $check = $conn->query("SELECT price_id FROM egg_prices WHERE size_code='$size' LIMIT 1");
            if ($check && $check->num_rows > 0) {
                $conn->query("UPDATE egg_prices SET price_per_tray=$price_tray, price_per_piece=$price_piece WHERE size_code='$size'");
            } else {
                $conn->query("INSERT INTO egg_prices (size_code, price_per_tray, price_per_piece) VALUES ('$size',$price_tray,$price_piece)");
            }
            $updated++;
        }
    }

    if ($updated > 0) {
        $message = "<div class='alert success'>✅ Prices saved for $updated egg sizes.</div>";
    }
}

$prices_query   = $conn->query("SELECT * FROM egg_prices ORDER BY FIELD(size_code,'PW','S','M','L','XL','J')");
$current_prices = [];
if ($prices_query) {
    while ($row = $prices_query->fetch_assoc()) {
        $current_prices[$row['size_code']] = $row;
    }
}

$size_meta = [
    'PW' => ['label' => 'Peewee',      'color' => '#adb5bd'],
    'S'  => ['label' => 'Small',        'color' => '#74c0fc'],
    'M'  => ['label' => 'Medium',       'color' => '#51cf66'],
    'L'  => ['label' => 'Large',        'color' => '#fcc419'],
    'XL' => ['label' => 'Extra Large',  'color' => '#ff922b'],
    'J'  => ['label' => 'Jumbo',        'color' => '#f03e3e'],
];
?>

<div style="max-width:680px; margin:2rem auto;">

    <div class="page-header">
        <div>
            <h2>🏷️ Egg Unit Pricing</h2>
            <p>Set the selling price per tray (30 eggs) for each size.</p>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card" style="padding:1.6rem 2rem;">
        <form method="POST" id="price-form">

            <div style="display:grid; gap:12px;">
                <?php foreach ($size_meta as $code => $meta):
                    $current_tray  = isset($current_prices[$code]) ? (float)$current_prices[$code]['price_per_tray']  : 0;
                    $current_piece = isset($current_prices[$code]) ? (float)$current_prices[$code]['price_per_piece'] : 0;
                    $updated_time  = isset($current_prices[$code]) ? $current_prices[$code]['updated_at'] : null;
                ?>
                <div style="display:flex; align-items:center; gap:14px; padding:14px 16px;
                            background:var(--bg-wood); border-radius:var(--radius);
                            border:1px solid var(--border-subtle);">

                    <!-- Color dot + label -->
                    <div style="display:flex; align-items:center; gap:10px; width:130px; flex-shrink:0;">
                        <span style="width:12px; height:12px; border-radius:50%;
                                     background:<?php echo $meta['color']; ?>;
                                     box-shadow:0 0 6px <?php echo $meta['color']; ?>66;
                                     flex-shrink:0; display:inline-block;"></span>
                        <div>
                            <div style="font-weight:700; font-size:0.9rem; color:var(--text-primary);"><?php echo $meta['label']; ?></div>
                            <div style="font-size:0.7rem; color:var(--text-muted); font-weight:700;"><?php echo $code; ?></div>
                        </div>
                    </div>

                    <!-- Price input -->
                    <div style="flex:1;">
                        <label style="font-size:0.7rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.6px; display:block; margin-bottom:5px;">
                            Per Tray (₱)
                        </label>
                        <input type="number" name="price_<?php echo $code; ?>"
                               id="price_<?php echo $code; ?>"
                               class="form-input" style="margin:0;"
                               step="0.01" min="0" placeholder="0.00"
                               value="<?php echo $current_tray > 0 ? number_format($current_tray, 2, '.', '') : ''; ?>"
                               oninput="updatePiece('<?php echo $code; ?>')">
                    </div>

                    <!-- Per-piece preview -->
                    <div style="text-align:right; min-width:110px; flex-shrink:0;">
                        <div style="font-size:0.68rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">
                            Per Piece
                        </div>
                        <div id="piece_<?php echo $code; ?>" style="font-size:0.92rem; font-weight:700; color:var(--gold);">
                            <?php echo $current_piece > 0 ? '≈ ₱' . number_format($current_piece, 4) : '—'; ?>
                        </div>
                        <?php if ($updated_time): ?>
                        <div style="font-size:0.68rem; color:var(--text-muted); margin-top:3px;">
                            <?php echo date('M d, Y', strtotime($updated_time)); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <!-- Info note -->
            <div style="margin-top:1.4rem; background:var(--info-bg); padding:12px 16px;
                        border-radius:var(--radius-sm); font-size:0.83rem; color:#6AABDE;
                        border-left:4px solid var(--info);">
                💡 <strong>Note:</strong> Per-piece price is calculated as tray price ÷ 30. This updates live as you type.
            </div>

            <button type="submit" class="btn-farm btn-orange btn-full" style="padding:14px; margin-top:1.4rem; font-size:1rem;">
                💾 Save All Prices
            </button>
        </form>
    </div>

    <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<script>
function updatePiece(code) {
    const tray = parseFloat(document.getElementById('price_' + code).value) || 0;
    const el   = document.getElementById('piece_' + code);
    el.textContent = tray > 0 ? '≈ ₱' + (tray / 30).toFixed(4) : '—';
}
</script>

<?php include('../../includes/footer.php'); ?>