<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    // Security: Only Staff can access
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
        $status_level = $_POST['status_level']; // e.g., Healthy, Warning, Critical
        $mortality_count = intval($_POST['mortality_count']);
        $symptoms = mysqli_real_escape_string($conn, $_POST['symptoms']);

        // Assuming you have a 'flock_health' table (SQL below)
        $sql = "INSERT INTO flock_health (staff_id, batch_id, status_level, mortality_count, symptoms) 
                VALUES ('$staff_id', '$batch_id', '$status_level', '$mortality_count', '$symptoms')";

        if ($conn->query($sql) === TRUE) {
            $message = "<div class='alert success'>✅ Health report submitted for Batch #$batch_id.</div>";
        } else {
            $message = "<div class='alert error'>Database Error: " . $conn->error . "</div>";
        }
    }
?>

<div class="card" style="max-width: 600px; margin: 2rem auto; border-top: 8px solid var(--accent-orange);">
    <h2 style="color: var(--dark-nest);">🐔 Flock Health Report</h2>
    <p style="color: #666; margin-bottom: 2rem;">Report any bird deaths, sickness, or general flock behavior.</p>

    <?php echo $message; ?>

    <form method="POST" id="healthForm">
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

        <div class="form-group">
            <label>Overall Health Status</label>
            <select name="status_level" class="form-input" required>
                <option value="Healthy" style="color: green;">🟢 Healthy (Normal)</option>
                <option value="Warning" style="color: orange;">🟡 Warning (Minor Issues)</option>
                <option value="Critical" style="color: red;">🔴 Critical (High Mortality/Disease)</option>
            </select>
        </div>

        <div class="form-group">
            <label>Mortality Count (Birds Found Dead Today)</label>
            <input type="number" name="mortality_count" class="form-input" value="0" min="0" required>
        </div>

        <div class="form-group">
            <label>Symptoms or Notes</label>
            <textarea name="symptoms" class="form-input" rows="4" placeholder="Describe behavior: e.g., sluggishness, coughing, or 'The birds look active and energetic.'"></textarea>
        </div>

        <button type="submit" class="btn-farm" style="width: 100%; padding: 18px; background: var(--accent-orange); border-bottom: 4px solid #d35400;">Submit Health Report</button>
        <a href="dashboard.php" id="backBtn" style="display: block; text-align: center; margin-top: 1.5rem; color: #888; text-decoration: none;">← Back to Dashboard</a>
    </form>
</div>

<script>
    // Safety Net: Warn if they try to leave with unsaved data
    let isDirty = false;
    document.getElementById('healthForm').addEventListener('input', () => isDirty = true);

    window.addEventListener('beforeunload', (e) => {
        if (isDirty) { e.preventDefault(); e.returnValue = ''; }
    });

    document.getElementById('backBtn').addEventListener('click', (e) => {
        if (isDirty && !confirm("Discard unsaved health report?")) e.preventDefault();
    });

    document.getElementById('healthForm').addEventListener('submit', () => isDirty = false);
</script>

<?php include('../includes/footer.php'); ?>