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
                // Add new exit document
                try {
                    $stmt = $pdo->prepare("INSERT INTO exit_documents (exit_id, employee_id, document_type, document_name, document_url, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['document_type'],
                        $_POST['document_name'],
                        $_POST['document_url'],
                        $_POST['notes']
                    ]);
                    $message = "Exit document added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding exit document: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update exit document
                try {
                    $stmt = $pdo->prepare("UPDATE exit_documents SET exit_id=?, employee_id=?, document_type=?, document_name=?, document_url=?, notes=? WHERE document_id=?");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['document_type'],
                        $_POST['document_name'],
                        $_POST['document_url'],
                        $_POST['notes'],
                        $_POST['document_id']
                    ]);
                    $message = "Exit document updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating exit document: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete exit document
                try {
                    $stmt = $pdo->prepare("DELETE FROM exit_documents WHERE document_id=?");
                    $stmt->execute([$_POST['document_id']]);
                    $message = "Exit document deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting exit document: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch exit documents with related data
$stmt = $pdo->query("
    SELECT 
        ed.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        ee.exit_type,
        ee.exit_date,
        jr.title as job_title,
        jr.department
    FROM exit_documents ed
    LEFT JOIN employee_profiles ep ON ed.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN employee_exits ee ON ed.exit_id = ee.exit_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY ed.document_id DESC
");
$exitDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employee exits for dropdown
$stmt = $pdo->query("
    SELECT 
        ee.exit_id, 
        ee.exit_type, 
        ee.exit_date,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM employee_exits ee
    LEFT JOIN employee_profiles ep ON ee.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY ee.exit_date DESC
");
$employeeExits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id, 
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    WHERE ep.employment_status != 'Terminated'
    ORDER BY pi.first_name
");
$activeEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exit Documents Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for exit documents page */
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

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
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

        .document-type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-resignation {
            background: #fff3cd;
            color: #856404;
        }

        .type-clearance {
            background: #d1ecf1;
            color: #0c5460;
        }

        .type-certificate {
            background: #d4edda;
            color: #155724;
        }

        .type-retirement {
            background: #e2e3e5;
            color: #383d41;
        }

        .type-termination {
            background: #f8d7da;
            color: #721c24;
        }

        .type-contract {
            background: #fce4ec;
            color: #880e4f;
        }

        .type-default {
            background: #f8f9fa;
            color: #495057;
        }

        .exit-type-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .exit-resignation {
            background: #fff3cd;
            color: #856404;
        }

        .exit-retirement {
            background: #e2e3e5;
            color: #383d41;
        }

        .exit-termination {
            background: #f8d7da;
            color: #721c24;
        }

        .exit-contract-end {
            background: #fce4ec;
            color: #880e4f;
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
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 95vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
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
            padding: 12px 15px;
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

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
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

        .document-link {
            color: var(--azure-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .document-link:hover {
            color: var(--azure-blue-dark);
            text-decoration: underline;
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

            .content {
                padding: 20px;
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
                <h2 class="section-title">Exit Documents Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by employee name, document type, or document name...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            📄 Add Exit Document
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="documentsTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Exit Type</th>
                                    <th>Document Type</th>
                                    <th>Document Name</th>
                                    <th>Exit Date</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="documentsTableBody">
                                <?php foreach ($exitDocuments as $document): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($document['employee_name']) ?></strong><br>
                                            <small style="color: #666;"><?= htmlspecialchars($document['employee_number']) ?> | <?= htmlspecialchars($document['job_title']) ?></small><br>
                                            <small style="color: #888;"><?= htmlspecialchars($document['department']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $exitClass = 'exit-' . strtolower(str_replace(['_', ' '], '-', $document['exit_type']));
                                        ?>
                                        <span class="exit-type-badge <?= $exitClass ?>">
                                            <?= htmlspecialchars($document['exit_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $typeClass = 'type-' . strtolower(str_replace([' ', '_'], '-', $document['document_type']));
                                        if (!in_array($typeClass, ['type-resignation', 'type-clearance', 'type-certificate', 'type-retirement', 'type-termination', 'type-contract'])) {
                                            $typeClass = 'type-default';
                                        }
                                        ?>
                                        <span class="document-type-badge <?= $typeClass ?>">
                                            <?= htmlspecialchars($document['document_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= htmlspecialchars($document['document_url']) ?>" class="document-link" target="_blank">
                                            <?= htmlspecialchars($document['document_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= $document['exit_date'] ? date('M d, Y', strtotime($document['exit_date'])) : 'N/A' ?></td>
                                    <td>
                                        <small><?= htmlspecialchars(substr($document['notes'], 0, 50)) ?><?= strlen($document['notes']) > 50 ? '...' : '' ?></small>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-small" onclick="viewDocument('<?= htmlspecialchars($document['document_url']) ?>')">
                                            👁️ View
                                        </button>
                                        <button class="btn btn-warning btn-small" onclick="editDocument(<?= $document['document_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteDocument(<?= $document['document_id'] ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($exitDocuments)): ?>
                        <div class="no-results">
                            <i>📄</i>
                            <h3>No exit documents found</h3>
                            <p>Start by adding your first exit document.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Document Modal -->
    <div id="documentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Exit Document</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="documentForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="document_id" name="document_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="exit_id">Employee Exit</label>
                                <select id="exit_id" name="exit_id" class="form-control" required onchange="updateEmployeeFromExit()">
                                    <option value="">Select exit record...</option>
                                    <?php foreach ($employeeExits as $exit): ?>
                                    <option value="<?= $exit['exit_id'] ?>" data-employee-id="<?= $exit['employee_id'] ?? '' ?>">
                                        <?= htmlspecialchars($exit['employee_name']) ?> - <?= htmlspecialchars($exit['exit_type']) ?> (<?= date('M d, Y', strtotime($exit['exit_date'])) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select employee...</option>
                                    <?php foreach ($activeEmployees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>"><?= htmlspecialchars($employee['employee_name']) ?> (<?= htmlspecialchars($employee['employee_number']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="document_type">Document Type</label>
                                <select id="document_type" name="document_type" class="form-control" required>
                                    <option value="">Select document type...</option>
                                    <option value="Resignation Letter">Resignation Letter</option>
                                    <option value="Clearance Form">Clearance Form</option>
                                    <option value="Certificate of Employment">Certificate of Employment</option>
                                    <option value="Final Pay Slip">Final Pay Slip</option>
                                    <option value="Retirement Application">Retirement Application</option>
                                    <option value="Service Record">Service Record</option>
                                    <option value="Retirement Benefits">Retirement Benefits</option>
                                    <option value="Medical Certificate">Medical Certificate</option>
                                    <option value="Contract Completion">Contract Completion</option>
                                    <option value="Performance Evaluation">Performance Evaluation</option>
                                    <option value="Job Offer Copy">Job Offer Copy</option>
                                    <option value="Termination Notice">Termination Notice</option>
                                    <option value="Investigation Report">Investigation Report</option>
                                    <option value="Due Process Records">Due Process Records</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="document_name">Document Name</label>
                                <input type="text" id="document_name" name="document_name" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="document_url">Document URL/Path</label>
                        <input type="text" id="document_url" name="document_url" class="form-control" placeholder="/documents/exits/filename.pdf" required>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Additional notes about this document..."></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let documentsData = <?= json_encode($exitDocuments) ?>;
        let employeeExits = <?= json_encode($employeeExits) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('documentsTableBody');
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
        function openModal(mode, documentId = null) {
            const modal = document.getElementById('documentModal');
            const form = document.getElementById('documentForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add Exit Document';
                action.value = 'add';
                form.reset();
                document.getElementById('document_id').value = '';
            } else if (mode === 'edit' && documentId) {
                title.textContent = 'Edit Exit Document';
                action.value = 'update';
                document.getElementById('document_id').value = documentId;
                populateEditForm(documentId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('documentModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(documentId) {
            const document = documentsData.find(doc => doc.document_id == documentId);
            if (document) {
                document.getElementById('exit_id').value = document.exit_id || '';
                document.getElementById('employee_id').value = document.employee_id || '';
                document.getElementById('document_type').value = document.document_type || '';
                document.getElementById('document_name').value = document.document_name || '';
                document.getElementById('document_url').value = document.document_url || '';
                document.getElementById('notes').value = document.notes || '';
            }
        }

        function updateEmployeeFromExit() {
            const exitSelect = document.getElementById('exit_id');
            const employeeSelect = document.getElementById('employee_id');
            const selectedOption = exitSelect.options[exitSelect.selectedIndex];
            
            if (selectedOption && selectedOption.dataset.employeeId) {
                employeeSelect.value = selectedOption.dataset.employeeId;
            }
        }

        function editDocument(documentId) {
            openModal('edit', documentId);
        }

        function deleteDocument(documentId) {
            if (confirm('Are you sure you want to delete this exit document? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="document_id" value="${documentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewDocument(documentUrl) {
            if (documentUrl) {
                window.open(documentUrl, '_blank');
            } else {
                alert('Document URL not available');
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('documentModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('documentForm').addEventListener('submit', function(e) {
            const documentUrl = document.getElementById('document_url').value.trim();
            if (!documentUrl.startsWith('/documents/') && !documentUrl.startsWith('http')) {
                e.preventDefault();
                alert('Please enter a valid document URL or file path');
                return;
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#documentsTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Auto-generate document name based on type and employee
            document.getElementById('document_type').addEventListener('change', function() {
                const exitSelect = document.getElementById('exit_id');
                const documentNameInput = document.getElementById('document_name');
                const selectedExitOption = exitSelect.options[exitSelect.selectedIndex];
                
                if (this.value && selectedExitOption && selectedExitOption.text) {
                    const employeeName = selectedExitOption.text.split(' - ')[0];
                    const suggestedName = this.value + ' - ' + employeeName;
                    
                    if (!documentNameInput.value) {
                        documentNameInput.value = suggestedName;
                    }
                }
            });

            // Auto-generate document URL based on name
            document.getElementById('document_name').addEventListener('input', function() {
                const documentUrlInput = document.getElementById('document_url');
                
                if (this.value && !documentUrlInput.value) {
                    const fileName = this.value.toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '_') + '.pdf';
                    documentUrlInput.value = '/documents/exits/' + fileName;
                }
            });
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>