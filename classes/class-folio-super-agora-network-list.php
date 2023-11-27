<?php

/**
 * Folio Super Agora Network List
 * 
 * @since      1.0.2
 *
 * @package         Folio
 * @subpackage 		Super
 */

/**
 * WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary
 * See WP_MS_Sites_List_Table
 */
if (!class_exists('WP_List_Table')) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Folio_Super_Agora_Network_List extends WP_List_Table {

	/**
	 * Site status list.
	 *
	 * @since 1.0.2
	 * @var array
	 */
	public $status_list;


	/**
	 * Set up a constructor that references the parent constructor
	 */
	function __construct($args = array()) {

		$this->status_list = array(
			'archived' => array( 'site-archived', __( 'Archived' ) ),
			'spam'     => array( 'site-spammed', _x( 'Spam', 'site' ) ),
			'deleted'  => array( 'site-deleted', __( 'Deleted' ) ),
			'mature'   => array( 'site-mature', __( 'Mature' ) ),
		);

		//Set parent defaults
		parent::__construct(array(
			'singular'  => 'superagora',    // singular name of the listed records
			'plural'    => 'superagoras',	// plural name of the listed records
			'ajax'      => false,           // does this table support ajax?
		));

	}


	/**
	 * Get columns
	 * 
	 * @since 1.0.2
	 * @return array
	 */
	function get_columns() {
		global $mode;

		$columns = array(
			//'id'          => 'ID',
			'blogname'    => __( 'URL' ),
			'lastupdated' => __( 'Last Updated' ),
			'registered'  => _x( 'Registered', 'site' ),
			'classrooms'  => __( 'Classrooms', 'folio-super-agora' ),
		);

		return $columns;
	}

	/**
	 * @return array
	 * 
	 * @since 1.0.2
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'blogname'    => array('blogname', true),
			'lastupdated' => array('lastupdated', true),
			'registered'  => array('blog_id', true),
		);
	}

	/**
	 * Prepares the list of sites for display.
	 *
	 * @since 1.0.2
	 *
	 * @global string $mode List table view mode.
	 * @global string $s
	 * @global wpdb   $wpdb WordPress database abstraction object.
	 */
	public function prepare_items() {
		global $mode, $s, $wpdb;

		$columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

		if ( ! empty( $_REQUEST['mode'] ) ) {
			$mode = 'excerpt' === $_REQUEST['mode'] ? 'excerpt' : 'list';
			update_user_meta( get_current_user_id(), 'superagoras_list_mode', $mode );
		} else {
			$mode = get_user_meta( get_current_user_id(), 'superagoras_list_mode', true );
			if (! $mode ){
				$mode = 'excerpt';
			}
		}

		$per_page = $this->get_items_per_page( 'superagoras_per_page' );

		$pagenum = $this->get_pagenum();

		$s    = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';
		$wild = '';
		if ( false !== strpos( $s, '*' ) ) {
			$wild = '*';
			$s    = trim( $s, '*' );
		}

		$args = array(
			'number'     => (int) $per_page,
			'offset'     => (int) ( ( $pagenum - 1 ) * $per_page ),
			'network_id' => get_current_network_id(),
		);

		if ( empty( $s ) ) {
			// Nothing to do.
		} elseif ( preg_match( '/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $s ) ||
					preg_match( '/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.?$/', $s ) ||
					preg_match( '/^[0-9]{1,3}\.[0-9]{1,3}\.?$/', $s ) ||
					preg_match( '/^[0-9]{1,3}\.$/', $s ) ) {
			// IPv4 address.
			$sql          = $wpdb->prepare( "SELECT blog_id FROM {$wpdb->registration_log} WHERE {$wpdb->registration_log}.IP LIKE %s", $wpdb->esc_like( $s ) . ( ! empty( $wild ) ? '%' : '' ) );
			$reg_blog_ids = $wpdb->get_col( $sql );

			if ( $reg_blog_ids ) {
				$args['site__in'] = $reg_blog_ids;
			}
		} elseif ( is_numeric( $s ) && empty( $wild ) ) {
			$args['ID'] = $s;
		} else {
			$args['search'] = $s;

			if ( ! is_subdomain_install() ) {
				$args['search_columns'] = array( 'path' );
			}
		}

		$order_by = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : '';
		if ( 'registered' === $order_by ) {
			// 'registered' is a valid field name.
		} elseif ( 'lastupdated' === $order_by ) {
			$order_by = 'last_updated';
		} elseif ( 'blogname' === $order_by ) {
			if ( is_subdomain_install() ) {
				$order_by = 'domain';
			} else {
				$order_by = 'path';
			}
		} elseif ( 'blog_id' === $order_by ) {
			$order_by = 'id';
		} elseif ( ! $order_by ) {
			$order_by = false;
		}

		$args['orderby'] = $order_by;

		if ( $order_by ) {
			$args['order'] = ( isset( $_REQUEST['order'] ) && 'DESC' === strtoupper( $_REQUEST['order'] ) ) ? 'DESC' : 'ASC';
		}

		$args['no_found_rows'] = false;

		// WP_Site_Query arguments
        $args['meta_query'] = array(
            array(
                'key'   => 'is_superagora',
                'value' => '1'
            )
        );

		$_sites = get_sites( $args );
		if ( is_array( $_sites ) ) {
			$this->items = array_slice( $_sites, 0, $per_page );
		}

		$total_sites = get_sites(
			array_merge(
				$args,
				array(
					'count'  => true,
					'offset' => 0,
					'number' => 0,
				)
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total_sites,
				'per_page'    => $per_page,
			)
		);
	}


	/**
	 * Handles output for the default column.
	 *
	 * @since 1.0.2
	 *
	 * @param array  $item        Current site.
	 * @param string $column_name Current column name.
	 */
	public function column_default( $item, $column_name ) {
		/**
		 * Fires for each registered custom column in the Sites list table.
		 *
		 * @since 1.0.1
		 *
		 * @param string $column_name The name of the column to display.
		 * @param int    $blog_id     The site ID.
		 */
		do_action( 'manage_sites_custom_column', $column_name, $item['blog_id'] );
	}


	/**
	 * Handles the ID column output.
	 *
	 * @since 1.0.2
	 *
	 * @param array $blog Current site.
	 */
	public function column_id( $blog ) {
		echo $blog['blog_id'];
	}

	/**
	 * Handles the site name column output.
	 *
	 * @since 1.0.2
	 *
	 * @global string $mode List table view mode.
	 *
	 * @param array $blog Current site.
	 */
	public function column_blogname( $blog ) {
		global $mode;

		$blogname = untrailingslashit( $blog['domain'] . $blog['path'] );

		?>
		<strong>
			<a href="<?php echo esc_url( network_admin_url( 'site-info.php?id=' . $blog['blog_id'] ) ); ?>" class="edit"><?php echo $blogname; ?></a>
			<?php $this->site_states( $blog ); ?>
		</strong>
		<?php
		if ( 'list' !== $mode ) {
			switch_to_blog( $blog['blog_id'] );
			echo '<p>';
			printf(
				/* translators: 1: Site title, 2: Site tagline. */
				__( '%1$s &#8211; %2$s' ),
				get_option( 'blogname' ),
				'<em>' . get_option( 'blogdescription' ) . '</em>'
			);
			echo '</p>';
			restore_current_blog();
		}
	}

	/**
	 * Handles the lastupdated column output.
	 *
	 * @since 1.0.2
	 *
	 * @global string $mode List table view mode.
	 *
	 * @param array $blog Current site.
	 */
	public function column_lastupdated( $blog ) {
		global $mode;

		if ( 'list' === $mode ) {
			$date = __( 'Y/m/d' );
		} else {
			$date = __( 'Y/m/d g:i:s a' );
		}

		echo ( '0000-00-00 00:00:00' === $blog['last_updated'] ) ? __( 'Never' ) : mysql2date( $date, $blog['last_updated'] );
	}

	/**
	 * Handles the registered column output.
	 *
	 * @since 1.0.2
	 *
	 * @global string $mode List table view mode.
	 *
	 * @param array $blog Current site.
	 */
	public function column_registered( $blog ) {
		global $mode;

		if ( 'list' === $mode ) {
			$date = __( 'Y/m/d' );
		} else {
			$date = __( 'Y/m/d g:i:s a' );
		}

		if ( '0000-00-00 00:00:00' === $blog['registered'] ) {
			echo '&#x2014;';
		} else {
			echo mysql2date( $date, $blog['registered'] );
		}
	}

	/**
	 * Handles the agoras column output.
	 *
	 * @since 1.0.2
	 *
	 * @param array $blog Current site.
	 */
	public function column_classrooms( $blog ) {
		global $mode;

		$options = get_blog_option( $blog['blog_id'], 'folio_superagora_settings', false );

		if ( 'list' === $mode ) {
			$codes = isset($options['codes']) ? $options['codes'] : [];
			if (count($codes)){
				echo implode(",", $codes);
			}else{
				echo __('No classrooms found', 'folio-super-agora');
			}
		} else {
			$agoras = isset($options['agoras']) ? $options['agoras'] : [];
			if (count($agoras)){
				foreach ( $agoras as $agora ) {
					switch_to_blog($agora['blog_id']);
					$site_url = get_bloginfo( 'url' );
					$site_name = get_bloginfo( 'blog_name' );
					restore_current_blog();
					printf(
						'<a href="%s">%s</a><br>',
						esc_url( $site_url ),
						$site_name 
					);
				}
			}else{
				echo __('No classrooms found', 'folio-super-agora');
			}
		}
		
	}


	/**
	 * Maybe output comma-separated site states.
	 *
	 * @since 1.0.2
	 *
	 * @param array $site
	 */
	protected function site_states( $site ) {
		$site_states = array();

		// $site is still an array, so get the object.
		$_site = WP_Site::get_instance( $site['blog_id'] );

		if ( is_main_site( $_site->id ) ) {
			$site_states['main'] = __( 'Main' );
		}

		reset( $this->status_list );

		$site_status = isset( $_REQUEST['status'] ) ? wp_unslash( trim( $_REQUEST['status'] ) ) : '';
		foreach ( $this->status_list as $status => $col ) {
			if ( ( 1 === (int) $_site->{$status} ) && ( $site_status !== $status ) ) {
				$site_states[ $col[0] ] = $col[1];
			}
		}

		/**
		 * Filters the default site display states for items in the Sites list table.
		 *
		 * @since 1.0.2
		 *
		 * @param string[] $site_states An array of site states. Default 'Main',
		 *                              'Archived', 'Mature', 'Spam', 'Deleted'.
		 * @param WP_Site  $site        The current site object.
		 */
		$site_states = apply_filters( 'display_site_states', $site_states, $_site );

		if ( ! empty( $site_states ) ) {
			$state_count = count( $site_states );

			$i = 0;

			echo ' &mdash; ';

			foreach ( $site_states as $state ) {
				++$i;

				$separator = ( $i < $state_count ) ? ', ' : '';

				echo "<span class='post-state'>{$state}{$separator}</span>";
			}
		}
	}


	/**
	 * Gets the name of the default primary column.
	 *
	 * @since 1.0.2
	 *
	 * @return string Name of the default primary column, in this case, 'blogname'.
	 */
	protected function get_default_primary_column_name() {
		return 'blogname';
	}


	/**
	 * Generates and displays row action links.
	 *
	 * @since 1.0.2
	 *
	 * @param array  $item        Site being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string Row actions output for sites in Multisite, or an empty string
	 *                if the current column is not the primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {

		if ( $primary !== $column_name ) {
			return '';
		}

		// Restores the more descriptive, specific name for use within this method.
		$blog     = $item;

		// Preordered.
		$actions = array(
			'edit'	     => '<a href="' . esc_url( network_admin_url( 'site-info.php?id=' . $blog['blog_id'] ) ) . '">' . __( 'Edit' ) . '</a>',
			'backend'	 => "<a href='" . esc_url( get_admin_url( $blog['blog_id'] ) ) . "' class='edit'>" . __( 'Dashboard' ) . '</a>',
			'visit'      => "<a href='" . esc_url( get_home_url( $blog['blog_id'], '/' ) ) . "' rel='bookmark'>" . __( 'Visit' ) . '</a>'
		);

		return $this->row_actions( $actions );

	}


	/**
	 * @global string $mode List table view mode.
	 */
	public function display_rows() {

		foreach ( $this->items as $blog ) {
			$blog  = $blog->to_array();
			$class = '';
			reset( $this->status_list );

			foreach ( $this->status_list as $status => $col ) {
				if ( 1 == $blog[ $status ] ) {
					$class = " class='{$col[0]}'";
				}
			}

			echo "<tr{$class}>";

			$this->single_row_columns( $blog );

			echo '</tr>';
		}
	}


	/**
	 * No items
	 */
	public function no_items() {
		_e( 'No SuperAgoras found.', 'folio-super-agora' );
	}


	/**
	 * @global string $mode List table view mode.
	 *
	 * @param string $which The location of the pagination nav markup: 'top' or 'bottom'.
	 */
	protected function pagination( $which ) {
		global $mode;

		parent::pagination( $which );

		if ( 'top' === $which ) {
			$this->view_switcher( $mode );
		}
	}

}
