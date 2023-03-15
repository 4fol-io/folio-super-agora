<?php

/**
 * Folio Super Agora Settings Management
 * 
 * @since      1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */

require_once ABSPATH . '/wp-content/plugins/uocapi/uocapi.php';

use Edu\Uoc\Te\Uocapi\Model\Vo\Classroom;

class Folio_Super_Agora_Settings extends Folio_Super_Agora_Base {

	/**
	 * Initialize the class
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		// Admin page
		add_action( 'admin_menu', [$this, 'add_admin_settings']);

		// Register Settings
		add_action( 'admin_init', [$this, 'register_settings']);

	}

	/**
	 * Creates a new item in the admin menu.
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 * @uses add_menu_page()
	 */
	public function add_admin_settings() {
		$page = add_options_page(
			__('Folio SuperAgora', 'folio-super-agora'),
			__('Folio SuperAgora', 'folio-super-agora'),
			//'manage_options',
			'manage_network_options',
			$this->settings_slug,
			[$this, 'settings_page']
		);
	}

	/**
	 * Register plugin settings
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {

		register_setting(
			$this->settings_slug . '-page',
			$this->settings_option,
			[ $this, 'validate_settings' ]
		);

		add_settings_section(
			$this->settings_slug .'-default',
			__('SuperAgora Settings', 'folio-super-agora'),
			[$this, 'settings_section_main'],
			$this->settings_slug . '-page'
		);
 
		add_settings_field(	
			'enabled',					
			__('Is SuperAgora?', 'folio-super-agora'),			
			[$this, 'setting_enabled_field'],
			$this->settings_slug . '-page',
			$this->settings_slug . '-default',
			array(__('Enable this site as SuperAgora', 'folio-super-agora'))
		);

		add_settings_field(	
			'redirect_front',					
			__('Redirect Agoras Frontend?', 'folio-super-agora'),			
			[$this, 'setting_redirect_front_field'],
			$this->settings_slug . '-page',
			$this->settings_slug . '-default',
			array(__('Redirect Agoras Frontend to SuperAgora Frontend', 'folio-super-agora'))
		);

		add_settings_field(	
			'redirect_back',					
			__('Redirect Agoras Backend?', 'folio-super-agora'),			
			[$this, 'setting_redirect_back_field'],
			$this->settings_slug . '-page',
			$this->settings_slug . '-default',
			array(__('Redirect Agoras Backend to SuperAgora Backend', 'folio-super-agora'))
		);


		add_settings_field(
			'classrooms',
			__('SuperAgora Classrooms', 'folio-super-agora'),
			[$this, 'setting_classrooms_field'],
			$this->settings_slug . '-page',
			$this->settings_slug . '-default',
			array(__('Separate classroom codes with commas', 'folio-super-agora'))
		);

	}

	/**
	 * Adds Super Agora settings page
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 * @uses settings_fields()
	 * @uses do_settings_sections()
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_attr( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( $this->settings_slug . '-page' );
				do_settings_sections( $this->settings_slug . '-page' );
				submit_button();
				?>
			</form>
			<?php $this->sync_form() ?>
		</div>

		<?php
	}



	/**
	 * Super Agora main settings intro text
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 */
	public function settings_section_main() {
		echo '<p>' . esc_html__('Here you can set the SuperAgora settings.', 'folio-super-agora') . '</p>';
	}

	/**
	 * Super Agora sync settings intro text
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 */
	public function sync_form() {
		?>
		<hr>
		<h2><?php esc_html_e( 'SuperAgora Sync', 'folio-super-agora' ); ?></h2>
		<p>
			<?php 
			if ( FOLIO_SUPER_AGORA_ENABLE_SUPERTAGS ) {
				esc_html_e( 'Here you can sync the SuperAgora ActiUOCs, SuperTags and Users.', 'folio-super-agora' ); 
			} else{
				esc_html_e( 'Here you can sync the SuperAgora ActiUOCs and Users.', 'folio-super-agora' ); 
			}
			?>
		</p>

		<?php 
		if ( ! empty( $_POST['action'] ) && $_POST['action'] === 'sync' ) {
			
			if ( wp_verify_nonce( $_POST['_wpnonce'], 'folio_superagora_sync' ) ) {
				
				$this->superagora_sync();

			} else {
				add_settings_error(
					$this->settings_slug . '-sync-feedback',
					'security-check',
					__('You do not have permission to perform this action', 'folio-super-agora'),
					'error'
				);
			}
		}
		?>
		<form method="POST" action="">
			<?php settings_errors( $this->settings_slug . '-sync-feedback', ); ?>
			<p class="submit">
				<?php wp_nonce_field( 'folio_superagora_sync' ); ?>
				<input type='hidden' name='action' value='sync' />
				<input type="submit" name="submit" id="folio-superagora-sync-submit" class="button button-primary" value="<?php esc_attr_e( 'Sync up', 'folio-super-agora' ); ?>">
			</p>
		</form>
		<?php
	}

