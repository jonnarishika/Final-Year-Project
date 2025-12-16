<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db_config.php';

$user_id = $_SESSION['user_id'];

// ✅ FIXED: Get sponsor_id from database for the logged-in user
$query = "SELECT sponsor_id FROM sponsors WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Sponsor not found. Please make sure this user account is registered as a sponsor.");
}

$sponsor_data = $result->fetch_assoc();
$sponsor_id = intval($sponsor_data['sponsor_id']);
$stmt->close();

// DEBUG: Log what sponsor_id we found
error_log("========================================");
error_log("SPONSORED CHILDREN PAGE");
error_log("Logged in user_id: $user_id");
error_log("Found sponsor_id: $sponsor_id");
error_log("========================================");

// Handle AJAX request for sponsored children data
if (isset($_GET['action']) && $_GET['action'] === 'get_children') {
    header('Content-Type: application/json');
    
    try {
        // ✅ FIXED QUERY: Fetch children directly from children table where sponsor_id matches
        // Also LEFT JOIN sponsorships to get history data if needed
        $children_query = "
            SELECT 
                c.child_id,
                c.first_name,
                c.last_name,
                c.dob,
                c.gender,
                c.status,
                c.sponsor_id,
                TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) as age,
                s.start_date,
                s.end_date
            FROM children c
            LEFT JOIN sponsorships s ON c.child_id = s.child_id 
                AND s.sponsor_id = ? 
                AND (s.end_date IS NULL OR s.end_date > CURDATE())
            WHERE c.sponsor_id = ?
            ORDER BY c.first_name ASC
        ";

        $stmt = $conn->prepare($children_query);
        $stmt->bind_param('ii', $sponsor_id, $sponsor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        error_log("Query executed. Found " . $result->num_rows . " children");
        
        $children = [];
        $active_count = 0;
        
        while ($row = $result->fetch_assoc()) {
            error_log("Found child: {$row['first_name']} {$row['last_name']} (ID: {$row['child_id']})");
            
            // Format dates
            $row['dob_formatted'] = date('F j, Y', strtotime($row['dob']));
            
            // If sponsorship history exists, use it; otherwise use current date
            if (!empty($row['start_date'])) {
                $row['start_date_formatted'] = date('F j, Y', strtotime($row['start_date']));
                $start_date = $row['start_date'];
            } else {
                // If no start_date in sponsorships table, use a default or estimate
                $row['start_date_formatted'] = 'Recently';
                $start_date = date('Y-m-d'); // Use today as fallback
            }
            
            // Calculate sponsorship duration
            $start = new DateTime($start_date);
            $now = new DateTime();
            $interval = $start->diff($now);
            
            $years = $interval->y;
            $months = $interval->m;
            
            if ($years > 0) {
                $row['sponsorship_duration'] = $years . ' year' . ($years > 1 ? 's' : '');
                if ($months > 0) {
                    $row['sponsorship_duration'] .= ', ' . $months . ' month' . ($months > 1 ? 's' : '');
                }
            } else if ($months > 0) {
                $row['sponsorship_duration'] = $months . ' month' . ($months > 1 ? 's' : '');
            } else {
                $row['sponsorship_duration'] = 'Less than a month';
            }
            
            // Generate initials
            $row['initials'] = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
            
            // Count active sponsorships
            if (strtolower($row['status']) === 'sponsored' || strtolower($row['status']) === 'active') {
                $active_count++;
            }
            
            $children[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        // Calculate stats
        $total_count = count($children);
        
        error_log("Returning $total_count children, $active_count active");
        
        echo json_encode([
            'success' => true,
            'data' => $children,
            'stats' => [
                'total_count' => $total_count,
                'active_count' => $active_count
            ]
        ]);
        exit();
        
    } catch (Exception $e) {
        error_log("ERROR fetching children: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching children: ' . $e->getMessage()
        ]);
        exit();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsored Children</title>
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

        /* Additional ambient glow */
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

        /* Back Button */
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

        /* Header Section */
        .page-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
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

        /* Debug Info Box */
        .debug-info {
            background: rgba(59, 130, 246, 0.1);
            border: 2px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        /* Stats Cards */
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

        /* Controls Section */
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

        /* Children Grid */
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .child-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .child-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(254, 240, 138, 0.4);
            background: rgba(255, 255, 255, 1);
            border-color: rgba(254, 240, 138, 0.5);
        }

        .child-photo {
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

        .child-photo img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .child-info {
            text-align: center;
        }

        .child-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #18181b;
            margin-bottom: 1rem;
            letter-spacing: -0.01em;
        }

        .child-details {
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

        .sponsorship-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid rgba(254, 240, 138, 0.3);
        }

        .sponsorship-date {
            font-size: 0.875rem;
            color: #71717a;
            margin-bottom: 0.5rem;
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

        /* Loading State */
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

        /* Empty State */
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

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .controls-grid {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                width: 100%;
                justify-content: center;
            }

            .children-grid {
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
        <a href="sponser_profile.php" class="back-btn">
            ← Back to Dashboard
        </a>

        <div class="page-header">
            <div class="header-content">
                <div>
                    <h1 class="page-title">Sponsored Children</h1>
                    <p class="page-subtitle">Making a lasting impact through child sponsorship</p>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-container">
            <div class="stat-card primary">
                <div class="stat-label">Total Sponsored</div>
                <div class="stat-value" id="totalCount">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active</div>
                <div class="stat-value" id="activeCount">0</div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section">
            <div class="controls-grid">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" 
                           class="search-input" 
                           id="searchInput" 
                           placeholder="Search by child name...">
                </div>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="Male">Boys</button>
                    <button class="filter-btn" data-filter="Female">Girls</button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div class="loading" id="loadingState">
            <div class="loading-spinner"></div>
            <p class="loading-text">Loading sponsored children...</p>
        </div>

        <!-- Children Grid -->
        <div class="children-grid" id="childrenGrid" style="display: none;">
            <!-- Child cards will be dynamically inserted here -->
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="emptyState" style="display: none;">
            <div class="empty-icon">📭</div>
            <h2 class="empty-title">No Children Found</h2>
            <p class="empty-message">You haven't sponsored any children yet.</p>
        </div>
    </div>

    <script>
        const SPONSOR_ID = <?php echo $sponsor_id; ?>;
        let allChildren = [];
        let currentFilter = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            console.log('=================================');
            console.log('🚀 Loading children for sponsor:', SPONSOR_ID);
            console.log('=================================');
            fetchSponsoredChildren();
            setupEventListeners();
        });

        async function fetchSponsoredChildren() {
            const loadingState = document.getElementById('loadingState');
            const childrenGrid = document.getElementById('childrenGrid');
            const emptyState = document.getElementById('emptyState');
            
            try {
                loadingState.style.display = 'block';
                childrenGrid.style.display = 'none';
                emptyState.style.display = 'none';
                
                console.log('📡 Fetching children for sponsor_id:', SPONSOR_ID);
                
                const response = await fetch(`sponsored_children.php?action=get_children&sponsor_id=${SPONSOR_ID}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('📦 API Response:', result);
                
                if (!result.success) {
                    throw new Error(result.message || 'Failed to fetch data');
                }
                
                allChildren = result.data || [];
                console.log('✅ Number of children found:', allChildren.length);
                
                if (allChildren.length > 0) {
                    console.log('👶 Children:', allChildren.map(c => c.first_name + ' ' + c.last_name).join(', '));
                }
                
                updateStatistics(result.stats);
                
                loadingState.style.display = 'none';
                
                if (allChildren.length === 0) {
                    console.warn('⚠️ No children found for this sponsor');
                    emptyState.style.display = 'block';
                } else {
                    childrenGrid.style.display = 'grid';
                    renderChildrenCards(allChildren);
                }
                
            } catch (error) {
                console.error('❌ ERROR fetching sponsored children:', error);
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
        }

        function renderChildrenCards(children) {
            const childrenGrid = document.getElementById('childrenGrid');
            
            if (!children || children.length === 0) {
                childrenGrid.innerHTML = '<p class="empty-state">No children match your search criteria.</p>';
                return;
            }
            
            childrenGrid.innerHTML = children.map(child => `
                <div class="child-card" data-gender="${child.gender}" data-name="${child.first_name} ${child.last_name}">
                    <div class="child-photo">
                        ${child.initials || '??'}
                    </div>
                    <div class="child-info">
                        <div class="child-name">${child.first_name} ${child.last_name}</div>
                        <div class="child-details">
                            <div class="detail-row">
                                <span class="detail-label">Age:</span>
                                <span class="detail-value">${child.age} years</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Gender:</span>
                                <span class="detail-value">${child.gender}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value">${child.status}</span>
                            </div>
                        </div>
                        <div class="sponsorship-info">
                            <div class="sponsorship-date">
                                Sponsored since ${child.start_date_formatted}
                            </div>
                            <div class="sponsorship-date">
                                Duration: ${child.sponsorship_duration}
                            </div>
                        </div>
                        <button class="view-btn" onclick="viewChildProfile(${child.child_id})">
                            View Profile
                        </button>
                    </div>
                </div>
            `).join('');
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
            
            let filteredChildren = allChildren;
            
            if (currentFilter !== 'all') {
                filteredChildren = filteredChildren.filter(child => child.gender === currentFilter);
            }
            
            if (currentSearchTerm) {
                filteredChildren = filteredChildren.filter(child => {
                    const fullName = `${child.first_name} ${child.last_name}`.toLowerCase();
                    return fullName.includes(currentSearchTerm);
                });
            }
            
            renderChildrenCards(filteredChildren);
            
            const childrenGrid = document.getElementById('childrenGrid');
            const emptyState = document.getElementById('emptyState');
            
            if (filteredChildren.length === 0) {
                childrenGrid.style.display = 'none';
                emptyState.style.display = 'block';
                document.querySelector('.empty-title').textContent = 'No Children Found';
                document.querySelector('.empty-message').textContent = 'Try adjusting your search or filter criteria.';
            } else {
                childrenGrid.style.display = 'grid';
                emptyState.style.display = 'none';
            }
        }

        function viewChildProfile(childId) {
            if (!childId) {
                console.error('Invalid child ID');
                return;
            }
            console.log('🔗 Navigating to child profile:', childId);
            window.location.href = `child_profile.php?child_id=${childId}`;
        }
    </script>
</body>
</html>