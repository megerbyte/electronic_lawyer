<?php
// case_eval/redirect_handler.php
// Custom 404 error handler for researchrepository.info

// Get the URL that caused the 404
$requested_url = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI']) : 'Unknown';

// Log the 404 for debugging (writes to a log file in the same directory)
$log_file = __DIR__ . '/404_log.txt';
$log_entry = date('Y-m-d H:i:s') . " | 404 | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | URL: " . $_SERVER['REQUEST_URI'] . "\n";
file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

// Send proper 404 header
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | Research Repository</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 50px 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        .error-code {
            font-size: 80px;
            font-weight: bold;
            color: #001f3f;
            line-height: 1;
        }
        h1 {
            font-size: 24px;
            color: #001f3f;
            margin: 15px 0 10px;
        }
        p {
            color: #666;
            margin-bottom: 10px;
            font-size: 15px;
            line-height: 1.6;
        }
        .url-display {
            background: #f1f3f5;
            border-left: 4px solid #dc3545;
            padding: 10px 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            color: #555;
            text-align: left;
            margin: 20px 0;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            margin: 8px;
            padding: 12px 28px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-primary {
            background-color: #001f3f;
            color: #fff;
        }
        .btn-primary:hover { background-color: #003060; }
        .btn-secondary {
            background-color: #e9ecef;
            color: #333;
        }
        .btn-secondary:hover { background-color: #dee2e6; }
        .divider {
            border: none;
            border-top: 1px solid #e9ecef;
            margin: 30px 0 20px;
        }
        .help-text {
            font-size: 13px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">404</div>
        <h1>Page Not Found</h1>
        <p>The page you were looking for could not be found. It may have moved, been renamed, or is temporarily unavailable.</p>

        <div class="url-display">
            Requested: <?php echo $requested_url; ?>
        </div>

        <p>Please use one of the options below to continue:</p>

        <div>
            <a href="/" class="btn btn-primary">Go to Home Page</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>

        <hr class="divider">
        <p class="help-text">If you believe this is an error, please contact the site administrator.<br>
        Error logged at <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>
