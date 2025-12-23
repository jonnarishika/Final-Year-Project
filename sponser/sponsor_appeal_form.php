<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../signup_and_login/login.php");
    exit();
}

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/fraud_services.php';

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT sponsor_id FROM sponsors WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$sponsor_result = $stmt->get_result()->fetch_assoc();

if (!$sponsor_result) {
    die('Access denied. Sponsors only.');
}

$sponsor_id = $sponsor_result['sponsor_id'];

if (!isset($_GET['case_id']) || !is_numeric($_GET['case_id'])) {
    die('Invalid case ID');
}

$case_id = intval($_GET['case_id']);

$stmt = $conn->prepare("
    SELECT fc.fraud_case_id, fc.status, fc.summary,
           s.first_name, s.last_name
    FROM fraud_cases fc
    INNER JOIN sponsors s ON fc.sponsor_id = s.sponsor_id
    WHERE fc.fraud_case_id = ? AND fc.sponsor_id = ?
");
$stmt->bind_param('ii', $case_id, $sponsor_id);
$stmt->execute();
$case_result = $stmt->get_result()->fetch_assoc();

if (!$case_result) {
    die('Case not found or access denied');
}

$stmt = $conn->prepare("
    SELECT appeal_id FROM fraud_appeals 
    WHERE fraud_case_id = ? AND status = 'pending'
");
$stmt->bind_param('i', $case_id);
$stmt->execute();
$existing_appeal = $stmt->get_result()->fetch_assoc();

if ($existing_appeal) {
    header("Location: sponser_main_page.php?error=appeal_exists");
    exit();
}

$submission_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_appeal'])) {
    $appeal_text = trim($_POST['appeal_text']);
    $attachment = null;
    
    // Handle PDF upload
    if (isset($_FILES['appeal_pdf']) && $_FILES['appeal_pdf']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['appeal_pdf'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate PDF
        if ($file_ext !== 'pdf') {
            $submission_result = ['success' => false, 'message' => 'Only PDF files are allowed'];
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $submission_result = ['success' => false, 'message' => 'File size must be less than 5MB'];
        } else {
            // Create upload directory if not exists
            $upload_dir = __DIR__ . '/../uploads/appeal_attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $filename = "appeal_{$sponsor_id}_{$case_id}_" . time() . ".pdf";
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $attachment = "uploads/appeal_attachments/" . $filename;
            } else {
                $submission_result = ['success' => false, 'message' => 'Failed to upload file'];
            }
        }
    }
    
    // Validate text
    if (!$submission_result) {
        if (empty($appeal_text)) {
            $submission_result = ['success' => false, 'message' => 'Please provide your explanation'];
        } elseif (strlen($appeal_text) < 50) {
            $submission_result = ['success' => false, 'message' => 'Please provide a more detailed explanation (minimum 50 characters)'];
        } else {
            $result = submitAppeal($conn, $sponsor_id, $case_id, $appeal_text, $attachment);
            
            if ($result['success']) {
                $submission_result = ['success' => true, 'message' => 'Your appeal has been submitted successfully!'];
            } else {
                $submission_result = ['success' => false, 'message' => $result['error']];
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Appeal</title>
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

        .container {
            max-width: 900px;
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
        }

        .page-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.2);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: #dc2626;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            font-size: 1rem;
            color: #71717a;
            font-weight: 500;
        }

        .case-info-box {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(254, 240, 138, 0.2);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 0.875rem;
            color: #71717a;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 1rem;
            color: #18181b;
            font-weight: 700;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #18181b;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: #3f3f46;
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: ' *';
            color: #dc2626;
        }

        .form-textarea {
            width: 100%;
            min-height: 200px;
            padding: 1rem;
            border: 2px solid rgba(254, 240, 138, 0.4);
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: rgba(255, 255, 255, 0.7);
            transition: all 0.3s;
            resize: vertical;
        }

        .form-textarea:focus {
            outline: none;
            border-color: rgba(254, 240, 138, 0.8);
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 4px rgba(254, 249, 195, 0.3);
        }

        .file-upload-area {
            border: 2px dashed rgba(254, 240, 138, 0.4);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: rgba(254, 252, 232, 0.3);
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload-area:hover {
            border-color: rgba(254, 240, 138, 0.6);
            background: rgba(254, 252, 232, 0.5);
        }

        .file-upload-area.dragover {
            border-color: #fbbf24;
            background: rgba(254, 249, 195, 0.4);
        }

        .upload-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .upload-text {
            font-size: 1rem;
            color: #3f3f46;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            font-size: 0.875rem;
            color: #71717a;
        }

        #fileInput {
            display: none;
        }

        .file-preview {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(254, 249, 195, 0.3);
            border-radius: 12px;
            display: none;
        }

        .file-preview.active {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .file-icon {
            font-size: 2rem;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #18181b;
        }

        .file-size {
            font-size: 0.875rem;
            color: #71717a;
        }

        .remove-file {
            background: rgba(239, 68, 68, 0.1);
            border: none;
            color: #dc2626;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .remove-file:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .form-hint {
            font-size: 0.875rem;
            color: #71717a;
            margin-top: 0.5rem;
        }

        .char-counter {
            text-align: right;
            font-size: 0.875rem;
            color: #71717a;
            margin-top: 0.5rem;
        }

        .guidelines-box {
            background: rgba(254, 249, 195, 0.3);
            border: 2px solid rgba(254, 240, 138, 0.5);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .guidelines-title {
            font-size: 1rem;
            font-weight: 700;
            color: #18181b;
            margin-bottom: 1rem;
        }

        .guidelines-list {
            list-style: none;
            padding-left: 0;
        }

        .guidelines-list li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
            font-size: 0.95rem;
            color: #3f3f46;
            line-height: 1.6;
        }

        .guidelines-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #16a34a;
            font-weight: 700;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-primary {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.9), rgba(153, 27, 27, 0.8));
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(220, 38, 38, 0.5);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: #3f3f46;
            border: 2px solid rgba(254, 240, 138, 0.4);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 1);
            border-color: rgba(254, 240, 138, 0.6);
        }

        .alert {
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 2px solid rgba(34, 197, 94, 0.3);
            color: #16a34a;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 2px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }

        .alert-success strong,
        .alert-error strong {
            display: block;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="sponser_main_page.php" class="back-btn">
            ← Back to Dashboard
        </a>

        <div class="page-header">
            <h1 class="page-title">
                ✍️ Submit an Appeal
            </h1>
            <p class="page-subtitle">
                Explain your situation and provide supporting documents
            </p>
        </div>

        <?php if ($submission_result): ?>
            <div class="alert <?php echo $submission_result['success'] ? 'alert-success' : 'alert-error'; ?>">
                <?php if ($submission_result['success']): ?>
                    <strong>✓ Appeal Submitted Successfully!</strong>
                    Your appeal has been received and will be reviewed by our administrators shortly.
                    <br><br>
                    <a href="sponser_main_page.php" style="color: inherit; text-decoration: underline; font-weight: 700;">
                        Return to Dashboard
                    </a>
                <?php else: ?>
                    <strong>✗ Submission Error</strong>
                    <?php echo htmlspecialchars($submission_result['message']); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="case-info-box">
            <div class="info-row">
                <span class="info-label">Case ID</span>
                <span class="info-value">#<?php echo $case_id; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Current Status</span>
                <span class="info-value"><?php echo ucwords(str_replace('_', ' ', $case_result['status'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Sponsor Name</span>
                <span class="info-value"><?php echo htmlspecialchars($case_result['first_name'] . ' ' . $case_result['last_name']); ?></span>
            </div>
        </div>

        <div class="guidelines-box">
            <div class="guidelines-title">📋 Appeal Guidelines</div>
            <ul class="guidelines-list">
                <li>Be honest and provide accurate information</li>
                <li>Explain any misunderstandings or special circumstances</li>
                <li>Combine all supporting documents into ONE PDF file (max 5MB)</li>
                <li>Include bank statements, ID proof, or transaction screenshots if relevant</li>
                <li>Remain respectful and professional in your communication</li>
            </ul>
        </div>

        <?php if (!$submission_result || !$submission_result['success']): ?>
        <form method="POST" action="" id="appealForm" enctype="multipart/form-data">
            <div class="form-card">
                <h2 class="form-section-title">Your Appeal</h2>

                <div class="form-group">
                    <label class="form-label required">Explain Your Situation</label>
                    <textarea 
                        name="appeal_text" 
                        class="form-textarea" 
                        placeholder="Please provide a detailed explanation..."
                        required
                        minlength="50"
                        maxlength="2000"
                        id="appealTextarea"><?php echo isset($_POST['appeal_text']) ? htmlspecialchars($_POST['appeal_text']) : ''; ?></textarea>
                    <div class="char-counter">
                        <span id="charCount">0</span> / 2000 characters (minimum 50)
                    </div>
                    <p class="form-hint">
                        Be specific: Include dates, transaction details, and any relevant context.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">Supporting Documents (Optional)</label>
                    <div class="file-upload-area" id="uploadArea">
                        <div class="upload-icon">📄</div>
                        <div class="upload-text">Click or drag to upload PDF</div>
                        <div class="upload-hint">Maximum file size: 5MB | Only PDF files allowed</div>
                    </div>
                    <input type="file" id="fileInput" name="appeal_pdf" accept=".pdf">
                    
                    <div class="file-preview" id="filePreview">
                        <div class="file-info">
                            <div class="file-icon">📎</div>
                            <div class="file-details">
                                <div class="file-name" id="fileName"></div>
                                <div class="file-size" id="fileSize"></div>
                            </div>
                        </div>
                        <button type="button" class="remove-file" id="removeFile">Remove</button>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                        Cancel
                    </button>
                    <button type="submit" name="submit_appeal" class="btn btn-primary" id="submitBtn">
                        📤 Submit Appeal
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        // Character counter
        const textarea = document.getElementById('appealTextarea');
        const charCount = document.getElementById('charCount');
        
        function updateCharCount() {
            const length = textarea.value.length;
            charCount.textContent = length;
            charCount.style.color = length < 50 ? '#dc2626' : length > 2000 ? '#d97706' : '#71717a';
        }
        
        if (textarea) {
            textarea.addEventListener('input', updateCharCount);
            updateCharCount();
        }

        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeFile = document.getElementById('removeFile');

        uploadArea.addEventListener('click', () => fileInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        });

        removeFile.addEventListener('click', () => {
            fileInput.value = '';
            filePreview.classList.remove('active');
        });

        function handleFile(file) {
            if (file.type !== 'application/pdf') {
                alert('Only PDF files are allowed');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }

            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            filePreview.classList.add('active');
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        // Form validation
        const form = document.getElementById('appealForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const text = textarea.value.trim();
                
                if (text.length < 50) {
                    e.preventDefault();
                    alert('Please provide a more detailed explanation (minimum 50 characters)');
                    textarea.focus();
                    return;
                }
                
                if (!confirm('Are you sure you want to submit this appeal? You can only submit one appeal per case.')) {
                    e.preventDefault();
                }
            });
        }
    </script>
</body>
</html>