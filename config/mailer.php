<?php
/**
 * Email helper.
 *
 * Uses PHP's built-in mail() function, which works out of the box on most
 * real shared hosting (cPanel/Hostinger/etc. all ship a local mail
 * transport). It will silently fail in local dev environments with no
 * mail server configured — that's expected and handled gracefully below:
 * failures are logged, never thrown at the visitor, and never block the
 * reservation/order/contact flow that triggered the email.
 *
 * To upgrade to SMTP (Gmail, SendGrid, Mailgun, etc.) in production,
 * this is the ONLY function that needs to change: swap the mail() call
 * for a PHPMailer SMTP send. Every other part of the app just calls
 * send_email() / the send_*_email() helpers below and doesn't care how
 * the message is actually transported.
 */

/**
 * Low-level send. Returns true/false, never throws, always logs failures.
 */
function send_email(string $to, string $subject, string $htmlBody, ?string $fromEmail = null, ?string $fromName = null): bool
{
    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("send_email: invalid recipient address skipped: $to");
            return false;
        }

        $fromEmail = $fromEmail ?: 'no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $fromName  = $fromName ?: 'Restaurant';

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $encodedFromName = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($fromName, 'UTF-8') : $fromName;
        $headers[] = sprintf('From: %s <%s>', $encodedFromName, $fromEmail);
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $sent = @mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers));

        if (!$sent) {
            error_log("send_email: failed to send '{$subject}' to {$to} (this is expected in local/dev environments without a configured mail server)");
        }

        return $sent;
    } catch (\Throwable $e) {
        // Email must NEVER break the reservation/order/contact flow that triggered it.
        error_log('send_email: unexpected error, swallowed to protect the calling flow: ' . $e->getMessage());
        return false;
    }
}

/**
 * Shared, simple branded HTML wrapper for all outgoing emails.
 */
function email_template(string $siteName, string $title, string $bodyHtml): string
{
    return '
    <div style="font-family: Arial, Helvetica, sans-serif; max-width: 560px; margin: 0 auto; background: #ffffff;">
      <div style="background: #e11d48; padding: 24px; text-align: center;">
        <h1 style="color: #ffffff; margin: 0; font-size: 22px;">' . htmlspecialchars($siteName) . '</h1>
      </div>
      <div style="padding: 28px; color: #333333; line-height: 1.6;">
        <h2 style="margin-top: 0; font-size: 18px;">' . htmlspecialchars($title) . '</h2>
        ' . $bodyHtml . '
      </div>
      <div style="padding: 16px; text-align: center; color: #999999; font-size: 12px;">
        This is an automated message from ' . htmlspecialchars($siteName) . '.
      </div>
    </div>';
}

function get_site_settings($conn): array
{
    return mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings LIMIT 1")) ?: ['site_name' => 'Restaurant', 'email' => null];
}

/** Reservation confirmation, sent to the customer right after booking. */
function send_reservation_confirmation_email($conn, array $reservation): bool
{
    $settings = get_site_settings($conn);
    $body = "
        <p>Hi " . htmlspecialchars($reservation['full_name']) . ",</p>
        <p>Thanks for your reservation request! Here's what we've got:</p>
        <table style=\"width:100%; border-collapse: collapse; margin: 16px 0;\">
          <tr><td style=\"padding:6px 0; color:#888;\">Date</td><td style=\"padding:6px 0; text-align:right;\"><strong>" . htmlspecialchars($reservation['reservation_date']) . "</strong></td></tr>
          <tr><td style=\"padding:6px 0; color:#888;\">Time</td><td style=\"padding:6px 0; text-align:right;\"><strong>" . htmlspecialchars(substr($reservation['reservation_time'], 0, 5)) . "</strong></td></tr>
          <tr><td style=\"padding:6px 0; color:#888;\">Guests</td><td style=\"padding:6px 0; text-align:right;\"><strong>" . (int) $reservation['guests'] . "</strong></td></tr>
        </table>
        <p>Status: <strong>Pending confirmation</strong> — we'll follow up shortly to confirm your table.</p>
    ";
    return send_email($reservation['email'], 'Your reservation request at ' . $settings['site_name'], email_template($settings['site_name'], 'Reservation Received', $body), $settings['email'] ?? null, $settings['site_name']);
}

/** Order confirmation, sent to the customer right after checkout. */
function send_order_confirmation_email($conn, int $orderId): bool
{
    $order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id=" . (int) $orderId));
    if (!$order) return false;
    $settings = get_site_settings($conn);
    $currency = $settings['currency_symbol'] ?? '$';

    $itemsRes = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id=" . (int) $orderId);
    $rows = '';
    while ($item = mysqli_fetch_assoc($itemsRes)) {
        $sub = $item['price'] * $item['quantity'];
        $rows .= "<tr>
            <td style=\"padding:6px 0;\">" . htmlspecialchars($item['title']) . " &times; " . (int) $item['quantity'] . "</td>
            <td style=\"padding:6px 0; text-align:right;\">{$currency}" . number_format($sub, 2) . "</td>
        </tr>";
    }

    $body = "
        <p>Hi " . htmlspecialchars($order['full_name']) . ",</p>
        <p>Thanks for your order! Your order <strong>#" . str_pad($order['id'], 6, '0', STR_PAD_LEFT) . "</strong> has been received.</p>
        <table style=\"width:100%; border-collapse: collapse; margin: 16px 0;\">
          {$rows}
          <tr><td style=\"padding:10px 0; border-top:2px solid #eee; font-weight:bold;\">Total</td><td style=\"padding:10px 0; border-top:2px solid #eee; text-align:right; font-weight:bold;\">{$currency}" . number_format($order['total_price'], 2) . "</td></tr>
        </table>
        <p>Payment method: <strong>" . htmlspecialchars($order['payment_method']) . "</strong></p>
        <p>Status: <strong>" . htmlspecialchars($order['status']) . "</strong></p>
        <p>Delivering to: " . nl2br(htmlspecialchars($order['address'])) . "</p>
    ";
    return send_email($order['email'], 'Order Confirmation #' . str_pad($order['id'], 6, '0', STR_PAD_LEFT) . ' - ' . $settings['site_name'], email_template($settings['site_name'], 'Order Confirmed', $body), $settings['email'] ?? null, $settings['site_name']);
}

/** Acknowledgement sent to a customer right after they submit the contact form. */
function send_contact_acknowledgement_email($conn, string $name, string $email): bool
{
    $settings = get_site_settings($conn);
    $body = "
        <p>Hi " . htmlspecialchars($name) . ",</p>
        <p>Thanks for reaching out to us! We've received your message and will get back to you within 2 hours.</p>
    ";
    return send_email($email, 'We received your message - ' . $settings['site_name'], email_template($settings['site_name'], 'Message Received', $body), $settings['email'] ?? null, $settings['site_name']);
}

/** Sent to the customer when an admin replies to their contact message. */
function send_message_reply_email($conn, array $message): bool
{
    $settings = get_site_settings($conn);
    $body = "
        <p>Hi " . htmlspecialchars($message['full_name']) . ",</p>
        <p>You have a reply to your message \"" . htmlspecialchars($message['subject'] ?: 'General Inquiry') . "\":</p>
        <blockquote style=\"border-left:3px solid #e11d48; margin:16px 0; padding:8px 16px; color:#555; background:#faf7ff;\">" . nl2br(htmlspecialchars($message['reply'])) . "</blockquote>
    ";
    return send_email($message['email'], 'Reply to your message - ' . $settings['site_name'], email_template($settings['site_name'], 'You Have a Reply', $body), $settings['email'] ?? null, $settings['site_name']);
}
