<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Collect filter parameters 
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$date = $_GET['date'] ?? '';
$location = $_GET['location'] ?? '';
$skills = $_GET['skills'] ?? '';

// Base query: select missions and calculate remaining slots
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

// if category filter provided → match exact category
if ($category) {
    $query .= " AND m.category = ?";
    $params[] = $category;
}

// if date filter provided → match exact mission date
if ($date) {
    $query .= " AND m.mission_date = ?";
    $params[] = $date;
}

// if location filter provided → partial match on location
if ($location) {
    $query .= " AND m.location LIKE ?";
    $params[] = "%$location%";
}

// if skills filter provided → partial match on skills
if ($skills) {
    $query .= " AND m.skills LIKE ?";
    $params[] = "%$skills%";
}

// order results by mission date and time ascending
$query .= " ORDER BY m.mission_date ASC, m.mission_time ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $missions = $stmt->fetchAll(); // Fetch all matching missions
    
    echo json_encode(['success' => true, 'missions' => $missions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>