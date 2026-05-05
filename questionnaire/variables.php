<?php
/**
 * questionnaire/variables.php
 *
 * Collects user-supplied variable information for the selected legal form.
 * Groups variables into logical sections and presents them as labeled form fields.
 * On submit, saves answers to user_answers and section selections to
 * user_section_selections, then redirects to output.php.
 */

session_start();

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['case_id'])) {
    header('Location: index.php');
    exit;
}

$pdo    = getDB();
$caseId = (int)$_SESSION['case_id'];

// Load the user case
$caseStmt = $pdo->prepare("SELECT * FROM user_cases WHERE id = ?");
$caseStmt->execute([$caseId]);
$userCase = $caseStmt->fetch();

if (!$userCase || !$userCase['form_id']) {
    header('Location: intake.php');
    exit;
}

$formId = (int)$userCase['form_id'];

// Load form metadata
$formStmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
$formStmt->execute([$formId]);
$form = $formStmt->fetch();

if (!$form) {
    header('Location: intake.php');
    exit;
}

// ───────────────────────────────────────────────────────────────
// Handle POST — save answers
// ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save variable answers
    foreach ($_POST as $key => $value) {
        if (!str_starts_with($key, 'var_') || !ctype_digit(substr($key, 4))) {
            continue;
        }
        $varId      = (int)substr($key, 4);
        $answerVal  = is_string($value) ? trim($value) : '';

        $stmt = $pdo->prepare(
            "INSERT INTO user_answers (case_id, variable_id, answer_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE answer_value = VALUES(answer_value)"
        );
        $stmt->execute([$caseId, $varId, $answerVal]);
    }

    // Save section selections
    if (!empty($_POST['sections'])) {
        foreach ($_POST['sections'] as $sectId => $included) {
            $sectId = (int)$sectId;
            $stmt   = $pdo->prepare(
                "INSERT INTO user_section_selections (case_id, section_id, is_included)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_included = VALUES(is_included)"
            );
            $stmt->execute([$caseId, $sectId, 1]);
        }
    }

    // Mark all optional sections NOT submitted as excluded
    $allOptSections = $pdo->prepare(
        "SELECT id FROM form_sections WHERE form_id = ? AND is_optional = 1"
    );
    $allOptSections->execute([$formId]);
    $submittedSections = array_map('intval', array_keys($_POST['sections'] ?? []));
    foreach ($allOptSections->fetchAll() as $os) {
        if (!in_array((int)$os['id'], $submittedSections, true)) {
            $stmt = $pdo->prepare(
                "INSERT INTO user_section_selections (case_id, section_id, is_included)
                 VALUES (?, ?, 0)
                 ON DUPLICATE KEY UPDATE is_included = 0"
            );
            $stmt->execute([$caseId, (int)$os['id']]);
        }
    }

    // Update case status
    $pdo->prepare("UPDATE user_cases SET status = 'complete' WHERE id = ?")->execute([$caseId]);

    header('Location: output.php');
    exit;
}

// ───────────────────────────────────────────────────────────────
// GET — display the variable form
// ───────────────────────────────────────────────────────────────

// Load all sections for this form
$sectStmt = $pdo->prepare(
    "SELECT * FROM form_sections WHERE form_id = ? ORDER BY section_order ASC"
);
$sectStmt->execute([$formId]);
$formSections = $sectStmt->fetchAll();

// Load all variables for this form (via blocks → sections)
$varStmt = $pdo->prepare(
    "SELECT DISTINCT fv.*, bv.placeholder_text
       FROM form_variables fv
       JOIN block_variables bv ON bv.variable_id = fv.id
       JOIN form_content_blocks fcb ON fcb.id = bv.block_id
       JOIN form_sections fs ON fs.id = fcb.section_id
      WHERE fs.form_id = ?
      ORDER BY fv.variable_name ASC"
);
$varStmt->execute([$formId]);
$allVars = $varStmt->fetchAll();

// Load any previously saved answers for this case
$ansStmt = $pdo->prepare(
    "SELECT variable_id, answer_value FROM user_answers WHERE case_id = ?"
);
$ansStmt->execute([$caseId]);
$savedAnswers = [];
foreach ($ansStmt->fetchAll() as $row) {
    $savedAnswers[(int)$row['variable_id']] = $row['answer_value'];
}

// Group variables into logical display categories
$varGroups = [
    'Case Caption'           => [],
    'Dates & Locations'      => [],
    'Attorney Information'   => [],
    'Opposing Counsel'       => [],
    'Claim-Specific Details' => [],
    'Other Information'      => [],
];

$captionVars = ['plaintiff_name','defendant_name','county_name','court_name','cause_number'];
$dateVars    = ['event_date','event_location'];
$attyVars    = ['attorney_name','bar_number','address','city_state_zip','phone_number','fax_number','email_address'];
$oppVars     = ['opposing_counsel_name'];
$claimVars   = [
    'facts_description','damages_amount','first_cause_of_action','second_cause_of_action',
    'additional_cause_of_action','equitable_relief','equitable_relief_facts','attorney_fees_authority',
    'cause_of_action_elements','additional_facts','explanation','elaboration',
];

