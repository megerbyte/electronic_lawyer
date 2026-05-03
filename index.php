<?php
// index.php - Main entry point for users to submit their basic contact information.
// This version collects user info and sends it to submit_user_info.php for processing.
// After submitting, the user will be directed to the main questionnaire.

session_start();

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Generate CSRF token if not already set (for security)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case Evaluation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Case Evaluation</h1>
        <p>This questionnaire will assist your counsel in mounting a proper defense for you.</p>

        <!-- Always visible Login and Lawyer Signup Buttons -->
        <div class="mb-4 d-flex gap-3">
            <a href="login.php" class="btn btn-primary">Login</a>
            <a href="register.php" class="btn btn-success">Lawyer Signup</a>
        </div>

        <?php if (!isset($_SESSION['prospect_id'])) { ?>
            <form method="POST" action="submit_user_info.php" autocomplete="off">
                <div class="mb-3">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>State</label>
                    <input type="text" name="state" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Zip</label>
                    <input type="text" name="zip" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Phone</label>
                    <input type="tel" name="phone" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" class="btn btn-primary">Submit and Get Started</button>
            </form>
        <?php } else { ?>
            <p>You are already logged in. 
                <a href="/Master_Folders/Criminal/First_Contact/First_Contact.html">Go to Questionnaire</a> 
                or 
                <a href="logout.php">Logout</a>.
            </p>
        <?php } ?>
    </div>
</body>
</html>