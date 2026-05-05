<?php
/**
 * seed_forms.php
 *
 * PHP CLI script that reads all HTML forms from the megerbyte/forms GitHub repository,
 * parses their structure (title, sections, paragraphs, placeholder variables),
 * and populates the forms, form_sections, form_content_blocks, form_variables,
 * and block_variables tables.
 *
 * Usage:
 *   php database/seed_forms.php
 *
 * Options:
 *   --dry-run   Parse forms without writing to database
 *   --verbose   Print detailed parsing output
 */

define('FORMS_REPO_BASE', 'https://raw.githubusercontent.com/megerbyte/forms/main/');
define('FORMS_API_URL', 'https://api.github.com/repos/megerbyte/forms/contents/');

// Instruction-only placeholder patterns to skip (not real user variables)
$SKIP_PATTERNS = [
    "/See\s+O'Connor/i",
    '/CHOOSE APPROPRIATE/i',
    '/ADD APPROPRIATE/i',
    '/ADD PARAGRAPHS/i',
    '/ADD SECTIONS/i',
    '/CERTIFICATE VERSION/i',
    '/For certificate version/i',
    '/For plaintiff designation/i',
    '/For defendant designation/i',
    '/For jurisdiction/i',
    '/For venue/i',
    '/STYLE OF THE CASE/i',
    '/see FORM/i',
    '/see FORMS/i',
    '/O\'Connor\'s/i',
    '/ch\.\s*\d/i',
    '/§\s*\d/i',
    '/p\.\s*\d/i',
    '/TRCP\s/i',
    '/Tex\.\s*R\./i',
];

// Variable normalization map - common phrases to canonical names
$VARIABLE_MAP = [
    'name of plaintiff'                        => 'plaintiff_name',
    'plaintiff'                                => 'plaintiff_name',
    'name of defendant'                        => 'defendant_name',
    'defendant'                                => 'defendant_name',
    'date'                                     => 'event_date',
    'identify location'                        => 'event_location',
    '_______'                                  => 'county_name',
    'county'                                   => 'county_name',
    'name of attorney'                         => 'attorney_name',
    'name of opposing counsel'                 => 'opposing_counsel_name',
    'opposing counsel'                         => 'opposing_counsel_name',
    'give reasons for inability to confer'     => 'inability_to_confer_reason',
    'state the reason'                         => 'conference_failure_reason',
    'describe events that resulted in lawsuit' => 'facts_description',
    'explain'                                  => 'explanation',
    'elaborate'                                => 'elaboration',
    'amount'                                   => 'damages_amount',
    'specify motion or other matter in dispute'=> 'motion_subject',
    'cause number'                             => 'cause_number',
    'court'                                    => 'court_name',
    'name of court'                            => 'court_name',
    'state details of emergency and harm'      => 'emergency_details',
    'identify dates, times, methods of contacts, and results' => 'contact_attempts',
    'identify dates, times, methods of contact, and results'  => 'contact_attempts',
    'name of first cause of action'            => 'first_cause_of_action',
    'name of another cause of action'          => 'second_cause_of_action',
    'identify another cause of action'         => 'additional_cause_of_action',
    'identify equitable relief sought'         => 'equitable_relief',
    'state facts supporting equitable relief'  => 'equitable_relief_facts',
    'identify code, statute, or contract permitting recovery of attorney fees' => 'attorney_fees_authority',
    'the alternative/addition'                 => 'alternative_or_addition',
    'in separately numbered paragraphs, identify elements and facts supporting a cause of action' => 'cause_of_action_elements',
    'in separately numbered paragraphs, identify elements and facts supporting the cause of action' => 'cause_of_action_elements',
    'state other relevant facts in separately numbered paragraphs' => 'additional_facts',
    '2/3'                                      => 'discovery_level',
    '190.3/190.4'                              => 'discovery_rule',
    'name'                                     => 'full_name',
    'address'                                  => 'address',
    'city, state, zip'                         => 'city_state_zip',
    'phone'                                    => 'phone_number',
    'bar number'                               => 'bar_number',
    'state bar number'                         => 'bar_number',
    'fax'                                      => 'fax_number',
    'email'                                    => 'email_address',
];

