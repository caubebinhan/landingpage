<?php

namespace simply_static_pro;

use Simply_Static\Integration;

// Runnable UAM integration in Simply Static Pro.
class UAM extends Integration {

    /** @var string */
    protected $id = 'ss-uam';

    /**
     * UAM is opt-in.
     * @var bool
     */
    protected $active_by_default = false;

    public function __construct() {
        $this->name        = __( 'User Access Management (Core)', 'simply-static-pro' );
        $this->description = __( 'Control access to Simply Static pages, menus, and features by assigning a minimum role.', 'simply-static-pro' );
        $this->requires_ui_reload = true;
    }

    /**
     * Register UAM-related hooks when enabled.
     */
    public function run() {
        // Map contexts to capabilities based on UAM selections.
        // Priority 10 so third-parties can override at a later priority.
        add_filter( 'ss_user_capability', [ $this, 'filter_user_capability_via_uam' ], 10, 2 );

        // Feature: Single Export button visibility in editors.
        add_filter( 'ss_can_show_single_export_button', [ $this, 'can_show_single_export_button' ], 10, 1 );

        // Gate Simply Static Pro menus when available.
        // Use very late priority to ensure our removals run after CPT/tax menus are registered
        add_action( 'admin_menu', [ $this, 'maybe_gate_core_menus' ], 9999 );
        add_action( 'admin_menu', [ $this, 'maybe_gate_form_connections_menu' ], 9999 );
        add_action( 'admin_menu', [ $this, 'maybe_gate_builds_menu' ], 9999 );

        // Block direct access to protected admin pages even if user knows the URL.
        add_action( 'admin_init', [ $this, 'protect_direct_admin_access' ] );

        // Provide allowed pages to the SPA when UAM is enabled.
        add_filter( 'ss_allowed_pages', [ $this, 'filter_allowed_pages' ], 10, 2 );
    }

    /**
     * Map a role to a capability used for checks.
     */
    private function map_role_to_cap( $role ) {
        switch ( $role ) {
            case 'administrator':
                return 'manage_options';
            case 'editor':
                return 'publish_pages';
            case 'author':
                return 'edit_published_posts';
            case 'contributor':
                return 'edit_posts';
            case 'subscriber':
            default:
                return 'read';
        }
    }

    /**
     * Filter capability per context via UAM settings.
     */
    public function filter_user_capability_via_uam( $default_cap, $context ) {
        $settings = get_option( 'simply-static' );
        $uam      = isset( $settings['ss_uam_access'] ) && is_array( $settings['ss_uam_access'] ) ? $settings['ss_uam_access'] : array();

        // Ensure admins are never locked out
        if ( current_user_can( 'manage_options' ) ) {
            return $default_cap;
        }

        $map = array(
            'generate'              => isset( $uam['menu_generate'] ) ? $uam['menu_generate'] : null,
            'settings'              => isset( $uam['menu_settings'] ) ? $uam['menu_settings'] : null,
            'diagnostics'           => isset( $uam['menu_diagnostics'] ) ? $uam['menu_diagnostics'] : null,
            'activity-log'          => isset( $uam['activity'] ) ? $uam['activity'] : null,
            'form-connections-menu' => isset( $uam['menu_form_connections'] ) ? $uam['menu_form_connections'] : null,
            'builds-menu'           => isset( $uam['menu_builds'] ) ? $uam['menu_builds'] : null,
            // Admin bar (top-level visibility)
            'adminbar'              => isset( $uam['adminbar'] ) ? $uam['adminbar'] : null,
            // Single export button visibility in editors
            'single-export-button'  => isset( $uam['single_export_button'] ) ? $uam['single_export_button'] : null,
        );

        if ( ! isset( $map[ $context ] ) || empty( $map[ $context ] ) ) {
            return $default_cap;
        }

        $cap = $this->map_role_to_cap( $map[ $context ] );

        return $cap ?: $default_cap;
    }

    /**
     * Used by editor integrations to decide whether to show the Single Export button.
     */
    public function can_show_single_export_button( $default ) {
        // If admin, always allow.
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        // Derive from UAM mapping via the shared context.
        $cap = apply_filters( 'ss_user_capability', 'publish_pages', 'single-export-button' );
        return current_user_can( $cap );
    }

