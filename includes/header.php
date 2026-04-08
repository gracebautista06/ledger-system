<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egg Ledger System</title>
    
    <?php
        // This calculates the correct path back to the root folder automatically
        $levels = substr_count($_SERVER['PHP_SELF'], '/') - 2; 
        $root = str_repeat('../', $levels);
        
        // Ensure session is started to check for logged-in users
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    ?>

    <link rel="stylesheet" href="<?php echo $root; ?>assets/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo">🥚 Egg Ledger</div>
            <ul class="nav-links">
                <li><a href="<?php echo $root; ?>index.php">Home</a></li>
                
                <?php if (isset($_SESSION['username'])): ?>
                    <li style="color: white; font-weight: bold; margin-left: 15px;">
                        👤 <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)
                    </li>
                    <li>
                        <a href="<?php echo $root; ?>portal/logout.php" 
                           style="background: #dc3545; color: white; padding: 5px 12px; border-radius: 4px; font-weight: bold;">
                           Logout 🚪
                        </a>
                    </li>
                <?php else: ?>
                    <li><a href="<?php echo $root; ?>portal/register.php">Register</a></li>
                    <li><a href="<?php echo $root; ?>portal/login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <div class="container">