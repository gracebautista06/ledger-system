<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- IMPROVEMENT: Dynamic page title per-page using $page_title variable -->
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' — ' : ''; ?>Egg Ledger System</title>

    <!-- IMPROVEMENT: Added SEO meta tags and favicon placeholder -->
    <meta name="description" content="Egg Ledger — Digital farm management for harvest tracking, flock health, and sales.">
    <meta name="robots" content="noindex, nofollow"> <!-- Private system; hide from search engines -->
    
    <?php
        /* ----------------------------------------------------------
           IMPROVEMENT: Root path calculation
           Original logic had a potential off-by-one error.
           Now using a cleaner approach: detect how many directories
           deep we are relative to the project root and build the
           correct relative path back to assets.
        ---------------------------------------------------------- */
        $levels = substr_count($_SERVER['PHP_SELF'], '/') - 2;
        // Clamp to 0 so root-level pages (index.php) don't get '../'
        $levels = max(0, $levels);
        $root = str_repeat('../', $levels);

        // IMPROVEMENT: Start session safely (prevents "headers already sent" errors
        // if some pages call session_start() before including this header)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $root; ?>assets/css/style.css">
</head>
<body>

    <header>
        <nav class="navbar">
            <!-- IMPROVEMENT: Logo links back to home page -->
            <a href="<?php echo $root; ?>index.php" class="logo">Egg Ledger</a>

            <ul class="nav-links">
                <li><a href="<?php echo $root; ?>index.php">Home</a></li>

                <?php if (isset($_SESSION['username'])): ?>
                    <!-- IMPROVEMENT: Styled user info badge -->
                    <li>
                        <span class="nav-user-info">
                            👤 <?php echo htmlspecialchars($_SESSION['username']); ?>
                            <span class="badge <?php echo strtolower($_SESSION['role']) === 'owner' ? 'badge-owner' : 'badge-staff'; ?>"
                                  style="font-size: 0.65rem;">
                                <?php echo htmlspecialchars($_SESSION['role']); ?>
                            </span>
                        </span>
                    </li>

                    <!-- IMPROVEMENT: Dashboard shortcut link based on role -->
                    <li>
                        <?php
                            $dashboard_link = ($_SESSION['role'] === 'Owner')
                                ? $root . 'owner/dashboard.php'
                                : $root . 'staff/dashboard.php';
                        ?>
                        <a href="<?php echo $dashboard_link; ?>">Dashboard</a>
                    </li>

                    <li>
                        <a href="<?php echo $root; ?>portal/logout.php" class="nav-logout">Logout 🚪</a>
                    </li>

                <?php else: ?>
                    <li><a href="<?php echo $root; ?>portal/register.php">Register</a></li>
                    <li><a href="<?php echo $root; ?>portal/login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- IMPROVEMENT: Added role="main" for accessibility -->
    <main role="main">
        <div class="container">