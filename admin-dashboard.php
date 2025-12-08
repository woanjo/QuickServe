<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireAdmin();

// Handle approve/reject signups
// admin can approve or reject volunteer requests
if (isset($_POST['action_signup']) && isset($_POST['signup_id'])) {
    $signupId = $_POST['signup_id'];
    $action = $_POST['action_signup'];
    $adminId = getUserId();
    
    // Verify this is admin's mission
    $checkStmt = $pdo->prepare("
        SELECT m.admin_id FROM signups s
        JOIN missions m ON s.mission_id = m.id
        WHERE s.id = ?
    ");
    $checkStmt->execute([$signupId]);
    $signupData = $checkStmt->fetch();
    
    if ($signupData && $signupData['admin_id'] == $adminId) {
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE signups SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $signupId]);
        $success = "Signup " . ($newStatus === 'approved' ? 'approved' : 'rejected') . " successfully!";
    }
}

// Handle confirm completion 
// admin confirms volunteer finished mission hours
if (isset($_POST['confirm_completion']) && isset($_POST['signup_id'])) {
    $signupId = $_POST['signup_id'];
    $adminId = getUserId();
    
    // Verify this is admin's mission
    $checkStmt = $pdo->prepare("
        SELECT m.admin_id FROM signups s
        JOIN missions m ON s.mission_id = m.id
        WHERE s.id = ?
    ");
    $checkStmt->execute([$signupId]);
    $signupData = $checkStmt->fetch();
    
    if ($signupData && $signupData['admin_id'] == $adminId) {
        $stmt = $pdo->prepare("UPDATE signups SET completed = 1 WHERE id = ?");
        $stmt->execute([$signupId]);
        $success = "Volunteer hours confirmed successfully!";
    }
}

// Handle delete mission 
// admin can delete mission and related signups
if (isset($_POST['delete_mission'])) {
    $missionId = $_POST['mission_id'] ?? null;
    $adminId = getUserId();
    
    if ($missionId) {
        try {
            // Check if ang admin owns ani nga mission
            $checkStmt = $pdo->prepare("SELECT admin_id FROM missions WHERE id = ?");
            $checkStmt->execute([$missionId]);
            $missionData = $checkStmt->fetch();
            
            if (!$missionData || $missionData['admin_id'] != $adminId) {
                $error = "You do not have permission to delete this mission!";
            } else {
                // Delete related signups first
                $stmt = $pdo->prepare("DELETE FROM signups WHERE mission_id = ?");
                $stmt->execute([$missionId]);
                
                // Then delete mission
                $stmt = $pdo->prepare("DELETE FROM missions WHERE id = ?");
                $stmt->execute([$missionId]);
                
                $success = "Mission deleted successfully!";
            }
        } catch (Exception $e) {
            $error = "Error deleting mission: " . $e->getMessage();
        }
    }
}

$adminId = getUserId();

// e count ang total missions nga gi created ani nga admin
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM missions WHERE admin_id = ?");
$stmt->execute([$adminId]);
$totalMissions = $stmt->fetch()['total'];

// e count ang pending signups nga nag need ug approval
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM signups 
    WHERE status = 'pending' 
    AND mission_id IN (SELECT id FROM missions WHERE admin_id = ?)
");
$stmt->execute([$adminId]);
$pendingVerification = $stmt->fetch()['total'];

// e count ang volunteers scheduled today
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.user_id) as total 
    FROM signups s
    JOIN missions m ON s.mission_id = m.id
    WHERE m.mission_date = CURRENT_DATE AND m.admin_id = ?
");
$stmt->execute([$adminId]);
$volunteersToday = $stmt->fetch()['total'];

