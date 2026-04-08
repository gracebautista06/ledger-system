<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Staff') {
        header("Location: ../portal/login.php");
        exit();
    }

    $staff_id = $_SESSION['user_id'];

    // 1. Fetch Harvests + Check if there is already a pending edit request
    $harvest_sql = "SELECT h.*, b.breed, er.status AS request_status
                    FROM harvests h 
                    JOIN batches b ON h.batch_id = b.batch_id 
                    LEFT JOIN edit_requests er ON er.record_id = h.harvest_id 
                         AND er.record_type = 'Harvest' AND er.status = 'Pending'
                    WHERE h.staff_id = $staff_id 
                    ORDER BY h.date_logged DESC LIMIT 10";
    $harvest_logs = $conn->query($harvest_sql);

    // 2. Fetch Health Reports + Check for pending requests
    $health_sql = "SELECT fh.*, b.breed, er.status AS request_status
                   FROM flock_health fh 
                   JOIN batches b ON fh.batch_id = b.batch_id 
                   LEFT JOIN edit_requests er ON er.record_id = fh.report_id 
                        AND er.record_type = 'Health' AND er.status = 'Pending'
                   WHERE fh.staff_id = $staff_id 
                   ORDER BY fh.date_reported DESC LIMIT 5";
    $health_logs = $conn->query($health_sql);
?>

<div class="container" style="max-width: 1100px; margin: 2rem auto; padding: 0 1rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h2 style="color: var(--dark-nest);">📋 Your Recent Activity</h2>
        <a href="dashboard.php" style="text-decoration: none; color: var(--barn-red); font-weight: bold;">← Back to Menu</a>
    </div>

    <div class="card" style="border-top: 6px solid var(--primary-yolk); margin-bottom: 2rem;">
        <h3 style="margin-bottom: 1.5rem;">🧺 Recent Harvests</h3>
        <div style="overflow-x: auto;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Batch</th>
                        <th>Total</th>
                        <th>Breakdown</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($harvest_logs->num_rows > 0): ?>
                        <?php while($row = $harvest_logs->fetch_assoc()): ?>
                        <tr>
                            <td style="font-size: 0.85rem; color: #666;"><?php echo date('M d, g:i A', strtotime($row['date_logged'])); ?></td>
                            <td><strong><?php echo $row['breed']; ?></strong></td>
                            <td style="font-weight: bold; color: var(--barn-red);"><?php echo $row['total_eggs']; ?></td>
                            <td style="font-family: monospace; font-size: 0.8rem;"><?php echo "{$row['size_pw']}-{$row['size_s']}-{$row['size_m']}-{$row['size_l']}-{$row['size_xl']}-{$row['size_j']}"; ?></td>
                            <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($row['notes']); ?></td>
                            <td>
                                <?php if ($row['request_status'] == 'Pending'): ?>
                                    <span style="color: #f39c12; font-size: 0.8rem; font-style: italic;">Wait for Owner...</span>
                                <?php else: ?>
                                    <a href="request_edit.php?type=Harvest&id=<?php echo $row['harvest_id']; ?>" 
                                       style="background: #fdf2f2; color: #d9534f; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; border: 1px solid #f5c6cb;">
                                       ✏️ Edit
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 2rem;">No harvest logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="border-top: 6px solid var(--accent-orange);">
        <h3 style="margin-bottom: 1.5rem;">🐔 Health Reports</h3>
        <div style="overflow-x: auto;">
            <table class="table-farm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Mortality</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($health_logs->num_rows > 0): ?>
                        <?php while($row = $health_logs->fetch_assoc()): ?>
                        <tr>
                            <td style="font-size: 0.85rem; color: #666;"><?php echo date('M d, g:i A', strtotime($row['date_reported'])); ?></td>
                            <td><?php echo $row['breed']; ?></td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 5px; font-size: 0.75rem; font-weight: bold; 
                                    background: <?php echo ($row['status_level'] == 'Healthy' ? '#d4edda' : ($row['status_level'] == 'Warning' ? '#fff3cd' : '#f8d7da')); ?>;">
                                    <?php echo $row['status_level']; ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: bold;"><?php echo $row['mortality_count']; ?></td>
                            <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($row['symptoms']); ?></td>
                            <td>
                                <?php if ($row['request_status'] == 'Pending'): ?>
                                    <span style="color: #f39c12; font-size: 0.8rem; font-style: italic;">Wait for Owner...</span>
                                <?php else: ?>
                                    <a href="request_edit.php?type=Health&id=<?php echo $row['report_id']; ?>" 
                                       style="background: #fdf2f2; color: #d9534f; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; border: 1px solid #f5c6cb;">
                                       ✏️ Edit
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 2rem;">No health reports found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>