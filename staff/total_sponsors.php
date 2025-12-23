<?php
session_start();

// Remove this test data line - it's overwriting real login sessions!
// $_SESSION['user_id'] = 1;  // ← DELETED

require_once __DIR__ . '/../db_config.php';

// Handle AJAX request for sponsors
if (isset($_GET['action']) && $_GET['action'] === 'get_sponsors') {
    header('Content-Type: application/json');
    
    try {
        // Get all sponsors with their sponsorship counts
        $sponsors_query = "
            SELECT 
                s.sponsor_id,
                s.user_id,
                s.first_name,
                s.last_name,
                s.dob,
                s.address,
                s.is_flagged,
                s.flag_reason,
                s.profile_picture,
                u.email,
                u.phone_no,
                u.created_at as registration_date,
                TIMESTAMPDIFF(YEAR, s.dob, CURDATE()) as age,
                COUNT(DISTINCT sp.child_id) as children_sponsored
            FROM sponsors s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN sponsorships sp ON s.sponsor_id = sp.sponsor_id 
                AND (sp.end_date IS NULL OR sp.end_date > CURDATE())
            GROUP BY s.sponsor_id
            ORDER BY u.created_at DESC
        ";

        $stmt = $conn->prepare($sponsors_query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sponsors = [];
        $active_count = 0;
        $inactive_count = 0;
        
        while ($row = $result->fetch_assoc()) {
            // Format dates
            if ($row['dob']) {
                $row['dob_formatted'] = date('F j, Y', strtotime($row['dob']));
            } else {
                $row['dob_formatted'] = 'N/A';
            }
            
            if ($row['registration_date']) {
                $row['registration_date_formatted'] = date('F j, Y', strtotime($row['registration_date']));
                
                // Calculate membership duration
                $registered = new DateTime($row['registration_date']);
                $now = new DateTime();
                $interval = $registered->diff($now);
                
                $years = $interval->y;
                $months = $interval->m;
                
                if ($years > 0) {
                    $row['membership_duration'] = $years . ' year' . ($years > 1 ? 's' : '');
                    if ($months > 0) {
                        $row['membership_duration'] .= ', ' . $months . ' month' . ($months > 1 ? 's' : '');
                    }
                } else if ($months > 0) {
                    $row['membership_duration'] = $months . ' month' . ($months > 1 ? 's' : '');
                } else {
                    $days = $interval->days;
                    $row['membership_duration'] = $days . ' day' . ($days > 1 ? 's' : '');
                }
            } else {
                $row['registration_date_formatted'] = 'N/A';
                $row['membership_duration'] = 'N/A';
            }
            
            // Generate initials
            $row['initials'] = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
            
            // Count active vs inactive sponsors
            if ($row['children_sponsored'] > 0) {
                $active_count++;
            } else {
                $inactive_count++;
            }
            
            $sponsors[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        // Calculate stats
        $total_count = count($sponsors);
        
        echo json_encode([
            'success' => true,
            'data' => $sponsors,
            'stats' => [
                'total_count' => $total_count,
                'active_count' => $active_count,
                'inactive_count' => $inactive_count
            ]
        ]);
        exit();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching sponsors: ' . $e->getMessage()
        ]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Total Sponsors</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ffffff;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Bright Yellow Splash/Aura Effects */
        body::before {
            content: '';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 150%;
            height: 150%;
            background: radial-gradient(circle at center, rgba(255, 237, 160, 0.8) 0%, rgba(254, 249, 195, 0.6) 15%, rgba(255, 253, 240, 0.4) 30%, transparent 60%);
            pointer-events: none;
            z-index: 0;
            filter: blur(80px);
        }

        body::after {
            content: '';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(255, 243, 176, 0.5) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
            filter: blur(120px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(254, 240, 138, 0.4);
            color: #3f3f46;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 2rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(254, 240, 138, 0.15);
        }

        .back-btn:hover {
            transform: translateX(-5px);
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 8px 16px rgba(254, 240, 138, 0.3);
            border-color: rgba(254, 240, 138, 0.6);
        }

        .page-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #18181b;
            letter-spacing: -0.02em;
        }

        .page-subtitle {
            font-size: 1rem;
            color: #71717a;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 48px rgba(254, 240, 138, 0.35);
        }

        .stat-card.primary {
            background: linear-gradient(135deg, rgba(254, 249, 195, 0.8), rgba(253, 230, 138, 0.7));
            border: 1px solid rgba(254, 240, 138, 0.5);
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #71717a;
            margin-bottom: 0.75rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #18181b;
        }

        .controls-section {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .controls-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1.5rem;
            align-items: center;
        }

        .search-box {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid rgba(254, 240, 138, 0.4);
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: rgba(255, 255, 255, 0.7);
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: rgba(254, 240, 138, 0.8);
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 4px rgba(254, 249, 195, 0.3);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
            opacity: 0.5;
        }

        .filter-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .filter-btn {
            padding: 0.875rem 1.5rem;
            border: 2px solid rgba(254, 240, 138, 0.4);
            background: rgba(255, 255, 255, 0.7);
            color: #3f3f46;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .filter-btn:hover {
            background: rgba(255, 255, 255, 1);
            border-color: rgba(254, 240, 138, 0.6);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, rgba(254, 249, 195, 0.9), rgba(253, 230, 138, 0.8));
            color: #18181b;
            border-color: rgba(254, 240, 138, 0.6);
            box-shadow: 0 4px 12px rgba(254, 240, 138, 0.4);
        }

        .sponsors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .sponsor-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .sponsor-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(254, 240, 138, 0.4);
            background: rgba(255, 255, 255, 1);
            border-color: rgba(254, 240, 138, 0.5);
        }

        .sponsor-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, rgba(254, 249, 195, 0.9), rgba(253, 230, 138, 0.8));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #3f3f46;
            font-weight: 700;
            box-shadow: 0 8px 24px rgba(254, 240, 138, 0.4);
        }

        .sponsor-info {
            text-align: center;
        }

        .sponsor-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #18181b;
            margin-bottom: 1rem;
            letter-spacing: -0.01em;
        }

        .sponsor-details {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: rgba(254, 252, 232, 0.5);
            border-radius: 16px;
            border: 1px solid rgba(254, 240, 138, 0.3);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
        }

        .detail-label {
            color: #71717a;
            font-weight: 600;
        }

        .detail-value {
            color: #18181b;
            font-weight: 700;
        }

        .detail-value.email {
            font-size: 0.85rem;
            word-break: break-all;
        }

        .sponsorship-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid rgba(254, 240, 138, 0.3);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .children-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .children-badge.inactive {
            background: rgba(161, 161, 170, 0.1);
            color: #71717a;
            border: 1px solid rgba(161, 161, 170, 0.2);
        }

        .member-since {
            font-size: 0.875rem;
            color: #71717a;
            font-weight: 500;
        }

        .view-btn {
            width: 100%;
            margin-top: 1.5rem;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(254, 249, 195, 0.9), rgba(253, 230, 138, 0.8));
            color: #18181b;
            border: 1px solid rgba(254, 240, 138, 0.5);
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(254, 240, 138, 0.3);
        }

        .view-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 24px rgba(254, 240, 138, 0.5);
            background: linear-gradient(135deg, rgba(254, 249, 195, 1), rgba(253, 230, 138, 0.9));
        }

        .loading {
            text-align: center;
            padding: 4rem 2rem;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(254, 240, 138, 0.3);
            border-top: 4px solid rgba(253, 230, 138, 0.8);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 1.1rem;
            color: #71717a;
            font-weight: 600;
        }

        .empty-state {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 24px;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .empty-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #18181b;
            margin-bottom: 0.75rem;
        }

        .empty-message {
            font-size: 1rem;
            color: #71717a;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .controls-grid {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                width: 100%;
                justify-content: center;
            }

            .sponsors-grid {
                grid-template-columns: 1fr;
            }

            .stat-value {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="javascript:history.back()" class="back-btn">
            ← Back to Dashboard
        </a>

        <div class="page-header">
            <h1 class="page-title">Total Sponsors</h1>
            <p class="page-subtitle">Honoring the generous individuals making a difference in children's lives</p>
        </div>

        <div class="stats-container">
            <div class="stat-card primary">
                <div class="stat-label">Total Sponsors</div>
                <div class="stat-value" id="totalCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Sponsors</div>
                <div class="stat-value" id="activeCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Inactive Sponsors</div>
                <div class="stat-value" id="inactiveCount">0</div>
            </div>
        </div>

        <div class="controls-section">
            <div class="controls-grid">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" 
                           class="search-input" 
                           id="searchInput" 
                           placeholder="Search by sponsor name or email...">
                </div>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="active">Active</button>
                    <button class="filter-btn" data-filter="inactive">Inactive</button>
                </div>
            </div>
        </div>

        <div class="loading" id="loadingState">
            <div class="loading-spinner"></div>
            <p class="loading-text">Loading sponsors...</p>
        </div>

        <div class="sponsors-grid" id="sponsorsGrid" style="display: none;"></div>

        <div class="empty-state" id="emptyState" style="display: none;">
            <div class="empty-icon">📭</div>
            <h2 class="empty-title">No Sponsors Found</h2>
            <p class="empty-message">There are currently no registered sponsors in the system.</p>
        </div>
    </div>

    <script>
        let allSponsors = [];
        let currentFilter = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            fetchSponsors();
            setupEventListeners();
        });

        async function fetchSponsors() {
            const loadingState = document.getElementById('loadingState');
            const sponsorsGrid = document.getElementById('sponsorsGrid');
            const emptyState = document.getElementById('emptyState');
            
            try {
                loadingState.style.display = 'block';
                sponsorsGrid.style.display = 'none';
                emptyState.style.display = 'none';
                
                const response = await fetch(`?action=get_sponsors`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message || 'Failed to fetch data');
                }
                
                allSponsors = result.data || [];
                updateStatistics(result.stats);
                
                loadingState.style.display = 'none';
                
                if (allSponsors.length === 0) {
                    emptyState.style.display = 'block';
                } else {
                    sponsorsGrid.style.display = 'grid';
                    renderSponsorCards(allSponsors);
                }
                
            } catch (error) {
                console.error('Error fetching sponsors:', error);
                loadingState.style.display = 'none';
                emptyState.style.display = 'block';
                document.querySelector('.empty-title').textContent = 'Error Loading Data';
                document.querySelector('.empty-message').textContent = error.message || 'Please try again later.';
            }
        }

        function updateStatistics(stats) {
            if (!stats) return;
            document.getElementById('totalCount').textContent = stats.total_count || 0;
            document.getElementById('activeCount').textContent = stats.active_count || 0;
            document.getElementById('inactiveCount').textContent = stats.inactive_count || 0;
        }

        function renderSponsorCards(sponsors) {
            const sponsorsGrid = document.getElementById('sponsorsGrid');
            
            if (!sponsors || sponsors.length === 0) {
                sponsorsGrid.innerHTML = '<p class="empty-state">No sponsors match your search criteria.</p>';
                return;
            }
            
            sponsorsGrid.innerHTML = sponsors.map(sponsor => {
                const isActive = sponsor.children_sponsored > 0;
                const childrenText = sponsor.children_sponsored === 1 
                    ? '1 child' 
                    : `${sponsor.children_sponsored} children`;
                
                return `
                <div class="sponsor-card" data-name="${sponsor.first_name} ${sponsor.last_name}" data-email="${sponsor.email || ''}" data-active="${isActive}">
                    <div class="sponsor-photo">
                        ${sponsor.initials || '??'}
                    </div>
                    <div class="sponsor-info">
                        <div class="sponsor-name">${sponsor.first_name} ${sponsor.last_name}</div>
                        <div class="sponsor-details">
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value email">${sponsor.email || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value">${sponsor.phone_no || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Age:</span>
                                <span class="detail-value">${sponsor.age} years</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address:</span>
                                <span class="detail-value">${sponsor.address || 'N/A'}</span>
                            </div>
                        </div>
                        <div class="sponsorship-info">
                            <div class="children-badge ${!isActive ? 'inactive' : ''}">
                                ${isActive ? `✓ Sponsoring ${childrenText}` : '○ Not currently sponsoring'}
                            </div>
                            <div class="member-since">
                                Member since: ${sponsor.registration_date_formatted}
                            </div>
                            <div class="member-since">
                                Duration: ${sponsor.membership_duration}
                            </div>
                        </div>
                        <button class="view-btn" onclick="viewSponsorProfile(${sponsor.sponsor_id})">
                            View Profile
                        </button>
                    </div>
                </div>
            `}).join('');
        }

        function setupEventListeners() {
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', handleSearch);
            
            const filterButtons = document.querySelectorAll('.filter-btn');
            filterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentFilter = this.dataset.filter;
                    applyFilters();
                });
            });
        }

        function handleSearch(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            applyFilters(searchTerm);
        }

        function applyFilters(searchTerm = '') {
            const searchInput = document.getElementById('searchInput');
            const currentSearchTerm = searchTerm || searchInput.value.toLowerCase().trim();
            
            let filteredSponsors = allSponsors;
            
            // Apply status filter
            if (currentFilter === 'active') {
                filteredSponsors = filteredSponsors.filter(sponsor => sponsor.children_sponsored > 0);
            } else if (currentFilter === 'inactive') {
                filteredSponsors = filteredSponsors.filter(sponsor => sponsor.children_sponsored === 0);
            }
            
            // Apply search filter
            if (currentSearchTerm) {
                filteredSponsors = filteredSponsors.filter(sponsor => {
                    const fullName = `${sponsor.first_name} ${sponsor.last_name}`.toLowerCase();
                    const email = (sponsor.email || '').toLowerCase();
                    return fullName.includes(currentSearchTerm) || email.includes(currentSearchTerm);
                });
            }
            
            renderSponsorCards(filteredSponsors);
            
            const sponsorsGrid = document.getElementById('sponsorsGrid');
            const emptyState = document.getElementById('emptyState');
            
            if (filteredSponsors.length === 0) {
                sponsorsGrid.style.display = 'none';
                emptyState.style.display = 'block';
                document.querySelector('.empty-title').textContent = 'No Sponsors Found';
                document.querySelector('.empty-message').textContent = 'Try adjusting your search or filter criteria.';
            } else {
                sponsorsGrid.style.display = 'grid';
                emptyState.style.display = 'none';
            }
        }

        function viewSponsorProfile(sponsorId) {
            if (!sponsorId) {
                console.error('Invalid sponsor ID');
                return;
            }
            // ✅ CHANGED: Now redirects to sponsor_profile_staff.php instead of sponsor_profile.php
            window.location.href = `sponsor_profile_staff.php?sponsor_id=${sponsorId}`;
        }
    </script>
</body>
</html>