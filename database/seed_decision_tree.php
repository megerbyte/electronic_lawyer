<?php
/**
 * seed_decision_tree.php
 *
 * PHP CLI script that seeds the decision_tree_nodes and decision_tree_options tables
 * with a complete multi-level decision tree for identifying the correct legal form.
 *
 * Usage:
 *   php database/seed_decision_tree.php
 *
 * Run AFTER seed_forms.php so that form IDs are available.
 */

require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

// ───────────────────────────────────────────────────────────────
// Helper: insert a node and return its ID
// ───────────────────────────────────────────────────────────────
function insert_node(
    \PDO $pdo,
    ?int $parentId,
    string $question,
    string $nodeType = 'question',
    ?int $resultFormId = null,
    ?string $resultFormIds = null,
    int $displayOrder = 0
): int {
    $stmt = $pdo->prepare(
        "INSERT INTO decision_tree_nodes
           (parent_id, question, node_type, result_form_id, result_form_ids, display_order)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$parentId, $question, $nodeType, $resultFormId, $resultFormIds, $displayOrder]);
    return (int)$pdo->lastInsertId();
}

// Helper: insert an option linking two nodes
function insert_option(
    \PDO $pdo,
    int $nodeId,
    string $optionText,
    ?int $nextNodeId,
    int $displayOrder = 0
): int {
    $stmt = $pdo->prepare(
        "INSERT INTO decision_tree_options (node_id, option_text, next_node_id, display_order)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$nodeId, $optionText, $nextNodeId, $displayOrder]);
    return (int)$pdo->lastInsertId();
}

// Helper: look up a form ID by form_code
function form_id(\PDO $pdo, string $code): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM forms WHERE form_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

// ───────────────────────────────────────────────────────────────
// Clear existing tree
// ───────────────────────────────────────────────────────────────
echo "Clearing existing decision tree...\n";
$pdo->exec("DELETE FROM decision_tree_options");
$pdo->exec("DELETE FROM decision_tree_nodes");

// ───────────────────────────────────────────────────────────────
// LEVEL 1 — Root: Role in the case
// ───────────────────────────────────────────────────────────────
echo "Seeding decision tree...\n";

$root = insert_node($pdo, null, 'What is your role in this case?', 'question', null, null, 0);

// ───────────────────────────────────────────────────────────────
// LEVEL 2A — Plaintiff branch
// ───────────────────────────────────────────────────────────────
$plaintiffNode = insert_node($pdo, $root,
    'What type of claim do you have?', 'question', null, null, 0);

insert_option($pdo, $root, 'I am the Plaintiff (filing the lawsuit)', $plaintiffNode, 0);

// ───────────────────────────────────────────────────────────────
// LEVEL 2B — Defendant branch
// ───────────────────────────────────────────────────────────────
$defendantNode = insert_node($pdo, $root,
    'What are you responding to?', 'question', null, null, 1);

insert_option($pdo, $root, 'I am the Defendant (responding to a lawsuit)', $defendantNode, 1);

// ───────────────────────────────────────────────────────────────
// LEVEL 2C — Procedural/Discovery branch
// ───────────────────────────────────────────────────────────────
$proceduralNode = insert_node($pdo, $root,
    'What type of procedural document do you need?', 'question', null, null, 2);

insert_option($pdo, $root, 'I need a procedural/discovery document', $proceduralNode, 2);

// ───────────────────────────────────────────────────────────────
// Plaintiff sub-options (Level 3A)
// ───────────────────────────────────────────────────────────────

// Breach of Contract
$bocFormId = form_id($pdo, '2B-2');
$bocNode   = insert_node($pdo, $plaintiffNode,
    'Breach of Contract Petition',
    'result',
    $bocFormId,
    null, 0
);
insert_option($pdo, $plaintiffNode, 'Breach of Contract', $bocNode, 0);

// Personal Injury - Auto Accident
$autoFormId = form_id($pdo, '2B-5');
$autoNode   = insert_node($pdo, $plaintiffNode,
    'Personal Injury (Auto Accident) Petition',
    'result',
    $autoFormId,
    null, 1
);
insert_option($pdo, $plaintiffNode, 'Personal Injury (Auto Accident)', $autoNode, 1);

// Personal Injury - Slip & Fall
$slipFormId = form_id($pdo, '2B-6');
$slipNode   = insert_node($pdo, $plaintiffNode,
    'Personal Injury (Slip & Fall) Petition',
    'result',
    $slipFormId,
    null, 2
);
insert_option($pdo, $plaintiffNode, 'Personal Injury (Slip & Fall)', $slipNode, 2);

// DTPA Consumer Claim
$dtpaFormId = form_id($pdo, '2B-7');
$dtpaNode   = insert_node($pdo, $plaintiffNode,
    'DTPA Consumer Claim Petition',
    'result',
    $dtpaFormId,
    null, 3
);
insert_option($pdo, $plaintiffNode, 'DTPA Consumer Claim', $dtpaNode, 3);

// Suit on Account
$acctFormId = form_id($pdo, '2B-3');
$acctNode   = insert_node($pdo, $plaintiffNode,
    'Suit on Account Petition',
    'result',
    $acctFormId,
    null, 4
);
insert_option($pdo, $plaintiffNode, 'Suit on Account', $acctNode, 4);

// Declaratory Judgment
$djFormId = form_id($pdo, '2D-1');
$djNode   = insert_node($pdo, $plaintiffNode,
    'Petition for Declaratory Judgment',
    'result',
    $djFormId,
    null, 5
);
insert_option($pdo, $plaintiffNode, 'Declaratory Judgment', $djNode, 5);

// TRO / Injunction
$troFormId = form_id($pdo, '2C-1');
$troNode   = insert_node($pdo, $plaintiffNode,
    'Petition & Application for Temporary Restraining Order',
    'result',
    $troFormId,
    null, 6
);
insert_option($pdo, $plaintiffNode, 'Temporary Restraining Order / Injunction', $troNode, 6);

// Bill of Review
$borFormId = form_id($pdo, '2B-8');
$borNode   = insert_node($pdo, $plaintiffNode,
    'Petition - Bill of Review',
    'result',
    $borFormId,
    null, 7
);
insert_option($pdo, $plaintiffNode, 'Bill of Review', $borNode, 7);

// General Petition (catch-all)
$genFormId = form_id($pdo, '2B-1');
$genNode   = insert_node($pdo, $plaintiffNode,
    'General Petition',
    'result',
    $genFormId,
    null, 8
);
insert_option($pdo, $plaintiffNode, 'General / Other Claim', $genNode, 8);

// ───────────────────────────────────────────────────────────────
// Defendant sub-options (Level 3B)
// ───────────────────────────────────────────────────────────────

// Original Petition → General Answer
$ansFormIds = json_encode(array_filter([
    form_id($pdo, '3E-1'),
    form_id($pdo, '3E-2'),
]));
$ansNode = insert_node($pdo, $defendantNode,
    'General Answer / Response to Original Petition',
    'result',
    form_id($pdo, '3E-1'),
    $ansFormIds, 0
);
insert_option($pdo, $defendantNode, 'Responding to an Original Petition', $ansNode, 0);

// Motion to Transfer Venue
$mtvFormIds = json_encode(array_filter([
    form_id($pdo, '3C-1'),
    form_id($pdo, '3C-2'),
    form_id($pdo, '3C-3'),
    form_id($pdo, '3C-4'),
]));
$mtvNode = insert_node($pdo, $defendantNode,
    'Motion to Transfer Venue',
    'result',
    form_id($pdo, '3C-1'),
    $mtvFormIds, 1
);
insert_option($pdo, $defendantNode, 'Challenging Venue (Motion to Transfer)', $mtvNode, 1);

// Special Appearance
$saFormIds = json_encode(array_filter([
    form_id($pdo, '3B-1'),
    form_id($pdo, '3B-2'),
]));
$saNode = insert_node($pdo, $defendantNode,
    'Special Appearance',
    'result',
    form_id($pdo, '3B-1'),
    $saFormIds, 2
);
insert_option($pdo, $defendantNode, 'Challenging Personal Jurisdiction (Special Appearance)', $saNode, 2);

// Motion to Compel Arbitration
$arbFormId = form_id($pdo, '4C-1');
$arbNode   = insert_node($pdo, $defendantNode,
    'Motion to Compel Arbitration',
    'result',
    $arbFormId,
    null, 3
);
insert_option($pdo, $defendantNode, 'Motion to Compel Arbitration', $arbNode, 3);

// Motion to Abate
$abateFormId = form_id($pdo, '3H-1');
$abateNode   = insert_node($pdo, $defendantNode,
    'Motion to Abate',
    'result',
    $abateFormId,
    null, 4
);
insert_option($pdo, $defendantNode, 'Motion to Abate', $abateNode, 4);

// Motion for Guardian Ad Litem
$galFormId = form_id($pdo, '1I-1');
$galNode   = insert_node($pdo, $defendantNode,
    'Motion for Guardian Ad Litem',
    'result',
    $galFormId,
    null, 5
);
insert_option($pdo, $defendantNode, 'Motion for Guardian Ad Litem', $galNode, 5);

// Motion to Disqualify Counsel
$disqFormId = form_id($pdo, '1H-10');
$disqNode   = insert_node($pdo, $defendantNode,
    'Motion to Disqualify Opposing Counsel',
    'result',
    $disqFormId,
    null, 6
);
insert_option($pdo, $defendantNode, 'Motion to Disqualify Counsel', $disqNode, 6);

// Motion to Dismiss (Indigent)
$dismissFormId = form_id($pdo, '2I-5');
$dismissNode   = insert_node($pdo, $defendantNode,
    'Motion to Dismiss',
    'result',
    $dismissFormId,
    null, 7
);
insert_option($pdo, $defendantNode, 'Motion to Dismiss', $dismissNode, 7);

// Other Motion — ask follow-up
$otherDefNode = insert_node($pdo, $defendantNode,
    'What type of motion are you filing?', 'question', null, null, 8);
insert_option($pdo, $defendantNode, 'Other Motion or Response', $otherDefNode, 8);

// Special Exceptions
$seFormId = form_id($pdo, '3G-1');
$seNode   = insert_node($pdo, $otherDefNode, 'Special Exceptions', 'result', $seFormId, null, 0);
insert_option($pdo, $otherDefNode, 'Special Exceptions to Petition', $seNode, 0);

// FNC Motion
$fncFormId = form_id($pdo, '3D-1');
$fncNode   = insert_node($pdo, $otherDefNode, 'Forum Non Conveniens Motion', 'result', $fncFormId, null, 1);
insert_option($pdo, $otherDefNode, 'Forum Non Conveniens (FNC) Motion', $fncNode, 1);

// Response to Special Appearance
$rsaFormId = form_id($pdo, '3B-3');
$rsaNode   = insert_node($pdo, $otherDefNode, 'Response to Special Appearance', 'result', $rsaFormId, null, 2);
insert_option($pdo, $otherDefNode, 'Response to Special Appearance', $rsaNode, 2);

// Vexatious Litigant Motion
$vexFormId = form_id($pdo, '1B-13');
$vexNode   = insert_node($pdo, $otherDefNode, 'Vexatious Litigant Motion', 'result', $vexFormId, null, 3);
insert_option($pdo, $otherDefNode, 'Vexatious Litigant Motion', $vexNode, 3);

// ───────────────────────────────────────────────────────────────
// Procedural/Discovery sub-options (Level 3C)
// ───────────────────────────────────────────────────────────────

// Certificate of Conference
$cocFormId = form_id($pdo, '1B-11');
$cocNode   = insert_node($pdo, $proceduralNode,
    'Certificate of Conference',
    'result',
    $cocFormId,
    null, 0
);
insert_option($pdo, $proceduralNode, 'Certificate of Conference', $cocNode, 0);

// Motion to Refer to ADR
$adrFormId = form_id($pdo, '4A-1');
$adrNode   = insert_node($pdo, $proceduralNode,
    'Motion to Refer to ADR / Mediation',
    'result',
    $adrFormId,
    null, 1
);
insert_option($pdo, $proceduralNode, 'Motion to Refer to ADR / Mediation', $adrNode, 1);

// Subpoena
$subFormId = form_id($pdo, '1K-1');
$subNode   = insert_node($pdo, $proceduralNode,
    'Subpoena',
    'result',
    $subFormId,
    null, 2
);
insert_option($pdo, $proceduralNode, 'Subpoena', $subNode, 2);

// Request for Production (Discovery)
$rfpFormId = form_id($pdo, '6H-1');
$rfpNode   = insert_node($pdo, $proceduralNode,
    'Request for Production',
    'result',
    $rfpFormId,
    null, 3
);
insert_option($pdo, $proceduralNode, 'Request for Production / Discovery', $rfpNode, 3);

// Motion for Continuance
$contFormId = form_id($pdo, '5D-1');
$contNode   = insert_node($pdo, $proceduralNode,
    'Motion for Continuance',
    'result',
    $contFormId,
    null, 4
);
insert_option($pdo, $proceduralNode, 'Motion for Continuance', $contNode, 4);

// Motion in Limine
$limFormId = form_id($pdo, '5C-1');
$limNode   = insert_node($pdo, $proceduralNode,
    'Motion in Limine',
    'result',
    $limFormId,
    null, 5
);
insert_option($pdo, $proceduralNode, 'Motion in Limine', $limNode, 5);

// Motion to Recuse Judge
$recuseFormId = form_id($pdo, '5C-2');
$recuseNode   = insert_node($pdo, $proceduralNode,
    'Motion to Recuse Judge',
    'result',
    $recuseFormId,
    null, 6
);
insert_option($pdo, $proceduralNode, 'Motion to Recuse Judge', $recuseNode, 6);

// Attorney Fees Notice
$afNoticeFormId = form_id($pdo, '2A-1');
$afNoticeNode   = insert_node($pdo, $proceduralNode,
    'Notice - Attorney Fees',
    'result',
    $afNoticeFormId,
    null, 7
);
insert_option($pdo, $proceduralNode, 'Notice of Attorney Fees', $afNoticeNode, 7);

// Pro Hac Vice
$phvFormId = form_id($pdo, '1H-2');
$phvNode   = insert_node($pdo, $proceduralNode,
    'Pro Hac Vice Motion',
    'result',
    $phvFormId,
    null, 8
);
insert_option($pdo, $proceduralNode, 'Pro Hac Vice (Nonresident Attorney Motion)', $phvNode, 8);

// Count summary
$nodeCount = $pdo->query("SELECT COUNT(*) FROM decision_tree_nodes")->fetchColumn();
$optCount  = $pdo->query("SELECT COUNT(*) FROM decision_tree_options")->fetchColumn();

echo "========================================\n";
echo "Done. Inserted $nodeCount nodes and $optCount options.\n";
