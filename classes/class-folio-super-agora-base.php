<?php

/**
 * Folio SuperAgora Base Class
 * 
 * @since      1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */

class Folio_Super_Agora_Base {

	/**
	 * SuperAgora DB Version
	 *
	 * @var string
	 */
	protected $db_version = '1.0.0';

	/**
	 * SuperAgora settings base slug for submenu URL in the settings page and to verify variables.
	 *
	 * @var string
	 */
	protected $settings_slug = 'folio-superagora-settings';

	/**
	 * SuperAgora site Settings Option Key
	 *
	 * @var string
	 */
	protected $settings_option = 'folio_superagora_settings';

	/**
	 * Agoras that belongs to SuperAgora Option Key
	 *
	 * @var string
	 */
	protected $superagora_option = 'folio_superagora';

	/**
	 * Agoras that belongs to SuperAgora Option Key
	 *
	 * @var string
	 */
	protected $supertag_tx_key = 'super_tag';

	/**
	 * SuperAgora DB Version Option Key
	 *
	 * @var string
	 */
	protected $db_version_option = 'folio_superagora_db_version';

	/**
	 * SuperAgora Posts Table Key
	 */
	protected $super_posts_table_key = 'uoc_superagora_posts';
	
	/**
	 * Get SuperAgora Posts Table
	 */
	protected function get_super_post_table () {
		global $wpdb;
	
		return $wpdb->base_prefix . $this->super_posts_table_key;
	}
	
	/**
	 * Get SuperAgora Classroom Id from blog_id
	 */
	protected function get_super_classroom_id ( $blog_id ) {
		return 'superagora_' . $blog_id;
	}

	/**
	 * Get SuperAgora Subject Id from blog_id
	 */
	protected function get_super_subject_id ( $blog_id ) {
		return 'superagora_' . $blog_id . '_subject';
	}

	/**
	 * Check if a site is superagora
	 * 
	 * @param int $blog_id Blog ID (optional)
	 * @return array SuperAgora settings
	 */
	protected function is_super ( $blog_id = false ) {
		if ( ! $blog_id ) $blog_id = get_current_blog_id();
		$settings = get_blog_option( $blog_id, $this->settings_option, false );
		return $settings;
	}

	/**
	 * Check if a site (Agora) belongs to a SuperAgora
	 *
	 * @param int $blog_id Blog ID (optional)
	 * @return int SuperAgora Blog ID
	 */
	protected function belongs_to_super ( $blog_id = false ) {
		if ( ! $blog_id ) $blog_id = get_current_blog_id();
		$option = get_blog_option( $blog_id, $this->superagora_option, 0 );
		return $option;
	}

	/**
	 * Debug log aux function
	 */
	protected function write_log ( $log )  {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}
	}


	/**
	 * Get agoras classroom users
	 * 
	 * @param array $classrooms
	 * @return array
	 */
	protected function get_agoras_classroom_users($classrooms){
		global $wpdb;

		$users = [];
		$classrooms_user_table = uoc_create_site_get_uoc_classrooms_user_table();

		if ( is_array($classrooms) && count($classrooms) ) {

			$in = '';
			$args = [];

			foreach ( $classrooms as $key => $classroom ) {
				if ( isset($classroom['domainId']) ) {
					$in .= $key > 0 ? ',%s' : '%s';
					$args[] = $classroom['domainId'];
				}
			}

			if ( count($args) ) {

				$users = $wpdb->get_results( $wpdb->prepare(
					"
					SELECT * FROM $classrooms_user_table
					WHERE classroomId IN ( $in ) GROUP BY userId ORDER BY is_teacher ASC
					",
					$args
				), ARRAY_A);

			}

		}

		return $users;
	}
	/**
	 * Get agora classroom users array
	 * 
	 * @param string $classroom
	 * @return array
	 */
	protected function get_agora_classroom_users( $classroom ){

		if ( $classroom ) {

			$cached = wp_cache_get( 'agora_classroom_users'. $classroom , 'super-agora' );

			if ( false !== $cached ) {
				return $cached;
			}
			
			global $wpdb;


			$classrooms_user_table = uoc_create_site_get_uoc_classrooms_user_table();

			$users = $wpdb->get_results( $wpdb->prepare(
				"
				SELECT * FROM $classrooms_user_table
				WHERE classroomId = %s GROUP BY userId
				",
				[ $classroom ]
			), ARRAY_A);

			wp_cache_set( 'agora_classroom_users'. $classroom, $users, 'super-agora' );

			return $users;

		}

		return [];
		
	}

	/**
	 * Return classroom id from blog id
	 *
	 * @since 1.0.0
	 */
	protected function get_classroom_id_from_blog_id( $blog_id ) {
		global $wpdb;

		$table 			= uoc_create_site_get_classroom_blog_table();
		$classroom_id   = $wpdb->get_var( $wpdb->prepare(
			"SELECT `domainId` FROM {$table} WHERE `blog_id` = %d", $blog_id
		) );

		return $classroom_id;
	}


	/**
	 * Insert/update SuperTag taxonomy term
	 */
	protected function update_supertag_term( $blog_id, $term ) {

		if ( $blog_id && $term && ! is_wp_error( $term ) ) {

			switch_to_blog( $blog_id );

			$blog_term = get_term_by( 'slug', $term->slug, $term->taxonomy );

			if ( ! $blog_term ) {
				$blog_term = wp_insert_term ( 
					$term->name,
					$term->taxonomy,
					[
						'description' => $term->description,
						'slug'        => $term->slug
					]
				);
			} else {
				$blog_term = wp_update_term ( 
					$blog_term->term_id, 
					$blog_term->taxonomy, 
					[
						'name'		  => $term->name,
						'description' => $term->description,
						'slug'        => $term->slug
					]
				);
			}

			if ( is_wp_error( $blog_term ) ) {
				$error = "Error syncing SuperTag term " . $term->slug;
				error_log( $error );
			}

			restore_current_blog();

		}
	}


	/**
	 * Remove SuperTag taxonomy term
	 */
	protected function delete_supertag_term( $blog_id, $term ) {

		if ( $blog_id && $term && ! is_wp_error( $term ) ) {

			switch_to_blog( $blog_id );

			$blog_term = get_term_by( 'slug', $term->slug, $term->taxonomy );

			if ( $blog_term && ! is_wp_error( $blog_term )) {
				wp_delete_term ( 
					$blog_term->term_id, 
					$blog_term->taxonomy
				);
			}

			restore_current_blog();

		}
	}



}