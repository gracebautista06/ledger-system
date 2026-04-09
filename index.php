<?php
/* ============================================================
   index.php — Landing / Home Page
   
   IMPROVEMENT NOTES:
   - Added $page_title for dynamic browser tab title
   - No longer includes db.php (homepage doesn't need DB)
   - Added role-based redirect if already logged in
   - Cards now use .card-hoverable for lift on hover
   - Added a third feature card for completeness
   ============================================================ */

// IMPROVEMENT: Set page title before including header
$page_title = 'Welcome';

include('includes/header.php');

// IMPROVEMENT: If user is already logged in, redirect them straight
// to their dashboard instead of showing the landing page again.
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'Owner') {
        header("Location: owner/dashboard.php");
    } else {
        header("Location: staff/dashboard.php");
    }
    exit();
}
?>

<div class="welcome-section" style="text-align: center; padding: 4rem 1rem;">

    <h1 style="color: var(--barn-red); font-size: 2.8rem; margin-bottom: 1.2rem; font-weight: 800; line-height: 1.2;">
        Welcome to the<br>Egg Ledger System
    </h1>

    <p style="font-size: 1.15rem; color: #666; max-width: 700px; margin: 0 auto 3.5rem; line-height: 1.8;">
        Your digital farm companion for tracking every harvest, monitoring flock health,
        and managing operations — from the nesting box to the customer's tray.
    </p>

    <!-- Feature Cards -->
    <div style="display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; margin-bottom: 3.5rem;">

        <div class="card card-hoverable" style="width: 250px; border-bottom: 5px solid var(--primary-yolk);">
            <div class="floating-icon">👩‍🌾</div>
            <h3 style="margin-top: 10px;">Precision Logging</h3>
            <p style="font-size: 0.9rem; color: #777; margin-top: 8px;">
                Real-time data entry for daily egg production and flock status.
            </p>
        </div>

        <div class="card card-hoverable" style="width: 250px; border-bottom: 5px solid var(--barn-red);">
            <div class="floating-icon" style="animation-delay: 0.5s;">📊</div>
            <h3 style="margin-top: 10px;">Smart Analytics</h3>
            <p style="font-size: 0.9rem; color: #777; margin-top: 8px;">
                Weekly production trends, sales summaries, and profitability insights.
            </p>
        </div>

        <div class="card card-hoverable" style="width: 250px; border-bottom: 5px solid var(--accent-orange);">
            <div class="floating-icon" style="animation-delay: 1s;">🔒</div>
            <h3 style="margin-top: 10px;">Role-Based Access</h3>
            <p style="font-size: 0.9rem; color: #777; margin-top: 8px;">
                Separate dashboards for Owners and Staff with controlled permissions.
            </p>
        </div>

    </div>

    <!-- CTA Buttons -->
    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
        <a href="portal/login.php" class="btn-farm" style="padding: 15px 35px; font-size: 1.1rem;">
            🔑 Login to Dashboard
        </a>
        <a href="portal/register.php" class="btn-farm btn-outline" style="padding: 15px 35px; font-size: 1.1rem;">
            📝 Create Account
        </a>
    </div>

</div>

<?php include('includes/footer.php'); ?>