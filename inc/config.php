<?php
if(!defined('ABSPATH'))	exit; //exit if accessed directly

new News_Manager_Settings($news_manager->get_defaults(), $news_manager->get_session_id());

class News_Manager_Settings
{
    private $capabilities = array();
    private $choices = array();
    private $defaults = array();
    private $errors = array();
    private $options = array();
    private $taxonomies = array();
    private $supports = array();
    private $tabs = array();
    private $prefix_types = array();
    private $transient_id = '';


    public function __construct($defaults = array(), $session_id = '')
    {
        //settings
        $this->options = array_merge(
            array('general' => get_option('oz_news_general')),
            array('permalinks' => get_option('oz_news_permalinks'))
        );

        //passed vars
        $this->defaults = $defaults;
        $this->transient_id = $session_id;

        //actions
        add_action('init', array(&$this, 'update_nav_menu'));
        add_action('admin_menu', array(&$this, 'settings_page'));
        add_action('admin_init', array(&$this, 'register_settings'));
        add_action('after_setup_theme', array(&$this, 'load_defaults'));

        //filters
        add_filter('plugin_action_links', array(&$this, 'plugin_settings_link'), 10, 2);
    }


    /**
     *
     */
    public function update_nav_menu()
    {
        if($this->options['general']['rewrite_rules'] === TRUE && $this->options['general']['news_nav_menu']['show'] === TRUE)
        {
            $this->update_menu($this->options['general']['news_nav_menu']['menu_id'], $this->options['general']['news_nav_menu']['menu_name']);
        }
    }


    /**
     *
     */
    public function load_defaults()
    {
        $this->choices = array(
            'yes' => __('Enable', 'oz-news'),
            'no' => __('Disable', 'oz-news')
        );

        $this->taxonomies = array(
            'tags' => array(
                'dedicated' => array(
                    __('News Tags', 'oz-news'),
                    __('Tags dedicated for news post type only.', 'oz-news')
                ),
                'builtin' => array(
                    __('Tags', 'oz-news'),
                    __('Default WordPress tags, same as for posts.', 'oz-news')
                )
            ),
            'categories' => array(
                'dedicated' => array(
                    __('News Categories', 'oz-news'),
                    __('Categories dedicated for news post type only.', 'oz-news')
                ),
                'builtin' => array(
                    __('Categories', 'oz-news'),
                    __('Default WordPress categories, same as for posts.', 'oz-news')
                )
            )
        );

        $this->supports = array(
            'title' => __('title', 'oz-news'),
            'editor' => __('editor', 'oz-news'),
            'author' => __('author', 'oz-news'),
            'thumbnail' => __('thumbnail', 'oz-news'),
            'excerpt' => __('excerpt', 'oz-news'),
            'custom-fields' => __('custom fields', 'oz-news'),
            'comments' => __('comments', 'oz-news'),
            'trackbacks' => __('trackbacks', 'oz-news'),
            'revisions' => __('revisions', 'oz-news')
        );

        $this->errors = array(
            'settings_gene_saved' => __('General settings saved.', 'oz-news'),
            'settings_caps_saved' => __('Capabilities settings saved.', 'oz-news'),
            'settings_perm_saved' => __('Permalinks settings saved.', 'oz-news'),
            'settings_gene_reseted' => __('General settings restored to defaults.', 'oz-news'),
            'settings_caps_reseted' => __('Capabilities settings restored to defaults.', 'oz-news'),
            'settings_perm_reseted' => __('Permalinks settings restored to defaults.', 'oz-news'),
            'no_such_menu' => __('Such menu does not exist.', 'oz-news'),
            'empty_menu_name' => __('Menu name can not be empty.', 'oz-news')
        );

        $this->tabs = array(
            'general' => array(
                'name' => __('General', 'oz-news'),
                'key' => 'oz_news_general',
                'submit' => 'save_nm_general',
                'reset' => 'reset_nm_general'
            ),
            'capabilities' => array(
                'name' => __('Capabilities', 'oz-news'),
                'key' => 'oz_news_capabilities',
                'submit' => 'save_nm_capabilities',
                'reset' => 'reset_nm_capabilities'
            ),
            'permalinks' => array(
                'name' => __('Permalinks', 'oz-news'),
                'key' => 'oz_news_permalinks',
                'submit' => 'save_nm_permalinks',
                'reset' => 'reset_nm_permalinks'
            )
        );

        $this->prefix_types = array(
            'tag' => __('Tag prefix', 'oz-news'),
            'category' => __('Category prefix', 'oz-news')
        );

        $this->capabilities = array(
            'publish_news' => __('Publish News', 'oz-news'),
            'edit_news' => __('Edit News', 'oz-news'),
            'edit_others_news' => __('Edit Others News', 'oz-news'),
            'edit_published_news' => __('Edit Published News', 'oz-news'),
            'delete_published_news' => __('Delete Published News', 'oz-news'),
            'delete_news' => __('Delete News', 'oz-news'),
            'delete_others_news' => __('Delete Others News', 'oz-news'),
            'read_private_news' => __('Read Private News', 'oz-news'),
            'manage_news_categories' => __('Manage News Categories', 'oz-news')
        );

        if($this->options['general']['use_tags'] === TRUE)
            $this->capabilities['manage_news_tags'] = __('Manage News Tags', 'oz-news');

        if($this->options['general']['use_categories'] === TRUE)
            $this->capabilities['manage_news_categories'] = __('Manage News Categories', 'oz-news');
    }


