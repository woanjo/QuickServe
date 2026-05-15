<?php
require_once 'config/database.php';   
require_once 'includes/functions.php';

requireLogin(); 

$missionId = $_GET['id'] ?? null; // Mission ID from URL
$message = '';
$messageType = '';

// If no mission ID → redirect back to missions list
if (!$missionId) {
    redirect('missions.php');
}

// Handle reservation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    $userId = getUserId();
    
    // Check if user already reserved this mission
    $stmt = $pdo->prepare("SELECT id FROM signups WHERE user_id = ? AND mission_id = ?");
    $stmt->execute([$userId, $missionId]);
    
    if ($stmt->fetch()) {
        $message = 'You have already reserved this mission.';
        $messageType = 'error';
    } else {
        // Check available slots
        $stmt = $pdo->prepare("
            SELECT total_slots, 
            (SELECT COUNT(*) FROM signups WHERE mission_id = ?) as reserved_slots
            FROM missions WHERE id = ?
        ");
        $stmt->execute([$missionId, $missionId]);
        $slots = $stmt->fetch();
        
        if ($slots['reserved_slots'] >= $slots['total_slots']) {
            $message = 'Sorry, this mission is full.';
            $messageType = 'error';
        } else {
            // Insert new signup record
            $stmt = $pdo->prepare("INSERT INTO signups (user_id, mission_id) VALUES (?, ?)");
            if ($stmt->execute([$userId, $missionId])) {
                $message = 'Mission reserved successfully!';
                $messageType = 'success';
            }
        }
    }
}

// Fetch mission details + slots left + whether user reserved
$stmt = $pdo->prepare("
    SELECT m.*, 
    (m.total_slots - COALESCE((SELECT COUNT(*) FROM signups WHERE mission_id = m.id), 0)) as slots_left,
    (SELECT COUNT(*) > 0 FROM signups WHERE mission_id = m.id AND user_id = ?) as is_reserved
    FROM missions m 
    WHERE m.id = ?
");
$stmt->execute([getUserId(), $missionId]);
$mission = $stmt->fetch();

// If mission not found → redirect
if (!$mission) {
    redirect('missions.php');
}

// Fetch recommended missions (same category, active, not this mission)
$stmt = $pdo->prepare("
    SELECT * FROM missions 
    WHERE id != ? AND category = ? AND status = 'active'
    ORDER BY mission_date ASC 
    LIMIT 3
");
$stmt->execute([$missionId, $mission['category']]);
$recommended = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($mission['title']); ?> - QuickServe</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo">🍃 QuickServe</div>
            <nav>
                <a href="missions.php">Missions</a>
                <a href="student-dashboard.php">My Dashboard</a>
            </nav>
            <div class="user-menu">
                <span>👤 <?php echo htmlspecialchars(getUserName()); ?></span>
                <a href="logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <a href="missions.php" class="back-link">← Back to Missions</a>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="mission-details-layout">
            <div class="mission-main">
                <div class="mission-header">
                    <h1><?php echo htmlspecialchars($mission['title']); ?></h1>
                    <div class="mission-badges">
                        <span class="badge"><?php echo htmlspecialchars($mission['category']); ?></span>
                        <span class="badge badge-success"><?php echo $mission['slots_left']; ?> slots left</span>
                    </div>
                </div>
                
                <div class="mission-info-grid">
                    <div class="info-item">
                        <strong>Date:</strong>
                        <?php echo formatDate($mission['mission_date']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Time:</strong>
                        <?php echo formatTime($mission['mission_time']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Location:</strong>
                        <?php echo htmlspecialchars($mission['location']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Hours:</strong>
                        <?php echo number_format($mission['hours'], 1); ?> hours
                    </div>
                    <div class="info-item">
                        <strong>Contact:</strong>
                        <?php echo htmlspecialchars($mission['contact_person']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Skills:</strong>
                        <?php echo htmlspecialchars($mission['skills']); ?>
                    </div>
                </div>
                
                <div class="mission-description">
                    <h2>About This Mission</h2>
                    <p><?php echo nl2br(htmlspecialchars($mission['description'])); ?></p>
                </div>
                
                <?php if ($mission['is_reserved']): ?>
                    <button class="btn btn-large btn-disabled" disabled>Already Reserved</button>
                <?php elseif ($mission['slots_left'] <= 0): ?>
                    <button class="btn btn-large btn-disabled" disabled>Mission Full</button>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" name="reserve" class="btn btn-large btn-primary">Reserve Your Slot</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <aside class="mission-sidebar">
                <h3>Recommended Missions</h3>
                <?php if (empty($recommended)): ?>
                    <p>No recommendations available.</p>
                <?php else: ?>
                    <?php foreach ($recommended as $rec): ?>
                        <div class="recommended-card">
                            <h4><?php echo htmlspecialchars($rec['title']); ?></h4>
                            <p class="small"><?php echo formatDate($rec['mission_date']); ?> • <?php echo formatTime($rec['mission_time']); ?></p>
                            <a href="mission-details.php?id=<?php echo $rec['id']; ?>" class="btn btn-small">View Details</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>
        </div>
    </main>
    
</body>
</html>
