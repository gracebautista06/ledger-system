<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    // Security: Only Staff can access this page
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Staff') {
        header("Location: ../portal/login.php");
        exit();
    }

    $message = "";

    // Fetch Active Batches
    $batch_query = $conn->query("SELECT batch_id, breed FROM batches WHERE status = 'Active'");

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $staff_id = $_SESSION['user_id'];
        $batch_id = $_POST['batch_id'];
        $total_eggs = $_POST['total_eggs'];
        $pw = $_POST['size_pw']; // Peewee
        $s = $_POST['size_s'];
        $m = $_POST['size_m'];
        $l = $_POST['size_l'];
        $xl = $_POST['size_xl'];
        $j = $_POST['size_j'];   // Jumbo
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);

        // Updated SQL to include PW and J columns
        $sql = "INSERT INTO harvests (staff_id, batch_id, total_eggs, size_pw, size_s, size_m, size_l, size_xl, size_j, notes) 
                VALUES ('$staff_id', '$batch_id', '$total_eggs', '$pw', '$s', '$m', '$l', '$xl', '$j', '$notes')";

        if ($conn->query($sql) === TRUE) {
            $message = "<div class='alert success'>🥚 Harvest Logged! Total: $total_eggs eggs recorded.</div>";
        } else {
            $message = "<div class='alert error'>Database Error: " . $conn->error . "</div>";
        }
    }
?>

<div class="card" style="max-width: 650px; margin: 2rem auto; border-top: 8px solid var(--primary-yolk);">
    <h2 style="color: var(--dark-nest);">🧺 Daily Harvest Log</h2>
    <p style="color: #666; margin-bottom: 2rem;">Log the counts for all egg sizes, from Peewee to Jumbo.</p>

    <?php echo $message; ?>

    <form method="POST" id="harvestForm">
        <div class="form-group">
            <label>Select Flock Batch</label>
            <select name="batch_id" class="form-input" required>
                <option value="" disabled selected>-- Select Batch --</option>
                <?php while($batch = $batch_query->fetch_assoc()): ?>
                    <option value="<?php echo $batch['batch_id']; ?>">
                        Batch #<?php echo $batch['batch_id']; ?> (<?php echo $batch['breed']; ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="background: #fffdf5; padding: 20px; border-radius: 12px; border: 1px solid #f1e4b8; margin-bottom: 20px;">
            <p style="font-weight: 700; margin-bottom: 15px; color: var(--barn-red); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;">Size Breakdown</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Peewee (PW)</label>
                    <input type="number" name="size_pw" class="form-input egg-count" value="0" min="0">
                </div>
                <div class="form-group">
                    <label>Small (S)</label>
                    <input type="number" name="size_s" class="form-input egg-count" value="0" min="0">
                </div>
                <div class="form-group">
                    <label>Medium (M)</label>
                    <input type="number" name="size_m" class="form-input egg-count" value="0" min="0">
                </div>
                <div class="form-group">
                    <label>Large (L)</label>
                    <input type="number" name="size_l" class="form-input egg-count" value="0" min="0">
                </div>
                <div class="form-group">
                    <label>Extra Large (XL)</label>
                    <input type="number" name="size_xl" class="form-input egg-count" value="0" min="0">
                </div>
                <div class="form-group">
                    <label>Jumbo (J)</label>
                    <input type="number" name="size_j" class="form-input egg-count" value="0" min="0">
                </div>
            </div>
        </div>

        <div class="form-group" style="background: var(--dark-nest); padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 1.5rem;">
            <label style="color: white; display: block; margin-bottom: 5px; opacity: 0.8;">Total Eggs Harvested</label>
            <input type="number" name="total_eggs" id="total_eggs_display" class="form-input" 
                   style="text-align: center; font-size: 2.5rem; font-weight: 800; color: var(--primary-yolk); border: none; background: transparent;" readonly>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-input" rows="2" placeholder="Any cracked eggs or health notes?"></textarea>
        </div>

        <button type="submit" class="btn-farm" style="width: 100%; padding: 18px; font-size: 1.1rem;">Submit Harvest</button>
        <a href="dashboard.php" id="backBtn" style="display: block; text-align: center; margin-top: 1.5rem; color: #888; text-decoration: none;">← Back to Dashboard</a>
    </form>
</div>

<script>
    const harvestForm = document.getElementById('harvestForm');
    const sizeInputs = document.querySelectorAll('.egg-count');
    const totalDisplay = document.getElementById('total_eggs_display');
    let isDirty = false;

    // 1. Calculate Totals
    function calculateTotal() {
        let total = 0;
        sizeInputs.forEach(input => {
            total += parseInt(input.value) || 0;
        });
        totalDisplay.value = total;
    }

    // 2. Track if user has typed anything
    harvestForm.addEventListener('input', function() {
        isDirty = true;
    });

    // 3. Warning when leaving via Browser Back/Close/Refresh
    window.addEventListener('beforeunload', function (e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = ''; // Standard for modern browsers
        }
    });

    // 4. Warning when clicking the "Back to Dashboard" link specifically
    document.getElementById('backBtn').addEventListener('click', function(e) {
        if (isDirty) {
            const confirmLeave = confirm("You have unsaved harvest data. Are you sure you want to go back?");
            if (!confirmLeave) {
                e.preventDefault(); // Stop them from leaving
            }
        }
    });

    // 5. Allow leave if form is submitted
    harvestForm.addEventListener('submit', function() {
        isDirty = false;
    });

    sizeInputs.forEach(input => {
        input.addEventListener('input', calculateTotal);
    });

    calculateTotal(); // Run on load
</script>

<?php include('../includes/footer.php'); ?>