    /**
     * Adds link to Settings page
     */
    public function plugin_settings_link($links, $file)
    {
        if(!is_admin() || !current_user_can('manage_options'))
            return $links;

        static $plugin;

        $plugin = plugin_basename(__FILE__);

        if($file == $plugin)
        {
            $settings_link = sprintf('<a href="%s">%s</a>', admin_url('options-general.php').'?page=oz-news-options', __('Settings', 'oz-news'));
            array_unshift($links, $settings_link);
        }

        return $links;
    }


    /**
     * Adds options page as submenu to news
     */
    public function settings_page()
    {
        add_submenu_page('edit.php?post_type=news', __('Settings', 'oz-news'), __('Settings', 'oz-news'), 'manage_options', 'news-settings', array($this, 'options_page'));
    }


    /**
     *
     */
    public function options_page()
    {
        $tab_key = (isset($_GET['tab']) ? $_GET['tab'] : 'general');

        echo '
		<div class="wrap">'.screen_icon().'
			<h2>'.__('News Manager', 'oz-news').'</h2>
			<h2 class="nav-tab-wrapper">';

        foreach($this->tabs as $key => $name)
        {
            echo '
			<a class="nav-tab '.($tab_key == $key ? 'nav-tab-active' : '').'" href="'.esc_url(admin_url('edit.php?post_type=news&page=news-settings&tab='.$key)).'">'.$name['name'].'</a>';
        }

        echo '
			</h2>
			<div class="oz-news-settings">
			
				<div class="df-credits">
					<h3 class="hndle">'.__('12oz News', 'oz-news').' '.$this->defaults['version'].'</h3>

				</div>
			
				<form action="options.php" method="post">';

        wp_nonce_field('update-options');
        settings_fields($this->tabs[$tab_key]['key']);
        do_settings_sections($this->tabs[$tab_key]['key']);

        echo '
					<p class="submit">';

        submit_button('', 'primary', $this->tabs[$tab_key]['submit'], FALSE);

        echo ' ';

        if($this->tabs[$tab_key]['reset'] !== FALSE)
            submit_button(__('Reset to defaults', 'oz-news'), 'secondary', $this->tabs[$tab_key]['reset'], FALSE);

        echo '
					</p>
				</form>
			</div>
			<div class="clear"></div>
		</div>';
    }


    /**
     *
     */
    public function register_settings()
    {
        //general
        register_setting('oz_news_general', 'oz_news_general', array(&$this, 'validate_general'));
        add_settings_section('oz_news_general', __('General settings', 'oz-news'), '', 'oz_news_general');
        add_settings_field('nm_general_available_functions', __('News features support', 'oz-news'), array(&$this, 'nm_general_available_functions'), 'oz_news_general', 'oz_news_general');
        add_settings_field('nm_general_use_tags', __('Use Tags', 'oz-news'), array(&$this, 'nm_general_use_tags'), 'oz_news_general', 'oz_news_general');
        add_settings_field('nm_general_use_categories', __('Use Categories', 'oz-news'), array(&$this, 'nm_general_use_categories'), 'oz_news_general', 'oz_news_general');
        add_settings_field('nm_general_display_news_in_tags_and_categories', __('News in Tags & Categories', 'oz-news'), array(&$this, 'nm_general_display_news_in_tags_and_categories'), 'oz_news_general', 'oz_news_general');
        add_settings_field('nm_general_news_in_rss', __('News in RSS', 'oz-news'), array(&$this, 'nm_general_news_in_rss'), 'oz_news_general', 'oz_news_general');
        add_settings_field('nm_general_news_nav_menu', __('Show link in menu', 'oz-news'), array(&$this, 'nm_general_news_nav_menu'), 'oz_news_general', 'oz_news_general');
        add_settings_field('nm_general_first_weekday', __('First day of week', 'oz-news'), array(&$this, 'nm_general_first_weekday'), 'oz_news_general', 'oz_news_general');
        add_settings_field('nm_general_deactivation_delete', __('Delete plugin settings on deactivation', 'oz-news'), array(&$this, 'nm_general_deactivation_delete'), 'oz_news_general', 'oz_news_general');

        //capabilities
        register_setting('oz_news_capabilities', 'oz_news_capabilities', array(&$this, 'validate_capabilities'));
        add_settings_section('oz_news_capabilities', __('Capabilities settings', 'oz-news'), array(&$this, 'nm_capabilities_table'), 'oz_news_capabilities');

        //permalinks
        register_setting('oz_news_permalinks', 'oz_news_permalinks', array(&$this, 'validate_permalinks'));
        add_settings_section('oz_news_permalinks', __('Permalinks settings', 'oz-news'), '', 'oz_news_permalinks');
        add_settings_field('nm_permalinks_news', __('News', 'oz-news'), array(&$this, 'nm_permalinks_news'), 'oz_news_permalinks', 'oz_news_permalinks');

        if(($this->options['general']['use_tags'] === TRUE && $this->options['general']['builtin_tags'] === FALSE) || ($this->options['general']['use_categories'] === TRUE && $this->options['general']['builtin_categories'] === FALSE))
            add_settings_field('nm_permalinks_single_news_prefix', __('Single News prefix', 'oz-news'), array(&$this, 'nm_permalinks_single_news_prefix'), 'oz_news_permalinks', 'oz_news_permalinks');

        if($this->options['general']['use_tags'] === TRUE && $this->options['general']['builtin_tags'] === FALSE)
            add_settings_field('nm_permalinks_tags_news', __('News Tags', 'oz-news'), array(&$this, 'nm_permalinks_tags_news'), 'oz_news_permalinks', 'oz_news_permalinks');

        if($this->options['general']['use_categories'] === TRUE && $this->options['general']['builtin_categories'] === FALSE)
            add_settings_field('nm_permalinks_categories_news', __('News Categories', 'oz-news'), array(&$this, 'nm_permalinks_categories_news'), 'oz_news_permalinks', 'oz_news_permalinks');
    }


