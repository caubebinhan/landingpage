<?php

namespace simply_static_pro;

/**
 * Class to handle setup task.
 */
class Multisite_Queue_Task extends \Simply_Static\Task {

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'multisite_queue';

	/**
	 * Do the initial setup for generating a static archive
	 *
	 * @return boolean true this always completes in one run, so returns true.
	 */
	public function perform() {
		$message = __( 'Waiting in queue...', 'simply-static-pro' );
		$this->save_status_message( $message, 'setup' );

		if ( Multisite_Integration::can_run_export( get_current_blog_id() ) || Multisite_Integration::is_queue_empty() ) {
			$message = __( 'Starting soon...', 'simply-static-pro' );
			$this->save_status_message( $message, 'setup' );

			// Making sure it's set just in case.
			Multisite_Integration::set_queued_export_as_running( get_current_blog_id() );
			return true;
		}


		return false;
	}
}
