<?php

namespace simply_static_pro;

class Minifer_HTML implements Minifer {
	public function minify( $content ) {
		$options  = \Simply_Static\Options::instance();
		$minified = $content;

		// Skip minification if content is empty
		if ( ! $minified || trim( $minified ) === '' ) {
			return $minified;
		}

		// Extract all script tags before minification to preserve their content
		$script_tags = array();
		$script_placeholder = '<!--SCRIPT_PLACEHOLDER_%d-->';
		$script_count = 0;

		$minified = preg_replace_callback(
			'/<script([^>]*)>([\s\S]*?)<\/script>/i',
			function ( $matches ) use ( &$script_tags, &$script_count, $script_placeholder ) {
				$script_tags[$script_count] = $matches[0];
				return sprintf($script_placeholder, $script_count++);
			},
			$minified
		);

		// Extract all style tags before minification to preserve their content
		$style_tags = array();
		$style_placeholder = '<!--STYLE_PLACEHOLDER_%d-->';
		$style_count = 0;

		$minified = preg_replace_callback(
			'/<style([^>]*)>([\s\S]*?)<\/style>/i',
			function ( $matches ) use ( &$style_tags, &$style_count, $style_placeholder ) {
				$style_tags[$style_count] = $matches[0];
				return sprintf($style_placeholder, $style_count++);
			},
			$minified
		);

		// Basic HTML minification using regex patterns
		$search = array(
			'/\>[^\S ]+/s',     // Strip whitespaces after tags, except space
			'/[^\S ]+\</s',     // Strip whitespaces before tags, except space
			'/(\s)+/s',         // Shorten multiple whitespace sequences
			'/<!--(?!SCRIPT_PLACEHOLDER|STYLE_PLACEHOLDER)(.|\s)*?-->/' // Remove HTML comments except our placeholders
		);

		$replace = array(
			'>',
			'<',
			'\\1',
			''
		);

		// No need for special handling of specific script tags anymore
		// We now use a more universal approach to detect and preserve problematic script tags

		// Apply the basic minification
		$minified = preg_replace($search, $replace, $minified);

		// Handle the option to leave quotes
		if ( ! $options->get('minify_html_leave_quotes') ) {
			// Remove quotes from tag attributes when possible
			$minified = preg_replace_callback(
				'/<([a-z0-9]+)((?:\s+[a-z0-9\-]+=[\'"][^\'"]+[\'"])*)>/i',
				function($matches) {
					// Don't touch the tag if it doesn't have attributes
					if (empty($matches[2])) {
						return $matches[0];
					}

					// Don't remove quotes from script tags
					if (strtolower($matches[1]) === 'script') {
						return $matches[0];
					}

					// Process attributes
					$attributes = preg_replace_callback(
						'/\s+([a-z0-9\-]+)=[\'"]([^\'"]+)[\'"]/i',
						function($attr) {
							$name = $attr[1];
							$value = $attr[2];

							// Check if the attribute value can have quotes omitted
							if (preg_match('/^[a-z0-9\-_]+$/i', $value)) {
								return ' ' . $name . '=' . $value;
							}

							// Keep quotes for other values
							return ' ' . $name . '="' . $value . '"';
						},
						$matches[2]
					);

					return '<' . $matches[1] . $attributes . '>';
				},
				$minified
			);
		}

		// Reinsert extracted style tags
		if (isset($style_count) && $style_count > 0) {
			$minify_css = $options->get( 'minify_inline_css' );
			for ($i = 0; $i < $style_count; $i++) {
				$style_tag = $style_tags[$i];
				if ($minify_css) {
					$style_tag = preg_replace_callback(
						'#<style(.*?)>(.*?)</style>#is',
						array( $this, 'minify_style_tag_match' ),
						$style_tag
					);
				}
				$minified = str_replace(
					sprintf($style_placeholder, $i),
					$style_tag,
					$minified
				);
			}
		}

		// Reinsert extracted script tags
		if (isset($script_count) && $script_count > 0) {
			$minify_js = $options->get( 'minify_inline_js' );
			for ($i = 0; $i < $script_count; $i++) {
				$script_tag = $script_tags[$i];
				if ($minify_js) {
					$script_tag = preg_replace_callback(
						'/<script([^>]*)>([\s\S]*?)<\/script>/i',
						array( $this, 'minify_script_tag_match' ),
						$script_tag
					);
				}
				$minified = str_replace(
					sprintf($script_placeholder, $i),
					$script_tag,
					$minified
				);
			}
		}

		// No need to restore specific script tags
		// Our universal approach preserves problematic script tags from the beginning

		return $minified;
	}