    /**
     *
     */
    public function nm_general_available_functions()
    {
        echo '
		<div id="nm_general_available_functions" class="wplikebtns">';

        foreach($this->supports as $val => $trans)
        {
            echo '
			<input id="nm-general-available-functions-'.$val.'" type="checkbox" name="oz_news_general[supports][]" value="'.esc_attr($val).'" '.checked(TRUE, $this->options['general']['supports'][$val], FALSE).' />
			<label for="nm-general-available-functions-'.$val.'">'.$trans.'</label>';
        }

        echo '
			<p class="description">'.__('Select which features would you like to enable for your news.', 'oz-news').'</p>
		</div>';
    }


    /**
     *
     */
    public function nm_general_news_nav_menu()
    {
        $menus = get_terms('nav_menu');

        echo '
		<div id="nm_general_news_nav_menu" class="wplikebtns">';

        foreach($this->choices as $val => $trans)
        {
            echo '
			<input id="nm-general-news-nav-menu-'.$val.'" type="radio" name="oz_news_general[news_nav_menu][show]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['general']['news_nav_menu']['show'], FALSE).' />
			<label for="nm-general-news-nav-menu-'.$val.'">'.$trans.'</label>';
        }

        echo '
			<p class="description">'.__('Select if you want to automatically add news archive link to nav menu.', 'oz-news').'</p>
			<div id="nm_news_nav_menu_opt"'.($this->options['general']['news_nav_menu']['show'] === FALSE ? ' style="display: none;"' : '').'>';

        if(!empty($menus))
        {
            echo '
				<label for="nm-news-nav-menu">'.__('Menu', 'oz-news').':</label>
				<select id="nm-news-nav-menu" name="oz_news_general[news_nav_menu][menu_id]">';

            foreach($menus as $menu)
            {
                echo '
					<option value="'.esc_attr($menu->term_id).'" '.selected($menu->term_id, $this->options['general']['news_nav_menu']['menu_id'], FALSE).'>'.$menu->name.'</option>';
            }

            echo '
				</select>
				<br />
				<label for="nm-news-nav-menu-title">'.__('Title', 'oz-news').':</label>
				<input id="nm-news-nav-menu-title" type="text" name="oz_news_general[news_nav_menu][menu_name]" value="'.esc_attr($this->options['general']['news_nav_menu']['menu_name']).'" />';
        }
        else
            echo '
				<p class="description">'.__('Note: there is no menu to which you could add news archive link.', 'oz-news').'</p>';

        echo '
			</div>
		</div>';
    }


    /**
     *
     */
    public function nm_general_first_weekday()
    {
        global $wp_locale;

        echo '
		<div id="nm_general_first_weekday">
			<select name="oz_news_general[first_weekday]">
				<option value="1" '.selected(1, $this->options['general']['first_weekday'], FALSE).'>'.$wp_locale->get_weekday(1).'</option>
				<option value="7" '.selected(7, $this->options['general']['first_weekday'], FALSE).'>'.$wp_locale->get_weekday(0).'</option>
			</select>
			<p class="description">'.__('Select preffered first day of the week for the calendar display.', 'oz-news').'</p>
		</div>';
    }