	/**
	 * Enable SuperAgora setting field
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 */
	public function setting_enabled_field($args) {

		$options = get_option( $this->settings_option );
		$enabled = isset($options['enabled']) ? intval($options['enabled']) : 0;

		$html = '<input type="checkbox" id="folio_superagora_enabled" name="'. $this->settings_option .'[enabled]" value="1" ' . checked(1, $enabled, false) . '/>';
		
		$html .= '<label for="folio_superagora_enabled"> '  . $args[0] . '</label>';
		
		echo $html;
	}

	/**
	 * Redirect Frontend SuperAgora setting field
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 */
	public function setting_redirect_front_field($args) {

		$options = get_option( $this->settings_option );
		$redirect_front = isset($options['redirect_front']) ? intval($options['redirect_front']) : 0;

		$html = '<input type="checkbox" id="folio_superagora_redirect_front" name="'. $this->settings_option .'[redirect_front]" value="1" ' . checked(1, $redirect_front, false) . '/>';
		
		$html .= '<label for="folio_superagora_redirect_front"> '  . $args[0] . '</label>';
		
		echo $html;
	}


	/**
	 * Redirect Backend SuperAgora setting field
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 */
	public function setting_redirect_back_field($args) {

		$options = get_option( $this->settings_option );
		$redirect_back = isset($options['redirect_back']) ? intval($options['redirect_back']) : 0;

		$html = '<input type="checkbox" id="folio_superagora_redirect_back" name="'. $this->settings_option .'[redirect_back]" value="1" ' . checked(1, $redirect_back, false) . '/>';
		
		$html .= '<label for="folio_superagora_redirect_back"> '  . $args[0] . '</label>';
		
		echo $html;
	}


	/**
	 * SuperAgora classrooms setting field
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 */
	public function setting_classrooms_field($args) {

		$options = get_option( $this->settings_option );
		$codes = isset($options['codes']) ? $options['codes'] : [];
		$codes_str = implode(",", $codes);
		
		$html = '<input type="text" id="folio_superagora_classrooms" name="'. $this->settings_option .'[classrooms]" value="' . $codes_str . '" class="regular-text">';
		$html .= '<p class="description">'  . $args[0] . '</p>';

		echo $html;

	}

