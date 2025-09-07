<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';
require_once 'email_config.php';

$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_application':
                $application_id = $_POST['application_id'];
                
                // Get application details
                $app_stmt = $conn->prepare("SELECT candidate_id FROM job_applications WHERE application_id = ?");
                $app_stmt->execute([$application_id]);
                $app_data = $app_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update application status to Screening (next stage after Applied)
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Screening' WHERE application_id = ?");
                $stmt->execute([$application_id]);
                
                // Get job opening ID and first interview stage
                $job_stmt = $conn->prepare("SELECT job_opening_id FROM job_applications WHERE application_id = ?");
                $job_stmt->execute([$application_id]);
                $job_data = $job_stmt->fetch(PDO::FETCH_ASSOC);
                
                $first_stage_stmt = $conn->prepare("SELECT stage_id, stage_name FROM interview_stages WHERE job_opening_id = ? ORDER BY stage_order LIMIT 1");
                $first_stage_stmt->execute([$job_data['job_opening_id']]);
                $first_stage = $first_stage_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($first_stage) {
                    // Move to Interview status and first interview stage
                    $stmt = $conn->prepare("UPDATE job_applications SET status = 'Interview' WHERE application_id = ?");
                    $stmt->execute([$application_id]);
                    
                    // Update candidate source to first interview stage
                    $stmt = $conn->prepare("UPDATE candidates SET source = ? WHERE candidate_id = ?");
                    $stmt->execute([$first_stage['stage_name'], $app_data['candidate_id']]);
                    
                    // Create first interview automatically
                    $stmt = $conn->prepare("INSERT INTO interviews (application_id, stage_id, schedule_date, duration, interview_type, status) VALUES (?, ?, NOW(), 60, 'Interview', 'Rescheduled')");
                    $stmt->execute([$application_id, $first_stage['stage_id']]);
                    
                    $success_message = "✅ Application approved and interview created automatically!";
                } else {
                    // No interview stages defined, just move to screening
                    $stmt = $conn->prepare("UPDATE candidates SET source = 'Approved' WHERE candidate_id = ?");
                    $stmt->execute([$app_data['candidate_id']]);
                    
                    $success_message = "✅ Application approved successfully!";
                }
                break;
                
            case 'reject_candidate':
                $application_id = $_POST['application_id'];
                
                // Get application details
                $app_stmt = $conn->prepare("SELECT candidate_id FROM job_applications WHERE application_id = ?");
                $app_stmt->execute([$application_id]);
                $app_data = $app_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Clear any existing interview history
                $stmt = $conn->prepare("DELETE FROM interviews WHERE application_id = ?");
                $stmt->execute([$application_id]);
                
                // Update application status to rejected
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Rejected' WHERE application_id = ?");
                $stmt->execute([$application_id]);
                
                // Remove from candidates dashboard by updating source
                $stmt = $conn->prepare("UPDATE candidates SET source = 'Rejected' WHERE candidate_id = ?");
                $stmt->execute([$app_data['candidate_id']]);
                
                $success_message = "❌ Application rejected and interview history cleared!";
                break;
                
            case 'reopen_application':
                $application_id = $_POST['application_id'];
                
                // Get application details
                $app_stmt = $conn->prepare("SELECT candidate_id FROM job_applications WHERE application_id = ?");
                $app_stmt->execute([$application_id]);
                $app_data = $app_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Clear any existing interview history
                $stmt = $conn->prepare("DELETE FROM interviews WHERE application_id = ?");
                $stmt->execute([$application_id]);
                
                // Update application status
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Applied' WHERE application_id = ?");
                $stmt->execute([$application_id]);
                
                // Reset candidate source back to original
                $stmt = $conn->prepare("UPDATE candidates SET source = 'Job Application' WHERE candidate_id = ?");
                $stmt->execute([$app_data['candidate_id']]);
                
                $success_message = "🔄 Application reopened with fresh start!";
                break;
        }
    }
}

$job_id = isset($_GET['job_id']) ? $_GET['job_id'] : null;

