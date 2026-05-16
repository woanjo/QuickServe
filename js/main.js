// Form toggle between log in and register
function showLogin() {
    document.getElementById('loginForm').style.display = 'flex';
    document.getElementById('registerForm').style.display = 'none';
    document.querySelectorAll('.toggle-btn')[0].classList.add('active');
    document.querySelectorAll('.toggle-btn')[1].classList.remove('active');
}

//
function showRegister() {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('registerForm').style.display = 'flex';
    document.querySelectorAll('.toggle-btn')[0].classList.remove('active');
    document.querySelectorAll('.toggle-btn')[1].classList.add('active');
}

// LOAD MISSIONS VIA AJAX
function loadMissions() {
    const container = document.getElementById('missionsContainer');
    if (!container) return;
    
    // Get all filter values from the search form
    const searchTerm = document.getElementById('searchInput')?.value || '';
    const category = document.getElementById('categoryFilter')?.value || '';
    const date = document.getElementById('dateFilter')?.value || '';
    const location = document.getElementById('locationFilter')?.value || '';
    const skills = document.getElementById('skillsFilter')?.value || '';
    
    const params = new URLSearchParams({
        search: searchTerm,
        category: category,
        date: date,
        location: location,
        skills: skills
    });
    
    // Fetch missions from API
    fetch(`./api/get-missions.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success || data.missions.length === 0) {
                container.innerHTML = '<div class="loading">No missions found.</div>';
                return;
            }
            
            // Generate HTML for each mission card
            container.innerHTML = data.missions.map(mission => `
                <div class="mission-card">
                    <h3>${escapeHtml(mission.title)}</h3>
                    <p>${escapeHtml(mission.description.substring(0, 120))}...</p>
                    <p class="mission-meta">
                        📅 ${formatDate(mission.mission_date)} • 
                        🕐 ${formatTime(mission.mission_time)}<br>
                        📍 ${escapeHtml(mission.location)}
                    </p>
                    <div class="mission-badges">
                        <span class="badge">${escapeHtml(mission.category)}</span>
                        <span class="badge badge-success">${mission.slots_left} slots left</span>
                    </div>
                    <a href="mission-details.php?id=${mission.id}" class="btn btn-primary" style="margin-top: 1rem;">View Details</a>
                </div>
            `).join('');
        })
        .catch(error => {
            console.error('Error loading missions:', error);
            container.innerHTML = '<div class="loading">Error loading missions. Please try again.</div>';
        });
}

// Apply filters and reload missions
function applyFilters() {
    loadMissions();
}

// Clear all filters and reload missions
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('dateFilter').value = '';
    document.getElementById('locationFilter').value = '';
    document.getElementById('skillsFilter').value = '';
    loadMissions();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format date for display (Jan 15, 2025)
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Format time for display (2:30 PM)
function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}
