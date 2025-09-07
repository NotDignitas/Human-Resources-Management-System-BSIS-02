<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
            case 'add_task':
                $task_name = $_POST['task_name'];
                $description = $_POST['description'];
                
                $order_stmt = $conn->prepare("SELECT MAX(task_order) as max_order FROM onboarding_tasks");
                $order_stmt->execute();
                $max_order = $order_stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;
                
                $stmt = $conn->prepare("INSERT INTO onboarding_tasks (task_name, description, task_order) VALUES (?, ?, ?)");
                $stmt->execute([$task_name, $description, $max_order + 1]);
                
                $success_message = "✅ Task added successfully!";
                break;
                
            case 'edit_task':
                $task_id = $_POST['task_id'];
                $task_name = $_POST['task_name'];
                $description = $_POST['description'];
                
                $stmt = $conn->prepare("UPDATE onboarding_tasks SET task_name = ?, description = ? WHERE task_id = ?");
                $stmt->execute([$task_name, $description, $task_id]);
                
                $success_message = "✅ Task updated successfully!";
                break;
                
            case 'delete_task':
                $task_id = $_POST['task_id'];
                
                $stmt = $conn->prepare("DELETE FROM onboarding_tasks WHERE task_id = ?");
                $stmt->execute([$task_id]);
                
                $success_message = "✅ Task deleted successfully!";
                break;
                
            case 'complete_task':
                $application_id = $_POST['application_id'];
                $task_id = $_POST['task_id'];
                
                $stmt = $conn->prepare("UPDATE onboarding_progress SET status = 'Completed', completed_date = NOW() WHERE application_id = ? AND task_id = ?");
                $stmt->execute([$application_id, $task_id]);
                
                // Check if all tasks completed
                $remaining = $conn->prepare("SELECT COUNT(*) as count FROM onboarding_progress WHERE application_id = ? AND status = 'Pending'");
                $remaining->execute([$application_id]);
                $remaining_count = $remaining->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($remaining_count == 0) {
                    $app_stmt = $conn->prepare("SELECT candidate_id FROM job_applications WHERE application_id = ?");
                    $app_stmt->execute([$application_id]);
                    $app_data = $app_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $conn->prepare("UPDATE job_applications SET status = 'Hired' WHERE application_id = ?");
                    $stmt->execute([$application_id]);
                    
                    $stmt = $conn->prepare("UPDATE candidates SET source = 'Hired' WHERE candidate_id = ?");
                    $stmt->execute([$app_data['candidate_id']]);
                    
                    $success_message = "🎉 All tasks completed! Candidate hired successfully.";
                } else {
                    $success_message = "✅ Task completed!";
                }
                break;
        }
    }
}