// Get job opening info if job_id is provided
$job_info = null;
if ($job_id) {
    $stmt = $conn->prepare("SELECT jo.*, d.department_name, jr.title as role_title FROM job_openings jo 
                           JOIN departments d ON jo.department_id = d.department_id 
                           JOIN job_roles jr ON jo.job_role_id = jr.job_role_id 
                           WHERE jo.job_opening_id = ?");
    $stmt->execute([$job_id]);
    $job_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Check if showing archived applications
$show_archived = isset($_GET['archived']) && $_GET['archived'] == '1';

// Get applications based on archived status
if ($show_archived) {
    // Show only Hired and Rejected applications (archived)
    $applications_query = "SELECT ja.*, c.first_name, c.last_name, c.email, c.phone, c.current_position, c.resume_filename,
                           jo.title as job_title, d.department_name
                           FROM job_applications ja 
                           JOIN candidates c ON ja.candidate_id = c.candidate_id 
                           JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                           JOIN departments d ON jo.department_id = d.department_id
                           WHERE ja.status IN ('Hired', 'Rejected')";
} else {
    // Show active applications
    $applications_query = "SELECT ja.*, c.first_name, c.last_name, c.email, c.phone, c.current_position, c.resume_filename,
                           jo.title as job_title, d.department_name
                           FROM job_applications ja 
                           JOIN candidates c ON ja.candidate_id = c.candidate_id 
                           JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                           JOIN departments d ON jo.department_id = d.department_id
                           WHERE ja.status IN ('Applied', 'Screening', 'Interview', 'Assessment', 'Reference Check')";
}

if ($job_id) {
    $applications_query .= " AND ja.job_opening_id = ?";
    $stmt = $conn->prepare($applications_query . " ORDER BY ja.application_date DESC");
    $stmt->execute([$job_id]);
} else {
    $stmt = $conn->query($applications_query . " ORDER BY ja.application_date DESC");
}
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Applications - HR Management System</title>
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
                <h2>📋 Job Applications Management</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if ($job_info): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($job_info['title']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($job_info['department_name']); ?> • <?php echo htmlspecialchars($job_info['role_title']); ?></small>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php
                    if ($show_archived) {
                        $stats = [
                            'Hired' => count(array_filter($applications, function($a) { return $a['status'] == 'Hired'; })),
                            'Declined' => count(array_filter($applications, function($a) { return $a['status'] == 'Declined'; })),
                            'Total' => count($applications)
                        ];
                    } else {
                        $stats = [
                            'Applied' => count(array_filter($applications, function($a) { return $a['status'] == 'Applied'; })),
                            'Approved' => count(array_filter($applications, function($a) { return $a['status'] == 'Screening'; })),
                            'Interview' => count(array_filter($applications, function($a) { return $a['status'] == 'Interview'; })),
                            'Assessment' => count(array_filter($applications, function($a) { return $a['status'] == 'Assessment'; })),
                            'Rejected' => count(array_filter($applications, function($a) { return $a['status'] == 'Rejected'; })),
                            'Total' => count($applications)
                        ];
                    }
                    ?>
                    <?php if ($show_archived): ?>
                        <div class="col-md-4">
                            <div class="stats-card card">
                                <div class="card-body text-center">
                                    <div class="activity-icon bg-success">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <h3 class="stats-number"><?php echo $stats['Hired']; ?></h3>
                                    <p class="stats-label">Hired</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card card">
                                <div class="card-body text-center">
                                    <div class="activity-icon bg-secondary">
                                        <i class="fas fa-ban"></i>
                                    </div>
                                    <h3 class="stats-number"><?php echo $stats['Declined']; ?></h3>
                                    <p class="stats-label">Declined</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card card">
                                <div class="card-body text-center">
                                    <div class="activity-icon bg-info">
                                        <i class="fas fa-archive"></i>
                                    </div>
                                    <h3 class="stats-number"><?php echo $stats['Total']; ?></h3>
                                    <p class="stats-label">Total Archived</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-md-2">
                            <div class="stats-card card">
                                <div class="card-body text-center">
                                    <div class="activity-icon bg-warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <h3 class="stats-number"><?php echo $stats['Applied']; ?></h3>
                                    <p class="stats-label">Pending</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card card">
                                <div class="card-body text-center">
                                    <div class="activity-icon bg-success">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <h3 class="stats-number"><?php echo $stats['Approved']; ?></h3>
                                    <p class="stats-label">Approved</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card card">
                                <div class="card-body text-center">
                                    <div class="activity-icon bg-primary">
                                        <i class="fas fa-comments"></i>
                                    </div>
                                    <h3 class="stats-number"><?php echo $stats['Interview']; ?></h3>
                                    <p class="stats-label">Interview</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card card">
                                <div class="card-body text-center">
                                    <div class="activity-icon bg-info">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <h3 class="stats-number"><?php echo $stats['Assessment']; ?></h3>
                                    <p class="stats-label">Assessment</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card card">
                                <div class="card-body text-center">
                                    <div class="activity-icon bg-secondary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <h3 class="stats-number"><?php echo $stats['Total']; ?></h3>
                                    <p class="stats-label">Total</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Applications Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> <?php echo $show_archived ? 'Archived Applications' : 'Job Applications'; ?></h5>
                        <div>
                            <?php if ($show_archived): ?>
                                <a href="?<?php echo $job_id ? 'job_id=' . $job_id : ''; ?>" class="btn btn-primary btn-sm action-btn">
                                    <i class="fas fa-arrow-left"></i> Back to Active
                                </a>
                            <?php else: ?>
                                <a href="?archived=1<?php echo $job_id ? '&job_id=' . $job_id : ''; ?>" class="btn btn-secondary btn-sm action-btn">
                                    <i class="fas fa-archive"></i> View Archived
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($applications) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Candidate</th>
                                            <th>Job Position</th>
                                            <th>Department</th>
                                            <th>Applied Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($applications as $application): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($application['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                                <td><?php echo htmlspecialchars($application['department_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($application['application_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    // Get current interview status for better status display
                                                    $current_interview = $conn->prepare("SELECT i.status as interview_status, ist.stage_name 
                                                                                         FROM interviews i 
                                                                                         JOIN interview_stages ist ON i.stage_id = ist.stage_id 
                                                                                         WHERE i.application_id = ? AND i.status != 'Completed' 
                                                                                         ORDER BY ist.stage_order DESC LIMIT 1");
                                                    $current_interview->execute([$application['application_id']]);
                                                    $interview_info = $current_interview->fetch(PDO::FETCH_ASSOC);
                                                    
                                                    if ($application['status'] == 'Assessment' && $interview_info) {
                                                        if ($interview_info['interview_status'] == 'Rescheduled') {
                                                            echo '<span class="badge badge-warning">🗓️ Interview Pending</span>';
                                                        } elseif ($interview_info['interview_status'] == 'Scheduled') {
                                                            echo '<span class="badge badge-primary">🎯 Interview Scheduled</span>';
                                                        } else {
                                                            echo '<span class="badge badge-info">📋 In Assessment</span>';
                                                        }
                                                    } elseif ($application['status'] == 'Screening') {
                                                        echo '<span class="badge badge-info">🔍 Under Review</span>';
                                                    } elseif ($application['status'] == 'Applied') {
                                                        echo '<span class="badge badge-warning">📝 Awaiting Review</span>';
                                                    } elseif ($application['status'] == 'Reference Check') {
                                                        echo '<span class="badge badge-info">📋 Reference Check</span>';
                                                    } elseif ($application['status'] == 'Approval') {
                                                        echo '<span class="badge badge-warning">📋 Pending HR Approval</span>';
                                                    } elseif ($application['status'] == 'Hired') {
                                                        echo '<span class="badge badge-success">🎉 Successfully Hired</span>';
                                                    } elseif ($application['status'] == 'Rejected') {
                                                        echo '<span class="badge badge-danger">❌ Application Rejected</span>';
                                                    } else {
                                                        echo '<span class="badge badge-secondary">' . htmlspecialchars($application['status']) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-info btn-sm action-btn" data-toggle="modal" data-target="#reviewModal<?php echo $application['application_id']; ?>">
                                                        <i class="fas fa-eye"></i> Review
                                                    </button>
                                                    
                                                    <?php if ($application['status'] == 'Applied'): ?>
                                                        <form method="POST" style="display:inline;" class="ml-1">
                                                            <input type="hidden" name="action" value="approve_application">
                                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                            <button type="submit" class="btn btn-success btn-sm action-btn" onclick="return confirm('Approve this application?')">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display:inline;" class="ml-1">
                                                            <input type="hidden" name="action" value="reject_candidate">
                                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm action-btn" onclick="return confirm('Reject this application?')">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        </form>
                                                    <?php elseif ($application['status'] == 'Approved'): ?>
                                                        <span class="badge badge-success ml-1">Ready for Interview Process</span>
                                                    <?php elseif (in_array($application['status'], ['Interview', 'Assessment'])): ?>
                                                        <span class="badge badge-info ml-1">In Progress - Check Candidates Dashboard</span>
                                                    <?php elseif ($application['status'] == 'Rejected'): ?>
                                                        <form method="POST" style="display:inline;" class="ml-1">
                                                            <input type="hidden" name="action" value="reopen_application">
                                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                            <button type="submit" class="btn btn-warning btn-sm action-btn" onclick="return confirm('Reopen this application?')">
                                                                <i class="fas fa-redo"></i> Reopen
                                                            </button>
                                                        </form>
                                                    <?php elseif (in_array($application['status'], ['Hired', 'Declined'])): ?>
                                                        <span class="badge badge-secondary ml-1">Archived</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <h5><i class="fas fa-info-circle"></i> No Applications</h5>
                                <p>No job applications found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Review Modals -->
                <?php foreach($applications as $application): ?>
                    <div class="modal fade" id="reviewModal<?php echo $application['application_id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-user"></i> Application Review - <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                                    </h5>
                                    <button type="button" class="close" data-dismiss="modal">
                                        <span>&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-info-circle"></i> Personal Information</h6>
                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($application['email']); ?></p>
                                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($application['phone']); ?></p>
                                            <p><strong>Current Position:</strong> <?php echo htmlspecialchars($application['current_position'] ?: 'Not specified'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-briefcase"></i> Application Details</h6>
                                            <p><strong>Applied For:</strong> <?php echo htmlspecialchars($application['job_title']); ?></p>
                                            <p><strong>Department:</strong> <?php echo htmlspecialchars($application['department_name']); ?></p>
                                            <p><strong>Application Date:</strong> <?php echo date('M d, Y', strtotime($application['application_date'])); ?></p>
                                            <p><strong>Status:</strong> 
                                                <?php 
                                                // Get current interview status for modal display
                                                $current_interview = $conn->prepare("SELECT i.status as interview_status, ist.stage_name 
                                                                                     FROM interviews i 
                                                                                     JOIN interview_stages ist ON i.stage_id = ist.stage_id 
                                                                                     WHERE i.application_id = ? AND i.status != 'Completed' 
                                                                                     ORDER BY ist.stage_order DESC LIMIT 1");
                                                $current_interview->execute([$application['application_id']]);
                                                $interview_info = $current_interview->fetch(PDO::FETCH_ASSOC);
                                                
                                                if ($application['status'] == 'Assessment' && $interview_info) {
                                                    if ($interview_info['interview_status'] == 'Rescheduled') {
                                                        echo '<span class="badge badge-warning">🗓️ Interview Awaiting Schedule</span>';
                                                    } elseif ($interview_info['interview_status'] == 'Scheduled') {
                                                        echo '<span class="badge badge-primary">🎯 Interview Scheduled</span>';
                                                    } else {
                                                        echo '<span class="badge badge-info">📋 In Assessment Process</span>';
                                                    }
                                                } elseif ($application['status'] == 'Screening') {
                                                    echo '<span class="badge badge-info">🔍 Under Initial Review</span>';
                                                } elseif ($application['status'] == 'Applied') {
                                                    echo '<span class="badge badge-warning">📝 Awaiting Initial Review</span>';
                                                } elseif ($application['status'] == 'Approval') {
                                                    echo '<span class="badge badge-warning">📋 Awaiting HR Approval</span>';
                                                } elseif ($application['status'] == 'Hired') {
                                                    echo '<span class="badge badge-success">🎉 Successfully Hired</span>';
                                                } elseif ($application['status'] == 'Rejected') {
                                                    echo '<span class="badge badge-danger">❌ Application Rejected</span>';
                                                } else {
                                                    echo '<span class="badge badge-secondary">' . htmlspecialchars($application['status']) . '</span>';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($application['resume_filename']): ?>
                                        <div class="mt-3">
                                            <h6><i class="fas fa-file-pdf"></i> Resume</h6>
                                            <a href="uploads/resumes/<?php echo $application['resume_filename']; ?>" target="_blank" class="btn btn-info action-btn">
                                                <i class="fas fa-download"></i> View Resume
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <?php if ($application['status'] == 'Applied'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="approve_application">
                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                            <button type="submit" class="btn btn-success action-btn" onclick="return confirm('Approve this application?')">
                                                <i class="fas fa-check"></i> Approve Application
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" class="ml-2">
                                            <input type="hidden" name="action" value="reject_candidate">
                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                            <button type="submit" class="btn btn-danger action-btn" onclick="return confirm('Reject this application?')">
                                                <i class="fas fa-times"></i> Reject Application
                                            </button>
                                        </form>
                                    <?php elseif ($application['status'] == 'Approved'): ?>
                                        <span class="badge badge-success">Application Approved - Ready for Interview Process</span>
                                    <?php elseif (in_array($application['status'], ['Interview', 'Assessment'])): ?>
                                        <span class="badge badge-info">In Progress - Managed in Candidates Dashboard</span>
                                    <?php elseif ($application['status'] == 'Rejected'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="reopen_application">
                                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                            <button type="submit" class="btn btn-warning action-btn" onclick="return confirm('Reopen this application?')">
                                                <i class="fas fa-redo"></i> Reopen Application
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
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