	/**
	 * Settings validation
	 * TODO: Validate all settings, include enable and perform agoras blogs actions
	 *
	 * @since 1.0.0
	 * 
	 * @return array
	 */
	public function validate_settings( $input ) {

		$options = get_option( $this->settings_option );
		$old_enabled = isset($options['enabled']) ? intval($options['enabled']) : 0;
		$old_agoras = isset($options['agoras']) ? $options['agoras'] : [];
		$codes_str = isset( $input['classrooms'] ) ? trim(sanitize_text_field($input['classrooms'])) : '';

		$output['enabled'] = isset( $input['enabled'] ) && $input['enabled'] == true ? 1 : 0;
		$output['redirect_front'] = isset( $input['redirect_front'] ) && $input['redirect_front'] == true ? 1 : 0;
		$output['redirect_back'] = isset( $input['redirect_back'] ) && $input['redirect_back'] == true ? 1 : 0;

		if($codes_str){
			$output['codes'] = explode(',',$codes_str);
			$output['agoras'] = $this->get_blogs_by_classroom_codes($output['codes']);
		}else{
			$output['codes'] = [];
			$output['agoras'] = [];
		}

		$added = [];
		$removed = [];

		foreach ( $output['agoras'] as $agora ) {
			if( array_search($agora['blog_id'], array_column($old_agoras, 'blog_id')) === false) {
				$added[] = $agora;
			}
		}

		foreach ( $old_agoras as $agora ) {
			if( array_search($agora['blog_id'], array_column($output['agoras'], 'blog_id')) === false) {
				$removed[]= $agora;
			}
		}

		if($output['enabled']) {

			if ($old_enabled){ // enable added agoras and disable removed agoras
				
				if( count($removed) ){
					$diff = array_udiff($output['agoras'], $removed, [$this, 'udiff_compare_agoras']);
					$this->disable_agoras($removed, $diff);
				}
	
				if( count($added) ){
					$this->enable_agoras($added);
				}

			} else { // enable all agoras

				$this->add_superagora_classroom();
				$this->enable_agoras($output['agoras']);

			}

		} elseif ( $old_enabled ){ // remove all agoras

			$this->remove_superagora_classroom();
			$this->disable_agoras($output['agoras']);

		}

		$this->superagora_sync_actiuocs($output);

		return $output;
	}


	/**
	 * Adds SuperAgora Classroom and Subject
	 */
	private function add_superagora_classroom(){

		// Create "dummy subject" for SuperAgora if not exist
		uoc_create_site_add_subject_if_not_exist_data(
			'superagora_' . get_current_blog_id() . '_subject',
			'superagora',
			get_bloginfo( 'name' ) . ' (subject)'
		);

		$classroom = new Classroom();
		$classroom->setId( 'superagora_' . get_current_blog_id() );
		$classroom->setFatherId( 'superagora_' . get_current_blog_id() . '_subject' );
		$classroom->setCode( 'superagora' );
		$classroom->setTitle( get_bloginfo( 'name' ) );
		$classroom->setInstitution( 1 );

		// Create "dummy clsasroom" for SuperAgora if not exist
		uoc_create_site_add_classroom_if_not_exist_data( $classroom );

	}

	/**
	 * Remove SuperAgora classroom
	 */
	private function remove_superagora_classroom(){
		global $wpdb;

		$classroom_table = uoc_create_site_get_classrooms_table();

		$result = $wpdb->query( $wpdb->prepare(
			"DELETE FROM $classroom_table WHERE domainId IN ( %s, %s )",
			array(
				'superagora_' . get_current_blog_id(),
				'superagora_' . get_current_blog_id() . '_subject'
			)
		) );

		return $result;
	
	}

	/**
	 * Enable agoras 
	 * 
	 * @param array  $agoras Passed by reference
	 * @return void
	 */
	private function enable_agoras(&$agoras){

		$users = $this->get_agoras_classroom_users($agoras);
		$this->add_users_to_superagora_classroom($users);
		$this->add_users_to_superagora_site($users);

		$blog_id = get_current_blog_id();
		foreach ( $agoras as $agora ) {
			if ( isset($agora['blog_id']) ){
				switch_to_blog( $agora['blog_id'] );
				update_option($this->superagora_option, $blog_id);
				restore_current_blog();
			}
		}
	}


	/**
	 * Disable agoras
	 * 
	 * @param array  $agoras Passed by reference
	 * @return void
	 */
	private function disable_agoras(&$agoras, $diff = false){

		$users = $this->get_agoras_classroom_users($agoras);

		if ($diff && is_array($diff) && count($diff) > 0) {
			$nodelete = $this->get_agoras_classroom_users($diff);
			$udiff = array_udiff($users, $nodelete, [$this, 'udiff_compare_users']);
			$this->remove_users_from_superagora_site($udiff);
			$this->remove_users_from_superagora_classroom($udiff);
		}else{
			$this->remove_users_from_superagora_site($users);
			$this->remove_users_from_superagora_classroom($users);
		}

		foreach ( $agoras as $agora ) {
			if ( isset($agora['blog_id']) ){
				switch_to_blog( $agora['blog_id'] );
				delete_option($this->superagora_option);
				restore_current_blog();
			}
		}
	}


