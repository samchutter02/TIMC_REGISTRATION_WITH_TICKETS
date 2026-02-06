<?php
session_start();

if (empty($_SESSION['cart']) || $_SESSION['cart']['payment_method'] !== 'purchase_order') {
    header("Location: index.php");
    exit;
}

$cart = $_SESSION['cart'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration Confirmation – Purchase Order</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; line-height: 1.6; }
    .box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 32px; text-align: center; }
    h1 { color: #166534; margin-bottom: 8px; }
    .highlight { font-weight: bold; color: #166534; }
    .info { background: #fefce8; border: 1px solid #fef08a; padding: 16px; border-radius: 8px; margin: 24px 0; }
  </style>
</head>
<body>

  <div class="box">
    <h1>Thank You!</h1>
    <p style="font-size: 1.2rem; margin: 16px 0 32px;">
      Your registration for <strong><?= htmlspecialchars($cart['group_name']) ?></strong><br>
      has been successfully received.
    </p>

    <div class="info">
      <strong>Payment Method:</strong> Purchase Order / Invoice<br><br>
      <strong>Transaction Number:</strong> <span class="highlight"><?= htmlspecialchars($cart['po_number']) ?></span><br>
      <strong>Total Amount Due:</strong> $<?= number_format($cart['total_cost'], 2) ?><br>
      <strong>Canta Tickets:</strong> <?= htmlspecialchars($cart['number_of_Canta_tickets'] ?? 0) ?> ($<?= number_format(10 * ($cart['number_of_Canta_tickets'] ?? 0), 2) ?>)<br>
      <strong>Garibaldi Tickets:</strong> <?= htmlspecialchars($cart['number_of_Garibaldi_tickets'] ?? 0) ?> ($<?= number_format(10 * ($cart['number_of_Garibaldi_tickets'] ?? 0), 2) ?>)<br><br>
      <strong>Next step:</strong> A confirmation email with invoice details has been sent to the director's email address.<br>
      Please submit your purchase order and payment by the published deadline.
    </div>

    <p style="color: #555; font-size: 0.95rem; margin-top: 32px;">
      You will be redirected to the main site in a few seconds...<br>
      <a href="https://tucsonmariachi.org">Go to Tucson International Mariachi Conference website</a>
    </p>
  </div>

  <script>
    setTimeout(() => {
      window.location.href = "https://tucsonmariachi.org";
    }, 8000);
  </script>

</body>
</html>