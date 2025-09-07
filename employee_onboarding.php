<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';

$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'complete_onboarding':
                $employee_id = $_POST['employee_id'];
                
                $stmt = $conn->prepare("UPDATE employees SET updated_at = NOW() WHERE employee_id = ?");
                $stmt->execute([$employee_id]);
                
                $success_message = "✅ Employee record updated!";
                break;
                
            case 'promote_employee':
                $employee_id = $_POST['employee_id'];
                $new_position = $_POST['new_position'];
                
                $stmt = $conn->prepare("UPDATE employees SET current_position = ?, updated_at = NOW() WHERE employee_id = ?");
                $stmt->execute([$new_position, $employee_id]);
                
                $success_message = "🎉 Employee position updated!";
                break;
        }
    }
}

// Get active employees
$hired_employees = $conn->query("SELECT * FROM employees WHERE status = 'Active' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get recent employees
$completed_employees = $conn->query("SELECT * FROM employees WHERE status = 'Active' ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'pending' => count($hired_employees),
    'completed' => $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'Active'")->fetch()['count']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Onboarding - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2>👥 Employee Onboarding</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['pending']; ?></h3>
                                <p class="stats-label">Pending Onboarding</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['completed']; ?></h3>
                                <p class="stats-label">Completed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Onboarding -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-user-plus"></i> Employees Pending Onboarding</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($hired_employees) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Position</th>
                                            <th>Department</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($hired_employees as $employee): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($employee['current_position']); ?></td>
                                                <td>-</td>
                                                <td><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                                                <td>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="complete_onboarding">
                                                        <input type="hidden" name="employee_id" value="<?php echo $employee['employee_id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Complete
                                                        </button>
                                                    </form>
                                                    <button class="btn btn-info btn-sm ml-1" data-toggle="modal" data-target="#promoteModal<?php echo $employee['employee_id']; ?>">
                                                        <i class="fas fa-arrow-up"></i> Promote
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> No employees pending onboarding.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recently Completed -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recently Completed Onboarding</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($completed_employees) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Position</th>
                                            <th>Department</th>
                                            <th>Completed Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($completed_employees as $employee): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['department_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($employee['onboarding_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> No completed onboarding records.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Promotion Modals -->
                <?php foreach($hired_employees as $employee): ?>
                    <div class="modal fade" id="promoteModal<?php echo $employee['employee_id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Promote Employee</h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="promote_employee">
                                        <input type="hidden" name="employee_id" value="<?php echo $employee['employee_id']; ?>">
                                        
                                        <div class="alert alert-info">
                                            <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong><br>
                                            Current: <?php echo htmlspecialchars($employee['current_position']); ?>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>New Position</label>
                                            <input type="text" name="new_position" class="form-control" required>
                                        </div>

                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">Promote</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>