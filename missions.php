<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

if (isAdmin()) {
    redirect('admin-dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Missions - QuickServe</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo">🍃 QuickServe</div>
            <nav>
                <a href="missions.php" class="active">Missions</a>
                <a href="student-dashboard.php">My Dashboard</a>
            </nav>
            <div class="user-menu">
                <span>👤 <?php echo htmlspecialchars(getUserName()); ?></span>
                <a href="logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="page-header">
            <h1>Available Volunteer Missions</h1>
            <p>Find meaningful ways to make a difference in your community</p>
        </div>
        
        <div class="search-filters">
            <input type="text" id="searchInput" placeholder="Search missions..." class="search-input">
            
            <div class="filters">
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="Environment">Environment</option>
                    <option value="Community Service">Community Service</option>
                    <option value="Social">Social</option>
                    <option value="Education">Education</option>
                </select>
                
                <input type="date" id="dateFilter" class="filter-input">
                
                <input type="text" id="locationFilter" placeholder="Location" class="filter-input">
                
                <input type="text" id="skillsFilter" placeholder="Skills" class="filter-input">
                
                <button onclick="applyFilters()" class="btn btn-secondary">Apply Filters</button>
                <button onclick="clearFilters()" class="btn btn-outline">Clear</button>
            </div>
        </div>
        
        <div id="missionsContainer" class="missions-grid">
            <div class="loading">Loading missions...</div>
        </div>
    </main>

    
    <script src="js/main.js"></script>
    <script>
        loadMissions();
        
        document.getElementById('searchInput').addEventListener('input', applyFilters);
    </script>
</body>
</html>