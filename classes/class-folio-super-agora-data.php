<?php

/**
 * Folio SuperAgora Custom DataBase Tables Management
 * No longer used (delete table if exists)
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */

class Folio_Super_Agora_Data extends Folio_Super_Agora_Base {

    /**
	 * SuperAgora DB Version (not used anymore)
     * @deprecated deprecated since version 1.0.1
	 *
	 * @var string
	 */
	protected $db_version = '1.0.0';

    /**
	 * SuperAgora DB Version Option Key
	 *
	 * @var string
	 */
	protected $db_version_option = 'folio_superagora_db_version';


	/**
	 * Initialize the class
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->update_db_check();

	}


	/**
     * Check db update
     *
     * @since 1.0.0
     * 
     */
    public function update_db_check() {

        if ( get_site_option($this->db_version_option) ) {
            $this->upgrade_db();
            delete_site_option($this->db_version_option);
        }
		
    }


    /**
     * Delete SuperAgora posts table if exists
     * (no longer used)
     * @since 1.0.0
     * 
     */
    private function upgrade_db() {
        global $wpdb;
        $table = $wpdb->base_prefix . 'uoc_superagora_posts';
        $sql = "DROP TABLE IF EXISTS $table;";
        $wpdb->query($sql);
    }


}