    /**
     *
     */
    public function nm_general_use_categories()
    {
        echo '
		<div id="nm_general_use_categories" class="wplikebtns">';

        foreach($this->choices as $val => $trans)
        {
            echo '
			<input id="nm-general-use-categories-'.$val.'" type="radio" name="oz_news_general[use_categories]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['general']['use_categories'], FALSE).' />
			<label for="nm-general-use-categories-'.$val.'">'.$trans.'</label>';
        }

        echo '
			<p class="description">'.__('Enable if you want to use News Categories.', 'oz-news').'</p>
		</div>
		<div id="nm_general_use_categories_opt" class="wplikebtns"'.($this->options['general']['use_categories'] === FALSE ? ' style="display: none;"' : '').'>';

        foreach($this->taxonomies['categories'] as $val => $trans)
        {
            echo '
			<input id="nm-general-use-'.$val.'-categories" type="radio" name="oz_news_general[builtin_categories]" value="'.esc_attr($val).'" '.checked(($val === 'builtin' ? TRUE : FALSE), $this->options['general']['builtin_categories'], FALSE).' />
			<label for="nm-general-use-'.$val.'-categories">'.$trans[0].'</label>
			<p class="description">'.$trans[1].'</p>';
        }

        echo '
		</div>';
    }


    /**
     *
     */
    public function nm_general_news_in_rss()
    {
        echo '
		<div id="nm_general_news_in_rss" class="wplikebtns">';

        foreach($this->choices as $val => $trans)
        {
            echo '
			<input id="nm-general-news-in-rss-'.$val.'" type="radio" name="oz_news_general[news_in_rss]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['general']['news_in_rss'], FALSE).' />
			<label for="nm-general-news-in-rss-'.$val.'">'.$trans.'</label>';
        }

        echo '
			<p class="description">'.__('Include news post type in main RSS feed.', 'oz-news').'</p>
		</div>';
    }


    /**
     *
     */
    public function nm_general_display_news_in_tags_and_categories()
    {
        echo '
		<div id="nm_general_display_news_in_tags_and_categories" class="wplikebtns">';

        foreach($this->choices as $val => $trans)
        {
            echo '
			<input id="nm-general-display-news-in-tags-and-categories-'.$val.'" type="radio" name="oz_news_general[display_news_in_tags_and_categories]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['general']['display_news_in_tags_and_categories'], FALSE).' '.disabled(TRUE, !(($this->options['general']['use_categories'] === TRUE && $this->options['general']['builtin_categories'] === TRUE) || ($this->options['general']['use_tags'] === TRUE && $this->options['general']['builtin_tags'] === TRUE)), FALSE).' />
			<label for="nm-general-display-news-in-tags-and-categories-'.$val.'">'.$trans.'</label>';
        }

        echo '
			<p class="description">'.__('By default Category and Tags archive pages include only default posts. Enable this if you want to display News (news post type) alongside posts.', 'oz-news').'</p>
		</div>';
    }


    /**
     *
     */
    public function nm_general_use_tags()
    {
        echo '
		<div id="nm_general_use_tags" class="wplikebtns">';

        foreach($this->choices as $val => $trans)
        {
            echo '
			<input id="nm-general-use-tags-'.$val.'" type="radio" name="oz_news_general[use_tags]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['general']['use_tags'], FALSE).' />
			<label for="nm-general-use-tags-'.$val.'">'.$trans.'</label>';
        }

        echo '
			<p class="description">'.__('Enable if you want to use News Tags.', 'oz-news').'</p>
		</div>
		<div id="nm_general_use_tags_opt" class="wplikebtns"'.($this->options['general']['use_tags'] === FALSE ? ' style="display: none;"' : '').'>';

        foreach($this->taxonomies['tags'] as $val => $trans)
        {
            echo '
			<input id="nm-general-use-'.$val.'-tags" type="radio" name="oz_news_general[builtin_tags]" value="'.esc_attr($val).'" '.checked(($val === 'builtin' ? TRUE : FALSE), $this->options['general']['builtin_tags'], FALSE).' />
			<label for="nm-general-use-'.$val.'-tags">'.$trans[0].'</label>
			<p class="description">'.$trans[1].'</p>';
        }

        echo '
		</div>';
    }


    /**
     *
     */
    public function nm_general_deactivation_delete()
    {
        echo '
		<div id="nm_general_deactivation_delete" class="wplikebtns">';

        foreach($this->choices as $val => $trans)
        {
            echo '
			<input id="nm-general-deactivation-delete-'.$val.'" type="radio" name="oz_news_general[deactivation_delete]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['general']['deactivation_delete'], FALSE).' />
			<label for="nm-general-deactivation-delete-'.$val.'">'.$trans.'</label>';
        }

        echo '
			<p class="description">'.__('Enable if you want all plugin data to be deleted on deactivation.', 'oz-news').'</p>
		</div>';
    }


