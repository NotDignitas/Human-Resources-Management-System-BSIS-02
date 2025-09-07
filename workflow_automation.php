<?php
require_once 'config.php';

// Function to create employee profile for hired candidate
function createEmployeeProfile($candidate_id, $conn) {
    $stmt = $conn->prepare("SELECT * FROM candidates WHERE candidate_id = ?");
    $stmt->execute([$candidate_id]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($candidate) {
        // Generate employee number
        $emp_number = 'EMP' . str_pad($candidate_id, 4, '0', STR_PAD_LEFT);
        
        // Create employee profile
        $stmt = $conn->prepare("INSERT INTO employee_profiles (employee_number, first_name, last_name, work_email, phone, address, hire_date, status) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'Active')");
        $stmt->execute([
            $emp_number,
            $candidate['first_name'],
            $candidate['last_name'],
            $candidate['email'],
            $candidate['phone'],
            $candidate['address']
        ]);
        
        return $conn->lastInsertId();
    }
    return false;
}

// Function to auto-assign onboarding tasks
function autoAssignOnboardingTasks($onboarding_id, $conn) {
    // Get default onboarding tasks
    $tasks = $conn->query("SELECT task_id FROM onboarding_tasks WHERE is_mandatory = 1 OR task_type IN ('Documentation', 'Orientation', 'IT Setup')")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tasks as $task) {
        $due_date = date('Y-m-d', strtotime('+7 days'));
        $stmt = $conn->prepare("INSERT INTO employee_onboarding_tasks (onboarding_id, task_id, due_date, status) VALUES (?, ?, ?, 'Not Started')");
        $stmt->execute([$onboarding_id, $task['task_id'], $due_date]);
    }
}

echo "Workflow automation functions created successfully!";
?>