// get recent missions list
$stmt = $pdo->prepare("
    SELECT m.*, 
    (SELECT COUNT(*) FROM signups WHERE mission_id = m.id) as signup_count
    FROM missions m
    WHERE m.admin_id = ?
    ORDER BY m.created_at DESC
    LIMIT 10
");
$stmt->execute([$adminId]);
$recentMissions = $stmt->fetchAll();

// get pending signups for this admin
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email, m.title as mission_title, m.mission_date
    FROM signups s
    JOIN users u ON s.user_id = u.id
    JOIN missions m ON s.mission_id = m.id
    WHERE s.status = 'pending' AND m.admin_id = ?
    ORDER BY s.signup_date DESC
    LIMIT 5
");
$stmt->execute([$adminId]);
$pendingSignups = $stmt->fetchAll();

// get approved signups waiting for completion
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email, m.title as mission_title, m.hours, m.mission_date
    FROM signups s
    JOIN users u ON s.user_id = u.id
    JOIN missions m ON s.mission_id = m.id
    WHERE s.status = 'approved' AND s.completed = 0 AND m.admin_id = ?
    ORDER BY m.mission_date ASC
    LIMIT 5
");
$stmt->execute([$adminId]);
$completionPending = $stmt->fetchAll();

// Get completed signups (this month)
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, m.title as mission_title, m.hours, m.mission_date
    FROM signups s
    JOIN users u ON s.user_id = u.id
    JOIN missions m ON s.mission_id = m.id
    WHERE s.completed = 1 AND m.admin_id = ?
    AND MONTH(m.mission_date) = MONTH(CURRENT_DATE)
    AND YEAR(m.mission_date) = YEAR(CURRENT_DATE)
    ORDER BY s.signup_date DESC
    LIMIT 5
");
$stmt->execute([$adminId]);
$completedSignups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - QuickServe</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo">🍃 QuickServe Admin</div>
            <nav>
                <a href="admin-dashboard.php" class="active">Dashboard</a>
                <a href="create-mission.php">Create Mission</a>
                <a href="manage-missions.php">Manage</a>
                <a href="reports.php">Reports</a>
            </nav>
            <div class="user-menu">
                <span>👤 <?php echo htmlspecialchars(getUserName()); ?></span>
                <a href="logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="dashboard-layout">
            <aside class="dashboard-sidebar">
                <nav class="sidebar-nav">
                    <a href="admin-dashboard.php" class="sidebar-link active">📊 Dashboard</a>
                    <a href="create-mission.php" class="sidebar-link">➕ Create Mission</a>
                    <a href="manage-missions.php" class="sidebar-link">📋 Manage Missions</a>
                    <a href="manage-missions.php?view=signups" class="sidebar-link">👥 View Signups</a>
                    <a href="reports.php" class="sidebar-link">📈 Reports</a>
                </nav>
            </aside>
            
            <div class="dashboard-main">
                <div class="page-header">
                    <h1>Admin Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars(getUserName()); ?></p>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-card stat-card-primary">
                        <div class="stat-icon">📋</div>
                        <div class="stat-value"><?php echo $totalMissions; ?></div>
                        <div class="stat-label">Total Missions</div>
                    </div>
                    
                    <div class="stat-card stat-card-warning">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-value"><?php echo $pendingVerification; ?></div>
                        <div class="stat-label">Pending Verification</div>
                    </div>
                    
                    <div class="stat-card stat-card-success">
                        <div class="stat-icon">👥</div>
                        <div class="stat-value"><?php echo $volunteersToday; ?></div>
                        <div class="stat-label">Volunteers Today</div>
                    </div>
                </div>
                
                <?php if (!empty($pendingSignups)): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>⏳ Pending Approvals (<?php echo count($pendingSignups); ?>)</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Mission</th>
                                    <th>Date</th>
                                    <th>Signup Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingSignups as $signup): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($signup['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($signup['email']); ?></td>
                                        <td><?php echo htmlspecialchars($signup['mission_title']); ?></td>
                                        <td><?php echo formatDate($signup['mission_date']); ?></td>
                                        <td><?php echo date('M d, H:i', strtotime($signup['signup_date'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="signup_id" value="<?php echo $signup['id']; ?>">
                                                <button type="submit" name="action_signup" value="approve" class="btn btn-small btn-success">✅ Approve</button>
                                                <button type="submit" name="action_signup" value="reject" class="btn btn-small btn-danger">❌ Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($completionPending)): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>✅ Confirm Volunteer Completion (<?php echo count($completionPending); ?>)</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Mission</th>
                                    <th>Date</th>
                                    <th>Hours</th>
                                    <th>Approved Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completionPending as $signup): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($signup['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($signup['mission_title']); ?></td>
                                        <td><?php echo formatDate($signup['mission_date']); ?></td>
                                        <td><strong><?php echo $signup['hours']; ?> hrs</strong></td>
                                        <td><?php echo date('M d', strtotime($signup['signup_date'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="signup_id" value="<?php echo $signup['id']; ?>">
                                                <button type="submit" name="confirm_completion" class="btn btn-small btn-success">✅ Confirm Hours</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($completedSignups)): ?>
                <div class="section">
                    <div class="section-header">
                        <h2>📊 Completed This Month (<?php echo count($completedSignups); ?>)</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Mission</th>
                                    <th>Date</th>
                                    <th>Hours</th>
                                    <th>Completed Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalHours = 0;
                                foreach ($completedSignups as $signup): 
                                    $totalHours += $signup['hours'];
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($signup['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($signup['mission_title']); ?></td>
                                        <td><?php echo formatDate($signup['mission_date']); ?></td>
                                        <td><?php echo $signup['hours']; ?> hrs</td>
                                        <td><?php echo date('M d, H:i', strtotime($signup['signup_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="background-color: #f0f0f0; font-weight: bold;">
                                    <td colspan="3">Total Volunteer Hours</td>
                                    <td colspan="2"><?php echo $totalHours; ?> hours</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="section">
                    <div class="section-header">
                        <h2>All Missions</h2>
                        <a href="create-mission.php" class="btn btn-primary">➕ Create New Mission</a>
                    </div>
                    
                    <?php if (empty($recentMissions)): ?>
                        <p>No missions created yet. <a href="create-mission.php">Create your first mission</a></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Mission Title</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Slots</th>
                                        <th>Signups</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentMissions as $mission): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($mission['title']); ?></td>
                                            <td>
                                                <?php echo formatDate($mission['mission_date']); ?><br>
                                                <small><?php echo formatTime($mission['mission_time']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($mission['location']); ?></td>
                                            <td><?php echo $mission['total_slots']; ?></td>
                                            <td><?php echo $mission['signup_count']; ?></td>
                                            <td><span class="badge"><?php echo htmlspecialchars($mission['status']); ?></span></td>
                                            <td>
                                                <a href="create-mission.php?id=<?php echo $mission['id']; ?>" class="btn btn-small">✏️ Edit</a>
                                                <a href="manage-missions.php?view_signups=<?php echo $mission['id']; ?>" class="btn btn-small btn-secondary">👥 Signups</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this mission?');">
                                                    <input type="hidden" name="mission_id" value="<?php echo $mission['id']; ?>">
                                                    <button type="submit" name="delete_mission" class="btn btn-small btn-danger">🗑️ Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="section">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions">
                        <a href="create-mission.php" class="quick-action-card">
                            <div class="action-icon">➕</div>
                            <div class="action-title">Create Mission</div>
                        </a>
                        <a href="manage-missions.php" class="quick-action-card">
                            <div class="action-icon">📋</div>
                            <div class="action-title">Manage Missions</div>
                        </a>
                        <a href="reports.php" class="quick-action-card">
                            <div class="action-icon">📊</div>
                            <div class="action-title">View Reports</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
</body>
</html>
