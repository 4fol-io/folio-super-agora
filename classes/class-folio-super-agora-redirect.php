<?php

/**
 * Folio SuperAgora Redirect
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */


class Folio_Super_Agora_Redirect extends Folio_Super_Agora_Base {


	/**
	 * Initialize the class
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		add_action( 'admin_init',  			[ $this, 'redirect_back' ] );
		add_action( 'template_redirect', 	[ $this, 'redirect_front' ] );

	}

	/**
	 * Redirect Backend Agoras to SuperAgora
	 * 
	 * @since    1.0.0
	 */
	public function redirect_back() {

		if ( ucs_is_classroom_blog () ) {
			$super = get_option( $this->superagora_option );
			if ( $super ) {
				$options = get_blog_option( $super, $this->settings_option );
				$enabled = isset($options['enabled']) ? intval($options['enabled']) : 0;
				$redirect = isset($options['redirect_back']) ? intval($options['redirect_back']) : 0;
				if( $enabled && $redirect ){
					switch_to_blog( $super );
					if ( is_admin() && ! is_user_logged_in() ) {
						wp_redirect( home_url() );
					} else {
						wp_redirect( admin_url() ); 
					}
					exit;
				}
			}
		}

	}

	/**
	 * Redirect Frontend Agoras to SuperAgora
	 * 
	 * @since    1.0.0
	 */
	public function redirect_front() {

		if ( ucs_is_classroom_blog () ) {
			$super = get_option( $this->superagora_option, 0 );
			if ( $super ) {
				$options = get_blog_option( $super, $this->settings_option );
				$enabled = isset($options['enabled']) ? intval($options['enabled']) : 0;
				$redirect = isset($options['redirect_front']) ? intval($options['redirect_front']) : 0;
				if( $enabled && $redirect ){
					switch_to_blog( $super );
                    wp_redirect( home_url() );
					exit;
				}
			}
		}

	}

}

