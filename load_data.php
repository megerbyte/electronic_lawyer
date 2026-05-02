<?php
require_once 'config.php'; // Load credentials from config.sys (or config.php)

try {
    // Connect to MySQL
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Prepare INSERT statement
    $stmt = $pdo->prepare("
        INSERT INTO lawyers (
            name, address, city, state, zip, email, phone, user_name, password,
            specialization, subscription_level, solicitations_received_this_month,
            max_solicitations_per_month, verified, verification_token, reset_token,
            reset_expiry, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Read CSV
    $file = fopen('lawyers.csv', 'r');
    while (($row = fgetcsv($file)) !== false) {
        // Ensure row has at least 17 columns (18 if created_at included)
        if (count($row) >= 17) {
            $stmt->execute([
                $row[0], // name
                $row[1], // address
                $row[2], // city
                $row[3], // state
                $row[4], // zip
                $row[5], // email
                $row[6], // phone
                $row[7], // user_name
                $row[8], // password
                $row[9] ?: null, // specialization
                $row[10] ?: 'Basic', // subscription_level
                $row[11] ? (int)$row[11] : 0, // solicitations_received_this_month
                $row[12] ? (int)$row[12] : 10, // max_solicitations_per_month
                $row[13] ? (int)$row[13] : 0, // verified
                $row[14] ?: null, // verification_token
                $row[15] ?: null, // reset_token
                $row[16] ?: null, // reset_expiry
                !empty($row[17]) ? $row[17] : null // created_at
            ]);
        }
    }
    fclose($file);
    echo "Data imported successfully";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>