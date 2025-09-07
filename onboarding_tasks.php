<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_task':
                $task_name = $_POST['task_name'];
                $description = $_POST['description'];
                $department_id = $_POST['department_id'] ?: null;
                $task_type = $_POST['task_type'];
                $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
                $default_due_days = $_POST['default_due_days'];
                
                $stmt = $conn->prepare("INSERT INTO onboarding_tasks (task_name, description, department_id, task_type, is_mandatory, default_due_days) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$task_name, $description, $department_id, $task_type, $is_mandatory, $default_due_days]);
                break;
                
            case 'update_task':
                $task_id = $_POST['task_id'];
                $task_name = $_POST['task_name'];
                $description = $_POST['description'];
                $department_id = $_POST['department_id'] ?: null;
                $task_type = $_POST['task_type'];
                $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
                $default_due_days = $_POST['default_due_days'];
                
                $stmt = $conn->prepare("UPDATE onboarding_tasks SET task_name = ?, description = ?, department_id = ?, task_type = ?, is_mandatory = ?, default_due_days = ? WHERE task_id = ?");
                $stmt->execute([$task_name, $description, $department_id, $task_type, $is_mandatory, $default_due_days, $task_id]);
                break;
                
            case 'delete_task':
                $task_id = $_POST['task_id'];
                $stmt = $conn->prepare("DELETE FROM onboarding_tasks WHERE task_id = ?");
                $stmt->execute([$task_id]);
                break;
        }
        header('Location: onboarding_tasks_modern.php');
        exit;
    }
}

