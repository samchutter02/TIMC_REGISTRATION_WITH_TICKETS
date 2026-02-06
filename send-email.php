<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendRegistrationConfirmation(
    string $toEmail,
    string $toName,
    string $groupName,
    string $poNumber,
    float $totalCost,
    int $performerCount,
    array $formData
): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'localhost';
        $mail->SMTPAuth = false;
        $mail->Username = '';
        $mail->Password = '';
        $mail->SMTPSecure = '';
        $mail->Port = 25;

        $mail->setFrom('regsystem@tucsonmariachi.org', 'Tucson Mariachi Registration');
        $mail->addAddress($toEmail, $toName);

        // Add user's email if provided && different from director's email
        if (!empty($formData['user_email']) && $formData['user_email'] !== $toEmail) {
            $userName = trim(($formData['user_first_name'] ?? '') . ' ' . ($formData['user_last_name'] ?? ''));
            $userName = !empty($userName) ? $userName : 'Registrant';
            $mail->addAddress($formData['user_email'], $userName);
        }

        // if you want to BCC admin in the future uncomment the line below
        // $mail->addBCC('info@tucsonmariachi.org', 'Registration Admin');

        $mail->isHTML(true);
        $mail->Subject = 'Registration Received - Transaction #' . htmlspecialchars($poNumber);

        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Registration Confirmation</title>
            <style type="text/css">
                body { font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #333; margin:0; padding:0; background:#f9f9f9; }
                .container { max-width: 620px; margin: 20px auto; background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
                .header { background: #b22222; color: white; padding: 20px 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                h2 { color: #b22222; margin-top: 1.5em; border-bottom: 2px solid #d4a017; padding-bottom: 8px; font-size: 20px; }
                h3 { color: #444; margin-top: 1.8em; font-size: 18px; border-left: 5px solid #d4a017; padding-left: 12px; }
                table { width: 100%; max-width: 600px; border-collapse: collapse; margin: 1.2em 0; font-size: 15px; }
                th, td { padding: 10px 12px; border: 1px solid #e0e0e0; text-align: left; }
                th { background: #f5f5f5; color: #444; font-weight: bold; }
                .highlight { background: #fff8e1; font-weight: bold; }
                .total-row td { background: #fdf2e9; font-size: 1.1em; }
                .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 13px; color: #666; border-top: 1px solid #eee; }
                .footer a { color: #b22222; text-decoration: none; }

                ul { margin: 0.6em 0 1.2em 1.8em; padding-left: 0; }
                ul li { margin-bottom: 0.5em; }
                .yes { color: #2e7d32; font-weight: bold; }
                .no  { color: #c62828; }
            </style>
        </head>
        <body>
        <div class="container">
            <div class="header">
                <h1>2026 Tucson International Mariachi Conference</h1>
                <p>Registration Confirmation</p>
            </div>

            <div class="content">
                <h2>Thank you, ' . htmlspecialchars($groupName) . '!</h2>
                <p>We have successfully received your registration. Below is a summary of your submission.</p>

                <h3>Registration Summary</h3>
                <table>
                    <tr><td><strong>Registration Type</strong></td><td>' . htmlspecialchars($formData['registration_type'] ?? '-') . '</td></tr>
                    <tr><td><strong>' . ($formData['registration_type'] === 'individual' ? 'Individual Name' : 'Group Name') . '</strong></td><td>' . htmlspecialchars($groupName) . '</td></tr>';

        if (!empty($formData['school_name'] ?? '')) {
            $body .= '<tr><td><strong>School Name</strong></td><td>' . htmlspecialchars($formData['school_name']) . '</td></tr>';
        }

        $body .= '
                    <tr><td><strong>Transaction Number</strong></td><td>' . htmlspecialchars($poNumber) . '</td></tr>
                    <tr><td><strong>Total Participants</strong></td><td>' . $performerCount . '</td></tr>
                    <tr class="total-row"><td><strong>Total Amount Due</strong></td><td class="highlight">$' . number_format($totalCost, 2) . '</td></tr>
                </table>

                <h3>Director & Contact Information</h3>
                <table>
                    <tr><td style="width:38%;"><strong>Director</strong></td><td>'
            . htmlspecialchars(trim(($formData['director_first'] ?? '') . ' ' . ($formData['director_last'] ?? ''))) . '</td></tr>
                    <tr><td><strong>Email</strong></td><td>' . htmlspecialchars($formData['email'] ?? '-') . '</td></tr>
                    <tr><td><strong>Cell Phone</strong></td><td>' . htmlspecialchars($formData['cell_phone'] ?? '-') . '</td></tr>';

        if (!empty($formData['daytime_phone'] ?? '')) {
            $body .= '<tr><td><strong>Day Phone</strong></td><td>' . htmlspecialchars($formData['daytime_phone']) . '</td></tr>';
        }

        $body .= '
                    <tr><td><strong>Address</strong></td><td>'
            . htmlspecialchars(trim(($formData['street_address'] ?? '') . '  ' . ($formData['city'] ?? '') . ', ' . ($formData['state'] ?? '') . ' ' . ($formData['zip_code'] ?? '')))
            . '</td></tr>
                </table>';

        // Assistant Director
        if (!empty($formData['has_assistant_director']) && $formData['has_assistant_director'] === 'yes') {
            $body .= '
            <h3>Assistant Director</h3>
            <table>
                <tr><td style="width:38%;"><strong>Name</strong></td><td>'
                . htmlspecialchars(trim(($formData['d2_first_name'] ?? '') . ' ' . ($formData['d2_last_name'] ?? ''))) . '</td></tr>';

            if (!empty($formData['d2_cell_phone'] ?? '')) {
                $body .= '<tr><td><strong>Cell Phone</strong></td><td>' . htmlspecialchars($formData['d2_cell_phone']) . '</td></tr>';
            }
            if (!empty($formData['d2_email'] ?? '')) {
                $body .= '<tr><td><strong>Email</strong></td><td>' . htmlspecialchars($formData['d2_email']) . '</td></tr>';
            }
            $body .= '</table>';
        }
        // Participants Table
        $body .= '
                <h3>Participants</h3>
                <table>
                    <thead>
                        <tr style="background:#b22222; color:white;">
                            <th>Name</th>
                            <th>Instrument</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>';

        $hasParticipants = false;
        if (!empty($formData['performers']) && is_array($formData['performers'])) {
            foreach ($formData['performers'] as $performer) {
                $first = trim($performer['first_name'] ?? '');
                $last = trim($performer['last_name'] ?? '');
                if ($first === '' && $last === '')
                    continue;

                $hasParticipants = true;
                $name = htmlspecialchars($first . ($first && $last ? ' ' : '') . $last);
                $instrument = htmlspecialchars($performer['class'] ?? '-');
                $level = htmlspecialchars($performer['level'] ?? '-');

                $body .= "
                        <tr>
                            <td>$name</td>
                            <td>$instrument</td>
                            <td>$level</td>
                        </tr>";
            }
        }

        if (!$hasParticipants) {
            $body .= '
                        <tr>
                            <td colspan="3" style="text-align:center; padding:20px; color:#777; font-style:italic;">
                                No participants listed
                            </td>
                        </tr>';
        }

        $body .= '
                    </tbody>
                </table>

                <h3>Conference Choices</h3>
                <table>
                    <tr><td style="width:45%;"><strong>Workshop Type</strong></td><td>' . htmlspecialchars($formData['workshop_type'] ?? '-') . '</td></tr>
                    <tr><td><strong>Group Type</strong></td><td>' . htmlspecialchars($formData['group_type'] ?? '-') . '</td></tr>';

        // Only include competition/showcase/garibaldi questions for non-individual registrations
        $isIndividual = strtolower(trim($formData['group_type'] ?? '')) === 'individual';

        if (!$isIndividual) {
            $body .= '
                    <tr><td><strong>Exclude from competition?</strong></td><td>'
                . (($formData['competition_exclusion'] ?? '') === 'yes'
                    ? '<span class="yes">Yes - exclude us from the competition</span>'
                    : '<span class="no">No - we would like to compete</span>')
                . '</td></tr>
                    <tr><td><strong>Showcase Performance?</strong></td><td>' . htmlspecialchars($formData['showcase_performance'] ?? 'No') . '</td></tr>';

            if (($formData['showcase_performance'] ?? 'No') === 'Yes' && !empty($formData['showcase_songs'] ?? [])) {
                $body .= '
                    <tr><td><strong>Showcase Songs</strong></td><td><ul style="margin:4px 0; padding-left:18px;">';
                foreach ($formData['showcase_songs'] as $song) {
                    if (empty($song['title']))
                        continue;
                    $secs = (int) ($song['seconds'] ?? 0);
                    $time = ($secs > 0) ? floor($secs / 60) . ':' . str_pad($secs % 60, 2, '0', STR_PAD_LEFT) : '';
                    $body .= '<li>' . htmlspecialchars($song['title']) . ($time ? " ($time)" : '') . '</li>';
                }
                $body .= '</ul></td></tr>';
            }

            $body .= '
                    <tr><td><strong>Garibaldi Performance?</strong></td><td>'
                . (($formData['garibaldi_performance'] ?? '') === 'yes'
                    ? '<span class="yes">Yes</span>'
                    : '<span class="no">No</span>')
                . '</td></tr>';
        }

        $body .= '
                    <tr><td><strong>Canta Tickets</strong></td><td>' . ($formData['number_of_Canta_tickets'] ?? 0) . '</td></tr>
                    <tr><td><strong>Garibaldi Tickets</strong></td><td>' . ($formData['number_of_Garibaldi_tickets'] ?? 0) . '</td></tr>
                    <tr><td><strong>Staying at hotel?</strong></td><td>'
            . (($formData['hotel'] ?? '') === 'yes'
                ? '<span class="yes">Yes - ' . htmlspecialchars($formData['hotel_name'] ?? 'not specified') . '</span>'
                : '<span class="no">No</span>')
            . '</td></tr>
                </table>

                <p style="margin-top:2em; font-style:italic; color:#555;">
                    <strong>Number of performers registered:</strong> ' . $performerCount . '
                </p>

                <hr style="border:none; border-top:1px solid #eee; margin:2.5em 0 1.5em;">

                <p style="font-size:14px; color:#555; text-align:center;">
                    This is an automated confirmation from the Tucson Mariachi Registration System.<br>
                    Questions? Reply to this email or contact 
                    <a href="mailto:info@tucsonmariachi.org">info@tucsonmariachi.org</a>
                </p>
            </div>

            <div class="footer">
                <p>2026 Tucson Mariachi Conference | tucsonmariachi.org</p>
            </div>
        </div>
        </body>
        </html>';

        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(
            ['<br>', '</p>', '</li>', '</tr>', '</td>', '</h', '<table', '<div'],
            ["\n", "\n\n", "\n- ", "\n", " | ", "\n\n", "\n\n---\n", "\n\n"],
            $body
        ));

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Registration email failed for $toEmail (Transaction $poNumber): " . $mail->ErrorInfo);
        return false;
    }
}

function sendResumeLink(
    string $toEmail,
    string $toName,
    string $resumeLink
): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'localhost';
        $mail->SMTPAuth = false;
        $mail->Username = '';
        $mail->Password = '';
        $mail->SMTPSecure = '';
        $mail->Port = 25;

        $mail->setFrom('regsystem@tucsonmariachi.org', 'Tucson Mariachi Registration');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Resume Your Mariachi Registration';

        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Resume Registration</title>
            <style>
                body { font-family: system-ui, sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; line-height: 1.6; }
                .box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 32px; text-align: center; }
                h1 { color: #166534; margin-bottom: 8px; }
                .highlight { font-weight: bold; color: #166534; }
                .info { background: #fefce8; border: 1px solid #fef08a; padding: 16px; border-radius: 8px; margin: 24px 0; }
            </style>
        </head>
        <body>
        <div class="container">
            <div class="header">
                <h1>2026 Tucson International Mariachi Conference</h1>
                <p>Resume Your Registration</p>
            </div>

            <div class="content">
                <h2>Hello, ' . htmlspecialchars($toName) . '!</h2>
                <p>You saved your registration progress. Click the link below to resume:</p>
                <p style="text-align:center; margin:1.5em 0;">
                    <a href="' . htmlspecialchars($resumeLink) . '" style="background:#b22222; color:white; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:bold;">
                        Resume Registration
                    </a>
                </p>
                <p>This link expires in 7 days. If you have questions, reply to this email.</p>
            </div>

            <div class="footer">
                <p>2026 Tucson Mariachi Conference | tucsonmariachi.org</p>
            </div>
        </div>
        </body>
        </html>';

        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(
            ['<br>', '</p>', '</li>', '</tr>', '</td>', '</h', '<table', '<div'],
            ["\n", "\n\n", "\n- ", "\n", " | ", "\n\n", "\n\n---\n", "\n\n"],
            $body
        ));

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Resume email failed for $toEmail: " . $mail->ErrorInfo);
        return false;
    }
}