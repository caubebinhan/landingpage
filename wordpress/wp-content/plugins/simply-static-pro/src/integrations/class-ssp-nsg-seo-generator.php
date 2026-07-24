<?php

namespace simply_static_pro;

use Simply_Static\Integration;
use Simply_Static\Options;
use Simply_Static\Util;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Pro: NSG SEO Generator Integration
 *
 * Full integration logic lives in Pro. The free plugin ships a dummy (pro-only) integration.
 */
class SS_Nsg_SEO_Generator extends Integration {
	/**
	 * Integration ID.
	 * @var string
	 */
	protected $id = 'nsg-seo-generator';

 public function __construct() {
        $this->name = __( 'SEO Generator', 'simply-static-pro' );
        $this->description = __( 'Efficiently crawl, queue and export pSEO websites with this integration.', 'simply-static-pro' );
        $this->active_by_default = true; // Auto-activate when dependency is present unless user saved custom selection
        
        // Ensure the integration is added to saved settings when dependency is present and settings already exist
        add_action( 'admin_init', [ $this, 'maybe_activate_integration' ] );
    }

	/**
	 * Run the integration: ensure NSG crawler is part of active crawlers.
	 * @return void
	 */
	public function run() {
		$this->activate_nsg_crawler();
	}

	/**
	 * Ensure the NSG crawler is part of the active crawlers without overwriting user choices.
	 * Mirrors Elementor/Divi behaviour.
	 * @return void
	 */
	protected function activate_nsg_crawler() {
		$options  = Options::instance();
		$crawlers = $options->get( 'crawlers' );

		// Respect user selections completely:
		// - If crawlers is an array and does NOT contain NSG, treat this as an explicit opt-out and do not re-add it.
		// - If crawlers is null or not an array, do not modify options either; fall back to default is_active logic.
		if ( is_array( $crawlers ) ) {
			if ( in_array( 'nsg-seo-generator', $crawlers, true ) ) {
				// Already selected by user; nothing to do.
				Util::debug_log( 'SEO Generator Crawler already present in user selection; leaving as-is.' );
			} else {
				// User has explicitly not selected the NSG crawler; respect this choice.
				Util::debug_log( 'SEO Generator Crawler not in user selection; respecting opt-out and not adding.' );
			}
		} else {
			// Option not set or not an array; do not modify to avoid overriding defaults.
			Util::debug_log( 'Crawlers option undefined or not an array; not modifying for NSG.' );
		}
	}

	/**
	 * If the NSG plugin is active, auto-activate the integration in settings
	 * (only when an integrations array exists to avoid overriding defaults).
	 * @return void
	 */
	public function maybe_activate_integration() {
		if ( ! $this->dependency_active() ) {
			return;
		}
		$options      = Options::instance();
		$integrations = $options->get( 'integrations' );
		if ( is_array( $integrations ) && ! in_array( $this->id, $integrations, true ) ) {
			$integrations[] = $this->id;
			$options->set( 'integrations', array_values( array_unique( $integrations ) ) );
			$options->save();
			Util::debug_log( 'NSG SEO Generator Integration auto-activated due to active dependency.' );
		}
	}

	/**
	 * Robust dependency check for NSG plugin activation/presence.
	 * @return bool
	 */
	public function dependency_active() {
		// Standard plugin active check
		if ( ! function_exists( 'is_plugin_active' ) ) {
			@include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_active = false;
		if ( function_exists( 'is_plugin_active' ) ) {
			$plugin_active = (
				is_plugin_active( 'nsg-seo-generator/nsg-seo-generator.php' ) ||
				is_plugin_active( 'nsg-seo-generator/plugin.php' ) ||
				is_plugin_active( 'nsg-seo-generator/index.php' )
			);
		}

		// Heuristic checks for NSG presence
		$has_functions = (
			function_exists( 'nsg_seo_generator_get_urls' ) ||
			function_exists( 'nsg_seo_generator_get_generated_urls' ) ||
			function_exists( 'nsg_get_generated_urls' ) ||
			function_exists( 'nsg_seo_get_urls' )
		);

		$has_class = class_exists( '\\NSG_SEO_Generator' ) || class_exists( 'NSG_SEO_Generator' );

		$has_cpts = (
			post_type_exists( 'nw_seo_page' ) ||
			post_type_exists( 'nsg_seo_page' ) ||
			post_type_exists( 'nsg_seo_generator' ) ||
			post_type_exists( 'nsg-seo' ) ||
			post_type_exists( 'nsg-seo-generator' )
		);

		$has_options = (
			get_option( 'nsg_seo_generator_generated_urls', null ) !== null ||
			get_option( 'nsg_seo_generator_urls', null ) !== null ||
			get_option( 'nsg_generated_urls', null ) !== null ||
			get_option( 'nsg_seo_urls', null ) !== null
		);

		return (bool) ( $plugin_active || $has_functions || $has_class || $has_cpts || $has_options );
	}
}
