<?php
/**
 * Sponsor Access Control Guard
 * Checks if sponsor is blocked/frozen and redirects if necessary
 * 
 * This file provides access control for sponsor pages based on fraud case status.
 * Include this at the top of any sponsor page that needs protection.
 */

// Include fraud services for access to canSponsorDonateDetailed()
require_once __DIR__ . '/fraud_services.php';

/**
 * Check sponsor access restrictions and fraud status
 * 
 * This function:
 * - Verifies the user is logged in
 * - Gets the sponsor ID from the database
 * - Checks fraud case status (blocked, frozen, restricted, etc.)
 * - Redirects blocked/frozen users away from restricted pages
 * - Returns sponsor ID and donation status for use in the page
 * 
 * @param resource $conn Database connection
 * @param string $current_page Current page filename (use basename(__FILE__))
 * @return array [
 *   'sponsor_id' => int,
 *   'donation_status' => array [
 *     'can_donate' => bool,
 *     'status' => string (normal|restricted|frozen|blocked|under_review),
 *     'reason' => string|null,
 *     'monthly_limit' => float|null,
 *     'remaining_limit' => float|null,
 *     'message' => string
 *   ]
 * ]
 */
function checkSponsorAccessRestrictions($conn, $current_page) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../signup_and_login/login_template.php");
        exit();
    }
    
    // Get sponsor ID from database
    $stmt = $conn->prepare("SELECT sponsor_id FROM sponsors WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $sponsor_result = $stmt->get_result();
    $sponsor_data = $sponsor_result->fetch_assoc();
    $stmt->close();
    
    if (!$sponsor_data) {
        die("Sponsor profile not found. Please contact administrator.");
    }
    
    $sponsor_id = $sponsor_data['sponsor_id'];
    
    // Check fraud case restrictions using detailed function
    $donation_status = canSponsorDonateDetailed($conn, $sponsor_id);
    
    // Pages that blocked/frozen users CAN still access
    // These are typically: dashboard, profile, appeal submission, and logout
    $allowed_when_blocked = [
        'sponser_main_page.php',
        'sponser_profile.php',
        'sponsor_appeal_form.php',
        'submit_appeal.php',
        'view_appeal_status.php',
        'logout.php'
    ];
    
    // If account is blocked or frozen, restrict access to certain pages
    if ($donation_status['status'] === 'blocked' || $donation_status['status'] === 'frozen') {
        // If trying to access a restricted page, redirect to main page
        if (!in_array($current_page, $allowed_when_blocked)) {
            $_SESSION['access_denied'] = "Your account is {$donation_status['status']}. Please submit an appeal.";
            $_SESSION['access_denied_details'] = $donation_status['message'];
            header("Location: sponser_main_page.php");
            exit();
        }
    }
    
    // Return sponsor ID and donation status for use in the page
    return [
        'sponsor_id' => $sponsor_id,
        'donation_status' => $donation_status
    ];
}

/**
 * Display fraud status notification banner
 * 
 * Call this function in your page HTML to show a banner when account has restrictions.
 * Only displays for restricted/frozen/blocked/under_review accounts.
 * 
 * @param array $donation_status The donation_status array from checkSponsorAccessRestrictions()
 * @param bool $show_appeal_link Whether to show a link to appeal form (default: true)
 * @return void Echoes HTML directly
 */
