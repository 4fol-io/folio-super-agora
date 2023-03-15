<?php

/**
 * Folio SuperAgora ActiFolios Widget
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */

require_once ABSPATH . '/wp-content/plugins/uocapi/uocapi.php';

use Edu\Uoc\Te\Uocapi\Model\Vo\Classroom;

class Folio_Super_Agora_Widget_Activities extends WP_Widget {

	/**
	 * Sets up the widget
	 */
	public function __construct() {
		parent::__construct(
			'folio_super_agora_widget_activities',
			__( 'SuperAgora ActiFolios', 'folio-super-agora' ),
			array(
				'classname'   => 'Folio_Super_Agora_Widget_Activities',
				'description' => __( 'Widget SuperAgora ActiFolios', 'folio-super-agora' ),
			)
		);
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {

		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		$output 			= '';
		$title 				= apply_filters( 'widget_title', $instance['title'] );
		$show_number_posts 	= ( ! empty( $instance['show_number_posts'] ) ) ? absint( $instance['show_number_posts'] ) : 0;
		$number            	= ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 0;

		$total = 0;
		$subjects = array();
		$activities = array();
		$classrooms = array();
		$options = get_option( 'folio_superagora_settings' );
		$agoras = isset($options['agoras']) ? $options['agoras'] : [];
		$user_classrooms = is_user_logged_in() ?
			$this->get_user_classrooms( get_current_user_id()) : array();

		if ( count($agoras) > 0) {
			foreach ( $agoras as $agora ) {
				if ( isset( $agora['domainId'] ) && isset( $agora['parentId'] ) ) {
					$uclassroom = new Classroom();
					$uclassroom->setId( $agora['domainId'] );
					$uclassroom->setFatherId( $agora['parentId'] );
					$uclassroom->setInstitution( 1 );
					$classroom = uoc_create_site_get_classroom_by_id( $uclassroom );
					if( $classroom ) {
						$classroom->name = $this->get_classroom_title($classroom);
						$classroom->is_user_class = in_array($classroom->domainId, array_column($user_classrooms, 'domainId')) ? 1 : 0;
						$classroom->actiuocs = array();
						$classroom->count = 0;
						$classrooms[] = $classroom;
					}
				}
			}
		}else {
			echo __( 'No classrooms found', 'folio-super-agora' );
			return;
		}

		$actiuocs = get_terms( 'actiuoc', [ 'hide_empty' => false ] );

		if ( ! is_array( $actiuocs ) || is_wp_error( $actiuocs )  || count( $actiuocs ) === 0 ) {
			echo __( 'No actiuocs found', 'folio-super-agora' );
			return;
		}

		usort($actiuocs, function ($a, $b) {
			if ($a->parent == $b->parent) {
				return strnatcasecmp($a->name, $b->name);
			}
			return ($a->parent < $b->parent) ? -1 : 1;
		});

		$output .= $args['before_widget'];

		if ( ! empty ($title) ) {
			$output .= $args['before_title'] . $title . $args['after_title'];
		}

		if (  is_array( $actiuocs ) && ! is_wp_error( $actiuocs ) ) {

			foreach ( $actiuocs as $term ) {
				if ( $term->parent !== 0){ // is activity
				//if ( strpos( $term->slug, 'activity-' ) === 0 ) {
					$activities[] = $term;
				}else{ // is classroom
					$subjects[$term->term_id] = $term;
				}
			}

			$parent = 0;
			$index = 0;

			foreach ( $activities as $term ) {

				if ( $term->parent !== $parent) {
					$parent = $term->parent;
					$index = 0;
				}

				if ( ! $number || $index < $number ) {

					$posts_array = get_posts(
						array(
							'posts_per_page' => - 1,
							'post_status'    => array( 'publish', 'private' ),
							'tax_query'      => array(
								array(
									'taxonomy' => 'actiuoc',
									'field'    => 'term_id',
									'terms'    => $term->term_id,
								),
							),
						)
					);

					$term->count = count( $posts_array );

					// Show only if has posts
					if( $term->count > 0 ){
						if ( array_key_exists( $parent, $subjects) ) {
							$parentId = $subjects[$parent]->slug;
							foreach ( $classrooms as $classroom ) {
								if( $classroom->parentId === $parentId ){
									$classroom->actiuocs[] = $term;
									$classroom->count += $term->count;
								}
							}
						}
						$total += $term->count;
						$index ++;
					}
				}
			}

		}

		$current_id = get_queried_object_id();

		$summary_list = '<ul class="mb-2">';
		foreach ( $classrooms as $classroom ) {
			$summary_list .=	'<li>' . $classroom->name.'</li>';
		}
		$summary_list .= '</ul>';

		$summary_class = ! empty ($title) ? 'mx-1 mb-3 mt-n3' : 'mx-1 mb-3 mt-n1';

		$summary_label = sprintf( 
			__( '%1$s actifolios of %2$s classrooms', 'folio-super-agora' ), 
			'<strong>' . $total . '</strong>',  
			'<strong>'. count($classrooms).'</strong>',
		);

		$summary_modal = '
			<div class="modal modal-super-agora fade" id="modal-summary-super-actiuocs" tabindex="-1" aria-labelledby="modal-summary-actiuocs-title" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title ml-1" id="modal-summary-actiuocs-title">'
						. __('Classrooms:', 'folio-super-agora') .
						'</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="' . __('Close', 'folio-super-agora') . '">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body pt-3 pb-1">' . $summary_list . '</div>
				</div>
			</div>
			</div>';


		$output .= '<div class="super-agora-widget-sumary '. $summary_class .'">' . $summary_label . '<a href="#modal-summary-super-actiuocs" class="super-agora-widget-info ml-1" role="button" data-toggle="modal" data-target="#modal-summary-super-actiuocs"><span class="icon icon--info-full icon--small" aria-hidden="true"></span></a></div>';
		$output .= $summary_modal;

		if ( $total > 0 ){

			$output .= '<div class="super-agora-widget-blocks d-flex flex-column">';

			foreach ( $classrooms as $classroom ) {

				$class = $classroom->is_user_class ? 'block-featured' : '';
				$concat = $classroom->is_user_class ? ' <span class="font-weight-normal">(' . __('Classroom where I am', 'folio-super-agora') . ')</span>' : '';
				$icon = $classroom->is_user_class? '<span class="icon icon--asterisk icon--small mr-1" aria-hidden="true" aria-label=""></span>' : '';

				$output .= '<div class="block block-super-actiuoc ' . $class . '">';
				$output .= '<h5 class="h6 p-1 pt-2 mb-2">'. $icon . $classroom->name . $concat . '</h5>';
				$output .= '<ul class="list list--menu list--superagora row d-flex flex-wrap">';

				foreach ( $classroom->actiuocs as $term ) {

					$output .= '<li class="col-md-6 col-lg-4">';
					
					$concat = $show_number_posts ? ' (' . $term->count . ')' : '';
					$class = $term->term_id == $current_id ? 'active' : '';
		
					$output .= '<a href="' . get_term_link( $term ) . '" class="'. $class .'">' . $term->name . $concat . '</a>';
					$output .= '</li>';
		
				}

				$output .= '</ul></div>';
			}

			$output.= '</div>';

		} else {
			$output = '<div class="block block-super-actiuoc mb-2"><ul class="list list--menu list--actifolio row">';
		    $output .= '<li class="col-md-12">';
			    $output .= __( 'There are not actiFolio with post related', 'folio-super-agora' );
			$output.= '</li>';
			$output .= '</ul></div>';
		}

		$output .= $args['after_widget'];

		echo $output;
	}

	/**
	 * Get user classrooms
	 * @return array
	 */
	private function get_user_classrooms( $user_id ) {

		$cached = wp_cache_get( 'user_classrooms_'. $user_id, 'super-agora' );

		if ( false !== $cached ) {
			return $cached;
        }

		global $wpdb;

		$classrooms_table      = uoc_create_site_get_classrooms_table();
		$classrooms_user_table = uoc_create_site_get_uoc_classrooms_user_table();

		$classrooms = $wpdb->get_results( $wpdb->prepare(
			"
			SELECT c.domainId, c.parentId, c.code, u.is_teacher FROM $classrooms_table AS c 
			INNER JOIN $classrooms_user_table AS u 
			ON c.domainId = u.classroomId AND u.userId = %d
			",
			array( $user_id )
		) );

		wp_cache_set( 'user_classrooms_'. $user_id, $classrooms, 'super-agora' );
	
		return $classrooms;
	}


	/**
	 * Get classroom title
	 */
	private function get_classroom_title($classroom) {

		$name = __( 'Subject', 'folio-super-agora' );
		$number = '';

		if ( $classroom ) {
			switch_to_blog($classroom->blog_id);
			$name = get_bloginfo( 'name');
			restore_current_blog();
			$code      = $classroom->code;
			$number = intval( preg_replace( "/.*\_(\d+)(\.[\w\d]+)?$/", "$1", $code ) );
		}

		$name = $name . ' - '. sprintf( __( 'Classroom %s', 'folio-super-agora' ), $number );
		
		return $name;
	}

	/**
	 * Handles updating the settings for the current widget instance
	 *
	 * @param array $new_instance New settings for this instance
	 * @param array $old_instance Old settings for this instance
	 *
	 * @return array Updated settings to save
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                      = $old_instance;
		$instance['title']			   = sanitize_text_field( $new_instance['title'] );
		$instance['number']            = (int) $new_instance['number'];
		$instance['show_number_posts'] = (int) $new_instance['show_number_posts'];

		return $instance;
	}

	/**
	 * Outputs the settings form for the widget
	 *
	 * @param array $instance Current settings
	 */
	public function form( $instance ) {
		$title             = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number            = isset( $instance['number'] ) ? absint( $instance['number'] ) : 20;
		$show_number_posts = isset( $instance['show_number_posts'] ) ? absint( $instance['show_number_posts'] ) : 1;
		?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>">
				<?php _e( 'Title:', 'folio-super-agora' ); ?>
			</label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
                   name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>"/>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'number' ); ?>">
				<?php _e( 'Max number of ActiFolio to show per Classroom:', 'folio-super-agora' ); ?>
			</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>"
                   name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="0"
                   value="<?php echo $number; ?>" size="3"/>
			<em><?php _e( '(0 or empty for unlimited)', 'folio-super-agora' ); ?></em>
        </p>
        <p>
			<input class="checkbox" id="<?php echo $this->get_field_id( 'show_number_posts' ); ?>"
                   name="<?php echo $this->get_field_name( 'show_number_posts' ); ?>" type="checkbox" step="1"
                   value="1" <?php echo $show_number_posts === 1 ? 'checked' : '' ?>/>
            <label for="<?php echo $this->get_field_id( 'show_number_posts' ); ?>">
				<?php _e( 'Show number of posts', 'folio-super-agora' ); ?>
			</label>
        </p>
		<?php
	}

}
