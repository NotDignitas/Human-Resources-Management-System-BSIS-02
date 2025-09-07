<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

echo "Testing job offers...<br>";

try {
    $test = $conn->query("SELECT COUNT(*) FROM job_offers");
    echo "job_offers table exists<br>";
} catch (Exception $e) {
    echo "Error with job_offers table: " . $e->getMessage() . "<br>";
}

try {
    $candidates = $conn->query("SELECT COUNT(*) as count FROM job_applications WHERE status = 'Reference Check'")->fetch();
    echo "Candidates ready for offers: " . $candidates['count'] . "<br>";
} catch (Exception $e) {
    echo "Error checking candidates: " . $e->getMessage() . "<br>";
}

echo "Test complete.";
?>