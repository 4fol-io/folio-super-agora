<?php

/**
 * Folio SuperAgora Tags
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */

class Folio_Super_Agora_Super_Tags extends Folio_Super_Agora_Base {

	/**
	 * Initialize the class
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		// Register Super Tags Taxonomy
		add_action('init', [$this, 'register_super_tag']);

		// Custom SuperTags Metabox (assign only as checkbox list if no allow manage)
		add_action('add_meta_boxes', [$this, 'meta_boxes'], 10, 2);

		// Sync descendants Agoras and/or Student's Folios
		add_action( 'delete_' . $this->supertag_tx_key, [ $this, 'deleted_supertag' ], 99, 3);
		add_action( 'saved_' . $this->supertag_tx_key, [ $this, 'saved_supertag' ], 99, 3);

		// Sync super tags post terms
		add_filter('folio-supertags/get', array($this, 'get_post_supertags'), 99, 3);
		add_action('folio-supertags/set', array($this, 'set_post_supertags'), 99, 3);
		
	}

	/**
	 * Register custom super tag taxonomy
	 */
	public function register_super_tag() {

		$labels = array(
			'name'                       => _x('SuperTags', 'taxonomy general name', 'folio-super-agora'),
			'singular_name'              => _x('SuperTag', 'taxonomy singular name', 'folio-super-agora'),
			'search_items'               => __('Search SuperTags', 'folio-super-agora'),
			'popular_items'              => __('Popular SuperTags', 'folio-super-agora'),
			'all_items'                  => __('All SuperTags', 'folio-super-agora'),
			'edit_item'                  => __('Edit SuperTag', 'folio-super-agora'),
			'update_item'                => __('Update SuperTag', 'folio-super-agora'),
			'add_new_item'               => __('Add New SuperTag', 'folio-super-agora'),
			'new_item_name'              => __('New SuperTag Name', 'folio-super-agora'),
			'separate_items_with_commas' => __('Separate supertags with commas', 'folio-super-agora'),
			'add_or_remove_items'        => __('Add or remove supertags', 'folio-super-agora'),
			'choose_from_most_used'      => __('Choose from the most used supertags', 'folio-super-agora'),
			'not_found'                  => __('No supertags found.', 'folio-super-agora'),
			'menu_name'                  => __('SuperTags', 'folio-super-agora'),
		);

		$caps = array (
			'assign_terms' => 'edit_posts',
			'manage_terms' => 'nobody',
			'edit_terms'   => 'nobody',
			'delete_terms' => 'nobody',
		);

		// Allo manage only if is SuperAgora or Standalone Agora
		$is_standalone = ucs_is_classroom_blog() && ! $this->belongs_to_super();
		$allow_manage = $this->is_super() || $is_standalone;

		if ( $allow_manage ){
			$caps['manage_terms'] = 'manage_categories';
			$caps['edit_terms'] = 'manage_categories';
			$caps['delete_terms'] = 'manage_categories';
		}

		$args = array(
			'hierarchical'      	=> false,
			'labels'            	=> $labels,
			'public'            	=> true,
			'show_ui'           	=> true,
			'show_in_menu'      	=> $allow_manage,
			'show_admin_column' 	=> $allow_manage,
			'query_var'         	=> true,
			'rewrite'           	=> array('slug' => 'supertag', 'with_front' => true),
			'show_in_rest'      	=> true,
			'show_in_quick_edit' 	=> false,
			'capabilities' 			=> $caps,
			'update_count_callback' => '_update_generic_term_count'
		);

		register_taxonomy($this->supertag_tx_key, $this->allowed_super_tags_post_types(), $args);
	}

	/**
	 * Deleted SuperTag action hook
	 * 
	 * @param int $term_id
	 * @param int $tt_id
	 * @param WP_Term $deleted_term
	 */
	public function deleted_supertag( $term_id, $tt_id, $deleted_term ) {
		if ( ! is_wp_error( $deleted_term ) ) {

			$super_settings =  $this->is_super();
			$is_standalone = ucs_is_classroom_blog() && ! $this->belongs_to_super();
			$classroom = false;

			if ( $super_settings ) { // is a SuperAgora propague to Agoras
				$agoras = isset($super_settings['agoras']) ? $super_settings['agoras'] : [];
				foreach ( $agoras as $agora ) {
					if ( isset($agora['blog_id']) ){
						$this->delete_supertag_term($agora['blog_id'], $deleted_term);
					}
				}
				$classroom = $this->get_super_classroom_id( get_current_blog_id() );
				
			} else if ( $is_standalone ) {
				$classroom = $this->get_classroom_id_from_blog_id( get_current_blog_id() );
			}

			// Is SuperAgora or Standalone Agora, propague to Students Folios
			if ( $classroom ){
				$users = $this->get_agora_classroom_users( $classroom );
				if( is_array($users) && !empty($users)) {
					foreach ( $users as $user ) {
						if ( isset( $user['userId'] ) ) {
							$blog_id = uoc_create_site_get_blog_id_from_user_id( $user['userId'] );
        					if ( $blog_id ) {
								$this->delete_supertag_term($blog_id, $deleted_term);
							}
						}
					}
				}
			}
		}	
	}

