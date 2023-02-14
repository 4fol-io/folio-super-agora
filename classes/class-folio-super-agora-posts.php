<?php

/**
 * Folio SuperAgora Posts Sync
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */

use portafolis_create_site\classes\PortafolioCreatesiteParseContent;
use portafolis_create_site\classes\PortafolioDuplicate;

class Folio_Super_Agora_Posts extends Folio_Super_Agora_Base {


	/**
	 * Initialize the class
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		add_action( 'save_post', 			[ $this, 'saved_post' ], 99, 3 );
		add_action( 'deleted_post', 		[ $this, 'deleted_post' ] );
		add_filter( 'get_edit_post_link', 	[ $this, 'get_edit_post_link' ], 10, 3 );
		add_filter( 'user_has_cap', 		[ $this, 'user_has_caps'], 10, 4 );


	}

	/**
	 * Saved post
	 */
	public function saved_post( $post_id, $post, $update ) {

		if ( ! uoc_create_site_check_has_to_do_action_saved_post( $post_id, $post ) ) {
			return $post_id;
		}
		// The user is editing his personal blog
		if ( uoc_create_site_is_current_student_blog() ) {

			$student_id = uoc_create_site_get_admin_student_id();

			if ( $student_id > 0 ) {

				$subjects 				= uoc_create_site_get_subjects_of_post( $post_id );
				$post 					= get_post( $post_id );
				$blog_id 				= get_current_blog_id();
				$post_visibility		= uoc_create_site_get_visibility( $post_id );
				$superagoras_ids		= [];
				$superagoras			= [];

				if ( ! $post ) {
					return false;
				}

				foreach ( $subjects as $subject ) {
					if ( isset($subject->other_blog_id) ) {
						$super = get_blog_option( $subject->other_blog_id, $this->superagora_option, 0 );
						if ( $super ) {
							$classroom_id = $this->get_super_classroom_id($super);
							if (! in_array($classroom_id, $superagoras_ids)){
								$superagoras[] = (object) array(
									'classroomId' 	=> $classroom_id, 
									'other_blog_id' => $super,
									'activities'	=> isset($subject->activities) ? $subject->activities : [],
								);
								$superagoras_ids[] = $classroom_id;
							}else{
								$index = array_search($classroom_id, array_column($superagoras, 'classroomId'));
								$activities = isset($subject->activities) ? $subject->activities : [];
								if ( $index !== false ) {
									$superagoras[$index]->activities = array_merge( $superagoras[$index]->activities, $activities );
								}
							}
						}
					}
				}

				foreach ( $superagoras as $superagora ) {
					switch ( $post_visibility ) {
						case PORTAFOLIS_UOC_ACCESS_PRIVATE: 
							//delete if exist on external blog
							$this->delete_external_post( $post, $superagora, $blog_id );
							break;
						default: 
							//false, public, uoc, subject and password
							$this->create_or_update_post( $post, $superagora, $student_id, $blog_id );
					}
				}
				
				$to_delete = $this->get_super_posts_to_delete( $blog_id, $post_id, $superagoras_ids );
            
				foreach ( $to_delete as $superagora ) {
					$this->delete_external_post_action( $post_id, $blog_id, $superagora );
				}
			}
		}else{

		}
	
		return $post_id;
	}

	/**
	 * Deleted post
	 */
	public function deleted_post( $post_id ) {

		//The user is editing his personal blog
		if ( uoc_create_site_is_current_student_blog() ) {
	
			$student_id = uoc_create_site_get_admin_student_id();
	
			if ( $student_id > 0 ) {
				$blog_id    = get_current_blog_id();
				$classrooms = $this->get_external_posts( $blog_id, $post_id );
				foreach ( $classrooms as $classroom ) {
					$this->delete_external_post( $post_id, $blog_id, $classroom );
				}
			}
	
		}
	
		return $post_id;
	}

