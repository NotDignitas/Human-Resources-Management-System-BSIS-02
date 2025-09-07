<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

echo "Testing employee onboarding database...<br>";

try {
    $test = $conn->query("SELECT COUNT(*) FROM employees");
    echo "employees table exists<br>";
} catch (Exception $e) {
    echo "Error with employees table: " . $e->getMessage() . "<br>";
}

try {
    $test = $conn->query("DESCRIBE employees");
    echo "employees table structure:<br>";
    while ($row = $test->fetch()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} catch (Exception $e) {
    echo "Error describing employees: " . $e->getMessage() . "<br>";
}

try {
    $count = $conn->query("SELECT COUNT(*) as count FROM employees WHERE onboarding_status = 'Pending'")->fetch();
    echo "Employees with Pending onboarding: " . $count['count'] . "<br>";
} catch (Exception $e) {
    echo "Error checking onboarding_status: " . $e->getMessage() . "<br>";
}
?>