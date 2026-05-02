<?php
// register.php - Updated for lawyer/admin signup option
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lawyer/Admin Subscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="assets/js/scripts.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body>
    <div class="container mt-5">
        <h2>Subscribe as Lawyer or Admin</h2>
        <form method="POST">
            <div class="mb-3">
                <label>Name/Username</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Address (for Lawyers)</label>
                <input type="text" name="address" class="form-control">
            </div>
            <div class="mb-3">
                <label>Type</label>
                <select name="type" class="form-control" required onchange="toggleFields(this.value)">
                    <option value="lawyer">Lawyer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div id="lawyer-fields" class="mb-3">
                <label>Subscription Level (for Lawyers)</label>
                <select name="subscription_level" class="form-control">
                    <option value="1">Level 1 (Free Evaluation, 1 solicitation/month)</option>
                    <option value="2">Level 2 (Paid, admin-set solicitations)</option>
                    <option value="3">Level 3 (Paid, higher solicitations)</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <div id="password-strength"></div>
            </div>
            <div class="h-captcha" data-sitekey="<?php echo hCAPTCHA_SITE_KEY; ?>"></div>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
    </div>
    <script>
        function toggleFields(type) {
            document.getElementById('lawyer-fields').style.display = (type === 'lawyer') ? 'block' : 'none';
        }
        toggleFields('lawyer'); // Initial state
    </script>
</body>
</html>
<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyHCaptcha($_POST['h-captcha-response'])) die('CAPTCHA failed');

    $type = $_POST['type'];
    if ($type === 'admin') {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute([$_POST['name'], hashPassword($_POST['password'])]);
        echo 'Admin registration complete.';
    } else {
        // Existing lawyer registration code
        $level = $_POST['subscription_level'];
        $maxSolic = ($level == 1) ? 1 : (($level == 2) ? getSetting('level2_solicitations') : getSetting('level3_solicitations'));
        $token = bin2hex(random_bytes(32));

        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO lawyers (name, email, address, subscription_level, max_solicitations_per_month, password, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['email'], $_POST['address'], $level, $maxSolic, hashPassword($_POST['password']), $token]);

        // Send verification (existing code)
        $link = SITE_URL . '/verify_email.php?email=' . $_POST['email'] . '&token=' . $token;
        $body = "Click to verify: <a href='$link'>Verify</a>";
        sendEmail($_POST['email'], 'Verify Your Subscription', $body);

        // Paid levels (existing code)
        if ($level > 1) {
            // Stripe code...
        }
        echo 'Registration complete. Check email for verification.';
    }
}
function getSetting($key) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}
?>