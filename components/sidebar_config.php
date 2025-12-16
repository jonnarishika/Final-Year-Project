<?php
// components/sidebar_config.php
// Configuration file for different sidebar menus based on user roles and pages

/**
 * Get the base path relative to current file location
 * This helps determine the correct path prefix based on where the page is located
 */
function getBasePath() {
    $currentFile = $_SERVER['SCRIPT_FILENAME'];
    $dashboardsPath = dirname(dirname($currentFile));
    
    // Check if we're in a subdirectory (owner, staff, sponser, etc.)
    $currentDir = basename(dirname($currentFile));
    $validSubdirs = ['owner', 'staff', 'sponser', 'donor'];
    
    if (in_array($currentDir, $validSubdirs)) {
        // We're in a subdirectory, need to go up one level
        return '../';
    } else {
        // We're in the root dashboards folder
        return './';
    }
}

/**
 * Get sidebar menu configuration
 * @param string $menu_type - Type of menu (owner, staff, donor, etc.)
 * @param string $current_page - Current page file name for active state
 * @return array - Menu configuration array
 */
function getSidebarMenu($menu_type, $current_page = '') {
    $basePath = getBasePath();
    
    $menus = [
        'owner' => [
            [
                'label' => 'Overview',
                'items' => [
                    [
                        'label' => 'Dashboard',
                        'url' => $basePath . 'owner/owner_home.php',
                        'active' => ($current_page === 'owner_home.php')
                    ],
                    [
                        'label' => 'Calendar',
                        'url' => $basePath . 'staff/staff_calendar.php',
                        'active' => ($current_page === 'staff_calendar.php')
                    ],
                    [
                        'label' => 'Fraud Management',
                        'url' => $basePath . 'owner/fraud.php',
                        'active' => ($current_page === 'fraud.php')
                    ]
                ]
            ],
            [
                'label' => 'Management',
                'items' => [
                    [
                        'label' => 'Children',
                        'url' => $basePath . 'staff/child_management.php',
                        'active' => ($current_page === 'child_management.php')
                    ],
                    [
                        'label' => 'Donors',
                        'url' => $basePath . 'owner/donor.php',
                        'active' => ($current_page === 'donor.php')
                    ],
                    [
                        'label' => 'Donations',
                        'url' => $basePath . 'owner/donation.php',
                        'active' => ($current_page === 'donation.php')
                    ],
                    [
                        'label' => 'Staff',
                        'url' => $basePath . 'owner/staff_management.php',
                        'active' => ($current_page === 'staff_management.php')
                    ]
                ]
            ]
        ],
        
        'staff' => [
            [
                'label' => 'Overview',
                'items' => [
                    [
                        'label' => 'Dashboard',
                        'url' => $basePath . 'staff/staff_home_old.php',
                        'active' => ($current_page === 'staff_home_old.php')
                    ],
                    [
                        'label' => 'Calendar',
                        'url' => $basePath . 'staff/staff_calendar.php',
                        'active' => ($current_page === 'staff_calendar.php')
                    ]
                ]
            ],
            [
                'label' => 'Management',
                'items' => [
                    [
                        'label' => 'Children',
                        'url' => $basePath . 'staff/child_management.php',
                        'active' => ($current_page === 'child_management.php')
                    ],
                    [
                        'label' => 'Donors',
                        'url' => $basePath . 'owner/donor.php',
                        'active' => ($current_page === 'donor.php')
                    ],
                    [
                        'label' => 'Donations',
                        'url' => $basePath . 'owner/donation.php',
                        'active' => ($current_page === 'donation.php')
                    ]
                ]
            ]
        ],
        
        'donor' => [
            [
                'label' => 'Overview',
                'items' => [
                    [
                        'label' => 'Dashboard',
                        'url' => $basePath . 'donor/donor_home.php',
                        'active' => ($current_page === 'donor_home.php')
                    ]
                ]
            ],
            [
                'label' => 'My Activity',
                'items' => [
                    [
                        'label' => 'My Sponsorships',
                        'url' => $basePath . 'donor/my_sponsorships.php',
                        'active' => ($current_page === 'my_sponsorships.php')
                    ],
                    [
                        'label' => 'My Donations',
                        'url' => $basePath . 'donor/my_donations.php',
                        'active' => ($current_page === 'my_donations.php')
                    ],
                    [
                        'label' => 'Available Children',
                        'url' => $basePath . 'donor/available_children.php',
                        'active' => ($current_page === 'available_children.php')
                    ]
                ]
            ]
        ],
        
        'sponsor' => [
            [
                'label' => 'Overview',
                'items' => [
                    [
                        'label' => 'Home',
                        'url' => $basePath . 'sponser/sponser_main_page.php',
                        'active' => ($current_page === 'sponser_main_page.php')
                    ],
                    [
                        'label' => 'My Profile',
                        'url' => $basePath . 'sponser/sponser_profile.php',
                        'active' => ($current_page === 'sponser_profile.php')
                    ],
                    [
                        'label' => 'My Home',
                        'url' => $basePath . 'sponser/my_home.php',
                        'active' => ($current_page === 'my_home.php')
                    ]
                ]
            ],
            [
                'label' => 'Children',
                'items' => [
                    [
                        'label' => 'All Children',
                        'url' => $basePath . 'all_children_profiles_sponser.php',
                        'active' => ($current_page === 'all_children_profiles_sponser.php')
                    ],
                    [
                        'label' => 'Sponsored Children',
                        'url' => $basePath . 'sponser/sponsored_children.php',
                        'active' => ($current_page === 'sponsored_children.php')
                    ]
                ]
            ]
        ]
    ];
    
    return $menus[$menu_type] ?? [];
}

/**
 * Initialize sidebar for a specific page
 * @param string $menu_type - Type of menu (owner, staff, donor, etc.)
 * @param string $current_page - Current page file name (optional, auto-detected if not provided)
 * @return array - Sidebar menu configuration
 */
function initSidebar($menu_type, $current_page = '') {
    // Auto-detect current page if not provided
    if (empty($current_page)) {
        $current_page = basename($_SERVER['PHP_SELF']);
    }
    
    return getSidebarMenu($menu_type, $current_page);
}
?>