<?php

if ( !function_exists( 'ssp_fs' ) ) {
    // Create a helper function for easy SDK access.
    function ssp_fs() {
        global $ssp_fs;
        if ( !isset( $ssp_fs ) ) {
            // Activate multisite network integration.
            if ( !defined( 'WP_FS__PRODUCT_8420_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_8420_MULTISITE', true );
            }
            // Define custom fs_normalize_path before Freemius loads to handle
            // Simply Static Pro being installed outside the plugins directory.
            if ( !function_exists( 'fs_normalize_path' ) ) {
                function fs_normalize_path(  $path  ) {
                    if ( str_contains( $path, ABSPATH . 'wp-content/simply-static-pro/simply-static-pro.php' ) ) {
                        return wp_normalize_path( ABSPATH . 'wp-content/simply-static-pro/simply-static-pro.php' );
                    }
                    return wp_normalize_path( $path );
                }

            }
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';
            $ssp_fs = fs_dynamic_init( array(
                'id'              => '8420',
                'slug'            => 'simply-static-pro',
                'premium_slug'    => 'simply-static-pro',
                'type'            => 'plugin',
                'public_key'      => 'pk_08b5bb42cc86b87f14937bab4075b',
                'is_premium'      => true,
                'is_premium_only' => true,
                'has_addons'      => false,
                'has_paid_plans'  => true,
                'has_affiliation' => false,
                'menu'            => array(
                    'contact' => false,
                    'support' => false,
                ),
                'is_live'         => true,
            ) );
        }
        return $ssp_fs;
    }

    // Init Freemius.
    ssp_fs();
    // Signal that SDK was initiated.
    do_action( 'ssp_fs_loaded' );
    /**
     * Remove freemius pages.
     *
     * @param bool $is_visible indicates if visible or not.
     * @param int $submenu_id current submenu id.
     *
     * @return bool
     */
    function ssp_fs_is_submenu_visible(  $is_visible, $submenu_id  ) {
        return false;
    }

    ssp_fs()->add_filter(
        'is_submenu_visible',
        'ssp_fs_is_submenu_visible',
        10,
        2
    );
    /**
     * Add custom icon for Freemius.
     *
     * @return string
     */
    function ssp_fs_custom_icon() {
        return SIMPLY_STATIC_PRO_PATH . '/assets/simply-static-icon.png';
    }

    ssp_fs()->add_filter( 'plugin_icon', 'ssp_fs_custom_icon' );
    /**
     * Remove deactivation survey.
     *
     * @return bool
     */
    ssp_fs()->add_filter( 'show_deactivation_feedback_form', '__return_false' );
    ssp_fs()->add_filter( 'show_deactivation_subscription_cancellation', '__return_false' );
    /**
     * Clean up Simply Static Pro settings after uninstallation
     *
     * @return void
     */
    function ssp_fs_cleanup() {
        global $wpdb;
        // Delete all form integrations.
        $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type='ssp-form'" );
        // Delete all builds.
        $terms = get_terms( array(
            'taxonomy'   => 'ssp-build',
            'fields'     => 'ids',
            'hide_empty' => false,
        ) );
        if ( !empty( $terms ) ) {
            foreach ( $terms as $term_id ) {
                wp_delete_term( $term_id, 'ssp-build' );
            }
        }
    }

    ssp_fs()->add_action( 'after_uninstall', 'ssp_fs_cleanup' );
}