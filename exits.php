<?php
// DEBUG (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Database connection (use existing $pdo from dp.php if available)
if (!isset($pdo) || !($pdo instanceof PDO)) {
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
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add':
                // Add new employee
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_profiles (personal_info_id, job_role_id, employee_number, hire_date, employment_status, current_salary, work_email, work_phone, location, remote_work) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['job_role_id'],
                        $_POST['employee_number'],
                        $_POST['hire_date'],
                        $_POST['employment_status'],
                        $_POST['current_salary'],
                        $_POST['work_email'],
                        $_POST['work_phone'],
                        $_POST['location'],
                        isset($_POST['remote_work']) ? 1 : 0
                    ]);
                    $message = "Employee profile added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update employee
                try {
                    $stmt = $pdo->prepare("UPDATE employee_profiles SET personal_info_id=?, job_role_id=?, employee_number=?, hire_date=?, employment_status=?, current_salary=?, work_email=?, work_phone=?, location=?, remote_work=? WHERE employee_id=?");
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['job_role_id'],
                        $_POST['employee_number'],
                        $_POST['hire_date'],
                        $_POST['employment_status'],
                        $_POST['current_salary'],
                        $_POST['work_email'],
                        $_POST['work_phone'],
                        $_POST['location'],
                        isset($_POST['remote_work']) ? 1 : 0,
                        $_POST['employee_id']
                    ]);
                    $message = "Employee profile updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete employee
                try {
                    $stmt = $pdo->prepare("DELETE FROM employee_profiles WHERE employee_id=?");
                    $stmt->execute([$_POST['employee_id']]);
                    $message = "Employee profile deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $messageType = "danger";
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch employees with related data
$stmt = $pdo->query("
    SELECT 
        ep.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        pi.first_name,
        pi.last_name,
        pi.phone_number,
        jr.title as job_title,
        jr.department
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY ep.employee_id DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch personal information for dropdown
$stmt = $pdo->query("SELECT personal_info_id, CONCAT(first_name, ' ', last_name) as full_name FROM personal_information ORDER BY first_name");
$personalInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch job roles for dropdown
$stmt = $pdo->query("SELECT job_role_id, title, department FROM job_roles ORDER BY title");
$jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Exit Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Copied styling from employee profiles for identical look */
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }
        .section-title { color: var(--azure-blue); margin-bottom: 30px; font-weight: 600; }
        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }
        body { background: var(--azure-blue-pale); }
        .main-content { background: var(--azure-blue-pale); padding: 20px; }
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .search-box { position: relative; flex: 1; max-width: 400px; }
        .search-box input { width: 100%; padding: 12px 15px 12px 45px; border: 2px solid #e0e0e0; border-radius: 25px; font-size: 16px; transition: all 0.3s ease; }
        .search-box input:focus { border-color: var(--azure-blue); outline: none; box-shadow: 0 0 10px rgba(233, 30, 99, 0.3); }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #666; }
        .btn { padding: 12px 25px; border: none; border-radius: 25px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-primary { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4); background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%); }
        .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
        .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: white; }
        .btn-small { padding: 8px 15px; font-size: 14px; margin: 0 3px; }
        .table-container { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%); padding: 15px; text-align: left; font-weight: 600; color: var(--azure-blue-dark); border-bottom: 2px solid #dee2e6; }
        .table td { padding: 15px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        .table tbody tr:hover { background-color: var(--azure-blue-lighter); transform: scale(1.01); transition: all 0.2s ease; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; background: #e2e3e5; color: #343a40; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }
        .modal-content { background: white; margin: 5% auto; padding: 0; border-radius: 15px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; padding: 20px 30px; border-radius: 15px 15px 0 0; }
        .modal-header h2 { margin: 0; }
        .close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: white; opacity: 0.7; }
        .close:hover { opacity: 1; }
        .modal-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--azure-blue-dark); }
        .form-control { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: all 0.3s ease; box-sizing: border-box; }
        .form-control:focus { border-color: var(--azure-blue); outline: none; box-shadow: 0 0 10px rgba(233, 30, 99, 0.3); }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        .no-results { text-align: center; padding: 50px; color: #666; }
        .no-results i { font-size: 4rem; margin-bottom: 20px; color: #ddd; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .required { color: #dc3545; }
        @media (max-width: 768px) {
            .controls { flex-direction: column; align-items: stretch; }
            .search-box { max-width: none; }
            .form-row { flex-direction: column; }
            .table-container { overflow-x: auto; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
                        <div class="main-content">
                <h2 class="section-title">Exits</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>


                    </div>
                </div>
            </div>
        </div>

   
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#exitsTable tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>