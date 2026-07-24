<?php

namespace simply_static_pro\database\form_entries\admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Entries_Admin {
    private static $instance;

    public static function get_instance() : Entries_Admin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], PHP_INT_MAX );
    }

    public function add_menu() {
        // Respect global Simply Static settings: only show Form Entries when enabled
        $options      = get_option( 'simply-static' );
        $use_forms    = ! empty( $options['use_forms'] );
        // Default behavior: save entries is enabled unless explicitly disabled
        $save_entries = array_key_exists( 'save_form_entries', (array) $options )
            ? (bool) $options['save_form_entries']
            : true;

        if ( ! $use_forms || ! $save_entries ) {
            return; // Do not register submenu when disabled
        }

        // Attach under Simply Static menu (matching helper placement)
        $menu_hook = add_submenu_page(
            'simply-static-generate',
            __( 'Form Entries', 'simply-static-pro' ),
            __( 'Form Entries', 'simply-static-pro' ),
            apply_filters( 'ss_user_capability', 'publish_pages', 'form_entries' ),
            'simply-static-entries',
            [ $this, 'render' ],
            4
        );

        add_action( "admin_print_scripts-{$menu_hook}", [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        // Prefer local React bundle within Pro. Fallback to helper bundle if not built locally yet.
        // Local build location:
        // - src/form/form-entries/admin/build/*
        $primary_js_rel  = 'src/form/form-entries/admin/build/index.js';
        $primary_css_rel = 'src/form/form-entries/admin/build/index.css';
        $primary_js_abs  = SIMPLY_STATIC_PRO_PATH . $primary_js_rel;
        $primary_css_abs = SIMPLY_STATIC_PRO_PATH . $primary_css_rel;

        $script_handle = 'ssp-form-entries';
        $style_handle  = 'ssp-form-entries-style';

        if ( file_exists( $primary_js_abs ) ) {
            // Build URL relative to the plugin root file to avoid duplicated path segments like /src/src/ in URLs.
            $plugin_root_file = SIMPLY_STATIC_PRO_PATH . 'simply-static-pro.php';
            wp_enqueue_script( $script_handle, plugins_url( $primary_js_rel, $plugin_root_file ), array(
                'wp-api', 'wp-components', 'wp-element', 'wp-api-fetch', 'wp-data', 'wp-i18n', 'wp-block-editor',
                'react', 'react-dom', 'react-jsx-runtime'
            ), SIMPLY_STATIC_PRO_VERSION, true );
        } elseif ( defined( 'SSS_URL' ) ) {
            // Fallback to helper bundle if present, to avoid blocking UI until we migrate/build locally.
            wp_enqueue_script( $script_handle, trailingslashit( SSS_URL ) . 'includes/Admin/build/index.js', array(
                'wp-api', 'wp-components', 'wp-element', 'wp-api-fetch', 'wp-data', 'wp-i18n', 'wp-block-editor',
                'react', 'react-dom', 'react-jsx-runtime'
            ), defined( 'SSS_VERSION' ) ? SSS_VERSION : SIMPLY_STATIC_PRO_VERSION, true );
        }

        // If we fell back to the helper bundle (or any bundle that renders encoded HTML),
        // patch the rendered markup so entries show as real HTML instead of escaped text.
        // This script is safe to run alongside our local build and will no-op there because
        // the local build already uses dangerouslySetInnerHTML.
        $inline_fix = <<<'JS'
        (function(){
            function decodeHtml(html){
                var txt = document.createElement('textarea');
                txt.innerHTML = html;
                return txt.value;
            }
            function fixNode(node){
                if (!node) return;
                try{
                    // Look for our wrapper elements produced by the server formatters
                    var wrappers = node.querySelectorAll('.sss-entry-data');
                    for (var i=0; i<wrappers.length; i++){
                        var el = wrappers[i];
                        // If inner text looks like encoded HTML (starts with &lt;div ) then decode it
                        var text = el.textContent || '';
                        if (text.indexOf('<div') !== -1 || text.indexOf('&lt;div') !== -1){
                            // Prefer reading raw HTML string; textContent will contain entities if escaped
                            var raw = el.innerHTML;
                            if (raw && (raw.indexOf('&lt;') !== -1 || raw.indexOf('&gt;') !== -1)){
                                el.innerHTML = decodeHtml(raw);
                            }
                        }
                    }
                }catch(e){/* noop */}
            }
            function observe(){
                var root = document.getElementById('static-form-entries');
                if (!root) return;
                // Initial pass after mount
                fixNode(root);
                var mo = new MutationObserver(function(mutations){
                    for (var j=0; j<mutations.length; j++){
                        var m = mutations[j];
                        if (m.type === 'childList'){
                            for (var k=0; k<m.addedNodes.length; k++){
                                var n = m.addedNodes[k];
                                if (n && n.nodeType === 1){ fixNode(n); }
                            }
                        } else if (m.type === 'characterData'){
                            var p = m.target && m.target.parentNode;
                            if (p && p.nodeType === 1){ fixNode(p); }
                        }
                    }
                });
                mo.observe(root, { subtree: true, childList: true, characterData: true });
            }
            if (document.readyState === 'complete' || document.readyState === 'interactive'){
                setTimeout(observe, 0);
            } else {
                document.addEventListener('DOMContentLoaded', observe);
            }
        })();
        JS;
        wp_add_inline_script( $script_handle, $inline_fix );

        $args = [
            'initial' => '/',
            'logo'    => defined( 'SIMPLY_STATIC_URL' ) ? SIMPLY_STATIC_URL . '/assets/simply-static-logo.svg' : '',
        ];
        wp_localize_script( $script_handle, 'form_entries_options', $args );

        if ( file_exists( $primary_css_abs ) ) {
            $plugin_root_file = SIMPLY_STATIC_PRO_PATH . 'simply-static-pro.php';
            wp_enqueue_style( $style_handle, plugins_url( $primary_css_rel, $plugin_root_file ), array( 'wp-components' ), SIMPLY_STATIC_PRO_VERSION );
        } elseif ( defined( 'SSS_URL' ) ) {
            wp_enqueue_style( $style_handle, trailingslashit( SSS_URL ) . 'includes/Admin/build/index.css', array( 'wp-components' ), defined( 'SSS_VERSION' ) ? SSS_VERSION : SIMPLY_STATIC_PRO_VERSION );
        }
    }

    public function render() {
        echo '<div id="static-form-entries"></div>';
    }
}
