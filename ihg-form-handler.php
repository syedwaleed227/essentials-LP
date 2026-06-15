<?php
/**
 * Plugin Name: IHG Landing Page Form Handler
 * Description: Receives submissions from the IHG landing-page forms (AJAX action "ihg_form_submit") and emails them to the office.
 * Version:     1.0
 * Author:      Ismail Hajeir Group
 *
 * INSTALL (easiest): upload this single file to  wp-content/mu-plugins/
 *   (create the "mu-plugins" folder if it doesn't exist). It activates
 *   automatically — nothing else to click. Matches your manual-upload workflow.
 *
 * ALTERNATIVE: paste everything BELOW the "----" line into your (child) theme's
 *   functions.php instead.
 */

if (!defined('ABSPATH')) { exit; } // no direct access

/* ==========================================================================
 * SMTP WITHOUT ANY PLUGIN
 * --------------------------------------------------------------------------
 * This routes WordPress email straight through your mailbox's SMTP server,
 * so leads arrive reliably WITHOUT installing WP Mail SMTP (or any plugin).
 *
 * >>> Fill in the 4 values marked CHANGE-ME with your hajeirgroup.com mailbox
 *     settings (your email host/cPanel/Google Workspace provides these).
 *     Do NOT share the password with anyone — just put it here on the server.
 * ========================================================================== */
add_action('phpmailer_init', 'ihg_configure_smtp');
function ihg_configure_smtp($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host       = 'smtp.hajeirgroup.com';      // CHANGE-ME: your SMTP host
    $phpmailer->Port       = 587;                          // 587 = TLS, 465 = SSL
    $phpmailer->SMTPSecure = 'tls';                        // 'tls' for 587, 'ssl' for 465
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = 'waleed@hajeirgroup.com';     // CHANGE-ME: SMTP username
    $phpmailer->Password   = 'YOUR-MAILBOX-PASSWORD';      // CHANGE-ME: SMTP password / app password
    // Send "from" your own domain so the mail server accepts it (avoids spam):
    $phpmailer->setFrom('waleed@hajeirgroup.com', 'IHG Website');
}

/* -------------------------------------------------------------------------- */

add_action('wp_ajax_ihg_form_submit',        'ihg_handle_form_submit'); // logged-in users
add_action('wp_ajax_nopriv_ihg_form_submit', 'ihg_handle_form_submit'); // public visitors

function ihg_handle_form_submit() {

    // === 1. Where submissions are emailed (change if needed) ===
    $to = 'waleed@hajeirgroup.com';

    // === 2. Optional honeypot anti-spam ===
    // If you ask me to add the hidden "website" field to the forms, bots that
    // fill it are silently dropped here. (Harmless if the field isn't present.)
    if (!empty($_POST['website'])) {
        wp_send_json_success(); // pretend success, ignore the bot
    }

    // === 3. Collect + sanitize the fields the forms send ===
    $full_name     = sanitize_text_field( wp_unslash($_POST['full_name']     ?? '') );
    $company_name  = sanitize_text_field( wp_unslash($_POST['company_name']  ?? '') );
    $email         = sanitize_email(      wp_unslash($_POST['email']         ?? '') );
    $phone         = sanitize_text_field( wp_unslash($_POST['phone']         ?? '') );
    $business_type = sanitize_text_field( wp_unslash($_POST['business_type'] ?? '') );
    $turnover      = sanitize_text_field( wp_unslash($_POST['turnover']      ?? '') );
    $services      = sanitize_text_field( wp_unslash($_POST['services']      ?? '') );
    $how_heard     = sanitize_text_field( wp_unslash($_POST['how_heard']     ?? '') );
    $message       = sanitize_textarea_field( wp_unslash($_POST['message']   ?? '') );

    // === 4. Basic server-side validation ===
    if (strlen($full_name) < 2 || !is_email($email) || strlen($phone) < 6) {
        wp_send_json_error(array('message' => 'Invalid submission.'));
    }

    // === 5. Build and send the notification email ===
    $subject = 'New website enquiry — ' . $full_name
             . ' (' . ($company_name !== '' ? $company_name : 'no company') . ')';

    $body = implode("\n", array(
        'Full name:     ' . $full_name,
        'Company:       ' . $company_name,
        'Email:         ' . $email,
        'Phone:         ' . $phone,
        'Business type: ' . $business_type,
        'Turnover:      ' . $turnover,
        'Services:      ' . $services,
        'Heard via:     ' . $how_heard,
        '',
        'Message:',
        ($message !== '' ? $message : '(none)'),
        '',
        '-- Sent from ' . home_url('/'),
    ));

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $full_name . ' <' . $email . '>',
    );

    // Optional: also store every lead in the WP dashboard as a private post.
    // Uncomment to enable.
    // wp_insert_post(array(
    //     'post_type'   => 'ihg_lead',
    //     'post_status' => 'private',
    //     'post_title'  => $subject,
    //     'post_content'=> $body,
    // ));

    if ( wp_mail($to, $subject, $body, $headers) ) {
        wp_send_json_success();                                   // -> {"success":true}
    } else {
        wp_send_json_error(array('message' => 'Mail could not be sent.'));
    }
}
