<?php
session_start();

if (empty($_POST['action']) || $_POST['action'] !== 'save_partial') {
    die("Invalid request.");
}

require_once __DIR__ . '/send-email.php';

// ────────────────────────────────────────────────
// Minimal validation
// ────────────────────────────────────────────────
$email = trim($_POST['email'] ?? $_POST['user_email'] ?? '');
$group_name_raw = trim($_POST['group_name'] ?? '');

if ($group_name_raw === '') {
    dieWithMessage("Group name is required to save progress.");
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    dieWithMessage("A valid email address is required to save and continue later.");
}

$group_name_key = strtolower($group_name_raw); // for lookup/upsert

// ────────────────────────────────────────────────
// Prepare data to save (exclude sensitive / transient fields)
// ────────────────────────────────────────────────
$data = $_POST;
unset($data['action']);
unset($data['stripeToken']);           // never save payment tokens
unset($data['purchase_order_number']); // if you ever add it

// ────────────────────────────────────────────────
// Database connection (reuse same style as process.php)
// ────────────────────────────────────────────────
$env = @parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
if ($env === false || empty($env['DB_HOST'])) {
    dieWithMessage("Configuration error. Please contact support.");
}

try {
    $pdo = new PDO(
        'mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_NAME'] . ';charset=utf8mb4',
        $env['DB_USER'],
        $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ────────────────────────────────────────────────
    // UPSERT logic (update if group_name exists, else insert)
    // ────────────────────────────────────────────────
    $token = bin2hex(random_bytes(12));
    $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

    $stmt = $pdo->prepare("
        INSERT INTO partial_registrations 
        (token, data_json, email, group_name, created_at, expires_at)
        VALUES (?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
            token = VALUES(token),
            data_json = VALUES(data_json),
            email = VALUES(email),
            created_at = NOW(),
            expires_at = VALUES(expires_at)
    ");

    $stmt->execute([
        $token,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $email,
        $group_name_key,
        $expires_at
    ]);

    // ────────────────────────────────────────────────
    // Send resume email
    // ────────────────────────────────────────────────
    $resumeLink = "https://www.timcregistration.org/resume.php?token=" . urlencode($token);

    $toName = trim(($_POST['director_first'] ?? '') . ' ' . ($_POST['director_last'] ?? ''));
    if ($toName === '') {
        $toName = trim(($_POST['user_first_name'] ?? '') . ' ' . ($_POST['user_last_name'] ?? ''));
    }
    if ($toName === '') $toName = 'Registrant';

    $success = sendResumeLink($email, $toName, $resumeLink);

    if ($success) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Progress Saved - Tucson Mariachi Conference</title>
            <style>
                body { font-family: system-ui, sans-serif; background:#fdfdfd; color:#1a1a1a; margin:0; padding:60px 20px; text-align:center; }
                .box { max-width:580px; margin:0 auto; background:white; padding:40px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.1); }
                h1 { color:#006400; }
                .btn { display:inline-block; margin-top:28px; padding:14px 32px; background:#b22222; color:white; text-decoration:none; border-radius:8px; font-weight:bold; }
                .btn:hover { background:#8b1a1a; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>Progress Saved!</h1>
                <p>A link to resume your registration has been sent to <strong><?= htmlspecialchars($email) ?></strong>.</p>
                <p>Check your inbox (and spam/junk folder). The link is valid for 7 days.</p>
                <p><a href="index.php" class="btn">← Back to Registration</a></p>
            </div>
        </body>
        </html>
        <?php
    } else {
        dieWithMessage("Email could not be sent. Please try again or contact support.");
    }

} catch (Exception $e) {
    error_log("Save partial failed: " . $e->getMessage());
    dieWithMessage("An error occurred while saving. Please try again or contact info@tucsonmariachi.org");
}

function dieWithMessage($msg) {
    http_response_code(400);
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body style='padding:60px;text-align:center;font-family:system-ui;'>";
    echo "<h1 style='color:#b22222;'>Error</h1><p>$msg</p>";
    echo "<p><a href='index.php'>← Back to form</a></p></body></html>";
    exit;
}