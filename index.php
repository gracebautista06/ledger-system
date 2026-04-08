<?php 
    // 1. Include the database connection (Engine)
    include('includes/db.php'); 
    
    // 2. Include the stylized header (Visual Top)
    include('includes/header.php'); 
?>

<div class="welcome-section" style="text-align: center; padding: 4rem 1rem;">
    <h1 style="color: var(--barn-red); font-size: 3rem; margin-bottom: 1.5rem; font-weight: 800;">
        Welcome to the Egg Ledger System
    </h1>
    
    <p style="font-size: 1.25rem; color: #555; max-width: 800px; margin: 0 auto 4rem; line-height: 1.8;">
        Your digital farm companion for tracking every harvest, monitoring flock health, 
        and managing sales—from the nesting box to the customer's tray.
    </p>

    <div class="actor-cards" style="display: flex; gap: 40px; justify-content: center; flex-wrap: wrap; margin-bottom: 4rem;">
        
        <div class="card" style="width: 280px; border-bottom: 5px solid var(--primary-yolk);">
            <div class="floating-icon">👩‍🌾</div>
            <h3 style="margin-top: 10px;">Precision Logging</h3>
            <p style="font-size: 0.9rem; color: #777;">Real-time data entry for daily egg production and flock status.</p>
        </div>

        <div class="card" style="width: 280px; border-bottom: 5px solid var(--barn-red);">
            <div class="floating-icon" style="animation-delay: 0.5s;">📊</div>
            <h3 style="margin-top: 10px;">Smart Analytics</h3>
            <p style="font-size: 0.9rem; color: #777;">Advanced reporting on sales trends and business profitability.</p>
        </div>

    </div>
</div>

<?php 
    // 3. Include the stylized footer (Visual Bottom)
    include('includes/footer.php'); 
?>