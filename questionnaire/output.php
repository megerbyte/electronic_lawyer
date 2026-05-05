<?php
/**
 * questionnaire/output.php
 *
 * Renders the completed legal document with all user-supplied variable values
 * substituted into the form template. Provides Print, Download, and Start Over buttons.
 */

session_start();

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['case_id'])) {
    header('Location: index.php');
    exit;
}

$pdo    = getDB();
$caseId = (int)$_SESSION['case_id'];

// Load the case
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

// Load user answers
$ansStmt = $pdo->prepare(
    "SELECT fv.variable_name, ua.answer_value
       FROM user_answers ua
       JOIN form_variables fv ON fv.id = ua.variable_id
      WHERE ua.case_id = ?"
);
$ansStmt->execute([$caseId]);
$answers = [];
foreach ($ansStmt->fetchAll() as $row) {
    $answers[$row['variable_name']] = $row['answer_value'];
}

// Load section selections
$selStmt = $pdo->prepare(
    "SELECT section_id, is_included FROM user_section_selections WHERE case_id = ?"
);
$selStmt->execute([$caseId]);
$sectionSelections = [];
foreach ($selStmt->fetchAll() as $row) {
    $sectionSelections[(int)$row['section_id']] = (bool)$row['is_included'];
}

// Load form sections and blocks in order
$sectStmt = $pdo->prepare(
    "SELECT * FROM form_sections WHERE form_id = ? ORDER BY section_order ASC"
);
$sectStmt->execute([$formId]);
$sections = $sectStmt->fetchAll();

$blockStmt = $pdo->prepare(
    "SELECT fcb.*, fs.section_letter, fs.section_title, fs.is_optional AS section_optional
       FROM form_content_blocks fcb
       JOIN form_sections fs ON fs.id = fcb.section_id
      WHERE fs.form_id = ?
      ORDER BY fcb.section_id ASC, fcb.block_order ASC"
);
$blockStmt->execute([$formId]);
$allBlocks = $blockStmt->fetchAll();

// Group blocks by section_id
$blocksBySection = [];
foreach ($allBlocks as $block) {
    $blocksBySection[(int)$block['section_id']][] = $block;
}

/**
 * Substitute {{variable_name}} tokens with user-supplied values (or a red placeholder).
 *
 * The pattern /\{\{([a-z0-9_]+)\}\}/ matches the normalized variable tokens produced by
 * seed_forms.php's build_content_template() function, which converts all real placeholders
 * to {{snake_case_name}} format using the same [a-z0-9_] character set.
 */
function substitute_vars(string $template, array $answers): string
{
    return preg_replace_callback(
        '/\{\{([a-z0-9_]+)\}\}/',
        function (array $m) use ($answers): string {
            $varName = $m[1];
            if (isset($answers[$varName]) && $answers[$varName] !== '') {
                return '<span style="font-style:normal;">'
                    . htmlspecialchars($answers[$varName], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    . '</span>';
            }
            // Missing value — show in red
            return '<span style="color:red; font-weight:bold;">[INFORMATION REQUIRED]</span>';
        },
        $template
    );
}

// Build the completed document HTML body
$docParts = [];
foreach ($sections as $section) {
    $sectionId = (int)$section['id'];

    // Skip optional sections the user deselected
    if ($section['is_optional'] && isset($sectionSelections[$sectionId]) && !$sectionSelections[$sectionId]) {
        continue;
    }

    $blocks = $blocksBySection[$sectionId] ?? [];
    if (empty($blocks)) {
        continue;
    }

    foreach ($blocks as $block) {
        // Skip optional choice-group blocks when user hasn't selected this section
        if ($block['is_optional'] && isset($sectionSelections[$sectionId]) && !$sectionSelections[$sectionId]) {
            continue;
        }

        $rendered = substitute_vars($block['content_template'], $answers);
        $docParts[] = $rendered;
    }
}

$documentHtml = implode("\n", $docParts);

// Build a download-safe filename
$safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $form['form_code'] . '_' . $form['form_title']);
$safeTitle = substr($safeTitle, 0, 60);

// Detect if user wants to download
$download = isset($_GET['download']);

