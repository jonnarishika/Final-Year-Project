<?php
/**
 * Calendar Helper Functions
 * Reusable functions for fetching calendar events
 */

/**
 * Get all events for a specific sponsor (filtered view)
 * Shows: Birthdays, Achievements, Reports, Public Events ONLY
 */
function getSponsorCalendarEvents($conn, $sponsor_id, $start_date, $end_date) {
    $events = [];
    
    // Get sponsored children
    $query = "SELECT c.child_id, c.first_name, c.last_name, c.dob, c.profile_picture
              FROM children c
              INNER JOIN sponsorships sp ON c.child_id = sp.child_id
              WHERE sp.sponsor_id = ? AND (sp.end_date IS NULL OR sp.end_date > CURDATE())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($children as $child) {
        $child_id = $child['child_id'];
        $child_name = $child['first_name'] . ' ' . $child['last_name'];
        
        // 1. BIRTHDAYS (calculated dynamically)
        $birthday_this_year = date('Y', strtotime($start_date)) . '-' . date('m-d', strtotime($child['dob']));
        if ($birthday_this_year >= $start_date && $birthday_this_year <= $end_date) {
            $age = date_diff(date_create($child['dob']), date_create($birthday_this_year))->y;
            $events[] = [
                'type' => 'birthday',
                'date' => $birthday_this_year,
                'title' => $child['first_name'] . "'s Birthday",
                'description' => "Turning {$age} years old!",
                'child_id' => $child_id,
                'child_name' => $child_name,
                'icon' => '🎂',
                'color' => '#ec4899'
            ];
        }
        
        // 2. ACHIEVEMENTS
        $query = "SELECT upload_id, upload_date, file_path 
                  FROM child_uploads 
                  WHERE child_id = ? AND category = 'Achievement' 
                  AND upload_date >= ? AND upload_date <= ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $child_id, $start_date, $end_date);
        $stmt->execute();
        $achievements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($achievements as $ach) {
            $events[] = [
                'type' => 'achievement',
                'date' => $ach['upload_date'],
                'title' => 'Achievement',
                'description' => 'New achievement uploaded',
                'child_id' => $child_id,
                'child_name' => $child_name,
                'icon' => '🏆',
                'color' => '#10b981',
                'event_id' => $ach['upload_id']
            ];
        }
        
        // 3. REPORTS
        $query = "SELECT report_id, report_date, report_text 
                  FROM child_reports 
                  WHERE child_id = ? 
                  AND report_date >= ? AND report_date <= ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $child_id, $start_date, $end_date);
        $stmt->execute();
        $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($reports as $rep) {
            $events[] = [
                'type' => 'report',
                'date' => $rep['report_date'],
                'title' => 'Progress Report',
                'description' => substr($rep['report_text'], 0, 100) . '...',
                'child_id' => $child_id,
                'child_name' => $child_name,
                'icon' => '📄',
                'color' => '#3b82f6',
                'event_id' => $rep['report_id']
            ];
        }
        
        // 4. PUBLIC EVENTS
        $query = "SELECT event_id, title, event_date, description, event_type 
                  FROM calendar_events 
                  WHERE child_id = ? AND is_public = 1 
                  AND event_date >= ? AND event_date <= ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $child_id, $start_date, $end_date);
        $stmt->execute();
        $public_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($public_events as $evt) {
            $events[] = [
                'type' => 'event',
                'date' => $evt['event_date'],
                'title' => $evt['title'],
                'description' => $evt['description'] ?? '',
                'child_id' => $child_id,
                'child_name' => $child_name,
                'icon' => '🎉',
                'color' => '#8b5cf6',
                'event_id' => $evt['event_id'],
                'event_type' => $evt['event_type']
            ];
        }
    }
    
    // Sort by date
    usort($events, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    return $events;
}

/**
 * Get all events for staff (full view)
 * Shows: Everything including internal events
 */
