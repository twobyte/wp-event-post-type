<?php
/*
Plugin Name: TT Event Post Type
Plugin URI: 
Description: Enables an event post type and taxonomies.
Version: 0.1
Author: Toby Trembath
Author URI: http://twobyte.com
License: GPLv2
*/

// Require the glancer class 
if ( is_admin() ) {

	// Loads for users viewing the WordPress dashboard
	if ( ! class_exists( 'Dashboard_Glancer' ) ) {
		require plugin_dir_path( __FILE__ ) . 'includes/class-dashboard-glancer.php';  // WP 3.8
	}

	
}

if ( ! class_exists( 'TT_Event_Post_Type' ) ) :

class TT_Event_Post_Type {

	// Current plugin version
	var $version = 0.7;

	function __construct() {

		// Runs when the plugin is activated
		register_activation_hook( __FILE__, array( &$this, 'plugin_activation' ) );

		// Add support for translations
		load_plugin_textdomain( 'eventposttype', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Adds the event post type and taxonomies
		add_action( 'init', array( &$this, 'event_init' ) );

		// Thumbnail support for event posts
		add_theme_support( 'post-thumbnails', array( 'event' ) );

		// Adds thumbnails to column view
		add_filter( 'manage_edit-event_columns', array( &$this, 'add_thumbnail_column'), 10, 1 );
		add_action( 'manage_posts_custom_column', array( &$this, 'display_thumbnail' ), 10, 1 );

		// Allows filtering of posts by taxonomy in the admin view
		add_action( 'restrict_manage_posts', array( &$this, 'add_taxonomy_filters' ) );

		// Show event post counts in the dashboard
		//add_action( 'right_now_content_table_end', array( &$this, 'add_event_counts' ) );

		// Give the event menu item a unique icon
		add_action( 'admin_head', array( &$this, 'event_icon' ) );
		
		// Add date and location meta boxes support
		add_action( 'admin_init', array( &$this, 'tt_eventposts_metaboxes') );
		add_action( 'save_post', array( &$this, 'tt_eventposts_save_meta'), 1, 2 );
		
		
		// Add custom CSS to style the metabox
		add_action('admin_print_styles-post.php', array( &$this, 'tt_eventposts_css'));
		add_action('admin_print_styles-post-new.php', array( &$this, 'tt_eventposts_css'));
		add_action( 'pre_get_posts', array( &$this, 'tt_event_query' ));
		
		
		add_action( 'dashboard_glance_items', array( &$this, 'add_glance_counts' ) );
	}

	/**
	 * Flushes rewrite rules on plugin activation to ensure event posts don't 404
	 * http://codex.wordpress.org/Function_Reference/flush_rewrite_rules
	 */

	function plugin_activation() {
		$this->event_init();
		flush_rewrite_rules();
	}

	function event_init() {

		/**
		 * Enable the Event custom post type
		 * http://codex.wordpress.org/Function_Reference/register_post_type
		 */

		$labels = array(
			'name' => __( 'Events', 'eventposttype' ),
			'singular_name' => __( 'Event', 'eventposttype' ),
			'add_new' => __( 'Add New Event', 'eventposttype' ),
			'add_new_item' => __( 'Add New Event', 'eventposttype' ),
			'edit_item' => __( 'Edit Event', 'eventposttype' ),
			'new_item' => __( 'Add New Event', 'eventposttype' ),
			'view_item' => __( 'View Event', 'eventposttype' ),
			'search_items' => __( 'Search Events', 'eventposttype' ),
			'not_found' => __( 'No events found', 'eventposttype' ),
			'not_found_in_trash' => __( 'No events found in trash', 'eventposttype' )
		);

		$args = array(
	    	'labels' => $labels,
	    	'public' => true,
			'supports' => array( 'title', 'editor', 'thumbnail', 'comments', 'revisions' ),
			'capability_type' => 'post',
			'rewrite' => array("slug" => "event"), // Permalinks format
			'menu_position' => 5,
			'menu_icon' => 'dashicons-nametag',
			'has_archive' => true
		);

		$args = apply_filters('eventposttype_args', $args);

		register_post_type( 'event', $args );

		/**
		 * Register a taxonomy for Event Tags
		 * http://codex.wordpress.org/Function_Reference/register_taxonomy
		 */
		 
		$taxonomy_event_tag_labels = array(
			'name' => __( 'Event Tags', 'eventposttype' ),
			'singular_name' => __( 'Event Tag', 'eventposttype' ),
			'search_items' => __( 'Search Event Tags', 'eventposttype' ),
			'popular_items' => __( 'Popular Event Tags', 'eventposttype' ),
			'all_items' => __( 'All Event Tags', 'eventposttype' ),
			'parent_item' => __( 'Parent Event Tag', 'eventposttype' ),
			'parent_item_colon' => __( 'Parent Event Tag:', 'eventposttype' ),
			'edit_item' => __( 'Edit Event Tag', 'eventposttype' ),
			'update_item' => __( 'Update Event Tag', 'eventposttype' ),
			'add_new_item' => __( 'Add New Event Tag', 'eventposttype' ),
			'new_item_name' => __( 'New Event Tag Name', 'eventposttype' ),
			'separate_items_with_commas' => __( 'Separate event tags with commas', 'eventposttype' ),
			'add_or_remove_items' => __( 'Add or remove event tags', 'eventposttype' ),
			'choose_from_most_used' => __( 'Choose from the most used event tags', 'eventposttype' ),
			'menu_name' => __( 'Event Tags', 'eventposttype' )
		);

		$taxonomy_event_tag_args = array(
			'labels' => $taxonomy_event_tag_labels,
			'public' => true,
			'show_in_nav_menus' => true,
			'show_ui' => true,
			'show_tagcloud' => true,
			'hierarchical' => false,
			'rewrite' => array( 'slug' => 'event-tags' ),
			'show_admin_column' => true,
			'query_var' => true
		);

		register_taxonomy( 'event-tags', array( 'event' ), $taxonomy_event_tag_args );
		
		/**
		 * Register a taxonomy for Event Categories
		 * http://codex.wordpress.org/Function_Reference/register_taxonomy
		 */

	    $taxonomy_event_category_labels = array(
			'name' => __( 'Event Categories', 'eventposttype' ),
			'singular_name' => __( 'Event Category', 'eventposttype' ),
			'search_items' => __( 'Search Event Categories', 'eventposttype' ),
			'popular_items' => __( 'Popular Event Categories', 'eventposttype' ),
			'all_items' => __( 'All Event Categories', 'eventposttype' ),
			'parent_item' => __( 'Parent Event Category', 'eventposttype' ),
			'parent_item_colon' => __( 'Parent Event Category:', 'eventposttype' ),
			'edit_item' => __( 'Edit Event Category', 'eventposttype' ),
			'update_item' => __( 'Update Event Category', 'eventposttype' ),
			'add_new_item' => __( 'Add New Event Category', 'eventposttype' ),
			'new_item_name' => __( 'New Event Category Name', 'eventposttype' ),
			'separate_items_with_commas' => __( 'Separate event categories with commas', 'eventposttype' ),
			'add_or_remove_items' => __( 'Add or remove event categories', 'eventposttype' ),
			'choose_from_most_used' => __( 'Choose from the most used event categories', 'eventposttype' ),
			'menu_name' => __( 'Event Categories', 'eventposttype' ),
	    );

	    $taxonomy_event_category_args = array(
			'labels' => $taxonomy_event_category_labels,
			'public' => true,
			'show_in_nav_menus' => true,
			'show_ui' => true,
			'show_admin_column' => true,
			//'show_tagcloud' => true,
			'hierarchical' => true,
			'rewrite' => array( 'slug' => 'events', 'hierarchical' => true ),
			'query_var' => true
	    );

	    register_taxonomy( 'events', array( 'event' ), $taxonomy_event_category_args );

	}

	/**
	 * Add Columns to Event Edit Screen
	 * http://wptheming.com/2010/07/column-edit-pages/
	 */

	function add_thumbnail_column( $columns ) {

		$column_thumbnail = array( 'thumbnail' => __('Thumbnail','eventposttype' ) );
		$columns = array_slice( $columns, 0, 2, true ) + $column_thumbnail + array_slice( $columns, 1, NULL, true );
		return $columns;
	}

	function display_thumbnail( $column ) {
		global $post;
		switch ( $column ) {
			case 'thumbnail':
				echo get_the_post_thumbnail( $post->ID, array(35, 35) );
				break;
		}
	}

	/**
	 * Adds taxonomy filters to the event admin page
	 * Code artfully lifed from http://pippinsplugins.com
	 */

	function add_taxonomy_filters() {
		global $typenow;

		// An array of all the taxonomyies you want to display. Use the taxonomy name or slug
		$taxonomies = array( 'events', 'event-tags' );
		//$taxonomies = array( 'events' );

		// must set this to the post type you want the filter(s) displayed on
		if ( $typenow == 'event' ) {

			foreach ( $taxonomies as $tax_slug ) {
				$current_tax_slug = isset( $_GET[$tax_slug] ) ? $_GET[$tax_slug] : false;
				$tax_obj = get_taxonomy( $tax_slug );
				$tax_name = $tax_obj->labels->name;
				$terms = get_terms($tax_slug);
				if ( count( $terms ) > 0) {
					echo "<select name='$tax_slug' id='$tax_slug' class='postform'>";
					echo "<option value=''>$tax_name</option>";
					foreach ( $terms as $term ) {
						echo '<option value=' . $term->slug, $current_tax_slug == $term->slug ? ' selected="selected"' : '','>' . $term->name .' (' . $term->count .')</option>';
					}
					echo "</select>";
				}
			}
		}
	}

	/**
	 * Add Event count to "Right Now" Dashboard Widget
	 */

	function add_event_counts() {
	        if ( ! post_type_exists( 'event' ) ) {
	             return;
	        }

	        $num_posts = wp_count_posts( 'event' );
	        $num = number_format_i18n( $num_posts->publish );
	        $text = _n( 'Event', 'Events', intval($num_posts->publish) );
	        if ( current_user_can( 'edit_posts' ) ) {
	            $num = "<a href='edit.php?post_type=event'>$num</a>";
	            $text = "<a href='edit.php?post_type=event'>$text</a>";
	        }
	        echo '<td class="first b b-event">' . $num . '</td>';
	        echo '<td class="t event">' . $text . '</td>';
	        echo '</tr>';

	        if ($num_posts->pending > 0) {
	            $num = number_format_i18n( $num_posts->pending );
	            $text = _n( 'Event Pending', 'Events Pending', intval($num_posts->pending) );
	            if ( current_user_can( 'edit_posts' ) ) {
	                $num = "<a href='edit.php?post_status=pending&post_type=event'>$num</a>";
	                $text = "<a href='edit.php?post_status=pending&post_type=event'>$text</a>";
	            }
	            echo '<td class="first b b-event">' . $num . '</td>';
	            echo '<td class="t event">' . $text . '</td>';

	            echo '</tr>';
	        }
	}

	/**
	 * Displays the custom post type icon in the dashboard
	 */
	function event_icon() { ?>
	    <style type="text/css" media="screen">
	    	#dashboard_right_now .event-count a:before, #dashboard_right_now .event-count span:before {
			    content: "\f484";
			}
	    </style>
	<?php }
	
