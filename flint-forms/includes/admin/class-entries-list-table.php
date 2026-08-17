<?php
/**
 * Entries WP_List_Table.
 *
 * @package WPFormsKit
 */

namespace FormsKit\Admin;

use FormsKit\Post_Type;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists submitted entries.
 */
class Entries_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'summary'    => __( 'Entry', 'flint-forms' ),
			'form'       => __( 'Form', 'flint-forms' ),
			'status'     => __( 'Status', 'flint-forms' ),
			'created_at' => __( 'Date', 'flint-forms' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'status'     => array( 'status', false ),
		);
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$args = array(
			'form_id'  => isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
			'search'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at', // phpcs:ignore WordPress.Security.NonceVerification
			'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc', // phpcs:ignore WordPress.Security.NonceVerification
			'per_page' => $per_page,
			'page'     => $paged,
		);

		$result = formskit()->entries->query( $args );

		$this->items = $result['items'];
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="entry[]" value="%d" />', (int) $item['id'] );
	}

	/**
	 * Summary column (first couple of fields + row actions).
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	public function column_summary( $item ) {
		$labels  = isset( $item['meta']['labels'] ) ? $item['meta']['labels'] : array();
		$preview = array();
		$i       = 0;
		foreach ( $item['fields'] as $name => $value ) {
			if ( $i >= 2 ) {
				break;
			}
			$label     = isset( $labels[ $name ] ) ? $labels[ $name ] : $name;
			$value     = is_array( $value ) ? implode( ', ', $value ) : $value;
			$preview[] = '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( wp_trim_words( $value, 8 ) );
			$i++;
		}

		$view_url   = add_query_arg(
			array(
				'post_type' => Post_Type::POST_TYPE,
				'page'      => 'formskit-entries',
				'entry'     => (int) $item['id'],
			),
			admin_url( 'edit.php' )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'post_type'       => Post_Type::POST_TYPE,
					'page'            => 'formskit-entries',
					'entry'           => (int) $item['id'],
					'formskit_action' => 'delete',
				),
				admin_url( 'edit.php' )
			),
			'formskit_entry_' . (int) $item['id']
		);

		$actions = array(
			'view'   => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( 'View', 'flint-forms' ) ),
			'delete' => sprintf( '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_url ), esc_js( __( 'Delete this entry?', 'flint-forms' ) ), esc_html__( 'Delete', 'flint-forms' ) ),
		);

		$weight = ( 'unread' === $item['status'] ) ? 'font-weight:600;' : '';

		return sprintf(
			'<a href="%1$s" style="%2$s">%3$s</a>%4$s',
			esc_url( $view_url ),
			esc_attr( $weight ),
			wp_kses_post( implode( '<br />', $preview ) ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Form column.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	public function column_form( $item ) {
		return esc_html( get_the_title( $item['form_id'] ) );
	}

	/**
	 * Status column.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	public function column_status( $item ) {
		$map = array(
			'unread' => __( 'Unread', 'flint-forms' ),
			'read'   => __( 'Read', 'flint-forms' ),
			'spam'   => __( 'Spam', 'flint-forms' ),
		);
		$label = isset( $map[ $item['status'] ] ) ? $map[ $item['status'] ] : $item['status'];
		return '<span class="formskit-status formskit-status-' . esc_attr( $item['status'] ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Date column.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	public function column_created_at( $item ) {
		$ts = strtotime( $item['created_at'] );
		return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) );
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param array  $item        Entry.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Empty-state message.
	 */
	public function no_items() {
		esc_html_e( 'No entries found yet.', 'flint-forms' );
	}

	/**
	 * Render a form filter dropdown above the table.
	 */
	public function form_filter_dropdown() {
		$forms   = get_posts( array( 'post_type' => Post_Type::POST_TYPE, 'numberposts' => 200, 'post_status' => 'publish' ) );
		$current = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="alignleft actions"><select name="form_id">';
		echo '<option value="0">' . esc_html__( 'All forms', 'flint-forms' ) . '</option>';
		foreach ( $forms as $form ) {
			printf( '<option value="%d" %s>%s</option>', (int) $form->ID, selected( $current, $form->ID, false ), esc_html( $form->post_title ) );
		}
		echo '</select> <button class="button">' . esc_html__( 'Filter', 'flint-forms' ) . '</button></div>';
	}
}