    /**
     * Conditionally hide Simply Static core menu entries (Generate/Settings/Diagnostics)
     * for users who do not meet the required UAM capability.
     *
     * Note: We avoid removing the top-level menu entirely unless the user lacks
     * access to all sub-items, to prevent hiding accessible subpages.
     */
    public function maybe_gate_core_menus() {
        if ( ! is_admin() ) {
            return;
        }

        // Super admins/admins are not gated
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        // Resolve caps for each core screen
        $cap_generate    = apply_filters( 'ss_user_capability', 'publish_pages', 'generate' );
        $cap_settings    = apply_filters( 'ss_user_capability', 'manage_options', 'settings' );
        $cap_diagnostics = apply_filters( 'ss_user_capability', 'publish_pages', 'diagnostics' );

        // Remove submenus the user cannot access.
        // Parent slug for Simply Static menu is the Generate page slug.
        $parent = 'simply-static-generate';

        if ( ! current_user_can( $cap_generate ) ) {
            remove_submenu_page( $parent, 'simply-static-generate' );
        }
        if ( ! current_user_can( $cap_settings ) ) {
            remove_submenu_page( $parent, 'simply-static-settings' );
        }
        if ( ! current_user_can( $cap_diagnostics ) ) {
            remove_submenu_page( $parent, 'simply-static-diagnostics' );
        }

        // If the user lacks all three, hide the whole menu.
        if ( ! current_user_can( $cap_generate ) && ! current_user_can( $cap_settings ) && ! current_user_can( $cap_diagnostics ) ) {
            remove_menu_page( $parent );
        }
    }

    /**
     * Conditionally hide the Form Connections CPT menu (ssp-form) for users without access.
     */
    public function maybe_gate_form_connections_menu() {
        if ( ! is_admin() ) {
            return;
        }
        $post_type = 'ssp-form';
        if ( ! post_type_exists( $post_type ) ) {
            return;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings = get_option( 'simply-static' );
        $uam      = isset( $settings['ss_uam_access'] ) && is_array( $settings['ss_uam_access'] ) ? $settings['ss_uam_access'] : array();
        $role     = isset( $uam['menu_form_connections'] ) ? $uam['menu_form_connections'] : 'administrator';
        $cap      = $this->map_role_to_cap( $role );
        if ( ! current_user_can( $cap ) ) {
            // Remove the top-level CPT menu
            remove_menu_page( 'edit.php?post_type=' . $post_type );
            // Also remove any lingering submenus registered under this CPT
            $this->remove_submenu_entries_by_partial_slug( 'edit.php?post_type=' . $post_type );
        }
    }

    /**
     * Block direct access to Simply Static admin pages, CPTs, and taxonomies
     * for users who do not meet the UAM-defined minimum role.
     */
    public function protect_direct_admin_access() {
        if ( ! is_admin() ) {
            return;
        }

        // Super admins/admins are not gated
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        global $pagenow;

        // Helper to deny access with a clear message.
        $deny = function () {
            wp_die( __( 'You do not have permission to access this page.', 'simply-static-pro' ), 403 );
        };

        // Admin pages registered by Simply Static (SPA wrappers)
        if ( 'admin.php' === $pagenow ) {
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
            if ( 'simply-static-settings' === $page ) {
                $cap = apply_filters( 'ss_user_capability', 'manage_options', 'settings' );
                if ( ! current_user_can( $cap ) ) {
                    $deny();
                }
            } elseif ( 'simply-static-generate' === $page ) {
                $cap = apply_filters( 'ss_user_capability', 'publish_pages', 'generate' );
                if ( ! current_user_can( $cap ) ) {
                    $deny();
                }
            } elseif ( 'simply-static-diagnostics' === $page ) {
                $cap = apply_filters( 'ss_user_capability', 'publish_pages', 'diagnostics' );
                if ( ! current_user_can( $cap ) ) {
                    $deny();
                }
            }
        }

        // CPT: Form Connections
        if ( 'edit.php' === $pagenow ) {
            $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
            if ( 'ssp-form' === $post_type ) {
                $cap = apply_filters( 'ss_user_capability', 'publish_pages', 'form-connections-menu' );
                if ( ! current_user_can( $cap ) ) {
                    $deny();
                }
            }
        }

        // Taxonomy: Builds
        if ( 'edit-tags.php' === $pagenow ) {
            $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
            if ( 'ssp-build' === $taxonomy ) {
                $cap = apply_filters( 'ss_user_capability', 'publish_pages', 'builds-menu' );
                if ( ! current_user_can( $cap ) ) {
                    $deny();
                }
            }
        }
    }

    /**
     * Conditionally hide the Builds CPT/Tax menu for users without access (when Builds are enabled in workflow).
     */
    public function maybe_gate_builds_menu() {
        if ( ! is_admin() ) {
            return;
        }
        // Only when workflow enables Builds.
        $settings = get_option( 'simply-static' );
        if ( empty( $settings['ss_use_builds'] ) ) {
            return;
        }
        // Hide only for non-admins lacking capability.
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }
        $uam  = isset( $settings['ss_uam_access'] ) && is_array( $settings['ss_uam_access'] ) ? $settings['ss_uam_access'] : array();
        $role = isset( $uam['menu_builds'] ) ? $uam['menu_builds'] : 'administrator';
        $cap  = $this->map_role_to_cap( $role );
        if ( ! current_user_can( $cap ) ) {
            // The Builds taxonomy usually appears as a submenu under the CPT parent
            // Remove any submenu entries that point to the Builds taxonomy screen
            $this->remove_submenu_entries_by_partial_slug( 'edit-tags.php?taxonomy=ssp-build' );
            // Also try removing a top-level menu just in case some setup registers it as such
            remove_menu_page( 'edit-tags.php?taxonomy=ssp-build' );
        }
    }

