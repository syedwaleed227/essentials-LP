<?php
/**
 * IHG Landing Page — Contact Form Handler
 * ----------------------------------------
 * Upload this file to:  wp-content/mu-plugins/   (auto-loads, nothing to click)
 *   — OR — paste everything BELOW the opening <?php line into the bottom of your
 *   child theme's functions.php  (wp-content/themes/YOUR-CHILD-THEME/functions.php)
 *
 * Receives the form POST, validates the nonce, sends an email to
 * waleed@hajeirgroup.com, and returns a JSON response.
 */

/* ---- 1. Inject AJAX URL + nonce into the page ---- */
add_action( 'wp_footer', 'ihg_inject_ajax_vars' );
function ihg_inject_ajax_vars() {
    ?>
    <script>
      window.ihg_ajax = {
        url:   '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'ihg_form_nonce' ) ); ?>'
      };
    </script>
    <?php
}

/* ---- 2. Handle form submission (logged-out users) ---- */
add_action( 'wp_ajax_nopriv_ihg_form_submit', 'ihg_handle_form_submit' );
add_action( 'wp_ajax_ihg_form_submit',        'ihg_handle_form_submit' );

function ihg_handle_form_submit() {

    /* --- Nonce check --- */
    if ( ! isset( $_POST['ihg_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ihg_nonce'] ) ), 'ihg_form_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed.' ) );
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
    if ( empty( $full_name ) || empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Invalid submission.' ) );
    }

    /* --- Build email --- */
    $to      = 'waleed@hajeirgroup.com';
    $subject = 'New IHG Lead: ' . $company_name . ' (' . $full_name . ')';

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
    $body .= "Message:\n" . $message . "\n\n";
    $body .= "---\n";
    $body .= "Submitted: " . current_time( 'Y-m-d H:i:s' ) . " (UAE time)\n";
    $body .= "Page: " . ( isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : 'N/A' ) . "\n";

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: IHG Website <no-reply@hajeirgroup.ae>',
        'Reply-To: ' . $full_name . ' <' . $email . '>',
    );

    /* --- Send --- */
    $sent = wp_mail( $to, $subject, $body, $headers );

    if ( $sent ) {
        /* Optional: also send a confirmation email to the lead */
        $confirm_subject = 'We received your enquiry — IHG';
        $confirm_body    = "Dear " . $full_name . ",\n\n"
            . "Thank you for reaching out to Ismail Hajeir Group.\n\n"
            . "We have received your enquiry and a member of our team will contact you "
            . "within one business day to confirm your free discovery call.\n\n"
            . "If you need to reach us urgently, please call: +971 4 252 3232\n\n"
            . "Best regards,\n"
            . "The IHG Team\n"
            . "Ismail Hajeir Group — Dubai & Abu Dhabi\n"
            . "https://hajeirgroup.ae";

        $confirm_headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: IHG — Ismail Hajeir Group <no-reply@hajeirgroup.ae>',
        );

        wp_mail( $email, $confirm_subject, $confirm_body, $confirm_headers );

        wp_send_json_success( array( 'message' => 'Enquiry sent successfully.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Mail sending failed.' ) );
    }
}