// Get onboarding tasks
try {
    $tasks_query = "SELECT ot.*, d.department_name,
                    COUNT(eot.employee_task_id) as usage_count
                    FROM onboarding_tasks ot
                    LEFT JOIN departments d ON ot.department_id = d.department_id
                    LEFT JOIN employee_onboarding_tasks eot ON ot.task_id = eot.task_id
                    GROUP BY ot.task_id
                    ORDER BY ot.task_type, ot.task_name";
    
    $tasks = $conn->query($tasks_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tasks = [];
}

// Get departments
try {
    $departments = $conn->query("SELECT * FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}

// Get statistics
$stats = ['total_tasks' => 0, 'mandatory_tasks' => 0, 'by_type' => [], 'most_used' => 0];
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM onboarding_tasks");
    $stats['total_tasks'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt = $conn->query("SELECT COUNT(*) as count FROM onboarding_tasks WHERE is_mandatory = 1");
    $stats['mandatory_tasks'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $type_stats = $conn->query("SELECT task_type, COUNT(*) as count FROM onboarding_tasks GROUP BY task_type")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($type_stats as $type) {
        $stats['by_type'][$type['task_type']] = $type['count'];
    }
    
    $stmt = $conn->query("SELECT MAX(usage_count) as max_usage FROM (SELECT COUNT(eot.employee_task_id) as usage_count FROM onboarding_tasks ot LEFT JOIN employee_onboarding_tasks eot ON ot.task_id = eot.task_id GROUP BY ot.task_id) as usage_stats");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['most_used'] = $result['max_usage'] ?? 0;
} catch (Exception $e) {
    // Keep default values
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding Tasks - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .task-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(233, 30, 99, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.15);
        }
        
        .task-type-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .type-administrative { background: #007bff; color: white; }
        .type-equipment { background: #6f42c1; color: white; }
        .type-training { background: #28a745; color: white; }
        .type-introduction { background: #fd7e14; color: white; }
        .type-documentation { background: #20c997; color: white; }
        .type-other { background: #6c757d; color: white; }
        
        .mandatory-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-clipboard-list text-rose"></i> Onboarding Tasks Management</h2>
                    <button class="btn btn-rose" data-toggle="modal" data-target="#createTaskModal">
                        <i class="fas fa-plus"></i> Create Task
                    </button>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['total_tasks']; ?></h3>
                                <p>Total Tasks</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['mandatory_tasks']; ?></h3>
                                <p>Mandatory Tasks</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3><?php echo count($stats['by_type']); ?></h3>
                                <p>Task Categories</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['most_used']; ?></h3>
                                <p>Most Used Task</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks List -->
                <div class="row">
                    <?php foreach ($tasks as $task): ?>
                    <div class="col-md-6">
                        <div class="task-card">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($task['task_name']); ?></h6>
                                        <div>
                                            <span class="task-type-badge type-<?php echo strtolower($task['task_type']); ?>">
                                                <?php echo $task['task_type']; ?>
                                            </span>
                                            <?php if ($task['is_mandatory']): ?>
                                            <span class="mandatory-badge ml-1">Mandatory</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <small class="text-muted">Used <?php echo $task['usage_count']; ?> times</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($task['description']): ?>
                                <p class="mb-2"><?php echo htmlspecialchars($task['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Department:</strong><br>
                                        <small><?php echo $task['department_name'] ?: 'All Departments'; ?></small>
                                    </div>
                                    <div class="col-6">
                                        <strong>Default Due:</strong><br>
                                        <small><?php echo $task['default_due_days']; ?> days</small>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-rose" data-toggle="modal" data-target="#editTaskModal<?php echo $task['task_id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this task?')">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Task Modal -->
                    <div class="modal fade" id="editTaskModal<?php echo $task['task_id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-rose text-white">
                                    <h5 class="modal-title">Edit Onboarding Task</h5>
                                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="update_task">
                                        <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                        
                                        <div class="form-group">
                                            <label>Task Name</label>
                                            <input type="text" name="task_name" class="form-control" value="<?php echo htmlspecialchars($task['task_name']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Description</label>
                                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($task['description']); ?></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Department</label>
                                                    <select name="department_id" class="form-control">
                                                        <option value="">All Departments</option>
                                                        <?php foreach ($departments as $dept): ?>
                                                        <option value="<?php echo $dept['department_id']; ?>" <?php echo $task['department_id'] == $dept['department_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Task Type</label>
                                                    <select name="task_type" class="form-control" required>
                                                        <option value="Administrative" <?php echo $task['task_type'] === 'Administrative' ? 'selected' : ''; ?>>Administrative</option>
                                                        <option value="Equipment" <?php echo $task['task_type'] === 'Equipment' ? 'selected' : ''; ?>>Equipment</option>
                                                        <option value="Training" <?php echo $task['task_type'] === 'Training' ? 'selected' : ''; ?>>Training</option>
                                                        <option value="Introduction" <?php echo $task['task_type'] === 'Introduction' ? 'selected' : ''; ?>>Introduction</option>
                                                        <option value="Documentation" <?php echo $task['task_type'] === 'Documentation' ? 'selected' : ''; ?>>Documentation</option>
                                                        <option value="Other" <?php echo $task['task_type'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Default Due Days</label>
                                                    <input type="number" name="default_due_days" class="form-control" value="<?php echo $task['default_due_days']; ?>" min="1" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" name="is_mandatory" id="mandatory<?php echo $task['task_id']; ?>" <?php echo $task['is_mandatory'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="mandatory<?php echo $task['task_id']; ?>">
                                                            Mandatory Task
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-rose">Update Task</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-rose text-white">
                    <h5 class="modal-title">Create Onboarding Task</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_task">
                        
                        <div class="form-group">
                            <label>Task Name</label>
                            <input type="text" name="task_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Department</label>
                                    <select name="department_id" class="form-control">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>">
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Task Type</label>
                                    <select name="task_type" class="form-control" required>
                                        <option value="Administrative">Administrative</option>
                                        <option value="Equipment">Equipment</option>
                                        <option value="Training">Training</option>
                                        <option value="Introduction">Introduction</option>
                                        <option value="Documentation">Documentation</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Default Due Days</label>
                                    <input type="number" name="default_due_days" class="form-control" value="7" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="is_mandatory" id="mandatoryNew">
                                        <label class="form-check-label" for="mandatoryNew">
                                            Mandatory Task
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-rose">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>