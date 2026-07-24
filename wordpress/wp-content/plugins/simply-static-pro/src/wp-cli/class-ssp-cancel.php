<?php

namespace simply_static_pro\commands;

use Simply_Static\Plugin;

/**
 * Cancel the current Simply Static export via WP-CLI.
 */
class Cancel extends CLI_Command {

    protected $blog_id = 0;

    public function get_description() {
        return 'Cancel the currently running Simply Static export.';
    }

    protected function get_command_name() {
        return 'cancel';
    }

    public function get_synopsis() {
        $synopsis = [];

        if ( is_multisite() ) {
            $synopsis[] = array(
                'type'        => 'assoc',
                'name'        => 'blog_id',
                'description' => 'Blog ID. If empty, it\'ll use the current blog.',
                'optional'    => true,
                'repeating'   => false,
            );
        }

        return $synopsis;
    }

    public function set_blog_id( $blog_id ) {
        if ( ! is_multisite() ) {
            $this->blog_id = get_current_blog_id();
            return;
        }

        if ( $blog_id && (int) $blog_id !== get_current_blog_id() ) {
            switch_to_blog( (int) $blog_id );
            $this->blog_id = (int) $blog_id;
        } else {
            $this->blog_id = get_current_blog_id();
        }
    }

    public function restore_blog() {
        if ( ! is_multisite() ) {
            return;
        }

        restore_current_blog();
    }

    public function __invoke( $args, $options ) {
        try {
            // Set target blog in multisite if provided.
            if ( isset( $options['blog_id'] ) ) {
                $this->set_blog_id( $options['blog_id'] );
            } else {
                $this->set_blog_id( get_current_blog_id() );
            }

            $job = Plugin::instance()->get_archive_creation_job();

            if ( method_exists( $job, 'is_job_done' ) && $job->is_job_done() ) {
                \WP_CLI::warning( 'No running export found to cancel.' );
            } else {
                $job->cancel();
                \WP_CLI::success( 'Export cancelled.' );
            }

            if ( isset( $options['blog_id'] ) ) {
                $this->restore_blog();
            }
        } catch ( \Exception $e ) {
            \WP_CLI::error( $e->getMessage() );
        }
    }
}
