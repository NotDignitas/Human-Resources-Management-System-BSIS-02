<?php
// Auto-trigger onboarding when candidate is hired
require_once 'config.php';

function autoTriggerOnboarding($candidate_id, $conn) {
    try {
        // Check if candidate is hired
        $stmt = $conn->prepare("SELECT * FROM candidates WHERE candidate_id = ? AND source = 'Hired'");
        $stmt->execute([$candidate_id]);
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($candidate) {
            // Check if employee profile exists
            $emp_check = $conn->prepare("SELECT employee_id FROM employee_profiles WHERE work_email = ?");
            $emp_check->execute([$candidate['email']]);
            $employee = $emp_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                // Create employee profile
                $emp_number = 'EMP' . str_pad($candidate_id, 4, '0', STR_PAD_LEFT);
                $emp_stmt = $conn->prepare("INSERT INTO employee_profiles (employee_number, first_name, last_name, work_email, phone, address, hire_date, status) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'Active')");
                $emp_stmt->execute([
                    $emp_number,
                    $candidate['first_name'],
                    $candidate['last_name'],
                    $candidate['email'],
                    $candidate['phone'],
                    $candidate['address']
                ]);
                $employee_id = $conn->lastInsertId();
            } else {
                $employee_id = $employee['employee_id'];
            }
            
            // Check if onboarding already exists
            $onboard_check = $conn->prepare("SELECT onboarding_id FROM employee_onboarding WHERE employee_id = ?");
            $onboard_check->execute([$employee_id]);
            
            if (!$onboard_check->fetch()) {
                // Create onboarding record
                $onboard_stmt = $conn->prepare("INSERT INTO employee_onboarding (employee_id, start_date, expected_completion_date, status) VALUES (?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'In Progress')");
                $onboard_stmt->execute([$employee_id]);
                $onboarding_id = $conn->lastInsertId();
                
                // Auto-assign essential onboarding tasks
                $essential_tasks = [
                    "Complete employment forms",
                    "IT equipment setup", 
                    "Office orientation",
                    "Department introduction",
                    "Policy review"
                ];
                
                foreach ($essential_tasks as $task_name) {
                    // Check if task exists, create if not
                    $task_check = $conn->prepare("SELECT task_id FROM onboarding_tasks WHERE task_name = ?");
                    $task_check->execute([$task_name]);
                    $task = $task_check->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$task) {
                        $task_stmt = $conn->prepare("INSERT INTO onboarding_tasks (task_name, task_type, description, is_mandatory) VALUES (?, 'Essential', ?, 1)");
                        $task_stmt->execute([$task_name, "Essential onboarding task: " . $task_name]);
                        $task_id = $conn->lastInsertId();
                    } else {
                        $task_id = $task['task_id'];
                    }
                    
                    // Assign task with staggered due dates
                    $due_date = date('Y-m-d', strtotime('+' . (array_search($task_name, $essential_tasks) + 1) . ' days'));
                    $assign_stmt = $conn->prepare("INSERT INTO employee_onboarding_tasks (onboarding_id, task_id, due_date, status) VALUES (?, ?, ?, 'Not Started')");
                    $assign_stmt->execute([$onboarding_id, $task_id, $due_date]);
                }
                
                return $onboarding_id;
            }
        }
    } catch (Exception $e) {
        error_log("Auto onboarding error: " . $e->getMessage());
    }
    return false;
}

// This function can be called from job_applications.php when hiring
?>