// Display labels for known variables
$VARIABLE_LABELS = [
    'plaintiff_name'             => 'Plaintiff Name',
    'defendant_name'             => 'Defendant Name',
    'event_date'                 => 'Date of Events',
    'event_location'             => 'Location of Events',
    'county_name'                => 'County',
    'attorney_name'              => 'Attorney Name',
    'opposing_counsel_name'      => 'Opposing Counsel Name',
    'inability_to_confer_reason' => 'Reason Unable to Confer',
    'conference_failure_reason'  => 'Reason Conference Failed',
    'facts_description'          => 'Description of Events / Facts',
    'explanation'                => 'Explanation',
    'elaboration'                => 'Elaboration',
    'damages_amount'             => 'Damages Amount ($)',
    'motion_subject'             => 'Subject of Motion / Dispute',
    'cause_number'               => 'Cause Number',
    'court_name'                 => 'Court Name',
    'emergency_details'          => 'Emergency Details',
    'contact_attempts'           => 'Contact Attempts (dates, times, methods)',
    'first_cause_of_action'      => 'First Cause of Action',
    'second_cause_of_action'     => 'Second Cause of Action',
    'additional_cause_of_action' => 'Additional Cause of Action',
    'equitable_relief'           => 'Equitable Relief Sought',
    'equitable_relief_facts'     => 'Facts Supporting Equitable Relief',
    'attorney_fees_authority'    => 'Authority for Attorney Fees',
    'alternative_or_addition'    => 'Alternative or Addition',
    'cause_of_action_elements'   => 'Elements and Facts of Cause of Action',
    'additional_facts'           => 'Additional Facts',
    'discovery_level'            => 'Discovery Level (2 or 3)',
    'discovery_rule'             => 'Discovery Rule (190.3 or 190.4)',
    'full_name'                  => 'Full Name',
    'address'                    => 'Street Address',
    'city_state_zip'             => 'City, State, ZIP',
    'phone_number'               => 'Phone Number',
    'bar_number'                 => 'State Bar Number',
    'fax_number'                 => 'Fax Number',
    'email_address'              => 'Email Address',
];

// Input types for known variables
$VARIABLE_INPUT_TYPES = [
    'event_date'                 => 'date',
    'facts_description'          => 'textarea',
    'explanation'                => 'textarea',
    'elaboration'                => 'textarea',
    'inability_to_confer_reason' => 'textarea',
    'conference_failure_reason'  => 'textarea',
    'emergency_details'          => 'textarea',
    'contact_attempts'           => 'textarea',
    'cause_of_action_elements'   => 'textarea',
    'additional_facts'           => 'textarea',
    'equitable_relief_facts'     => 'textarea',
    'discovery_level'            => 'select',
    'alternative_or_addition'    => 'select',
];

// Hints for variables
$VARIABLE_HINTS = [
    'plaintiff_name'             => 'Full legal name of the plaintiff (e.g., "John Smith" or "ABC Corp.")',
    'defendant_name'             => 'Full legal name of the defendant',
    'event_date'                 => 'Date the relevant events occurred',
    'event_location'             => 'Specific address or description of the location',
    'county_name'                => 'Texas county where the case is filed',
    'attorney_name'              => 'Full name of the filing attorney',
    'opposing_counsel_name'      => 'Full name of opposing counsel',
    'cause_number'               => 'Assigned cause/case number (leave blank if not yet assigned)',
    'court_name'                 => 'Name of the court (e.g., "123rd District Court")',
    'damages_amount'             => 'Dollar amount of damages claimed (numbers only)',
    'bar_number'                 => '6-digit Texas State Bar number',
    'discovery_level'            => 'Discovery control plan level (2 or 3)',
];

// Form type classification by form code prefix
$FORM_TYPE_MAP = [
    '1B' => 'other',
    '1H' => 'motion',
    '1I' => 'motion',
    '1J' => 'other',
    '1K' => 'notice',
    '2A' => 'notice',
    '2B' => 'petition',
    '2C' => 'petition',
    '2D' => 'petition',
    '2I' => 'motion',
    '3B' => 'motion',
    '3C' => 'motion',
    '3D' => 'motion',
    '3E' => 'answer',
    '3F' => 'motion',
    '3G' => 'motion',
    '3H' => 'motion',
    '4A' => 'motion',
    '4C' => 'motion',
    '5C' => 'motion',
    '5D' => 'motion',
    '6H' => 'other',
    '6I' => 'other',
    '6J' => 'other',
];

