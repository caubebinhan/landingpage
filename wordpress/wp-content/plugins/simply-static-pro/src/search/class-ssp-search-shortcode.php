<?php

namespace simply_static_pro;

/**
 * Unified shortcode + assets controller for Simply Static search UI (Fuse and Algolia).
 *
 * Responsibilities:
 * - Register [ssp-search] shortcode and render unified markup
 * - Enqueue front-end assets according to selected search type (Fuse or Algolia)
 * - Localize common config used by the front-end scripts
 * - Previously auto-rendered markup in footer when a selector was configured (no longer needed)
 *
 * Indexing and config generation remain in type-specific classes.
 */
class Search_Shortcode {
    /** @var object|null */
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {
        $options    = get_option( 'simply-static' );
        $use_search = $options['use_search'] ?? false;

        if ( ! $use_search ) {
            return;
        }

        // Always register the shortcode; it returns empty string if not applicable
        add_shortcode( 'ssp-search', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'add_search_scripts' ) );
    }

    /**
     * Enqueue assets for the active search type and localize shared config.
     */
    public function add_search_scripts() {
        // Do not enqueue search assets while any visual builder is active.
        if ( Builder_Support::is_builder_editing_context() ) {
            return;
        }
        global $post;
        $options     = get_option( 'simply-static' );
        $use_search  = $options['use_search'] ?? false;
        $search_type = $options['search_type'] ?? 'fuse';
        $use_results = $options['use_search_results_page'] ?? true;
        // Allow advanced override
        $use_results = apply_filters( 'ssp_search_use_results_page', $use_results, $options );

        // Exclude all search assets on ssp-form custom post type views
        $is_ssp_form_view = false;
        if ( function_exists( 'is_singular' ) && is_singular( 'ssp-form' ) ) {
            $is_ssp_form_view = true;
        } elseif ( function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'ssp-form' ) ) {
            $is_ssp_form_view = true;
        } elseif ( isset( $post ) && is_object( $post ) ) {
            // Fallback check in case conditional tags are unreliable in this context
            $is_ssp_form_view = function_exists( 'get_post_type' ) && ( 'ssp-form' === get_post_type( $post ) );
        }

        if ( $is_ssp_form_view ) {
            return;
        }

        if ( ! $use_search ) {
            return;
        }

        $enqueue         = false;
        $use_selector    = false;
        $custom_selector = '';

        if ( function_exists( 'is_search' ) && is_search() ) {
            $enqueue = true;
        }

        if ( 'fuse' === $search_type ) {
            $custom_selector = $options['fuse_selector'] ?? '';
        } else {
            $custom_selector = $options['algolia_selector'] ?? '';
        }

        if ( '' !== $custom_selector ) {
            $enqueue      = true;
            $use_selector = true;
        }

        if ( ! $enqueue && $post && has_shortcode( $post->post_content, 'ssp-search' ) ) {
            $enqueue = true;
        }

        if ( ! $enqueue ) {
            return;
        }

        // Shared CSS
        wp_enqueue_style( 'ssp-search', SIMPLY_STATIC_PRO_URL . '/assets/ssp-search.css', array(), SIMPLY_STATIC_PRO_VERSION, 'all' );

        // Base search results URL (filterable) with a placeholder for JS replacement.
        $base_search_url = function_exists( 'get_search_link' ) ? get_search_link( 'SSP_PLACEHOLDER' ) : home_url( '/?s=SSP_PLACEHOLDER' );
        $base_search_url = apply_filters( 'ssp_search_results_url', $base_search_url );

        // Static search paths
        $static_base = apply_filters( 'ssp_search_static_base', '/__qs/' );
        if ( substr( $static_base, 0, 1 ) !== '/' ) {
            $static_base = '/' . $static_base;
        }
        if ( substr( $static_base, - 1 ) !== '/' ) {
            $static_base .= '/';
        }
        $static_path = apply_filters( 'ssp_search_static_path', '/__qs/index.html' );
        if ( substr( $static_path, 0, 1 ) !== '/' ) {
            $static_path = '/' . $static_path;
        }

        // Render markup now for localization consumers
        $html = $this->render_shortcode();

        // Determine excerpt display option (generic, default false)
        $show_excerpt_opt = isset( $options['search_show_excerpt'] ) ? $options['search_show_excerpt'] : false;

        if ( 'fuse' === $search_type ) {
            // Fuse assets
            wp_enqueue_script( 'ssp-fuse', SIMPLY_STATIC_PRO_URL . '/assets/fuse.js', array(), SIMPLY_STATIC_PRO_VERSION, true );
            wp_enqueue_script( 'ssp-search', SIMPLY_STATIC_PRO_URL . '/assets/ssp-search.js', array( 'ssp-fuse' ), SIMPLY_STATIC_PRO_VERSION, true );
            if ( $use_results ) {
                wp_enqueue_script( 'ssp-search-page', SIMPLY_STATIC_PRO_URL . '/assets/ssp-search-page.js', array( 'ssp-search' ), SIMPLY_STATIC_PRO_VERSION, true );
            }

            // Apply generic + legacy filters for showing excerpt
            $show_excerpt = apply_filters( 'ssp_search_show_excerpt', $show_excerpt_opt );
            $show_excerpt = apply_filters( 'ssp_search_fuse_show_excerpt', $show_excerpt );

            wp_localize_script(
                    'ssp-search',
                    'ssp_search',
                    array(
                            'html'               => $html,
                            'use_selector'       => $use_selector,
                            'is_search'          => ( function_exists( 'is_search' ) && is_search() ),
                            'custom_selector'    => $custom_selector,
                            'selectors'          => apply_filters(
                                    'ssp_search_container_selectors',
                                    array(
                                            "main#primary .site-main",
                                            "main.site-main",
                                            ".site-main",
                                            ".content-area",
                                            "#primary",
                                            ".entry-content",
                                            ".wp-block-post-content",
                                            ".wp-site-blocks main",
                                    )
                            ),
                            'inject_mode'        => apply_filters( 'ssp_search_injection_mode', 'replace' ),
                            'search_url'         => $base_search_url,
                            'search_placeholder' => 'SSP_PLACEHOLDER',
                            'use_static_results_page' => (bool) $use_results,
                            'static_search_path' => $use_results ? $static_path : '',
                            'show_excerpt'       => $show_excerpt,
                    )
            );
        } else {
            // Algolia assets
            $load_remote = apply_filters( 'ssp_algolia_load_remote', true );
            if ( $load_remote ) {
                wp_enqueue_script( 'ssp-algolia', 'https://cdn.jsdelivr.net/algoliasearch/3/algoliasearch.min.js', array(), SIMPLY_STATIC_PRO_VERSION, true );
                wp_enqueue_script( 'ssp-algolia-autocomplete', 'https://cdn.jsdelivr.net/autocomplete.js/0/autocomplete.min.js', array(), SIMPLY_STATIC_PRO_VERSION, true );
            } else {
                wp_enqueue_script( 'ssp-algolia', SIMPLY_STATIC_PRO_URL . '/assets/algolia-search.min.js', array(), SIMPLY_STATIC_PRO_VERSION, true );
                wp_enqueue_script( 'ssp-algolia-autocomplete', SIMPLY_STATIC_PRO_URL . '/assets/algolia-autocomplete.min.js', array(), SIMPLY_STATIC_PRO_VERSION, true );
            }
            wp_enqueue_script( 'ssp-algolia-script', SIMPLY_STATIC_PRO_URL . '/assets/ssp-search-algolia.js', array(
                    'ssp-algolia-autocomplete',
                    'ssp-algolia'
            ), SIMPLY_STATIC_PRO_VERSION, true );
            if ( $use_results ) {
                wp_enqueue_script( 'ssp-search-page', SIMPLY_STATIC_PRO_URL . '/assets/ssp-search-page.js', array( 'ssp-algolia-script' ), SIMPLY_STATIC_PRO_VERSION, true );
            }

            // Build frontend Algolia config from options for dynamic pages
            $algolia_front_config = array(
                    'app_id'      => isset( $options['algolia_app_id'] ) ? $options['algolia_app_id'] : '',
                    'api_key'     => isset( $options['algolia_search_api_key'] ) ? $options['algolia_search_api_key'] : '',
                // Search key only
                    'index'       => isset( $options['algolia_index'] ) ? $options['algolia_index'] : '',
                    'selector'    => $custom_selector,
                    'use_excerpt' => apply_filters( 'ssp_algolia_use_excerpt', true ),
            );

            // Apply generic + legacy filters for showing excerpt (Algolia)
            $show_excerpt = apply_filters( 'ssp_search_show_excerpt', $show_excerpt_opt );
            $show_excerpt = apply_filters( 'ssp_search_algolia_show_excerpt', $show_excerpt );

            wp_localize_script(
                    'ssp-algolia-script',
                    'ssp_search',
                    array(
                            'html'               => $html,
                            'use_selector'       => $use_selector,
                            'is_search'          => ( function_exists( 'is_search' ) && is_search() ),
                            'custom_selector'    => $custom_selector,
                            'selectors'          => apply_filters(
                                    'ssp_search_container_selectors',
                                    array(
                                            "main#primary .site-main",
                                            "main.site-main",
                                            ".site-main",
                                            ".content-area",
                                            "#primary",
                                            ".entry-content",
                                            ".wp-block-post-content",
                                            ".wp-site-blocks main",
                                    )
                            ),
                            'inject_mode'        => apply_filters( 'ssp_search_injection_mode', 'replace' ),
                            'search_url'         => $base_search_url,
                            'search_placeholder' => 'SSP_PLACEHOLDER',
                            'use_static_results_page' => (bool) $use_results,
                            'static_search_path' => $use_results ? $static_path : '',
                            'show_excerpt'       => $show_excerpt,
                            'algolia_config'     => $algolia_front_config,
                    )
            );
        }
    }

    /**
     * Render unified search form markup, reading generic options and applying generic (and legacy) filters.
     */
    public function render_shortcode() {
        $options     = get_option( 'simply-static' );
        $use_search  = $options['use_search'] ?? false;
        $search_type = $options['search_type'] ?? 'fuse';

        // Exclude output entirely on ssp-form custom post type views
        if (
                ( function_exists( 'is_singular' ) && is_singular( 'ssp-form' ) ) ||
                ( function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'ssp-form' ) )
        ) {
            return '';
        }

        if ( ! $use_search || ( 'fuse' !== $search_type && 'algolia' !== $search_type ) ) {
            return '';
        }

        ob_start();

        // UI customization
        $opts = $options; // same option
        // Default: do not show submit button unless explicitly enabled in settings or via filter
        $default_show        = false;
        $default_text        = __( 'Search', 'simply-static-pro' );
        $default_placeholder = __( 'Search..', 'simply-static-pro' );

        $show_submit = isset( $opts['search_show_submit'] )
                ? $opts['search_show_submit']
                : ( isset( $opts['search_fuse_show_submit'] ) ? $opts['search_fuse_show_submit'] : $default_show );

        $submit_text = isset( $opts['search_submit_text'] ) && '' !== $opts['search_submit_text']
                ? $opts['search_submit_text']
                : ( isset( $opts['search_fuse_submit_text'] ) && '' !== $opts['search_fuse_submit_text'] ? $opts['search_fuse_submit_text'] : $default_text );

        $placeholder = isset( $opts['search_placeholder'] ) && '' !== $opts['search_placeholder']
                ? $opts['search_placeholder']
                : ( isset( $opts['search_fuse_placeholder'] ) && '' !== $opts['search_fuse_placeholder'] ? $opts['search_fuse_placeholder'] : $default_placeholder );

        // Apply generic filters and legacy fuse filters for back-compat
        $show_submit = apply_filters( 'ssp_search_show_submit', $show_submit );
        $show_submit = apply_filters( 'ssp_search_fuse_show_submit', $show_submit );

        $submit_text = apply_filters( 'ssp_search_submit_text', $submit_text );
        $submit_text = apply_filters( 'ssp_search_fuse_submit_text', $submit_text );

        $placeholder = apply_filters( 'ssp_search_placeholder', $placeholder );
        $placeholder = apply_filters( 'ssp_search_fuse_placeholder', $placeholder );

        $placeholder = sanitize_text_field( $placeholder );
        $submit_aria = wp_strip_all_tags( $submit_text );
        ?>
        <div class="ssp-search">
            <form class="search-form">
                <div class="form-row">
                    <div class="search-input-container">
                        <input class="search-input" name="search-input"
                               placeholder="<?php echo esc_attr( $placeholder ); ?>"
                               autocomplete="off"
                               data-noresult="<?php esc_attr_e( 'No results found.', 'simply-static-pro' ); ?>">
                        <div class="search-auto-complete"></div>
                    </div>
                    <?php if ( $show_submit ) : ?>
                        <button type="submit" class="search-submit"
                                aria-label="<?php echo esc_attr( $submit_aria ); ?>"><?php echo wp_kses_post( $submit_text ); ?></button>
                    <?php endif; ?>
                </div>
            </form>
            <div class="result"></div>
        </div>
        <?php
        $html = ob_get_clean();

        return $html;
    }
}
