<?php
/**
 * WordPress admin UI.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_Admin {

	/** @var ZIPQUANTUM_Sync */
	private $sync;

	public function __construct( ZIPQUANTUM_Sync $sync ) {
		$this->sync = $sync;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'admin_post_zipquantum_object_sync', array( $this, 'manual_sync' ) );
		add_action( 'admin_post_zipquantum_object_attach', array( $this, 'attach' ) );
		add_action( 'admin_post_zipquantum_bulk_enqueue', array( $this, 'bulk_enqueue' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'product_category_panel' ) );
	}

	public function menus() {
		add_options_page(
			__( 'ZipQuantum', 'zipquantum-smart-links' ),
			__( 'ZipQuantum', 'zipquantum-smart-links' ),
			'manage_options',
			'zipquantum-smart-links',
			array( $this, 'settings_page' )
		);
		add_management_page(
			__( 'ZipQuantum tools', 'zipquantum-smart-links' ),
			__( 'ZipQuantum', 'zipquantum-smart-links' ),
			'manage_options',
			'zipquantum-smart-links-tools',
			array( $this, 'tools_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'zipquantum_smart_links',
			ZIPQUANTUM_Options::SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	public function sanitize_settings( $input ) {
		$current  = ZIPQUANTUM_Options::settings();
		$api_base = isset( $input['api_base'] ) ? esc_url_raw( untrailingslashit( $input['api_base'] ) ) : $current['api_base'];
		if ( 'https' !== wp_parse_url( $api_base, PHP_URL_SCHEME ) ) {
			$api_base = 'https://a.zq.tn';
		}

		$available = array_keys( $this->object_type_choices() );
		$requested = isset( $input['object_types'] ) ? array_map( 'sanitize_key', (array) $input['object_types'] ) : array();
		return array(
			'api_base'                  => $api_base,
			'managed_subdomain'         => isset( $input['managed_subdomain'] ) ? sanitize_title( $input['managed_subdomain'] ) : '',
			'custom_domain'             => isset( $input['custom_domain'] ) ? sanitize_text_field( $input['custom_domain'] ) : '',
			'coupon_destination'        => $this->local_path( isset( $input['coupon_destination'] ) ? $input['coupon_destination'] : '/checkout/' ),
			'auto_create'               => ! empty( $input['auto_create'] ),
			'object_types'              => array_values( array_intersect( $requested, $available ) ),
			'delete_metadata_uninstall' => ! empty( $input['delete_metadata_uninstall'] ),
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( $hook, 'zipquantum-smart-links' ) && ! in_array( $hook, array( 'post.php', 'post-new.php', 'term.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'zipquantum-smart-links-admin', ZIPQUANTUM_SMART_LINKS_URL . 'assets/css/admin.css', array(), ZIPQUANTUM_SMART_LINKS_VERSION );
		wp_enqueue_script( 'zipquantum-smart-links-admin', ZIPQUANTUM_SMART_LINKS_URL . 'assets/js/admin.js', array( 'jquery' ), ZIPQUANTUM_SMART_LINKS_VERSION, true );
		wp_localize_script(
			'zipquantum-smart-links-admin',
			'ZIPQUANTUMSmartLinks',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'adminPostUrl' => admin_url( 'admin-post.php' ),
				'nonce'        => wp_create_nonce( 'zipquantum_oauth' ),
				'i18n'         => array(
					'connecting' => __( 'Waiting for ZipQuantum authorization…', 'zipquantum-smart-links' ),
					'connected'  => __( 'ZipQuantum is connected.', 'zipquantum-smart-links' ),
					'copied'     => __( 'Smart Link copied.', 'zipquantum-smart-links' ),
					'popup'      => __( 'Allow pop-ups, then try again.', 'zipquantum-smart-links' ),
				),
			)
		);
	}

	public function settings_page() {
		$settings  = ZIPQUANTUM_Options::settings();
		$context   = ZIPQUANTUM_Options::get( ZIPQUANTUM_Options::CONTEXT, array() );
		$state     = ZIPQUANTUM_Options::get( ZIPQUANTUM_Options::STATE, array() );
		$connected = (bool) ZIPQUANTUM_Options::get_secret( ZIPQUANTUM_Options::CREDENTIALS, array() );
		?>
		<div class="wrap zipquantum-wrap">
			<div class="zipquantum-brand"><img src="<?php echo esc_url( ZIPQUANTUM_SMART_LINKS_URL . 'assets/img/zipquantum-logo.png' ); ?>" alt="ZipQuantum"><h1><?php esc_html_e( 'Smart Links & QR Codes', 'zipquantum-smart-links' ); ?></h1></div>
			<p class="description"><?php esc_html_e( 'Connect this site, choose its routing domain, then synchronize public content.', 'zipquantum-smart-links' ); ?></p>
			<?php if ( ! empty( $state['identity_mismatch'] ) ) : ?>
				<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'This site appears to have been moved or cloned.', 'zipquantum-smart-links' ); ?></strong></p></div>
			<?php endif; ?>
			<div class="zipquantum-card">
				<h2><?php esc_html_e( '1. Account connection', 'zipquantum-smart-links' ); ?></h2>
				<?php if ( $connected ) : ?>
					<p><span class="zipquantum-status zipquantum-status-ok"><?php esc_html_e( 'Connected', 'zipquantum-smart-links' ); ?></span>
					<?php echo esc_html( isset( $context['account']['name'] ) ? $context['account']['name'] : '' ); ?></p>
					<button class="button zipquantum-oauth-start" data-intent="reconnect"><?php esc_html_e( 'Reconnect ZipQuantum', 'zipquantum-smart-links' ); ?></button>
					<?php if ( ! empty( $state['identity_mismatch'] ) ) : ?>
						<button class="button button-primary zipquantum-oauth-start" data-intent="move"><?php esc_html_e( 'Move existing installation', 'zipquantum-smart-links' ); ?></button>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zipquantum_new_installation' ), 'zipquantum_new_installation' ) ); ?>"><?php esc_html_e( 'Create a new installation', 'zipquantum-smart-links' ); ?></a>
					<?php endif; ?>
					<a class="button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zipquantum_disconnect' ), 'zipquantum_disconnect' ) ); ?>"><?php esc_html_e( 'Disconnect', 'zipquantum-smart-links' ); ?></a>
				<?php else : ?>
					<button class="button button-primary zipquantum-oauth-start" data-intent="connect"><?php esc_html_e( 'Connect ZipQuantum', 'zipquantum-smart-links' ); ?></button>
				<?php endif; ?>
				<p class="zipquantum-oauth-message" aria-live="polite"></p>
			</div>
			<form method="post" action="options.php">
				<?php settings_fields( 'zipquantum_smart_links' ); ?>
				<div class="zipquantum-card">
					<h2><?php esc_html_e( '2. Routing', 'zipquantum-smart-links' ); ?></h2>
					<?php $this->text_field( 'managed_subdomain', __( 'Managed zq.tn subdomain', 'zipquantum-smart-links' ), $settings['managed_subdomain'], 'mybrand' ); ?>
					<?php $this->text_field( 'custom_domain', __( 'Verified custom domain', 'zipquantum-smart-links' ), $settings['custom_domain'], 'go.example.com' ); ?>
					<?php $this->text_field( 'coupon_destination', __( 'Coupon destination', 'zipquantum-smart-links' ), $settings['coupon_destination'] ?? '/checkout/', '/checkout/' ); ?>
				</div>
				<div class="zipquantum-card">
					<h2><?php esc_html_e( '3. Content automation', 'zipquantum-smart-links' ); ?></h2>
					<label><input type="checkbox" name="<?php echo esc_attr( ZIPQUANTUM_Options::SETTINGS ); ?>[auto_create]" value="1" <?php checked( $settings['auto_create'] ); ?>> <?php esc_html_e( 'Automatically create Smart Links for newly published selected content', 'zipquantum-smart-links' ); ?></label>
					<div class="zipquantum-check-grid">
					<?php foreach ( $this->object_type_choices() as $type => $label ) : ?>
						<label><input type="checkbox" name="<?php echo esc_attr( ZIPQUANTUM_Options::SETTINGS ); ?>[object_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $settings['object_types'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
					</div>
				</div>
				<details class="zipquantum-card"><summary><?php esc_html_e( 'Advanced', 'zipquantum-smart-links' ); ?></summary>
					<?php $this->text_field( 'api_base', __( 'ZipQuantum API base', 'zipquantum-smart-links' ), $settings['api_base'], 'https://a.zq.tn' ); ?>
					<label><input type="checkbox" name="<?php echo esc_attr( ZIPQUANTUM_Options::SETTINGS ); ?>[delete_metadata_uninstall]" value="1" <?php checked( $settings['delete_metadata_uninstall'] ); ?>> <?php esc_html_e( 'Delete local association metadata when uninstalling', 'zipquantum-smart-links' ); ?></label>
				</details>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function tools_page() {

		$stats         = ZIPQUANTUM_Queue::stats();
		$status_labels = array(
			'pending'     => __( 'Pending', 'zipquantum-smart-links' ),
			'processing'  => __( 'Processing', 'zipquantum-smart-links' ),
			'retry'       => __( 'Retry', 'zipquantum-smart-links' ),
			'blocked'     => __( 'Blocked', 'zipquantum-smart-links' ),
			'quarantined' => __( 'Quarantined', 'zipquantum-smart-links' ),
			'failed'      => __( 'Failed', 'zipquantum-smart-links' ),
			'complete'    => __( 'Complete', 'zipquantum-smart-links' ),
		);
		?>
		<div class="wrap zipquantum-wrap"><h1><?php esc_html_e( 'ZipQuantum tools', 'zipquantum-smart-links' ); ?></h1>
			<div class="zipquantum-card"><h2><?php esc_html_e( 'Synchronization queue', 'zipquantum-smart-links' ); ?></h2>
				<div class="zipquantum-stat-grid">
				<?php
				foreach ( $status_labels as $status => $label ) :
					?>
					<div><strong><?php echo esc_html( isset( $stats[ $status ] ) ? $stats[ $status ] : 0 ); ?></strong><span><?php echo esc_html( $label ); ?></span></div>
				<?php endforeach; ?>
				</div>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zipquantum_queue_retry' ), 'zipquantum_queue_retry' ) ); ?>"><?php esc_html_e( 'Retry failed', 'zipquantum-smart-links' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zipquantum_queue_resume' ), 'zipquantum_queue_resume' ) ); ?>"><?php esc_html_e( 'Resume', 'zipquantum-smart-links' ); ?></a>
			</div>
			<div class="zipquantum-card"><h2><?php esc_html_e( 'Bulk enqueue', 'zipquantum-smart-links' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="zipquantum_bulk_enqueue"><?php wp_nonce_field( 'zipquantum_bulk_enqueue' ); ?>
					<select name="object_type">
					<?php
					foreach ( $this->object_type_choices() as $type => $label ) :
						?>
						<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
					<?php submit_button( __( 'Add published content to queue', 'zipquantum-smart-links' ), 'secondary', 'submit', false ); ?>
				</form>
				<p><?php esc_html_e( 'Progress is durable: refresh this page at any time. Failed rows retain explicit errors and can be retried.', 'zipquantum-smart-links' ); ?></p>
			</div>
		</div>
		<?php
	}

	public function meta_boxes() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( class_exists( 'WooCommerce' ) ) {
			$post_types[] = 'shop_coupon';
		}
		foreach ( array_unique( $post_types ) as $post_type ) {
			add_meta_box( 'zipquantum-smart-link', __( 'ZipQuantum Smart Link', 'zipquantum-smart-links' ), array( $this, 'meta_box' ), $post_type, 'side', 'default' );
		}
	}

	public function meta_box( $post ) {
		$object_type = 'product' === $post->post_type ? 'product' : ( 'shop_coupon' === $post->post_type ? 'coupon' : $post->post_type );
		$this->object_panel( $object_type, $post->ID );
	}

	public function product_category_panel( $term ) {
		echo '<tr class="form-field"><th>' . esc_html__( 'ZipQuantum', 'zipquantum-smart-links' ) . '</th><td>';
		$this->object_panel( 'product_cat', $term->term_id );
		echo '</td></tr>';
	}

	private function object_panel( $object_type, $object_id ) {
		$association = ZIPQUANTUM_Associations::get( $object_type, $object_id );
		$smart       = isset( $association['smart_link'] ) ? $association['smart_link'] : array();
		if ( ! empty( $smart['short_link'] ) ) {
			echo '<p><a href="' . esc_url( $smart['short_link'] ) . '" target="_blank" rel="noopener">' . esc_html( $smart['short_link'] ) . '</a></p>';
			echo '<p><button type="button" class="button zipquantum-copy-link" data-zipquantum-copy="' . esc_attr( $smart['short_link'] ) . '">' . esc_html__( 'Copy Smart Link', 'zipquantum-smart-links' ) . '</button></p>';
			echo '<p>' . esc_html__( 'Clicks:', 'zipquantum-smart-links' ) . ' ' . esc_html( isset( $smart['clicks'] ) ? absint( $smart['clicks'] ) : 0 ) . '</p>';
			if ( ! empty( $smart['qr'] ) && 0 === strpos( $smart['qr'], 'data:image/svg+xml;base64,' ) ) {
				echo '<p><img class="zipquantum-qr" src="' . esc_attr( $smart['qr'] ) . '" alt="' . esc_attr__( 'Smart Link QR code', 'zipquantum-smart-links' ) . '"></p>';
				echo '<a class="button" download="zipquantum-qr.svg" href="' . esc_attr( $smart['qr'] ) . '">' . esc_html__( 'Download QR', 'zipquantum-smart-links' ) . '</a> ';
			}
		}
		if ( 'attached' === ( $association['management_mode'] ?? '' ) ) {
			echo '<p><span class="zipquantum-status">' . esc_html__( 'Attached — read only', 'zipquantum-smart-links' ) . '</span></p>';
		} else {
			echo '<p><button type="button" class="button zipquantum-object-action" data-zipquantum-action="zipquantum_object_sync" data-zipquantum-object-type="' . esc_attr( $object_type ) . '" data-zipquantum-object-id="' . esc_attr( $object_id ) . '" data-zipquantum-nonce="' . esc_attr( wp_create_nonce( 'zipquantum_object_' . $object_type . '_' . $object_id ) ) . '">' . esc_html( empty( $association ) ? __( 'Create Smart Link', 'zipquantum-smart-links' ) : __( 'Synchronize', 'zipquantum-smart-links' ) ) . '</button></p>';
		}
		echo '<details><summary>' . esc_html__( 'Attach existing (read-only)', 'zipquantum-smart-links' ) . '</summary><p><label>' . esc_html__( 'Smart Link ID', 'zipquantum-smart-links' ) . ' <input type="number" min="1" class="zipquantum-attach-link-id" required></label></p><p><button type="button" class="button zipquantum-object-action" data-zipquantum-action="zipquantum_object_attach" data-zipquantum-object-type="' . esc_attr( $object_type ) . '" data-zipquantum-object-id="' . esc_attr( $object_id ) . '" data-zipquantum-nonce="' . esc_attr( wp_create_nonce( 'zipquantum_object_' . $object_type . '_' . $object_id ) ) . '">' . esc_html__( 'Attach', 'zipquantum-smart-links' ) . '</button></p></details>';
	}

	public function manual_sync() {
		list( $type, $id ) = $this->guard_object_action();
		ZIPQUANTUM_Queue::enqueue( 'sync', $type, $id, $this->sync->build_payload( $type, $id, 'managed' ) );
		$this->redirect_object( $type, $id );
	}

	public function attach() {
		list( $type, $id ) = $this->guard_object_action();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by guard_object_action().
		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
		if ( ! $link_id ) {
			wp_die( esc_html__( 'A Smart Link ID is required.', 'zipquantum-smart-links' ) );
		}
		ZIPQUANTUM_Associations::set(
			$type,
			$id,
			array(
				'management_mode' => 'attached',
				'smart_link'      => array( 'id' => $link_id ),
				'local_status'    => 'pending',
			)
		);
		ZIPQUANTUM_Queue::enqueue( 'sync', $type, $id, $this->sync->build_payload( $type, $id, 'attached' ) );
		$this->redirect_object( $type, $id );
	}

	public function bulk_enqueue() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zipquantum-smart-links' ) );
		}
		check_admin_referer( 'zipquantum_bulk_enqueue' );
		$type = isset( $_POST['object_type'] ) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : 'post';
		if ( 'product_cat' === $type ) {
			$ids = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'fields'     => 'ids',
					'number'     => 500,
				)
			);
		} else {
			$post_type = 'coupon' === $type ? 'shop_coupon' : $type;
			$ids       = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'posts_per_page' => 500,
					'no_found_rows'  => true,
				)
			);
		}
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			try {
				ZIPQUANTUM_Queue::enqueue( 'sync', $type, $id, $this->sync->build_payload( $type, $id ) );
			} catch ( Throwable $error ) {
				continue;
			}
		}
		wp_safe_redirect( admin_url( 'tools.php?page=zipquantum-smart-links-tools' ) );
		exit;
	}

	private function guard_object_action() {
		$type = isset( $_POST['object_type'] ) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : '';
		$id   = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
		if ( ! current_user_can( 'edit_post', $id ) && ! current_user_can( 'manage_product_terms' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'zipquantum-smart-links' ) );
		}
		check_admin_referer( 'zipquantum_object_' . $type . '_' . $id );
		return array( $type, $id );
	}

	private function redirect_object( $type, $id ) {
		$url = 'product_cat' === $type ? get_edit_term_link( $id, 'product_cat' ) : get_edit_post_link( $id, 'url' );
		wp_safe_redirect( $url ? $url : admin_url() );
		exit;
	}

	private function object_type_choices() {
		$choices = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type => $object ) {
			$choices[ $type ] = $object->labels->singular_name;
		}
		if ( class_exists( 'WooCommerce' ) ) {
			$choices['product']     = __( 'Product', 'zipquantum-smart-links' );
			$choices['product_cat'] = __( 'Product category', 'zipquantum-smart-links' );
			$choices['coupon']      = __( 'Coupon', 'zipquantum-smart-links' );
		}
		return $choices;
	}

	private function text_field( $key, $label, $value, $placeholder ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="regular-text" type="text" name="' . esc_attr( ZIPQUANTUM_Options::SETTINGS ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"></label></p>';
	}

	private function local_path( $path ) {
		$path = trim( sanitize_text_field( $path ) );
		if ( '' === $path || 0 === strpos( $path, '//' ) || preg_match( '#^[a-z][a-z0-9+.-]*:#i', $path ) ) {
			return '/checkout/';
		}
		return '/' . ltrim( $path, '/' );
	}
}
