<?php
require_once 'config/database.php'; 
require_once 'includes/functions.php';

requireLogin(); // Ensure only logged-in users can access

// If logged-in user is an admin → redirect to admin dashboard
if (isAdmin()) {
    redirect('admin-dashboard.php');
}

$userId = getUserId(); // Get current logged-in student’s ID

// Query upcoming missions reserved by this student(excluded ang completed)
$stmt = $pdo->prepare("
    SELECT m.*, s.signup_date, s.completed, s.status
    FROM signups s
    JOIN missions m ON s.mission_id = m.id
    WHERE s.user_id = ? 
    AND m.mission_date >= CURRENT_DATE 
    AND s.completed = 0
    ORDER BY m.mission_date ASC, m.mission_time ASC
");
$stmt->execute([$userId]);
$upcomingMissions = $stmt->fetchAll();

// Query total volunteer hours completed by this student
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(m.hours), 0) as total_hours
    FROM signups s
    JOIN missions m ON s.mission_id = m.id
    WHERE s.user_id = ? AND s.completed = TRUE
");
$stmt->execute([$userId]);
$hoursData = $stmt->fetch();
$totalHours = $hoursData['total_hours'];

// completed mission list
$stmt = $pdo->prepare("
    SELECT m.*, s.signup_date, s.completed, s.status
    FROM signups s
    JOIN missions m ON s.mission_id = m.id
    WHERE s.user_id = ? AND s.completed = 1
    ORDER BY m.mission_date DESC
");
$stmt->execute([$userId]);
$completedMissions = $stmt->fetchAll();

// Handle CSV download of volunteer hours
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="volunteer_hours.csv"');
    
    $output = fopen('php://output', 'w');
    // CSV header row
    fputcsv($output, ['Mission Title','Date','Hours','Status','Completed']);
    
    // Query all missions signed up by this student
    $stmt = $pdo->prepare("
        SELECT m.title, m.mission_date, m.hours, s.status, s.completed
        FROM signups s
        JOIN missions m ON s.mission_id = m.id
        WHERE s.user_id = ?
        ORDER BY m.mission_date DESC
    ");
    $stmt->execute([$userId]);
    
    // Write each mission record to CSV
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['title'],
            $row['mission_date'],
            $row['hours'],
            $row['status'],
            $row['completed'] ? 'Yes' : 'No'
        ]);
    }
    
    fclose($output);
    exit; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - QuickServe</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo">🍃 QuickServe</div>
            <nav>
                <a href="missions.php">Missions</a>
                <a href="student-dashboard.php" class="active">My Dashboard</a>
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
                    <a class="sidebar-link active" data-tab="missions" onclick="switchTab('missions', event)">📋 My Missions</a>
                    <a class="sidebar-link" data-tab="hours" onclick="switchTab('hours', event)">⏱️ Hours</a>
                    <a class="sidebar-link" data-tab="profile" onclick="switchTab('profile', event)">👤 Profile</a>
                </nav>
            </aside>
            
            <div class="dashboard-main">
                <div class="page-header">
                    <h1>My Dashboard</h1>
                </div>
                
                <!-- MISSIONS TAB -->
                <div id="missions-tab" class="dashboard-tab active">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($upcomingMissions); ?></div>
                            <div class="stat-label">Active Missions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($totalHours, 1); ?></div>
                            <div class="stat-label">Verified Hours</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo ceil($totalHours / 10) * 10; ?></div>
                            <div class="stat-label">Hours Goal</div>
                        </div>
                    </div>
                    
                    <?php 
                    // Separate missions by status
                    $pendingMissions = [];
                    $approvedMissions = [];
                    $completedMissions = [];
                    
                    foreach ($upcomingMissions as $mission) {
                        if ($mission['status'] === 'pending') {
                            $pendingMissions[] = $mission;
                        } elseif ($mission['status'] === 'approved') {
                            $approvedMissions[] = $mission;
                        } elseif ($mission['status'] === 'rejected') {
                            continue;
                        }
                    }
                    
                    // Get completed missions
                    $stmt = $pdo->prepare("
                        SELECT m.*, s.signup_date, s.completed, s.status
                        FROM signups s
                        JOIN missions m ON s.mission_id = m.id
                        WHERE s.user_id = ? AND s.completed = 1
                        ORDER BY m.mission_date DESC
                    ");
                    $stmt->execute([$userId]);
                    $completedMissions = $stmt->fetchAll();
                    ?>
                    
                    <?php if (!empty($pendingMissions)): ?>
                    <div class="section">
                        <h2>Awaiting Approval</h2>
                        <p class="text-muted">These missions are pending admin approval to confirm your participation.</p>
                        <div class="missions-list">
                            <?php foreach ($pendingMissions as $mission): ?>
                                <div class="mission-list-item">
                                    <div class="mission-info">
                                        <h3><?php echo htmlspecialchars($mission['title']); ?></h3>
                                        <p class="mission-meta">
                                            📅 <?php echo formatDate($mission['mission_date']); ?> • 
                                            🕐 <?php echo formatTime($mission['mission_time']); ?> • 
                                            📍 <?php echo htmlspecialchars($mission['location']); ?>
                                        </p>
                                        <span class="badge" style="background-color: #FFA500; color: white;">Pending</span>
                                    </div>
                                    <div class="mission-actions">
                                        <a href="mission-details.php?id=<?php echo $mission['id']; ?>" class="btn btn-small">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($approvedMissions)): ?>
                    <div class="section">
                        <h2>Approved - Ready to Attend</h2>
                        <p class="text-muted">Your participation has been confirmed! These are your upcoming approved missions.</p>
                        <div class="missions-list">
                            <?php foreach ($approvedMissions as $mission): ?>
                                <div class="mission-list-item">
                                    <div class="mission-info">
                                        <h3><?php echo htmlspecialchars($mission['title']); ?></h3>
                                        <p class="mission-meta">
                                            📅 <?php echo formatDate($mission['mission_date']); ?> • 
                                            🕐 <?php echo formatTime($mission['mission_time']); ?> • 
                                            📍 <?php echo htmlspecialchars($mission['location']); ?>
                                        </p>
                                        <span class="badge" style="background-color: #28a745; color: white;">✅ Approved</span>
                                    </div>
                                    <div class="mission-actions">
                                        <a href="mission-details.php?id=<?php echo $mission['id']; ?>" class="btn btn-small">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($completedMissions)): ?>
                    <div class="section">
                        <h2>Completed & Verified</h2>
                        <p class="text-muted">Admin has confirmed your attendance and volunteer hours.</p>
                        <div class="missions-list">
                            <?php foreach ($completedMissions as $mission): ?>
                                <div class="mission-list-item">
                                    <div class="mission-info">
                                        <h3><?php echo htmlspecialchars($mission['title']); ?></h3>
                                        <p class="mission-meta">
                                            📅 <?php echo formatDate($mission['mission_date']); ?> • 
                                            ⏱️ <?php echo htmlspecialchars($mission['hours']); ?> hours • 
                                            📍 <?php echo htmlspecialchars($mission['location']); ?>
                                        </p>
                                        <!-- Badge shows that this mission is completed and verified -->
                                        <span class="badge" style="background-color: #17a2b8; color: white;">Completed</span>
                                    </div>
                                    <div class="mission-actions">
                                        <a href="mission-details.php?id=<?php echo $mission['id']; ?>" class="btn btn-small">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    
                    <?php if (empty($pendingMissions) && empty($approvedMissions) && empty($completedMissions)): ?>
                    <div class="section">
                        <p style="text-align: center; padding: 2rem; color: #666;">
                            You don't have any missions yet. <a href="missions.php">Browse available missions</a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- HOURS TAB -->
                <div id="hours-tab" class="dashboard-tab" style="display: none;">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($totalHours, 1); ?></div>
                            <div class="stat-label">Total Volunteer Hours</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo ceil($totalHours / 10) * 10; ?></div>
                            <div class="stat-label">Hours Goal</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($upcomingMissions); ?></div>
                            <div class="stat-label">Active Missions</div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2>Volunteer Hours Progress</h2>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($totalHours / 50) * 100); ?>%"></div>
                        </div>
                        <p class="progress-text"><?php echo number_format($totalHours, 1); ?> / 50 hours</p>
                    </div>
                    
                    <div class="section">
                        <h2>Hours Breakdown</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Mission</th>
                                    <th>Date</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $stmt = $pdo->prepare("
                                    SELECT m.title, m.mission_date, m.hours, s.status, s.completed
                                    FROM signups s
                                    JOIN missions m ON s.mission_id = m.id
                                    WHERE s.user_id = ?
                                    ORDER BY m.mission_date DESC
                                ");
                                $stmt->execute([$userId]);
                                $allMissions = $stmt->fetchAll();
                                
                                if (empty($allMissions)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 2rem;">No mission data yet</td>
                                    </tr>
                                <?php else:
                                    foreach ($allMissions as $mission):
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mission['title']); ?></td>
                                        <td><?php echo formatDate($mission['mission_date']); ?></td>
                                        <td><?php echo htmlspecialchars($mission['hours']); ?> hrs</td>
                                        <td>
                                            <span class="badge <?php echo $mission['completed'] ? 'badge-success' : ''; ?>">
                                                <?php echo $mission['completed'] ? 'Completed' : htmlspecialchars($mission['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="?download=csv" class="btn btn-secondary">📥 Download CSV</a>
                        <button onclick="generateCertificate()" class="btn btn-outline">📜 Download Certificate</button>
                    </div>
                </div>
                
                <!-- PROFILE TAB -->
                <div id="profile-tab" class="dashboard-tab" style="display: none;">
                    <div class="section">
                        <h2>Your Profile</h2>
                        <div class="profile-card">
                            <div class="profile-field">
                                <label>Full Name:</label>
                                <p><?php echo htmlspecialchars(getUserName()); ?></p>
                            </div>
                            <div class="profile-field">
                                <label>Email:</label>
                                <p><?php echo htmlspecialchars(getUserEmail()); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2>Your Volunteer Stats</h2>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo count($upcomingMissions); ?></div>
                                <div class="stat-label">Upcoming Missions</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php 
                                    $stmt = $pdo->prepare("
                                        SELECT COUNT(*) as count
                                        FROM signups
                                        WHERE user_id = ? AND completed = TRUE
                                    ");
                                    $stmt->execute([$userId]);
                                    echo $stmt->fetch()['count'];
                                ?></div>
                                <div class="stat-label">Completed Missions</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format($totalHours, 1); ?></div>
                                <div class="stat-label">Total Hours Logged</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2>Account Actions</h2>
                        <div class="action-buttons">
                            <button onclick="alert('Password change feature coming soon')" class="btn btn-outline">🔐 Change Password</button>
                            <a href="logout.php" class="btn btn-secondary">🚪 Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="js/main.js"></script>
    <script>
        function switchTab(tabName, event) {
            event.preventDefault();
            
            // Hide all tabs
            document.querySelectorAll('.dashboard-tab').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all sidebar links
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected tab
            const selectedTab = document.getElementById(tabName + '-tab');
            if (selectedTab) {
                selectedTab.style.display = 'block';
            }
            
            // Add active class to clicked sidebar link
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
