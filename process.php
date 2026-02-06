<?php
if (file_exists(__DIR__ . '/.reg-closed')) {
    include 'registration-closed.php';
    exit;
}

require_once __DIR__ . '/send-email.php';

$env = @parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW); // never include any spaces or quotes in the .env file

if ($env !== false) {
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("$key=$value");
    }
}

$required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($required as $var) {
    if (empty($_ENV[$var])) {
        die("Missing or empty required .env variable: $var");
    }
}

// ────────────────────────
//  establish connection
// ────────────────────────
function get_custom_db_connection()
{
    try {
        $pdo = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=' . $_ENV['DB_CHARSET'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

session_start();

// Store all POST data in session for later use / debugging
$_SESSION['form_data'] = $_POST;

if (isset($_POST['action']) && $_POST['action'] === 'save_partial') {
    require __DIR__ . '/save-partial.php';
    exit;
}

$group_name = trim($_POST['group_name'] ?? '');
if ($group_name === '') {
    die("Group name is required.");
}

$group_type = $_POST['group_type'] ?? '';

$pdo = get_custom_db_connection();

// Only check for duplicate group name if this is NOT an Individual registration
if ($group_type !== 'Individual') {
    $stmt = $pdo->prepare("SELECT 1 FROM groups WHERE group_name = ? LIMIT 1");
    $stmt->execute([$group_name]);
    if ($stmt->fetch()) {
        ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Group already registered - Tucson Mariachi Conference</title>
            <style>
                body {
                    font-family: system-ui, sans-serif;
                    background: #fdfdfd;
                    color: #1a1a1a;
                    margin: 0;
                    padding: 40px 20px;
                    text-align: center;
                    line-height: 1.6;
                }

                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                }

                h1 {
                    color: #b22222;
                }

                .btn {
                    display: inline-block;
                    margin-top: 24px;
                    padding: 12px 28px;
                    background: #006400;
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: bold;
                }

                .btn:hover {
                    background: #004d00;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <h1>Group already registered</h1>
                <p>A group has already registered with the name <strong><?= htmlspecialchars($group_name) ?></strong>.</p>
                <p>Please contact us if you need to request changes.</p>
                <p>Tel: (520) 838-3913 | Email: info@tucsonmariachi.org</p>
                <p><a href="index.php" class="btn">← Back to registration form</a></p>
            </div>
        </body>

        </html>
        <?php
        exit;
    }
}

$po_suffix = substr($_POST['cell_phone'] ?? '0000', -6);
$po_number = '2026' . str_pad($po_suffix, 4, '0', STR_PAD_LEFT);

$total_cost = 0;
$inserted_performers = 0;

if (!empty($_POST['performers']) && is_array($_POST['performers'])) {
    foreach ($_POST['performers'] as $p) {
        if (empty($p['first_name']) && empty($p['last_name']))
            continue;

        $cost = 0;
        $lvl = $p['level'] ?? '';
        $cls = $p['class'] ?? '';
        if (in_array($lvl, ['I', 'II', 'III']))
            $cost = 115;
        else if ($lvl === 'Master')
            $cost = ($cls === 'Voice') ? 115 : 165;

        $total_cost += $cost;
        $inserted_performers++;
    }
}

$performer_count = $inserted_performers;

$payment_method = $_POST['payment_method'] ?? 'credit_card';
if (!in_array($payment_method, ['credit_card', 'purchase_order'])) {
    $payment_method = 'credit_card';
}

$cantaTickets = (int) ($_POST['number_of_Canta_tickets'] ?? 0);
$garibaldiTickets = (int) ($_POST['number_of_Garibaldi_tickets'] ?? 0);
$total_cost += 10 * ($cantaTickets + $garibaldiTickets);

try {
    if ($payment_method === 'purchase_order') {
        // For PO, insert data immediately
        $pdo->beginTransaction();

        // Insert directors
        $stmt = $pdo->prepare("
            INSERT INTO directors 
            (group_name, first_name, last_name, street_address, city, state, zip_code, daytime_phone, cell_phone, email, d2_first_name, d2_last_name, d2_cell_phone, d2_daytime_phone, d2_email)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $group_name,
            $_POST['director_first'] ?? '',
            $_POST['director_last'] ?? '',
            $_POST['street_address'] ?? '',
            $_POST['city'] ?? '',
            $_POST['state'] ?? '',
            $_POST['zip_code'] ?? '',
            $_POST['daytime_phone'] ?? '',
            $_POST['cell_phone'] ?? '',
            $_POST['email'] ?? '',
            $_POST['d2_first_name'] ?? '',
            $_POST['d2_last_name'] ?? '',
            $_POST['d2_cell_phone'] ?? '',
            $_POST['d2_daytime_phone'] ?? '',
            $_POST['d2_email'] ?? ''
        ]);

        $director_id = $pdo->lastInsertId();

        // Insert performers
        if (!empty($_POST['performers']) && is_array($_POST['performers'])) {
            $stmt = $pdo->prepare("
                INSERT INTO performers 
                (group_name, first_name, last_name, age, gender, grade, race, class, level, cost)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($_POST['performers'] as $p) {
                if (empty($p['first_name']) && empty($p['last_name']))
                    continue;

                $cost = 0;
                $lvl = $p['level'] ?? '';
                $cls = $p['class'] ?? '';

                $workshop_type = $_POST['workshop_type'] ?? 'Mariachi';
                if ($workshop_type === 'Folklorico') {
                    $cls = 'Dance';
                }

                if (in_array($lvl, ['I', 'II', 'III'])) {
                    $cost = 115;
                } else if ($lvl === 'Master') {
                    $cost = (in_array($cls, ['Voice', 'Harp'])) ? 115 : 165;
                }

                $stmt->execute([
                    $group_name,
                    $p['first_name'] ?? '',
                    $p['last_name'] ?? '',
                    (int) ($p['age'] ?? 0),
                    $p['gender'] ?? '',
                    $p['grade'] ?? '',
                    $p['race'] ?? '',
                    $cls,
                    $lvl,
                    $cost
                ]);

                $inserted_performers++;
            }
        }

        $paid_status = 'No';

        // ─────────────────────────────────────────────────────────────
        // Insert group - Purchase Order version (payment_1 fields NULL)
        // ─────────────────────────────────────────────────────────────
        $stmt_group = $pdo->prepare("
            INSERT INTO groups (
                group_name,
                group_type,
                workshop_type,
                showcase_performance,
                garibaldi_performance,
                school_name,
                user_first_name,          
                user_last_name,           
                user_email,               
                user_phone,               
                total_cost,
                po_number,
                registration_date,
                paid,
                competition_exclusion,
                hotel,
                hotel_name,
                hotel_duration,
                number_of_Canta_tickets,
                number_of_Garibaldi_tickets,
                payment_1_date,
                payment_1_amount,
                payment_1_method
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?)
        ");

        $hotel_duration = isset($_POST['hotel_nights']) && $_POST['hotel_nights'] !== ''
            ? (int) $_POST['hotel_nights']
            : null;

        $stmt_group->execute([
        $group_name,
        $_POST['group_type'] ?? null,
        $_POST['workshop_type'] ?? null,
        $_POST['showcase_performance'] ?? 'No',
        $_POST['garibaldi_performance'] ?? 'no',
        $_POST['school_name'] ?? '',
        $_POST['user_first_name'] ?? '',
        $_POST['user_last_name'] ?? '',
        $_POST['user_email'] ?? '',
        $_POST['user_phone'] ?? '',
        $total_cost,
        $po_number,
        $paid_status,
        $_POST['competition_exclusion'] ?? null,
        $_POST['hotel'] ?? 'no',
        $_POST['hotel_name'] ?? '',
        $hotel_duration,
        $cantaTickets,
        $garibaldiTickets,
        $payment_method 
    ]);

        $group_id = $pdo->lastInsertId();

        $showcase_requested = ($_POST['showcase_performance'] ?? 'No') === 'Yes';
        $is_individual = ($_POST['group_type'] ?? '') === 'Individual';

        if ($showcase_requested && !$is_individual) {
            $song_fields = [
                1 => ['title' => '', 'length' => ''],
                2 => ['title' => '', 'length' => ''],
                3 => ['title' => '', 'length' => ''],
            ];

            if (!empty($_POST['showcase_songs']) && is_array($_POST['showcase_songs'])) {
                foreach ($_POST['showcase_songs'] as $num => $song) {
                    $num = (int) $num;
                    if ($num < 1 || $num > 3)
                        continue;

                    $title = trim($song['title'] ?? '');
                    $seconds = (int) ($song['seconds'] ?? 0);

                    if ($title !== '' && $seconds > 0) {
                        $minutes = floor($seconds / 60);
                        $secs = $seconds % 60;
                        $length = sprintf("%02d:%02d", $minutes, $secs);
                        $song_fields[$num]['title'] = $title;
                        $song_fields[$num]['length'] = $length;
                    }
                }
            }

            $hasAnySong = false;
            foreach ($song_fields as $f) {
                if ($f['title'] !== '' || $f['length'] !== '') {
                    $hasAnySong = true;
                    break;
                }
            }

            if ($hasAnySong) {
                $stmt = $pdo->prepare("
                    INSERT INTO songs 
                    (group_name, song_1_title, song_1_length, song_2_title, song_2_length, song_3_title, song_3_length)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $group_name,
                    $song_fields[1]['title'],
                    $song_fields[1]['length'],
                    $song_fields[2]['title'],
                    $song_fields[2]['length'],
                    $song_fields[3]['title'],
                    $song_fields[3]['length'],
                ]);
            }
        }

        $pdo->commit();

        // Send emails for PO
        $directorEmail = trim($_POST['email'] ?? '');
        $userEmail = trim($_POST['user_email'] ?? '');
        $adminEmail = 'info@tucsonmariachi.org';

        $directorName = trim(($_POST['director_first'] ?? '') . ' ' . ($_POST['director_last'] ?? ''));
        $userName = trim(($_POST['user_first_name'] ?? '') . ' ' . ($_POST['user_last_name'] ?? ''));

        $common = [
            'groupName' => $group_name,
            'poNumber' => $po_number,
            'totalCost' => $total_cost,
            'performerCount' => $performer_count,
            'formData' => $_POST + ['payment_method' => $payment_method],
            'cantaTickets' => $cantaTickets,
            'garibaldiTickets' => $garibaldiTickets,
        ];

        $send = function ($email, $name, $fallback) use ($common) {
            $email = trim($email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $name = trim($name) ?: $fallback;
            sendRegistrationConfirmation(
                toEmail: $email,
                toName: $name,
                groupName: $common['groupName'],
                poNumber: $common['poNumber'],
                totalCost: $common['totalCost'],
                performerCount: $common['performerCount'],
                formData: $common['formData']
            );
        };

        $send($directorEmail, $directorName, $group_name);

        if ($userEmail && strcasecmp($userEmail, $directorEmail) !== 0) {
            $send($userEmail, $userName, $group_name);
        }

        $send($adminEmail, '', $group_name);

        // Set session for PO
        $_SESSION['cart'] = [
            'group_name' => $group_name,
            'director_name' => ($_POST['director_first'] ?? '') . ' ' . ($_POST['director_last'] ?? ''),
            'assistant_name' => ($_POST['d2_first_name'] ?? '') . ' ' . ($_POST['d2_last_name'] ?? ''),
            'total_cost' => $total_cost,
            'po_number' => $po_number,
            'group_id' => $group_id ?? null,
            'payment_method' => $payment_method,
            'status' => 'pending_po',
            'number_of_Canta_tickets' => $cantaTickets,
            'number_of_Garibaldi_tickets' => $garibaldiTickets,
        ];

        header("Location: confirmation-po.php");
        exit;

    } else {
        // Credit card flow → go to invoice
        $_SESSION['cart'] = [
            'group_name' => $group_name,
            'director_name' => ($_POST['director_first'] ?? '') . ' ' . ($_POST['director_last'] ?? ''),
            'assistant_name' => ($_POST['d2_first_name'] ?? '') . ' ' . ($_POST['d2_last_name'] ?? ''),
            'total_cost' => $total_cost,
            'po_number' => $po_number,
            'group_id' => null,
            'payment_method' => $payment_method,
            'status' => 'pending_payment',
            'email' => $_POST['email'] ?? $_POST['user_email'] ?? null,
        ];

        header("Location: invoice.php");
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $payment_method === 'purchase_order') {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage());
}