// Party role classification
$PARTY_ROLE_MAP = [
    '2B' => 'plaintiff',
    '2C' => 'plaintiff',
    '2D' => 'plaintiff',
    '2A' => 'plaintiff',
    '3B' => 'defendant',
    '3C' => 'either',
    '3E' => 'defendant',
    '3F' => 'defendant',
    '3G' => 'defendant',
    '3H' => 'defendant',
    '1B' => 'either',
    '1H' => 'either',
    '1I' => 'either',
    '1K' => 'either',
    '4A' => 'either',
    '4C' => 'either',
    '5C' => 'either',
    '5D' => 'either',
    '6H' => 'either',
    '6I' => 'either',
    '6J' => 'either',
];

$isDryRun = in_array('--dry-run', $argv ?? []);
$isVerbose = in_array('--verbose', $argv ?? []);

function log_msg(string $msg, bool $verbose = false): void
{
    global $isVerbose;
    if (!$verbose || $isVerbose) {
        echo $msg . PHP_EOL;
    }
}

/**
 * Fetch a URL via cURL and return the body as a string.
 */
function fetch_url(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'electronic-lawyer-seeder/1.0',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300) {
        return null;
    }
    return $body;
}

/**
 * List all HTML filenames from the megerbyte/forms GitHub repo.
 *
 * @return string[]
 */
function list_form_files(): array
{
    log_msg('Fetching form list from GitHub API...');
    $json = fetch_url(FORMS_API_URL);
    if (!$json) {
        die("ERROR: Could not fetch form list from GitHub API.\n");
    }
    $items = json_decode($json, true);
    if (!is_array($items)) {
        die("ERROR: Invalid response from GitHub API.\n");
    }
    $files = [];
    foreach ($items as $item) {
        if (isset($item['name']) && str_ends_with($item['name'], '.html')) {
            $files[] = $item['name'];
        }
    }
    sort($files);
    log_msg('Found ' . count($files) . ' HTML form files.');
    return $files;
}

/**
 * Extract the form code and title from the HTML title tag.
 * E.g. "Form 2B:1 Petition - General Form" → ['2B-1', 'Petition - General Form']
 */
function parse_form_title(string $html): array
{
    $code  = 'UNKNOWN';
    $title = 'Unknown Form';

    if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
        $raw = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // e.g. "Form 2B:1 Petition - General Form"
        if (preg_match('/Form\s+(\d+[A-Z](?:[:\-]\d+)?(?:[:\-]\d+)?)\s+(.*)/i', $raw, $parts)) {
            $code  = str_replace(':', '-', trim($parts[1]));
            $title = trim($parts[2]);
        }
    }

    return [$code, $title];
}

/**
 * Determine form category from code (e.g., '2B-1' → '2B').
 */
function get_form_category(string $code): string
{
    if (preg_match('/^(\d+[A-Z])/i', $code, $m)) {
        return strtoupper($m[1]);
    }
    return 'OTHER';
}

/**
 * Determine if a placeholder text should be treated as a real user variable.
 */
function is_real_variable(string $placeholder): bool
{
    global $SKIP_PATTERNS;
    foreach ($SKIP_PATTERNS as $pattern) {
        if (preg_match($pattern, $placeholder)) {
            return false;
        }
    }
    return true;
}

/**
 * Normalize a raw placeholder string to a snake_case variable name.
 * e.g. "name of plaintiff" → "plaintiff_name"
 */
function normalize_variable_name(string $raw): string
{
    global $VARIABLE_MAP;

    $lower = strtolower(trim($raw));

    // Direct lookup
    if (isset($VARIABLE_MAP[$lower])) {
        return $VARIABLE_MAP[$lower];
    }

    // Partial match
    foreach ($VARIABLE_MAP as $pattern => $name) {
        if (str_contains($lower, $pattern)) {
            return $name;
        }
    }

    // Generic normalization: strip special chars, convert to snake_case
    $clean = preg_replace('/[^a-z0-9\s]/', '', $lower);
    $clean = preg_replace('/\s+/', '_', trim($clean));
    $clean = preg_replace('/_+/', '_', $clean);

    // Truncate to 100 chars max
    return substr($clean, 0, 100);
}