	/****
	* Start event date meta stuff
	* Adds event post metaboxes for start time and end time
	* http://codex.wordpress.org/Function_Reference/add_meta_box
	*
	* We want two time event metaboxes, one for the start time and one for the end time.
	* Two avoid repeating code, we'll just pass the $identifier in a callback.
	* If you wanted to add this to regular posts instead, just swap 'event' for 'post' in add_meta_box.
	*/
	
	function tt_eventposts_metaboxes() {
		add_meta_box( 'tt_event_date_start', 'Start Date and Time', array( &$this, 'tt_event_date'), 'event', 'side', 'default', array( 'id' => '_start') );
		add_meta_box( 'tt_event_date_end', 'End Date and Time', array( &$this, 'tt_event_date'), 'event', 'side', 'default', array('id'=>'_end') );
		// add_meta_box( 'tt_event_location', 'Event Location', array( &$this, 'tt_event_location'), 'event', 'normal', 'default', array('id'=>'_end') );
	}
	
	// Metabox HTML
	
	function tt_event_date($post, $args) {
		$metabox_id = $args['args']['id'];
		global $post, $wp_locale;
		
		// Use nonce for verification
		wp_nonce_field( plugin_basename( __FILE__ ), 'tt_eventposts_nonce' );
		
		$time_adj = current_time( 'timestamp' );
		$month = get_post_meta( $post->ID, $metabox_id . '_month', true );
		
		if ( empty( $month ) ) {
			$month = gmdate( 'm', $time_adj );
		}
		
		$day = get_post_meta( $post->ID, $metabox_id . '_day', true );
		
		if ( empty( $day ) ) {
			$day = gmdate( 'd', $time_adj );
		}
		
		$year = get_post_meta( $post->ID, $metabox_id . '_year', true );
		
		if ( empty( $year ) ) {
			$year = gmdate( 'Y', $time_adj );
		}
		
		$hour = get_post_meta($post->ID, $metabox_id . '_hour', true);
		
		if ( empty($hour) ) {
		    $hour = gmdate( 'H', $time_adj );
		}
		
		$min = get_post_meta($post->ID, $metabox_id . '_minute', true);
		
		if ( empty($min) ) {
		    $min = '00';
		}
		
		$month_s = '<select name="' . $metabox_id . '_month">';
		for ( $i = 1; $i < 13; $i = $i +1 ) {
			$month_s .= "\t\t\t" . '<option value="' . zeroise( $i, 2 ) . '"';
			if ( $i == $month )
				$month_s .= ' selected="selected"';
			$month_s .= '>' . $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) . "</option>\n";
		}
		$month_s .= '</select>';
		
