<?php

namespace simply_static_pro\Crawler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Multilingual Crawler class
 *
 * This crawler detects translations created with Polylang, WPML, and TranslatePress.
 * It is only active if one of these plugins is activated AND Simply Static Pro is installed.
 */
class Multilingual_Crawler extends \Simply_Static\Crawler\Crawler {

	/**
	 * Crawler ID.
	 * @var string
	 */
	protected $id = 'multilingual';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name = __( 'Multilingual URLs', 'simply-static-pro' );
		$this->description = __( 'Detects translations created with Polylang, WPML, and TranslatePress.', 'simply-static-pro' );
		$this->active_by_default = false; // Only active if dependencies are met
	}

	/**
	 * Check if the crawler is active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// Check if any of the supported plugins is active
		$is_wpml_active = is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' );
		$is_polylang_active = is_plugin_active( 'polylang/polylang.php' ) || is_plugin_active( 'polylang-pro/polylang.php' );
		$is_translatepress_active = is_plugin_active( 'translatepress-multilingual/index.php' );

		if ( ! $is_wpml_active && ! $is_polylang_active && ! $is_translatepress_active ) {
			return false;
		}

		// Call parent method to check if the crawler is enabled in the settings
		return parent::is_active();
	}

	/**
	 * Detect multilingual URLs.
	 *
	 * @return array List of URLs
	 */
	public function detect() : array {
		$urls = [];

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// Detect WPML translations
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$urls = array_merge( $urls, $this->detect_wpml_urls() );
		}

		// Detect Polylang translations
		if ( is_plugin_active( 'polylang/polylang.php' ) || is_plugin_active( 'polylang-pro/polylang.php' ) ) {
			$urls = array_merge( $urls, $this->detect_polylang_urls() );
		}

		// Detect TranslatePress translations
		if ( is_plugin_active( 'translatepress-multilingual/index.php' ) ) {
			$urls = array_merge( $urls, $this->detect_translatepress_urls() );
		}

		return array_unique( $urls );
	}

	/**
	 * Detect WPML translations.
	 *
	 * @return array List of URLs
	 */
	private function detect_wpml_urls() : array {
		$urls = [];

		// Check if WPML functions are available
		if ( function_exists( 'icl_get_languages' ) ) {
			$languages = icl_get_languages( 'skip_missing=0' );
			
			if ( ! empty( $languages ) ) {
				// Get all published posts and pages
				$args = [
					'post_type'      => [ 'post', 'page' ],
					'post_status'    => 'publish',
					'posts_per_page' => -1,
				];
				
				$query = new \WP_Query( $args );
				
				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						$post_id = get_the_ID();
						
						// Get translations for this post
						foreach ( $languages as $language ) {
							$translated_post_id = icl_object_id( $post_id, get_post_type( $post_id ), false, $language['code'] );
							
							if ( $translated_post_id ) {
								$translated_url = get_permalink( $translated_post_id );
								if ( $translated_url ) {
									$urls[] = $translated_url;
								}
							}
						}
					}
					
					wp_reset_postdata();
				}
			}
		}

		return $urls;
	}

	/**
	 * Detect Polylang translations.
	 *
	 * @return array List of URLs
	 */
	private function detect_polylang_urls() : array {
		$urls = [];

		// Check if Polylang functions are available
		if ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_get_post_translations' ) ) {
			$languages = pll_languages_list();
			
			if ( ! empty( $languages ) ) {
				// Get all published posts and pages
				$args = [
					'post_type'      => [ 'post', 'page' ],
					'post_status'    => 'publish',
					'posts_per_page' => -1,
				];
				
				$query = new \WP_Query( $args );
				
				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						$post_id = get_the_ID();
						
						// Get translations for this post
						$translations = pll_get_post_translations( $post_id );
						
						foreach ( $translations as $lang => $translated_post_id ) {
							$translated_url = get_permalink( $translated_post_id );
							if ( $translated_url ) {
								$urls[] = $translated_url;
							}
						}
					}
					
					wp_reset_postdata();
				}
			}
		}

		return $urls;
	}

	/**
	 * Detect TranslatePress translations.
	 *
	 * @return array List of URLs
	 */
	private function detect_translatepress_urls() : array {
		$urls = [];

		// Check if TranslatePress functions are available
		if ( function_exists( 'trp_get_languages' ) ) {
			$trp = \TRP_Translate_Press::get_trp_instance();
			$settings = $trp->get_component( 'settings' );
			$settings_array = $settings->get_settings();
			
			if ( ! empty( $settings_array['publish-languages'] ) ) {
				$languages = $settings_array['publish-languages'];
				
				// Get all published posts and pages
				$args = [
					'post_type'      => [ 'post', 'page' ],
					'post_status'    => 'publish',
					'posts_per_page' => -1,
				];
				
				$query = new \WP_Query( $args );
				
				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						$post_url = get_permalink();
						
						// TranslatePress uses URL parameters or subdirectories for translations
						foreach ( $languages as $language ) {
							if ( $language === $settings_array['default-language'] ) {
								continue; // Skip default language as it's already included
							}
							
							// TranslatePress can use different URL modification methods
							if ( isset( $settings_array['url-modification'] ) ) {
								switch ( $settings_array['url-modification'] ) {
									case 'subdirectory':
										$translated_url = trailingslashit( home_url() ) . $language . '/' . ltrim( str_replace( home_url(), '', $post_url ), '/' );
										break;
									case 'domain':
										// Domain-based translations are more complex and may require additional handling
										continue 2; // Skip to next language
									default: // 'parameter'
										$translated_url = add_query_arg( 'trp-edit-translation', $language, $post_url );
										break;
								}
								
								$urls[] = $translated_url;
							}
						}
					}
					
					wp_reset_postdata();
				}
			}
		}

		return $urls;
	}
}