<?php
session_start();
require_once '../db_config.php';

$success = $error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $about_me = trim($_POST['about_me']);
    $aspiration = trim($_POST['aspiration']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender)) {
        $error = "All required fields must be filled.";
    }
    
    // Validate age (must be between 0-18)
    $age = date_diff(date_create($dob), date_create('today'))->y;
    if ($age < 0 || $age > 18) {
        $error = "Child age must be between 0-18 years.";
    }
    
    // File Upload Handling
    $profile_picture = $profile_video = null;
    $upload_dir = '../uploads/children/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Image Upload (Optional)
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $image_tmp = $_FILES['profile_picture']['tmp_name'];
        $image_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_image = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($image_ext, $allowed_image)) {
            $error = "Only JPG, JPEG, PNG, GIF images are allowed.";
        } elseif ($_FILES['profile_picture']['size'] > 5000000) { // 5MB limit
            $error = "Image size must be less than 5MB.";
        } else {
            $image_name = strtolower($first_name . '' . $last_name) . '' . time() . '.' . $image_ext;
            $profile_picture = $upload_dir . $image_name;
            move_uploaded_file($image_tmp, $profile_picture);
        }
    }
    
    // Video Upload (Optional)
    if (isset($_FILES['profile_video']) && $_FILES['profile_video']['error'] === 0) {
        $video_tmp = $_FILES['profile_video']['tmp_name'];
        $video_ext = strtolower(pathinfo($_FILES['profile_video']['name'], PATHINFO_EXTENSION));
        $allowed_video = ['mp4', 'avi', 'mov', 'wmv'];
        
        if (!in_array($video_ext, $allowed_video)) {
            $error = "Only MP4, AVI, MOV, WMV videos are allowed.";
        } elseif ($_FILES['profile_video']['size'] > 50000000) { // 50MB limit
            $error = "Video size must be less than 50MB.";
        } else {
            $video_name = strtolower($first_name . '' . $last_name) . '' . time() . '.' . $video_ext;
            $profile_video = $upload_dir . $video_name;
            move_uploaded_file($video_tmp, $profile_video);
        }
    }
    
    // Insert into Database
    if (empty($error)) {
        $stmt = $conn->prepare("
            INSERT INTO children (
                first_name, last_name, dob, gender,
                about_me, aspiration, profile_picture, profile_video,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Unsponsored')
        ");
        
        $stmt->bind_param(
            "ssssssss",
            $first_name, $last_name, $dob, $gender,
            $about_me, $aspiration, $profile_picture, $profile_video
        );
        
        if ($stmt->execute()) {
            $success = "Child profile created successfully!";
            // Redirect after 2 seconds
            header("refresh:2;url=child_management.php");
        } else {
            $error = "Database error: " . $stmt->error;
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Child - SponsoLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #7e8ba3 50%, #89a6c7 75%, #a8c5e7 100%);
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 20% 50%, rgba(62, 106, 188, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(105, 155, 224, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(168, 197, 231, 0.2) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
            z-index: 0;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, rgba(62, 106, 188, 0.4) 0%, rgba(105, 155, 224, 0.3) 100%);
            backdrop-filter: blur(10px);
            color: white;
            padding: 50px 40px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .header h1 {
            font-size: 36px;
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header p {
            opacity: 0.95;
            font-size: 16px;
            font-weight: 400;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .form-container {
            padding: 50px;
            background: rgba(255, 255, 255, 0.05);
        }
        
        .alert {
            padding: 18px 24px;
            border-radius: 16px;
            margin-bottom: 30px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.4s ease-out;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: rgba(72, 187, 120, 0.2);
            color: #f0fff4;
            border-left: 4px solid #48bb78;
        }
        
        .alert-error {
            background: rgba(245, 101, 101, 0.2);
            color: #fff5f5;
            border-left: 4px solid #f56565;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
        }
        
        .form-group {
            margin-bottom: 5px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 12px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        label .required {
            color: #fc8181;
            margin-left: 2px;
        }
        
        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 16px 18px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            color: white;
        }
        
        input::placeholder,
        textarea::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }
        
        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23ffffff' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            padding-right: 45px;
        }
        
        select option {
            background: #2a5298;
            color: white;
        }
        
        /* Date input styling for webkit browsers */
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 35px 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 130px;
        }
        
        .file-input-label:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .file-input-label .icon {
            font-size: 36px;
            margin-bottom: 12px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }
        
        .file-input-label .text {
            font-size: 15px;
            color: white;
            font-weight: 600;
            margin-bottom: 6px;
        }
        
        .file-input-label .subtext {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .file-preview {
            margin-top: 16px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            display: none;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .file-preview p {
            color: white;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-container {
            display: flex;
            gap: 16px;
            margin-top: 45px;
            padding-top: 35px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .btn {
            flex: 1;
            padding: 18px 28px;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            backdrop-filter: blur(10px);
        }
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary:active {
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-decoration: none;
            text-align: center;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            grid-column: 1 / -1;
            font-size: 19px;
            font-weight: 700;
            color: white;
            margin: 25px 0 15px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            body {
                padding: 20px 15px;
            }
            
            .form-container {
                padding: 30px 25px;
            }
            
            .header {
                padding: 35px 25px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            
            .btn-container {
                flex-direction: column;
            }
            
            .header h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="glass-card">
            <div class="header">
                <h1> Add New Child Profile</h1>
                <p>Create a profile for a child seeking sponsorship</p>
            </div>
            
            <div class="form-container">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <span style="font-size: 20px;">✅</span>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span style="font-size: 20px;">❌</span>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="addChildForm">
                    <div class="form-grid">
                        <div class="section-title"> Basic Information</div>
                        
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" required 
                                   placeholder="Enter first name"
                                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" required
                                   placeholder="Enter last name"
                                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Date of Birth <span class="required">*</span></label>
                            <input type="date" name="dob" required max="<?= date('Y-m-d') ?>"
                                   value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Gender <span class="required">*</span></label>
                            <select name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="section-title"> Personal Details</div>
                        
                        <div class="form-group full-width">
                            <label>About Me</label>
                            <textarea name="about_me"
                                      placeholder="Tell us about this child - their personality, interests, favorite activities, school life..."><?= htmlspecialchars($_POST['about_me'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Aspiration / Dreams</label>
                            <textarea name="aspiration"
                                      placeholder="What does this child dream of becoming? What are their goals and aspirations for the future?"><?= htmlspecialchars($_POST['aspiration'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="section-title">Media Uploads</div>
                        
                        <div class="form-group">
                            <label>Profile Picture</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="profile_picture" id="profile_picture" 
                                       accept="image/*" onchange="previewImage(event)">
                                <label for="profile_picture" class="file-input-label">
                                    <div class="icon">📷</div>
                                    <div class="text">Choose Profile Picture</div>
                                    <div class="subtext">JPG, PNG, GIF (Max 5MB)</div>
                                </label>
                            </div>
                            <div id="imagePreview" class="file-preview"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Introduction Video</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="profile_video" id="profile_video" 
                                       accept="video/*" onchange="previewVideo(event)">
                                <label for="profile_video" class="file-input-label">
                                    <div class="icon">🎥</div>
                                    <div class="text">Choose Introduction Video</div>
                                    <div class="subtext">MP4, AVI, MOV, WMV (Max 50MB)</div>
                                </label>
                            </div>
                            <div id="videoPreview" class="file-preview"></div>
                        </div>
                    </div>
                    
                    <div class="btn-container">
                        <button type="submit" class="btn btn-primary">
                            <span></span>
                            <span>Create Child Profile</span>
                        </button>
                        <a href="child_management.php" class="btn btn-secondary">
                            <span>←</span>
                            <span>Back to List</span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('imagePreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = <img src="${e.target.result}" alt="Preview">;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }
        
        function previewVideo(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('videoPreview');
            
            if (file) {
                preview.innerHTML = <p><span style="font-size: 20px;">✅</span> Video selected: <strong>${file.name}</strong></p>;
                preview.style.display = 'block';
            }
        }
        
        // Form validation
        document.getElementById('addChildForm').addEventListener('submit', function(e) {
            const dobInput = document.querySelector('input[name="dob"]');
            if (!dobInput.value) return;
            
            const dob = new Date(dobInput.value);
            const today = new Date();
            const age = Math.floor((today - dob) / (365.25 * 24 * 60 * 60 * 1000));
            
            if (age < 0 || age > 18) {
                e.preventDefault();
                alert('⚠ Child age must be between 0-18 years.');
            }
        });
    </script>
</body>
</html>