	public function minify_inline_js( $content ) {
		if ( \strpos( $content, '</script>' ) !== false ) {
			// Use a more robust pattern to match script tags
			// This pattern handles nested content better by using a more specific approach
			// Use a non-greedy match for script content to avoid issues with nested tags
			$content = preg_replace_callback(
				'/<script([^>]*)>([\s\S]*?)<\/script>/i',
				array( $this, 'minify_script_tag_match' ),
				$content
			);
		}

		return $content;
	}

	public function minify_script_tag_match( $matches ) {
		// Check for patterns that might indicate a problematic script
		$script_content = $matches[2];

		// Don't minify if the script contains specific patterns that might break during minification
		if ($this->should_preserve_script($script_content, $matches[1])) {
			// Return the original script tag with its formatting preserved
			return $matches[0];
		}

		return '<script' . $matches[1] . '>' . $this->minify_js( $matches[2] ) . '</script>';
	}

	/**
	 * Determines if a script should be preserved in its original form
	 * 
	 * @param string $content The script content
	 * @param string $attributes The script tag attributes
	 * @return bool Whether the script should be preserved
	 */
	private function should_preserve_script($content, $attributes) {
		// 1. Check for specific IDs that are known to be problematic
		// This is just one example - the method uses more universal patterns below
		if (strpos($attributes, 'wp-block-template-skip-link-js-after') !== false) {
			return true;
		}

		// 2. Check for complex comment patterns that might break during minification
		$has_complex_comments = false;

		// Check for multi-line comments with specific formatting
		if (preg_match('/\/\*\s*\n\s*\*/', $content)) {
			$has_complex_comments = true;
		}

		// Check for comments that contain code examples or important formatting
		if (preg_match('/\/\/.*?\n/', $content)) {
			$has_complex_comments = true;
		}

		// 3. Check for specific code patterns that might be problematic
		$has_complex_code = false;

		// Check for immediately invoked function expressions (IIFE) with specific formatting
		if (preg_match('/\(\s*function\s*\(\s*\)\s*\{.*?\}\s*\(\s*\)\s*\)/', $content)) {
			$has_complex_code = true;
		}

		// Check for complex regex patterns
		if (preg_match('/=\s*\/.*?\/[gim]*/', $content)) {
			$has_complex_code = true;
		}

		return $has_complex_comments || $has_complex_code;
	}

	public function minify_js( $content ) {
		// For all scripts, use the standard minifier
		$minifer = new Minifer_JS();
		return $minifer->minify( $content );
	}

	public function minify_css( $content ) {
		$minifer = new Minifer_CSS();

		return $minifer->minify( $content );
	}

	public function minify_style_attribute_match( $matches ) {
		return '<' . $matches[1] . ' style=' . $matches[2] . $this->minify_css( $matches[3] ) . $matches[2];
	}

	public function minify_style_tag_match( $matches ) {
		return '<style' . $matches[1] . '>' . $this->minify_css( $matches[2] ) . '</style>';
	}

	public function minify_inline_css( $content ) {
		// Minify inline CSS declaration(s)
		if ( \strpos( $content, ' style=' ) !== false ) {
			$content = (string) \preg_replace_callback(
				'#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s',
				array( $this, 'minify_style_attribute_match' ),
				$content
			);
		}

		if ( \strpos( $content, '</style>' ) !== false ) {
			$content = (string) \preg_replace_callback(
				'#<style(.*?)>(.*?)</style>#is',
				array( $this, 'minify_style_tag_match' ),
				$content
			);
		}

		return $content;
	}
}
