<?php

/**
 * Folio SuperAgora Participants Widget
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */


require_once ABSPATH . '/wp-content/plugins/uocapi/uocapi.php';

use Edu\Uoc\Te\Uocapi\Model\Vo\Classroom;

class Folio_Super_Agora_Widget_Users extends WP_Widget {

	/**
	 * Sets up the widget
	 */
	public function __construct() {
		parent::__construct(
			'folio_super_agora_widget_users',
			__('SuperAgora Participants', 'folio-super-agora'),
			array(
				'classname'   => 'Folio_Super_Agora_Widget_Users',
				'description' => __('Widget SuperAgora Participants', 'folio-super-agora'),
			)
		);
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget($args, $instance) {

		$output = $args['before_widget'];

		if ( is_user_logged_in() ) {

			if (empty($args['widget_id'])) {
				$args['widget_id'] = $this->id;
			}

			$options = get_option( 'folio_superagora_settings' );
			$agoras = isset($options['agoras']) ? $options['agoras'] : [];
			$classrooms = array();
			$user_classrooms = $this->get_user_classrooms( get_current_user_id());

			if ( count($agoras) > 0 ) {
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
							$classroom->users = array();
							$classroom->count = 0;
							$classrooms[] = $classroom;
						}
					}
				}
			}else{
				$output .= __( 'No classrooms found', 'folio-super-agora' );
				$output .= $args['before_widget'];
				echo $output;
				return false;
			}

			$title = apply_filters('widget_title', $instance['title']);

			$args_query  = array(
				'role'   => 'subscriber',
				'fields' => 'all_with_meta',
			);
			
			$users = get_users($args_query);

			usort($users, function ($a, $b) {
				if ($a->last_name == $b->last_name) {
					return strcmp($a->first_name, $b->first_name);
				}
				return strcmp($a->last_name, $b->last_name);
			});

			if (is_array ($users) ) {
				foreach ($users as $user) {
                    $user_classrooms = $this->get_user_classrooms( $user->ID );
					foreach ( $classrooms as $classroom ) {
						$index = array_search($classroom->domainId, array_column($user_classrooms, 'domainId'));
						if ( $index !== false ) {
							$user_class = $user_classrooms[$index];
							if ( $user_class && $user_class->is_teacher != 1 ) {
								$user->data->count = count_user_posts(
									$user->ID,
									uoc_create_site_allowed_post_type_to_change_visibilty()
								);
								$classroom->users[] = $user;
							}
						}
					}
                }
            }

			$output .= $args['before_widget'];

			if (!empty($title)) {
				$output .= '<div class="visually-hidden">' . $args['before_title'] . $title . $args['after_title'] . '</div>';
			}

			$output .= '<div class="block block--mega block--participants" data-orderby="surname" data-order="ASC"><div class="super-agora-widget-blocks d-flex flex-column"><div class="block-content px-2">';

			$output .= $this->add_html_filter($classrooms, count($users), $args['widget_id']);

			foreach ( $classrooms as $classroom ) {
				$output .= $this->render_classroom($classroom);
			}

