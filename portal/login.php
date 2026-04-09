<?php
/* ============================================================
   portal/login.php — User Login
   IMPROVEMENTS:
   - session_start() handled safely inside header.php
   - Redirect if already logged in
   - Logout success message via ?message=logged_out
   - Uses prepared statements (fixes SQL injection risk)
   - session_regenerate_id() on login (prevents session fixation)
   - Generic error message (prevents username enumeration)
   - show/hide password toggle added
   - Registered success flash message support
   ============================================================ */

$page_title = 'Login';

include('../includes/db.php');
include('../includes/header.php');

// Redirect if already logged in
if (isset($_SESSION['role'])) {
    $redirect = ($_SESSION['role'] === 'Owner') ? '../owner/dashboard.php' : '../staff/dashboard.php';
    header("Location: $redirect");
    exit();
}

$error   = "";
$success = "";

if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
    $success = "You have been securely logged out. See you next time! 👋";
}

// Show flash message after successful registration
if (isset($_GET['registered'])) {
    $success = "✅ Account created! You can now log in.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both your username and password.";
    } else {

        // IMPROVEMENT: Use prepared statement — no more manual escaping needed
        $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                $redirect = ($user['role'] === 'Owner') ? '../owner/dashboard.php' : '../staff/dashboard.php';
                header("Location: $redirect");
                exit();

            } else {
                $error = "Invalid username or password. Please try again.";
                sleep(1); // Slow down brute-force attempts
            }
        } else {
            $error = "Invalid username or password. Please try again.";
            sleep(1);
        }

        $stmt->close();
    }
}
?>

<div style="display: flex; justify-content: center; align-items: center; min-height: 70vh;">
    <div class="card" style="width: 100%; max-width: 420px; border-top: 8px solid var(--terra); animation: slideUp 0.6s ease-out;">

        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 4rem; animation: float 3s ease-in-out infinite; display: inline-block;">🔑</div>
            <h2 style="color: var(--gold); margin-top: 1rem; font-family: 'Playfair Display', serif;">Farm Login</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Enter your credentials to access the ledger.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input"
                       placeholder="Enter your username" required autocomplete="username"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars(trim($_POST['username'])) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <!-- IMPROVEMENT: Show/hide password toggle -->
                <div style="position: relative;">
                    <input type="password" id="password" name="password" class="form-input"
                           placeholder="••••••••" required autocomplete="current-password"
                           style="padding-right: 44px;">
                    <button type="button" id="toggle-pw"
                            onclick="togglePassword('password', 'eye-icon')"
                            style="position:absolute; right:12px; top:50%; transform:translateY(-50%);
                                   background:none; border:none; cursor:pointer; color:var(--text-muted);
                                   font-size:1.1rem; padding:4px;" title="Show/hide password">
                        <span id="eye-icon">👁️</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-farm btn-full" style="padding: 15px; margin-top: 0.5rem;">
                Open Ledger 🚪
            </button>
        </form>

        <div style="margin-top: 2rem; text-align: center; border-top: 1px solid var(--border-subtle); padding-top: 1.5rem;">
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                Don't have an account?
                <a href="register.php" style="color: var(--gold); font-weight: 700; text-decoration: none;">Register here</a>
            </p>
        </div>

    </div>
</div>

<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = '🙈';
    } else {
        input.type = 'password';
        icon.textContent = '👁️';
    }
}
</script>

<?php include('../includes/footer.php'); ?>