$seen = [];
foreach ($allVars as $var) {
    $vn = $var['variable_name'];
    if (isset($seen[$vn])) {
        continue;
    }
    $seen[$vn] = true;

    if (in_array($vn, $captionVars, true)) {
        $varGroups['Case Caption'][] = $var;
    } elseif (in_array($vn, $dateVars, true)) {
        $varGroups['Dates & Locations'][] = $var;
    } elseif (in_array($vn, $attyVars, true)) {
        $varGroups['Attorney Information'][] = $var;
    } elseif (in_array($vn, $oppVars, true)) {
        $varGroups['Opposing Counsel'][] = $var;
    } elseif (in_array($vn, $claimVars, true)) {
        $varGroups['Claim-Specific Details'][] = $var;
    } else {
        $varGroups['Other Information'][] = $var;
    }
}

// Remove empty groups
$varGroups = array_filter($varGroups, fn($g) => !empty($g));

// Optional sections
$optSections = array_filter($formSections, fn($s) => $s['is_optional']);

// Load existing section selections
$selStmt = $pdo->prepare(
    "SELECT section_id, is_included FROM user_section_selections WHERE case_id = ?"
);
$selStmt->execute([$caseId]);
$sectionSelections = [];
foreach ($selStmt->fetchAll() as $row) {
    $sectionSelections[(int)$row['section_id']] = (bool)$row['is_included'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal Document — Enter Information</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="card">
        <h1>Enter Document Information</h1>
        <p class="progress">
            <strong>Form:</strong> <?= htmlspecialchars($form['form_code'] . ' — ' . $form['form_title']) ?>
        </p>

        <form method="POST" action="variables.php">

            <?php foreach ($varGroups as $groupName => $vars): ?>
            <div class="section-group">
                <h3><?= htmlspecialchars($groupName) ?></h3>

                <?php foreach ($vars as $var): ?>
                <?php
                    $varId      = (int)$var['id'];
                    $savedVal   = $savedAnswers[$varId] ?? '';
                    $inputType  = $var['input_type'];
                    $label      = htmlspecialchars($var['display_label']);
                    $hint       = $var['hint'] ? htmlspecialchars($var['hint']) : '';
                    $fieldName  = 'var_' . $varId;
                ?>
                <div style="margin-bottom:16px;">
                    <label for="<?= $fieldName ?>"><?= $label ?></label>

                    <?php if ($inputType === 'textarea'): ?>
                        <textarea
                            id="<?= $fieldName ?>"
                            name="<?= $fieldName ?>"
                            placeholder="<?= $hint ?>"
                        ><?= htmlspecialchars($savedVal) ?></textarea>

                    <?php elseif ($inputType === 'date'): ?>
                        <input
                            type="date"
                            id="<?= $fieldName ?>"
                            name="<?= $fieldName ?>"
                            value="<?= htmlspecialchars($savedVal) ?>">

                    <?php elseif ($inputType === 'select' && $var['variable_name'] === 'discovery_level'): ?>
                        <select id="<?= $fieldName ?>" name="<?= $fieldName ?>">
                            <option value="">-- Select --</option>
                            <option value="1" <?= $savedVal === '1' ? 'selected' : '' ?>>Level 1 ($50,000 or less)</option>
                            <option value="2" <?= $savedVal === '2' ? 'selected' : '' ?>>Level 2 (Standard)</option>
                            <option value="3" <?= $savedVal === '3' ? 'selected' : '' ?>>Level 3 (Complex / Court-ordered)</option>
                        </select>

                    <?php elseif ($inputType === 'select' && $var['variable_name'] === 'alternative_or_addition'): ?>
                        <select id="<?= $fieldName ?>" name="<?= $fieldName ?>">
                            <option value="">-- Select --</option>
                            <option value="the alternative" <?= $savedVal === 'the alternative' ? 'selected' : '' ?>>In the Alternative</option>
                            <option value="addition" <?= $savedVal === 'addition' ? 'selected' : '' ?>>In Addition</option>
                        </select>

                    <?php else: ?>
                        <input
                            type="text"
                            id="<?= $fieldName ?>"
                            name="<?= $fieldName ?>"
                            value="<?= htmlspecialchars($savedVal) ?>"
                            placeholder="<?= $hint ?>">
                    <?php endif; ?>

                    <?php if ($hint): ?>
                        <div class="hint"><?= $hint ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($optSections)): ?>
            <div class="section-group">
                <h3>Optional Sections</h3>
                <p style="font-style:italic; font-size:0.9em;">Check the sections you want to include in your document:</p>
                <?php foreach ($optSections as $os): ?>
                <label style="font-weight:normal; margin-bottom:8px; display:block;">
                    <input
                        type="checkbox"
                        name="sections[<?= (int)$os['id'] ?>]"
                        value="1"
                        <?= ($sectionSelections[(int)$os['id']] ?? false) ? 'checked' : '' ?>>
                    <?= htmlspecialchars(
                        ($os['section_letter'] ? $os['section_letter'] . '. ' : '') . $os['section_title']
                    ) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($allVars)): ?>
            <p style="color:#666; font-style:italic;">
                This form has no user-supplied variables — it will be generated as-is.
            </p>
            <?php endif; ?>

            <button type="submit" class="btn">Generate Document &rarr;</button>

        </form>

        <br>
        <form method="GET" action="intake.php" style="display:inline;">
            <button type="submit" class="btn btn-secondary">&larr; Back to Questions</button>
        </form>
        <form method="GET" action="index.php" style="display:inline; margin-left:10px;">
            <button type="submit" class="btn btn-secondary">Start Over</button>
        </form>
    </div>
</body>
</html>
