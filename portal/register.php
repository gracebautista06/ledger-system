<?php
/* ============================================================
   portal/register.php — New User Registration
   IMPROVEMENTS:
   - Uses prepared statements (fixes SQL injection risk)
   - Server-side role validation (whitelist)
   - Username + password strength validation
   - Password confirmation field added
   - Password strength meter (JS)
   - Secret keys externalized to a constant block with note
     to move to .env in production
   - Redirect to login on success
   ============================================================ */

$page_title = 'Register';

include('../includes/db.php');
include('../includes/header.php');

// Redirect if already logged in
if (isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit();
}

// IMPROVEMENT: Define secret keys in one place.
// In production, move these to a config.php outside the web root.
define('KEY_STAFF', 'EGG_STAFF_2026');
define('KEY_OWNER', 'FARM_BOSS_99');

$errors  = [];
$success = "";
$post    = []; // Holds safe repopulation values

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username  = trim($_POST['username']  ?? '');
    $password  = $_POST['password']       ?? '';
    $password2 = $_POST['password2']      ?? '';
    $role      = $_POST['role']           ?? '';
    $input_key = $_POST['secret_key']     ?? '';

    // --- VALIDATION ---

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = "Username must be 3–20 characters: letters, numbers, or underscores only.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    // IMPROVEMENT: Confirm password match
    if ($password !== $password2) {
        $errors[] = "Passwords do not match. Please try again.";
    }

    // Whitelist roles server-side
    $allowed_roles = ['Staff', 'Owner'];
    if (!in_array($role, $allowed_roles)) {
        $errors[] = "Invalid role selected.";
    }

    // Validate secret key against role
    if (empty($errors)) {
        $authorized = ($role === 'Staff' && $input_key === KEY_STAFF)
                   || ($role === 'Owner' && $input_key === KEY_OWNER);

        if (!$authorized) {
            $errors[] = "🔒 Invalid secret key for the selected role. Access denied.";
        }
    }

    if (empty($errors)) {
        // IMPROVEMENT: Prepared statement — no manual escaping needed
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "That username is already taken. Please choose another.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed, $role);

        if ($stmt->execute()) {
            header("Location: login.php?registered=1");
            exit();
        } else {
            $errors[] = "A database error occurred. Please try again.";
        }
        $stmt->close();
    }

    // Repopulate form (never repopulate passwords)
    $post = [
        'username' => htmlspecialchars($username),
        'role'     => $role,
    ];
}
?>

