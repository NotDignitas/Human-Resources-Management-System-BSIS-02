<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection and helper functions
require_once 'dp.php';

// Database connection
$host = 'localhost';
$dbname = 'CC_HR';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new survey
                try {
                    $stmt = $pdo->prepare("INSERT INTO post_exit_surveys (employee_id, exit_id, survey_date, survey_response, satisfaction_rating, submitted_date) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['exit_id'],
                        $_POST['survey_date'],
                        $_POST['survey_response'],
                        $_POST['satisfaction_rating']
                    ]);
                    $message = "Post-exit survey added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding survey: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update survey
                try {
                    $stmt = $pdo->prepare("UPDATE post_exit_surveys SET employee_id=?, exit_id=?, survey_date=?, survey_response=?, satisfaction_rating=?, submitted_date=NOW() WHERE survey_id=?");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['exit_id'],
                        $_POST['survey_date'],
                        $_POST['survey_response'],
                        $_POST['satisfaction_rating'],
                        $_POST['survey_id']
                    ]);
                    $message = "Post-exit survey updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating survey: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete survey
                try {
                    $stmt = $pdo->prepare("DELETE FROM post_exit_surveys WHERE survey_id=?");
                    $stmt->execute([$_POST['survey_id']]);
                    $message = "Post-exit survey deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting survey: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch surveys with related data
$stmt = $pdo->query("
    SELECT 
        pes.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        e.exit_date,
        e.exit_reason,
        jr.title as job_title,
        jr.department
    FROM post_exit_surveys pes
    LEFT JOIN employee_profiles ep ON pes.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN exits e ON pes.exit_id = e.exit_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY pes.survey_date DESC, pes.survey_id DESC
");
$surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown (only those with exits)
$stmt = $pdo->query("
    SELECT DISTINCT
        ep.employee_id,
        ep.employee_number,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name
    FROM employee_profiles ep
    INNER JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    INNER JOIN exits e ON ep.employee_id = e.employee_id
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown
$stmt = $pdo->query("
    SELECT 
        e.exit_id,
        e.exit_date,
        e.exit_reason,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY e.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalSurveys = count($surveys);
$avgRating = 0;
if ($totalSurveys > 0) {
    $ratings = array_filter(array_column($surveys, 'satisfaction_rating'));
    $avgRating = count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : 0;
}
$recentSurveys = array_slice($surveys, 0, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Exit Surveys Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .container-fluid {
            padding: 0;
        }
        
        .row {
            margin-right: 0;
            margin-left: 0;
        }

        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.2);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-content h3 {
            font-size: 28px;
            margin: 0;
            color: var(--azure-blue-dark);
        }

        .stat-content p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #20c9c9 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
            margin: 0 3px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--azure-blue-lighter);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .rating-stars {
            color: #ffc107;
            font-size: 18px;
        }

        .rating-number {
            display: inline-block;
            background: var(--azure-blue);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 600;
        }

        .response-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #666;
            font-size: 14px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        .modal-content.view-modal {
            max-width: 600px;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
        }

        .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .form-control {
            width: 100%;
            padding: 6px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 150px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
        }

        .rating-input {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .rating-input input[type="radio"] {
            display: none;
        }

        .rating-input label {
            cursor: pointer;
            font-size: 30px;
            color: #ddd;
            transition: color 0.2s;
        }

        .rating-input input[type="radio"]:checked ~ label,
        .rating-input label:hover,
        .rating-input label:hover ~ label {
            color: #ffc107;
        }

        .view-details {
            background: var(--azure-blue-pale);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .view-details h4 {
            color: var(--azure-blue-dark);
            margin-bottom: 15px;
            font-size: 18px;
        }

        .view-details p {
            margin: 8px 0;
            color: #333;
        }

        .view-details strong {
            color: var(--azure-blue-dark);
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-results {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .form-row {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Post-Exit Survey Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Dashboard -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-icon">📊</div>
                            <div class="stat-content">
                                <h3><?= $totalSurveys ?></h3>
                                <p>Total Surveys</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">⭐</div>
                            <div class="stat-content">
                                <h3><?= $avgRating ?>/5</h3>
                                <p>Average Rating</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">📅</div>
                            <div class="stat-content">
                                <h3><?= count($recentSurveys) ?></h3>
                                <p>Recent Surveys</p>
                            </div>
                        </div>
                    </div>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by employee name, department, or exit reason...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add New Survey
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="surveyTable">
                            <thead>
                                <tr>
                                    <th>Survey ID</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Exit Date</th>
                                    <th>Survey Date</th>
                                    <th>Rating</th>
                                    <th>Response Preview</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="surveyTableBody">
                                <?php foreach ($surveys as $survey): ?>
                                <tr>
                                    <td><strong>#<?= str_pad($survey['survey_id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($survey['employee_name']) ?></strong><br>
                                            <small style="color: #666;">ID: <?= htmlspecialchars($survey['employee_number']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($survey['department'] ?? 'N/A') ?></td>
                                    <td><?= $survey['exit_date'] ? date('M d, Y', strtotime($survey['exit_date'])) : 'N/A' ?></td>
                                    <td><?= date('M d, Y', strtotime($survey['survey_date'])) ?></td>
                                    <td>
                                        <?php if ($survey['satisfaction_rating']): ?>
                                            <span class="rating-stars">
                                                <?php 
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $survey['satisfaction_rating'] ? '★' : '☆';
                                                }
                                                ?>
                                            </span>
                                            <span class="rating-number"><?= $survey['satisfaction_rating'] ?>/5</span>
                                        <?php else: ?>
                                            <span style="color: #999;">No rating</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="response-preview" title="<?= htmlspecialchars($survey['survey_response'] ?? '') ?>">
                                            <?= htmlspecialchars(substr($survey['survey_response'] ?? 'No response provided', 0, 50)) ?>...
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-small" onclick="viewSurvey(<?= $survey['survey_id'] ?>)">
                                            👁️ View
                                        </button>
                                        <button class="btn btn-warning btn-small" onclick="editSurvey(<?= $survey['survey_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteSurvey(<?= $survey['survey_id'] ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($surveys)): ?>
                        <div class="no-results">
                            <i>📋</i>
                            <h3>No surveys found</h3>
                            <p>Start by adding your first post-exit survey.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Survey Modal -->
    <div id="surveyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Post-Exit Survey</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="surveyForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="survey_id" name="survey_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee</label>
                                <select id="employee_id" name="employee_id" class="form-control" required onchange="updateExitDropdown()">
                                    <option value="">Select employee...</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>">
                                        <?= htmlspecialchars($employee['full_name']) ?> (<?= htmlspecialchars($employee['employee_number']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="exit_id">Exit Record</label>
                                <select id="exit_id" name="exit_id" class="form-control" required>
                                    <option value="">Select exit record...</option>
                                    <?php foreach ($exits as $exit): ?>
                                    <option value="<?= $exit['exit_id'] ?>" data-employee-id="<?= isset($exit['employee_id']) ? $exit['employee_id'] : '' ?>">
                                        <?= htmlspecialchars($exit['employee_name']) ?> - <?= date('M d, Y', strtotime($exit['exit_date'])) ?> (<?= htmlspecialchars($exit['exit_reason']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="survey_date">Survey Date</label>
                                <input type="date" id="survey_date" name="survey_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Satisfaction Rating</label>
                                <div class="rating-input">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" id="star<?= $i ?>" name="satisfaction_rating" value="<?= $i ?>">
                                    <label for="star<?= $i ?>">★</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="survey_response">Survey Response / Feedback</label>
                        <textarea id="survey_response" name="survey_response" class="form-control" placeholder="Enter detailed feedback from the exit survey..."></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Survey</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Survey Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content view-modal">
            <div class="modal-header">
                <h2>Survey Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let surveysData = <?= json_encode($surveys) ?>;
        let exitsData = <?= json_encode($exits) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('surveyTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Modal functions
        function openModal(mode, surveyId = null) {
            const modal = document.getElementById('surveyModal');
            const form = document.getElementById('surveyForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Post-Exit Survey';
                action.value = 'add';
                form.reset();
                document.getElementById('survey_id').value = '';
            } else if (mode === 'edit' && surveyId) {
                title.textContent = 'Edit Post-Exit Survey';
                action.value = 'update';
                document.getElementById('survey_id').value = surveyId;
                populateEditForm(surveyId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('surveyModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function updateExitDropdown() {
            const employeeId = document.getElementById('employee_id').value;
            const exitSelect = document.getElementById('exit_id');
            const options = exitSelect.getElementsByTagName('option');
            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                if (!option.value) { // placeholder
                    option.style.display = '';
                    continue;
                }
                const empId = option.getAttribute('data-employee-id');
                if (employeeId && empId === employeeId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
            exitSelect.value = '';
        }

        function populateEditForm(surveyId) {
            const survey = surveysData.find(s => s.survey_id == surveyId);
            if (!survey) return;

            document.getElementById('employee_id').value = survey.employee_id;
            updateExitDropdown();
            document.getElementById('exit_id').value = survey.exit_id;
            document.getElementById('survey_date').value = survey.survey_date;
            document.getElementById('survey_response').value = survey.survey_response;

            // Clear all ratings
            document.querySelectorAll('input[name="satisfaction_rating"]').forEach(el => el.checked = false);
            if (survey.satisfaction_rating) {
                const ratingInput = document.querySelector(`input[name="satisfaction_rating"][value="${survey.satisfaction_rating}"]`);
                if (ratingInput) ratingInput.checked = true;
            }
        }

        function viewSurvey(surveyId) {
            const survey = surveysData.find(s => s.survey_id == surveyId);
            if (!survey) return;

            const modalBody = document.getElementById('viewModalBody');
            modalBody.innerHTML = `
                <div class="view-details">
                    <h4>Employee Information</h4>
                    <p><strong>Name:</strong> ${survey.employee_name || 'N/A'}</p>
                    <p><strong>Employee Number:</strong> ${survey.employee_number || 'N/A'}</p>
                    <p><strong>Department:</strong> ${survey.department || 'N/A'}</p>
                    <p><strong>Job Title:</strong> ${survey.job_title || 'N/A'}</p>
                </div>
                <div class="view-details">
                    <h4>Exit Details</h4>
                    <p><strong>Exit Date:</strong> ${survey.exit_date ? new Date(survey.exit_date).toLocaleDateString() : 'N/A'}</p>
                    <p><strong>Exit Reason:</strong> ${survey.exit_reason || 'N/A'}</p>
                </div>
                <div class="view-details">
                    <h4>Survey Information</h4>
                    <p><strong>Survey Date:</strong> ${survey.survey_date ? new Date(survey.survey_date).toLocaleDateString() : 'N/A'}</p>
                    <p><strong>Satisfaction Rating:</strong> ${survey.satisfaction_rating ? survey.satisfaction_rating + '/5' : 'No rating'}</p>
                    <p><strong>Response:</strong><br>${survey.survey_response || 'No response provided'}</p>
                </div>
            `;

            const modal = document.getElementById('viewModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function editSurvey(surveyId) {
            openModal('edit', surveyId);
        }

        function deleteSurvey(surveyId) {
            if (!confirm('Are you sure you want to delete this survey?')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="survey_id" value="${surveyId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const surveyModal = document.getElementById('surveyModal');
            const viewModal = document.getElementById('viewModal');
            if (event.target === surveyModal) {
                closeModal();
            }
            if (event.target === viewModal) {
                closeViewModal();
            }
        }
    </script>
</body>
</html>
