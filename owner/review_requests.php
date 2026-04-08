<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
        header("Location: ../portal/login.php");
        exit();
    }

    // --- LOGIC: Handle Approval or Rejection ---
    if (isset($_GET['action']) && isset($_GET['req_id'])) {
        $req_id = intval($_GET['req_id']);
        $action = $_GET['action'];

        if ($action == 'approve') {
            // 1. Get the request details
            $req_query = $conn->query("SELECT * FROM edit_requests WHERE request_id = $req_id");
            $req = $req_query->fetch_assoc();
            $new_values = json_decode($req['new_data'], true);
            $record_id = $req['record_id'];

            if ($req['record_type'] == 'Harvest') {
                $total = $new_values['total_eggs'];
                $sql = "UPDATE harvests SET total_eggs = '$total' WHERE harvest_id = $record_id";
            } elseif ($req['record_type'] == 'Health') {
                $mortality = $new_values['mortality_count'];
                $sql = "UPDATE flock_health SET mortality_count = '$mortality' WHERE report_id = $record_id";
            }
            
            $conn->query($sql);
            $conn->query("UPDATE edit_requests SET status = 'Approved' WHERE request_id = $req_id");
            echo "<script>alert('Record Updated Successfully!'); window.location='review_requests.php';</script>";

        } else {
            // Rejection logic
            $conn->query("UPDATE edit_requests SET status = 'Rejected' WHERE request_id = $req_id");
            echo "<script>alert('Request Rejected.'); window.location='review_requests.php';</script>";
        }
    }

    // Fetch all pending requests
    $requests = $conn->query("SELECT er.*, u.username FROM edit_requests er 
                              JOIN users u ON er.staff_id = u.user_id 
                              WHERE er.status = 'Pending' ORDER BY created_at DESC");
?>

<div class="container" style="max-width: 900px; margin: 2rem auto; padding: 0 1rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h2 style="color: var(--dark-nest);">🛠️ Review Edit Requests</h2>
        <a href="dashboard.php" style="text-decoration: none; color: var(--barn-red); font-weight: bold;">← Dashboard</a>
    </div>

    <?php if ($requests->num_rows > 0): ?>
        <?php while($r = $requests->fetch_assoc()): 
            $data = json_decode($r['new_data'], true);
        ?>
        <div class="card" style="margin-bottom: 1.5rem; border-left: 10px solid var(--accent-orange);">
            <div style="display: flex; justify-content: space-between;">
                <div>
                    <span style="background: #eee; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; color: #555;">
                        <?php echo strtoupper($r['record_type']); ?> #<?php echo $r['record_id']; ?>
                    </span>
                    <h4 style="margin: 10px 0 5px;">Staff: <?php echo $r['full_name']; ?></h4>
                    <p style="color: #666; font-size: 0.9rem;"><strong>Reason:</strong> "<?php echo htmlspecialchars($r['reason']); ?>"</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 0.8rem; color: #999;"><?php echo date('M d, h:i A', strtotime($r['created_at'])); ?></p>
                </div>
            </div>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px dashed #ccc;">
                <p style="margin: 0; font-weight: bold; color: var(--barn-red);">
                    Proposed Change: 
                    <?php 
                        if($r['record_type'] == 'Harvest') echo "Total Eggs → " . $data['total_eggs'];
                        if($r['record_type'] == 'Health') echo "Mortality → " . $data['mortality_count'];
                    ?>
                </p>
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="review_requests.php?action=approve&req_id=<?php echo $r['request_id']; ?>" 
                   onclick="return confirm('Update the official database with these numbers?')"
                   style="flex: 1; background: #28a745; color: white; text-align: center; padding: 10px; border-radius: 5px; text-decoration: none; font-weight: bold;">
                   ✅ Approve & Update
                </a>
                <a href="review_requests.php?action=reject&req_id=<?php echo $r['request_id']; ?>" 
                   onclick="return confirm('Reject this request?')"
                   style="flex: 1; background: #dc3545; color: white; text-align: center; padding: 10px; border-radius: 5px; text-decoration: none; font-weight: bold;">
                   ❌ Reject
                </a>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <p style="color: #999;">No pending requests. Your data is up to date! 🥚</p>
        </div>
    <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>