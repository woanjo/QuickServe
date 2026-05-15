<?php
require_once 'config/database.php';   // Load database connection
require_once 'includes/functions.php';// Load helper functions

requireAdmin(); // Restrict access to admins only

// Collect filter inputs from URL
$missionFilter = $_GET['mission'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$results = [];

// If user clicked "Generate" or "Export"
if (isset($_GET['generate']) || isset($_GET['export'])) {
    // Base query: join signups, missions, and users
    $query = "
        SELECT m.title, m.mission_date, m.mission_time, m.location, m.category, m.hours,
               u.full_name, u.email, s.signup_date, s.status, s.completed
        FROM signups s
        JOIN missions m ON s.mission_id = m.id
        JOIN users u ON s.user_id = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply mission filter
    if ($missionFilter) {
        $query .= " AND m.id = ?";
        $params[] = $missionFilter;
    }
    
    // Apply date filters
    if ($dateFrom) {
        $query .= " AND m.mission_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $query .= " AND m.mission_date <= ?";
        $params[] = $dateTo;
    }
    
    // Only include completed signups in reports
    $query .= " AND s.completed = 1";
    
    // Order results by mission date and signup date
    $query .= " ORDER BY m.mission_date DESC, s.signup_date DESC";
    
    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // If export requested → output CSV
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="volunteer_report.csv"');
        
        $output = fopen('php://output', 'w');
        // CSV header row
        fputcsv($output, ['Mission','Date','Time','Location','Category','Hours','Volunteer','Email','Signup Date','Status','Completed']);
        
        // Write each row
        foreach ($results as $row) {
            fputcsv($output, [
                $row['title'],$row['mission_date'],$row['mission_time'],$row['location'],
                $row['category'],$row['hours'],$row['full_name'],$row['email'],
                $row['signup_date'],$row['status'],$row['completed'] ? 'Yes' : 'No'
            ]);
        }
        
        fclose($output);
        exit; 
    }
}

// Fetch missions list for filter dropdown
$missions = $pdo->query("SELECT id, title FROM missions ORDER BY mission_date DESC")->fetchAll();

// calculate total verified hours for summary card
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(m.hours), 0) as total_hours
    FROM signups s
    JOIN missions m ON s.mission_id = m.id
    WHERE s.completed = 1
");
$stmt->execute();
$hoursData = $stmt->fetch();
$totalHours = $hoursData['total_hours'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - QuickServe</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo">🍃 QuickServe Admin</div>
            <nav>
                <a href="admin-dashboard.php">Dashboard</a>
                <a href="create-mission.php">Create Mission</a>
                <a href="manage-missions.php">Manage</a>
                <a href="reports.php" class="active">Reports</a>
            </nav>
            <div class="user-menu">
                <span>👤 <?php echo htmlspecialchars(getUserName()); ?></span>
                <a href="logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <a href="admin-dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1>Reports & Export</h1>
            <p>Generate and export volunteer activity reports</p>
        </div>
        
        <!-- Summary card for total verified hours -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($totalHours, 1); ?></div>
                <div class="stat-label">Total Verified Hours</div>
            </div>
        </div>
        
        <div class="section">
            <h2>Filter Reports</h2>
            <form method="GET" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Mission</label>
                        <select name="mission">
                            <option value="">All Missions</option>
                            <?php foreach ($missions as $m): ?>
                                <option value="<?php echo $m['id']; ?>" 
                                        <?php echo $missionFilter == $m['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="generate" class="btn btn-primary">Generate Report</button>
                    <button type="submit" name="export" value="csv" class="btn btn-secondary">📥 Export to CSV</button>
                    <a href="reports.php" class="btn btn-outline">Clear Filters</a>
                </div>
            </form>
        </div>
        
        <?php if (!empty($results)): ?>
            <div class="section">
                <div class="section-header">
                    <h2>Report Results</h2>
                    <p><?php echo count($results); ?> records found</p>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Mission</th>
                                <th>Date</th>
                                <th>Volunteer</th>
                                <th>Email</th>
                                <th>Hours</th>
                                <th>Status</th>
                                <th>Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo formatDate($row['mission_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo number_format($row['hours'], 1); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                    <td><?php echo $row['completed'] ? '✓ Yes' : '✗ No'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif (isset($_GET['generate'])): ?>
            <div class="section">
                <p class="text-center">No results found for the selected filters.</p>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>