			$output .= '</div></div></div>';

		} else {
			$output = __('Log in to see classroom mates', 'portafolis-uoc-access');
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
	 * Render users
	 */
	private function render_classroom( $classroom ) {
		$output = '';

		$class = $classroom->is_user_class ? 'block-featured' : '';
		$concat = $classroom->is_user_class ? ' <span class="font-weight-normal">(' . __('Classroom where I am', 'folio-super-agora') . ')</span>' : '';
		$icon = $classroom->is_user_class? '<span class="icon icon--asterisk icon--small mr-1" aria-hidden="true" aria-label=""></span>' : '';

		$output .= '<div class="block block-super-participats ' . $class . '">';
		$output .= '<h5 class="h6 p-1 pt-2 mb-2">'. $icon . $classroom->name . $concat . '</h5>';


		if ( $classroom->users && is_array($classroom->users)) {
			$output .= '<ul class="list list--participants row d-block d-md-flex flex-wrap">';

			foreach ((array) $classroom->users as $user) {
				$visit_with   = sprintf(__("Visit %s's Folio", "portafolis-uoc-access"), $user->display_name);
				$view_profile = sprintf(__("Entries by %s published in the Agora", "portafolis-uoc-access"), $user->display_name);

				$parts    = explode("@", $user->user_email);
				$username = $parts[0];
				$photo    = 'https://campus.uoc.edu/UOC/mc-icons/fotos/' . $username . '.jpg';

				$student_url        = str_replace(
					'https://',
					'https://' . str_replace('_', '-', $user->user_login) . '.',
					network_site_url('')
				);

				$concat = '';
				if ($user->count > 0) {
					$concat = ' (' . $user->count . ')';
				}

				$first_name = get_user_meta($user->ID, 'first_name', true);
				$last_name = get_user_meta($user->ID, 'last_name', true);
				if (empty($first_name)) {
					$first_name = $user->display_name;
				}
				if (empty($last_name)) {
					$last_name = $user->display_name;
				}

				$profile_url = ' <a href="' . get_author_posts_url($user->ID) . '" class="user_folio" title="' . $view_profile . '">' . $user->display_name . $concat . '</a>';
				$output .= '<li class="col-md-4 col-lg-3" role="presentation"  data-name="' . $first_name . '" data-surname="' . $last_name . '">
                    <div class="media media--profile">
                        <div class="media__left">
                            <a href="' . $student_url . '" target="_blank"  title="' . $visit_with . '"
							aria-label="' . $visit_with . '" class="media__thumb photo" style="background-image:url(\'' . $photo . '\')"><span class="sr-only">' . __('(opens in new window)', 'folio-super-agora') . '</span></a>
                        </div>
                        <div class="media__body details d-flex align-items-center">
                            <div class="user_folio_widget">
                               ' . $profile_url . '
                            </div>
                        </div>
                    </div>
                </li>';
			}
			$output .= '</ul>';
		} else {
			$output .= '<p class="p-1 mb-3">' . __('There aren\'t classroom mates', 'folio-super-agora') . '</p>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Render order filters
	 */
	private function add_html_filter($classrooms, $total, $wid) {

		$summary_list = '<ul class="mb-2">';
		foreach ( $classrooms as $classroom ) {
			$summary_list .=	'<li>' . $classroom->name.'</li>';
		}
		$summary_list .= '</ul>';

		$summary_label = sprintf( 
			__( '%1$s participants of %2$s classrooms', 'folio-super-agora' ), 
			'<strong>' . $total . '</strong>',  
			'<strong>'. count($classrooms).'</strong>',
		);

		$summary_modal = '
			<div class="modal modal-super-agora fade" id="modal-summary-super-participants" tabindex="-1" aria-labelledby="modal-summary-participants-title" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title ml-1" id="modal-summary-participants-title">'
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

		return '
		<div class="block-filters">
			<fieldset aria-labelledby="participants_filters_legend_' . $wid . '" class="row">

                <div class="col-md-4 col-xl-6 sr-only-sm">
                    <legend class="super-agora-widget-summary py-3 pl-1 pl-md-3" id="participants_filters_legend_' . $wid . '">' . $summary_label .'
					<a href="#modal-summary-super-participants" class="super-agora-widget-info ml-1" role="button" data-toggle="modal" data-target="#modal-summary-super-participants"><span class="icon icon--info-full icon--small" aria-hidden="true"></span></a>
					</legend>
                </div>

                <div class="col-md-4 col-xl-3">
                    <div class="form-inline-radio align-right py-3 pl-md-2">
                        <label for="participants_orderby_name_' . $wid . '">
                            <input class="participants-orderby" id="participants_orderby_name_' . $wid . '" name="participants_orderby_' . $wid . '" type="radio" value="name" data-label="Nombre">
                            <span aria-hidden="true" class="icon icon--radio-button-off icon--small"></span> ' . __('Name', 'portafolis-uoc-access' ) . '
                        </label>

                        <label for="participants_orderby_surname_' . $wid . '">
                            <input class="participants-orderby" id="participants_orderby_surname_' . $wid . '" name="participants_orderby_' . $wid . '" type="radio" value="surname" data-label="Apellidos" checked>
                            <span aria-hidden="true" class="icon icon--radio-button-off icon--small"></span> ' . __('Surnames','portafolis-uoc-access') . '
                        </label>
                    </div>
                    <div class="block--participants-sort visible-xs visible-sm">
                        <a href="#" role="button" class="btn btn--secondary px-2 btn-participants-sort">
                            <span class="icon icon-svg icon-svg--order" aria-hidden="true"></span>
                            <span class="icon-alt" aria-hidden="true">' . 
							__('Order:', 'portafolis-uoc-access') . 
							' <span class="lbl">' . __('Ascending','portafolis-uoc-access') . 
							'</span></span>
                        </a>
                    </div>
                </div>

                <div class="col-md-4 col-xl-3 hidden-xs hidden-sm">
                    <div class="form-inline-radio align-right py-3 pr-2">
                        <label for="participants_order_asc_' . $wid . '">
                            <input class="participants-order" id="participants_order_asc_' . $wid . '" name="participants_order_' . $wid . '" type="radio" value="ASC" data-label="Ascendente" checked>
                            <span aria-hidden="true" class="icon icon--radio-button-off icon--small"></span> ' . __('Ascending','portafolis-uoc-access') . '
                        </label>

                        <label for="participants_order_desc_' . $wid . '">
                            <input class="participants-order" id="participants_order_desc_' . $wid . '" name="participants_order_' . $wid . '" type="radio" value="DESC" data-label="Descendente">
                            <span aria-hidden="true" class="icon icon--radio-button-off icon--small"></span> ' . __('Descending','portafolis-uoc-access') . '
                        </label>
                    </div>
                </div>

            </fieldset>

			' . $summary_modal . '
                
        </div>';
	}

	/**
	 * Handles updating the settings for the widget instance.
	 *
	 * @param array $new_instance New settings for this instance
	 * @param array $old_instance Old settings for this instance
	 *
	 * @return array Updated settings to save.
	 */
	public function update($new_instance, $old_instance){
		$instance          = $old_instance;
		$instance['title'] = sanitize_text_field($new_instance['title']);
		return $instance;
	}

	/**
	 * Outputs the settings form for the widget
	 *
	 * @param array $instance Current settings
	 */
	public function form($instance){
		$title = isset($instance['title']) ? esc_attr($instance['title']) : ''; ?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">
				<?php _e('Title:','folio-super-agora'); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
		<?php
	}

}
