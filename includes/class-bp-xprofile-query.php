<?php

/**
 * Build SQL clauses to limit a user query by xProfile data.
 *
 * Borrowed heavily from {@link WP_Meta_Query}.
 *
 * @since BuddyPress (2.2.0)
 */
class BP_XProfile_Query {

	/**
	* List of metadata queries. A single query is an associative array:
	* - 'key' string The meta key
	* - 'value' string|array The meta value
	* - 'compare' (optional) string How to compare the key to the value.
	*   Possible values: '=', '!=', '>', '>=', '<', '<=', 'LIKE',
	*	'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'REGEXP',
	*	'NOT REGEXP', 'RLIKE'. Default: '='.
	* - 'type' string (optional) The type of the value.
	*   Possible values: 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME',
	*       'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'. Default: 'CHAR'.
	*
	* @since BuddyPress (2.2.0)
	* @access public
	* @var array
	*/
	public $queries = array();

	/**
	 * The relation between the queries. Can be one of 'AND' or 'OR'.
	 *
	 * @since BuddyPress (2.2.0)
	 * @access public
	 * @var string
	 */
	public $relation;

	/**
	 * Table aliases.
	 *
	 * A list of table aliases used in the xprofile query.
	 *
	 * @since BuddyPress (2.2.0)
	 * @access public
	 * @var array
	 */
	public $aliases = array();

	/**
	 * Constructor.
	 *
	 * @since BuddyPress (2.2.0)
	 *
	 * @param array $xprofile_query Array of query parameters.
	 */
	public function __construct( $xprofile_query = array() ) {
		if ( empty( $xprofile_query ) ) {
			return;
		}

		// 'relation' defaults to 'AND'
		if ( isset( $xprofile_query['relation'] ) && strtoupper( $xprofile_query['relation'] ) === 'OR' ) {
			$this->relation = 'OR';
		} else {
			$this->relation = 'AND';
		}

		$this->queries = array();

		foreach ( $xprofile_query as $key => $query ) {
			// No empties
			if ( ! is_array( $query ) ) {
				continue;
			}

			$this->queries[] = $query;
		}

		// Standardize queries
		$this->transform_queries();
	}

	/**
	 * Transform queries as necessary.
	 *
	 * Translates field_name param into field_id.
	 *
	 * @since BuddyPress (2.2.0)
	 *
	 * @param array $query
	 * @return array
	 */
	protected function transform_queries() {
		foreach ( $this->queries as $k => &$query ) {
			// If a field_id is provided, trust it
			if ( ! empty( $query['field_id'] ) ) {
				continue;
			}

			// If no field_name is provided, nothing to do here
			if ( empty( $query['field_name'] ) ) {
				continue;
			}

			$field_id = xprofile_get_field_id_from_name( $query['field_name'] );

			// Runnning through intval() means that failed
			// lookups will result in field_id = 0. This leads to
			// expected behavior in the SQL query later on
			$query['field_id'] = intval( $field_id );
		}
	}

