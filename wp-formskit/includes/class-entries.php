<?php
/**
 * Entry data store (custom table).
 *
 * @package WPFormsKit
 */

namespace FormsKit;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD wrapper around the entries table.
 */
class Entries {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'formskit_entries';
	}

	/**
	 * Insert an entry.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $fields  Field values keyed by field name.
	 * @param array $meta    Metadata (ip, user_agent, referer …).
	 * @return int Inserted ID or 0 on failure.
	 */
	public function insert( $form_id, array $fields, array $meta = array() ) {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'form_id'    => absint( $form_id ),
				'fields'     => wp_json_encode( $fields ),
				'meta'       => wp_json_encode( $meta ),
				'status'     => 'unread',
				'user_id'    => get_current_user_id(),
				'ip_address' => isset( $meta['ip'] ) ? $meta['ip'] : '',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a single entry, decoded.
	 *
	 * @param int $id Entry ID.
	 * @return array|null
	 */
	public function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $this->decode( $row ) : null;
	}

	/**
	 * Query entries.
	 *
	 * @param array $args Query args (form_id, status, per_page, page, search, orderby, order).
	 * @return array{items:array,total:int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'form_id'  => 0,
				'status'   => '',
				'search'   => '',
				'per_page' => 20,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['form_id'] ) {
			$where[]  = 'form_id = %d';
			$params[] = absint( $args['form_id'] );
		}
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( '' !== $args['search'] ) {
			$where[]  = 'fields LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_sql = implode( ' AND ', $where );

		$allowed_orderby = array( 'id', 'created_at', 'status', 'form_id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, absint( $args['per_page'] ) );
		$offset   = ( max( 1, absint( $args['page'] ) ) - 1 ) * $per_page;

		// Total.
		$count_sql = "SELECT COUNT(*) FROM {$this->table()} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql ); // phpcs:ignore WordPress.DB

		// Items.
		$sql          = "SELECT * FROM {$this->table()} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A ); // phpcs:ignore WordPress.DB

		return array(
			'items' => array_map( array( $this, 'decode' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Update an entry status.
	 *
	 * @param int    $id     Entry ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public function set_status( $id, $status ) {
		global $wpdb;
		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array( 'status' => sanitize_key( $status ) ),
			array( 'id' => absint( $id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete an entry.
	 *
	 * @param int $id Entry ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => absint( $id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Count entries for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return int
	 */
	public function count_for_form( $form_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE form_id = %d", absint( $form_id ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Decode JSON columns on a raw row.
	 *
	 * @param array $row Raw DB row.
	 * @return array
	 */
	protected function decode( array $row ) {
		$row['fields'] = json_decode( isset( $row['fields'] ) ? $row['fields'] : '', true ) ?: array();
		$row['meta']   = json_decode( isset( $row['meta'] ) ? $row['meta'] : '', true ) ?: array();
		return $row;
	}
}