	/**
	 * Get blogs info by classroom codes
	 */
	private function get_blogs_by_classroom_codes(&$codes){
		global $wpdb;

		$table            = uoc_create_site_get_classrooms_table();
		$classrooms_table = uoc_create_site_get_classroom_blog_table();

		$args = [1, 1];
		$where = '';

		if (is_array($codes) && count($codes)) {
            $where .= ' AND (';
			foreach ( $codes as $key => $code ) {
				$code = trim($code);
				if ($code !== ''){
					$where .= $key > 0 ? ' OR t.code LIKE %s' : 't.code LIKE %s';
					$args[] = '%' . $wpdb->esc_like( trim($code)) . '%';
				}
			}
			$where .= ')';
        }else{
			return [];
		}
		
		if (count($args) <= 2){
			return [];
		}

		$classrooms = $wpdb->get_results( $wpdb->prepare(
			"
			SELECT DISTINCT t.domainId, t.parentId, ct.blog_id FROM $table t
			INNER JOIN $classrooms_table ct ON ct.domainId = t.domainId
			WHERE %d=%d
			" . $where,
			$args
		), ARRAY_A);

		return $classrooms;
	}


	/**
	 * Insert/update actiuoc taxonomy subject term
	 */
	private function update_subject_term( $subject_id, $subject) {

		$term = get_term_by( 'slug', $subject_id, 'actiuoc' );

		if ( ! $term ) {
			$term = wp_insert_term ( 
				$subject->name . ' ' . $subject->code,
				'actiuoc',
				array(
					'description' => $subject->name,
					'slug'        => $subject_id,
					'parent'      => 0
				)
			);
		}

		$term = get_term_by( 'slug', $subject_id, 'actiuoc' );

		return $term;
	}


	/**
	 * Sync SuperAgora
	 */
	private function superagora_sync(){

		$this->superagora_sync_actiuocs();

		if ( FOLIO_SUPER_AGORA_ENABLE_SUPERTAGS ) {
			$this->superagora_sync_supertags();
		}
		
		$this->superagora_sync_users();

		add_settings_error(
			$this->settings_slug . '-sync-feedback',
			'sync-success',
			__('Synchronization finished successfully', 'folio-super-agora'),
			'success'
		);


	}

		
	/**
	 * Update actiuocs taxonomy activity terms
	 */
	private function superagora_sync_actiuocs( $options = false ){

		if ( ! $options ) {
			$options = get_option( $this->settings_option );
		}

		//print_r($options);

		$agoras = isset($options['agoras']) ? $options['agoras'] : [];

		foreach ( $agoras as $agora ) {
			if ( isset( $agora['domainId'] ) ) {
				$subject = uoc_create_site_get_subject_from_db( $agora['domainId'] );
				if ( $subject && $subject->parentId > 0 ) {
					$term = $this->update_subject_term($subject->parentId, $subject);
					if ( $term  && ! is_wp_error( $term ) ) {
						$classroom = new Classroom();
						$classroom->setId( $subject->domainId );
						$classroom->setFatherId( $subject->parentId );
						$classroom->setInstitution( 1 );
						$activities = uoc_create_site_get_activities_from_db( $classroom );
						if ( is_array($activities) && count($activities) > 0 ) {
							uoc_create_site_update_activities_from_db($activities, $classroom, $term->term_id);
						}
					}
				}
			}
        }

	}


	/**
	 * Sync agoras users in SuperAgora
	 */
	private function superagora_sync_users(){

		$options = get_option( $this->settings_option );
		$enabled = isset($options['enabled']) ? intval($options['enabled']) : 0;
		$users = $this->get_agora_classroom_users( $this->get_super_classroom_id( get_current_blog_id() ) );

		if( $enabled ){

			$this->add_users_to_superagora_classroom($users);
			$this->add_users_to_superagora_site($users);

		} else {

			$this->remove_users_from_superagora_site($users);
			$this->remove_users_from_superagora_classroom($users);

		}

	}

