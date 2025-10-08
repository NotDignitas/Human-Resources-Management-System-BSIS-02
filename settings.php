<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection (assuming you have this file)
// include 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Settings';

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            // Handle profile update
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            // Validate and update profile
            if (!empty($username) && !empty($email)) {
                // Here you would update the database
                $_SESSION['username'] = $username;
                $success_message = 'Profile updated successfully!';
            } else {
                $error_message = 'Please fill in all required fields.';
            }
            break;
            
        case 'change_password':
            // Handle password change
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password === $confirm_password && strlen($new_password) >= 6) {
                // Here you would verify current password and update
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Passwords do not match or are too short.';
            }
            break;
            
        case 'update_system':
            // Handle system settings update
            $success_message = 'System settings updated successfully!';
            break;
            
        case 'update_notifications':
            // Handle notification preferences
            $success_message = 'Notification preferences updated!';
            break;
    }
}

// Get current user data (mock data for demonstration)
$user_data = [
    'username' => $_SESSION['username'] ?? 'Admin User',
    'email' => 'admin@company.com',
    'phone' => '+1234567890',
    'role' => $_SESSION['role'] ?? 'Administrator',
    'last_login' => date('Y-m-d H:i:s', strtotime('-2 hours'))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HR Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=rose">
    
    <style>
        :root {
            /* Align palette with employee_profile.php */
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
            /* Back-compat with existing variable names */
            --primary-color: var(--azure-blue);
            --secondary-color: var(--azure-blue-dark);
        }

        body {
            background: var(--azure-blue-pale);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .settings-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .settings-card-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 15px 20px;
            border-bottom: none;
        }

        .settings-card-body { padding: 20px; }

        .form-group label {
            font-weight: 600;
            color: var(--azure-blue-dark);
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            padding: 6px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: 600;
            color: #fff;
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }

        .nav-tabs { border-bottom: 2px solid #dee2e6; margin-bottom: 20px; }
        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 20px;
            margin-right: 5px;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            border: none;
        }

        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc; transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--azure-blue); }
        input:checked + .slider:before { transform: translateX(26px); }

        .stat-card {
            background: white; border-radius: 10px; padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;
        }
        .stat-card i { font-size: 2.5rem; color: var(--azure-blue); margin-bottom: 15px; }
        .stat-card h4 { color: #333; margin-bottom: 5px; }
        .stat-card p { color: #6c757d; margin: 0; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center"></div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Settings Navigation -->
        <ul class="nav nav-tabs" id="settingsTabs">
            <li class="nav-item">
                <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile">
                    <i class="fas fa-user mr-2"></i>Profile Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="security-tab" data-toggle="tab" href="#security">
                    <i class="fas fa-shield-alt mr-2"></i>Security
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="system-tab" data-toggle="tab" href="#system">
                    <i class="fas fa-cogs mr-2"></i>System Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="notifications-tab" data-toggle="tab" href="#notifications">
                    <i class="fas fa-bell mr-2"></i>Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="backup-tab" data-toggle="tab" href="#backup">
                    <i class="fas fa-database mr-2"></i>Backup & Data
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="settingsTabsContent">
            <!-- Profile Settings Tab -->
            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h5 class="mb-0"><i class="fas fa-user mr-2"></i>Personal Information</h5>
                            </div>
                            <div class="settings-card-body">
                                <form method="POST" id="profileForm">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="username">Username *</label>
                                                <input type="text" class="form-control" id="username" name="username" 
                                                       value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">Email Address *</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($user_data['phone']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="role">Role</label>
                                                <input type="text" class="form-control" id="role" 
                                                       value="<?php echo htmlspecialchars($user_data['role']); ?>" disabled>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="bio">Bio</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="3" 
                                                  placeholder="Tell us about yourself..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-user-circle"></i>
                            <h4><?php echo htmlspecialchars($user_data['username']); ?></h4>
                            <p><?php echo htmlspecialchars($user_data['role']); ?></p>
                            <hr>
                            <small class="text-muted">
                                Last Login: <?php echo date('M d, Y H:i', strtotime($user_data['last_login'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Settings Tab -->
            <div class="tab-pane fade" id="security" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h5 class="mb-0"><i class="fas fa-key mr-2"></i>Change Password</h5>
                            </div>
                            <div class="settings-card-body">
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="form-group">
                                        <label for="current_password">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="new_password">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password" required>
                                        <small class="form-text text-muted">
                                            Password must be at least 6 characters long
                                        </small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-lock mr-2"></i>Update Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h5 class="mb-0"><i class="fas fa-shield-alt mr-2"></i>Security Options</h5>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-group">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Two-Factor Authentication</strong>
                                            <br><small class="text-muted">Add an extra layer of security</small>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" id="two_factor">
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Login Notifications</strong>
                                            <br><small class="text-muted">Get notified of login attempts</small>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" id="login_notifications" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Session Timeout</strong>
                                            <br><small class="text-muted">Auto logout after inactivity</small>
                                        </div>
                                        <select class="form-control" style="width: 120px;">
                                            <option value="30">30 min</option>
                                            <option value="60" selected>1 hour</option>
                                            <option value="120">2 hours</option>
                                            <option value="240">4 hours</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Settings Tab -->
            <div class="tab-pane fade" id="system" role="tabpanel">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs mr-2"></i>System Configuration</h5>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST" id="systemForm">
                            <input type="hidden" name="action" value="update_system">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="company_name">Company Name</label>
                                        <input type="text" class="form-control" id="company_name" 
                                               name="company_name" value="ABC Corporation">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="time_zone">Time Zone</label>
                                        <select class="form-control" id="time_zone" name="time_zone">
                                            <option value="UTC-8">Pacific Time (UTC-8)</option>
                                            <option value="UTC-5" selected>Eastern Time (UTC-5)</option>
                                            <option value="UTC">GMT (UTC)</option>
                                            <option value="UTC+1">Central European (UTC+1)</option>
                                            <option value="UTC+8">Philippine Time (UTC+8)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="date_format">Date Format</label>
                                        <select class="form-control" id="date_format" name="date_format">
                                            <option value="MM/DD/YYYY" selected>MM/DD/YYYY</option>
                                            <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                                            <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="currency">Default Currency</label>
                                        <select class="form-control" id="currency" name="currency">
                                            <option value="USD" selected>USD - US Dollar</option>
                                            <option value="EUR">EUR - Euro</option>
                                            <option value="GBP">GBP - British Pound</option>
                                            <option value="PHP">PHP - Philippine Peso</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="language">System Language</label>
                                        <select class="form-control" id="language" name="language">
                                            <option value="en" selected>English</option>
                                            <option value="es">Spanish</option>
                                            <option value="fr">French</option>
                                            <option value="de">German</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="theme">System Theme</label>
                                        <select class="form-control" id="theme" name="theme">
                                            <option value="light" selected>Light Theme</option>
                                            <option value="dark">Dark Theme</option>
                                            <option value="auto">Auto (System)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i>Save System Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div class="tab-pane fade" id="notifications" role="tabpanel">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h5 class="mb-0"><i class="fas fa-bell mr-2"></i>Notification Preferences</h5>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST" id="notificationsForm">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <h6 class="mb-3">Email Notifications</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Leave Requests</strong>
                                                <br><small class="text-muted">New leave requests and approvals</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_leave" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Performance Reviews</strong>
                                                <br><small class="text-muted">Review deadlines and updates</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_performance" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Training Sessions</strong>
                                                <br><small class="text-muted">Upcoming training and enrollments</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_training" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>System Updates</strong>
                                                <br><small class="text-muted">System maintenance and updates</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_system">
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3">Push Notifications</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Urgent Requests</strong>
                                                <br><small class="text-muted">High priority notifications</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="push_urgent" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Daily Summaries</strong>
                                                <br><small class="text-muted">Daily activity summaries</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="push_summary">
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i>Save Notification Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Backup & Data Tab -->
            <div class="tab-pane fade" id="backup" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h5 class="mb-0"><i class="fas fa-database mr-2"></i>Data Management</h5>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-group">
                                    <label>Automated Backups</label>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Enable Automatic Backups</strong>
                                            <br><small class="text-muted">Create automatic database backups</small>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" id="auto_backup" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="backup_frequency">Backup Frequency</label>
                                    <select class="form-control" id="backup_frequency">
                                        <option value="daily" selected>Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="retention_days">Backup Retention (Days)</label>
                                    <input type="number" class="form-control" id="retention_days" 
                                           value="30" min="1" max="365">
                                    <small class="form-text text-muted">
                                        Number of days to keep backup files
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <button type="button" class="btn btn-secondary" id="createBackup">
                                        <i class="fas fa-download mr-2"></i>Create Manual Backup
                                    </button>
                                    <button type="button" class="btn btn-primary ml-2" id="saveBackupSettings">
                                        <i class="fas fa-save mr-2"></i>Save Backup Settings
                                    </button>
                                </div>
                                
                                <hr>
                                
                                <h6>Data Export</h6>
                                <p class="text-muted">Export your data for external use or migration</p>
                                <div class="form-group">
                                    <button type="button" class="btn btn-outline-primary" id="exportEmployees">
                                        <i class="fas fa-file-csv mr-2"></i>Export Employees
                                    </button>
                                    <button type="button" class="btn btn-outline-primary ml-2" id="exportPayroll">
                                        <i class="fas fa-file-excel mr-2"></i>Export Payroll Data
                                    </button>
                                    <button type="button" class="btn btn-outline-primary ml-2" id="exportReports">
                                        <i class="fas fa-file-pdf mr-2"></i>Export Reports
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-hdd"></i>
                            <h4>2.1 GB</h4>
                            <p>Database Size</p>
                            <hr>
                            <small class="text-muted">Last Backup: Today, 03:00 AM</small>
                        </div>
                        
                        <div class="stat-card mt-3">
                            <i class="fas fa-history"></i>
                            <h4>15</h4>
                            <p>Backup Files Available</p>
                            <hr>
                            <button class="btn btn-sm btn-outline-primary" id="viewBackupHistory">
                                <i class="fas fa-eye mr-1"></i>View History
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- jQuery, Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Form validation and submission
            $('#profileForm').on('submit', function(e) {
                const username = $('#username').val().trim();
                const email = $('#email').val().trim();
                
                if (!username || !email) {
                    e.preventDefault();
                    showAlert('Please fill in all required fields.', 'danger');
                    return false;
                }
                
                // Email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    showAlert('Please enter a valid email address.', 'danger');
                    return false;
                }
            });

            $('#passwordForm').on('submit', function(e) {
                const currentPassword = $('#current_password').val();
                const newPassword = $('#new_password').val();
                const confirmPassword = $('#confirm_password').val();
                
                if (!currentPassword || !newPassword || !confirmPassword) {
                    e.preventDefault();
                    showAlert('Please fill in all password fields.', 'danger');
                    return false;
                }
                
                if (newPassword.length < 6) {
                    e.preventDefault();
                    showAlert('New password must be at least 6 characters long.', 'danger');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showAlert('New passwords do not match.', 'danger');
                    return false;
                }
            });

            // Tab switching with URL hash
            if (window.location.hash) {
                const tab = window.location.hash.substring(1);
                $('#' + tab + '-tab').tab('show');
            }
            
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                const target = $(e.target).attr('href');
                window.location.hash = target.substring(1);
            });

            // Switch toggles
            $('.switch input[type="checkbox"]').on('change', function() {
                const isChecked = $(this).is(':checked');
                const settingName = $(this).attr('id') || $(this).attr('name');
                
                // Show confirmation for security settings
                if (settingName === 'two_factor' && isChecked) {
                    if (!confirm('Enabling two-factor authentication will require you to use an authenticator app. Continue?')) {
                        $(this).prop('checked', false);
                        return;
                    }
                }
                
                // Auto-save setting
                saveToggleSetting(settingName, isChecked);
            });

            // Backup operations
            $('#createBackup').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Creating Backup...');
                
                // Simulate backup creation
                setTimeout(function() {
                    btn.prop('disabled', false).html('<i class="fas fa-download mr-2"></i>Create Manual Backup');
                    showAlert('Backup created successfully!', 'success');
                }, 3000);
            });

            $('#saveBackupSettings').on('click', function() {
                const frequency = $('#backup_frequency').val();
                const retention = $('#retention_days').val();
                const autoBackup = $('#auto_backup').is(':checked');
                
                // Save backup settings
                showAlert('Backup settings saved successfully!', 'success');
            });

            // Export functions
            $('#exportEmployees').on('click', function() {
                exportData('employees', 'csv');
            });

            $('#exportPayroll').on('click', function() {
                exportData('payroll', 'excel');
            });

            $('#exportReports').on('click', function() {
                exportData('reports', 'pdf');
            });

            $('#viewBackupHistory').on('click', function() {
                showBackupHistory();
            });

            // Theme switching
            $('#theme').on('change', function() {
                const theme = $(this).val();
                // persist to localStorage
                try { localStorage.setItem('hr_theme', theme); } catch (e) {}
                // apply immediately
                if (window.setTheme) { window.setTheme(theme); }
                else { applyTheme(theme); }
            });

            // Initialize theme selector from saved preference
            (function initThemeFromStorage(){
                try {
                    const saved = localStorage.getItem('hr_theme');
                    if (saved) {
                        $('#theme').val(saved);
                        if (window.setTheme) { window.setTheme(saved); }
                        else { applyTheme(saved); }
                    }
                } catch(e) {}
            })();

            // Auto-save for system settings
            $('#systemForm input, #systemForm select').on('change', function() {
                const settingName = $(this).attr('name');
                const settingValue = $(this).val();
                
                // Auto-save after 2 seconds of no changes
                clearTimeout(window.settingsTimeout);
                window.settingsTimeout = setTimeout(function() {
                    saveSetting(settingName, settingValue);
                }, 2000);
            });

            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();

            // Password strength indicator
            $('#new_password').on('input', function() {
                const password = $(this).val();
                updatePasswordStrength(password);
            });
        });

        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>
                    ${message}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            `;
            $('.main-content').prepend(alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);
        }

        function saveToggleSetting(settingName, isChecked) {
            // Simulate AJAX save
            console.log(`Saving ${settingName}: ${isChecked}`);
            
            // You would make an AJAX call here to save the setting
            $.ajax({
                url: 'ajax/save_setting.php',
                method: 'POST',
                data: {
                    setting: settingName,
                    value: isChecked
                },
                success: function(response) {
                    // Handle success
                },
                error: function() {
                    showAlert('Failed to save setting. Please try again.', 'danger');
                }
            });
        }

        function saveSetting(settingName, settingValue) {
            // Show saving indicator
            const saveIndicator = $('<small class="text-muted ml-2"><i class="fas fa-spinner fa-spin"></i> Saving...</small>');
            $(`[name="${settingName}"]`).parent().append(saveIndicator);
            
            // Simulate AJAX save
            setTimeout(function() {
                saveIndicator.remove();
                const savedIndicator = $('<small class="text-success ml-2"><i class="fas fa-check"></i> Saved</small>');
                $(`[name="${settingName}"]`).parent().append(savedIndicator);
                
                setTimeout(function() {
                    savedIndicator.fadeOut(function() {
                        $(this).remove();
                    });
                }, 2000);
            }, 1000);
        }

        function exportData(type, format) {
            const btn = $(`#export${type.charAt(0).toUpperCase() + type.slice(1)}`);
            const originalText = btn.html();
            
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Exporting...');
            
            // Simulate export process
            setTimeout(function() {
                btn.prop('disabled', false).html(originalText);
                
                // Create a temporary download link
                const link = document.createElement('a');
                link.href = `exports/${type}_export_${Date.now()}.${format}`;
                link.download = `${type}_export_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                showAlert(`${type.charAt(0).toUpperCase() + type.slice(1)} data exported successfully!`, 'success');
            }, 2000);
        }

        function showBackupHistory() {
            const modalHtml = `
                <div class="modal fade" id="backupHistoryModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title"><i class="fas fa-history mr-2"></i>Backup History</h5>
                                <button type="button" class="close text-white" data-dismiss="modal">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Type</th>
                                                <th>Size</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>2024-08-26</td>
                                                <td>03:00 AM</td>
                                                <td>Automatic</td>
                                                <td>2.1 GB</td>
                                                <td><span class="badge badge-success">Success</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>2024-08-25</td>
                                                <td>03:00 AM</td>
                                                <td>Automatic</td>
                                                <td>2.0 GB</td>
                                                <td><span class="badge badge-success">Success</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>2024-08-24</td>
                                                <td>02:30 PM</td>
                                                <td>Manual</td>
                                                <td>2.0 GB</td>
                                                <td><span class="badge badge-success">Success</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-danger">
                                    <i class="fas fa-trash mr-2"></i>Delete Old Backups
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            $('#backupHistoryModal').modal('show');
            
            $('#backupHistoryModal').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }

        function applyTheme(theme) {
            // Theme switching logic
            if (theme === 'dark') {
                $('body').addClass('dark-theme');
                showAlert('Dark theme applied successfully!', 'success');
            } else if (theme === 'light') {
                $('body').removeClass('dark-theme');
                showAlert('Light theme applied successfully!', 'success');
            } else if (theme === 'auto') {
                // Auto theme based on system preference
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    $('body').addClass('dark-theme');
                } else {
                    $('body').removeClass('dark-theme');
                }
                showAlert('Auto theme applied successfully!', 'success');
            }
        }

        function updatePasswordStrength(password) {
            let strength = 0;
            let strengthText = '';
            let strengthClass = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch (strength) {
                case 0:
                case 1:
                    strengthText = 'Very Weak';
                    strengthClass = 'text-danger';
                    break;
                case 2:
                    strengthText = 'Weak';
                    strengthClass = 'text-warning';
                    break;
                case 3:
                    strengthText = 'Fair';
                    strengthClass = 'text-info';
                    break;
                case 4:
                    strengthText = 'Good';
                    strengthClass = 'text-primary';
                    break;
                case 5:
                    strengthText = 'Strong';
                    strengthClass = 'text-success';
                    break;
            }
            
            // Remove existing strength indicator
            $('#new_password').siblings('.password-strength').remove();
            
            // Add new strength indicator
            const strengthIndicator = $(`<small class="form-text password-strength ${strengthClass}">Password Strength: ${strengthText}</small>`);
            $('#new_password').after(strengthIndicator);
        }

        // Responsive sidebar toggle for mobile
        function toggleSidebar() {
            $('.sidebar').toggleClass('show');
        }

        // Add mobile menu button if screen is small
        if (window.innerWidth <= 768) {
            const mobileToggle = `
                <button class="btn btn-primary d-md-none mb-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i> Menu
                </button>
            `;
            $('.page-header').prepend(mobileToggle);
        }
    </script>

    <!-- Dark theme styles -->
    <style>
        .dark-theme {
            background-color: #121212 !important;
            color: #ffffff !important;
        }
        
        .dark-theme .settings-card,
        .dark-theme .page-header,
        .dark-theme .stat-card {
            background-color: #1e1e1e !important;
            color: #ffffff !important;
            border-color: #333 !important;
        }
        
        .dark-theme .form-control {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
            color: #ffffff !important;
        }
        
        .dark-theme .form-control:focus {
            background-color: #2d2d2d !important;
            border-color: var(--primary-color) !important;
            color: #ffffff !important;
        }
        
        .dark-theme .nav-tabs .nav-link {
            color: #cccccc !important;
            border-color: #444 !important;
        }
        
        .dark-theme .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: white !important;
        }
        
        .dark-theme .table {
            color: #ffffff !important;
        }
        
        .dark-theme .table thead th {
            border-color: #444 !important;
        }
        
        .dark-theme .table td {
            border-color: #444 !important;
        }
        
        .dark-theme .modal-content {
            background-color: #1e1e1e !important;
            color: #ffffff !important;
        }
    </style>
</body>
</html>