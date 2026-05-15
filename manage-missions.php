<?php
require_once 'config/database.php';   // Load database connection
require_once 'includes/functions.php';// Load helper functions

requireAdmin(); // Restrict access to admins only

// Determine view mode (missions or signups)
$view = $_GET['view'] ?? 'missions';
$viewSignupsFor = $_GET['view_signups'] ?? null;
$message = '';
$messageType = '';

// --- Handle mission deletion ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $adminId = getUserId();          // Current admin ID
    $missionId = $_GET['delete'];    // Mission ID to delete
    
    // Verify mission belongs to this admin
    $checkStmt = $pdo->prepare("SELECT admin_id FROM missions WHERE id = ?");
    $checkStmt->execute([$missionId]);
    $missionData = $checkStmt->fetch();
    
    if (!$missionData || $missionData['admin_id'] != $adminId) {
        $message = 'You do not have permission to delete this mission!';
        $messageType = 'error';
    } else {
        // Delete mission
        $stmt = $pdo->prepare("DELETE FROM missions WHERE id = ?");
        if ($stmt->execute([$missionId])) {
            $message = 'Mission deleted successfully!';
            $messageType = 'success';
        }
    }
}

// --- Handle export to CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="missions_export.csv"');
    
    $output = fopen('php://output', 'w');
    // CSV header row includes completed count
    fputcsv($output, ['ID','Title','Date','Time','Location','Total Slots','Signups','Category','Status','Completed']);
    
    // Query missions with signup and completed counts
    $stmt = $pdo->query("
        SELECT m.*, 
        (SELECT COUNT(*) FROM signups WHERE mission_id = m.id) as signup_count,
        (SELECT COUNT(*) FROM signups WHERE mission_id = m.id AND completed = 1) as completed_count
        FROM missions m
        ORDER BY m.mission_date DESC
    ");
    
    // Write each mission row to CSV
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],$row['title'],$row['mission_date'],$row['mission_time'],
            $row['location'],$row['total_slots'],$row['signup_count'],
            $row['category'],$row['status'],$row['completed_count']
        ]);
    }
    
    fclose($output);
    exit; // Stop script after export
}

// --- Handle marking signup as completed ---
if (isset($_GET['complete']) && is_numeric($_GET['complete'])) {
    $signupId = $_GET['complete'];   // Signup ID to mark completed
    $stmt = $pdo->prepare("UPDATE signups SET completed = 1 WHERE id = ?");
    if ($stmt->execute([$signupId])) {
        $message = 'Volunteer marked as completed!';
        $messageType = 'success';
    } else {
        $message = 'Failed to mark as completed.';
        $messageType = 'error';
    }
}