function getStaffCalendarEvents($conn, $start_date, $end_date) {
    $events = [];
    
    // Get all children
    $query = "SELECT child_id, first_name, last_name, dob, profile_picture FROM children";
    $children = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
    
    foreach ($children as $child) {
        $child_id = $child['child_id'];
        $child_name = $child['first_name'] . ' ' . $child['last_name'];
        
        // 1. BIRTHDAYS
        $birthday_this_year = date('Y', strtotime($start_date)) . '-' . date('m-d', strtotime($child['dob']));
        if ($birthday_this_year >= $start_date && $birthday_this_year <= $end_date) {
            $age = date_diff(date_create($child['dob']), date_create($birthday_this_year))->y;
            $events[] = [
                'type' => 'birthday',
                'date' => $birthday_this_year,
                'title' => $child['first_name'] . "'s Birthday",
                'description' => "Turning {$age} years old!",
                'child_id' => $child_id,
                'child_name' => $child_name,
                'icon' => '🎂',
                'color' => '#ec4899',
                'is_public' => 1
            ];
        }
        
        // 2. ACHIEVEMENTS
        $query = "SELECT upload_id, upload_date, file_path 
                  FROM child_uploads 
                  WHERE child_id = ? AND category = 'Achievement' 
                  AND upload_date >= ? AND upload_date <= ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $child_id, $start_date, $end_date);
        $stmt->execute();
        $achievements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($achievements as $ach) {
            $events[] = [
                'type' => 'achievement',
                'date' => $ach['upload_date'],
                'title' => 'Achievement',
                'description' => 'New achievement uploaded',
                'child_id' => $child_id,
                'child_name' => $child_name,
                'icon' => '🏆',
                'color' => '#10b981',
                'event_id' => $ach['upload_id'],
                'is_public' => 1
            ];
        }
        
        // 3. REPORTS
        $query = "SELECT report_id, report_date, report_text 
                  FROM child_reports 
                  WHERE child_id = ? 
                  AND report_date >= ? AND report_date <= ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $child_id, $start_date, $end_date);
        $stmt->execute();
        $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($reports as $rep) {
            $events[] = [
                'type' => 'report',
                'date' => $rep['report_date'],
                'title' => 'Progress Report',
                'description' => substr($rep['report_text'], 0, 100) . '...',
                'child_id' => $child_id,
                'child_name' => $child_name,
                'icon' => '📄',
                'color' => '#3b82f6',
                'event_id' => $rep['report_id'],
                'is_public' => 1
            ];
        }
        
        // 4. ALL EVENTS (public AND internal)
        $query = "SELECT event_id, title, event_date, description, event_type, is_public, created_by 
                  FROM calendar_events 
                  WHERE child_id = ? 
                  AND event_date >= ? AND event_date <= ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $child_id, $start_date, $end_date);
        $stmt->execute();
        $all_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($all_events as $evt) {
            $icon = $evt['is_public'] ? '🎉' : '🏥';
            $color = $evt['is_public'] ? '#8b5cf6' : '#ef4444';
            
            $events[] = [
                'type' => $evt['is_public'] ? 'event' : 'internal',
                'date' => $evt['event_date'],
                'title' => $evt['title'],
                'description' => $evt['description'] ?? '',
                'child_id' => $child_id,
                'child_name' => $child_name,
                'icon' => $icon,
                'color' => $color,
                'event_id' => $evt['event_id'],
                'event_type' => $evt['event_type'],
                'is_public' => $evt['is_public'],
                'created_by' => $evt['created_by']
            ];
        }
    }
    
    // Sort by date
    usort($events, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    return $events;
}

/**
 * Generate calendar grid structure
 */
function generateCalendarGrid($year, $month) {
    $first_day = date('Y-m-01', strtotime("$year-$month-01"));
    $last_day = date('Y-m-t', strtotime($first_day));
    $month_name = date('F Y', strtotime($first_day));
    $start_weekday = date('w', strtotime($first_day)); // 0 (Sun) - 6 (Sat)
    $days_in_month = date('t', strtotime($first_day));
    
    return [
        'year' => $year,
        'month' => $month,
        'month_name' => $month_name,
        'first_day' => $first_day,
        'last_day' => $last_day,
        'start_weekday' => $start_weekday,
        'days_in_month' => $days_in_month
    ];
}

/**
 * Group events by date
 */
function groupEventsByDate($events) {
    $grouped = [];
    foreach ($events as $event) {
        $date = $event['date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = [];
        }
        $grouped[$date][] = $event;
    }
    return $grouped;
}

/**
 * Get child sponsors (for email notifications)
 */
function getChildSponsors($conn, $child_id) {
    $query = "SELECT u.user_id, u.email, s.sponsor_id, s.first_name, s.last_name
              FROM users u
              INNER JOIN sponsors s ON u.user_id = s.user_id
              INNER JOIN sponsorships sp ON s.sponsor_id = sp.sponsor_id
              WHERE sp.child_id = ? 
              AND (sp.end_date IS NULL OR sp.end_date > CURDATE())
              AND u.user_role = 'Sponsor'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $child_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $result;
}

/**
 * Get child details by ID
 */
function getChildDetails($conn, $child_id) {
    $query = "SELECT * FROM children WHERE child_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $child_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result;
}
?>