// Create onboarding tasks for new candidates
$conn->exec("INSERT INTO onboarding_progress (application_id, task_id, status)
             SELECT ja.application_id, ot.task_id, 'Pending'
             FROM job_applications ja
             CROSS JOIN onboarding_tasks ot
             WHERE ja.status = 'Reference Check'
             AND NOT EXISTS (SELECT 1 FROM onboarding_progress op WHERE op.application_id = ja.application_id AND op.task_id = ot.task_id)");

// Get onboarding tasks
$onboarding_tasks = $conn->query("SELECT * FROM onboarding_tasks ORDER BY task_order")->fetchAll(PDO::FETCH_ASSOC);

// Get candidates in onboarding with task progress
$onboarding_candidates = $conn->query("SELECT c.*, ja.application_id, ja.application_date, ja.status, 
                                      jo.title as job_title, d.department_name,
                                      COUNT(op.task_id) as total_tasks,
                                      COUNT(CASE WHEN op.status = 'Completed' THEN 1 END) as completed_tasks
                                      FROM candidates c 
                                      JOIN job_applications ja ON c.candidate_id = ja.candidate_id
                                      JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                                      JOIN departments d ON jo.department_id = d.department_id
                                      LEFT JOIN onboarding_progress op ON ja.application_id = op.application_id
                                      WHERE ja.status = 'Reference Check'
                                      GROUP BY c.candidate_id, ja.application_id
                                      ORDER BY ja.application_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total' => count($onboarding_candidates),
    'tasks' => count($onboarding_tasks)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding Management - HR Management System</title>
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
                <h2>🎯 Onboarding Management</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-primary">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['total']; ?></h3>
                                <p class="stats-label">In Onboarding</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['tasks']; ?></h3>
                                <p class="stats-label">Total Tasks</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onboarding Tasks Management -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-tasks"></i> Onboarding Tasks</h5>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addTaskModal">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (count($onboarding_tasks) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Task Name</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($onboarding_tasks as $task): ?>
                                            <tr>
                                                <td><?php echo $task['task_order']; ?></td>
                                                <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                                                <td><?php echo htmlspecialchars($task['description']); ?></td>
                                                <td>
                                                    <button class="btn btn-info btn-sm" onclick="editTask(<?php echo $task['task_id']; ?>, '<?php echo htmlspecialchars($task['task_name']); ?>', '<?php echo htmlspecialchars($task['description']); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display:inline;" class="ml-1">
                                                        <input type="hidden" name="action" value="delete_task">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this task?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> No onboarding tasks defined. Add tasks to get started.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Candidates Progress -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-users"></i> Candidates Onboarding Progress</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($onboarding_candidates) > 0): ?>
                            <?php foreach($onboarding_candidates as $candidate): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></strong>
                                                <small class="text-muted"> - <?php echo htmlspecialchars($candidate['job_title']); ?></small>
                                            </div>
                                            <div>
                                                <span class="badge badge-info"><?php echo $candidate['completed_tasks']; ?>/<?php echo $candidate['total_tasks']; ?> Tasks</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $progress_query = $conn->prepare("SELECT op.*, ot.task_name, ot.description FROM onboarding_progress op JOIN onboarding_tasks ot ON op.task_id = ot.task_id WHERE op.application_id = ? ORDER BY ot.task_order");
                                        $progress_query->execute([$candidate['application_id']]);
                                        $tasks_progress = $progress_query->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        <div class="row">
                                            <?php foreach($tasks_progress as $task_progress): ?>
                                                <div class="col-md-4 mb-2">
                                                    <div class="card border-<?php echo $task_progress['status'] == 'Completed' ? 'success' : 'warning'; ?>">
                                                        <div class="card-body p-2">
                                                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($task_progress['task_name']); ?></h6>
                                                            <p class="card-text small"><?php echo htmlspecialchars($task_progress['description']); ?></p>
                                                            <?php if ($task_progress['status'] == 'Pending'): ?>
                                                                <form method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="complete_task">
                                                                    <input type="hidden" name="application_id" value="<?php echo $candidate['application_id']; ?>">
                                                                    <input type="hidden" name="task_id" value="<?php echo $task_progress['task_id']; ?>">
                                                                    <button type="submit" class="btn btn-success btn-sm">
                                                                        <i class="fas fa-check"></i> Complete
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="badge badge-success">✅ Completed</span>
                                                                <br><small class="text-muted"><?php echo date('M d, Y', strtotime($task_progress['completed_date'])); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <h5><i class="fas fa-info-circle"></i> No Candidates in Onboarding</h5>
                                <p>No candidates are currently in the onboarding process.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Add Task Modal -->
                <div class="modal fade" id="addTaskModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Onboarding Task</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="add_task">
                                    <div class="form-group">
                                        <label>Task Name</label>
                                        <input type="text" name="task_name" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Task</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Task Modal -->
                <div class="modal fade" id="editTaskModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Onboarding Task</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="edit_task">
                                    <input type="hidden" name="task_id" id="editTaskId">
                                    <div class="form-group">
                                        <label>Task Name</label>
                                        <input type="text" name="task_name" id="editTaskName" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description" id="editTaskDescription" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Task</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        function editTask(taskId, taskName, description) {
            document.getElementById('editTaskId').value = taskId;
            document.getElementById('editTaskName').value = taskName;
            document.getElementById('editTaskDescription').value = description;
            $('#editTaskModal').modal('show');
        }
    </script>
</body>
</html>