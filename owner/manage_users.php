<?php 
    session_start();
    include('../includes/db.php'); 
    include('../includes/header.php'); 

    // SECURITY: Only Owners allowed
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Owner') {
        header("Location: ../portal/login.php");
        exit();
    }

    $message = "";

    // --- HANDLE ACTIONS ---

    // 1. Reset Password to Default
    if (isset($_GET['reset_id'])) {
        $id = intval($_GET['reset_id']);
        $default_pass = password_hash("Farm1234", PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$default_pass' WHERE user_id = $id AND role = 'Staff'");
        $message = "<div class='alert success'>Password reset to <b>Farm1234</b> for Staff ID #$id.</div>";
    }

    // 2. Delete Staff Account
    if (isset($_GET['delete_id'])) {
        $id = intval($_GET['delete_id']);
        // We only allow deleting Staff, never other Owners through this page
        $conn->query("DELETE FROM users WHERE user_id = $id AND role = 'Staff'");
        $message = "<div class='alert error'>Staff account #$id has been removed.</div>";
    }

    // --- FETCH ALL STAFF ---
    $result = $conn->query("SELECT user_id, username, role FROM users WHERE role = 'Staff' ORDER BY username ASC");
?>

<div class="card" style="max-width: 900px; margin: 2rem auto; border-top: 8px solid var(--dark-nest);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h2 style="color: var(--barn-red);">👥 Staff Management</h2>
            <p style="color: #666; font-size: 0.9rem;">Control access and reset credentials for farm workers.</p>
        </div>
        <a href="../portal/register.php" class="btn-farm" style="background: var(--accent-orange); text-decoration: none; font-size: 0.8rem;">+ Add New Staff</a>
    </div>

    <?php echo $message; ?>

    <table class="table-farm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th style="text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $row['user_id']; ?></td>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><span style="background: #eee; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;"><?php echo $row['role']; ?></span></td>
                    <td style="text-align: center;">
                        <a href="manage_users.php?reset_id=<?php echo $row['user_id']; ?>" 
                           onclick="return confirm('Reset password to Farm1234?')"
                           style="color: #FF6B35; text-decoration: none; margin-right: 15px; font-weight: 600; font-size: 0.85rem;">
                           Reset Password
                        </a>
                        
                        <a href="manage_users.php?delete_id=<?php echo $row['user_id']; ?>" 
                           onclick="return confirm('Are you sure you want to delete this staff member?')"
                           style="color: #8B1E1E; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                           🗑️ 
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 2rem; color: #999;">No staff members found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 2rem;">
        <a href="dashboard.php" style="color: #888; text-decoration: none;">← Back to Command Center</a>
    </div>
</div>

<?php include('../includes/footer.php'); ?>