		echo $month_s;
		echo '<input type="text" name="' . $metabox_id . '_day" value="' . $day  . '" size="2" maxlength="2" />';
		echo '<input type="text" name="' . $metabox_id . '_year" value="' . $year . '" size="4" maxlength="4" /> @ ';
		echo '<input type="text" name="' . $metabox_id . '_hour" value="' . $hour . '" size="2" maxlength="2"/>:';
		echo '<input type="text" name="' . $metabox_id . '_minute" value="' . $min . '" size="2" maxlength="2" />';
		
	}
	
	/*function tt_event_location() {
		global $post;
		// Use nonce for verification
		wp_nonce_field( plugin_basename( __FILE__ ), 'tt_eventposts_nonce' );
		// The metabox HTML
		$event_location = get_post_meta( $post->ID, '_event_location', true );
		echo '<label for="_event_location">Location:</label>';
		echo '<input type="text" name="_event_location" value="' . $event_location  . '" />';
	}*/
	
	// Save the Metabox Data
	
	function tt_eventposts_save_meta( $post_id, $post ) {
	
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;
		
		if ( !isset( $_POST['tt_eventposts_nonce'] ) || !wp_verify_nonce( $_POST['tt_eventposts_nonce'], plugin_basename( __FILE__ ) ) )
			return;
		
		// Is the user allowed to edit the post or page?
		if ( !current_user_can( 'edit_post', $post->ID ) )
			return;
		
		// OK, we're authenticated: we need to find and save the data
		// We'll put it into an array to make it easier to loop though
		
		$metabox_ids = array( '_start', '_end' );
		
		foreach ($metabox_ids as $key ) {
		    
		    $aa = $_POST[$key . '_year'];
			$mm = $_POST[$key . '_month'];
			$jj = $_POST[$key . '_day'];
			$hh = $_POST[$key . '_hour'];
			$mn = $_POST[$key . '_minute'];
			
			$aa = ($aa <= 0 ) ? date('Y') : $aa;
			$mm = ($mm <= 0 ) ? date('n') : $mm;
			$jj = sprintf('%02d',$jj);
			$jj = ($jj > 31 ) ? 31 : $jj;
			$jj = ($jj <= 0 ) ? date('j') : $jj;
			$hh = sprintf('%02d',$hh);
			$hh = ($hh > 23 ) ? 23 : $hh;
			$mn = sprintf('%02d',$mn);
			$mn = ($mn > 59 ) ? 59 : $mn;
			
			$events_meta[$key . '_year'] = $aa;
			$events_meta[$key . '_month'] = $mm;
			$events_meta[$key . '_day'] = $jj;
			$events_meta[$key . '_hour'] = $hh;
			$events_meta[$key . '_minute'] = $mn;
		    $events_meta[$key . '_eventtimestamp'] = $aa . $mm . $jj . $hh . $mn;
		    
		}
	
		// Save Locations Meta
		// $events_meta['_event_location'] = $_POST['_event_location'];	
	
	
		// Add values of $events_meta as custom fields
		foreach ( $events_meta as $key => $value ) { // Cycle through the $events_meta array!
			if ( $post->post_type == 'revision' ) return; // Don't store custom data twice
			$value = implode( ',', (array)$value ); // If $value is an array, make it a CSV (unlikely)
			if ( get_post_meta( $post->ID, $key, FALSE ) ) { // If the custom field already has a value
				update_post_meta( $post->ID, $key, $value );
			} else { // If the custom field doesn't have a value
				add_post_meta( $post->ID, $key, $value );
			}
			if ( !$value ) delete_post_meta( $post->ID, $key ); // Delete if blank
		}
	
	}
	
	
	/**
	* Helpers to display the date on the front end
	*/
	
	// Get the Month Abbreviation
	
	function eventposttype_get_the_month_abbr($month) {
		global $wp_locale;
		for ( $i = 1; $i < 13; $i = $i +1 ) {
			if ( $i == $month )
				$monthabbr = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
		}
		return $monthabbr;
	}
		
	// Display the date
	
	function eventposttype_get_the_event_date() {
		global $post;
		$eventdate = '';
		$month = get_post_meta($post->ID, '_month', true);
		$eventdate = eventposttype_get_the_month_abbr($month);
		$eventdate .= ' ' . get_post_meta($post->ID, '_day', true) . ',';
		$eventdate .= ' ' . get_post_meta($post->ID, '_year', true);
		$eventdate .= ' at ' . get_post_meta($post->ID, '_hour', true);
		$eventdate .= ':' . get_post_meta($post->ID, '_minute', true);
		echo $eventdate;
	}
	
		
	function tt_eventposts_css() {
		wp_enqueue_style('your-meta-box', plugin_dir_url( __FILE__ ) . '/event-post-metabox.css');
	}
	
	/**
	* Customize Event Query using Post Meta
	* 
	* @link http://www.billerickson.net/customize-the-wordpress-query/
	* @param object $query data
	*
	*/
	function tt_event_query( $query ) {
		
		// http://codex.wordpress.org/Function_Reference/current_time
		$current_time = current_time('mysql'); 
		list( $today_year, $today_month, $today_day, $hour, $minute, $second ) = split( '([^0-9])', $current_time );
		$current_timestamp = $today_year . $today_month . $today_day . $hour . $minute;
		
		if ( $query->is_main_query() && !is_admin() && is_post_type_archive( 'event' ) ) {
			$meta_query = array(
				array(
					'key' => '_end_eventtimestamp',
					'value' => $current_timestamp,
					'compare' => '>'
				)
			);
			$query->set( 'meta_query', $meta_query );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'meta_key', '_start_eventtimestamp' );
			$query->set( 'order', 'ASC' );
			$query->set( 'posts_per_page', '2' );
		}
	
	}
	
	
	/****
	
	end event date meta stuff
	
	*****/
	
	
	/**
	 * Add counts to "At a Glance" dashboard widget in WP 3.8+
	 *
	 * @since 0.1.0
	 */
	public function add_glance_counts() {
		if ( class_exists( 'Dashboard_Glancer' ) ) {
			$glancer = new Dashboard_Glancer;
			$glancer->add( 'event', array( 'publish', 'pending' ) );
		}
	}
	
	
}

new TT_Event_Post_Type;

endif;
