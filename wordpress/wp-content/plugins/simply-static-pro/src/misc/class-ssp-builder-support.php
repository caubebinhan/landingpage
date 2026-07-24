<?php

namespace simply_static_pro;

/**
 * Centralized helpers for page builder compatibility.
 *
 * Provides detection for when a visual builder/editor is active so the plugin
 * can avoid injecting DOM/meta that might interfere with the editor lifecycle.
 */
class Builder_Support {
    /**
     * Detect if a page builder editing session is active that we should not interfere with.
     *
     * Supports popular frontend builders and can be extended via filters:
     * - Divi
     * - Elementor
     * - WPBakery Page Builder (Visual Composer)
     * - Oxygen Builder
     * - Bricks Builder
     *
     * Filters:
     * - ssp_skip_in_builder: return bool to force result (override).
     * - ssp_is_builder_context: filter the detected boolean for custom builders.
     *
     * @return bool
     */
    public static function is_builder_editing_context(): bool {
        // Allow 3rd parties (or tests) to override immediately.
        $skip_in_builder = apply_filters( 'ssp_skip_in_builder', null );
        if ( null !== $skip_in_builder ) {
            return (bool) $skip_in_builder;
        }

        $is_builder = false;

        // --- Divi Visual Builder ---
        // Common Divi flag on frontend editor.
        if ( isset( $_GET['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $is_builder = true;
        }

        // Divi helper (exists when Divi theme/plugin is active).
        if ( function_exists( '\\et_core_is_fb_enabled' ) && \et_core_is_fb_enabled() ) {
            $is_builder = true;
        }

        // Fallback: try other known Divi API if present.
        if ( function_exists( '\\et_builder_is_enabled' ) && \et_builder_is_enabled() && isset( $_GET['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $is_builder = true;
        }

        // --- Elementor ---
        // Query arg used on frontend preview/editor iframe.
        if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $is_builder = true;
        }
        // Runtime API checks when Elementor is loaded.
        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            $elementor = \Elementor\Plugin::$instance ?? null;
            if ( $elementor ) {
                // Editor edit mode (usually inside editor iframe).
                if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) ) {
                    if ( $elementor->editor->is_edit_mode() ) {
                        $is_builder = true;
                    }
                }
                // Preview mode.
                if ( isset( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) ) {
                    if ( $elementor->preview->is_preview_mode() ) {
                        $is_builder = true;
                    }
                }
            }
        }

        // --- WPBakery Page Builder (Visual Composer) ---
        if ( isset( $_GET['vc_editable'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $is_builder = true;
        }
        if ( isset( $_GET['vc_action'] ) && 'vc_inline' === $_GET['vc_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $is_builder = true;
        }
        if ( function_exists( '\\vc_is_inline' ) && \vc_is_inline() ) {
            $is_builder = true;
        }

        // --- Oxygen Builder ---
        if ( isset( $_GET['ct_builder'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $is_builder = true;
        }
        if ( defined( 'SHOW_CT_BUILDER' ) && constant( 'SHOW_CT_BUILDER' ) ) {
            $is_builder = true;
        }
        if ( defined( 'OXYGEN_IFRAME' ) && constant( 'OXYGEN_IFRAME' ) ) {
            $is_builder = true;
        }

        // --- Bricks Builder ---
        // Bricks commonly uses the `bricks` query arg (e.g., builder/preview/run).
        if ( isset( $_GET['bricks'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $is_builder = true;
        }
        if ( function_exists( '\\bricks_is_builder' ) && \bricks_is_builder() ) {
            $is_builder = true;
        }

        // Allow external overrides and additional builders.
        return (bool) apply_filters( 'ssp_is_builder_context', $is_builder );
    }
}
