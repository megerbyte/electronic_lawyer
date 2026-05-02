<?php  
echo "Script started<br>";
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
echo "Loaded config<br>";
require_once 'includes/functions.php';
echo "Loaded functions<br>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "POST request detected<br>";

    $prospectId = $_POST['prospect_id'] ?? null;
    echo "prospect_id: " . htmlspecialchars((string)$prospectId) . "<br>";

    $answers = json_encode($_POST);
    echo "answers encoded<br>";

    // Generate motions and documents based on answers
    $motions = [];
    $docs = [];
    if (isset($_POST['miranda']) && $_POST['miranda'] == 'no') {
        $motions[] = 'Motion to Suppress Statements';
        $docs[] = 'motion_suppress_statements.docx';
        echo "Miranda selected: no<br>";
    }
    if (isset($_POST['probable_cause']) && $_POST['probable_cause'] == 'no') {
        $motions[] = 'Motion to Dismiss for Lack of Probable Cause';
        $docs[] = 'motion_dismiss_probable.docx';
        echo "Probable cause selected: no<br>";
    }
    if (isset($_POST['warrant']) && $_POST['warrant'] == 'yes') {
        $motions[] = 'Motion to Suppress Evidence';
        $docs[] = 'motion_suppress_evidence.docx';
        echo "Warrant selected: yes<br>";
    }
    $motionsText = implode(', ', $motions);
    echo "Motions determined: $motionsText<br>";

    // Update prospect with answers and motions
    $pdo = getDB();
    echo "Database connected<br>";

    $stmt = $pdo->prepare("UPDATE prospects SET answers = ?, motions = ? WHERE id = ?");
    $stmt->execute([$answers, $motionsText, $prospectId]);
    echo "Prospect updated<br>";

    // Get prospect data for merging
    $stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ?");
    $stmt->execute([$prospectId]);
    $prospect = $stmt->fetch();
    echo "Prospect data fetched<br>";

    // Merge and save documents
    $mergedPaths = [];
    $data = [
        'name' => $prospect['name'],
        'address' => $prospect['address'],
        'city' => $prospect['city'],
        'state' => $prospect['state'],
        'zip' => $prospect['zip'],
        'phone' => $prospect['phone'],
        'email' => $prospect['email'],
        'motions' => $motionsText
    ];
    echo "Data array built<br>";
    foreach ($docs as $doc) {
        $template = DOC_TEMPLATES_DIR . $doc;
        if (file_exists($template)) {
            echo "Template $template exists<br>";
            $mergedPath = mergeDocument($template, $data);
            $mergedPaths[] = $mergedPath;
            // Save to DB
            $stmt = $pdo->prepare("INSERT INTO documents (solicitation_id, doc_name, file_path, merged) VALUES (0, ?, ?, 1)"); // solicitation_id updated later
            $stmt->execute([$doc, $mergedPath]);
            $docId = $pdo->lastInsertId();
            echo "Document $doc saved<br>";
        } else {
            echo "Template $template missing<br>";
        }
    }

    // Generate report text
    $reportText = "Case Evaluation Report for {$prospect['name']} \nAddress: {$prospect['address']}, {$prospect['city']}, {$prospect['state']} {$prospect['zip']} \nPhone: {$prospect['phone']} \nEmail: {$prospect['email']} \n\nMotions: $motionsText \nDocuments: " . implode(', ', $docs);
    $reportPath = DOC_GENERATED_DIR . 'report_' . $prospectId . '.txt';
    file_put_contents($reportPath, $reportText);
    echo "Report file written: $reportPath<br>";

    // Get admin setting for num lawyers
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'num_lawyers_to_email'");
    $stmt->execute();
    $num = $stmt->fetchColumn() ?: 3;
    echo "Number of lawyers to email: $num<br>";

    // Select lawyers
    $lawyers = selectLawyers($num);
    echo "Lawyers selected<br>";

    // Send to lawyers
    foreach ($lawyers as $lawyerId) {
        $stmt = $pdo->prepare("SELECT email FROM lawyers WHERE id = ?");
        $stmt->execute([$lawyerId]);
        $lawyerEmail = $stmt->fetchColumn();
        echo "Lawyer $lawyerId email: $lawyerEmail<br>";

        $body = "New solicitation for representation. Report attached. Prospect: {$prospect['name']}. Please login to view documents.";
        sendEmail($lawyerEmail, 'New Case Solicitation', $body, $reportPath, $prospect['email']);
        echo "Email sent to $lawyerEmail<br>";
        updateSolicitationCount($lawyerId);

        // Save solicitation
        $stmt = $pdo->prepare("INSERT INTO solicitations (prospect_id, lawyer_id, report_file) VALUES (?, ?, ?)");
        $stmt->execute([$prospectId, $lawyerId, $reportPath]);

        // Update document solicitation_id
        $solicitationId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("UPDATE documents SET solicitation_id = ? WHERE solicitation_id = 0");
        $stmt->execute([$solicitationId]);
        logAudit($lawyerId, 'Solicitation sent for prospect ' . $prospectId);
        echo "Solicitation and audit logged for $lawyerId<br>";
    }

    // Make available to court (send email or link)
    $courtBody = "New case evaluation report attached.";
    sendEmail('court@example.com', 'New Case Evaluation', $courtBody, $reportPath); // Replace court email
    echo "Court notified<br>";

    echo 'Report generated and sent. Thank you.<br>';
    unset($_SESSION['prospect_id']);
} else {
    echo "GET request. Waiting for form submission.<br>";
}
?>