function displayFraudStatusBanner($donation_status, $show_appeal_link = true) {
    // Don't show banner for normal accounts
    if ($donation_status['status'] === 'normal') {
        return;
    }
    
    // Status-specific styling
    $status_config = [
        'blocked' => [
            'icon' => '🚫',
            'title' => 'Account Blocked',
            'bg_color' => '#fee2e2',
            'border_color' => '#dc2626',
            'text_color' => '#991b1b',
            'show_appeal' => true
        ],
        'frozen' => [
            'icon' => '❄️',
            'title' => 'Account Frozen',
            'bg_color' => '#fef3c7',
            'border_color' => '#f59e0b',
            'text_color' => '#92400e',
            'show_appeal' => true
        ],
        'restricted' => [
            'icon' => '⚠️',
            'title' => 'Account Restricted',
            'bg_color' => '#dbeafe',
            'border_color' => '#3b82f6',
            'text_color' => '#1e40af',
            'show_appeal' => false
        ],
        'under_review' => [
            'icon' => 'ℹ️',
            'title' => 'Under Review',
            'bg_color' => '#e0e7ff',
            'border_color' => '#6366f1',
            'text_color' => '#3730a3',
            'show_appeal' => false
        ]
    ];
    
    $config = $status_config[$donation_status['status']] ?? $status_config['under_review'];
    
    echo '<div style="
        background: ' . $config['bg_color'] . ';
        border: 2px solid ' . $config['border_color'] . ';
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        color: ' . $config['text_color'] . ';
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    ">';
    
    // Title and icon
    echo '<div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">';
    echo '<span style="font-size: 1.5rem;">' . $config['icon'] . '</span>';
    echo '<strong style="font-size: 1.1rem; font-weight: 700;">' . $config['title'] . '</strong>';
    echo '</div>';
    
    // Main message
    echo '<p style="margin: 0; font-weight: 500; font-size: 0.95rem; line-height: 1.5;">';
    echo htmlspecialchars($donation_status['message']);
    echo '</p>';
    
    // Show remaining limit for restricted accounts
    if ($donation_status['status'] === 'restricted' && $donation_status['remaining_limit'] !== null) {
        echo '<p style="margin: 0.75rem 0 0 0; font-size: 0.9rem; opacity: 0.85;">';
        echo '<strong>Remaining this month:</strong> ₹' . number_format($donation_status['remaining_limit'], 2);
        echo ' of ₹' . number_format($donation_status['monthly_limit'], 2);
        echo '</p>';
    }
    
    // Show appeal link for blocked/frozen accounts
    if ($show_appeal_link && $config['show_appeal']) {
        echo '<div style="margin-top: 1rem;">';
        echo '<a href="sponsor_appeal_form.php" style="
            display: inline-block;
            background: ' . $config['border_color'] . ';
            color: white;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: opacity 0.2s;
        " onmouseover="this.style.opacity=0.9" onmouseout="this.style.opacity=1">
            Submit an Appeal
        </a>';
        echo '</div>';
    }
    
    echo '</div>';
}

/**
 * Check if access was denied and display message
 * 
 * Call this near the top of your page HTML to show denial messages.
 * Automatically clears the session message after displaying.
 * 
 * @return void Echoes HTML directly if message exists
 */
function displayAccessDeniedMessage() {
    if (isset($_SESSION['access_denied'])) {
        $message = htmlspecialchars($_SESSION['access_denied']);
        $details = isset($_SESSION['access_denied_details']) ? htmlspecialchars($_SESSION['access_denied_details']) : '';
        
        echo '<div style="
            background: #fee2e2;
            border: 2px solid #dc2626;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            color: #991b1b;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        ">';
        echo '<div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">';
        echo '<span style="font-size: 1.5rem;">⛔</span>';
        echo '<strong style="font-size: 1.1rem;">Access Denied</strong>';
        echo '</div>';
        echo '<p style="margin: 0; font-weight: 500;">' . $message . '</p>';
        if ($details) {
            echo '<p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; opacity: 0.85;">' . $details . '</p>';
        }
        echo '</div>';
        
        // Clear the messages
        unset($_SESSION['access_denied']);
        unset($_SESSION['access_denied_details']);
    }
}

/**
 * Quick check if sponsor can make donations
 * 
 * Simple boolean check for donation forms or buttons.
 * 
 * @param array $donation_status The donation_status array from checkSponsorAccessRestrictions()
 * @return bool True if sponsor can donate, false otherwise
 */
function canDonate($donation_status) {
    return $donation_status['can_donate'] === true;
}

/**
 * Get human-readable status badge HTML
 * 
 * Returns a styled badge for displaying account status.
 * 
 * @param array $donation_status The donation_status array from checkSponsorAccessRestrictions()
 * @return string HTML badge
 */
function getStatusBadge($donation_status) {
    $badges = [
        'normal' => ['text' => 'Active', 'color' => '#10b981', 'bg' => '#d1fae5'],
        'restricted' => ['text' => 'Restricted', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
        'frozen' => ['text' => 'Frozen', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
        'blocked' => ['text' => 'Blocked', 'color' => '#dc2626', 'bg' => '#fee2e2'],
        'under_review' => ['text' => 'Under Review', 'color' => '#6366f1', 'bg' => '#e0e7ff']
    ];
    
    $badge = $badges[$donation_status['status']] ?? $badges['normal'];
    
    return '<span style="
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: ' . $badge['bg'] . ';
        color: ' . $badge['color'] . ';
    ">' . $badge['text'] . '</span>';
}
?>