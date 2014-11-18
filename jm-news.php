<?php

/**
 * @package Jade Macro Custom News
 * @version 1.0.1
 */
/*
Plugin Name: JM News
Plugin URI:
Description: This plugin is for handling news both admin and front panel. More description coming.......
Author: Mithun Das
Version: 1.0.1
Author URI: https://github.com/mithundas79
*/

define('JM_NEWS_URL',plugin_dir_url(__FILE__ ));
define('JM_NEWS_PATH',plugin_dir_path(__FILE__ ));
define('JM_NEWS_REL_PATH', dirname(plugin_basename(__FILE__)).'/');

$news_manager = new News_Manager();

include_once(JM_NEWS_PATH.'inc/config.php');
include_once(JM_NEWS_PATH.'inc/query.php');
//include_once(JM_NEWS_PATH.'inc/widgets.php');
include_once(JM_NEWS_PATH.'inc/functions.php');

if(file_exists(JM_NEWS_PATH."VB.php")){
	require_once JM_NEWS_PATH.'VB.php';
}

/*$testVb = new WPVB();
echo $testVb->newThread(array(
	'forum_id' => 1,
	'wp_category'=> 'My thread',
	'wp_title' => 'Post title',
		'wp_post_text' => 'Post title'
)); die;
*/

class News_Manager
{
	private $vb;

	private $options = array();
	private $currencies = array();
	private $defaults = array(
			'general' => array(
					'supports' => array(
							'title' => TRUE,
							'editor' => TRUE,
							'author' => TRUE,
							'thumbnail' => TRUE,
							'excerpt' => TRUE,
							'custom-fields' => TRUE,
							'comments' => TRUE,
							'trackbacks' => FALSE,
							'revisions' => FALSE
					),
					'use_categories' => TRUE,
					'builtin_categories' => FALSE,
					'use_tags' => TRUE,
					'builtin_tags' => FALSE,
					'deactivation_delete' => FALSE,
					'news_nav_menu' => array(
							'show' => FALSE,
							'menu_name' => '',
							'menu_id' => 0,
							'item_id' => 0
					),
					'first_weekday' => 1,
					'news_in_rss' => FALSE,
					'display_news_in_tags_and_categories' => TRUE,
					'rewrite_rules' => TRUE
			),
			'capabilities' => array(
					'publish_news',
					'edit_news',
					'edit_others_news',
					'edit_published_news',
					'delete_published_news',
					'delete_news',
					'delete_others_news',
					'read_private_news',
					'manage_news_categories',
					'manage_news_tags'
			),
			'permalinks' => array(
					'news_slug' => 'news',
					'news_categories_rewrite_slug' => 'category',
					'news_tags_rewrite_slug' => 'tag',
					'single_news_prefix' => FALSE,
					'single_news_prefix_type' => 'category'
			),
			'version' => '1.0.1'
	);
	private $transient_id = '';


	public function __construct()
	{
		register_activation_hook(__FILE__, array(&$this, 'multisite_activation'));
		register_deactivation_hook(__FILE__, array(&$this, 'multisite_deactivation'));

		//settings
		$this->options = array_merge(
				array('general' => get_option('JM_news_general')),
				array('permalinks' => get_option('JM_news_permalinks'))
		);

		//initialize vb object to use in our custom news post
		if(class_exists("WPVB")){
			$this->vb = new WPVB();
		}


		//update plugin version
		update_option('JM_news_version', $this->defaults['version'], '', 'no');

		//session id
		$this->transient_id = (isset($_COOKIE['nm_transient_id']) ? $_COOKIE['nm_transient_id'] : 'nmtr_'.sha1($this->generate_hash()));

		//actions
		add_action('init', array(&$this, 'register_taxonomies'));
		add_action('init', array(&$this, 'register_post_types'));
		add_action('plugins_loaded', array(&$this, 'init_session'), 1);
		add_action('plugins_loaded', array(&$this, 'load_textdomain'));
		add_action('admin_footer', array(&$this, 'edit_screen_icon'));
		add_action('admin_enqueue_scripts', array(&$this, 'admin_scripts_styles'));
		add_action('wp_enqueue_scripts', array(&$this, 'front_scripts_styles'));
		add_action('admin_notices', array(&$this, 'news_admin_notices'));

		//filters
		add_filter('map_meta_cap', array(&$this, 'news_map_meta_cap'), 10, 4);
		add_filter('post_updated_messages', array(&$this, 'register_post_types_messages'));

		add_filter('request', array(&$this, 'myfeed_request'));
		add_filter('post_type_link', array(&$this, 'custom_post_type_link'), 10, 2);

		add_filter( 'cmb_meta_boxes', array(&$this, 'custom_news_meta_boxes'), 10, 5);

		add_action( 'init', array(&$this, 'cmb_initialize_cmb_meta_boxes'), 9999 );

		add_action( 'save_post', array(&$this, 'do_post_into_forum'), 10, 3 );
		add_action( 'before_delete_post', array(&$this, 'do_post_delete_forum'), 10, 3 );
		//add_action( 'pre_get_posts', array(&$this, 'posts_for_current_author'), 10, 3 );
	}



