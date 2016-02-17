<?php
/*
Plugin Name: Publishing Checklist
Version: 0.2.0-alpha
Description: Pre-flight your posts.
Author: Fusion Engineering
Author URI: http://fusion.net/
Plugin URI: https://github.com/fusioneng/publishing-checklist
Text Domain: publishing-checklist
Domain Path: /languages
*/

define( 'PUBLISHING_CHECKLIST_VERSION', '0.2.0-alpha' );

class Publishing_Checklist {

	private static $instance;
	private $tasks = array();

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Publishing_Checklist;
			self::$instance->setup_actions();
			do_action( 'publishing_checklist_init' );

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				require_once dirname( __FILE__ ) . '/inc/class-cli-command.php';
			}
		}

		return self::$instance;
	}

	/**
	 * Set up actions for the plugin
	 */
	private function setup_actions() {

		add_action( 'publishing_checklist_enqueue_scripts', array( $this, 'action_publishing_checklist_enqueue_scripts' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'action_post_submitbox_misc_actions_render_checklist' ) );
		add_action( 'transition_post_status', array( $this, 'action_transition_post_status' ), 1, 3 );
		add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );

		// Must be called before list table is rendered, but after all tasks have been registered.
		add_action( 'admin_head', function() {

			// Find all post types with tasks associated.
			$post_types = array();
			foreach ( $this->tasks as $task ) {
				$post_types = array_unique( array_merge( $post_types, $task['post_type'] ) );
			}

			// Add post list table columns
			foreach ( $post_types as $post_type ) {
				add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'action_manage_posts_custom_column' ), 10, 2 );
				add_filter( "manage_{$post_type}_posts_columns", array( $this, 'filter_manage_posts_columns' ), 99 );
			}

		} );

	}

	/**
	 * Register a validation task for our publishing checklist
	 *
	 * @param string $id Unique identifier for the task (can be arbitrary, as long as it doesn't conflict with others)
	 * @param string $label Human-friendly label for the task
	 * @param mixed $callback Callable function or method to indicate whether or not the task has been complete
	 * @param string $explanation A longer description as to what needs to be accomplished for the task
	 */
	public function register_task( $id, $args = array() ) {

		$defaults = array(
			'label'          => $id,
			'callback'       => '__return_false',
			'explanation'    => '',
			'post_type'      => array(),
			'required'       => false,
			);
		$args = array_merge( $defaults, $args );

		$this->tasks[ $id ] = $args;
	}

	/**
	 * Render the checklist in the publish submit box
	 */
	public function action_post_submitbox_misc_actions_render_checklist() {
		$post_id = get_the_ID();
		$tasks_completed = $this->evaluate_checklist( $post_id );
		if ( $tasks_completed ) {
			do_action( 'publishing_checklist_enqueue_scripts' );
			echo $this->get_template_part( 'post-submitbox-misc-actions',
				array(
					'tasks' => $tasks_completed['tasks'],
					'completed_tasks' => $tasks_completed['completed'],
				)
			);
		}
	}

	/**
	 * On publishing a post, make sure that it fulfills all required tasks.
	 *
	 * If there are one or more tasks with "required" status that are not
	 * completed, the status transition will not be performed, and a warning
	 * message alerting the user of the conditions which must be met in order
	 * to publish will be displayed instead.
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param object $post
	 */
	public function action_transition_post_status( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status && 'future' !== $new_status ) {
			return;
		}

		$required_tasks = $this->evaluate_checklist( $post->ID, true );
		if ( count( $required_tasks['completed'] ) === count( $required_tasks['tasks'] ) ) {
			return;
		}

		$post->post_status = $old_status;
		wp_update_post( $post );

		$redirect_url = add_query_arg( array( 'checklist' => 'fail' ), get_edit_post_link( $post->ID, 'raw' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render admin notices, if a failed task is preventing publishing.
	 *
	 * If there are one or more tasks with "required" status that are not completed
	 * on attempting to publish a post, a warning message alerting the user of the
	 * conditions which must be met in order to publish will be displayed.
	 *
	 */
	public function action_admin_notices() {
		if ( empty( $_GET['checklist'] ) || 'fail' !== $_GET['checklist'] ) {
			return;
		}
		$post_id = get_the_ID();
		$tasks_completed = $this->evaluate_checklist( $post_id, true );
		if ( ! $post_id || ! $tasks_completed ) {
			return;
		}

		foreach ( $tasks_completed['tasks'] as $task_id => $task ) {
			if ( ! in_array( $task_id, $tasks_completed['completed'], true ) ) {
				printf( '<div class="%1$s"><p><strong>%2$s:</strong> %3$s</p></div>',
					'error',
					esc_html__( 'Unable to publish', 'publishing-checklist' ),
					$task['explanation']
				);
			}
		}
	}

	/**
	* Evaluate tasks for a post
	*
	* @param string $post_id WordPress post ID
	* @param bool $required_only If true, will only evaluate required tasks
	*/
	public function evaluate_checklist( $post_id, $required_only = false ) {

		if ( empty( $post_id ) ) {
			return false;
		}

		if ( empty( $this->tasks ) ) {
			return false;
		}

		$post_type = get_post_type( $post_id );

		$tasks = array_filter( $this->tasks,
			function( $task ) use ( $post_type, $required_only ) {
				if ( ! is_callable( $task['callback'] ) ) {
					return false;
				}
				if ( ! empty( $task['post_type'] ) && ! in_array( $post_type, $task['post_type'], true ) ) {
					return false;
				}
				if ( $required_only && ! $task['required'] ) {
					return false;
				}
				return true;
			}
		);

		// Shuffle "required" tasks to the beginning of the list
		uasort( $tasks, function( $a, $b ) {
			if ( ! $a['required'] && ! $b['required'] ) {
				return 0;
			}
			return ( $a['required'] ) ? -1 : 1;
		});

		$completed_tasks = array();

		foreach ( $tasks as $task_id => $task ) {
			if ( call_user_func_array( $task['callback'], array( $post_id, $task_id ) ) ) {
				$completed_tasks[] = $task_id;
			}
		}

		if ( empty( $tasks ) ) {
			return false;
		}

		$checklist_data = array(
			'tasks' => $tasks,
			'completed' => $completed_tasks,
		);

		return $checklist_data;

	}

	/**
	 * Load our scripts and styles
	 */
	public function action_publishing_checklist_enqueue_scripts() {
		wp_enqueue_style( 'publishing-checklist', plugins_url( 'assets/css/publishing-checklist.css', __FILE__ ), false, PUBLISHING_CHECKLIST_VERSION );
		wp_enqueue_script( 'publishing-checklist', plugins_url( 'assets/js/src/publishing-checklist.js', __FILE__ ), array( 'jquery' ), PUBLISHING_CHECKLIST_VERSION );
	}

	/**
	 * Get a rendered template part
	 *
	 * @param string $template
	 * @param array $vars
	 * @return string
	 */
	private function get_template_part( $template, $vars = array() ) {
		$full_path = dirname( __FILE__ ) . '/templates/' . sanitize_file_name( $template ) . '.php';

		if ( ! file_exists( $full_path ) ) {
			return '';
		}

		ob_start();
		// @codingStandardsIgnoreStart
		if ( ! empty( $vars ) ) {
			extract( $vars );
		}
		// @codingStandardsIgnoreEnd
		include $full_path;
		return ob_get_clean();
	}

	/**
	 * Customize columns on the "Manage Posts" views
	 */
	public function filter_manage_posts_columns( $columns ) {

		foreach ( $this->tasks as $task_id => $task ) {
			if ( ! is_callable( $task['callback'] ) ) {
				unset( $this->tasks[ $task_id ] );
			}

			if ( ! empty( $task['post_type'] ) && ! in_array( get_post_type(), $task['post_type'], true ) ) {
				unset( $this->tasks[ $task_id ] );
			}
		}

		if ( empty( $this->tasks ) ) {
			return $columns;
		}

		$columns['publishing_checklist'] = esc_html__( 'Publishing Checklist', 'publishing-checklist' );
		do_action( 'publishing_checklist_enqueue_scripts' );
		return $columns;
	}

	/**
	 * Handle the output for a custom column
	 */
	public function action_manage_posts_custom_column( $column_name, $post_id ) {
		if ( 'publishing_checklist' === $column_name ) {
			$tasks_completed = $this->evaluate_checklist( $post_id );
			echo $this->get_template_part( 'column-checklist', array(
				'tasks' => $tasks_completed['tasks'],
				'completed_tasks' => $tasks_completed['completed'],
			) );
		}
	}
}

/**
 * Load the plugin
 */
// @codingStandardsIgnoreStart
function Publishing_Checklist() {
// @codingStandardsIgnoreEnd
	return Publishing_Checklist::get_instance();
}
add_action( 'init', 'Publishing_Checklist' );
