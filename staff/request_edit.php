<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    $type = $_GET['type'];
    $id = intval($_GET['id']);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $staff_id = $_SESSION['user_id'];
        
        // We package the new numbers into a JSON string to keep it clean
        $new_values = json_encode($_POST['new_data']);

        $sql = "INSERT INTO edit_requests (staff_id, record_type, record_id, new_data, reason) 
                VALUES ('$staff_id', '$type', '$id', '$new_values', '$reason')";
        
        if ($conn->query($sql)) {
            echo "<script>alert('Request sent to Owner for approval!'); window.location='view_logs.php';</script>";
        }
    }
?>

<div class="card" style="max-width: 500px; margin: 2rem auto; border-top: 8px solid var(--accent-orange);">
    <h3>Request Record Correction</h3>
    <p style="color: #666; font-size: 0.9rem;">Original Record: <strong><?php echo $type; ?> #<?php echo $id; ?></strong></p>

    <form method="POST">
        <div class="form-group">
            <label>Why do you need to change this?</label>
            <textarea name="reason" class="form-input" required placeholder="Example: I typed 50 instead of 5 for Peewee sizes by mistake."></textarea>
        </div>

        <p style="font-weight: bold; margin-top: 1rem;">Input Corrected Values:</p>
        <input type="number" name="new_data[total_eggs]" class="form-input" placeholder="New Total" required>
        
        <button type="submit" class="btn-farm" style="width: 100%; margin-top: 1rem;">Send to Owner</button>
    </form>
</div>