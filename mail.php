<?php
/**
 * mail.php — Contact form handler for evelynramsburgh.com
 *
 * Receives POST from contact.html, validates inputs, and sends email
 * to the configured inbox. Returns JSON so the frontend can show
 * inline feedback without page reload.
 */

/* ─── CONFIG ─────────────────────────────────────────────────────── */
$TO_EMAIL    = 'evelynramsburgh@gmail.com';
$FROM_EMAIL  = 'noreply@evelynramsburgh.com';   // must exist in cPanel OR be a domain alias
$FROM_NAME   = 'evelynramsburgh.com — Contact Form';
$SUBJECT_TAG = '[Portfolio Contact]';

/* ─── HEADERS ────────────────────────────────────────────────────── */
header('Content-Type: application/json; charset=utf-8');

function respond($ok, $msg, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg]);
    exit;
}

/* ─── METHOD CHECK ───────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

/* ─── HONEYPOT (anti-bot) ────────────────────────────────────────── */
// If the hidden "company" field is filled, it's a bot. Fail silently.
if (!empty($_POST['company'])) {
    respond(true, 'Thanks!'); // pretend success so bot doesn't retry
}

/* ─── GATHER + SANITIZE ──────────────────────────────────────────── */
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$website = trim($_POST['website'] ?? '');
$message = trim($_POST['message'] ?? '');

/* ─── VALIDATION ─────────────────────────────────────────────────── */
$errors = [];

if ($name === '' || mb_strlen($name) > 100) {
    $errors[] = 'Please provide your name.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please provide a valid email address.';
}
if ($message === '' || mb_strlen($message) < 10) {
    $errors[] = 'Please share a short message (at least 10 characters).';
}
if (mb_strlen($message) > 5000) {
    $errors[] = 'Message is too long.';
}

if (!empty($errors)) {
    respond(false, implode(' ', $errors), 400);
}

/* ─── HEADER INJECTION PROTECTION ────────────────────────────────── */
// Strip any CR/LF from fields that could be injected into mail headers.
$safeName  = preg_replace('/[\r\n]+/', ' ', $name);
$safeEmail = preg_replace('/[\r\n]+/', '',  $email);

/* ─── BUILD EMAIL ────────────────────────────────────────────────── */
$subject = sprintf('%s %s', $SUBJECT_TAG, $safeName);

$body  = "You received a new message from the portfolio contact form.\n";
$body .= str_repeat('-', 56) . "\n\n";
$body .= "Name:    {$safeName}\n";
$body .= "Email:   {$safeEmail}\n";
if ($website !== '') {
    $safeWebsite = preg_replace('/[\r\n]+/', '', $website);
    $body .= "Website: {$safeWebsite}\n";
}
$body .= "\nMessage:\n{$message}\n";
$body .= "\n" . str_repeat('-', 56) . "\n";
$body .= "Sent: " . date('Y-m-d H:i:s') . "\n";
$body .= "IP:   " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";

$headers  = "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n";
$headers .= "Reply-To: {$safeName} <{$safeEmail}>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

/* ─── SEND ───────────────────────────────────────────────────────── */
$sent = @mail($TO_EMAIL, $subject, $body, $headers, "-f{$FROM_EMAIL}");

if ($sent) {
    respond(true, 'Thanks! Your message is on its way — I\'ll get back to you within a few business days.');
} else {
    respond(false, 'Something went wrong sending your message. Please try again or email me directly at hello@evelynramsburgh.com.', 500);
}
