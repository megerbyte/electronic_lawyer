<?php
/**
 * questionnaire/intake.php
 *
 * Decision-tree questionnaire that walks the user through a series of
 * questions to determine which legal form they need.
 *
 * Query parameters:
 *   ?node=<id>   — the decision tree node to display (default: root)
 *
 * POST parameters (on answer submission):
 *   option_id    — the selected decision_tree_options.id
 *   node_id      — the current node ID (for CSRF-like validation)
 */

session_start();

require_once __DIR__ . '/../includes/db.php';

// Ensure we have a valid case session
if (empty($_SESSION['case_id'])) {
    header('Location: index.php');
    exit;
}

$pdo    = getDB();
$caseId = (int)$_SESSION['case_id'];

// ───────────────────────────────────────────────────────────────
// Handle POST — user selected an option
// ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $optionId  = (int)($_POST['option_id']  ?? 0);
    $curNodeId = (int)($_POST['node_id']    ?? 0);

    if ($optionId > 0) {
        // Load the selected option
        $stmt = $pdo->prepare(
            "SELECT dto.*, dtn.node_type, dtn.result_form_id, dtn.result_form_ids, dtn.question
               FROM decision_tree_options dto
               JOIN decision_tree_nodes dtn ON dtn.id = dto.next_node_id
              WHERE dto.id = ? AND dto.node_id = ?"
        );
        $stmt->execute([$optionId, $curNodeId]);
        $option = $stmt->fetch();

        if ($option) {
            // Save to breadcrumb
            if (!isset($_SESSION['breadcrumb'])) {
                $_SESSION['breadcrumb'] = [];
            }

            // Get the question text for this node
            $qStmt = $pdo->prepare("SELECT question FROM decision_tree_nodes WHERE id = ?");
            $qStmt->execute([$curNodeId]);
            $questionText = $qStmt->fetchColumn();

            $_SESSION['breadcrumb'][] = [
                'question' => $questionText,
                'answer'   => $option['option_text'],
                'node_id'  => $curNodeId,
            ];

            if ($option['node_type'] === 'result') {
                // Determine form ID(s)
                $formId = $option['result_form_id'] ? (int)$option['result_form_id'] : null;

                // Update the user_cases record
                $upd = $pdo->prepare(
                    "UPDATE user_cases SET form_id = ?, status = 'variables' WHERE id = ?"
                );
                $upd->execute([$formId, $caseId]);

                $_SESSION['result_form_ids'] = $option['result_form_ids']
                    ? json_decode($option['result_form_ids'], true)
                    : ($formId ? [$formId] : []);

                header('Location: variables.php');
                exit;
            }

            // Continue to next question node
            header('Location: intake.php?node=' . (int)$option['next_node_id']);
            exit;
        }
    }

    // Invalid option — restart
    header('Location: intake.php');
    exit;
}

// ───────────────────────────────────────────────────────────────
// GET — display the current node
// ───────────────────────────────────────────────────────────────

// Find the root node if no node specified
$nodeId = isset($_GET['node']) ? (int)$_GET['node'] : 0;

if ($nodeId === 0) {
    $stmt  = $pdo->prepare("SELECT id FROM decision_tree_nodes WHERE parent_id IS NULL LIMIT 1");
    $stmt->execute();
    $nodeId = (int)($stmt->fetchColumn() ?: 0);
}

if ($nodeId === 0) {
    die('Decision tree not yet seeded. Please run: php database/seed_decision_tree.php');
}

// Load the node
$stmt = $pdo->prepare("SELECT * FROM decision_tree_nodes WHERE id = ?");
$stmt->execute([$nodeId]);
$node = $stmt->fetch();

if (!$node) {
    header('Location: intake.php');
    exit;
}

// If it's a result node hit directly, redirect to variables
if ($node['node_type'] === 'result') {
    $upd = $pdo->prepare("UPDATE user_cases SET form_id = ?, status = 'variables' WHERE id = ?");
    $upd->execute([$node['result_form_id'], $caseId]);
    header('Location: variables.php');
    exit;
}

// Load options for this node
$optStmt = $pdo->prepare(
    "SELECT * FROM decision_tree_options WHERE node_id = ? ORDER BY display_order ASC"
);
$optStmt->execute([$nodeId]);
$options = $optStmt->fetchAll();

$breadcrumb = $_SESSION['breadcrumb'] ?? [];
$stepNumber = count($breadcrumb) + 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal Document Intake — Step <?= $stepNumber ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="card">
        <h1>Legal Document Questionnaire</h1>

        <?php if (!empty($breadcrumb)): ?>
        <div class="progress">
            <strong>Progress:</strong>
            <?php foreach ($breadcrumb as $i => $crumb): ?>
                <?= htmlspecialchars($crumb['question']) ?>
                → <em><?= htmlspecialchars($crumb['answer']) ?></em>
                <?= ($i < count($breadcrumb) - 1) ? ' &rsaquo; ' : '' ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p class="question">
            <strong>Step <?= $stepNumber ?>:</strong>
            <?= htmlspecialchars($node['question']) ?>
        </p>

        <form method="POST" action="intake.php">
            <input type="hidden" name="node_id" value="<?= (int)$nodeId ?>">

            <?php foreach ($options as $opt): ?>
            <label class="option">
                <input type="radio" name="option_id" value="<?= (int)$opt['id'] ?>" required>
                <?= htmlspecialchars($opt['option_text']) ?>
            </label>
            <?php endforeach; ?>

            <?php if (empty($options)): ?>
            <p style="color:red;">No options available for this question. The decision tree may be incomplete.</p>
            <?php else: ?>
            <br>
            <button type="submit" class="btn">Continue &rarr;</button>
            <?php endif; ?>
        </form>

        <?php if (!empty($breadcrumb)): ?>
        <br>
        <form method="GET" action="intake.php" style="display:inline;">
            <?php
            // Go back to previous node
            $prevCrumb = end($breadcrumb);
            ?>
            <input type="hidden" name="node" value="<?= (int)$prevCrumb['node_id'] ?>">
            <button type="submit"
                class="btn btn-secondary"
                onclick="<?= htmlspecialchars("history.go(-1); return false;") ?>">
                &larr; Back
            </button>
        </form>
        <?php endif; ?>

        <form method="GET" action="index.php" style="display:inline; margin-left:10px;">
            <button type="submit" class="btn btn-secondary">Start Over</button>
        </form>
    </div>
</body>
</html>