    /**
     *
     */
    public function nm_permalinks_news()
    {
        $now = date_parse(current_time('mysql'));
        $now['month'] = str_pad($now['month'], 2, '0', STR_PAD_LEFT);

        echo '
		<div id="nm_permalinks_news">
			<input type="text" name="oz_news_permalinks[news_slug]" value="'.esc_attr($this->options['permalinks']['news_slug']).'" />
			<p class="description">
				<code>'.site_url().'/<strong>'.$this->options['permalinks']['news_slug'].'</strong>/</code>
				<br />
				<code>'.site_url().'/<strong>'.$this->options['permalinks']['news_slug'].'</strong>/<strong>'.$now['year'].'</strong>/</code>
				<br />
				<code>'.site_url().'/<strong>'.$this->options['permalinks']['news_slug'].'</strong>/<strong>'.$now['year'].'</strong>/<strong>'.$now['month'].'</strong>/</code>
				<br />
				<code>'.site_url().'/<strong>'.$this->options['permalinks']['news_slug'].'</strong>/<strong>'.$now['year'].'</strong>/<strong>'.$now['month'].'</strong>/<strong>'.str_pad($now['day'], 2, '0', STR_PAD_LEFT).'</strong>/</code>
				<br />
				'.__('General News root slug to prefix all your news pages with.', 'oz-news').'
			</p>
		</div>';
    }


    /**
     *
     */
    public function nm_permalinks_tags_news()
    {
        echo '
		<div id="nm_permalinks_tags_news">
			<input type="text" name="oz_news_permalinks[news_tags_rewrite_slug]" value="'.esc_attr($this->options['permalinks']['news_tags_rewrite_slug']).'" />
			<p class="description">
				<code>'.site_url().'/<strong>'.$this->options['permalinks']['news_slug'].'</strong>/<strong>'.$this->options['permalinks']['news_tags_rewrite_slug'].'</strong>/</code><br />
				'.__('News Tags archive page slug.', 'oz-news').'
			</p>
		</div>';
    }


    /**
     *
     */
    public function nm_permalinks_categories_news()
    {
        echo '
		<div id="nm_permalinks_categories_news">
			<input type="text" name="oz_news_permalinks[news_categories_rewrite_slug]" value="'.esc_attr($this->options['permalinks']['news_categories_rewrite_slug']).'" />
			<p class="description">
				<code>'.site_url().'/<strong>'.$this->options['permalinks']['news_slug'].'</strong>/<strong>'.$this->options['permalinks']['news_categories_rewrite_slug'].'</strong>/</code><br />
				'.__('News Categories archive page slug.', 'oz-news').'
			</p>
		</div>';
    }


    /**
     *
     */
    public function nm_permalinks_single_news_prefix()
    {
        echo '
		<div id="nm_permalinks_single_news_prefix" class="wplikebtns">';

        foreach($this->choices as $val => $trans)
        {
            echo '
			<input id="nm-permalinks-single-news-prefix-'.$val.'" type="radio" name="oz_news_permalinks[single_news_prefix]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['permalinks']['single_news_prefix'], FALSE).' '.disabled((($this->options['general']['use_categories'] === FALSE || $this->options['general']['builtin_categories'] === TRUE) && ($this->options['general']['use_tags'] === FALSE || $this->options['general']['builtin_tags'] === TRUE)), TRUE, FALSE).' />
			<label for="nm-permalinks-single-news-prefix-'.$val.'">'.$trans.'</label>';
        }

        echo '
			<p class="description">'.__('Do you want to prefix your news with the news category or news tag it belongs to.<br /><strong>Notice:</strong> This is an experimental feature and may not work on some permalink configurations.', 'oz-news').'</p>
		</div>
		<div id="nm_permalinks_single_news_prefix_opt" class="wplikebtns"'.($this->options['permalinks']['single_news_prefix'] === FALSE ? ' style="display: none;"' : '').'>';

        foreach($this->prefix_types as $val => $trans)
        {
            echo '
			<input id="nm-permalinks-single-news-prefix-type-'.$val.'" type="radio" name="oz_news_permalinks[single_news_prefix_type]" value="'.esc_attr($val).'" '.checked($val, $this->options['permalinks']['single_news_prefix_type'], FALSE).' '.disabled(((($this->options['general']['use_categories'] === FALSE || $this->options['general']['builtin_categories'] === TRUE) && $val === 'category') || (($this->options['general']['use_tags'] === FALSE || $this->options['general']['builtin_tags'] === TRUE) && $val === 'tag')), TRUE, FALSE).' />
			<label for="nm-permalinks-single-news-prefix-type-'.$val.'">'.$trans.'</label>';
        }

        echo '
			<br />
			<code id="nm_permalinks_single_news_prefix_code">'.site_url().'/<strong>'.$this->options['permalinks']['news_slug'].'</strong>/<strong>'.($this->options['permalinks']['single_news_prefix_type'] === 'tag' ? $this->options['permalinks']['news_tags_rewrite_slug'] : $this->options['permalinks']['news_categories_rewrite_slug']).'</strong>/news-title/</code><br />
		</div>';
    }


