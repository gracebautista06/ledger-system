<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
        header("Location: ../portal/login.php");
        exit();
    }

    // Filter Logic
    $filter = isset($_GET['role']) ? $_GET['role'] : 'all';
    $where_clause = ($filter !== 'all') ? "WHERE user_role = '$filter'" : "";

    $sql = "SELECT activity_logs.*, users.username 
            FROM activity_logs 
            LEFT JOIN users ON activity_logs.user_id = users.user_id 
            $where_clause 
            ORDER BY timestamp DESC LIMIT 100";
            
    $logs = $conn->query($sql);
?>

<div class="card" style="max-width: 1000px; margin: 2rem auto; border-top: 8px solid #607d8b;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h2 style="color: var(--dark-nest);">📜 System Activity History</h2>
        
        <div style="background: #eee; padding: 5px; border-radius: 10px;">
            <a href="view_history.php?role=all" style="padding: 8px 15px; text-decoration: none; color: <?php echo $filter=='all'?'white':'#666'; ?>; background: <?php echo $filter=='all'?'var(--dark-nest)':'transparent'; ?>; border-radius: 8px; display: inline-block;">All</a>
            <a href="view_history.php?role=Staff" style="padding: 8px 15px; text-decoration: none; color: <?php echo $filter=='Staff'?'white':'#666'; ?>; background: <?php echo $filter=='Staff'?'var(--accent-orange)':'transparent'; ?>; border-radius: 8px; display: inline-block;">Staff Only</a>
            <a href="view_history.php?role=Owner" style="padding: 8px 15px; text-decoration: none; color: <?php echo $filter=='Owner'?'white':'#666'; ?>; background: <?php echo $filter=='Owner'?'var(--barn-red)':'transparent'; ?>; border-radius: 8px; display: inline-block;">Owner Only</a>
        </div>
    </div>

    <table class="table-farm">
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $logs->fetch_assoc()): ?>
            <tr>
                <td style="font-size: 0.8rem; color: #888;">
                    <?php echo date('M d, g:i A', strtotime($row['timestamp'])); ?>
                </td>
                <td>
                    <strong><?php echo $row['username'] ?? 'Deleted User'; ?></strong>
                    <br><small style="color: var(--accent-orange);"><?php echo $row['user_role']; ?></small>
                </td>
                <td><span style="font-weight: 700; color: var(--barn-red);"><?php echo $row['action_type']; ?></span></td>
                <td style="font-size: 0.9rem;"><?php echo $row['description']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 2rem;">
        <a href="dashboard.php" style="color: #888; text-decoration: none;">← Back to Command Center</a>
    </div>
</div>

<?php include('../includes/footer.php'); ?>