<?php

/**
 * Folio SuperAgora Custom DataBase Tables Management
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */

class Folio_Super_Agora_Data extends Folio_Super_Agora_Base {


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

        if (get_site_option($this->db_version_option) !== $this->db_version) {
            $this->upgrade_db();
			update_site_option($this->db_version_option, $this->db_version);
        }
		
    }

	/**
     * Creating Site Search Indexed Table
     *
     * @since 1.0.0
     * 
     */
    private function upgrade_db() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

		$table = $this->get_super_post_table();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `blogId` bigint NOT NULL COMMENT 'Blog of current User Id',
			`postId` bigint NOT NULL COMMENT 'Post of current blog',
			`classroomId` varchar(255) NOT NULL COMMENT 'Course identifier related',
			`institution` decimal(3,0) DEFAULT 1 COMMENT 'Maps the LMS related, 1 is for campus UOC, 2 is for Canvas UOC',
            `userId` decimal(10,0) NOT NULL COMMENT 'Wordpress User id',
			`classroomBlogId` bigint NOT NULL COMMENT 'Blog of SuperAgora subject',
			`classroomPostId` bigint NOT NULL COMMENT 'Post of SuperAgora blog',
			`created` datetime NOT NULL COMMENT 'Date created',
			`updated` datetime NOT NULL COMMENT 'Date updated',
		  	PRIMARY KEY (`blogId`, `postId`, `classroomId`, `institution`)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

    }


}
