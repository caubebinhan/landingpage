<?php
// Minimal standalone template for ssp-form single pages that does not depend on theme header/footer templates.
// We call wp_head() / wp_footer() directly to ensure all plugin assets (e.g., jQuery, Fluent Forms) are printed.

// Get related metadata.
$post_id = get_the_ID();
$meta    = array(
    'form_type'            => get_post_meta( $post_id, 'form_type', true ),
    'form_plugin'          => get_post_meta( $post_id, 'form_plugin', true ),
    'form_id'              => get_post_meta( $post_id, 'form_id', true ),
    'form_shortcode'       => get_post_meta( $post_id, 'form_shortcode', true ),
    'form_custom_css'      => get_post_meta( $post_id, 'form_custom_css', true ),
    'form_success_message' => get_post_meta( $post_id, 'form_success_message', true ),
    'form_error_message'   => get_post_meta( $post_id, 'form_error_message', true ),
    'form_use_redirect'    => get_post_meta( $post_id, 'form_use_redirect', true ),
    'form_redirect_url'    => get_post_meta( $post_id, 'form_redirect_url', true ),
);
?><!DOCTYPE html>
<html <?php language_attributes(); ?> >
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
    <style>
        html { margin: 0 !important; height: auto !important; min-height: 0 !important; }
        body { margin: 0 !important; background: transparent; height: auto !important; min-height: 0 !important; overflow: visible !important; }

        /* Hide site chrome inside the iframe */
        header, footer, #footer, #header, #wpadminbar { display: none !important; }
        hr { display: none; }
        .elementor-message { display: none; }

        /* Defensive: ensure form controls and submit buttons are visible */
        textarea, input, select { visibility: visible !important; }
        textarea { display: block !important; min-height: 100px; }
        button[type="submit"], input[type="submit"], .ff-btn-submit {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /* Ensure outer wrapper never clips content */
        .ssp-form-embed { overflow: visible !important; }
    
        <?php if ( ! empty( $meta['form_custom_css'] ) ) : ?>
        <?php echo wp_kses_post( $meta['form_custom_css'] ); ?>
        <?php endif; ?>
    </style>
</head>
<body <?php body_class( 'ssp-form-embed-body' ); ?> >
    <div class="ssp-form-embed">
        <?php if ( ! empty( $meta['form_type'] ) && 'embedded' === $meta['form_type'] ) : ?>
            <?php if ( ! empty( $meta['form_shortcode'] ) ) : ?>
                <?php echo do_shortcode( $meta['form_shortcode'] ); ?>
            <?php endif; ?>
        <?php else : ?>
            <p><?php esc_html_e( 'You have configured a webhook, this template will not be used.', 'simply-static-pro' ); ?></p>
        <?php endif; ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
