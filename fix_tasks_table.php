<?php
require_once 'config.php';

try {
    // Add task_order column if it doesn't exist
    $conn->exec("ALTER TABLE onboarding_tasks ADD COLUMN task_order INT DEFAULT 1");
    echo "✅ task_order column added!<br>";
} catch (Exception $e) {
    echo "Column might already exist: " . $e->getMessage() . "<br>";
}

try {
    // Insert default tasks
    $count = $conn->query("SELECT COUNT(*) as count FROM onboarding_tasks")->fetch()['count'];
    if ($count == 0) {
        $conn->exec("INSERT INTO onboarding_tasks (task_name, description, task_order) VALUES
            ('Document Verification', 'Verify ID and certificates', 1),
            ('Contract Signing', 'Sign employment contract', 2),
            ('IT Setup', 'Setup computer and email', 3),
            ('Orientation', 'Company orientation', 4)");
        echo "✅ Default tasks added!<br>";
    } else {
        echo "Tasks already exist: $count tasks<br>";
    }
    
    echo "<a href='onboarding.php'>Go to Onboarding Page</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>