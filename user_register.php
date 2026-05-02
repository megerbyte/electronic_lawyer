<?php
// user_register.php - New file for user (prospect) signup
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Signup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="assets/js/scripts.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body>
    <div class="container mt-5">
        <h2>User Signup</h2>
        <form method="POST">
            <div class="mb-3">
                <label>Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <div id="password-strength"></div>
            </div>
            <div class="h-captcha" data-sitekey="<?php echo hCAPTCHA_SITE_KEY; ?>"></div>
            <button type="submit" class="btn btn-primary">Signup</button>
        </form>
    </div>
</body>
</html>
<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyHCaptcha($_POST['h-captcha-response'])) die('CAPTCHA failed');

    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO prospects (name, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['name'], $_POST['email'], hashPassword($_POST['password'])]);
    $prospectId = $pdo->lastInsertId();

    // Send verification or login directly
    $_SESSION['prospect_id'] = $prospectId;
    header('Location: questionnaire.php');
    exit;
}
?>