    /**
     * Utility: Remove submenu entries whose slug contains a given partial string.
     * Handles cases where WordPress appends extra query args (e.g., &post_type=ssp-form).
     *
     * @param string $partial
     * @return void
     */
    private function remove_submenu_entries_by_partial_slug( $partial ) {
        if ( empty( $partial ) ) {
            return;
        }
        global $submenu;
        if ( ! is_array( $submenu ) || empty( $submenu ) ) {
            return;
        }
        foreach ( $submenu as $parent => $items ) {
            if ( ! is_array( $items ) ) {
                continue;
            }
            foreach ( $items as $index => $item ) {
                // $item structure: [0] => title, [1] => cap, [2] => slug
                if ( isset( $item[2] ) && false !== strpos( $item[2], $partial ) ) {
                    unset( $submenu[ $parent ][ $index ] );
                }
            }
        }
    }

    /**
     * Compute allowed SPA pages for current user based on UAM roles mapping.
     */
    private function get_allowed_pages_for_current_user( $current_settings = null ) {
        // Accept preloaded settings (from Free) to avoid extra queries, but fall back to option.
        $settings = is_array( $current_settings ) ? $current_settings : get_option( 'simply-static' );
        $uam      = isset( $settings['ss_uam_access'] ) && is_array( $settings['ss_uam_access'] ) ? $settings['ss_uam_access'] : array();

        $pages_to_routes = array(
            'activity'     => '/',
            'diagnostics'  => '/diagnostics',
            'general'      => '/general',
            'deployment'   => '/deployment',
            'forms'        => '/forms',
            'search'       => '/search',
            'optimize'     => '/optimize',
            'workflow'     => '/workflow',
            'utilities'    => '/utilities',
            'integrations' => '/integrations',
            'debug'        => '/debug',
            'uam'          => '/uam',
        );

        $allowed = array();
        foreach ( $pages_to_routes as $page => $route ) {
            $role = isset( $uam[ $page ] ) ? $uam[ $page ] : 'administrator';
            $cap  = $this->map_role_to_cap( $role );
            if ( current_user_can( $cap ) || current_user_can( 'manage_options' ) ) {
                $allowed[] = $route;
            }
        }
        return $allowed;
    }

    /**
     * Filter to provide allowed pages to the SPA.
     *
     * @param array $allowed_pages_default Default pages provided by Free core.
     * @param array $current_settings      Current Simply Static settings array.
     * @return array
     */
    public function filter_allowed_pages( $allowed_pages_default, $current_settings ) {
        // Replace with UAM-computed list including '/uam'.
        return $this->get_allowed_pages_for_current_user( $current_settings );
    }
}
