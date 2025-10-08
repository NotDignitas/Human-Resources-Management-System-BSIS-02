<?php
require_once 'config.php';

function getTotalEmployees() {
    global $conn;
    try {
        $sql = "SELECT COUNT(*) as total_employees FROM employee_profiles";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_employees'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getTotalDepartments() {
    global $conn;
    try {
        $sql = "SELECT COUNT(*) as total_departments FROM departments";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_departments'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getActiveJobOpenings() {
    global $conn;
    try {
        $sql = "SELECT COUNT(*) as active_job_openings FROM job_openings WHERE status = 'Open'";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['active_job_openings'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getUpcomingTrainingSessions() {
    global $conn;
    try {
        $sql = "SELECT COUNT(*) as upcoming_training_sessions FROM training_sessions WHERE status = 'Scheduled' AND start_date >= CURDATE()";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['upcoming_training_sessions'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getRecentActivities() {
    global $conn;
    try {
        $sql = "SELECT a.action, u.username, a.created_at FROM audit_logs a JOIN users u ON a.user_id = u.user_id ORDER BY a.created_at DESC LIMIT 5";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
?>