if ($download) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $safeTitle . '.html"');
    // Fall through to output the full document
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['form_code'] . ' — ' . $form['form_title']) ?></title>
    <style type="text/css">
        /* Original form styling — preserved from source forms */
        @page {
            size: 8.5in 11in;
            margin-right: 1.25in;
            margin-top: 1in;
            margin-bottom: 1in;
        }
        body {
            font-family: "Times New Roman", serif;
            font-size: 12pt;
            color: #000000;
            max-width: 7in;
            margin: 0 auto;
            padding: 20px;
            background: white;
            text-align: justify;
        }
        p {
            margin-bottom: 0.17in;
            direction: ltr;
            color: #000000;
            text-align: justify;
            widows: 2;
            orphans: 2;
        }
        p.center {
            text-align: center;
        }
        p.underline-center {
            text-align: center;
            text-decoration: underline;
            margin-bottom: 0.17in;
        }
        p.indented {
            text-indent: 0.3in;
            margin-bottom: 0.17in;
        }
        p.sub-indented {
            margin-left: 0.9in;
            text-indent: -0.3in;
            margin-bottom: 0.17in;
        }
        p.signature-block {
            margin-left: 3in;
            margin-top: 0.33in;
            margin-bottom: 0.17in;
        }
        /* Toolbar (hidden on print) */
        .toolbar {
            font-family: "Times New Roman", serif;
            background: #1a3a5c;
            color: white;
            padding: 12px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .toolbar h2 {
            margin: 0;
            flex: 1;
            font-size: 1em;
            font-weight: normal;
        }
        .toolbar-btn {
            background: white;
            color: #1a3a5c;
            border: none;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
            font-family: "Times New Roman", serif;
            font-size: 0.9em;
            text-decoration: none;
            display: inline-block;
        }
        .toolbar-btn:hover {
            background: #e8e8e8;
        }
        .document-body {
            max-width: 7in;
            margin: 30px auto;
            padding: 1in;
            background: white;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        .form-header {
            text-align: center;
            margin-bottom: 0.5in;
        }
        .form-header h1 {
            font-size: 14pt;
            font-weight: bold;
            font-family: "Times New Roman", serif;
            text-transform: uppercase;
        }
        @media print {
            .toolbar { display: none; }
            .document-body { box-shadow: none; margin: 0; padding: 0; }
            body { background: white; }
        }
    </style>
</head>
<body>
<?php if (!$download): ?>
    <div class="toolbar no-print">
        <h2><?= htmlspecialchars($form['form_code'] . ' — ' . $form['form_title']) ?></h2>
        <button class="toolbar-btn" onclick="window.print()">&#128438; Print</button>
        <a class="toolbar-btn" href="output.php?download=1">&#128229; Download HTML</a>
        <a class="toolbar-btn" href="variables.php">&#9998; Edit Answers</a>
        <a class="toolbar-btn" href="index.php">&#8635; Start Over</a>
    </div>
<?php endif; ?>

    <div class="document-body">
        <div class="form-header">
            <p class="center" style="font-size:12pt;">
                <?= htmlspecialchars($form['form_code']) ?>
                <?= htmlspecialchars($form['form_title']) ?>
            </p>

            <?php
            // Case caption block if plaintiff/defendant/court/cause are provided
            $pName  = $answers['plaintiff_name'] ?? '';
            $dName  = $answers['defendant_name'] ?? '';
            $court  = $answers['court_name']     ?? '';
            $county = $answers['county_name']    ?? '';
            $cause  = $answers['cause_number']   ?? '';

            if ($pName || $dName || $court || $county || $cause):
            ?>
            <table style="width:100%; border-collapse:collapse; margin: 0.3in 0; font-family:'Times New Roman',serif; font-size:12pt;">
                <tr>
                    <td style="width:50%; vertical-align:top; padding-right:0.2in; border-right: 1px solid #000;">
                        <?php if ($pName): ?>
                            <?= htmlspecialchars($pName) ?>,<br>
                            <em style="font-size:10pt;">Plaintiff,</em>
                        <?php endif; ?>
                        <?php if ($pName && $dName): ?><br><br>vs.<br><br><?php endif; ?>
                        <?php if ($dName): ?>
                            <?= htmlspecialchars($dName) ?>,<br>
                            <em style="font-size:10pt;">Defendant.</em>
                        <?php endif; ?>
                    </td>
                    <td style="width:50%; vertical-align:top; padding-left:0.2in; text-align:left;">
                        <?php if ($court): ?>
                            IN THE <?= htmlspecialchars(strtoupper($court)) ?><br><br>
                        <?php endif; ?>
                        <?php if ($county): ?>
                            <?= htmlspecialchars(strtoupper($county)) ?> COUNTY, TEXAS<br><br>
                        <?php endif; ?>
                        <?php if ($cause): ?>
                            CAUSE NO. <?= htmlspecialchars($cause) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php endif; ?>
        </div>

        <!-- Document body from template -->
        <?= $documentHtml ?>

        <?php
        // Signature block if attorney name is set
        $attyName = $answers['attorney_name'] ?? '';
        $barNum   = $answers['bar_number']    ?? '';
        $attyAddr = $answers['address']       ?? '';
        $attyCity = $answers['city_state_zip']?? '';
        $attyPhone= $answers['phone_number']  ?? '';
        $attyFax  = $answers['fax_number']    ?? '';
        $attyEmail= $answers['email_address'] ?? '';

        if ($attyName):
        ?>
        <div style="margin-top:0.5in; font-family:'Times New Roman',serif; font-size:12pt;">
            <p style="border-top:1px solid #000; width:3in; margin-bottom:4px;">&nbsp;</p>
            <p style="margin:0; font-weight:bold;"><?= htmlspecialchars($attyName) ?></p>
            <?php if ($barNum): ?>
                <p style="margin:0;">State Bar No. <?= htmlspecialchars($barNum) ?></p>
            <?php endif; ?>
            <?php if ($attyAddr): ?>
                <p style="margin:0;"><?= htmlspecialchars($attyAddr) ?></p>
            <?php endif; ?>
            <?php if ($attyCity): ?>
                <p style="margin:0;"><?= htmlspecialchars($attyCity) ?></p>
            <?php endif; ?>
            <?php if ($attyPhone): ?>
                <p style="margin:0;">Tel: <?= htmlspecialchars($attyPhone) ?></p>
            <?php endif; ?>
            <?php if ($attyFax): ?>
                <p style="margin:0;">Fax: <?= htmlspecialchars($attyFax) ?></p>
            <?php endif; ?>
            <?php if ($attyEmail): ?>
                <p style="margin:0;"><?= htmlspecialchars($attyEmail) ?></p>
            <?php endif; ?>
            <p style="margin-top:8px;"><strong>ATTORNEY FOR
                <?= strtoupper(htmlspecialchars($form['party_role'] === 'plaintiff' ? 'Plaintiff' : ($form['party_role'] === 'defendant' ? 'Defendant' : 'Movant'))) ?>
            </strong></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
