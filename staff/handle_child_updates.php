<?php
// handle_child_updates.php
// Single endpoint to handle ALL child updates: info, profile picture, and documents
// MODIFIED: Added email triggers for Achievement uploads

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check role
$user_role = strtolower($_SESSION['role']);
if ($user_role !== 'staff' && $user_role !== 'owner') {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

require_once '../db_config.php';
require_once '../email_system/class.EmailSender.php';

// Determine the action type
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : null);

// If no action in POST/GET, check if it's JSON for field updates
if (!$action && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['field'])) {
        $action = 'update_field';
    }
}

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

try {
    switch ($action) {
        
        // ========== UPDATE CHILD INFORMATION FIELDS ==========
        case 'update_field':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['child_id']) || !isset($input['field'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit();
            }
            
            $child_id = intval($input['child_id']);
            $field = $input['field'];
            
            switch ($field) {
                case 'name':
                    $first_name = trim($input['first_name']);
                    $last_name = trim($input['last_name']);
                    
                    $stmt = $conn->prepare("UPDATE children SET first_name = ?, last_name = ? WHERE child_id = ?");
                    $stmt->bind_param('ssi', $first_name, $last_name, $child_id);
                    $success_message = 'Name updated successfully';
                    break;
                    
                case 'dob':
                    $dob = $input['dob'];
                    
                    $stmt = $conn->prepare("UPDATE children SET dob = ? WHERE child_id = ?");
                    $stmt->bind_param('si', $dob, $child_id);
                    $success_message = 'Date of birth updated successfully';
                    break;
                    
                case 'gender':
                    $gender = $input['gender'];
                    
                    $stmt = $conn->prepare("UPDATE children SET gender = ? WHERE child_id = ?");
                    $stmt->bind_param('si', $gender, $child_id);
                    $success_message = 'Gender updated successfully';
                    break;
                    
                case 'about_me':
                    $about_me = trim($input['about_me']);
                    
                    $stmt = $conn->prepare("UPDATE children SET about_me = ? WHERE child_id = ?");
                    $stmt->bind_param('si', $about_me, $child_id);
                    $success_message = 'About Me updated successfully';
                    break;
                    
                case 'aspiration':
                    $aspiration = trim($input['aspiration']);
                    
                    $stmt = $conn->prepare("UPDATE children SET aspiration = ? WHERE child_id = ?");
                    $stmt->bind_param('si', $aspiration, $child_id);
                    $success_message = 'Aspirations updated successfully';
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid field']);
                    exit();
            }
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => $success_message]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            
            $stmt->close();
            break;
            
        
        // ========== UPDATE PROFILE PICTURE ==========
        case 'update_profile_picture':
            if (!isset($_FILES['profile_picture']) || !isset($_POST['child_id'])) {
                echo json_encode(['success' => false, 'message' => 'Missing file or child ID']);
                exit();
            }
            
            $child_id = intval($_POST['child_id']);
            $file = $_FILES['profile_picture'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
                exit();
            }
            
            // Validate file size (5MB max)
            $max_size = 5 * 1024 * 1024;
            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                exit();
            }
            
            // Create uploads directory
            $upload_dir = '../uploads/children/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'child_' . $child_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            $db_path = 'uploads/children/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Update database
                $stmt = $conn->prepare("UPDATE children SET profile_picture = ? WHERE child_id = ?");
                $stmt->bind_param('si', $db_path, $child_id);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Profile picture updated successfully', 
                        'path' => $db_path
                    ]);
                } else {
                    // Delete uploaded file if database update fails
                    unlink($filepath);
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
                }
                
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
            break;
            
        
        // ========== UPLOAD DOCUMENT (WITH EMAIL TRIGGER FOR ACHIEVEMENTS) ==========
        case 'upload_document':
            if (!isset($_FILES['document']) || !isset($_POST['child_id']) || !isset($_POST['category'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit();
            }
            
            $child_id = intval($_POST['child_id']);
            $category = trim($_POST['category']);
            $file = $_FILES['document'];
            $uploaded_by = $_SESSION['user_id'];
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            
            // Validate file type
            $allowed_types = [
                'application/pdf',
                'image/jpeg',
                'image/jpg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, JPG, PNG, DOC, and DOCX are allowed.']);
                exit();
            }
            
            // Validate file size (10MB max)
            $max_size = 10 * 1024 * 1024;
            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10MB.']);
                exit();
            }
            
            // Create uploads directory for this child
            $upload_dir = '../uploads/child' . $child_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = strtolower($category) . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            $db_path = 'uploads/child' . $child_id . '/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO child_uploads (child_id, category, file_path, uploaded_by, upload_date) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param('issi', $child_id, $category, $db_path, $uploaded_by);
                
                if ($stmt->execute()) {
                    $upload_id = $stmt->insert_id;
                    
                    // ===== EMAIL TRIGGER: Send achievement email if category is "Achievement" =====
                    if (strtolower($category) === 'achievement') {
                        try {
                            // Get child details
                            $child_query = "SELECT * FROM children WHERE child_id = ?";
                            $child_stmt = $conn->prepare($child_query);
                            $child_stmt->bind_param('i', $child_id);
                            $child_stmt->execute();
                            $child_result = $child_stmt->get_result();
                            
                            if ($child_result->num_rows > 0) {
                                $child = $child_result->fetch_assoc();
                                
                                // Get all active sponsors for this child
                                $sponsor_query = "SELECT s.user_id, s.first_name, s.last_name, u.email 
                                                FROM sponsors s
                                                INNER JOIN users u ON s.user_id = u.user_id
                                                INNER JOIN sponsorships sp ON s.sponsor_id = sp.sponsor_id
                                                WHERE sp.child_id = ? 
                                                AND sp.end_date IS NULL
                                                AND u.user_role = 'Sponsor'";
                                
                                $sponsor_stmt = $conn->prepare($sponsor_query);
                                $sponsor_stmt->bind_param('i', $child_id);
                                $sponsor_stmt->execute();
                                $sponsor_result = $sponsor_stmt->get_result();
                                
                                // Initialize email sender
                                $emailSender = new EmailSender();
                                
                                // Prepare achievement data
                                $achievement = [
                                    'upload_id' => $upload_id,
                                    'description' => $description ?: 'A new achievement has been added!',
                                    'upload_date' => date('Y-m-d')
                                ];
                                
                                // Send email to each sponsor
                                $emails_sent = 0;
                                while ($sponsor = $sponsor_result->fetch_assoc()) {
                                    $success = $emailSender->sendAchievementEmail($sponsor, $child, $achievement);
                                    if ($success) {
                                        $emails_sent++;
                                    }
                                }
                                
                                $sponsor_stmt->close();
                            }
                            
                            $child_stmt->close();
                        } catch (Exception $e) {
                            // Log error but don't fail the upload
                            error_log("Achievement email sending failed: " . $e->getMessage());
                        }
                    }
                    // ===== END EMAIL TRIGGER =====
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Document uploaded successfully' . ($category === 'Achievement' ? ' and sponsors notified!' : ''),
                        'upload_id' => $upload_id,
                        'path' => $db_path,
                        'category' => $category
                    ]);
                } else {
                    // Delete uploaded file if database insert fails
                    unlink($filepath);
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
                }
                
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
            break;
            
        
        // ========== DELETE DOCUMENT ==========
        case 'delete_document':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['upload_id'])) {
                echo json_encode(['success' => false, 'message' => 'Missing upload ID']);
                exit();
            }
            
            $upload_id = intval($input['upload_id']);
            
            // Get file path before deleting
            $stmt = $conn->prepare("SELECT file_path FROM child_uploads WHERE upload_id = ?");
            $stmt->bind_param('i', $upload_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $file_path = '../' . $row['file_path'];
                
                // Delete from database
                $delete_stmt = $conn->prepare("DELETE FROM child_uploads WHERE upload_id = ?");
                $delete_stmt->bind_param('i', $upload_id);
                
                if ($delete_stmt->execute()) {
                    // Delete physical file
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                    echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $delete_stmt->error]);
                }
                
                $delete_stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Document not found']);
            }
            
            $stmt->close();
            break;
            
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>