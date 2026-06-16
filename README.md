# Electronic Lawyer

A PHP web application that guides users through a legal intake questionnaire, generates a report based on their answers, and routes that report to relevant lawyers.

## Getting Started

1. **Clone the repository**
   ```bash
   git clone https://github.com/megerbyte/electronic_lawyer.git
   cd electronic_lawyer
   ```

2. **Configure environment variables**
   ```bash
   cp .env.example .env
   ```
   Open `.env` and fill in your real credentials:
   - Database connection details (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`)
   - hCaptcha keys (`hCAPTCHA_SITE_KEY`, `hCAPTCHA_SECRET_KEY`)
   - Stripe keys (`STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY`)
   - SMTP / email settings
   - Site URL

   > ⚠️ **Never commit `.env` to version control.** It is listed in `.gitignore`.

3. **Set up the database**
   Import your SQL schema into the database specified in your `.env` file.

4. **Serve the application**
   Point your web server (Apache/Nginx) document root at this directory, or use PHP's built-in server for local development:
   ```bash
   php -S localhost:8000
   ```

5. **Local development only**
   To enable PHP error output while developing locally, set `display_errors = 1` and `error_reporting = E_ALL` at the top of the relevant config file (`config.php`, `config.sys`, `includes/config.php`, `includes/config.sys`, or `includes/functions.php`). **Do not enable these in production.**