	/**
	 * Saved SuperTag action hook
	 * 
	 * @param int $term_id
	 * @param int $tt_id
	 * @param bool $update
	 */
	public function saved_supertag( $term_id, $tt_id, $update ) {
		$term = get_term_by('term_id', $term_id, $this->supertag_tx_key);

		if ($term && ! is_wp_error( $term ) ) {

			$super_settings =  $this->is_super();
			$is_standalone = ucs_is_classroom_blog() && ! $this->belongs_to_super();
			$classroom = false;

			if ( $super_settings ) { // is a SuperAgora
				$agoras = isset($super_settings['agoras']) ? $super_settings['agoras'] : [];
				foreach ( $agoras as $agora ) {
					if ( isset($agora['blog_id']) ){
						$this->update_supertag_term($agora['blog_id'], $term);
					}
				}
				$classroom = $this->get_super_classroom_id( get_current_blog_id() );
			} else if ( $is_standalone ) { // Is a Standalone Classroom
				$classroom = $this->get_classroom_id_from_blog_id( get_current_blog_id() );
			}

			// Is SuperAgora or Standalone Agora, propague to Students Folios
			if ( $classroom ){
				$users = $this->get_agora_classroom_users( $classroom );
				if( is_array($users) && !empty($users)) {
					foreach ( $users as $user ) {
						if ( isset( $user['userId'] ) ) {
							$blog_id = uoc_create_site_get_blog_id_from_user_id( $user['userId'] );
        					if ( $blog_id ) {
								$this->update_supertag_term($blog_id, $term);
							}
						}
					}
				}
			}

		}
	}

	/**
	 * Get supertags from post
	 *
	 * @since 1.0.0
	 *
	 * @param array	$terms
	 * @param int	$post_id
	 * @param int	$blog_id
	 * @return array
	 */
	public function get_post_supertags($terms = [], $post_id = 0, $blog_id = 0) {
		$cached = wp_cache_get( 'supertags_'. $post_id . '_' . $blog_id, 'super-agora' );
		if ( false !== $cached ) {
			return $cached;
        } else {
			$the_terms = wp_get_post_terms( $post_id, $this->supertag_tx_key, array( 'fields' => 'slugs' ) );
			if ( ! empty( $the_terms ) && ! is_wp_error( $the_terms ) ) {
				foreach ( $the_terms as $term ) {
					$terms[] = $term;
				}
				wp_cache_set( 'supertags_'. $post_id . '_' . $blog_id, $terms, 'super-agora' );
			}
		}
		return $terms;
	}


	/**
	 * Set supertags to post
	 *
	 * @since 1.0.0
	 *
	 * @param array $terms
	 * @param int	$post_id
	 * @param int	$blog_id
	 */
	public function set_post_supertags($terms = [], $post_id = 0, $blog_id = 0) {
		wp_delete_object_term_relationships( $post_id, $this->supertag_tx_key );
		if ( ! empty( $terms ) ) {
			$filtered_terms = array_filter($terms, function($term) {
				return term_exists($term, $this->supertag_tx_key);
			});
			wp_set_object_terms( $post_id, $filtered_terms, $this->supertag_tx_key );
		}
	}

	/**
	 * Return allowed super tags post types
	 * 
	 * @since    1.0.0
	 * 
	 * @return array
	 */
	public function allowed_super_tags_post_types() {
		return array('post');
	}


