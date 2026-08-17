<?php
/**
 * Admin controller: menus, assets, entry actions, CSV export.
 *
 * @package WPFormsKit
 */

namespace FormsKit\Admin;

use FormsKit\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_export_csv' ) );
		add_action( 'admin_init', array( $this, 'handle_entry_actions' ) );
	}

	/**
	 * Register admin submenus.
	 */
	public function menu() {
		$parent = 'edit.php?post_type=' . Post_Type::POST_TYPE;

		add_submenu_page(
			$parent,
			__( 'Entries', 'flint-forms' ),
			__( 'Entries', 'flint-forms' ),
			'edit_posts',
			'formskit-entries',
			array( $this, 'render_entries_page' )
		);

		add_submenu_page(
			$parent,
			__( 'Settings', 'flint-forms' ),
			__( 'Settings', 'flint-forms' ),
			'manage_options',
			'formskit-settings',
			array( '\FormsKit\Admin\Settings', 'render_page' )
		);

		/**
		 * Lets add-ons register extra submenus (e.g. Pro license, integrations).
		 *
		 * @param string $parent Parent menu slug.
		 */
		do_action( 'formskit_admin_menu', $parent );
	}

	/**
	 * Whether the current screen belongs to FormsKit.
	 *
	 * @return bool
	 */
	protected function is_formskit_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		if ( Post_Type::POST_TYPE === $screen->post_type ) {
			return true;
		}
		return isset( $_GET['page'] ) && in_array( sanitize_key( wp_unslash( $_GET['page'] ) ), array( 'formskit-entries', 'formskit-settings' ), true ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Enqueue admin assets on FormsKit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( $hook ) {
		unset( $hook );
		if ( ! $this->is_formskit_screen() ) {
			return;
		}

		wp_enqueue_style( 'formskit-admin', FORMSKIT_URL . 'assets/css/admin.css', array(), FORMSKIT_VERSION );
		wp_enqueue_script( 'formskit-admin-builder', FORMSKIT_URL . 'assets/js/admin-builder.js', array( 'jquery', 'jquery-ui-sortable', 'wp-util' ), FORMSKIT_VERSION, true );

		$types = array();
		foreach ( formskit()->fields->all() as $slug => $def ) {
			$types[ $slug ] = array(
				'label'       => $def['label'],
				'icon'        => $def['icon'],
				'has_options' => ! empty( $def['has_options'] ),
				'group'       => $def['group'],
				'pro'         => ! empty( $def['pro'] ),
			);
		}

		wp_localize_script(
			'formskit-admin-builder',
			'FormsKitAdmin',
			array(
				'fieldTypes' => $types,
				'i18n'       => array(
					'newField'    => __( 'New Field', 'flint-forms' ),
					'option'      => __( 'Option', 'flint-forms' ),
					'confirmDel'  => __( 'Remove this field?', 'flint-forms' ),
					'untitled'    => __( 'Untitled Field', 'flint-forms' ),
					'proField'    => __( 'This is a Pro field. Upgrade to use it.', 'flint-forms' ),
				),
				'isPro'      => \FormsKit\is_pro(),
			)
		);

		/**
		 * Allow add-ons to enqueue builder assets.
		 */
		do_action( 'formskit_admin_assets' );
	}

	/**
	 * Render the Entries admin page (list or single view).
	 */
	public function render_entries_page() {
		if ( isset( $_GET['entry'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->render_single_entry( absint( $_GET['entry'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$table = new Entries_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap formskit-entries-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Form Entries', 'flint-forms' ); ?></h1>
			<?php $this->export_button(); ?>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( Post_Type::POST_TYPE ); ?>" />
				<input type="hidden" name="page" value="formskit-entries" />
				<?php
				$table->form_filter_dropdown();
				$table->search_box( __( 'Search Entries', 'flint-forms' ), 'formskit-entries' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a single entry detail view.
	 *
	 * @param int $entry_id Entry ID.
	 */
	protected function render_single_entry( $entry_id ) {
		$entry = formskit()->entries->get( $entry_id );
		if ( ! $entry ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Entry not found.', 'flint-forms' ) . '</p></div>';
			return;
		}

		formskit()->entries->set_status( $entry_id, 'read' );

		$labels = isset( $entry['meta']['labels'] ) ? $entry['meta']['labels'] : array();
		$back   = admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE . '&page=formskit-entries' );
		?>
		<div class="wrap formskit-entry-detail">
			<h1 class="wp-heading-inline">
				<?php printf( esc_html__( 'Entry #%d', 'flint-forms' ), (int) $entry['id'] ); ?>
			</h1>
			<a href="<?php echo esc_url( $back ); ?>" class="page-title-action"><?php esc_html_e( 'Back to entries', 'flint-forms' ); ?></a>
			<hr class="wp-header-end" />

			<div class="formskit-entry-columns">
				<div class="formskit-entry-main">
					<table class="widefat striped">
						<tbody>
						<?php foreach ( $entry['fields'] as $name => $value ) : ?>
							<tr>
								<th style="width:220px;"><?php echo esc_html( isset( $labels[ $name ] ) ? $labels[ $name ] : $name ); ?></th>
								<td><?php echo nl2br( esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="formskit-entry-side">
					<div class="postbox"><div class="inside">
						<p><strong><?php esc_html_e( 'Form:', 'flint-forms' ); ?></strong> <?php echo esc_html( get_the_title( $entry['form_id'] ) ); ?></p>
						<p><strong><?php esc_html_e( 'Submitted:', 'flint-forms' ); ?></strong> <?php echo esc_html( $entry['created_at'] ); ?></p>
						<p><strong><?php esc_html_e( 'IP:', 'flint-forms' ); ?></strong> <?php echo esc_html( $entry['ip_address'] ); ?></p>
						<?php if ( ! empty( $entry['meta']['referer'] ) ) : ?>
							<p><strong><?php esc_html_e( 'Page:', 'flint-forms' ); ?></strong><br /><a href="<?php echo esc_url( $entry['meta']['referer'] ); ?>"><?php echo esc_html( $entry['meta']['referer'] ); ?></a></p>
						<?php endif; ?>
					</div></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the export button (links back to the current filtered view).
	 */
	protected function export_button() {
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$url     = wp_nonce_url(
			add_query_arg(
				array(
					'post_type'       => Post_Type::POST_TYPE,
					'page'            => 'formskit-entries',
					'formskit_export' => 1,
					'form_id'         => $form_id,
				),
				admin_url( 'edit.php' )
			),
			'formskit_export',
			'formskit_export_nonce'
		);
		printf( '<a href="%s" class="page-title-action">%s</a>', esc_url( $url ), esc_html__( 'Export CSV', 'flint-forms' ) );
	}

	/**
	 * Handle CSV export.
	 */
	public function maybe_export_csv() {
		if ( empty( $_GET['formskit_export'] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_GET['formskit_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['formskit_export_nonce'] ) ), 'formskit_export' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'flint-forms' ) );
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$result  = formskit()->entries->query( array( 'form_id' => $form_id, 'per_page' => 100000, 'page' => 1 ) );

		$columns = array();
		foreach ( $result['items'] as $entry ) {
			foreach ( array_keys( $entry['fields'] ) as $name ) {
				$label             = isset( $entry['meta']['labels'][ $name ] ) ? $entry['meta']['labels'][ $name ] : $name;
				$columns[ $name ] = $label;
			}
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=formskit-entries-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array_merge( array( 'ID', 'Date', 'IP' ), array_values( $columns ) ) );

		foreach ( $result['items'] as $entry ) {
			$row = array( $entry['id'], $entry['created_at'], $entry['ip_address'] );
			foreach ( array_keys( $columns ) as $name ) {
				$value = isset( $entry['fields'][ $name ] ) ? $entry['fields'][ $name ] : '';
				$row[] = is_array( $value ) ? implode( ', ', $value ) : $value;
			}
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Handle single-entry actions (delete).
	 */
	public function handle_entry_actions() {
		if ( empty( $_GET['formskit_action'] ) || empty( $_GET['entry'] ) ) {
			return;
		}
		$action   = sanitize_key( wp_unslash( $_GET['formskit_action'] ) );
		$entry_id = absint( $_GET['entry'] );

		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'formskit_entry_' . $entry_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'flint-forms' ) );
		}

		if ( 'delete' === $action ) {
			formskit()->entries->delete( $entry_id );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE . '&page=formskit-entries' ) );
		exit;
	}
}
