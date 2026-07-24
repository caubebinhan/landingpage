<?php

namespace simply_static_pro\commands;

/**
 * Manage Shortpixel integration
 */
class Shortpixel {

	protected function maybe_switch_blog( $options ) {
		if ( ! is_multisite() ) {
			return;
		}

		if ( ! isset( $options['blog_id'] ) || ! absint( $options['blog_id'] ) ) {
			return;
		}

		switch_to_blog( absint( $options['blog_id'] ) );
	}

	protected function maybe_restore_blog( $options ) {
		if ( ! is_multisite() ) {
			return;
		}

		if ( ! isset( $options['blog_id'] ) || ! absint( $options['blog_id'] ) ) {
			return;
		}

		restore_current_blog();
	}

	/**
	 * Reset all metadata saved on images for Shortpixel.
	 *
	 * ## OPTIONS
	 *
	 * [--blog_id=<blog_id>]
	 * : Blog ID. Used only on multisite.
	 *
	 * ## EXAMPLES
	 *
	 *     wp simply-static shortpixel reset_meta
	 *
	 * @when after_wp_load
	 */
	function reset_meta( $args, $options ) {
		$this->maybe_switch_blog( $options );

		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_queued_shortpixel' OR meta_key = '_optimized_shortpixel';" );

		$this->maybe_restore_blog( $options );
	}

}