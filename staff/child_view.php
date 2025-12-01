<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../signup_and_login/login.php');
    exit();
}

// Check role
$user_role = strtolower($_SESSION['role']);
if ($user_role !== 'staff' && $user_role !== 'owner') {
    header('Location: ../unauthorized.php');
    exit();
}

require_once '../db_config.php';

// Fetch all children
$query = "SELECT * FROM children ORDER BY first_name, last_name";
$result = $conn->query($query);
$children = [];
while ($row = $result->fetch_assoc()) {
    $children[] = $row;
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3), transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(138, 43, 226, 0.3), transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        h1 {
            font-size: 32px;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .add-child-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 12px;
            color: #667eea;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .add-child-btn:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .child-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s ease;
            cursor: pointer;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .child-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .card-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin: 0 auto 15px;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .card-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-name {
            font-size: 20px;
            font-weight: 600;
            color: white;
            text-align: center;
            margin-bottom: 10px;
        }

        .card-info {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 13px;
            color: white;
            font-weight: 500;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-view {
            background: rgba(255, 255, 255, 0.9);
            color: #667eea;
        }

        .btn-view:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-edit {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .btn-edit:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            color: white;
        }

        .empty-state svg {
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 16px;
            opacity: 0.8;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideUp 0.4s ease;
            position: relative;
        }

        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 30px 30px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.05);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .close-btn:hover {
            background: rgba(0, 0, 0, 0.1);
            transform: rotate(90deg);
        }

        .profile-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            color: white;
            overflow: hidden;
            border: 4px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            margin-bottom: 15px;
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .profile-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            color: white;
            font-size: 13px;
            font-weight: 600;
        }

        .modal-body {
            padding: 25px 30px 30px;
        }

        .info-item {
            padding: 18px;
            background: rgba(102, 126, 234, 0.08);
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
        }

        .info-item:hover {
            background: rgba(102, 126, 234, 0.12);
        }

        .info-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 500;
            color: #2d3748;
            line-height: 1.6;
        }

        .empty-value {
            color: #a0aec0;
            font-style: italic;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            padding: 20px 30px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .modal-btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .modal-btn-edit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .modal-btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .modal-btn-close {
            background: rgba(0, 0, 0, 0.05);
            color: #4a5568;
        }

        .modal-btn-close:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 24px;
            }

            .children-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }

            .modal-content {
                border-radius: 20px;
            }

            .modal-header {
                padding: 25px 20px 15px;
            }

            .modal-body {
                padding: 20px;
            }

            .modal-actions {
                padding: 15px 20px;
                flex-direction: column;
            }

            .profile-name {
                font-size: 20px;
            }
        }

        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.3);
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: rgba(102, 126, 234, 0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Child Management</h1>
            <a href="add_child.php" class="add-child-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add New Child
            </a>
        </div>

        <?php if (empty($children)): ?>
            <div class="empty-state">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <h2>No Children Yet</h2>
                <p>Start by adding your first child to the system</p>
            </div>
        <?php else: ?>
            <div class="children-grid">
                <?php foreach ($children as $child): ?>
                    <?php
                    $dob = new DateTime($child['dob']);
                    $now = new DateTime();
                    $age = $now->diff($dob)->y;
                    ?>
                    <div class="child-card">
                        <div class="card-avatar">
                            <?php if (!empty($child['profile_picture']) && file_exists('../' . $child['profile_picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($child['profile_picture']); ?>" alt="Profile">
                            <?php else: ?>
                                <?php echo strtoupper(substr($child['first_name'], 0, 1) . substr($child['last_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="card-name">
                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                        </div>
                        <div class="card-info">
                            <span class="info-badge">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <?php echo $age; ?> years
                            </span>
                            <span class="info-badge">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <?php echo ucfirst(htmlspecialchars($child['gender'])); ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <button class="action-btn btn-view" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($child)); ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                View
                            </button>
                            <a href="child_edit.php?id=<?php echo $child['id']; ?>" class="action-btn btn-edit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Edit
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- View Modal -->
    <div class="modal-overlay" id="viewModal" onclick="closeModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <button class="close-btn" onclick="closeModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                
                <div class="profile-section">
                    <div class="profile-picture" id="modalAvatar"></div>
                    <div class="profile-name" id="modalName"></div>
                    <div class="profile-badges" id="modalBadges"></div>
                </div>
            </div>

            <div class="modal-body">
                <div class="info-item">
                    <div class="info-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        Date of Birth
                    </div>
                    <div class="info-value" id="modalDob"></div>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        About
                    </div>
                    <div class="info-value" id="modalAbout"></div>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        Aspiration
                    </div>
                    <div class="info-value" id="modalAspiration"></div>
                </div>
            </div>

            <div class="modal-actions">
                <a href="#" id="modalEditBtn" class="modal-btn modal-btn-edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit Profile
                </a>
                <button class="modal-btn modal-btn-close" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openViewModal(child) {
            // Calculate age
            const dob = new Date(child.dob);
            const now = new Date();
            const age = now.getFullYear() - dob.getFullYear();

            // Set avatar
            const avatarEl = document.getElementById('modalAvatar');
            if (child.profile_picture) {
                avatarEl.innerHTML = `<img src="../${child.profile_picture}" alt="Profile">`;
            } else {
                const initials = child.first_name.charAt(0).toUpperCase() + child.last_name.charAt(0).toUpperCase();
                avatarEl.textContent = initials;
            }

            // Set name
            document.getElementById('modalName').textContent = `${child.first_name} ${child.last_name}`;

            // Set badges
            document.getElementById('modalBadges').innerHTML = `
                <span class="badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    ${age} years old
                </span>
                <span class="badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    ${child.gender.charAt(0).toUpperCase() + child.gender.slice(1)}
                </span>
            `;

            // Format date
            const dobFormatted = new Date(child.dob).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            document.getElementById('modalDob').textContent = dobFormatted;

            // Set about
            document.getElementById('modalAbout').innerHTML = child.about_me 
                ? child.about_me.replace(/\n/g, '<br>')
                : '<span class="empty-value">No information provided</span>';

            // Set aspiration
            document.getElementById('modalAspiration').innerHTML = child.aspiration 
                ? child.aspiration.replace(/\n/g, '<br>')
                : '<span class="empty-value">No aspiration provided</span>';

            // Set edit button link
            document.getElementById('modalEditBtn').href = `child_edit.php?id=${child.id}`;

            // Show modal
            document.getElementById('viewModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('viewModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>