<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'config.php';

// Use the global database connection
$pdo = $conn;

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_career_path':
                try {
                    $stmt = $pdo->prepare("INSERT INTO career_paths (path_name, description, department_id) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $_POST['path_name'],
                        $_POST['description'],
                        $_POST['department_id']
                    ]);
                    $message = "Career path added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding career path: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'add_career_stage':
                try {
                    $stmt = $pdo->prepare("INSERT INTO career_path_stages (path_id, job_role_id, stage_order, minimum_time_in_role, required_skills, required_experience) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['path_id'],
                        $_POST['job_role_id'],
                        $_POST['stage_order'],
                        $_POST['minimum_time_in_role'],
                        $_POST['required_skills'],
                        $_POST['required_experience']
                    ]);
                    $message = "Career stage added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding career stage: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'assign_employee_path':
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_career_paths (employee_id, path_id, current_stage_id, start_date, target_completion_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['path_id'],
                        $_POST['current_stage_id'],
                        $_POST['start_date'],
                        $_POST['target_completion_date'],
                        $_POST['status']
                    ]);
                    $message = "Employee assigned to career path successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error assigning employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_career_path':
                try {
                    $stmt = $pdo->prepare("DELETE FROM career_paths WHERE path_id=?");
                    $stmt->execute([$_POST['path_id']]);
                    $message = "Career path deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting career path: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch career paths
try {
    $stmt = $pdo->query("
        SELECT cp.*, d.department_name 
        FROM career_paths cp 
        LEFT JOIN departments d ON cp.department_id = d.department_id 
        ORDER BY cp.path_name
    ");
    $careerPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $careerPaths = [];
    $message = "Error fetching career paths: " . $e->getMessage();
    $messageType = "error";
}

// Fetch career path stages
try {
    $stmt = $pdo->query("
        SELECT cps.*, cp.path_name, jr.title as job_role_title 
        FROM career_path_stages cps 
        JOIN career_paths cp ON cps.path_id = cp.path_id 
        JOIN job_roles jr ON cps.job_role_id = jr.job_role_id 
        ORDER BY cp.path_name, cps.stage_order
    ");
    $careerStages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $careerStages = [];
}

// Fetch employee career paths
try {
    $stmt = $pdo->query("
        SELECT ecp.*, e.first_name, e.last_name, cp.path_name, cps.stage_order, jr.title as current_role
        FROM employee_career_paths ecp 
        JOIN employee_profiles ep ON ecp.employee_id = ep.employee_id 
        JOIN personal_information e ON ep.personal_info_id = e.personal_info_id 
        JOIN career_paths cp ON ecp.path_id = cp.path_id 
        JOIN career_path_stages cps ON ecp.current_stage_id = cps.stage_id 
        JOIN job_roles jr ON cps.job_role_id = jr.job_role_id 
        ORDER BY e.last_name, cp.path_name
    ");
    $employeePaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employeePaths = [];
}

// Fetch departments for dropdowns
try {
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// Fetch job roles for dropdowns
try {
    $stmt = $pdo->query("SELECT * FROM job_roles ORDER BY title");
    $jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $jobRoles = [];
}

// Fetch employees for dropdowns
try {
    $stmt = $pdo->query("
        SELECT ep.employee_id, pi.first_name, pi.last_name 
        FROM employee_profiles ep 
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id 
        ORDER BY pi.last_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM career_paths");
    $totalPaths = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM career_path_stages");
    $totalStages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_career_paths WHERE status = 'Active'");
    $activeAssignments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_career_paths WHERE status = 'Completed'");
    $completedPaths = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $totalPaths = 0;
    $totalStages = 0;
    $activeAssignments = 0;
    $completedPaths = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Development Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    
    <style>
        :root {
            --primary-color: #e91e63;
            --primary-dark: #c2185b;
            --primary-light: #f8bbd9;
            --secondary-color: #fce4ec;
            --accent-color: #ff4081;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --border-color: #e0e0e0;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;
        }

        .main-content {
            background: linear-gradient(135deg, #fce4ec 0%, #f8bbd9 100%);
            min-height: 100vh;
            padding: 30px;
        }

        .section-title {
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.15);
            border: 2px solid var(--primary-light);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(233, 30, 99, 0.25);
        }

        .stats-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .stats-card h3 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 10px 0;
        }

        .stats-card h6 {
            color: var(--text-light);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .tab-navigation {
            display: flex;
            background: white;
            border-radius: 15px;
            padding: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid var(--primary-light);
        }

        .tab-button {
            flex: 1;
            padding: 15px 20px;
            background: transparent;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab-button.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
        }

        .tab-button:hover:not(.active) {
            background: var(--secondary-color);
            color: var(--primary-color);
        }

        .tab-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 2px solid var(--primary-light);
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 20px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.1rem;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
        }

        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid var(--primary-light);
        }

        .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 20px 15px;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr:hover {
            background: var(--secondary-color);
            transform: scale(1.01);
        }

        .table tbody td {
            padding: 20px 15px;
            vertical-align: middle;
            border: none;
            color: var(--text-dark);
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 0.85rem;
            border-radius: 20px;
            margin: 2px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-warning {
            background: var(--warning-color);
            border: none;
            color: white;
        }

        .btn-warning:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger-color);
            border: none;
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        .btn-info {
            background: var(--info-color);
            border: none;
            color: white;
        }

        .btn-info:hover {
            background: #1976d2;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-color);
            border: none;
            color: white;
        }

        .btn-success:hover {
            background: #388e3c;
            transform: translateY(-2px);
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .no-results i {
            font-size: 4rem;
            color: var(--primary-light);
            margin-bottom: 20px;
        }

        .no-results h3 {
            color: var(--text-light);
            margin-bottom: 10px;
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
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .close {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close:hover {
            transform: scale(1.1);
        }

        .modal-body {
            padding: 30px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-completed {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-paused {
            background: #fff3e0;
            color: #ef6c00;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .tab-navigation {
                flex-direction: column;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                max-width: none;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
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
                <h2 class="section-title">Career Development Management</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-road"></i>
                            <h3><?php echo $totalPaths; ?></h3>
                            <h6>Career Paths</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-route"></i>
                            <h3><?php echo $totalStages; ?></h3>
                            <h6>Career Stages</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-user-check"></i>
                            <h3><?php echo $activeAssignments; ?></h3>
                            <h6>Active Assignments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-trophy"></i>
                            <h3><?php echo $completedPaths; ?></h3>
                            <h6>Completed Paths</h6>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="tab-navigation">
                    <button class="tab-button active" onclick="showTab('paths')">
                        <i class="fas fa-road"></i> Career Paths
                    </button>
                    <button class="tab-button" onclick="showTab('stages')">
                        <i class="fas fa-route"></i> Career Stages
                    </button>
                    <button class="tab-button" onclick="showTab('assignments')">
                        <i class="fas fa-user-check"></i> Employee Assignments
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="tab-content" id="careerTabsContent">
                    <!-- Career Paths Tab -->
                    <div id="paths" class="tab-pane active">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="pathSearch" placeholder="Search career paths..." onkeyup="searchPaths()">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('careerPath')">
                                ➕ Add Career Path
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="pathsTable">
                                <thead>
                                    <tr>
                                        <th>Path Name</th>
                                        <th>Department</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($careerPaths)): ?>
                                    <tr>
                                        <td colspan="4" class="no-results">
                                            <i class="fas fa-road"></i>
                                            <h3>No career paths found</h3>
                                            <p>Start by adding your first career path.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($careerPaths as $path): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($path['path_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($path['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(substr($path['description'], 0, 50)) . (strlen($path['description']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editCareerPath(<?php echo $path['path_id']; ?>, '<?php echo addslashes($path['path_name']); ?>', '<?php echo addslashes($path['description']); ?>', '<?php echo $path['department_id']; ?>')">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteCareerPath(<?php echo $path['path_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Career Stages Tab -->
                    <div id="stages" class="tab-pane">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="stageSearch" placeholder="Search career stages..." onkeyup="searchStages()">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('careerStage')">
                                ➕ Add Career Stage
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="stagesTable">
                                <thead>
                                    <tr>
                                        <th>Career Path</th>
                                        <th>Stage Order</th>
                                        <th>Job Role</th>
                                        <th>Min Time (Months)</th>
                                        <th>Required Skills</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($careerStages)): ?>
                                    <tr>
                                        <td colspan="6" class="no-results">
                                            <i class="fas fa-route"></i>
                                            <h3>No career stages found</h3>
                                            <p>Start by adding your first career stage.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($careerStages as $stage): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($stage['path_name']); ?></strong></td>
                                        <td><span class="status-badge status-active">Stage <?php echo $stage['stage_order']; ?></span></td>
                                        <td><?php echo htmlspecialchars($stage['job_role_title']); ?></td>
                                        <td><?php echo $stage['minimum_time_in_role']; ?> months</td>
                                        <td><?php echo htmlspecialchars(substr($stage['required_skills'], 0, 30)) . (strlen($stage['required_skills']) > 30 ? '...' : ''); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editCareerStage(<?php echo $stage['stage_id']; ?>, '<?php echo $stage['path_id']; ?>', '<?php echo $stage['job_role_id']; ?>', '<?php echo $stage['stage_order']; ?>', '<?php echo $stage['minimum_time_in_role']; ?>', '<?php echo addslashes($stage['required_skills']); ?>', '<?php echo addslashes($stage['required_experience']); ?>')">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteCareerStage(<?php echo $stage['stage_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Employee Assignments Tab -->
                    <div id="assignments" class="tab-pane">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="assignmentSearch" placeholder="Search assignments..." onkeyup="searchAssignments()">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('employeeAssignment')">
                                ➕ Assign Employee
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="assignmentsTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Career Path</th>
                                        <th>Current Stage</th>
                                        <th>Current Role</th>
                                        <th>Start Date</th>
                                        <th>Target Completion</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($employeePaths)): ?>
                                    <tr>
                                        <td colspan="8" class="no-results">
                                            <i class="fas fa-user-check"></i>
                                            <h3>No employee assignments found</h3>
                                            <p>Start by assigning an employee to a career path.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($employeePaths as $assignment): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($assignment['path_name']); ?></td>
                                        <td><span class="status-badge status-active">Stage <?php echo $assignment['stage_order']; ?></span></td>
                                        <td><?php echo htmlspecialchars($assignment['current_role']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($assignment['start_date'])); ?></td>
                                        <td><?php echo $assignment['target_completion_date'] ? date('M d, Y', strtotime($assignment['target_completion_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($assignment['status']); ?>">
                                                <?php echo htmlspecialchars($assignment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editAssignment(<?php echo $assignment['employee_path_id']; ?>, '<?php echo $assignment['employee_id']; ?>', '<?php echo $assignment['path_id']; ?>', '<?php echo $assignment['current_stage_id']; ?>', '<?php echo $assignment['start_date']; ?>', '<?php echo $assignment['target_completion_date']; ?>', '<?php echo $assignment['status']; ?>')">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteAssignment(<?php echo $assignment['employee_path_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Career Path Modal -->
    <div id="careerPathModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="careerPathModalTitle">Add New Career Path</h2>
                <span class="close" onclick="closeModal('careerPath')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="careerPathForm" method="POST">
                    <input type="hidden" id="careerPath_action" name="action" value="add_career_path">
                    <input type="hidden" id="careerPath_id" name="path_id">

                    <div class="form-group">
                        <label for="path_name">Path Name *</label>
                        <input type="text" id="path_name" name="path_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id" class="form-control">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Description of the career path"></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('careerPath')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Career Path</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Career Stage Modal -->
    <div id="careerStageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="careerStageModalTitle">Add New Career Stage</h2>
                <span class="close" onclick="closeModal('careerStage')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="careerStageForm" method="POST">
                    <input type="hidden" id="careerStage_action" name="action" value="add_career_stage">
                    <input type="hidden" id="careerStage_id" name="stage_id">

                    <div class="form-group">
                        <label for="stage_path_id">Career Path *</label>
                        <select id="stage_path_id" name="path_id" class="form-control" required>
                            <option value="">Select Career Path</option>
                            <?php foreach ($careerPaths as $path): ?>
                            <option value="<?php echo $path['path_id']; ?>">
                                <?php echo htmlspecialchars($path['path_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="job_role_id">Job Role *</label>
                        <select id="job_role_id" name="job_role_id" class="form-control" required>
                            <option value="">Select Job Role</option>
                            <?php foreach ($jobRoles as $role): ?>
                            <option value="<?php echo $role['job_role_id']; ?>">
                                <?php echo htmlspecialchars($role['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="stage_order">Stage Order *</label>
                                <input type="number" id="stage_order" name="stage_order" class="form-control" min="1" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="minimum_time_in_role">Min Time in Role (Months) *</label>
                                <input type="number" id="minimum_time_in_role" name="minimum_time_in_role" class="form-control" min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="required_skills">Required Skills</label>
                        <textarea id="required_skills" name="required_skills" class="form-control" rows="2" placeholder="Skills required for this stage"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="required_experience">Required Experience</label>
                        <textarea id="required_experience" name="required_experience" class="form-control" rows="2" placeholder="Experience requirements"></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('careerStage')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Career Stage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Employee Assignment Modal -->
    <div id="employeeAssignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="employeeAssignmentModalTitle">Assign Employee to Career Path</h2>
                <span class="close" onclick="closeModal('employeeAssignment')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="employeeAssignmentForm" method="POST">
                    <input type="hidden" id="employeeAssignment_action" name="action" value="assign_employee_path">
                    <input type="hidden" id="employeeAssignment_id" name="employee_path_id">

                    <div class="form-group">
                        <label for="assignment_employee_id">Employee *</label>
                        <select id="assignment_employee_id" name="employee_id" class="form-control" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['employee_id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assignment_path_id">Career Path *</label>
                        <select id="assignment_path_id" name="path_id" class="form-control" required onchange="loadStages()">
                            <option value="">Select Career Path</option>
                            <?php foreach ($careerPaths as $path): ?>
                            <option value="<?php echo $path['path_id']; ?>">
                                <?php echo htmlspecialchars($path['path_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assignment_stage_id">Current Stage *</label>
                        <select id="assignment_stage_id" name="current_stage_id" class="form-control" required>
                            <option value="">Select Career Path first</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="target_completion_date">Target Completion Date</label>
                                <input type="date" id="target_completion_date" name="target_completion_date" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="assignment_status">Status *</label>
                        <select id="assignment_status" name="status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                            <option value="Abandoned">Abandoned</option>
                        </select>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('employeeAssignment')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab panes
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab pane
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Modal functions
        function openModal(type) {
            if (type === 'careerPath') {
                document.getElementById('careerPathModal').style.display = 'block';
                document.getElementById('careerPathModalTitle').textContent = 'Add New Career Path';
                document.getElementById('careerPath_action').value = 'add_career_path';
                document.getElementById('careerPathForm').reset();
            } else if (type === 'careerStage') {
                document.getElementById('careerStageModal').style.display = 'block';
                document.getElementById('careerStageModalTitle').textContent = 'Add New Career Stage';
                document.getElementById('careerStage_action').value = 'add_career_stage';
                document.getElementById('careerStageForm').reset();
            } else if (type === 'employeeAssignment') {
                document.getElementById('employeeAssignmentModal').style.display = 'block';
                document.getElementById('employeeAssignmentModalTitle').textContent = 'Assign Employee to Career Path';
                document.getElementById('employeeAssignment_action').value = 'assign_employee_path';
                document.getElementById('employeeAssignmentForm').reset();
            }
        }

        function closeModal(type) {
            if (type === 'careerPath') {
                document.getElementById('careerPathModal').style.display = 'none';
            } else if (type === 'careerStage') {
                document.getElementById('careerStageModal').style.display = 'none';
            } else if (type === 'employeeAssignment') {
                document.getElementById('employeeAssignmentModal').style.display = 'none';
            }
        }

        // Edit functions
        function editCareerPath(id, name, description, departmentId) {
            document.getElementById('careerPathModal').style.display = 'block';
            document.getElementById('careerPathModalTitle').textContent = 'Edit Career Path';
            document.getElementById('careerPath_action').value = 'update_career_path';
            document.getElementById('careerPath_id').value = id;
            document.getElementById('path_name').value = name;
            document.getElementById('description').value = description;
            document.getElementById('department_id').value = departmentId;
        }

        function editCareerStage(id, pathId, jobRoleId, stageOrder, minTime, skills, experience) {
            document.getElementById('careerStageModal').style.display = 'block';
            document.getElementById('careerStageModalTitle').textContent = 'Edit Career Stage';
            document.getElementById('careerStage_action').value = 'update_career_stage';
            document.getElementById('careerStage_id').value = id;
            document.getElementById('stage_path_id').value = pathId;
            document.getElementById('job_role_id').value = jobRoleId;
            document.getElementById('stage_order').value = stageOrder;
            document.getElementById('minimum_time_in_role').value = minTime;
            document.getElementById('required_skills').value = skills;
            document.getElementById('required_experience').value = experience;
        }

        function editAssignment(id, employeeId, pathId, stageId, startDate, targetDate, status) {
            document.getElementById('employeeAssignmentModal').style.display = 'block';
            document.getElementById('employeeAssignmentModalTitle').textContent = 'Edit Employee Assignment';
            document.getElementById('employeeAssignment_action').value = 'update_assignment';
            document.getElementById('employeeAssignment_id').value = id;
            document.getElementById('assignment_employee_id').value = employeeId;
            document.getElementById('assignment_path_id').value = pathId;
            document.getElementById('assignment_stage_id').value = stageId;
            document.getElementById('start_date').value = startDate;
            document.getElementById('target_completion_date').value = targetDate;
            document.getElementById('assignment_status').value = status;
        }

        // Delete functions
        function deleteCareerPath(id) {
            if (confirm('Are you sure you want to delete this career path?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_career_path"><input type="hidden" name="path_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteCareerStage(id) {
            if (confirm('Are you sure you want to delete this career stage?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_career_stage"><input type="hidden" name="stage_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteAssignment(id) {
            if (confirm('Are you sure you want to delete this assignment?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_assignment"><input type="hidden" name="employee_path_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Search functionality
        function searchPaths() {
            var input = document.getElementById('pathSearch');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('pathsTable');
            var tr = table.getElementsByTagName('tr');

            for (var i = 1; i < tr.length; i++) {
                var td = tr[i].getElementsByTagName('td');
                var found = false;
                for (var j = 0; j < td.length; j++) {
                    if (td[j]) {
                        var txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        function searchStages() {
            var input = document.getElementById('stageSearch');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('stagesTable');
            var tr = table.getElementsByTagName('tr');

            for (var i = 1; i < tr.length; i++) {
                var td = tr[i].getElementsByTagName('td');
                var found = false;
                for (var j = 0; j < td.length; j++) {
                    if (td[j]) {
                        var txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        function searchAssignments() {
            var input = document.getElementById('assignmentSearch');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('assignmentsTable');
            var tr = table.getElementsByTagName('tr');

            for (var i = 1; i < tr.length; i++) {
                var td = tr[i].getElementsByTagName('td');
                var found = false;
                for (var j = 0; j < td.length; j++) {
                    if (td[j]) {
                        var txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        // Load stages based on career path selection
        function loadStages() {
            var pathId = document.getElementById('assignment_path_id').value;
            var stageSelect = document.getElementById('assignment_stage_id');
            
            stageSelect.innerHTML = '<option value="">Loading stages...</option>';
            
            if (pathId) {
                // Filter stages for the selected path
                var stages = <?php echo json_encode($careerStages); ?>;
                var filteredStages = stages.filter(function(stage) {
                    return stage.path_id == pathId;
                });
                
                stageSelect.innerHTML = '<option value="">Select Stage</option>';
                filteredStages.forEach(function(stage) {
                    stageSelect.innerHTML += '<option value="' + stage.stage_id + '">Stage ' + stage.stage_order + ' - ' + stage.job_role_title + '</option>';
                });
            } else {
                stageSelect.innerHTML = '<option value="">Select Career Path first</option>';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modals = ['careerPathModal', 'careerStageModal', 'employeeAssignmentModal'];
            modals.forEach(function(modalId) {
                var modal = document.getElementById(modalId);
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Show message if there's one
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            alert('<?php echo addslashes($message); ?>');
        });
        <?php endif; ?>
    </script>
</body>
</html>

}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'config.php';

// Use the global database connection
$pdo = $conn;

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_career_path':
                try {
                    $stmt = $pdo->prepare("INSERT INTO career_paths (path_name, description, department_id) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $_POST['path_name'],
                        $_POST['description'],
                        $_POST['department_id']
                    ]);
                    $message = "Career path added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding career path: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'add_career_stage':
                try {
                    $stmt = $pdo->prepare("INSERT INTO career_path_stages (path_id, job_role_id, stage_order, minimum_time_in_role, required_skills, required_experience) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['path_id'],
                        $_POST['job_role_id'],
                        $_POST['stage_order'],
                        $_POST['minimum_time_in_role'],
                        $_POST['required_skills'],
                        $_POST['required_experience']
                    ]);
                    $message = "Career stage added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding career stage: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'assign_employee_path':
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_career_paths (employee_id, path_id, current_stage_id, start_date, target_completion_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['path_id'],
                        $_POST['current_stage_id'],
                        $_POST['start_date'],
                        $_POST['target_completion_date'],
                        $_POST['status']
                    ]);
                    $message = "Employee assigned to career path successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error assigning employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_career_path':
                try {
                    $stmt = $pdo->prepare("DELETE FROM career_paths WHERE path_id=?");
                    $stmt->execute([$_POST['path_id']]);
                    $message = "Career path deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting career path: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch career paths
try {
    $stmt = $pdo->query("
        SELECT cp.*, d.department_name 
        FROM career_paths cp 
        LEFT JOIN departments d ON cp.department_id = d.department_id 
        ORDER BY cp.path_name
    ");
    $careerPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $careerPaths = [];
    $message = "Error fetching career paths: " . $e->getMessage();
    $messageType = "error";
}

// Fetch career path stages
try {
    $stmt = $pdo->query("
        SELECT cps.*, cp.path_name, jr.title as job_role_title 
        FROM career_path_stages cps 
        JOIN career_paths cp ON cps.path_id = cp.path_id 
        JOIN job_roles jr ON cps.job_role_id = jr.job_role_id 
        ORDER BY cp.path_name, cps.stage_order
    ");
    $careerStages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $careerStages = [];
}

// Fetch employee career paths
try {
    $stmt = $pdo->query("
        SELECT ecp.*, e.first_name, e.last_name, cp.path_name, cps.stage_order, jr.title as current_role
        FROM employee_career_paths ecp 
        JOIN employee_profiles ep ON ecp.employee_id = ep.employee_id 
        JOIN personal_information e ON ep.personal_info_id = e.personal_info_id 
        JOIN career_paths cp ON ecp.path_id = cp.path_id 
        JOIN career_path_stages cps ON ecp.current_stage_id = cps.stage_id 
        JOIN job_roles jr ON cps.job_role_id = jr.job_role_id 
        ORDER BY e.last_name, cp.path_name
    ");
    $employeePaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employeePaths = [];
}

// Fetch departments for dropdowns
try {
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// Fetch job roles for dropdowns
try {
    $stmt = $pdo->query("SELECT * FROM job_roles ORDER BY title");
    $jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $jobRoles = [];
}

// Fetch employees for dropdowns
try {
    $stmt = $pdo->query("
        SELECT ep.employee_id, pi.first_name, pi.last_name 
        FROM employee_profiles ep 
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id 
        ORDER BY pi.last_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM career_paths");
    $totalPaths = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM career_path_stages");
    $totalStages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_career_paths WHERE status = 'Active'");
    $activeAssignments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_career_paths WHERE status = 'Completed'");
    $completedPaths = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $totalPaths = 0;
    $totalStages = 0;
    $activeAssignments = 0;
    $completedPaths = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Development Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Career Development Management</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-road"></i>
                            <h3><?php echo $totalPaths; ?></h3>
                            <h6>Career Paths</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-route"></i>
                            <h3><?php echo $totalStages; ?></h3>
                            <h6>Career Stages</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-user-check"></i>
                            <h3><?php echo $activeAssignments; ?></h3>
                            <h6>Active Assignments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-trophy"></i>
                            <h3><?php echo $completedPaths; ?></h3>
                            <h6>Completed Paths</h6>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="careerTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="paths-tab" data-toggle="tab" href="#paths" role="tab">
                            <i class="fas fa-road"></i> Career Paths
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="stages-tab" data-toggle="tab" href="#stages" role="tab">
                            <i class="fas fa-route"></i> Career Stages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="assignments-tab" data-toggle="tab" href="#assignments" role="tab">
                            <i class="fas fa-user-check"></i> Employee Assignments
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="careerTabsContent">
                    <!-- Career Paths Tab -->
                    <div class="tab-pane fade show active" id="paths" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="pathSearch" placeholder="Search career paths...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addCareerPathModal">
                                <i class="fas fa-plus"></i> Add Career Path
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-road"></i> Career Paths</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Path Name</th>
                                                <th>Department</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($careerPaths as $path): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($path['path_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($path['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars(substr($path['description'], 0, 50)) . (strlen($path['description']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editCareerPath(<?php echo $path['path_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCareerPath(<?php echo $path['path_id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Career Stages Tab -->
                    <div class="tab-pane fade" id="stages" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="stageSearch" placeholder="Search career stages...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addCareerStageModal">
                                <i class="fas fa-plus"></i> Add Career Stage
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-route"></i> Career Path Stages</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Career Path</th>
                                                <th>Stage Order</th>
                                                <th>Job Role</th>
                                                <th>Min Time (Months)</th>
                                                <th>Required Skills</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($careerStages as $stage): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($stage['path_name']); ?></strong></td>
                                                <td><span class="stage-badge">Stage <?php echo $stage['stage_order']; ?></span></td>
                                                <td><?php echo htmlspecialchars($stage['job_role_title']); ?></td>
                                                <td><?php echo $stage['minimum_time_in_role']; ?> months</td>
                                                <td><?php echo htmlspecialchars(substr($stage['required_skills'], 0, 30)) . (strlen($stage['required_skills']) > 30 ? '...' : ''); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editCareerStage(<?php echo $stage['stage_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Assignments Tab -->
                    <div class="tab-pane fade" id="assignments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="assignmentSearch" placeholder="Search assignments...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#assignEmployeeModal">
                                <i class="fas fa-plus"></i> Assign Employee
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user-check"></i> Employee Career Path Assignments</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Career Path</th>
                                                <th>Current Stage</th>
                                                <th>Current Role</th>
                                                <th>Start Date</th>
                                                <th>Target Completion</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($employeePaths as $assignment): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($assignment['path_name']); ?></td>
                                                <td><span class="stage-badge">Stage <?php echo $assignment['stage_order']; ?></span></td>
                                                <td><?php echo htmlspecialchars($assignment['current_role']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($assignment['start_date'])); ?></td>
                                                <td><?php echo $assignment['target_completion_date'] ? date('M d, Y', strtotime($assignment['target_completion_date'])) : 'N/A'; ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($assignment['status']); ?>">
                                                        <?php echo htmlspecialchars($assignment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?php echo $assignment['employee_path_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Career Path Modal -->
    <div class="modal fade" id="addCareerPathModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Career Path</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_career_path">
                        <div class="form-group">
                            <label>Path Name *</label>
                            <input type="text" class="form-control" name="path_name" required>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Description of the career path"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Career Path</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Career Stage Modal -->
    <div class="modal fade" id="addCareerStageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Career Stage</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_career_stage">
                        <div class="form-group">
                            <label>Career Path *</label>
                            <select class="form-control" name="path_id" required>
                                <option value="">Select Career Path</option>
                                <?php foreach ($careerPaths as $path): ?>
                                <option value="<?php echo $path['path_id']; ?>">
                                    <?php echo htmlspecialchars($path['path_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Job Role *</label>
                            <select class="form-control" name="job_role_id" required>
                                <option value="">Select Job Role</option>
                                <?php foreach ($jobRoles as $role): ?>
                                <option value="<?php echo $role['job_role_id']; ?>">
                                    <?php echo htmlspecialchars($role['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Stage Order *</label>
                                    <input type="number" class="form-control" name="stage_order" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Min Time in Role (Months) *</label>
                                    <input type="number" class="form-control" name="minimum_time_in_role" min="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Required Skills</label>
                            <textarea class="form-control" name="required_skills" rows="2" placeholder="Skills required for this stage"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Required Experience</label>
                            <textarea class="form-control" name="required_experience" rows="2" placeholder="Experience requirements"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Employee Modal -->
    <div class="modal fade" id="assignEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Assign Employee to Career Path</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_employee_path">
                        <div class="form-group">
                            <label>Employee *</label>
                            <select class="form-control" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['employee_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Career Path *</label>
                            <select class="form-control" name="path_id" id="pathSelect" required>
                                <option value="">Select Career Path</option>
                                <?php foreach ($careerPaths as $path): ?>
                                <option value="<?php echo $path['path_id']; ?>">
                                    <?php echo htmlspecialchars($path['path_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Current Stage *</label>
                            <select class="form-control" name="current_stage_id" id="stageSelect" required>
                                <option value="">Select Career Path first</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Target Completion Date</label>
                                    <input type="date" class="form-control" name="target_completion_date">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status *</label>
                            <select class="form-control" name="status" required>
                                <option value="Active">Active</option>
                                <option value="On Hold">On Hold</option>
                                <option value="Completed">Completed</option>
                                <option value="Abandoned">Abandoned</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success/Error Message Modal -->
    <?php if ($message): ?>
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header <?php echo $messageType === 'success' ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <h5 class="modal-title">
                        <?php echo $messageType === 'success' ? '<i class="fas fa-check-circle"></i> Success' : '<i class="fas fa-exclamation-circle"></i> Error'; ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Show message modal if there's a message
        <?php if ($message): ?>
        $(document).ready(function() {
            $('#messageModal').modal('show');
        });
        <?php endif; ?>

        // Search functionality
        $('#pathSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#paths table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        $('#stageSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#stages table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        $('#assignmentSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#assignments table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Dynamic stage loading based on career path selection
        $('#pathSelect').change(function() {
            var pathId = $(this).val();
            var stageSelect = $('#stageSelect');
            
            stageSelect.html('<option value="">Loading stages...</option>');
            
            if (pathId) {
                // Filter stages for the selected path
                var stages = <?php echo json_encode($careerStages); ?>;
                var filteredStages = stages.filter(function(stage) {
                    return stage.path_id == pathId;
                });
                
                stageSelect.html('<option value="">Select Stage</option>');
                filteredStages.forEach(function(stage) {
                    stageSelect.append('<option value="' + stage.stage_id + '">Stage ' + stage.stage_order + ' - ' + stage.job_role_title + '</option>');
                });
            } else {
                stageSelect.html('<option value="">Select Career Path first</option>');
            }
        });

        // Edit functions
        function editCareerPath(pathId) {
            alert('Edit career path with ID: ' + pathId);
        }

        function editCareerStage(stageId) {
            alert('Edit career stage with ID: ' + stageId);
        }

        function editAssignment(assignmentId) {
            alert('Edit assignment with ID: ' + assignmentId);
        }

        // Delete career path function
        function deleteCareerPath(pathId) {
            if (confirm('Are you sure you want to delete this career path?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_career_path">
                    <input type="hidden" name="path_id" value="${pathId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

</html>