	/**
	 * Given a data type, return the appropriate alias if applicable.
	 *
	 * The data type will be used to CAST the 'value' column in
	 * self::get_sql().
	 *
	 * @since BuddyPress (2.2.0)
	 *
	 * @param string $type MySQL type to cast.
	 * @return string MySQL type.
	 */
	public function get_cast_for_type( $type = '' ) {
		if ( empty( $type ) ) {
			return 'CHAR';
		}

		$type = strtoupper( $type );

		if ( ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', $type ) )
			return 'CHAR';

		if ( 'NUMERIC' == $type ) {
			$type = 'SIGNED';
		}

		return $type;
	}

	/**
	 * Generate SQL for this set of xprofile query clauses.
	 *
	 * @param string $type This param does nothing in this context. It's
	 *        kept here for parity with the function arguments in
	 *        WP_Meta_Query.
	 * @param string $primary_table SQL alias for the primary table in the
	 *        user query. Will typically be 'u'.
	 * @param string $primary_id_column Name of the SQL column containing
	 *        the user ID in $primary_table. Will be 'ID' in the case of
	 *        wp_users, and 'user_id' in the case of other BuddyPress
	 *        tables.
	 * @return array {
	 *     @type string $join JOIN clauses
	 *     @type string $where WHERE clauses
	 * }
	 */
	public function get_sql( $type = 'xprofile', $primary_table, $primary_id_column ) {
		global $wpdb;

		$data_table = buddypress()->profile->table_name_data;

		$join = $where = $queries = $field_id_only_queries = array();

		// We can save a JOIN on some queries by combining EXISTS queries
		// (or queries with no 'value', which amounts to the same
		// thing) into a single WHERE clause. First, find queries with
		// an empty array as the 'value'
		foreach ( $this->queries as $k => $q ) {
			if ( isset( $q['value'] ) && is_array( $q['value'] ) && empty( $q['value'] ) ) {
				$field_id_only_queries[ $k ] = $q;
				unset( $this->queries[ $k ] );
			}
		}

		// In the case of multiple clauses joined by OR, we can also
		// group together empty non-array values of 'value'
		if ( 'OR' == $this->relation ) {
			foreach ( $this->queries as $k => $q ) {
				if ( ( empty( $q['compare'] ) || 'NOT EXISTS' != $q['compare'] ) && ! array_key_exists( 'value', $q ) && ! empty( $q['field_id'] ) ) {
					$field_id_only_queries[ $k ] = $q;
				} else {
					$queries[ $k ] = $q;
				}
			}
		} else {
			$queries = $this->queries;
		}

		// Specify all the meta_key only queries in one go
		if ( $field_id_only_queries ) {
			$join[]  = "INNER JOIN $data_table ON $primary_table.$primary_id_column = $data_table.user_id";

			foreach ( $field_id_only_queries as $key => $q ) {
				$where["field-id-only-$key"] = $wpdb->prepare( "$data_table.field_id = %d", $q['field_id'] );
			}
		}

		$where_meta_key = array();

		foreach ( $queries as $k => $q ) {
			$field_id = isset( $q['field_id'] ) ? intval( $q['field_id'] ) : '';
			$data_type = $this->get_cast_for_type( isset( $q['type'] ) ? $q['type'] : '' );

			if ( array_key_exists( 'value', $q ) && is_null( $q['value'] ) ) {
				$q['value'] = '';
			}

			$value = isset( $q['value'] ) ? $q['value'] : null;

			if ( isset( $q['compare'] ) ) {
				$compare = strtoupper( $q['compare'] );
			} else {
				$compare = is_array( $value ) ? 'IN' : '=';
			}

			if ( ! in_array( $compare, array(
				'=', '!=', '>', '>=', '<', '<=',
				'LIKE', 'NOT LIKE',
				'IN', 'NOT IN',
				'BETWEEN', 'NOT BETWEEN',
				'NOT EXISTS',
				'REGEXP', 'NOT REGEXP', 'RLIKE'
			) ) ) {
				$compare = '=';
			}

			// Iterate the table alias
			$i = count( $join );
			$alias = $i ? 'xpd' . $i : $data_table;

			if ( 'NOT EXISTS' == $compare ) {
				$join[ $i ]  = "LEFT JOIN $data_table";
				$join[ $i ] .= $i ? " AS $alias" : '';
				$join[ $i ] .= " ON ($primary_table.$primary_id_column = $alias.user_id AND $alias.field_id = '$field_id')";

				$where[ $k ] = ' ' . $alias . '.user_id IS NULL';

				continue;
			}

			// Store the table alias - we will use it for lookups
			// in the populate_extras() method
			$this->aliases[ $k ] = $alias;

			$join[ $i ]  = "INNER JOIN $data_table";
			$join[ $i ] .= $i ? " AS $alias" : '';
			$join[ $i ] .= " ON ($primary_table.$primary_id_column = $alias.user_id)";

			$where[ $k ] = '';

			// The is_null() check (instead of empty) allows for
			// a field_id of 0 to be passed. This is necessary to
			// ensure a failed lookup when a bad field_id or
			// field_name is passed
			if ( ! is_null( $field_id ) ) {
				if ( isset( $q['compare'] ) ) {
					$where_meta_key[ $k ] = $wpdb->prepare( "$alias.field_id = %s", $field_id );
				} else {
					$where[ $k ] = $wpdb->prepare( "$alias.field_id = %s", $field_id );
				}
			}

			if ( is_null( $value ) ) {
				if ( empty( $where[ $k ] ) && empty( $where_meta_key ) ) {
					unset( $join[ $i ] );
				}
				continue;
			}

			if ( in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
				if ( ! is_array( $value ) ) {
					$value = preg_split( '/[,\s]+/', $value );
				}

				if ( empty( $value ) ) {
					unset( $join[ $i ] );
					continue;
				}
			} else {
				$value = trim( $value );
			}

			// SQL clause syntax depends on the $compare operator
			switch ( $compare ) {
				case 'IN' :
				case 'NOT IN' :
					$compare_string = '(' . substr( str_repeat( ',%s', count( $value ) ), 1 ) . ')';
					break;

				case 'BETWEEN' :
				case 'NOT BETWEEN' :
					$value = array_slice( $value, 0, 2 );
					$compare_string = '%s AND %s';
					break;

				case 'LIKE' :
				case 'NOT LIKE' :
					$value = '%' . $wpdb->esc_like( $value ) . '%';
					$compare_string = '%s';

				default :
					$compare_string = '%s';
					break;
			}

			if ( ! empty( $where[ $k ] ) ) {
				$where[ $k ] .= ' AND ';
			}

			$where[ $k ] = ' (' . $where[ $k ] . $wpdb->prepare( "CAST($alias.value AS {$data_type}) {$compare} {$compare_string})", $value );
		}

		// Remove empties
		$where = array_filter( $where );

		if ( empty( $where ) ) {
			$where = '';
		} else {
			$where = ' AND ( ' . implode( "\n{$this->relation} ", $where ) . ' )';
		}

		if ( ! empty( $where_meta_key ) ) {
			$where .= "\nAND ( " . implode( "\nAND ", $where_meta_key ) . ' )';
		}

		$join = implode( "\n", $join );
		if ( ! empty( $join ) ) {
			$join = ' ' . $join;
		}

		/**
		 * Filter the xProfile query's generated SQL.
		 *
		 * @since BuddyPress (2.2.0)
		 *
		 * @param array $args {
		 *     An array of arguments.
		 *
		 *     @type array $clauses Array containing the query's JOIN
		 *	     and WHERE clauses.
		 *     @type array $queries Array of xprofile queries, as
		 *           passed to the constructor.
		 *     @type string $type 'xprofile'
		 *     @type string $primary_table Table of the primary query.
		 *     @type string $primary_id_column Primary column ID.
		 * }
		 */
		$clauses = apply_filters_ref_array( 'bp_get_xprofile_meta_sql', array( compact( 'join', 'where' ), $this->queries, $type, $primary_table, $primary_id_column ) );

		// Store to make available to later filters
		$this->clauses = $clauses;

		return $clauses;
	}

	/**
	 * Add information about matched xprofile fields to the BP_User_Query results.
	 *
	 * @since BuddyPress (2.2.0)
	 *
	 * @param BP_User_Query User query object.
	 * @param string $user_ids_sql
	 */
	public function populate_extras( BP_User_Query $query, $user_ids_sql ) {
		global $wpdb;

		if ( empty( $this->aliases ) ) {
			return;
		}

		// We don't need any JOINs because we want to gather all data
		// regardless of duplicates. So we zero out the table aliases
		// and do a simple SELECT query
		$where = preg_replace( '/^\s*AND /', '', $this->clauses['where'] );
		$where = str_replace( $this->aliases, buddypress()->profile->table_name_data, $where );

		$sql = 'SELECT * FROM ' . buddypress()->profile->table_name_data . ' WHERE ' . $where . ' AND user_id IN (' . $user_ids_sql . ')';

		$xprofile_data = $wpdb->get_results( $sql );

		// Add the data to the user objects
		foreach ( $xprofile_data as $xpd ) {
			if ( ! isset( $xpd->user_id ) ) {
				continue;
			}

			$user_id = $xpd->user_id;

			if ( ! isset( $query->results[ $user_id ]->xprofile_matched_fields ) ) {
				$query->results[ $user_id ]->xprofile_matched_fields = array();
			}

			foreach ( $xpd as $xpd_k => $xpd_v ) {
				if ( ! isset( $xpd->field_id ) ) {
					continue;
				}

				$field_id = $xpd->field_id;

				if ( isset( $query->results[ $user_id ]->xprofile_matched_fields[ $field_id ] ) ) {
					continue;
				}

				if ( ! isset( $xpd->value ) ) {
					continue;
				}

				$query->results[ $user_id ]->xprofile_matched_fields[ $field_id ] = $xpd->value;
			}
		}

		// Don't recurse
		remove_action( 'bp_user_query_populate_extras', array( $this, 'populate_extras' ), 10, 2 );
	}
}

/**
 * Process xprofile_query parameters in BP_User_Query.
 *
 * @since BuddyPress (2.2.0)
 *
 * @param BP_User_Query Query object.
 */
function bp_xprofile_process_xprofile_query( $user_query ) {
	if ( empty( $user_query->query_vars['xprofile_query'] ) ) {
		return;
	}

	$user_query->xprofile_query = new BP_XProfile_Query( $user_query->query_vars['xprofile_query'] );
	$xprofile_query_sql         = $user_query->xprofile_query->get_sql( 'xprofile', 'u', 'user_id' );

	$user_query->uid_clauses['select'] .= $xprofile_query_sql['join'];
	$user_query->uid_clauses['where']  .= $xprofile_query_sql['where'];

	add_action( 'bp_user_query_populate_extras', array( $user_query->xprofile_query, 'populate_extras' ), 10, 2 );
}
add_action( 'bp_pre_user_query', 'bp_xprofile_process_xprofile_query' );