<div style="display: flex; justify-content: center; padding: 2rem 1rem;">
    <div class="card" style="width: 100%; max-width: 500px; border-top: 8px solid var(--gold);">

        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 3.5rem; animation: float 3s ease-in-out infinite; display:inline-block;">📝</div>
            <h2 style="color: var(--gold); margin-top: 1rem; font-family: 'Playfair Display', serif;">Join the Farm</h2>
            <p style="color: var(--text-muted);">Create your account to start managing the ledger.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                ⚠️ <?php echo implode("<br>⚠️ ", array_map('htmlspecialchars', $errors)); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="register-form" novalidate>

            <div class="form-group">
                <label for="reg_username">Username</label>
                <input type="text" id="reg_username" name="username" class="form-input"
                       placeholder="3–20 chars, letters/numbers/underscores" required
                       value="<?php echo $post['username'] ?? ''; ?>">
            </div>

            <div class="form-group">
                <label for="reg_password">Password</label>
                <div style="position: relative;">
                    <input type="password" id="reg_password" name="password" class="form-input"
                           placeholder="Minimum 8 characters" required
                           oninput="updateStrength(this.value)"
                           style="padding-right: 44px;">
                    <button type="button" onclick="togglePassword('reg_password', 'eye1')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                                   background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:1.1rem; padding:4px;">
                        <span id="eye1">👁️</span>
                    </button>
                </div>
                <!-- IMPROVEMENT: Password strength meter -->
                <div id="strength-bar-wrap" style="margin-top:8px; display:none;">
                    <div style="height:5px; background:var(--bg-wood); border-radius:4px; overflow:hidden;">
                        <div id="strength-bar" style="height:100%; width:0%; transition:width 0.3s, background 0.3s; border-radius:4px;"></div>
                    </div>
                    <small id="strength-label" style="font-size:0.75rem; color:var(--text-muted); margin-top:4px; display:block;"></small>
                </div>
            </div>

            <div class="form-group">
                <label for="reg_password2">Confirm Password</label>
                <div style="position: relative;">
                    <input type="password" id="reg_password2" name="password2" class="form-input"
                           placeholder="Re-enter your password" required
                           style="padding-right: 44px;">
                    <button type="button" onclick="togglePassword('reg_password2', 'eye2')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                                   background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:1.1rem; padding:4px;">
                        <span id="eye2">👁️</span>
                    </button>
                </div>
                <small id="pw-match-msg" style="font-size:0.75rem; margin-top:4px; display:block;"></small>
            </div>

            <div class="form-group">
                <label for="reg_role">Your Role</label>
                <select id="reg_role" name="role" class="form-input" required>
                    <option value="" disabled <?php echo empty($post) ? 'selected' : ''; ?>>Select your position...</option>
                    <option value="Staff"  <?php echo (($post['role'] ?? '') === 'Staff')  ? 'selected' : ''; ?>>Farm Staff (Harvesting / Logging)</option>
                    <option value="Owner"  <?php echo (($post['role'] ?? '') === 'Owner')  ? 'selected' : ''; ?>>Administrator / Owner (Management)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="reg_key">Role Secret Key</label>
                <div style="position: relative;">
                    <input type="password" id="reg_key" name="secret_key" class="form-input"
                           placeholder="Key provided by management" required
                           style="padding-right: 44px;">
                    <button type="button" onclick="togglePassword('reg_key', 'eye3')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                                   background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:1.1rem; padding:4px;">
                        <span id="eye3">👁️</span>
                    </button>
                </div>
                <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 5px;">
                    💡 Contact your Farm Owner/Admin for this key.
                </small>
            </div>

            <button type="submit" class="btn-farm btn-full" style="padding: 15px; margin-top: 0.5rem;">
                Register Account ✅
            </button>
        </form>

        <div style="margin-top: 2rem; text-align: center; border-top: 1px solid var(--border-subtle); padding-top: 1.5rem;">
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                Already have an account?
                <a href="login.php" style="color: var(--gold); font-weight: 700; text-decoration: none;">Sign in instead</a>
            </p>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.textContent = input.type === 'password' ? '👁️' : '🙈';
}

function updateStrength(val) {
    const wrap  = document.getElementById('strength-bar-wrap');
    const bar   = document.getElementById('strength-bar');
    const label = document.getElementById('strength-label');
    wrap.style.display = val.length ? 'block' : 'none';

    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '20%',  color: '#C23A3A', text: 'Very weak' },
        { pct: '40%',  color: '#C24B2A', text: 'Weak' },
        { pct: '60%',  color: '#D4900A', text: 'Fair' },
        { pct: '80%',  color: '#4E9B5B', text: 'Strong' },
        { pct: '100%', color: '#2A7A40', text: 'Very strong' },
    ];
    const l = levels[Math.min(score, 4)];
    bar.style.width      = l.pct;
    bar.style.background = l.color;
    label.textContent    = l.text;
    label.style.color    = l.color;
}

// Live password match check
document.getElementById('reg_password2').addEventListener('input', function () {
    const pw1 = document.getElementById('reg_password').value;
    const msg = document.getElementById('pw-match-msg');
    if (this.value.length === 0) { msg.textContent = ''; return; }
    if (this.value === pw1) {
        msg.textContent = '✔ Passwords match';
        msg.style.color = '#4E9B5B';
    } else {
        msg.textContent = '✖ Passwords do not match';
        msg.style.color = '#C23A3A';
    }
});
</script>

<?php include('../includes/footer.php'); ?>