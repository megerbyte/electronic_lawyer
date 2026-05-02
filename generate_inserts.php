<?php
require_once 'config.php';

try {
    $file = fopen('C:/xampp/htdocs/researchrepository.info/dfw.csv', 'r');
    $sql_file = fopen('C:/xampp/htdocs/researchrepository.info/import_lawyers.sql', 'w');

    fwrite($sql_file, "USE resemzqt_researchrepository;\n\n");

    while (($row = fgetcsv($file)) !== false) {
        if (count($row) >= 17) {
            // Escape values for SQL
            $name = addslashes($row[0]);
            $address = addslashes($row[1]);
            $city = addslashes($row[2]);
            $state = addslashes($row[3]);
            $zip = addslashes($row[4]);
            $email = addslashes($row[5]);
            $phone = addslashes($row[6]);
            $user_name = addslashes($row[7]);
            $password = addslashes($row[8]);
            $specialization = $row[9] ? "'".addslashes($row[9])."'" : 'NULL';
            $subscription_level = $row[10] ? "'".addslashes($row[10])."'" : "'Basic'";
            $solicitations = $row[11] ? (int)$row[11] : 0;
            $max_solicitations = $row[12] ? (int)$row[12] : 10;
            $verified = $row[13] ? (int)$row[13] : 0;
            $verification_token = $row[14] ? "'".addslashes($row[14])."'" : 'NULL';
            $reset_token = $row[15] ? "'".addslashes($row[15])."'" : 'NULL';
            $reset_expiry = $row[16] ? "'".addslashes($row[16])."'" : 'NULL';
            $created_at = !empty($row[17]) ? "'".addslashes($row[17])."'" : 'NULL';

            $sql = "INSERT INTO lawyers (
                name, address, city, state, zip, email, phone, user_name, password,
                specialization, subscription_level, solicitations_received_this_month,
                max_solicitations_per_month, verified, verification_token, reset_token,
                reset_expiry, created_at
            ) VALUES (
                '$name', '$address', '$city', '$state', '$zip', '$email', '$phone',
                '$user_name', '$password', $specialization, $subscription_level,
                $solicitations, $max_solicitations, $verified, $verification_token,
                $reset_token, $reset_expiry, $created_at
            );\n";

            fwrite($sql_file, $sql);
        }
    }

    fclose($file);
    fclose($sql_file);
    echo "SQL file generated: import_lawyers.sql";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>