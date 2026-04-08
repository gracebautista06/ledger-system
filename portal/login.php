<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    $error = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = $_POST['password'];

        // 1. Find the user
        $sql = "SELECT * FROM users WHERE username = '$username'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // 2. Verify the hashed password
            if (password_verify($password, $user['password'])) {
                
                // 3. Start the Session and store user info
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // 4. Redirect based on the ROLE in the database
                if ($user['role'] == 'Owner') {
                    header("Location: ../owner/dashboard.php");
                } else {
                    header("Location: ../staff/dashboard.php");
                }
                exit();
                
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "Username not found.";
        }
    }
?>

<div class="login-container" style="display: flex; justify-content: center; align-items: center; min-height: 70vh;">
    <div class="card" style="width: 100%; max-width: 400px; border-top: 8px solid var(--barn-red); animation: slideUp 0.6s ease-out;">
        
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="floating-icon" style="font-size: 4rem;">🔑</div>
            <h2 style="color: var(--barn-red); margin-top: 1rem;">Farm Login</h2>
            <p style="color: #666; font-size: 0.9rem;">Enter your credentials to access the ledger.</p>
        </div>

        <?php if($error): ?>
            <div style="background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 1.5rem; font-size: 0.85rem; border-left: 4px solid #c62828;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-input" placeholder="Enter username" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-farm" style="width: 100%; margin-top: 1rem; padding: 15px;">
                Open Ledger
            </button>
        </form>

        <div style="margin-top: 2rem; text-align: center; border-top: 1px solid #eee; padding-top: 1.5rem;">
            <p style="font-size: 0.85rem; color: #888;">
                Don't have an account? 
                <a href="register.php" style="color: var(--accent-orange); font-weight: 700; text-decoration: none;">Register here</a>
            </p>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>