/**
 * Parse all sections and content blocks from an HTML form.
 *
 * Returns:
 *   [
 *     ['letter' => 'A', 'title' => 'Discovery Control Plan', 'order' => 1, 'blocks' => [
 *       ['paragraph_number' => 1, 'content_template' => '...', 'is_optional' => false, 'choice_group' => null],
 *       ...
 *     ]],
 *     ...
 *   ]
 */
function parse_form_html(string $html): array
{
    // Strip the form title header paragraph (first c1 paragraph with "Form X:")
    // Work through paragraphs in order

    $sections = [];
    $currentSection = null;
    $blockOrder     = 0;
    $sectionOrder   = 0;
    $paragraphNum   = 0;
    $choiceGroup    = null;
    $choiceCounter  = 0;

    // Extract all <p> tags with their class and content
    preg_match_all('/<p\s+class="[^"]*"[^>]*>(.*?)<\/p>/si', $html, $matches, PREG_SET_ORDER);

    $firstPara = true;

    foreach ($matches as $match) {
        $paraHtml = $match[0];
        $inner    = $match[1];

        // Determine CSS class
        preg_match('/class="([^"]+)"/', $paraHtml, $classMatch);
        $classes = isset($classMatch[1]) ? explode(' ', $classMatch[1]) : [];

        // Decode HTML entities in inner text for analysis
        $innerText = trim(strip_tags($inner));
        $innerText = html_entity_decode($innerText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Skip empty paragraphs
        if ($innerText === '' || $innerText === "\u{00A0}") {
            continue;
        }

        // Skip the first title paragraph ("Form 2B:1 ...")
        if ($firstPara) {
            $firstPara = false;
            if (preg_match('/^Form\s+\d+/i', $innerText)) {
                continue;
            }
        }

        $isCenter    = in_array('c1', $classes) || in_array('c2', $classes);
        $isUnderline = in_array('c3', $classes);
        $isIndented  = in_array('c5', $classes) || in_array('c4', $classes) || in_array('c8', $classes);

        // Section header: underlined center (c3)
        if ($isUnderline) {
            // Extract letter and title: "A. Discovery Control Plan" or just "Discovery Control Plan"
            $letter = null;
            $sTitle = $innerText;
            if (preg_match('/^([A-Z])\.\s+(.+)/', $innerText, $hm)) {
                $letter = $hm[1];
                $sTitle = $hm[2];
            }

            $sectionOrder++;
            $currentSection = [
                'letter'      => $letter,
                'title'       => $sTitle,
                'order'       => $sectionOrder,
                'is_optional' => false,
                'blocks'      => [],
            ];
            $sections[]   = &$currentSection;
            $blockOrder   = 0;
            $choiceGroup  = null;
            continue;
        }

        // Detect "CHOOSE APPROPRIATE" or "ADD APPROPRIATE" instruction lines (centered)
        if ($isCenter && preg_match('/\{[^}]+\}/i', $innerText)) {
            $choiceCounter++;
            $choiceGroup = 'choice_' . $choiceCounter;
            continue;
        }

        // If no section yet, create a default one
        if ($currentSection === null) {
            $sectionOrder++;
            $currentSection = [
                'letter'      => null,
                'title'       => 'Preamble',
                'order'       => $sectionOrder,
                'is_optional' => false,
                'blocks'      => [],
            ];
            $sections[] = &$currentSection;
            $blockOrder = 0;
        }

        // Extract paragraph number if present
        $paraNum = null;
        if (preg_match('/^\s*(\d+)\.\s/', $innerText, $nm)) {
            $paraNum = (int)$nm[1];
            $paragraphNum = $paraNum;
        }

        // Detect optional blocks (those introduced by "ADD ... IF APPLICABLE" or choice groups)
        $isOptional = ($choiceGroup !== null);

        // Build a normalized content template: replace {placeholder} with {{variable_name}}
        $template = build_content_template($paraHtml, $innerText);

        $blockOrder++;
        $block = [
            'paragraph_number' => $paraNum,
            'content_template' => $template,
            'is_optional'      => $isOptional,
            'choice_group'     => $choiceGroup,
            'order'            => $blockOrder,
            'raw_html'         => $paraHtml,
        ];

        $currentSection['blocks'][] = $block;

        // Reset choice group after using it for one block (some apply to next group)
        // Actually, keep it for the current run of alternatives
    }

    // Remove reference
    unset($currentSection);

    return $sections;
}

/**
 * Build a content template string from the HTML, converting {placeholder}
 * to {{variable_name}} tokens for real variables.
 */
function build_content_template(string $paraHtml, string $innerText): string
{
    // Preserve the raw HTML but replace {placeholder} with {{var_name}} for real vars
    $template = $paraHtml;

    preg_match_all('/\{([^}]+)\}/u', $innerText, $vars, PREG_SET_ORDER);
    foreach ($vars as $varMatch) {
        $rawPlaceholder = $varMatch[0]; // e.g., {name of plaintiff}
        $rawContent     = $varMatch[1]; // e.g., name of plaintiff

        if (!is_real_variable($rawContent)) {
            continue;
        }

        $varName = normalize_variable_name($rawContent);
        if ($varName === '') {
            continue;
        }

        // Replace in template (escape regex special chars in the placeholder)
        $escaped  = preg_quote($rawPlaceholder, '/');
        $template = preg_replace('/' . $escaped . '/u', '{{' . $varName . '}}', $template, 1);
    }

    return $template;
}

/**
 * Extract all variables from a content template string.
 * Returns array of ['var_name' => ..., 'placeholder' => ...]
 */
function extract_variables_from_template(string $template, string $rawHtml): array
{
    $vars = [];

    // Find {{var_name}} tokens in template
    preg_match_all('/\{\{([^}]+)\}\}/', $template, $tmplMatches, PREG_SET_ORDER);

    // Also find original {placeholder} in raw HTML to get the display text
    preg_match_all('/\{([^}]+)\}/u', strip_tags($rawHtml), $rawMatches, PREG_SET_ORDER);

    // Build a map of var_name → original placeholder
    $rawMap = [];
    foreach ($rawMatches as $rm) {
        $rawContent = $rm[1];
        if (!is_real_variable($rawContent)) {
            continue;
        }
        $vn = normalize_variable_name($rawContent);
        if (!isset($rawMap[$vn])) {
            $rawMap[$vn] = trim($rm[0]); // keep first occurrence
        }
    }

    foreach ($tmplMatches as $tm) {
        $varName = $tm[1];
        $vars[]  = [
            'var_name'    => $varName,
            'placeholder' => $rawMap[$varName] ?? '{' . $varName . '}',
        ];
    }

    return $vars;
}

// ───────────────────────────────────────────────────────────────
// Main seeding logic
// ───────────────────────────────────────────────────────────────

if (!$isDryRun) {
    require_once __DIR__ . '/../includes/db.php';
    $pdo = getDB();
}

$formFiles = list_form_files();
$totalForms = 0;

// Cache of variable_name → DB id
$variableCache = [];

foreach ($formFiles as $filename) {
    log_msg("\nProcessing: $filename");

    $html = fetch_url(FORMS_REPO_BASE . rawurlencode($filename));
    if (!$html) {
        log_msg("  WARN: Could not fetch $filename, skipping.", false);
        continue;
    }

    [$formCode, $formTitle] = parse_form_title($html);
    $formCategory = get_form_category($formCode);

    $formType   = $FORM_TYPE_MAP[$formCategory]   ?? 'other';
    $partyRole  = $PARTY_ROLE_MAP[$formCategory]  ?? 'either';

    // Classify "response" forms
    if (stripos($formTitle, 'response') !== false || stripos($formTitle, 'reply') !== false) {
        $formType  = 'response';
        $partyRole = 'defendant';
    }
    if (stripos($formTitle, 'answer') !== false) {
        $formType  = 'answer';
        $partyRole = 'defendant';
    }
    if (stripos($formTitle, 'order') !== false) {
        $formType  = 'order';
        $partyRole = 'court';
    }
    if (stripos($formTitle, 'notice') !== false) {
        $formType  = 'notice';
        $partyRole = 'plaintiff';
    }

    log_msg("  Code: $formCode | Title: $formTitle | Category: $formCategory | Type: $formType", true);

    $sections = parse_form_html($html);
    log_msg("  Found " . count($sections) . " sections.", true);

    if ($isDryRun) {
        foreach ($sections as $section) {
            log_msg("    Section [{$section['letter']}] {$section['title']} — " . count($section['blocks']) . " blocks", true);
        }
        $totalForms++;
        continue;
    }

    // Insert form
    $stmt = $pdo->prepare(
        "INSERT INTO forms (form_code, form_title, form_category, form_filename, form_type, party_role)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE form_title=VALUES(form_title), form_category=VALUES(form_category),
           form_filename=VALUES(form_filename), form_type=VALUES(form_type), party_role=VALUES(party_role)"
    );
    $stmt->execute([$formCode, $formTitle, $formCategory, $filename, $formType, $partyRole]);
    $formId = (int)$pdo->lastInsertId();
    if ($formId === 0) {
        // Fetch existing ID
        $s2 = $pdo->prepare("SELECT id FROM forms WHERE form_code = ?");
        $s2->execute([$formCode]);
        $formId = (int)($s2->fetchColumn() ?: 0);
    }

    foreach ($sections as $section) {
        // Insert section
        $stmtSec = $pdo->prepare(
            "INSERT INTO form_sections (form_id, section_letter, section_title, section_order, is_optional)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmtSec->execute([
            $formId,
            $section['letter'],
            $section['title'],
            $section['order'],
            $section['is_optional'] ? 1 : 0,
        ]);
        $sectionId = (int)$pdo->lastInsertId();

        foreach ($section['blocks'] as $block) {
            // Insert content block
            $stmtBlock = $pdo->prepare(
                "INSERT INTO form_content_blocks
                   (section_id, block_order, paragraph_number, content_template, is_optional, choice_group)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmtBlock->execute([
                $sectionId,
                $block['order'],
                $block['paragraph_number'],
                $block['content_template'],
                $block['is_optional'] ? 1 : 0,
                $block['choice_group'],
            ]);
            $blockId = (int)$pdo->lastInsertId();

            // Extract and insert variables
            $blockVars = extract_variables_from_template(
                $block['content_template'],
                $block['raw_html']
            );

            foreach ($blockVars as $bv) {
                $varName = $bv['var_name'];
                $placeholder = $bv['placeholder'];

                // Upsert variable
                if (!isset($variableCache[$varName])) {
                    $displayLabel = $VARIABLE_LABELS[$varName]
                        ?? ucwords(str_replace('_', ' ', $varName));
                    $inputType    = $VARIABLE_INPUT_TYPES[$varName] ?? 'text';
                    $hint         = $VARIABLE_HINTS[$varName] ?? null;

                    $stmtVar = $pdo->prepare(
                        "INSERT INTO form_variables (variable_name, display_label, input_type, hint)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE display_label=VALUES(display_label)"
                    );
                    $stmtVar->execute([$varName, $displayLabel, $inputType, $hint]);
                    $varId = (int)$pdo->lastInsertId();
                    if ($varId === 0) {
                        $sv2 = $pdo->prepare("SELECT id FROM form_variables WHERE variable_name = ?");
                        $sv2->execute([$varName]);
                        $varId = (int)($sv2->fetchColumn() ?: 0);
                    }
                    $variableCache[$varName] = $varId;
                }

                $varId = $variableCache[$varName];

                // Insert block_variables mapping
                $stmtBV = $pdo->prepare(
                    "INSERT IGNORE INTO block_variables (block_id, variable_id, placeholder_text)
                     VALUES (?, ?, ?)"
                );
                $stmtBV->execute([$blockId, $varId, $placeholder]);

                log_msg("      Var: $varName ($placeholder)", true);
            }
        }
    }

    $totalForms++;
    log_msg("  ✓ Inserted form ID $formId with " . count($sections) . " sections.");
}

log_msg("\n========================================");
log_msg("Done. Processed $totalForms forms.");
if ($isDryRun) {
    log_msg("(DRY RUN - no database changes made)");
}
