<?php
/**
 * Plugin Name: YT 404 Logger & Insights
 * Plugin URI: https://github.com/krasenslavov/yt-404-logger-insights
 * Description: Logs 404 URLs and shows top missing pages with optional auto-redirect for common repeats.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Krasen Slavov
 * Author URI: https://krasenslavov.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yt-404-logger-insights
 * Domain Path: /languages
 *
 * @package YT_404_Logger_Insights
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'YT_404_LOGGER_VERSION', '1.0.0' );

/**
 * Plugin base name.
 */
define( 'YT_404_LOGGER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin directory path.
 */
define( 'YT_404_LOGGER_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'YT_404_LOGGER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
class YT_404_Logger_Insights {

	/**
	 * Single instance of the class.
	 *
	 * @var YT_404_Logger_Insights|null
	 */
	private static $instance = null;

	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Get single instance of the class.
	 *
	 * @return YT_404_Logger_Insights
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'yt_404_logs';
		$this->options    = get_option( 'yt_404_logger_options', array() );
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'yt_404_logger_load_textdomain' ) );
		add_action( 'template_redirect', array( $this, 'yt_404_logger_capture_404' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'yt_404_logger_add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'yt_404_logger_register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'yt_404_logger_enqueue_admin_assets' ) );
			add_filter( 'plugin_action_links_' . YT_404_LOGGER_BASENAME, array( $this, 'yt_404_logger_add_action_links' ) );
			add_action( 'wp_ajax_yt_404_logger_delete_log', array( $this, 'yt_404_logger_ajax_delete_log' ) );
			add_action( 'wp_ajax_yt_404_logger_clear_logs', array( $this, 'yt_404_logger_ajax_clear_logs' ) );
		}
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function yt_404_logger_load_textdomain() {
		load_plugin_textdomain(
			'yt-404-logger-insights',
			false,
			dirname( YT_404_LOGGER_BASENAME ) . '/languages'
		);
	}

	/**
	 * Capture 404 errors and log them.
	 *
	 * @return void
	 */
	public function yt_404_logger_capture_404() {
		if ( ! is_404() ) {
			return;
		}

		global $wpdb;

		$request_uri = esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' );
		$referer     = esc_url_raw( $_SERVER['HTTP_REFERER'] ?? '' );
		$user_agent  = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$ip_address  = sanitize_text_field( $this->yt_404_logger_get_ip_address() );

		// Check if auto-redirect is enabled and URL has a redirect.
		$redirect_url = $this->yt_404_logger_get_redirect_url( $request_uri );
		if ( $redirect_url && $this->yt_404_logger_is_auto_redirect_enabled() ) {
			wp_safe_redirect( $redirect_url, 301 );
			exit;
		}

		// Check if this exact URL was logged in the last minute (prevent spam).
		$recent_log = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE path = %s AND logged_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) LIMIT 1", // phpcs:ignore
				$request_uri
			)
		);

		if ( $recent_log ) {
			return;
		}

		// Insert new log entry.
		$wpdb->insert(
			$this->table_name,
			array(
				'path'       => $request_uri,
				'referer'    => $referer,
				'user_agent' => $user_agent,
				'ip_address' => $ip_address,
				'logged_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get visitor IP address.
	 *
	 * @return string
	 */
	private function yt_404_logger_get_ip_address() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Add plugin admin menu.
	 *
	 * @return void
	 */
	public function yt_404_logger_add_admin_menu() {
		add_management_page(
			__( '404 Logs', 'yt-404-logger-insights' ),
			__( '404 Logs', 'yt-404-logger-insights' ),
			'manage_options',
			'yt-404-logs',
			array( $this, 'yt_404_logger_render_logs_page' )
		);

		add_options_page(
			__( '404 Logger Settings', 'yt-404-logger-insights' ),
			__( '404 Logger', 'yt-404-logger-insights' ),
			'manage_options',
			'yt-404-logger-settings',
			array( $this, 'yt_404_logger_render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function yt_404_logger_register_settings() {
		register_setting(
			'yt_404_logger_options_group',
			'yt_404_logger_options',
			array( $this, 'yt_404_logger_sanitize_options' )
		);

		add_settings_section(
			'yt_404_logger_main_section',
			__( 'Auto-Redirect Settings', 'yt-404-logger-insights' ),
			array( $this, 'yt_404_logger_render_section_info' ),
			'yt-404-logger-settings'
		);

		add_settings_field(
			'enable_auto_redirect',
			__( 'Enable Auto-Redirect', 'yt-404-logger-insights' ),
			array( $this, 'yt_404_logger_render_enable_redirect_field' ),
			'yt-404-logger-settings',
			'yt_404_logger_main_section'
		);

		add_settings_field(
			'redirect_rules',
			__( 'Redirect Rules', 'yt-404-logger-insights' ),
			array( $this, 'yt_404_logger_render_redirect_rules_field' ),
			'yt-404-logger-settings',
			'yt_404_logger_main_section'
		);
	}

	/**
	 * Sanitize plugin options.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public function yt_404_logger_sanitize_options( $input ) {
		$sanitized = array();

		if ( isset( $input['enable_auto_redirect'] ) ) {
			$sanitized['enable_auto_redirect'] = (bool) $input['enable_auto_redirect'];
		}

		if ( isset( $input['redirect_rules'] ) ) {
			$sanitized['redirect_rules'] = sanitize_textarea_field( $input['redirect_rules'] );
		}

		return $sanitized;
	}

	/**
	 * Render settings section information.
	 *
	 * @return void
	 */
	public function yt_404_logger_render_section_info() {
		echo '<p>' . esc_html__( 'Configure auto-redirect rules for commonly missing pages.', 'yt-404-logger-insights' ) . '</p>';
	}

	/**
	 * Render enable auto-redirect checkbox field.
	 *
	 * @return void
	 */
	public function yt_404_logger_render_enable_redirect_field() {
		$value = isset( $this->options['enable_auto_redirect'] ) ? $this->options['enable_auto_redirect'] : false;
		?>
		<input type="checkbox"
			name="yt_404_logger_options[enable_auto_redirect]"
			value="1"
			<?php checked( $value, true ); ?> />
		<label for="enable_auto_redirect">
			<?php esc_html_e( 'Automatically redirect 404 URLs based on rules below', 'yt-404-logger-insights' ); ?>
		</label>
		<?php
	}

	/**
	 * Render redirect rules textarea field.
	 *
	 * @return void
	 */
	public function yt_404_logger_render_redirect_rules_field() {
		$value = isset( $this->options['redirect_rules'] ) ? $this->options['redirect_rules'] : '';
		?>
		<textarea
			name="yt_404_logger_options[redirect_rules]"
			rows="10"
			class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Enter redirect rules, one per line. Format: /old-url => /new-url', 'yt-404-logger-insights' ); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function yt_404_logger_render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'yt-404-logger-insights' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'yt_404_logger_options_group' );
				do_settings_sections( 'yt-404-logger-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render logs page.
	 *
	 * @return void
	 */
	public function yt_404_logger_render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'yt-404-logger-insights' ) );
		}

		global $wpdb;

		// Get summary statistics.
		$total_logs   = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" ); // phpcs:ignore
		$unique_paths = $wpdb->get_var( "SELECT COUNT(DISTINCT path) FROM {$this->table_name}" ); // phpcs:ignore

		// Get top 404 URLs.
		$top_404s = $wpdb->get_results(
			"SELECT path, COUNT(*) as hit_count, MAX(logged_at) as last_seen FROM {$this->table_name} GROUP BY path ORDER BY hit_count DESC LIMIT 20" // phpcs:ignore
		);

		// Get recent logs.
		$recent_logs = $wpdb->get_results(
			"SELECT * FROM {$this->table_name} ORDER BY logged_at DESC LIMIT 50" // phpcs:ignore
		);

		?>
		<div class="wrap yt-404-logger-wrap">
			<h1><?php esc_html_e( '404 Logger & Insights', 'yt-404-logger-insights' ); ?></h1>

			<div class="yt-404-stats">
				<div class="yt-404-stat-box">
					<h3><?php echo esc_html( number_format_i18n( $total_logs ) ); ?></h3>
					<p><?php esc_html_e( 'Total 404 Errors', 'yt-404-logger-insights' ); ?></p>
				</div>
				<div class="yt-404-stat-box">
					<h3><?php echo esc_html( number_format_i18n( $unique_paths ) ); ?></h3>
					<p><?php esc_html_e( 'Unique URLs', 'yt-404-logger-insights' ); ?></p>
				</div>
			</div>

			<div class="yt-404-actions">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=yt-404-logger-settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Settings', 'yt-404-logger-insights' ); ?>
				</a>
				<button type="button" class="button button-secondary yt-404-clear-logs">
					<?php esc_html_e( 'Clear All Logs', 'yt-404-logger-insights' ); ?>
				</button>
			</div>

			<h2><?php esc_html_e( 'Top Missing Pages', 'yt-404-logger-insights' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'URL Path', 'yt-404-logger-insights' ); ?></th>
						<th><?php esc_html_e( 'Hit Count', 'yt-404-logger-insights' ); ?></th>
						<th><?php esc_html_e( 'Last Seen', 'yt-404-logger-insights' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $top_404s ) ) : ?>
						<?php foreach ( $top_404s as $log ) : ?>
							<tr>
								<td><code><?php echo esc_html( $log->path ); ?></code></td>
								<td><?php echo esc_html( number_format_i18n( $log->hit_count ) ); ?></td>
								<td><?php echo esc_html( $log->last_seen ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="3"><?php esc_html_e( 'No 404 errors logged yet.', 'yt-404-logger-insights' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Recent 404 Errors', 'yt-404-logger-insights' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'URL Path', 'yt-404-logger-insights' ); ?></th>
						<th><?php esc_html_e( 'Referer', 'yt-404-logger-insights' ); ?></th>
						<th><?php esc_html_e( 'IP Address', 'yt-404-logger-insights' ); ?></th>
						<th><?php esc_html_e( 'Date/Time', 'yt-404-logger-insights' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'yt-404-logger-insights' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $recent_logs ) ) : ?>
						<?php foreach ( $recent_logs as $log ) : ?>
							<tr data-log-id="<?php echo esc_attr( $log->id ); ?>">
								<td><code><?php echo esc_html( $log->path ); ?></code></td>
								<td><?php echo $log->referer ? '<a href="' . esc_url( $log->referer ) . '" target="_blank">' . esc_html( wp_trim_words( $log->referer, 5, '...' ) ) . '</a>' : '—'; ?></td>
								<td><?php echo esc_html( $log->ip_address ); ?></td>
								<td><?php echo esc_html( $log->logged_at ); ?></td>
								<td>
									<button type="button" class="button button-small yt-404-delete-log" data-log-id="<?php echo esc_attr( $log->id ); ?>">
										<?php esc_html_e( 'Delete', 'yt-404-logger-insights' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No 404 errors logged yet.', 'yt-404-logger-insights' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function yt_404_logger_add_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'tools.php?page=yt-404-logs' ) ) . '">' . esc_html__( 'View Logs', 'yt-404-logger-insights' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'options-general.php?page=yt-404-logger-settings' ) ) . '">' . esc_html__( 'Settings', 'yt-404-logger-insights' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function yt_404_logger_enqueue_admin_assets( $hook ) {
		if ( 'tools_page_yt-404-logs' !== $hook && 'settings_page_yt-404-logger-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'yt-404-logger-admin-style',
			YT_404_LOGGER_URL . 'assets/css/yt-404-logger-admin.css',
			array(),
			YT_404_LOGGER_VERSION,
			'all'
		);

		wp_enqueue_script(
			'yt-404-logger-admin-script',
			YT_404_LOGGER_URL . 'assets/js/yt-404-logger-admin.js',
			array( 'jquery' ),
			YT_404_LOGGER_VERSION,
			true
		);

		wp_localize_script(
			'yt-404-logger-admin-script',
			'yt404Logger',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'yt_404_logger_nonce' ),
				'confirmClear'  => __( 'Are you sure you want to clear all logs? This action cannot be undone.', 'yt-404-logger-insights' ),
				'confirmDelete' => __( 'Are you sure you want to delete this log entry?', 'yt-404-logger-insights' ),
				'deleteSuccess' => __( 'Log entry deleted successfully.', 'yt-404-logger-insights' ),
				'clearSuccess'  => __( 'All logs cleared successfully.', 'yt-404-logger-insights' ),
				'errorOccurred' => __( 'An error occurred. Please try again.', 'yt-404-logger-insights' ),
			)
		);
	}

	/**
	 * AJAX handler for deleting a single log entry.
	 *
	 * @return void
	 */
	public function yt_404_logger_ajax_delete_log() {
		check_ajax_referer( 'yt_404_logger_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'yt-404-logger-insights' ) ) );
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;

		if ( ! $log_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log ID', 'yt-404-logger-insights' ) ) );
		}

		global $wpdb;
		$deleted = $wpdb->delete( $this->table_name, array( 'id' => $log_id ), array( '%d' ) );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'Log deleted', 'yt-404-logger-insights' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete log', 'yt-404-logger-insights' ) ) );
		}
	}

	/**
	 * AJAX handler for clearing all logs.
	 *
	 * @return void
	 */
	public function yt_404_logger_ajax_clear_logs() {
		check_ajax_referer( 'yt_404_logger_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'yt-404-logger-insights' ) ) );
		}

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->table_name}" ); // phpcs:ignore

		wp_send_json_success( array( 'message' => __( 'All logs cleared', 'yt-404-logger-insights' ) ) );
	}

	/**
	 * Check if auto-redirect is enabled.
	 *
	 * @return bool
	 */
	private function yt_404_logger_is_auto_redirect_enabled() {
		return ! empty( $this->options['enable_auto_redirect'] );
	}

	/**
	 * Get redirect URL for a given path.
	 *
	 * @param string $path Requested path.
	 * @return string|false Redirect URL or false if not found.
	 */
	private function yt_404_logger_get_redirect_url( $path ) {
		if ( empty( $this->options['redirect_rules'] ) ) {
			return false;
		}

		$rules = explode( "\n", $this->options['redirect_rules'] );

		foreach ( $rules as $rule ) {
			$rule = trim( $rule );
			if ( empty( $rule ) || strpos( $rule, '=>' ) === false ) {
				continue;
			}

			list( $from, $to ) = array_map( 'trim', explode( '=>', $rule, 2 ) );

			if ( $from === $path ) {
				return home_url( $to );
			}
		}

		return false;
	}

	/**
	 * Plugin activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'yt_404_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			path varchar(255) NOT NULL,
			referer varchar(255) DEFAULT '',
			user_agent varchar(255) DEFAULT '',
			ip_address varchar(45) DEFAULT '',
			logged_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY path (path),
			KEY logged_at (logged_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$default_options = array(
			'enable_auto_redirect' => false,
			'redirect_rules'       => '',
		);

		if ( ! get_option( 'yt_404_logger_options' ) ) {
			add_option( 'yt_404_logger_options', $default_options );
		}
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Nothing to do on deactivation.
	}
}

/**
 * Plugin uninstall hook.
 *
 * @return void
 */
function yt_404_logger_insights_uninstall() {
	global $wpdb;

	delete_option( 'yt_404_logger_options' );

	$table_name = $wpdb->prefix . 'yt_404_logs';
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore

	wp_cache_flush();
}

// Register activation hook.
register_activation_hook( __FILE__, array( 'YT_404_Logger_Insights', 'activate' ) );

// Register deactivation hook.
register_deactivation_hook( __FILE__, array( 'YT_404_Logger_Insights', 'deactivate' ) );

// Register uninstall hook.
register_uninstall_hook( __FILE__, 'yt_404_logger_insights_uninstall' );

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'YT_404_Logger_Insights', 'get_instance' ) );
