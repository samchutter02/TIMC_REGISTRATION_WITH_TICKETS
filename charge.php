<?php
session_start();

if (file_exists(__DIR__ . '/.reg-closed')) {
    include 'registration-closed.php';
    exit;
}

if (empty($_SESSION['cart']) || empty($_SESSION['form_data'])) {
    $error_message = "No cart or form data found. Please start registration again.";
    $is_success = false;
} else {
    require 'vendor/autoload.php';
    \Stripe\Stripe::setApiKey('you-thought-LOL');

    $token = $_POST['stripeToken'] ?? null;
    $total_cost = (float) ($_SESSION['cart']['total_cost'] ?? 0);
    $group_name = $_SESSION['cart']['group_name'] ?? 'Unknown Group';
    $email = $_SESSION['cart']['email'] ?? null;

    if (!$token) {
        $error_message = "No payment token received. Please try again.";
        $is_success = false;
    } elseif ($total_cost <= 0) {
        $error_message = "Registration total is $0. Please add at least one participant.";
        $is_success = false;
    } else {
        $amount_in_cents = (int) round($total_cost * 100);

        try {
            $charge = \Stripe\Charge::create([
                'amount'      => $amount_in_cents,
                'currency'    => 'usd',
                'description' => "2026 Tucson Mariachi Conference Registration - $group_name",
                'source'      => $token,
                'receipt_email' => $email,
            ]);

            $is_success = true;
            $charge_id = $charge->id;
            $payment_amount = number_format($total_cost, 2);

            // Now, since payment successful, insert data into DB
            require_once __DIR__ . '/send-email.php';

            $env = @parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
            if ($env !== false) {
                foreach ($env as $key => $value) {
                    $_ENV[$key]    = $value;
                    $_SERVER[$key] = $value;
                    putenv("$key=$value");
                }
            }

            $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
            foreach ($required as $var) {
                if (empty($_ENV[$var])) {
                    throw new Exception("Missing or empty required .env variable: $var");
                }
            }

            $pdo = new PDO(
                'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=' . $_ENV['DB_CHARSET'],
                $_ENV['DB_USER'],
                $_ENV['DB_PASS'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            $pdo->beginTransaction();

            $form_data = $_SESSION['form_data'];
            $cantaTickets = (int) ($form_data['number_of_Canta_tickets'] ?? 0);
            $garibaldiTickets = (int) ($form_data['number_of_Garibaldi_tickets'] ?? 0);
            // $total_cost += 10 * ($cantaTickets + $garibaldiTickets);
            $amount_in_cents = (int) round($total_cost * 100);

            // Insert directors
            $stmt = $pdo->prepare("
                INSERT INTO directors 
                (group_name, first_name, last_name, street_address, city, state, zip_code, daytime_phone, cell_phone, email, d2_first_name, d2_last_name, d2_cell_phone, d2_daytime_phone, d2_email)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $group_name,
                $form_data['director_first'] ?? '',
                $form_data['director_last'] ?? '',
                $form_data['street_address'] ?? '',
                $form_data['city'] ?? '',
                $form_data['state'] ?? '',
                $form_data['zip_code'] ?? '',
                $form_data['daytime_phone'] ?? '',
                $form_data['cell_phone'] ?? '',
                $form_data['email'] ?? '',
                $form_data['d2_first_name'] ?? '',
                $form_data['d2_last_name'] ?? '',
                $form_data['d2_cell_phone'] ?? '',
                $form_data['d2_daytime_phone'] ?? '',
                $form_data['d2_email'] ?? ''
            ]);

            $director_id = $pdo->lastInsertId();

            // Insert performers
$inserted_performers = 0;
if (!empty($form_data['performers']) && is_array($form_data['performers'])) {
    $stmt = $pdo->prepare("
        INSERT INTO performers 
        (group_name, first_name, last_name, age, gender, grade, race, class, level, cost)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($form_data['performers'] as $p) {
        if (empty($p['first_name']) && empty($p['last_name'])) continue;

        $cost = 0;
        $lvl  = $p['level']  ?? '';
        $cls  = $p['class']  ?? '';

        // Enforce Dance class when Folklorico is selected
        $workshop_type = $form_data['workshop_type'] ?? 'Mariachi';
        if ($workshop_type === 'Folklorico') {
            $cls = 'Dance';
        }

        if (in_array($lvl, ['I','II','III'])) {
            $cost = 115;
        } else if ($lvl === 'Master') {
            $cost = (in_array($cls, ['Voice', 'Harp'])) ? 115 : 165;
        }

        $stmt->execute([
            $group_name,
            $p['first_name'] ?? '',
            $p['last_name']  ?? '',
            (int)($p['age'] ?? 0),
            $p['gender']     ?? '',
            $p['grade']      ?? '',
            $p['race']       ?? '',
            $cls,
            $lvl,
            $cost
        ]);

        $inserted_performers++;
    }
}

            $performer_count = $inserted_performers;

            $paid_status = 'Yes'; // Since payment succeeded

            // Insert groups
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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");

            $hotel_duration = isset($form_data['hotel_nights']) && $form_data['hotel_nights'] !== ''
                ? (int) $form_data['hotel_nights']
                : null;

            $stmt_group->execute([
                $group_name,
                $form_data['group_type']               ?? null,
                $form_data['workshop_type']            ?? null,
                $form_data['showcase_performance']     ?? 'No',
                $form_data['garibaldi_performance']    ?? 'no',
                $form_data['school_name']              ?? '',
                $form_data['user_first_name']          ?? '',
                $form_data['user_last_name']           ?? '',
                $form_data['user_email']               ?? '',
                $form_data['user_phone']               ?? '',
                $total_cost,
                $_SESSION['cart']['po_number'],
                $paid_status,                            
                $form_data['competition_exclusion']    ?? null,
                $form_data['hotel']                    ?? 'no',
                $form_data['hotel_name']               ?? '',
                $hotel_duration,
                $cantaTickets,
                $garibaldiTickets,                                                               
                $total_cost,                                
                'credit_card'                            
            ]);

            $group_id = $pdo->lastInsertId();

            // Insert songs
            $song_fields = [
                1 => ['title' => '', 'length' => ''],
                2 => ['title' => '', 'length' => ''],
                3 => ['title' => '', 'length' => ''],
            ];

            if (!empty($form_data['showcase_songs']) && is_array($form_data['showcase_songs'])) {
                foreach ($form_data['showcase_songs'] as $num => $song) {
                    $num = (int)$num;
                    if ($num < 1 || $num > 3) continue;

                    $title   = trim($song['title']   ?? '');
                    $seconds = (int)($song['seconds'] ?? 0);

                    if ($title !== '' && $seconds > 0) {
                        $minutes = floor($seconds / 60);
                        $secs    = $seconds % 60;
                        $length  = sprintf("%02d:%02d", $minutes, $secs);
                        $song_fields[$num]['title']  = $title;
                        $song_fields[$num]['length'] = $length;
                    }
                }
            }

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

            $pdo->commit();

            // Send emails
            $directorEmail = trim($form_data['email'] ?? '');
            $userEmail     = trim($form_data['user_email'] ?? '');
            $adminEmail    = 'info@tucsonmariachi.org';

            $directorName  = trim(($form_data['director_first'] ?? '') . ' ' . ($form_data['director_last'] ?? ''));
            $userName      = trim(($form_data['user_first_name'] ?? '') . ' ' . ($form_data['user_last_name'] ?? ''));

            $common = [
                'groupName'      => $group_name,
                'poNumber'       => $_SESSION['cart']['po_number'],
                'totalCost'      => $total_cost,
                'performerCount' => $performer_count,
                'formData'       => $form_data + ['payment_method' => 'credit_card'],
                'cantaTickets' => $cantaTickets,
                'garibaldiTickets' => $garibaldiTickets,
            ];  

            $send = function($email, $name, $fallback) use ($common) {
                $email = trim($email);
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return;
                }
                $name = trim($name) ?: $fallback;
                sendRegistrationConfirmation(
                    toEmail:       $email,
                    toName:        $name,
                    groupName:     $common['groupName'],
                    poNumber:      $common['poNumber'],
                    totalCost:     $common['totalCost'],
                    performerCount: $common['performerCount'],
                    formData:      $common['formData']
                );
            };

            $send($directorEmail, $directorName, $group_name);

            if ($userEmail && strcasecmp($userEmail, $directorEmail) !== 0) {
                $send($userEmail, $userName, $group_name);
            }

            $send($adminEmail, '', $group_name);

            // Update session if needed, clear form_data
            unset($_SESSION['form_data']);
            unset($_SESSION['cart']);

        } catch (\Stripe\Exception\CardException $e) {
            $error_message = $e->getError()->message ?? 'Your card was declined.';
            $is_success = false;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $error_message = $e->getMessage() ?: 'Invalid payment request.';
            $is_success = false;
        } catch (Exception $e) {
            $error_message = $e->getMessage() ?: 'An unexpected error occurred.';
            error_log("Stripe or DB error: " . $e->getMessage());
            if (isset($pdo)) $pdo->rollBack();
            $is_success = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Result - Tucson Mariachi Conference</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background: #f9f9f9;
        }
        .container {
            max-width: 620px;
            margin: 40px auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .header {
            background: #10e225;
            color: white;
            padding: 30px 25px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 26px;
        }
        .content {
            padding: 35px 30px;
            text-align: center;
        }
        h2 {
            margin: 0 0 1.2em;
            font-size: 28px;
        }
        .success-icon {
            font-size: 64px;
            color: #2e7d32;
            margin-bottom: 0.4em;
        }
        .error-icon {
            font-size: 64px;
            color: #c62828;
            margin-bottom: 0.4em;
        }
        .details {
            background: #f5f5f5;
            border-radius: 6px;
            padding: 20px;
            margin: 1.8em 0;
            text-align: left;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        .details p {
            margin: 0.8em 0;
        }
        .highlight {
            color: #b22222;
            font-weight: bold;
        }
        .gold {
            color: #d4a017;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            background: #f5f5f5;
            color: #222;
            padding: 12px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 1.5em;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #c6c6c6;
        }
        .footer {
            background: #f5f5f5;
            padding: 20px;
            text-align: center;
            font-size: 13px;
            color: #666;
            border-top: 1px solid #eee;
        }
        .footer a {
            color: #b22222;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>2026 Tucson International Mariachi Conference</h1>
        <p>Payment Processing</p>
    </div>

    <div class="content">

        <?php if ($is_success): ?>
            <h2>Payment Successful!</h2>
            <p>Thank you for registering your group<?php if ($group_name !== 'Unknown Group') echo " <strong>$group_name</strong>"; ?>.</p>
            <p>Your payment of <span class="highlight">$<?php echo $payment_amount; ?></span> was successfully processed.</p>

            <div class="details">
                <p><strong>Charge ID:</strong> <?php echo htmlspecialchars($charge_id); ?></p>
                <p><strong>Amount:</strong> $<?php echo $payment_amount; ?></p>
                <?php if ($email): ?>
                    <p><strong>Receipt sent to:</strong> <?php echo htmlspecialchars($email); ?></p>
                <?php endif; ?>
            </div>

            <p>A detailed confirmation email has also been sent to you.</p>
            <a href="index.php" class="btn">Return to Home Page</a>

        <?php else: ?>
            <h2>Payment Could Not Be Processed</h2>
            <p><?php echo htmlspecialchars($error_message ?? 'An unknown error occurred.'); ?></p>

            <?php if (isset($total_cost) && $total_cost <= 0): ?>
                <p>Please go back and make sure at least one participant is added.</p>
            <?php endif; ?>

            <p style="color:#555; margin-top:2em;">
                If this problem continues, please contact us at<br>
                <a href="mailto:info@tucsonmariachi.org">info@tucsonmariachi.org</a>
            </p>
        <?php endif; ?>

    </div>

    <div class="footer">
        <p>2026 Tucson Mariachi Conference | tucsonmariachi.org</p>
    </div>
</div>

</body>

</html>
