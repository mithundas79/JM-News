<?php

class NewsInstall{
	public $news_db_version = 1.0;

	function __construct() {
		register_activation_hook( __FILE__, array( $this, 'news_install' ) );
		register_activation_hook( __FILE__, array( $this, 'news_install_data' ) );
		register_deactivation_hook( __FILE__, array( $this, 'news_deactivate' ) );

	}

	function news_install(){
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$news_table = $wpdb->prefix . "news";


		$sql = "CREATE TABLE ".$news_table." (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            category_id bigint(20),
            name VARCHAR(250) NOT NULL,
            image_url VARCHAR(250) NOT NULL,
            description TEXT DEFAULT '' NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY (id)
          )ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;";


		dbDelta( $sql );


		$category_table = $wpdb->prefix . "news_category";
		$sql = "CREATE TABLE ".$category_table." (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            name VARCHAR(250) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY (id)
          )ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;";


		dbDelta( $sql );


		add_option( "news_db_version", $this->news_db_version );



	}

	function news_deactivate(){

	}

	function news_install_data() {
		global $wpdb;
		$category_table = $wpdb->prefix . "news_category";
		$data = array(
				array( 'time' => current_time('mysql'), 'name' => "Category 1", ),
				array( 'time' => current_time('mysql'), 'name' => "Category 2", )
		);

		$rows_affected = $wpdb->insert( $category_table, $data[0] );
		$rows_affected = $wpdb->insert( $category_table, $data[1] );
	}

	function update(){
		global $wpdb;
		$installed_ver = get_option( "news_db_version" );

		if( $installed_ver != $this->news_db_version ) {
			//Write db update codes here

		}
	}
}

$newsInstall = new NewsInstall();

?>