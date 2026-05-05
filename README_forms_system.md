# Electronic Lawyer — Legal Document Generation System

A complete PHP/MySQL system for generating Texas civil litigation documents using
the O'Connor's Texas Rules forms from [megerbyte/forms](https://github.com/megerbyte/forms).

---

## Overview

This system:
1. Stores all 85+ Texas civil litigation forms in a MySQL database (parsed from the `megerbyte/forms` repository)
2. Presents a **decision-tree questionnaire** to determine which form the user needs
3. Presents a **variable questionnaire** to collect user-specific information
4. Generates a **completed output document** styled like the original forms, ready to print or download

---

## Directory Structure

```
/
├── database/
│   ├── schema.sql              — MySQL schema (run first)
│   ├── seed_forms.php          — CLI: parses forms from GitHub and seeds the DB
│   └── seed_decision_tree.php  — CLI: seeds the intake decision tree
├── includes/
│   ├── db.php                  — PDO database connection helper
│   └── config.php              — Application configuration (DB credentials, etc.)
├── questionnaire/
│   ├── index.php               — Entry point / session initializer
│   ├── intake.php              — Decision-tree questionnaire
│   ├── variables.php           — Variable collection questionnaire
│   ├── output.php              — Completed document renderer
│   └── styles.css              — Shared stylesheet
└── README_forms_system.md      — This file
```

---

## Setup

### 1. Database

Create the MySQL database and tables:

```bash
mysql -u root -p < database/schema.sql
```

Or create the database manually and import the schema:

```sql
CREATE DATABASE electronic_lawyer CHARACTER SET utf8mb4;
```

### 2. Configure Database Connection

Edit `includes/db.php` (or `includes/config.php`) and set your credentials:

```php
define('EL_DB_HOST', 'localhost');
define('EL_DB_NAME', 'electronic_lawyer');
define('EL_DB_USER', 'your_db_user');
define('EL_DB_PASS', 'your_db_password');
```

> **Note:** The `includes/db.php` file reads from `includes/config.php`. You can override
> the `EL_DB_*` constants in `config.php` or define them in your server environment.

### 3. Seed the Forms

Run the form seeder to fetch all 85+ HTML forms from `megerbyte/forms` and parse them:

```bash
php database/seed_forms.php
```

Options:
- `--dry-run` — Parse forms without writing to the database (useful for testing)
- `--verbose` — Print detailed output about each form, section, and variable found

### 4. Seed the Decision Tree

After seeding the forms, run the decision tree seeder:

```bash
php database/seed_decision_tree.php
```

This populates the intake questionnaire with a multi-level decision tree that guides users
from their role (Plaintiff / Defendant / Procedural) down to the specific form they need.

---

## Usage

Navigate to the questionnaire entry point in your browser:

```
https://your-domain.com/questionnaire/
```

The flow is:

1. **`/questionnaire/`** — Starts or resumes a session, creates a `user_cases` record
2. **`/questionnaire/intake.php`** — Decision-tree questionnaire (role → claim type → form)
3. **`/questionnaire/variables.php`** — Collects plaintiff name, defendant name, county, court, attorney info, facts, etc.
4. **`/questionnaire/output.php`** — Renders the completed document with all variables filled in

### Output Options

The output page provides three buttons:
- **Print** — Opens the browser print dialog (formatted for legal paper)
- **Download HTML** — Downloads the completed document as a standalone HTML file
- **Start Over** — Clears the session and returns to the entry point

---

## Adding New Forms

1. Add the HTML file to the [megerbyte/forms](https://github.com/megerbyte/forms) repository following the existing naming convention:
   ```
   Form_{CATEGORY}-{NUMBER}_{Title_With_Underscores}.html
   ```

2. Re-run the seeder (it uses `ON DUPLICATE KEY UPDATE` so existing forms are safely updated):
   ```bash
   php database/seed_forms.php
   ```

3. If the new form introduces new placeholder variables in `{italic text}` format, they will be
   automatically extracted and normalized into `snake_case` variable names.

4. To add the new form to the decision tree, edit `database/seed_decision_tree.php` and add
   the appropriate `insert_node` and `insert_option` calls, then re-run it:
   ```bash
   php database/seed_decision_tree.php
   ```

---

## Variable Normalization

Placeholder text from the forms is normalized using these rules:

| Raw placeholder | Normalized variable name |
|---|---|
| `{name of plaintiff}` | `plaintiff_name` |
| `{name of defendant}` | `defendant_name` |
| `{date}` | `event_date` |
| `{identify location}` | `event_location` |
| `{_______} County` | `county_name` |
| `{name of attorney}` | `attorney_name` |
| `{name of opposing counsel}` | `opposing_counsel_name` |
| `{amount}` | `damages_amount` |
| `{describe events that resulted in lawsuit}` | `facts_description` |

Instruction-only placeholders (references to O'Connor's, "CHOOSE APPROPRIATE PARAGRAPH", etc.)
are **not** treated as user variables and are stripped or left as-is in the template.

---

## Shared Variables

The following variables are considered "shared" — they appear across many forms and are collected
once per case:

- `plaintiff_name` — Full legal name of the plaintiff
- `defendant_name` — Full legal name of the defendant
- `county_name` — Texas county where the case is filed
- `court_name` — Name of the court
- `cause_number` — Assigned cause/case number
- `attorney_name` — Filing attorney's full name
- `bar_number` — Texas State Bar number

---

## Security Notes

- All database queries use PDO prepared statements — no raw SQL string concatenation with user input
- Session tokens are 64-character hex strings generated with `random_bytes(32)`
- User-supplied values are HTML-escaped with `htmlspecialchars()` before output
- The `config.php` file contains database credentials — ensure it is **not** publicly accessible

---

## Requirements

- PHP 8.0+ with PDO and cURL extensions enabled
- MySQL 5.7+ or MariaDB 10.3+
- Internet access to fetch forms from GitHub during seeding (or pre-download to a local path)
