<?php
/**
 * IHG Landing Page — Contact Form Handler  (NO PLUGIN REQUIRED)
 * ============================================================================
 * Handles both forms (hero quick-form + main contact form) on every landing
 * page. Validates, emails the lead to waleed@hajeirgroup.com, sends the visitor
 * a confirmation, and returns the JSON the page JS expects.
 *
 * INSTALL — pick ONE:
 *   A) Upload this whole file to:  wp-content/mu-plugins/
 *      (create the "mu-plugins" folder if needed — it auto-loads, no theme edit)
 *   B) Or paste everything BELOW this comment block into the bottom of your
 *      child theme's functions.php
 *
 * IMPORTANT — your landing pages are uploaded as static .html files, which
 * WordPress does NOT render. So the wp_footer nonce injection (section 1) only
 * appears on real WordPress pages. The handler therefore treats the nonce as
 * OPTIONAL: WordPress-rendered pages get full CSRF protection, and the static
 * pages still work (protected by a honeypot + same-site origin check).
 * ============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // no direct access

/* ----------------------------------------------------------------------------
 * 0) SMTP WITHOUT A PLUGIN — reliable delivery without WP Mail SMTP.
 *    Fill in the 4 values marked CHANGE-ME with your mailbox's SMTP settings.
 *    (cPanel "Connect Devices", Google Workspace App Password, or M365.)
 * -------------------------------------------------------------------------- */
add_action( 'phpmailer_init', 'ihg_configure_smtp' );
function ihg_configure_smtp( $phpmailer ) {
    $phpmailer->isSMTP();
    $phpmailer->Host       = 'smtp.hajeirgroup.com';      // CHANGE-ME: SMTP host
    $phpmailer->Port       = 587;                          // 587 = TLS, 465 = SSL
    $phpmailer->SMTPSecure = 'tls';                        // 'tls' (587) or 'ssl' (465)
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = 'waleed@hajeirgroup.com';     // CHANGE-ME: SMTP username
    $phpmailer->Password   = 'YOUR-MAILBOX-PASSWORD';      // CHANGE-ME: mailbox/app password
    // The "From" MUST be on the same domain you authenticate with, or the mail
    // server will reject it / it lands in spam:
    $phpmailer->setFrom( 'waleed@hajeirgroup.com', 'IHG Website' );
}

/* ----------------------------------------------------------------------------
 * 1) Inject AJAX url + nonce — only runs on WordPress-rendered pages.
 *    Harmless (just absent) on the static .html landing pages.
 * -------------------------------------------------------------------------- */
add_action( 'wp_footer', 'ihg_inject_ajax_vars' );
function ihg_inject_ajax_vars() { ?>
    <script>
      window.ihg_ajax = {
        url:   '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'ihg_form_nonce' ) ); ?>'
      };
    </script>
<?php }

/* ----------------------------------------------------------------------------
 * 2) Handle the submission (logged-in + logged-out visitors)
 * -------------------------------------------------------------------------- */
add_action( 'wp_ajax_nopriv_ihg_form_submit', 'ihg_handle_form_submit' );
add_action( 'wp_ajax_ihg_form_submit',        'ihg_handle_form_submit' );

function ihg_handle_form_submit() {

    /* --- Honeypot: silently drop bots. (Active only if you add a hidden
           "website" field to the forms — ask me and I'll add it.) --- */
    if ( ! empty( $_POST['website'] ) ) {
        wp_send_json_success( array( 'message' => 'ok' ) );
    }

    /* --- Nonce: enforced ONLY when present, so static pages still work
           while WordPress-rendered pages keep full CSRF protection. --- */
    if ( ! empty( $_POST['ihg_nonce'] ) &&
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ihg_nonce'] ) ), 'ihg_form_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed.' ) );
    }

    /* --- Same-site guard for the no-nonce (static page) case: the request must
           come from your own domain. --- */
    $referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
    if ( empty( $_POST['ihg_nonce'] ) ) {
        $allowed_host = 'hajeirgroup.ae';
        $ref_host     = $referer ? wp_parse_url( $referer, PHP_URL_HOST ) : '';
        if ( $ref_host && stripos( $ref_host, $allowed_host ) === false ) {
            wp_send_json_error( array( 'message' => 'Invalid origin.' ) );
        }
    }

    /* --- Sanitize inputs --- */
    $full_name     = sanitize_text_field( wp_unslash( $_POST['full_name']     ?? '' ) );
    $company_name  = sanitize_text_field( wp_unslash( $_POST['company_name']  ?? '' ) );
    $email         = sanitize_email(      wp_unslash( $_POST['email']         ?? '' ) );
    $phone         = sanitize_text_field( wp_unslash( $_POST['phone']         ?? '' ) );
    $business_type = sanitize_text_field( wp_unslash( $_POST['business_type'] ?? '' ) );
    $turnover      = sanitize_text_field( wp_unslash( $_POST['turnover']      ?? '' ) );
    $services      = sanitize_text_field( wp_unslash( $_POST['services']      ?? '' ) );
    $how_heard     = sanitize_text_field( wp_unslash( $_POST['how_heard']     ?? '' ) );
    $message       = sanitize_textarea_field( wp_unslash( $_POST['message']   ?? '' ) );

    /* --- Basic validation --- */
    if ( strlen( $full_name ) < 2 || empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Invalid submission.' ) );
    }

    /* --- Build the office notification email --- */
    $to      = 'waleed@hajeirgroup.com';
    $subject = 'New IHG Lead: ' . ( $company_name !== '' ? $company_name : 'no company' ) . ' (' . $full_name . ')';

    $body  = "New enquiry from IHG landing page\n";
    $body .= "===================================\n\n";
    $body .= "Full Name:        " . $full_name     . "\n";
    $body .= "Company:          " . $company_name  . "\n";
    $body .= "Email:            " . $email         . "\n";
    $body .= "Phone:            " . $phone         . "\n";
    $body .= "Business Type:    " . $business_type . "\n";
    $body .= "Annual Turnover:  " . $turnover      . "\n";
    $body .= "Services:         " . $services      . "\n";
    $body .= "How They Heard:   " . $how_heard     . "\n\n";
    $body .= "Message:\n" . ( $message !== '' ? $message : '(none)' ) . "\n\n";
    $body .= "---\n";
    $body .= "Submitted: " . current_time( 'Y-m-d H:i:s' ) . " (site time)\n";
    $body .= "Page: " . ( $referer !== '' ? $referer : 'N/A' ) . "\n";

    // "From" is set globally in ihg_configure_smtp(); we only set Reply-To here
    // so you can reply straight to the lead.
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $full_name . ' <' . $email . '>',
    );

    $sent = wp_mail( $to, $subject, $body, $headers );

    if ( $sent ) {

        /* --- Auto-reply confirmation to the visitor --- */
        $confirm_subject = 'We received your enquiry — Ismail Hajeir Group';
        $confirm_body    = "Dear " . $full_name . ",\n\n"
            . "Thank you for reaching out to Ismail Hajeir Group.\n\n"
            . "We have received your enquiry and a member of our team will contact you "
            . "within one business day.\n\n"
            . "If you need to reach us urgently, please call: +971 4 252 3232\n\n"  // CHANGE-ME: verify this number
            . "Best regards,\n"
            . "The IHG Team\n"
            . "Ismail Hajeir Group — Dubai & Abu Dhabi\n"
            . "https://hajeirgroup.ae";

        $confirm_headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        wp_mail( $email, $confirm_subject, $confirm_body, $confirm_headers );

        wp_send_json_success( array( 'message' => 'Enquiry sent successfully.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Mail sending failed.' ) );
    }
}