    /**
     *
     */
    public function nm_capabilities_table()
    {
        global $wp_roles;

        $built_in_roles = array('administrator', 'author', 'contributor', 'editor', 'subscriber');

        $html = '
		<table class="widefat fixed posts">
			<thead>
				<tr>
					<th>'.__('Role', 'oz-news').'</th>';

        foreach($built_in_roles as $role_name)
        {
            $html .= '<th>'.esc_html((isset($wp_roles->role_names[$role_name]) ? translate_user_role($wp_roles->role_names[$role_name]) : __('None', 'oz-news'))).'</th>';
        }

        $html .= '
				</tr>
			</thead>
			<tbody id="the-list">';

        $i = 0;

        $capabilities = $this->capabilities;

        if($this->options['general']['use_tags'] === TRUE)
        {
            if($this->options['general']['builtin_tags'] === TRUE)
                unset($capabilities['manage_news_tags']);

            if($this->options['general']['builtin_categories'] === TRUE)
                unset($capabilities['manage_news_categories']);
        }

        foreach($capabilities as $nm_role => $role_display)
        {
            $html .= '
				<tr'.(($i++ % 2 === 0) ? ' class="alternate"' : '').'>
					<td>'.esc_html(__($role_display, 'oz-news')).'</td>';

            foreach($built_in_roles as $role_name)
            {
                $role = $wp_roles->get_role($role_name);
                $html .= '
					<td>
						<input type="checkbox" name="oz_news_capabilities['.esc_attr($role->name).']['.esc_attr($nm_role).']" value="1" '.checked('1', $role->has_cap($nm_role), FALSE).' '.disabled($role->name, 'administrator', FALSE).' />
					</td>';
            }

            $html .= '
				</tr>';
        }

        $html .= '
			</tbody>
		</table>';

        echo $html;
    }


    /**
     * Validates capabilities settings
     */
    function validate_capabilities($input)
    {
        global $wp_roles;

        if(isset($_POST['save_nm_capabilities']))
        {
            foreach($wp_roles->roles as $role_name => $role_text)
            {
                $role = $wp_roles->get_role($role_name);

                if(!$role->has_cap('manage_options'))
                {
                    foreach($this->defaults['capabilities'] as $capability)
                    {
                        if(isset($input[$role_name][$capability]) && $input[$role_name][$capability] === '1')
                            $role->add_cap($capability);
                        else
                            $role->remove_cap($capability);
                    }
                }
            }

            set_transient($this->transient_id, maybe_serialize(array('status' => 'updated', 'text' => $this->errors['settings_caps_saved'])), 60);
        }
        elseif(isset($_POST['reset_nm_capabilities']))
        {
            foreach($wp_roles->roles as $role_name => $display_name)
            {
                $role = $wp_roles->get_role($role_name);

                foreach($this->defaults['capabilities'] as $capability)
                {
                    if($role->has_cap('manage_options'))
                        $role->add_cap($capability);
                    else
                        $role->remove_cap($capability);
                }
            }

            set_transient($this->transient_id, maybe_serialize(array('status' => 'updated', 'text' => $this->errors['settings_caps_reseted'])), 60);
        }

        return '';
    }


