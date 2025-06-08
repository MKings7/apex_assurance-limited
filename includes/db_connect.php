<?php
require_once 'config.php';

try {
    // Create connection
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset
    $conn->set_charset(DB_CHARSET);
    
    // Create additional tables that might be missing
    $additional_tables = [
        "CREATE TABLE IF NOT EXISTS `policy_type` (
            `Id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `description` text,
            `base_premium_rate` decimal(5,2) NOT NULL,
            `minimum_premium` decimal(10,2) NOT NULL DEFAULT 5000.00,
            `coverage_details` json,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`Id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS `contact_messages` (
            `Id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `email` varchar(100) NOT NULL,
            `phone` varchar(20),
            `subject` varchar(100) NOT NULL,
            `message` text NOT NULL,
            `status` enum('New','Read','Replied') DEFAULT 'New',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`Id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($additional_tables as $sql) {
        $conn->query($sql);
    }
    
    // Insert default policy types if table is empty
    $check_policy_types = $conn->query("SELECT COUNT(*) as count FROM policy_type");
    if ($check_policy_types && $check_policy_types->fetch_assoc()['count'] == 0) {
        $policy_types = [
            ['Comprehensive', 'Complete coverage including theft, fire, and third party', 8.50, 15000.00, '["Accident damage", "Theft protection", "Fire damage", "Third party liability", "Medical expenses", "Legal liability"]'],
            ['Third Party', 'Basic third party liability coverage only', 3.50, 5000.00, '["Third party liability", "Legal expenses", "Emergency assistance"]'],
            ['Theft Only', 'Protection against vehicle theft only', 2.50, 3000.00, '["Theft protection", "Hijacking coverage", "Keys and locks replacement"]']
        ];
        
        foreach ($policy_types as $type) {
            $stmt = $conn->prepare("INSERT INTO policy_type (name, description, base_premium_rate, minimum_premium, coverage_details) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdds", $type[0], $type[1], $type[2], $type[3], $type[4]);
            $stmt->execute();
        }
    }
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}
?>