	/**
	 * Multisite activation
	 */
	public function multisite_activation($networkwide)
	{
		if(is_multisite() && $networkwide)
		{
			global $wpdb;

			$activated_blogs = array();
			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->activate_single();
				$activated_blogs[] = (int)$blog_id;
			}

			switch_to_blog($current_blog_id);
			update_site_option('JM_news_activated_blogs', $activated_blogs, array());
		}
		else
			$this->activate_single();
	}


	/**
	 * Activation
	 */
	public function activate_single()
	{
		global $wp_roles;

		//add caps to administrators
		foreach($wp_roles->roles as $role_name => $display_name)
		{
			$role = $wp_roles->get_role($role_name);

			if($role->has_cap('manage_options'))
			{
				foreach($this->defaults['capabilities'] as $capability)
				{
					$role->add_cap($capability);
				}
			}
		}

		//add default options
		add_option('JM_news_general', $this->defaults['general'], '', 'no');
		add_option('JM_news_capabilities', '', '', 'no');
		add_option('JM_news_permalinks', $this->defaults['permalinks'], '', 'no');
		add_option('JM_news_version', $this->defaults['version'], '', 'no');

		//permalinks
		flush_rewrite_rules();
	}


	/**
	 * Multisite deactivation
	 */
	public function multisite_deactivation($networkwide)
	{
		if(is_multisite() && $networkwide)
		{
			global $wpdb;

			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			if(($activated_blogs = get_site_option('JM_news_activated_blogs', FALSE, FALSE)) === FALSE)
				$activated_blogs = array();

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->deactivate_single(TRUE);

				if(in_array((int)$blog_id, $activated_blogs, TRUE))
					unset($activated_blogs[array_search($blog_id, $activated_blogs)]);
			}

			switch_to_blog($current_blog_id);
			update_site_option('JM_news_activated_blogs', $activated_blogs);
		}
		else
			$this->deactivate_single();
	}


	/**
	 * Deactivation
	 */
	public function deactivate_single($multi = FALSE)
	{
		global $wp_roles;

		//remove capabilities
		foreach($wp_roles->roles as $role_name => $display_name)
		{
			$role = $wp_roles->get_role($role_name);

			foreach($this->defaults['capabilities'] as $capability)
			{
				$role->remove_cap($capability);
			}
		}

		if($multi === TRUE)
		{
			$options = get_option('JM_news_general');
			$check = $options['deactivation_delete'];
		}
		else
			$check = $this->options['general']['deactivation_delete'];

		if($check === TRUE)
		{
			$settings = new News_Manager_Settings();
			$settings->update_menu();

			delete_option('JM_news_general');
			delete_option('JM_news_capabilities');
			delete_option('JM_news_permalinks');
			delete_option('JM_news_version');
		}

		//permalinks
		flush_rewrite_rules();
	}


	/**
	 *
	 */
	public function myfeed_request($feeds)
	{
		if(isset($feeds['feed']) && !isset($feeds['post_type']) && $this->options['general']['news_in_rss'] === TRUE)
			$feeds['post_type'] = array('post', 'news');

		return $feeds;
	}


	/**
	 *
	 */
	public function custom_post_type_link($post_link, $post_id)
	{
		$post = get_post($post_id);

		if(is_wp_error($post) || $post->post_type !== 'news' || empty($post->post_name))
			return $post_link;

		if($this->options['permalinks']['single_news_prefix'] === TRUE)
		{
			if($this->options['general']['use_tags'] === TRUE && $this->options['general']['builtin_tags'] === FALSE && $this->options['permalinks']['single_news_prefix_type'] === 'tag')
				$category = 'news-tag';
			elseif($this->options['general']['use_categories'] === TRUE && $this->options['general']['builtin_categories'] === FALSE && $this->options['permalinks']['single_news_prefix_type'] === 'category')
				$category = 'news-category';
			else
				return $post_link;
		}
		else
			return $post_link;

		$terms = get_the_terms($post->ID, $category);

		if(is_wp_error($terms) || !$terms)
			$term = '';
		else
		{
			$term_obj = array_pop($terms);
			$term = $term_obj->slug . '/';
		}

		return home_url(user_trailingslashit($this->options['permalinks']['news_slug'].'/'.$term.$post->post_name));
	}


	public function custom_news_meta_boxes(array $meta_boxes){

		// Start with an underscore to hide fields from custom fields list
		$prefix = '_cmb_';

		//more info box
		$meta_boxes['JM_news_more_info_box'] = array(
				'id'         => 'JM_news_more_info_box',
				'title'      => __( 'Publish', 'cmb' ),
				'pages'      => array( 'news', ), // Post type
				'context'    => 'normal',
				'priority'   => 'high',
				'show_names' => true, // Show field names on the left
			// 'cmb_styles' => true, // Enqueue the CMB stylesheet on the frontend
				'fields'     => array(
						array(
								'name' => __( 'Entry Thumbnail', 'cmb' ),
								'desc'    => __( 'Entry Thumbnail', 'cmb' ),
								'id'      => $prefix . 'news_thumb_image',
								'type'    => 'file',
						),
						array(
								'name' => __( 'News Type', 'cmb' ),
								'desc'    => __( 'News Type', 'cmb' ),
								'id'      => $prefix . 'news_type',
								'type'    => 'select',
								'options' => array(
										'regular' => __( 'Regular', 'cmb' ),
										'feature'   => __( 'Feature', 'cmb' ),
								),
						),
						array(
								'name' => __( 'Leading Content Asset - Image Gallery', 'cmb' ),
								'desc'    => __( 'Drop Your Files Here....', 'cmb' ),
								'id'      => $prefix . 'news_image_gallery',
								'type'    => 'file_list',
						),
						array(
								'name' => __( 'Content', 'cmb' ),
								'desc' => __( 'Content ', 'cmb' ),
								'id'   => $prefix . 'content',
								'type' => 'textarea',

						),
						array(
								'name'     => __( 'Content Tags ', 'cmb' ),
								'desc'     => __( 'Select tags', 'cmb' ),
								'id'       => $prefix . 'tags',
								'type'     => 'taxonomy_multicheck',
								'taxonomy' => 'news-tag', // Taxonomy Slug
								'inline'  => true, // Toggles display to inline
						),
						array(
								'name'     => __( 'Content Channel ', 'cmb' ),
								'desc'     => __( 'Content Channel ', 'cmb' ),
								'id'       => $prefix . 'taxonomy_category',
								'type'     => 'taxonomy_select',
								'taxonomy' => 'news-category', // Taxonomy Slug
						),
						array(
								'name' => __( 'Twitter Social Tags ', 'cmb' ),
								'desc' => __( 'Twitter Social Tags ', 'cmb' ),
								'id'   => $prefix . 'twitter_social_tags',
								'type' => 'text',
						),
						array(
								'name' => __( 'Exclusive', 'cmb' ),
								'desc'    => __( 'Exclusive', 'cmb' ),
								'id'      => $prefix . 'news_exclusive',
								'type'    => 'select',
								'options' => array(
										'1' => __( 'Yes', 'cmb' ),
										'0'   => __( 'No', 'cmb' ),
								),
						),
						array(
								'name' => __( 'Spotlight', 'cmb' ),
								'desc'    => __( 'Spotlight', 'cmb' ),
								'id'      => $prefix . 'news_spotlight',
								'type'    => 'select',
								'options' => array(
										'1' => __( 'Yes', 'cmb' ),
										'0'   => __( 'No', 'cmb' ),
								),
						),
						array(
								'name' => __( 'Spotlight Image (954 x 537px) ', 'cmb' ),
								'desc'    => __( 'Spotlight Image (954 x 537px) ', 'cmb' ),
								'id'      => $prefix . 'news_spotlight_image',
								'type'    => 'file',
						),
						array(
								'name' => __( 'Leading Asset Type', 'cmb' ),
								'desc'    => __( 'Leading Asset Type', 'cmb' ),
								'id'      => $prefix . 'news_leading_asset_type',
								'type'    => 'select',
								'options' => array(
										'gallery' => __( 'Gallery', 'cmb' ),
										'image'   => __( 'Image', 'cmb' ),
										'video'   => __( 'Video', 'cmb' ),
								),
						),
						array(
								'name' => __( 'Leading Content Asset - Image', 'cmb' ),
								'desc'    => __( 'Leading Content Asset - Image', 'cmb' ),
								'id'      => $prefix . 'news_leading_asset_image',
								'type'    => 'file_list',
						),
						array(
								'name' => __( 'Leading Content Asset - Video', 'cmb' ),
								'desc'    => __( 'Leading Content Asset - Video', 'cmb' ),
								'id'      => $prefix . 'news_leading_asset_video',
								'type'    => 'text',
						),
				),


		);

		//date meta box
		$meta_boxes['JM_news_date_metabox'] = array(
				'id'         => 'JM_news_date_metabox',
				'title'      => __( 'Date', 'cmb' ),
				'pages'      => array( 'news', ), // Post type
				'context'    => 'normal',
				'priority'   => 'high',
				'show_names' => true, // Show field names on the left
			// 'cmb_styles' => true, // Enqueue the CMB stylesheet on the frontend
				'fields'     => array(
						array(
								'name' => __( 'Entry date', 'cmb' ),
								'desc' => __( 'Entry date', 'cmb' ),
								'id'   => $prefix . 'entry_date',
								'type' => 'text_date_timestamp',
							// 'timezone_meta_key' => $prefix . 'timezone', // Optionally make this field honor the timezone selected in the select_timezone specified above
						),
						array(
								'name' => __( 'Expiration date', 'cmb' ),
								'desc' => __( 'Expiration date', 'cmb' ),
								'id'   => $prefix . 'expiry_date',
								'type' => 'text_date_timestamp',
							// 'timezone_meta_key' => $prefix . 'timezone', // Optionally make this field honor the timezone selected in the select_timezone specified above
						),
						array(
								'name' => __( 'Comment Expiration date', 'cmb' ),
								'desc' => __( 'Comment Expiration date', 'cmb' ),
								'id'   => $prefix . 'commen_expiry_date',
								'type' => 'text_date_timestamp',
							// 'timezone_meta_key' => $prefix . 'timezone', // Optionally make this field honor the timezone selected in the select_timezone specified above
						),
				),


		);

		//nsm meta box
		$meta_boxes['JM_news_nsm_metabox'] = array(
				'id'         => 'JM_news_nsm_metabox',
				'title'      => __( 'NSM Better Meta', 'cmb' ),
				'pages'      => array( 'news', ), // Post type
				'context'    => 'normal',
				'priority'   => 'high',
				'show_names' => true, // Show field names on the left
			// 'cmb_styles' => true, // Enqueue the CMB stylesheet on the frontend
				'fields'     => array(
						array(
								'name' => __( 'Title', 'cmb' ),
								'desc' => __( 'Title', 'cmb' ),
								'id'   => $prefix . 'nsm_title',
								'type' => 'text',
						),
						array(
								'name' => __( 'Description', 'cmb' ),
								'desc' => __( 'Recommended length 150 characters', 'cmb' ),
								'id'   => $prefix . 'nsm_description',
								'type' => 'textarea',

						),
				),


		);

		//forum meta box



		$vb = new WPVB();
		$forums = $vb->getForums();
		$forumOptions = null;
		if(!empty($forums)){
			foreach($forums as $forum){
				$forumOptions[$forum['forumid']] = $forum['forumtitle'];
			}

		}


		$meta_boxes['JM_news_forum_metabox'] = array(
				'id'         => 'JM_news_forum_metabox',
				'title'      => __( 'Forum', 'cmb' ),
				'pages'      => array( 'news', ), // Post type
				'context'    => 'normal',
				'priority'   => 'high',
				'show_names' => true, // Show field names on the left
			// 'cmb_styles' => true, // Enqueue the CMB stylesheet on the frontend
				'fields'     => array(
						array(
								'name' => __( 'Forum Topic Title ', 'cmb' ),
								'desc' => __( 'Forum Topic Title ', 'cmb' ),
								'id'   => $prefix . 'forum_title',
								'type' => 'text',
						),
						array(
								'name' => __( 'Forum Topic Text ', 'cmb' ),
								'desc' => __( 'Forum Topic Text ', 'cmb' ),
								'id'   => $prefix . 'forum_text',
								'type' => 'textarea',

						),
						array(
								'name' => __( 'Forum', 'cmb' ),
								'desc'    => __( 'Forum', 'cmb' ),
								'id'      => $prefix . 'forum_id',
								'type'    => 'select',
								'options' => $forumOptions,
						),
						array(
								'name' => __( 'Forum Topic ID', 'cmb' ),
								'desc' => __( 'Instructions:  If a forum topic already exists and you would like to associate it with your entry, submit the topic ID number and leave the above fields blank.', 'cmb' ),
								'id'   => $prefix . 'forum_topic_id',
								'type' => 'text',
						),
				),


		);
		return $meta_boxes;
	}

	public function do_post_into_forum($post_id){
		$slug = 'news';

		if ( $slug != $_POST['post_type'] ) {
			return;
		}

		$prefix = '_cmb_';
		$forum_title = '';
		$forum_text = '';
		$forum_id = '';
		$forum_topic_id  = '';

		if ( isset( $_REQUEST[$prefix . 'forum_title'] ) ) {
			$forum_title = $_REQUEST[$prefix . 'forum_title'];
		}
		if ( isset( $_REQUEST[$prefix . 'forum_text'] ) ) {
			$forum_text = $_REQUEST[$prefix . 'forum_text'];
		}
		if ( isset( $_REQUEST[$prefix . 'forum_id'] ) ) {
			$forum_id = $_REQUEST[$prefix . 'forum_id'];
		}
		if ( isset( $_REQUEST[$prefix . 'forum_topic_id'] ) ) {
			$forum_topic_id = $_REQUEST[$prefix . 'forum_topic_id'];
		}else{
			$forum_topic_id = get_post_meta($post_id, $prefix.'forum_topic_id', true);
		}
		//echo $forum_topic_id; die;
		$vB = new WPVB();

		if(!$forum_topic_id && $forum_title != '' && $forum_id != ''){
			$thread_id = $vB->newThread(array(
					'forum_id' => $forum_id,
					'thread_id' => $forum_topic_id,
				//'wp_category'=> 'Test Category',
					'wp_title' => $forum_title,
					'wp_post_text' => $forum_text
			));
			update_post_meta( $post_id, $prefix.'forum_topic_id', sanitize_text_field( $thread_id ) );
		}else if($forum_topic_id && $forum_title != '' && $forum_id != ''){
			$vB->newThread(array(
					'forum_id' => $forum_id,
					'thread_id' => $forum_topic_id,
				//'wp_category'=> 'Test Category',
					'wp_title' => $forum_title,
					'wp_post_text' => $forum_text
			));
			//update_post_meta( $post_id, $prefix.'forum_topic_id', sanitize_text_field( $forum_topic_id ) );
		}

	}


	public function do_post_delete_forum($post_id){
		$slug = 'news';
		global $post_type;

		if ( $slug != $post_type ) {
			return;
		}

		$prefix = '_cmb_';

		$forum_topic_id = get_post_meta($post_id, $prefix.'forum_topic_id', true);

		//echo $forum_topic_id; die;
		$vB = new WPVB();

		if($forum_topic_id){
			$vB->deleteThread($forum_topic_id);
		}

	}



	public function cmb_initialize_cmb_meta_boxes(){
		if ( ! class_exists( 'cmb_Meta_Box' ) )
			require_once JM_NEWS_PATH.'cmb/init.php';
	}


	function posts_for_current_author($query) {
		$slug = 'news';
		global $post_type;
		if ( $slug != $post_type ) {
			return;
		}
		if($query->is_admin) {

			global $user_ID;
			$query->set('author',  $user_ID);
		}
		return $query;
	}


	/**
	 *
	 */
	private function get_supports()
	{
		$supports = array();

		if(!empty($this->options['general']['supports'])){
			foreach($this->options['general']['supports'] as $support => $bool)
			{
				if($bool === TRUE)
					$supports[] = $support;
			}
		}


		return $supports;
	}


	/**
	 *
	 */
	public function get_defaults()
	{
		return $this->defaults;
	}


	/**
	 *
	 */
	public function get_session_id()
	{
		return $this->transient_id;
	}


	/**
	 * Generates random string
	 */
	private function generate_hash()
	{
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_[]{}<>~`+=,.;:/?|';
		$max = strlen($chars) - 1;
		$password = '';

		for($i = 0; $i < 64; $i++)
		{
			$password .= substr($chars, mt_rand(0, $max), 1);
		}

		return $password;
	}


	/**
	 * Initializes cookie-session
	 */
	public function init_session()
	{
		setcookie('nm_transient_id', $this->transient_id, 0, COOKIEPATH, COOKIE_DOMAIN);
	}


	/**
	 * Loads text domain
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain('JM-news', FALSE, JM_NEWS_REL_PATH.'languages/');
	}


	/**
	 *
	 */
	public function news_admin_notices()
	{
		global $pagenow;

		$screen = get_current_screen();
		$message_arr = get_transient($this->transient_id);

		if($screen->post_type === 'news' && $message_arr !== FALSE)
		{
			if(($pagenow === 'post.php' && $screen->id === 'news') || $screen->id === 'news_page_news-settings')
			{
				$messages = maybe_unserialize($message_arr);

				echo '
				<div id="message" class="'.$messages['status'].'">
					<p>'.$messages['text'].'</p>
				</div>';
			}

			delete_transient($this->transient_id);
		}
	}


	/**
	 * Registration of new custom taxonomies: news-category, news-tag
	 */
	public function register_taxonomies()
	{

		if($this->options['general']['use_categories'] === TRUE && $this->options['general']['builtin_categories'] === FALSE)
		{
			$labels_news_categories = array(
					'name' => _x('News Categories', 'taxonomy general name', 'JM-news'),
					'singular_name' => _x('News Category', 'taxonomy singular name', 'JM-news'),
					'search_items' =>  __('Search News Categories', 'JM-news'),
					'all_items' => __('All News Categories', 'JM-news'),
					'parent_item' => __('Parent News Category', 'JM-news'),
					'parent_item_colon' => __('Parent News Category:', 'JM-news'),
					'edit_item' => __('Edit News Category', 'JM-news'),
					'view_item' => __('View News Category', 'JM-news'),
					'update_item' => __('Update News Category', 'JM-news'),
					'add_new_item' => __('Add New News Category', 'JM-news'),
					'new_item_name' => __('New News Category Name', 'JM-news'),
					'menu_name' => __('News Categories', 'JM-news'),
			);

			$slug = $this->options['permalinks']['news_slug'].'/'.$this->options['permalinks']['news_categories_rewrite_slug'];

			if($this->options['permalinks']['single_news_prefix'] === TRUE && $this->options['permalinks']['single_news_prefix_type'] === 'category')
				$slug = $this->options['permalinks']['news_slug'];

			$args_news_categories = array(
					'public' => TRUE,
					'hierarchical' => TRUE,
					'labels' => $labels_news_categories,
					'show_ui' => TRUE,
					'show_admin_column' => TRUE,
					'update_count_callback' => '_update_post_term_count',
					'query_var' => TRUE,
					'rewrite' => array(
							'slug' => $slug,
							'with_front' => FALSE,
							'hierarchical' => FALSE
					),
					'capabilities' => array(
							'manage_terms' => 'manage_news_categories',
							'edit_terms' => 'manage_news_categories',
							'delete_terms' => 'manage_news_categories',
							'assign_terms' => 'edit_news'
					)
			);

			register_taxonomy('news-category', 'news', apply_filters('nm_register_news_categories', $args_news_categories));
		}

		if($this->options['general']['use_tags'] === TRUE && $this->options['general']['builtin_tags'] === FALSE)
		{
			$labels_news_tags = array(
					'name' => _x('News Tags', 'taxonomy general name', 'JM-news'),
					'singular_name' => _x('News Tag', 'taxonomy singular name', 'JM-news'),
					'search_items' =>  __('Search News Tags', 'JM-news'),
					'popular_items' => __('Popular News Tags', 'JM-news'),
					'all_items' => __('All News Tags', 'JM-news'),
					'parent_item' => null,
					'parent_item_colon' => null,
					'edit_item' => __('Edit News Tag', 'JM-news'),
					'update_item' => __('Update News Tag', 'JM-news'),
					'add_new_item' => __('Add New News Tag', 'JM-news'),
					'new_item_name' => __('New News Tag Name', 'JM-news'),
					'separate_items_with_commas' => __('Separate news tags with commas', 'JM-news'),
					'add_or_remove_items' => __('Add or remove news tags', 'JM-news'),
					'choose_from_most_used' => __('Choose from the most used news tags', 'JM-news'),
					'menu_name' => __('News Tags', 'JM-news'),
			);

			$slug = $this->options['permalinks']['news_slug'].'/'.$this->options['permalinks']['news_tags_rewrite_slug'];

			if($this->options['permalinks']['single_news_prefix'] === TRUE && $this->options['permalinks']['single_news_prefix_type'] === 'tag')
				$slug = $this->options['permalinks']['news_slug'];

			$args_news_tags = array(
					'public' => TRUE,
					'hierarchical' => FALSE,
					'labels' => $labels_news_tags,
					'show_ui' => TRUE,
					'show_admin_column' => TRUE,
					'update_count_callback' => '_update_post_term_count',
					'query_var' => TRUE,
					'rewrite' => array(
							'slug' => $slug,
							'with_front' => FALSE,
							'hierarchical' => FALSE
					),
					'capabilities' => array(
							'manage_terms' => 'manage_news_tags',
							'edit_terms' => 'manage_news_tags',
							'delete_terms' => 'manage_news_tags',
							'assign_terms' => 'edit_news'
					)
			);

			register_taxonomy('news-tag', 'news', apply_filters('nm_register_news_tags', $args_news_tags));
		}

	}


	/**
	 * Registration of new custom post types: news
	 */
	public function register_post_types()
	{
		$labels_news = array(
				'name' => _x('News', 'post type general name', 'JM-news'),
				'singular_name' => _x('News', 'post type singular name', 'JM-news'),
				'menu_name' => __('News', 'JM-news'),
				'all_items' => __('All News', 'JM-news'),
				'add_new' => __('Add New', 'JM-news'),
				'add_new_item' => __('Add New News', 'JM-news'),
				'edit_item' => __('Edit News', 'JM-news'),
				'new_item' => __('New News', 'JM-news'),
				'view_item' => __('View News', 'JM-news'),
				'items_archive' => __('News Archive', 'JM-news'),
				'search_items' => __('Search News', 'JM-news'),
				'not_found' => __('No news found', 'JM-news'),
				'not_found_in_trash' => __('No news found in trash', 'JM-news'),
				'parent_item_colon' => ''
		);

		$taxonomies = array();

		if($this->options['general']['use_tags'] === TRUE)
		{
			if($this->options['general']['builtin_tags'] === FALSE)
				$taxonomies[] = 'news-tag';
			else
				$taxonomies[] = 'post_tag';
		}

		if($this->options['general']['use_categories'] === TRUE)
		{
			if($this->options['general']['builtin_categories'] === FALSE)
				$taxonomies[] = 'news-category';
			else
				$taxonomies[] = 'category';
		}

		$prefix = '';

		if($this->options['permalinks']['single_news_prefix'] === TRUE)
		{
			if($this->options['general']['use_tags'] === TRUE && $this->options['general']['builtin_tags'] === FALSE && $this->options['permalinks']['single_news_prefix_type'] === 'tag')
				$prefix = '/%news-tag%';
			elseif($this->options['general']['use_categories'] === TRUE && $this->options['general']['builtin_categories'] === FALSE && $this->options['permalinks']['single_news_prefix_type'] === 'category')
				$prefix = '/%news-category%';
		}

		// Menu icon
		global $wp_version;

		$menu_icon = JM_NEWS_URL.'/images/icon-news-16.png';
		if ($wp_version >= 3.8)
		{
			$menu_icon = 'dashicons-format-aside';
		}

		$args_news = array(
				'labels' => $labels_news,
				'description' => '',
				'public' => TRUE,
				'exclude_from_search' => FALSE,
				'publicly_queryable' => TRUE,
				'show_ui' => TRUE,
				'show_in_menu' => TRUE,
				'show_in_admin_bar' => TRUE,
				'show_in_nav_menus' => TRUE,
				'menu_position' => 5,
				'menu_icon' => $menu_icon,
				'capability_type' => 'news',
				'capabilities' => array(
						'publish_posts' => 'publish_news',
						'edit_posts' => 'edit_news',
						'edit_others_posts' => 'edit_others_news',
						'edit_published_posts' => 'edit_published_news',
						'delete_published_posts' => 'delete_published_news',
						'delete_posts' => 'delete_news',
						'delete_others_posts' => 'delete_others_news',
						'read_private_posts' => 'read_private_news',
						'edit_post' => 'edit_single_news',
						'delete_post' => 'delete_single_news',
						'read_post' => 'read_single_news',
				),
				'map_meta_cap' => FALSE,
				'hierarchical' => FALSE,
				'supports' => $this->get_supports($this->options['general']['supports']),
				'rewrite' => array(
						'slug' => $this->options['permalinks']['news_slug'].$prefix,
						'with_front' => FALSE,
						'feed'=> TRUE,
						'pages'=> TRUE
				),
				'has_archive' => $this->options['permalinks']['news_slug'],
				'query_var' => TRUE,
				'can_export' => TRUE,
				'taxonomies' => $taxonomies,
		);

		register_post_type('news', apply_filters('nm_register_post_type', $args_news));
	}


	/**
	 * Custom post type messages
	 */
	public function register_post_types_messages($messages)
	{
		global $post, $post_ID;

		$messages['news'] = array(
				0 => '', //Unused. Messages start at index 1.
				1 => sprintf(__('News updated. <a href="%s">View news</a>', 'JM-news'), esc_url(get_permalink($post_ID))),
				2 => __('Custom field updated.', 'JM-news'),
				3 => __('Custom field deleted.', 'JM-news'),
				4 => __('News updated.', 'JM-news'),
			//translators: %s: date and time of the revision
				5 => isset($_GET['revision']) ? sprintf(__('News restored to revision from %s', 'JM-news'), wp_post_revision_title((int)$_GET['revision'], FALSE)) : FALSE,
				6 => sprintf(__('News published. <a href="%s">View news</a>', 'JM-news'), esc_url(get_permalink($post_ID))),
				7 => __('News saved.', 'JM-news'),
				8 => sprintf(__('News submitted. <a target="_blank" href="%s">Preview news</a>', 'JM-news'), esc_url( add_query_arg('preview', 'true', get_permalink($post_ID)))),
				9 => sprintf(__('News scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview news</a>', 'JM-news'),
						//translators: Publish box date format, see http://php.net/date
						date_i18n(__('M j, Y @ G:i'), strtotime($post->post_date)), esc_url(get_permalink($post_ID))),
				10 => sprintf(__('News draft updated. <a target="_blank" href="%s">Preview news</a>', 'JM-news'), esc_url(add_query_arg('preview', 'true', get_permalink($post_ID))))
		);

		return $messages;
	}


	/**
	 *
	 */
	public function admin_scripts_styles($page)
	{
		$screen = get_current_screen();

		//widgets
		if($page === 'widgets.php')
		{
			wp_register_script(
					'JM-news-admin-widgets',
					JM_NEWS_URL.'/js/admin-widgets.js',
					array('jquery')
			);

			wp_enqueue_script('JM-news-admin-widgets');

			wp_register_style(
					'JM-news-admin',
					JM_NEWS_URL.'/css/admin.css'
			);

			wp_enqueue_style('JM-news-admin');
		}
		//news options page
		elseif($page === 'news_page_news-settings')
		{
			wp_register_script(
					'JM-news-admin-settings',
					JM_NEWS_URL.'/js/admin-settings.js',
					array('jquery', 'jquery-ui-core', 'jquery-ui-button')
			);

			wp_enqueue_script('JM-news-admin-settings');

			wp_localize_script(
					'JM-news-admin-settings',
					'nmArgs',
					array(
							'resetToDefaults' => __('Are you sure you want to reset these settings to defaults?', 'JM-news'),
							'tagsRewriteURL' => site_url().'/<strong>'.$this->options['permalinks']['news_slug'].'</strong>/<strong>'.$this->options['permalinks']['news_tags_rewrite_slug'].'</strong>/news-title/',
							'categoriesRewriteURL' => site_url().'/<strong>'.$this->options['permalinks']['news_slug'].'</strong>/<strong>'.$this->options['permalinks']['news_categories_rewrite_slug'].'</strong>/news-title/'
					)
			);

			wp_register_style(
					'JM-news-admin',
					JM_NEWS_URL.'/css/admin.css'
			);

			wp_enqueue_style('JM-news-admin');

			wp_register_style(
					'JM-news-wplike',
					JM_NEWS_URL.'/css/wp-like-ui-theme.css'
			);

			wp_enqueue_style('JM-news-wplike');
		}
		//list of news
		elseif($page === 'edit.php' && $screen->post_type === 'news')
		{
			wp_register_style(
					'JM-news-admin',
					JM_NEWS_URL.'/css/admin.css'
			);

			wp_enqueue_style('JM-news-admin');



		}


		//new add or edit
		if(($page === 'post.php' || $page === 'edit.php' || $page === 'post-new.php') && $screen->post_type === 'news')
		{
			wp_register_style(
					'JM-news-admin',
					JM_NEWS_URL.'/css/admin.css'
			);

			wp_enqueue_style('JM-news-admin');

			wp_register_style(
					'JM-news-wplike',
					JM_NEWS_URL.'/css/wp-like-ui-theme.css'
			);

			wp_enqueue_style('JM-news-wplike');

			wp_enqueue_script("jquery-ui-tabs");

			wp_register_script(
					'JM-news-admin-news-edit',
					JM_NEWS_URL.'/js/news-edit.js',
					array('jquery', 'jquery-ui-tabs')
			);

			wp_enqueue_script('JM-news-admin-news-edit');
		}

	}


	/**
	 *
	 */
	public function front_scripts_styles()
	{
		wp_register_style(
				'JM-news-front',
				JM_NEWS_URL.'/css/front.css'
		);

		wp_enqueue_style('JM-news-front');
	}


	/**
	 * Edit screen icon
	 */
	public function edit_screen_icon()
	{
		// Screen icon
		global $wp_version;
		if ($wp_version < 3.8)
		{
			global $post;

			if(get_post_type($post) === 'news' || (isset($_GET['post_type']) && $_GET['post_type'] === 'news'))
			{
				echo '
				<style>
					#icon-edit { background: transparent url(\''.JM_NEWS_URL.'/images/icon-news-32.png\') no-repeat; }
				</style>';
			}
		}
	}





	/**
	 * Maps capabilities
	 */
	public function news_map_meta_cap($caps, $cap, $user_id, $args)
	{
		if('edit_single_news' === $cap || 'delete_single_news' === $cap || 'read_single_news' === $cap)
		{
			$post = get_post($args[0]);
			$post_type = get_post_type_object($post->post_type);
			$caps = array();

			if($post->post_type !== 'news')
				return $caps;
		}

		if('edit_single_news' === $cap)
		{
			if ($user_id == $post->post_author)
				$caps[] = $post_type->cap->edit_posts;
			else
				$caps[] = $post_type->cap->edit_others_posts;
		}
		elseif('delete_single_news' === $cap)
		{
			if (isset($post->post_author) && $user_id == $post->post_author)
				$caps[] = $post_type->cap->delete_posts;
			else
				$caps[] = $post_type->cap->delete_others_posts;
		}
		elseif('read_single_news' === $cap)
		{
			if ('private' != $post->post_status)
				$caps[] = 'read';
			elseif ($user_id == $post->post_author)
				$caps[] = 'read';
			else
				$caps[] = $post_type->cap->read_private_posts;
		}

		return $caps;
	}
}

