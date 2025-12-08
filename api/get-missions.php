<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$date = $_GET['date'] ?? '';
$location = $_GET['location'] ?? '';
$skills = $_GET['skills'] ?? '';

$query = "
    SELECT m.*, 
    (m.total_slots - COALESCE((SELECT COUNT(*) FROM signups WHERE mission_id = m.id), 0)) as slots_left
    FROM missions m
    WHERE m.status = 'active'
";

$params = [];

// if search keyword provided → filter by title or description
if ($search) {
    $query .= " AND (m.title LIKE ? OR m.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category) {
    $query .= " AND m.category = ?";
    $params[] = $category;
}

if ($date) {
    $query .= " AND m.mission_date = ?";
    $params[] = $date;
}

if ($location) {
    $query .= " AND m.location LIKE ?";
    $params[] = "%$location%";
}

if ($skills) {
    $query .= " AND m.skills LIKE ?";
    $params[] = "%$skills%";
}

$query .= " ORDER BY m.mission_date ASC, m.mission_time ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $missions = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'missions' => $missions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>