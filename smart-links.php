<?php

add_shortcode('crm_tag_button', function ($atts) {

    $atts = shortcode_atts([
        'text'   => 'Continue',
        'action' => '',
        'tag_id' => '',
        'url'    => '/',
        'class'  => '',
    ], $atts, 'crm_tag_button');

    if (!is_user_logged_in()) {
        return '';
    }

    $text   = sanitize_text_field($atts['text']);
    $action = sanitize_key($atts['action']);
    $tag_id = absint($atts['tag_id']);
    $url    = trim((string) $atts['url']);
    $class  = trim((string) $atts['class']);

    if (!in_array($action, ['add', 'remove'], true) || !$tag_id) {
        return '';
    }

    // Detect external URL
    $is_external = false;

    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host  = wp_parse_url($url, PHP_URL_HOST);

        if ($url_host && $site_host && strtolower($url_host) !== strtolower($site_host)) {
            $is_external = true;
        }
    }

    // If external, current tab should refresh to same page after action
    $redirect = $is_external
        ? wp_unslash($_SERVER['REQUEST_URI'] ?? '/')
        : $url;

    $form_id = 'crm_tag_form_' . wp_generate_password(8, false, false);

    ob_start();
    ?>
    <form
        method="post"
        id="<?php echo esc_attr($form_id); ?>"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        style="display:inline;"
        <?php if ($is_external): ?>
            data-external-url="<?php echo esc_url($url); ?>"
        <?php endif; ?>
    >
        <input type="hidden" name="action" value="crm_tag_action">
        <input type="hidden" name="crm_tag_action_type" value="<?php echo esc_attr($action); ?>">
        <input type="hidden" name="crm_tag_id" value="<?php echo esc_attr($tag_id); ?>">
        <input type="hidden" name="crm_redirect" value="<?php echo esc_attr($redirect); ?>">

        <?php wp_nonce_field('crm_tag_action|' . $action . '|' . $tag_id); ?>

        <button type="submit" class="<?php echo esc_attr($class); ?>">
            <?php echo esc_html($text); ?>
        </button>
    </form>

    <?php if ($is_external): ?>
    <script>
    (function() {
        const form = document.getElementById('<?php echo esc_js($form_id); ?>');
        if (!form) return;

        form.addEventListener('submit', function() {
            const url = form.getAttribute('data-external-url');
            if (url) {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        });
    })();
    </script>
    <?php endif; ?>

    <?php
    return ob_get_clean();
});