    /**
     * Validates or resets general settings
     */
    public function validate_general($input)
    {
        if(isset($_POST['save_nm_general']))
        {
            //rewrite rules
            $input['rewrite_rules'] = FALSE;

            //supports
            $supports = array();
            $input['supports'] = (isset($input['supports']) ? array_flip($input['supports']) : NULL);

            foreach($this->supports as $function => $trans)
            {
                $supports[$function] = (isset($input['supports'][$function]) ? TRUE : FALSE);
            }

            $input['supports'] = $supports;

            //weekday
            $input['first_weekday'] = (in_array($input['first_weekday'], array(1, 7)) ? (int)$input['first_weekday']: $this->defaults['general']['first_weekday']);

            //tags
            $input['use_tags'] = (isset($input['use_tags']) && in_array($input['use_tags'], array_keys($this->choices)) ? ($input['use_tags'] === 'yes' ? TRUE : FALSE) : $this->defaults['general']['use_tags']);

            if($input['use_tags'] === TRUE)
                $input['builtin_tags'] = (isset($input['builtin_tags']) && in_array($input['builtin_tags'], array_keys($this->taxonomies['tags'])) ? ($input['builtin_tags'] === 'builtin' ? TRUE : FALSE) : $this->defaults['general']['builtin_tags']);
            else
                $input['builtin_tags'] = $this->defaults['general']['builtin_tags'];

            if($input['builtin_tags'] === TRUE)
                $this->handle_capabilities(TRUE, 'manage_news_tags');
            else
                $this->handle_capabilities(FALSE, 'manage_news_tags', 'manage_options');

            //categories
            $input['use_categories'] = (isset($input['use_categories']) && in_array($input['use_categories'], array_keys($this->choices)) ? ($input['use_categories'] === 'yes' ? TRUE : FALSE) : $this->defaults['general']['use_categories']);

            if($input['use_categories'] === TRUE)
                $input['builtin_categories'] = (isset($input['builtin_categories']) && in_array($input['builtin_categories'], array_keys($this->taxonomies['categories'])) ? ($input['builtin_categories'] === 'builtin' ? TRUE : FALSE) : $this->defaults['general']['builtin_categories']);
            else
                $input['builtin_categories'] = $this->defaults['general']['builtin_categories'];

            if($input['builtin_categories'] === TRUE)
                $this->handle_capabilities(TRUE, 'manage_news_categories');
            else
                $this->handle_capabilities(FALSE, 'manage_news_categories', 'manage_options');

            //deactivation
            $input['deactivation_delete'] = (isset($input['deactivation_delete']) && in_array($input['deactivation_delete'], array_keys($this->choices)) ? ($input['deactivation_delete'] === 'yes' ? TRUE : FALSE) : $this->defaults['general']['deactivation_delete']);

            //news in rss
            $input['news_in_rss'] = (isset($input['news_in_rss']) && in_array($input['news_in_rss'], array_keys($this->choices)) ? ($input['news_in_rss'] === 'yes' ? TRUE : FALSE) : $this->defaults['general']['news_in_rss']);

            //display news in tags and categories
            $input['display_news_in_tags_and_categories'] = (isset($input['display_news_in_tags_and_categories']) && in_array($input['display_news_in_tags_and_categories'], array_keys($this->choices)) ? ($input['display_news_in_tags_and_categories'] === 'yes' ? TRUE : FALSE) : $this->defaults['general']['display_news_in_tags_and_categories']);

            //menu
            $input['news_nav_menu']['show'] = (isset($input['news_nav_menu']['show']) && in_array($input['news_nav_menu']['show'], array_keys($this->choices)) ? ($input['news_nav_menu']['show'] === 'yes' ? TRUE : FALSE) : $this->defaults['general']['news_nav_menu']['show']);

            $menu_failed = FALSE;
            $menus = get_terms('nav_menu');

            if($input['news_nav_menu']['show'] === TRUE && !empty($menus))
            {
                $input['news_nav_menu']['menu_id'] = (int)$input['news_nav_menu']['menu_id'];

                if(($input['news_nav_menu']['menu_name'] = sanitize_text_field($input['news_nav_menu']['menu_name'])) === '')
                {
                    $menu_failed = TRUE;

                    $input['news_nav_menu']['menu_id'] = 0;
                    $input['news_nav_menu']['item_id'] = 0;

                    set_transient($this->transient_id, maybe_serialize(array('status' => 'error', 'text' => $this->errors['empty_menu_name'])), 60);
                }
                else
                {
                    if(($menu_item = $this->update_menu($input['news_nav_menu']['menu_id'], $input['news_nav_menu']['menu_name'])) === FALSE)
                    {
                        $menu_failed = TRUE;

                        $input['news_nav_menu']['menu_id'] = 0;
                        $input['news_nav_menu']['item_id'] = 0;
                        $input['news_nav_menu']['menu_name'] = '';

                        set_transient($this->transient_id, maybe_serialize(array('status' => 'error', 'text' => $this->errors['no_such_menu'])), 60);
                    }
                    else
                        $input['news_nav_menu']['item_id'] = $menu_item;
                }
            }
            else
            {
                $input['news_nav_menu']['show'] = FALSE;
                $input['news_nav_menu']['menu_id'] = $this->defaults['general']['news_nav_menu']['menu_id'];
                $input['news_nav_menu']['menu_name'] = $this->defaults['general']['news_nav_menu']['menu_name'];
                $input['news_nav_menu']['item_id'] = $this->update_menu();
            }

            if($menu_failed === FALSE)
                set_transient($this->transient_id, maybe_serialize(array('status' => 'updated', 'text' => $this->errors['settings_gene_saved'])), 60);
        }
        elseif(isset($_POST['reset_nm_general']))
        {
            $input = $this->defaults['general'];

            //menu
            $input['news_nav_menu']['show'] = FALSE;
            $input['news_nav_menu']['menu_id'] = $this->defaults['general']['news_nav_menu']['menu_id'];
            $input['news_nav_menu']['menu_name'] = $this->defaults['general']['news_nav_menu']['menu_name'];
            $input['news_nav_menu']['item_id'] = $this->update_menu();

            set_transient($this->transient_id, maybe_serialize(array('status' => 'updated', 'text' => $this->errors['settings_gene_reseted'])), 60);
        }

        return $input;
    }


