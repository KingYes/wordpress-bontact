<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class BONT_Settings {

	/**
	 * @var array
	 */
	protected $_form_messages = array();
	
	/**
	 * @var string
	 */
	protected $_option_slug;
	
	protected $_api_login_url = 'https://dashboard.bontact.com/api/bontactapi.aspx?func=login&username=%s&password=%s';
	protected $_api_signup_url = 'https://dashboard.bontact.com/api/bontactapi.aspx?func=signup&username=%s&password=%s';
	
	protected function _do_redirect_option_page( $message_code = null ) {
		$return_url = add_query_arg( 'page', $this->_option_slug, admin_url( 'options-general.php' ) );
		
		if ( ! is_null( $message_code ) )
			$return_url = add_query_arg( 'message', $message_code, $return_url );
		
		wp_redirect( $return_url );
		die();
	}
	
	protected function _get_logout_link() {
		$url = add_query_arg( array( 'page' => $this->_option_slug, 'bont-action' => 'logout' ), admin_url( 'options-general.php' ) );
		return wp_nonce_url( $url, 'bontact_logout_' . get_current_user_id() );
	}
	
	public function admin_init() {
		if ( ! current_user_can( 'manage_options' ) || empty( $_REQUEST['bont-action'] ) )
			return;

		if ( 'logout' === $_REQUEST['bont-action'] ) {
			check_admin_referer( 'bontact_logout_' . get_current_user_id() );
			delete_option( $this->_option_slug );
			$this->_do_redirect_option_page( 5 );
		}
		
		if ( 'login' === $_REQUEST['bont-action'] || 'register' === $_REQUEST['bont-action'] ) {
			if ( empty( $_REQUEST['nonce'] ) || ! check_ajax_referer( 'bontact_' . $_REQUEST['bont-action'] . '_' . get_current_user_id(), 'nonce', false ) ) {
				$this->_do_redirect_option_page( 6 );
			}
			
			$api_url = 'login' === $_REQUEST['bont-action'] ? $this->_api_login_url : $this->_api_signup_url;
			$response = wp_remote_get( sprintf( $api_url, $_POST['bontact']['username'], $_POST['bontact']['password'] ), array( 'sslverify' => false, 'timeout' => 30 ) );
			
			if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
				$this->_do_redirect_option_page( 4 );
			}
			
			$data_return = json_decode( $response['body'] );
			if ( is_null( $data_return ) || '200' !== $data_return->code ) {
				$this->_do_redirect_option_page( 3 );
			}

			$options = get_option( $this->_option_slug, array() );
			$options['token'] = $data_return->token;
			$options['username'] = $_POST['bontact']['username'];
			$options['password'] = $_POST['bontact']['password'];
			
			update_option( $this->_option_slug, $options );

			$msg_code = 'login' === $_REQUEST['bont-action'] ? 2 : 1;
			$this->_do_redirect_option_page( $msg_code );
			
			if ( 'register' === $_POST['type-submit'] ) {

			}
		}
	}
	
	public function bontact_setting_content() {
		$username = $this->get_option( 'username' );
		$password = $this->get_option( 'password' );
		?>
		<!-- Create a header in the default WordPress 'wrap' container -->
		<div class="wrap">
			<div id="icon-themes" class="icon32"></div>
			<h2><?php _e( 'Bontact Settings', 'bontact' ); ?></h2>
			
			<?php if ( ! empty( $_GET['message'] ) && ! empty( $this->_form_messages[ $_GET['message'] ] ) ) : ?>
			<div class="<?php echo $this->_form_messages[ $_GET['message'] ]['status']; ?>"><p><?php echo $this->_form_messages[ $_GET['message'] ]['msg']; ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $username ) ) : ?>
				<div>Current User: <?php echo $username; ?> [<a href="<?php echo $this->_get_logout_link(); ?>"><?php _e( 'Logout', 'bontact' ); ?></a>]</div>
				<div>
					<iframe src="https://dashboard.bontact.com/html/login.aspx?username=<?php echo $username; ?>&pass=<?php echo $password; ?>" style="width: 100%; min-height: 500px;"></iframe>
				</div>
			<?php else : ?>
				<h3><?php _e( 'Login:', 'bontact' ); ?></h3>
				<form action="" method="post">
					<input type="hidden" name="page" value="<?php echo $this->_option_slug; ?>" />
					<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'bontact_login_' . get_current_user_id() ); ?>" />
					<input type="hidden" name="bont-action" value="login" />
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								<label for="bontact_username"><?php _e( 'Username (email):', 'bontact' ); ?></label>
							</th>
							<td>
								<input id="bontact_username" type="text" name="bontact[username]" value="" autocomplete="off" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="bontact_password"><?php _e( 'Password:', 'bontact' ); ?></label>
							</th>
							<td>
								<input id="bontact_password" type="password" name="bontact[password]" value="" autocomplete="off" />
							</td>
						</tr>
					</table>
					<div class="submit">
						<input type="submit" name="type-submit" class="button button-primary" value="<?php _e( 'Login', 'bontact' ); ?>" />
					</div>
				</form>
				
				<hr />

				<h3><?php _e( 'Register:', 'bontact' ); ?></h3>
				<form action="" method="post">
					<input type="hidden" name="page" value="<?php echo $this->_option_slug; ?>" />
					<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'bontact_register_' . get_current_user_id() ); ?>" />
					<input type="hidden" name="bont-action" value="register" />
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								<label for="bontact_username"><?php _e( 'Username (email):', 'bontact' ); ?></label>
							</th>
							<td>
								<input id="bontact_username" type="text" name="bontact[username]" value="" autocomplete="off" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="bontact_password"><?php _e( 'Password:', 'bontact' ); ?></label>
							</th>
							<td>
								<input id="bontact_password" type="password" name="bontact[password]" value="" autocomplete="off" />
							</td>
						</tr>
					</table>
					<div class="submit">
						<input type="submit" class="button button-primary" value="<?php _e( 'Register', 'bontact' ); ?>" />
					</div>
				</form>
			<?php endif; ?>
		</div><!-- /.wrap -->
	<?php
	}
	
	public function admin_menu() {
		add_options_page( 'Bontact Settings', 'Bontact', 'manage_options', 'bontact-settings', array( &$this, 'bontact_setting_content' ) );
	}
	
	public function get_option( $key ) {
		$options = get_option( $this->_option_slug );
		return isset( $options[ $key ] ) ? $options[ $key ] : '';
	}
	
	public function __construct() {
		$this->_form_messages = array(
			'', // Just skip from zero array.
			array(
				'msg' => __( 'Registration was successful.', 'bontact' ),
				'status' => 'updated',
			),
			array(
				'msg' => __( 'Login was successful.', 'bontact' ),
				'status' => 'updated',
			),
			array(
				'msg' => __( 'Invalid login.', 'bontact' ),
				'status' => 'error',
			),
			array(
				'msg' => __( 'Error with API server.', 'bontact' ),
				'status' => 'error',
			),
			array(
				'msg' => __( 'Your logout was successful.', 'bontact' ),
				'status' => 'updated',
			),
			array(
				'msg' => __( 'Action expired.', 'bontact' ),
				'status' => 'error',
			),
		);
		$this->_option_slug = 'bontact-settings';
		
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
	}
	
}