// --- Fetch signups for a specific mission ---
if ($viewSignupsFor) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name, u.email, m.title as mission_title
        FROM signups s
        JOIN users u ON s.user_id = u.id
        JOIN missions m ON s.mission_id = m.id
        WHERE s.mission_id = ?
        ORDER BY s.signup_date DESC
    ");
    $stmt->execute([$viewSignupsFor]);
    $signups = $stmt->fetchAll();
} else {
    // --- Fetch all missions owned by this admin ---
    $adminId = getUserId();
    $stmt = $pdo->prepare("
        SELECT m.*, 
        (SELECT COUNT(*) FROM signups WHERE mission_id = m.id) as signup_count,
        (SELECT COUNT(*) FROM signups WHERE mission_id = m.id AND completed = 1) as completed_count
        FROM missions m
        WHERE m.admin_id = ?
        ORDER BY m.mission_date DESC
    ");
    $stmt->execute([$adminId]);
    $missions = $stmt->fetchAll();
    
    // --- If view=signups → fetch all signups across admin’s missions ---
    if ($view === 'signups') {
        $stmt = $pdo->prepare("
            SELECT s.*, u.full_name, u.email, m.title as mission_title, m.mission_date
            FROM signups s
            JOIN users u ON s.user_id = u.id
            JOIN missions m ON s.mission_id = m.id
            WHERE m.admin_id = ?
            ORDER BY s.signup_date DESC
        ");
        $stmt->execute([$adminId]);
        $allSignups = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Missions - QuickServe</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo">🍃 QuickServe Admin</div>
            <nav>
                <a href="admin-dashboard.php">Dashboard</a>
                <a href="create-mission.php">Create Mission</a>
                <a href="manage-missions.php" class="active">Manage</a>
                <a href="reports.php">Reports</a>
            </nav>
            <div class="user-menu">
                <span>👤 <?php echo htmlspecialchars(getUserName()); ?></span>
                <a href="logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <a href="admin-dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($viewSignupsFor): ?>
            <!-- View signups for a specific mission -->
            <div class="page-header">
                <h1>Mission Signups</h1>
                <a href="manage-missions.php" class="btn btn-secondary">Back to Missions</a>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Volunteer Name</th>
                            <th>Email</th>
                            <th>Signup Date</th>
                            <th>Status</th>
                            <th>Completed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($signups)): ?>
                            <tr><td colspan="6" class="text-center">No signups yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($signups as $signup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($signup['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($signup['email']); ?></td>
                                    <td><?php echo formatDate($signup['signup_date']); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($signup['status']); ?></span></td>
                                    <td><?php echo $signup['completed'] ? '✓ Yes' : '✗ No'; ?></td>
                                    <td>
                                        <!-- Show Mark Completed button only if not yet completed -->
                                        <?php if (!$signup['completed']): ?>
                                            <a href="?complete=<?php echo $signup['id']; ?>" 
                                               onclick="return confirm('Mark this volunteer as completed?')" 
                                               class="btn btn-small btn-success">Mark Completed</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($view === 'signups'): ?>
            <!-- View all signups across admin’s missions -->
            <div class="page-header">
                <h1>All Signups</h1>
                <div>
                    <a href="manage-missions.php" class="btn btn-secondary">View Missions</a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mission</th>
                            <th>Volunteer</th>
                            <th>Email</th>
                            <th>Date</th>
                            <th>Signup Date</th>
                            <th>Status</th>
                            <th>Completed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allSignups)): ?>
                            <tr><td colspan="8" class="text-center">No signups yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($allSignups as $signup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($signup['mission_title']); ?></td>
                                    <td><?php echo htmlspecialchars($signup['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($signup['email']); ?></td>
                                    <td><?php echo formatDate($signup['mission_date']); ?></td>
                                    <td><?php echo formatDate($signup['signup_date']); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($signup['status']); ?></span></td>
                                    <td><?php echo $signup['completed'] ? '✓ Yes' : '✗ No'; ?></td>
                                    <td>
                                        <!-- Show Mark Completed button only if not yet completed -->
                                        <?php if (!$signup['completed']): ?>
                                            <a href="?complete=<?php echo $signup['id']; ?>" 
                                               onclick="return confirm('Mark this volunteer as completed?')" 
                                               class="btn btn-small btn-success">Mark Completed</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Default view: Manage Missions -->
            <div class="page-header">
                <h1>Manage Missions</h1>
                <div>
                    <a href="?export=csv" class="btn btn-secondary">📥 Export CSV</a>
                    <a href="?view=signups" class="btn btn-outline">View All Signups</a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mission Name</th>
                            <th>Date</th>
                            <th>Slots</th>
                            <th>Signups</th>
                            <th>Completed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($missions)): ?>
                            <tr><td colspan="7" class="text-center">No missions created yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($missions as $mission): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($mission['title']); ?></td>
                                    <td><?php echo formatDate($mission['mission_date']); ?></td>
                                    <td><?php echo $mission['total_slots']; ?></td>
                                    <td><?php echo $mission['signup_count']; ?></td>
                                    <td><?php echo $mission['completed_count']; ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($mission['status']); ?></span></td>
                                    <td class="actions">
                                        <a href="create-mission.php?id=<?php echo $mission['id']; ?>" class="btn btn-small">Edit</a>
                                        <a href="?view_signups=<?php echo $mission['id']; ?>" class="btn btn-small btn-secondary">View Signups</a>
                                        <a href="?delete=<?php echo $mission['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this mission?')" 
                                           class="btn btn-small btn-danger">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
