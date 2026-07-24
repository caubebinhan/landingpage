<?php

namespace simply_static_pro;

use JShrink\Minifier;

class Minifer_JS implements Minifer {

	/**
	 * @throws \Exception
	 */
	public function minify( $content ) {
		if ( ! $content || trim( $content ) === '' || ! is_string( $content ) ) {
			return $content;
		}

		// Try to minify with JShrink, but handle potential errors
		try {
			// Set options to preserve important comments
			$options = array('flaggedComments' => true);
			$output = Minifier::minify($content, $options);
			return $output;
		} catch (\Exception $e) {
			// If minification fails, return the original content
			return $content;
		}
	}
}