	/**
	 * Sync SuperTags
	 */
	private function superagora_sync_supertags(){

		$terms = get_terms( $this->supertag_tx_key, [ 'hide_empty' => false ] );

		if ($terms && ! is_wp_error( $terms ) ) {

			$super_settings =  $this->is_super();
			$is_standalone = ucs_is_classroom_blog() && ! $this->belongs_to_super();
			$classroom = false;
			$agoras = false;

			if ( $super_settings ) { // is a SuperAgora
				$agoras = isset($super_settings['agoras']) ? $super_settings['agoras'] : [];
				$classroom = $this->get_super_classroom_id( get_current_blog_id() );
			} else if ( $is_standalone ) { // Is a Standalone Classroom
				$classroom = $this->get_classroom_id_from_blog_id( get_current_blog_id() );
			}


			foreach ( $terms as $term ) {

				// Is SuperAgora propage to Agoras
				if ( $agoras ) {
					foreach ( $agoras as $agora ) {
						if ( isset($agora['blog_id']) ){
							$this->update_supertag_term($agora['blog_id'], $term);
						}
					}
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

	}


	/**
	 * Add users to SuperAgora Classroom
	 */
	private function add_users_to_superagora_classroom(&$users){

		global $wpdb;

		$blog_id = get_current_blog_id();
		$classrooms_user_table = uoc_create_site_get_uoc_classrooms_user_table();

		if ( is_array($users) ) {

			foreach ( $users as $user ){
				$wpdb->query( $wpdb->prepare(
					"
					INSERT INTO $classrooms_user_table
					( userId, classroomId, color, is_teacher, created, updated, institution ) 
					VALUES ( %d, %s, %s, %d, now(), now(), %d ) 
					ON DUPLICATE KEY UPDATE color = %s, is_teacher = %s
					",
					array(
						$user['userId'],
						'superagora_' . $blog_id,
						$user['color'],
						$user['is_teacher'],
						$user['institution'],
						$user['color'],
						$user['is_teacher'],
					)
				) );
			}

		}

	}
	

	/**
	 * Remove users to SuperAgora Classroom
	 */
	private function remove_users_from_superagora_classroom(&$users){
		global $wpdb;

		$blog_id = get_current_blog_id();
		$classrooms_user_table = uoc_create_site_get_uoc_classrooms_user_table();

		if (is_array($users) && count($users)) {

			$in = '';
			$args = [ 'superagora_' . $blog_id ];

			foreach ( $users as $key => $user ) {
				if ( isset($user['userId']) ) {
					$in .= $key > 0 ? ',%d' : '%d';
					$args[] = $user['userId'];
				}
			}

			$wpdb->query( $wpdb->prepare(
				"DELETE FROM $classrooms_user_table WHERE classroomId = %s AND userId IN ( $in )",
				$args
			) );
		
		}
	}

	/**
	 * Add SuperAgora classroom users to site
	 */
	private function add_users_to_superagora_site($users){
		if( is_array($users) && !empty($users)) {
			foreach ( $users as $user ) {
				$this->add_user_to_superagora_site($user['userId'], $user['is_teacher']);
			}
		}
	}

	/**
	 * Add SuperAgora classroom user to site
	 */
	private function add_user_to_superagora_site( $user_id = 0, $is_teacher = false ) {
		if ( $user_id && ! is_user_member_of_blog( $user_id ) ) {
			$role = $is_teacher ? 'editor' : 'subscriber';
			add_user_to_blog( get_current_blog_id(), $user_id, $role );
		}
	}


	/**
	 * Remove users from SuperAgora site
	 */
	private function remove_users_from_superagora_site($users){
		if( is_array($users) && !empty($users)) {
			foreach ( $users as $user ) {
				$this->remove_user_from_superagora_site($user['userId']);
			}
		}
	}

	/**
	 * Remove user from SuperAgora site
	 */
	private function remove_user_from_superagora_site( $user_id = 0 ) {
		if ( $user_id && is_user_member_of_blog( $user_id ) && ! is_super_admin($user_id) ) {
			remove_user_from_blog( $user_id, get_current_blog_id() );
		}
	}


	/**
	 * Aux compare agores bidirectional array diff
	 */
	private function udiff_compare_agoras($a, $b){
		return $a['blog_id'] - $b['blog_id'];
	}

	/**
	 * Aux compare users bidimensional array diff
	 */
	private function udiff_compare_users($a, $b){
		return $a['userId'] - $b['userId'];
	}


}
