<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Starting...<br>";

session_start();
echo "Step 2: Session started<br>";

require_once 'config.php';
echo "Step 3: Config loaded<br>";

try {
    $test = $conn->query("SELECT 1");
    echo "Step 4: Database connected<br>";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
    exit;
}

echo "Step 5: All good - the issue might be in the HTML<br>";
echo "Check your browser's developer console for JavaScript errors<br>";
echo "Or check Apache error logs<br>";
?>