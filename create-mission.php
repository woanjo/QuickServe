<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireAdmin(); // make sure nga only admins ray maka access ani nga page

$missionId = $_GET['id'] ?? null;
$mission = null;
$isEdit = false;
$message = '';
$messageType = '';

// Check if ang mission ID exists in URL 
// if nag exist, ma load mission data and ma switch to edit mode
if ($missionId) {
    $adminId = getUserId();
    $stmt = $pdo->prepare("SELECT * FROM missions WHERE id = ? AND admin_id = ?");
    $stmt->execute([$missionId, $adminId]);
    $mission = $stmt->fetch();
    
    if (!$mission) {
        $message = 'You do not have permission to edit this mission!';
        $messageType = 'error';
        $missionId = null;
    } else {
        $isEdit = true;
    }
}

   
// e handle form submission
// mag collect ug inputs, validate sa required fields, and prepare for save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // mag collect ug form inputs
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $missionDate = $_POST['mission_date'];
    $missionTime = $_POST['mission_time'];
    $location = trim($_POST['location']);
    $totalSlots = (int)$_POST['total_slots'];
    $category = trim($_POST['category']);
    $skills = trim($_POST['skills']);
    $contactPerson = trim($_POST['contact_person']);
    $hours = (float)$_POST['hours'];
    
    $errors = [];
    
    // e validate/check ang required fields
    if (empty($title)) $errors[] = 'Title is required';
    if (empty($description)) $errors[] = 'Description is required';
    if (empty($missionDate)) $errors[] = 'Date is required';
    if (empty($missionTime)) $errors[] = 'Time is required';
    if (empty($location)) $errors[] = 'Location is required';
    if ($totalSlots <= 0) $errors[] = 'Slots must be a positive number';
    if ($hours <= 0) $errors[] = 'Hours must be a positive number';

// mag save ug mission 
// if edit mode siya, e update lang ang existing record, else insert a new mission
    if (empty($errors)) {
        if ($isEdit) {
            // update ug mission
            $stmt = $pdo->prepare("
                UPDATE missions 
                SET title = ?, description = ?, mission_date = ?, mission_time = ?, 
                    location = ?, total_slots = ?, category = ?, skills = ?, 
                    contact_person = ?, hours = ?
                WHERE id = ?
            ");
            $success = $stmt->execute([
                $title, $description, $missionDate, $missionTime, $location,
                $totalSlots, $category, $skills, $contactPerson, $hours, $missionId
            ]);
            
            if ($success) {
                $message = 'Mission updated successfully!';
                $messageType = 'success';
            }
        } else {
            // mag insert ug new mission
            $adminId = getUserId();
            $stmt = $pdo->prepare("
                INSERT INTO missions (admin_id, title, description, mission_date, mission_time, location, 
                                    total_slots, category, skills, contact_person, hours)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $success = $stmt->execute([
                $adminId, $title, $description, $missionDate, $missionTime, $location,
                $totalSlots, $category, $skills, $contactPerson, $hours
            ]);
            
            if ($success) {
                $message = 'Mission created successfully!';
                $messageType = 'success';
                $mission = null;
            }
        }
    } else {
        $message = implode(', ', $errors);
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Mission - QuickServe</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo">🍃 QuickServe Admin</div>
            <nav>
                <a href="admin-dashboard.php">Dashboard</a>
                <a href="create-mission.php" class="active">Create Mission</a>
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
        <a href="admin-dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1><?php echo $isEdit ? 'Edit' : 'Create New'; ?> Mission</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" class="mission-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Mission Title *</label>
                        <input type="text" name="title" required 
                               value="<?php echo htmlspecialchars($mission['title'] ?? ''); ?>" 
                               placeholder="e.g., Beach Cleanup Drive">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" required rows="4" 
                              placeholder="Describe the mission and what volunteers will do..."><?php echo htmlspecialchars($mission['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="mission_date" required 
                               value="<?php echo $mission['mission_date'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Time *</label>
                        <input type="time" name="mission_time" required 
                               value="<?php echo $mission['mission_time'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Location *</label>
                        <input type="text" name="location" required 
                               value="<?php echo htmlspecialchars($mission['location'] ?? ''); ?>" 
                               placeholder="e.g., Central Park">
                    </div>
                    
                    <div class="form-group">
                        <label>Total Slots *</label>
                        <input type="number" name="total_slots" required min="1" 
                               value="<?php echo $mission['total_slots'] ?? ''; ?>" 
                               placeholder="e.g., 25">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="">Select Category</option>
                            <option value="Environment" <?php echo ($mission['category'] ?? '') === 'Environment' ? 'selected' : ''; ?>>Environment</option>
                            <option value="Community Service" <?php echo ($mission['category'] ?? '') === 'Community Service' ? 'selected' : ''; ?>>Community Service</option>
                            <option value="Social" <?php echo ($mission['category'] ?? '') === 'Social' ? 'selected' : ''; ?>>Social</option>
                            <option value="Education" <?php echo ($mission['category'] ?? '') === 'Education' ? 'selected' : ''; ?>>Education</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Volunteer Hours *</label>
                        <input type="number" name="hours" required min="0.5" step="0.5" 
                               value="<?php echo $mission['hours'] ?? ''; ?>" 
                               placeholder="e.g., 2.5">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Required Skills</label>
                    <input type="text" name="skills" 
                           value="<?php echo htmlspecialchars($mission['skills'] ?? ''); ?>" 
                           placeholder="e.g., Teamwork, Physical Activity">
                </div>
                
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" 
                           value="<?php echo htmlspecialchars($mission['contact_person'] ?? ''); ?>" 
                           placeholder="e.g., John Doe">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $isEdit ? 'Save Changes' : 'Create Mission'; ?>
                    </button>
                    <a href="admin-dashboard.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </main>
    
</body>
</html>