    /**
     * Validates permalinks settings
     */
    public function validate_permalinks($input)
    {
        if(isset($_POST['save_nm_permalinks']))
        {
            //slugs
            $input['news_slug'] = sanitize_title($input['news_slug']);

            if($this->options['general']['use_tags'] === TRUE)
            {
                if($this->options['general']['builtin_tags'] === FALSE)
                    $input['news_tags_rewrite_slug'] = sanitize_title(isset($input['news_tags_rewrite_slug']) ? $input['news_tags_rewrite_slug'] : $this->defaults['permalinks']['news_tags_rewrite_slug']);
                else
                    $input['news_tags_rewrite_slug'] = 'tag';
            }
            else
                $input['news_tags_rewrite_slug'] = $this->defaults['permalinks']['news_tags_rewrite_slug'];

            if($this->options['general']['use_categories'] === TRUE)
            {
                if($this->options['general']['builtin_tags'] === FALSE)
                    $input['news_categories_rewrite_slug'] = sanitize_title(isset($input['news_categories_rewrite_slug']) ? $input['news_categories_rewrite_slug'] : $this->defaults['permalinks']['news_categories_rewrite_slug']);
                else
                    $input['news_categories_rewrite_slug'] = 'category';
            }
            else
                $input['news_categories_rewrite_slug'] = $this->defaults['permalinks']['news_categories_rewrite_slug'];

            //prefix
            $input['single_news_prefix'] = (isset($input['single_news_prefix']) && in_array($input['single_news_prefix'], array_keys($this->choices)) ? ($input['single_news_prefix'] === 'yes' ? TRUE : FALSE) : $this->defaults['permalinks']['single_news_prefix']);

            //prefix type
            if($input['single_news_prefix'] === TRUE)
            {
                $input['single_news_prefix_type'] = (isset($input['single_news_prefix_type']) && in_array($input['single_news_prefix_type'], array_keys($this->prefix_types)) ? $input['single_news_prefix_type'] : $this->defaults['permalinks']['single_news_prefix_type']);
            }

            set_transient($this->transient_id, maybe_serialize(array('status' => 'updated', 'text' => $this->errors['settings_perm_saved'])), 60);
        }
        elseif(isset($_POST['reset_nm_permalinks']))
        {
            $input = $this->defaults['permalinks'];

            set_transient($this->transient_id, maybe_serialize(array('status' => 'updated', 'text' => $this->errors['settings_perm_reseted'])), 60);
        }

        $general_opts = get_option('oz_news_general');
        $general_opts['rewrite_rules'] = TRUE;

        update_option('oz_news_general', $general_opts);

        return $input;
    }


    /**
     * Adds new menu item to specified menu or removes it
     */
    function update_menu($menu_id = NULL, $menu_item_title = '')
    {
        $menu_item_id = $this->options['general']['news_nav_menu']['item_id'];

        if(is_nav_menu_item($menu_item_id))
        {
            $nav_menu_item = TRUE;

            if($menu_id === NULL)
            {
                wp_delete_post($menu_item_id, TRUE);
                $menu_item_id = 0;
            }
        }
        else
        {
            $nav_menu_item = FALSE;
            $menu_item_id = 0;
        }

        if(is_int($menu_id) && !empty($menu_id))
        {
            if(($menu = wp_get_nav_menu_object($menu_id)) === FALSE)
                return FALSE;

            $menu_id = $menu->term_id;
            $menu_item_data = array(
                'menu-item-title' => $menu_item_title,
                'menu-item-url' => nm_get_news_date_link(),
                'menu-item-object' => 'news',
                'menu-item-status' => ($menu_id == 0 ? '' : 'publish'),
                'menu-item-type' => 'custom'
            );

            if($nav_menu_item === TRUE)
            {
                $menu_item = wp_setup_nav_menu_item(get_post($menu_item_id));
                $menu_item_data['menu-item-parent-id'] = $menu_item->menu_item_parent;
                $menu_item_data['menu-item-position'] = $menu_item->menu_order;
            }

            $menu_item_id = wp_update_nav_menu_item($menu_id, $menu_item_id, $menu_item_data);
        }

        return $menu_item_id;
    }


    /**
     *
     */
    function handle_capabilities($remove = TRUE, $capability = '', $who = 'manage_options')
    {
        global $wp_roles;

        if($remove === TRUE)
        {
            foreach($wp_roles->roles as $role_name => $display_name)
            {
                $role = $wp_roles->get_role($role_name);
                $role->remove_cap($capability);
            }
        }
        else
        {
            foreach($wp_roles->roles as $role_name => $display_name)
            {
                $role = $wp_roles->get_role($role_name);

                if($role->has_cap($who))
                    $role->add_cap($capability);
            }
        }
    }
}
?>