	/**
	 * Create or update post
	 */
	private function create_or_update_post( $post, $subject, $user_id, $blog_id ) {
		if ( $subject->other_blog_id > 0 ) {
	
			$external_post = $this->get_external_post( $blog_id, $post->ID, $subject->classroomId );
			$post          = PortafolioDuplicate::getFilteredS3Content( $post );
			$my_post       = $post->to_array();
			if ( $external_post ) {
				$my_post['ID'] = $external_post->classroomPostId;
			} else {
				unset( $my_post['ID'] );
			}

			// Get SuperTags from original post
			$supertags = apply_filters( 'folio-supertags/get', [], $post->ID, $blog_id );

			$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
			$image_url         = false;
			if ( ! empty( $post_thumbnail_id ) ) {
				$image_url = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );
				$image_url = count( $image_url ) > 0 ? $image_url[0] : false;
			}
	
			// We are using S3 uploader we don't need to change url
			$source_blog_url = site_url();
			switch_to_blog( $subject->other_blog_id );
			$destination_blog_url = site_url();
			$parserPortfolio = new PortafolioCreatesiteParseContent( 
				$source_blog_url, 
				$destination_blog_url, 
				$blog_id, 
				$subject->other_blog_id 
			);
			switch_to_blog( $subject->other_blog_id );
			$my_post['post_content'] = $parserPortfolio->uoc_portfolio_create_site_parse_content( $my_post['post_content'] );
	
			// Insert or UPDATE the post into the database
			$new_post_id = wp_insert_post( $my_post );
			if ( is_wp_error( $new_post_id ) ) {
				$error = "Error generating SuperAgora external post " . print_r( $new_post_id, true );
				error_log( $error );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					wp_die( $error );
				}
			}

			// Sync SuperTags to new post
			do_action( 'folio-supertags/set', $supertags, $new_post_id, $subject->other_blog_id );
	
