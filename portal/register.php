<?php 
    // 1. Connect to DB and Styles
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    $message = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Sanitize inputs
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Secure hashing
        $role = $_POST['role'];
        $input_key = $_POST['secret_key'];

        // --- THE SECRET KEYS ---
        // Change these to whatever you like!
        $staff_key = "EGG_STAFF_2026";
        $owner_key = "FARM_BOSS_99";

        $authorized = false;

        // Check if the key matches the selected role
        if ($role == 'Staff' && $input_key === $staff_key) {
            $authorized = true;
        } elseif ($role == 'Owner' && $input_key === $owner_key) {
            $authorized = true;
        }

        if ($authorized) {
            // Check if username already exists
            $checkUser = "SELECT * FROM users WHERE username='$username'";
            $res = $conn->query($checkUser);

            if ($res->num_rows > 0) {
                $message = "<div class='alert error'>Username is already taken! Choose another.</div>";
            } else {
                // Insert into database
                $sql = "INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')";
                if ($conn->query($sql) === TRUE) {
                    $message = "<div class='alert success'>Registration successful! <a href='login.php'>Login here</a></div>";
                } else {
                    $message = "<div class='alert error'>Database Error: " . $conn->error . "</div>";
                }
            }
        } else {
            $message = "<div class='alert error'>Invalid Secret Key for the selected role! Access Denied.</div>";
        }
    }
?>

<style>
    .alert { padding: 15px; border-radius: var(--radius); margin-bottom: 1.5rem; font-size: 0.9rem; border-left: 5px solid; }
    .success { background: #e8f5e9; color: #2e7d32; border-color: #2e7d32; }
    .error { background: #ffebee; color: #c62828; border-color: #c62828; }
    .registration-card { max-width: 500px; margin: 3rem auto; border-top: 8px solid var(--accent-orange); }
</style>

<div class="registration-card card">
    <div style="text-align: center; margin-bottom: 2rem;">
        <div class="floating-icon">📝</div>
        <h2 style="color: var(--barn-red); margin-top: 1rem;">Join the Farm</h2>
        <p style="color: #666;">Create your account to start managing the ledger.</p>
    </div>

    <?php echo $message; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-input" placeholder="Enter username" required>
        </div>
        
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-input" placeholder="Create a strong password" required>
        </div>

        <div class="form-group">
            <label>Your Role</label>
            <select name="role" class="form-input" required>
                <option value="" disabled selected>Select your position...</option>
                <option value="Staff">Farm Staff (Harvesting/Logging)</option>
                <option value="Owner">Administrator/Owner (Analytics/Management)</option>
            </select>
        </div>

        <div class="form-group">
            <label>Role Secret Key</label>
            <input type="password" name="secret_key" class="form-input" placeholder="Enter the key provided by management" required>
        </div>

        <button type="submit" class="btn-farm" style="width: 100%; margin-top: 1rem; padding: 15px;">
            Register Account
        </button>
    </form>

    <div style="margin-top: 2rem; text-align: center; border-top: 1px solid #eee; padding-top: 1.5rem;">
        <p style="font-size: 0.85rem; color: #888;">
            Already have an account? 
            <a href="login.php" style="color: var(--accent-orange); font-weight: 700; text-decoration: none;">Sign in instead</a>
        </p>
    </div>
</div>

<?php include('../includes/footer.php'); ?>