<?php
/*
Plugin Name: RSS feeds for scheduled and draft posts
Description: Expose Wordpress RSS feeds for scheduled and draft posts

Add WordPress RSS Feed Retriever plugin:
https://wordpress.org/plugins/wp-rss-retriever/

Add Patreon plugin:
https://wordpress.org/plugins/patreon-connect/

Trying this:
https://www.wpbeginner.com/wp-tutorials/how-to-create-custom-rss-feeds-in-wordpress/
but it had copied code

try this:
https://philipnewcomer.net/2016/08/creating-custom-rss-feeds-wordpress-right-way/

also this for custom query:
https://gist.github.com/ocean90/4973288

example URL:
http://localhost:88/wordpress/?feed=next-scheduled&key=d355cb44-820b-4d75-a7c7-dde69130b64e
http://localhost:88/wordpress/?feed=scheduled&key=aafa2968-cfa3-451c-9269-db68a9804084
http://localhost:88/wordpress/?feed=drafts&key=a187db6e-97a1-49af-87d2-c6d90c78c94e

*/
/* Start Adding Functions Below this Line */

add_action('init', 'initDraftPlusScheduledPostRssFeeds');
function initDraftPlusScheduledPostRssFeeds() {
	add_feed('drafts', 'renderDraftOrScheduledPostRssFeed');
	add_feed('next-scheduled', 'renderDraftOrScheduledPostRssFeed');
	add_feed('scheduled', 'renderDraftOrScheduledPostRssFeed');
	add_action( 'pre_get_posts', 'setupDraftOrScheduledPostRssFeedContent' );
	add_filter( 'query_vars', 'addVarsForDraftOrScheduledPostRssFeed' );
}


function addVarsForDraftOrScheduledPostRssFeed( $vars ) {
	$vars[] = "key";
	return $vars;
}

/**
 * Customizes the query.
 * It will bail if $query is not an object, filters are suppressed and it's not our feed query.
 *
 * @param  WP_Query $query The current query
 */
function setupDraftOrScheduledPostRssFeedContent( $query ) {
	// Bail if $query is not an object or of incorrect class
	if ( ! $query instanceof WP_Query )
		return;

	// Bail if filters are suppressed on this query
	if ( $query->get( 'suppress_filters' ) )
		return;

	// Change params as needed
	if ($query->is_feed('next-scheduled' ) ) {
		$query->set( 'post_status', array('future') );
		$query->set( 'posts_per_rss', 1 );
		$query->set( 'nopaging', true );
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'ASC' );
	}

	if ($query->is_feed('drafts' ) ) {
		$query->set( 'post_status', array('draft') );
	}

	if ($query->is_feed('scheduled' ) ) {
		$query->set( 'post_status', array('future') );
	}
}

function renderDraftOrScheduledPostRssFeed($content_type, $type) {	
	$requestedSecurityKey = get_query_var('key');
	$keyToSecurityKeyOption = "DraftPlusScheduledPostRssFeeds-$type";
	$securityKey = get_option($keyToSecurityKeyOption);

	if (current_user_can( 'administrator' )) {
		
		if ($securityKey == '') {
			$securityKey = wp_generate_uuid4();
			echo "Added new security key";
			update_option($keyToSecurityKeyOption, $securityKey);
		}

		echo "Your authenticated URL is: ";
		self_link();
		echo "&key=$securityKey";
	} else if ($requestedSecurityKey != $securityKey || $requestedSecurityKey == ''){
		status_header(403);
		header("Content-Type: text/html");
		die("Forbidden");
	}

	add_filter( 'get_post_time', 'get_post_time_for_drafts', 10, 3 );
	add_filter( 'excerpt_more', 'custom_auto_excerpt_more' );
	add_filter( 'the_excerpt_rss', 'cleanup_excerpt' );

	if ( file_exists( ABSPATH . WPINC . '/feed-rss2.php' ) ) {
		require( ABSPATH . WPINC . '/feed-rss2.php' );
	}

	remove_filter( 'the_excerpt_rss', 'cleanup_excerpt' );
	add_filter( 'excerpt_more', 'custom_auto_excerpt_more' );
	remove_filter( 'get_post_time', 'get_post_time_for_drafts' );
}

function custom_auto_excerpt_more( $more ) {
    return '';
}

function get_post_time_for_drafts($time, $d = 'U', $gmt = false) {
	if (get_post_status() == 'draft') {
		return get_post_modified_time($d, $gmt);
	}
	return $time;
}

function cleanup_excerpt( $output ) {
	if ( !is_attachment() && function_exists("has_post_thumbnail") && has_post_thumbnail() && strpos($output, '<img src') == false ) {
		return $output . "<p>" . get_the_post_thumbnail(get_the_ID(), '500w', array("style" => "max-width: 500px; height: auto;")) . "</p>";
	}
	return $output;
}

/* Stop Adding Functions Below this Line */

?>