	/**
	 * Removes the default meta box from the post editing screen and adds our custom meta box.
	 * 
	 * @since    1.0.0
	 * @param string $object_type The object type (eg. the post type).
	 * @param mixed  $object      The object (eg. a WP_Post object).
	 */
	public function meta_boxes(string $object_type, $object): void {

		if (!is_a($object, 'WP_Post')) {
			return;
		}

		$post_type = $object_type;
		$post = $object;
		$taxos = get_post_taxonomies($post);
		$key = $this->supertag_tx_key;

		if (in_array($key, $taxos, true)) {

			$tax = get_taxonomy($key);

			# Remove default meta box from classic editor:
			if ($tax->hierarchical) {
				remove_meta_box("{$key}div", $post_type, 'side');
			} else {
				remove_meta_box("tagsdiv-{$key}", $post_type, 'side');
			}

			# Remove default meta box from block editor:
			wp_add_inline_script(
				'wp-edit-post',
				sprintf(
					'wp.data.dispatch( "core/edit-post" ).removeEditorPanel( "taxonomy-panel-%s" );',
					$key
				)
			);

			if (!current_user_can($tax->cap->assign_terms)) {
				return;
			}

			add_meta_box("{$key}div", $tax->labels->name, [$this, 'meta_box_check'], $post_type, 'side', 'default', ['tax' => $key]);

			//add_meta_box("{$key}div", $tax->labels->name, 'post_categories_meta_box', $post_type, 'side', 'default', ['tax' => $key]);
		}
	}

	/**
	 * Displays the 'checkbox' meta box on the post editing screen.
	 *
	 * @param WP_Post             $post     The post object.
	 * @param array<string,mixed> $meta_box The meta box arguments.
	 */
	public function meta_box_check(WP_Post $post, array $meta_box): void {
		$this->do_meta_box($post, $meta_box['args']['tax']);
	}

	/**
	 * Displays a meta box on the post editing screen.
	 *
	 * @param WP_Post $post      The post object.
	 * @param \Walker $walker    Optional. A term walker.
	 * @param bool    $show_none Optional. Whether to include a 'none' item in the term list. Default false.
	 * @param string  $type      Optional. The taxonomy list type (checklist or dropdown). Default 'checklist'.
	 */
	protected function do_meta_box(WP_Post $post, string $taxonomy = 'super_tag', \Walker $walker = null, bool $show_none = false, string $type = 'checklist'): void {
		$tax = get_taxonomy($taxonomy);
		$selected = wp_get_object_terms(
			$post->ID,
			$taxonomy,
			[ 'fields' => 'ids' ]
		);

		if ($show_none) {
			if (isset($tax->labels->no_item)) {
				$none = $tax->labels->no_item;
			} else {
				$none = esc_html__('Not specified', 'folio-super-agora');
			}
		} else {
			$none = '';
		}

		/**
		 * Execute code before the taxonomy meta box content outputs to the page.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Taxonomy $tax  The current taxonomy object.
		 * @param WP_Post     $post The current post object.
		 * @param string      $type The taxonomy list type ('checklist').
		 */
		do_action('folio-supertags/meta_box/before', $tax, $post, $type);
		?>
		<div id="taxonomy-<?php echo esc_attr($taxonomy); ?>" class="categorydiv">

			<input type="hidden" name="tax_input[<?php echo esc_attr($taxonomy); ?>][]" value="0" />

			<ul id="<?php echo esc_attr($taxonomy); ?>checklist" class="list:<?php echo esc_attr($taxonomy); ?> categorychecklist form-no-clear">

				<?php

				# Standard WP Walker_Category_Checklist does not cut it
				if (!$walker) {
					$walker = new Folio_Super_Check_Walker();
				}

				# Output the terms:
				$checklist = wp_terms_checklist(
					$post->ID,
					[
						'taxonomy'      => $taxonomy,
						'walker'        => $walker,
						'selected_cats' => $selected,
						'checked_ontop' => null,
						'echo' 			=> false
					]
				);

				if ( ! empty( $checklist ) ){
					echo $checklist;
				} else {
					echo '<li>' . esc_html_e('No terms found', 'folio-super-agora') . '</li>';
				}

				# Output the 'none' item:
				/*if ($show_none) {
					$output = '';
					$o = (object) [
						'term_id' => 0,
						'name'    => $none,
						'slug'    => 'none',
					];
					if (empty($selected)) {
						$_selected = [0];
					} else {
						$_selected = $selected;
					}
					$args = [
						'taxonomy'      => $taxonomy,
						'selected_cats' => $_selected,
						'disabled'      => false,
					];
					$walker->start_el($output, $o, 1, $args);
					$walker->end_el($output, $o, 1, $args);

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $output;
				}*/

				?>

			</ul>
			
		</div>
		<?php

		/**
		 * Execute code after the taxonomy meta box content outputs to the page.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Taxonomy $tax  The current taxonomy object.
		 * @param WP_Post     $post The current post object.
		 * @param string      $type The taxonomy list type ('checklist').
		 */
		do_action('folio-supertags/meta_box/after', $tax, $post, $type);
	}
}