			if ( $image_url ) {
				// Add Featured Image to Post
				$arrContextOptions = array(
					"ssl" => array(
						"verify_peer"      => false,
						"verify_peer_name" => false,
					),
				);
				$upload_dir        = wp_upload_dir(); // Set upload folder
				$image_data        = file_get_contents ( 
					$image_url, 
					false, 
					stream_context_create( $arrContextOptions ) 
				); // Get image data
				$filename          = basename( $image_url ); // Create image file name
	
				// Check folder permission and define file location
				if ( wp_mkdir_p( $upload_dir['path'] ) ) {
					$file = $upload_dir['path'] . '/' . $filename;
				} else {
					$file = $upload_dir['basedir'] . '/' . $filename;
				}
	
				// Create the image  file on the server
				file_put_contents( $file, $image_data );
	
				// Check image file type
				$wp_filetype = wp_check_filetype( $filename, null );
	
				// Set attachment data
				$attachment = array(
					'post_mime_type' => $wp_filetype['type'],
					'post_title'     => sanitize_file_name( $filename ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				);
	
				// Create the attachment
				$attach_id = wp_insert_attachment( $attachment, $file, $post->ID );
	
				// Include image.php
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
	
				// Define attachment metadata
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
	
				// Assign metadata to attachment
				wp_update_attachment_metadata( $attach_id, $attach_data );
	
				// And finally assign featured image to post
				set_post_thumbnail( $new_post_id, $attach_id );
			} else {
				if ( ! empty( get_post_thumbnail_id( $new_post_id ) ) ) {
					delete_post_thumbnail( $new_post_id );
				}
			}
	
			switch_to_blog( $blog_id );
	
			$this->update_external_post( $external_post, $blog_id, $post, $subject, $new_post_id, $user_id );
		} else {
			$error = "Can not register post because SuperAgora blog subject don't exist " . print_r( $subject, 1 );
			error_log( $error );
		}
	}

	/**
     * Update external post
	 */
	private function update_external_post( $external_post, $blog_id, $post, $subject, $new_post_id, $user_id ) {
		global $wpdb;

		$table = $this->get_super_post_table();
		if ( $external_post ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE $table SET updated=now() WHERE blogId = %d AND postId=%d AND classroomId=%s",
				array(
					$blog_id,
					$post->ID,
					$subject->classroomId
				)
			) );
		} else {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO $table
				  (blogId, postId, classroomId, userId, classroomBlogId, classroomPostId, created, updated) VALUES
				  (%d, %d, %s, %d, %d, %d, now(), now())",
				array(
					$blog_id,
					$post->ID,
					$subject->classroomId,
					$user_id,
					$subject->other_blog_id,
					$new_post_id
				)
			) );
		}
	
		switch_to_blog( $subject->other_blog_id );
		wp_delete_object_term_relationships( $new_post_id, 'actiuoc' );
		wp_set_object_terms( $new_post_id, $subject->activities, 'actiuoc' );
		switch_to_blog( $blog_id );
	}
	
	/**
	 * Delete extenal post
	 */
	private function delete_external_post( $post, $subject, $blog_id ) {
		if ( $subject->other_blog_id > 0 ) {
			$external_post = $this->get_external_post( $blog_id, $post->ID, $subject->classroomId );
	
			if ( $external_post ) {
				$this->delete_external_post_action( $post->ID, $blog_id, $external_post );
			}
	
		} else {
			$error = "Can not delete external SuperAgora post because blog subject don't exist " . print_r( $subject, 1 );
			error_log( $error );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				wp_die( $error );
			}
		}
	}

	/**
	 * Delete external post action
	 * 
	 * @param $post_id
	 * @param $blog_id
	 * @param $external_post
	 */
	private function delete_external_post_action( $post_id, $blog_id, $external_post ) {
		global $wpdb;

		switch_to_blog( $external_post->classroomBlogId );
		
		$table = $this->get_super_post_table();

		wp_delete_post( $external_post->classroomPostId );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM $table WHERE blogId = %d AND postId=%d AND classroomId=%d",
			array(
				$blog_id,
				$post_id,
				$external_post->classroomId 
			)
		) );

		switch_to_blog( $blog_id );
	}

	/**
	 * Returns information of external post
	 *
	 * @param $blog_id
	 * @param $post_id
	 * @param $classroom_id
	 *
	 * @return array|null|object|void
	 */
	private function get_external_post( $blog_id, $post_id, $classroom_id ) {
		global $wpdb;

		$table = $this->get_super_post_table();
		$external_post   = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE blogId = %d AND postId = %d AND classroomId = %d",
			array( $blog_id, $post_id, $classroom_id )
		) );

		return $external_post;
	}

	/**
	 * Get superagoras posts of current post
	 *
	 * @param $blog_id
	 * @param $post_id
	 *
	 * @return array|null|object|void
	 */
	private function get_external_posts( $blog_id, $post_id ) {
		global $wpdb;

		$table = $this->get_super_post_table();
		$external_posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE blogId = %d AND postId = %d",
			array( $blog_id, $post_id )
		) );

		return $external_posts;
	}

	/**
	 * Returns information of original post
	 *
	 * @param $classroomBlogId
	 * @param $classroomPostId
	 *
	 * @return array|null|object|void
	 */
	function get_original_post( $classroomBlogId, $classroomPostId ) {
		global $wpdb;

		$table = $this->get_super_post_table();
		$original_post = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE classroomBlogId = %d AND classroomPostId = %d",
			array( $classroomBlogId, $classroomPostId )
		) );

		return $original_post;
	}

	/**
	 * Get super agora posts to delete
	 */
	private function get_super_posts_to_delete( $blog_id, $post_id, $superagoras ) {
		global $wpdb;

		if ( count( $superagoras ) == 0 ) {
			$superagoras = array( 0 );
		}
		$table = $this->get_super_post_table();
		$external_posts   = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE blogId = %d AND postId = %d AND classroomId NOT IN 
			(" . implode( ', ', array_fill( 0, count( $superagoras ), '%s' ) ) . ")",
			array_merge( array( $blog_id, $post_id ), $superagoras )
		) );
	
		return $external_posts;
	}

	/**
     * Get superagora edit post link
     */
	public function get_edit_post_link( $link, $post_id, $context ) {
		if ( ! uoc_create_site_is_classroom_blog() && ! uoc_create_site_is_student_blog() ) {
			$is_superagora = get_option( $this->settings_option );
			if ( $is_superagora ) {
				$blog_id   = get_current_blog_id();
				$classroom = $this->get_original_post( $blog_id, $post_id );
				if ( $classroom ) {
					switch_to_blog( $classroom->blogId );
					$link_temp = get_edit_post_link( $classroom->postId, $context );
					if ( $link_temp != null && ! empty( $link_temp ) ) {
						$link = $link_temp;
					}
					switch_to_blog( $blog_id );
				}
			}
		}
	
		return $link;
	}

	/**
	 * Enable capabilities for post author
	 */
	public function user_has_caps( $allcaps, $caps, $args, $user ) {
		if ( ! uoc_create_site_is_classroom_blog() && ! uoc_create_site_is_student_blog() && count( $args ) == 3 && $args[0] == 'edit_post' ) {
			//then check if user is the author
			$current_user_id = $args[1];
			$post_id         = $args[2];
			if ( get_post_field( 'post_author', $post_id ) == $current_user_id ) {
				foreach ( $caps as $cap ) {
					if ( ! isset( $allcaps[ $cap ] ) ) {
						$allcaps[ $cap ] = true;
					}
				}
			}
		}
	
		return $allcaps;
	}
	

}

