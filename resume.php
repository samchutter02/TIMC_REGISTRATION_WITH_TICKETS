<?php
session_start();

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    header("Location: index.php?resume=expired");
    exit;
}

$env = @parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
if ($env === false || empty($env['DB_HOST'])) {
    die("Configuration error.");
}

try {
    $pdo = new PDO(
        'mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_NAME'] . ';charset=utf8mb4',
        $env['DB_USER'],
        $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        SELECT data_json 
        FROM partial_registrations 
        WHERE token = ? AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);

    $row = $stmt->fetch();

    if ($row) {
        $_SESSION['partial_resume_data'] = json_decode($row['data_json'], true);
        // Optional: delete or mark as used (not required for overwrite strategy)
        // $pdo->prepare("DELETE FROM partial_registrations WHERE token = ?")->execute([$token]);
        header("Location: index.php");
        exit;
    } else {
        header("Location: index.php?resume=expired");
        exit;
    }

} catch (Exception $e) {
    error_log("Resume failed: " . $e->getMessage());
    header("Location: index.php?resume=expired");
    exit;
}