<?php
/**
 * Plugin Name: WordPress RSS Feed Retriever
 * Plugin URI: https://wordpress.org/plugins/wp-rss-retriever/
 * Description: A lightweight RSS feed plugin which uses shortcode to fetch and display an RSS feed including thumbnails and an excerpt.
 * Version: 1.3.1
 * Author: Theme Mason
 * Author URI: https://thememason.com/
 * Text Domain: wp-rss-retriever
 * Domain Path: /languages
 * License: GPL2
 */

// Global variables
define( 'WP_RSS_RETRIEVER_VER', '1.3.1' );

include( plugin_dir_path( __FILE__ ) . 'inc/welcome-screen.php');

add_action( 'wp_enqueue_scripts', 'wp_rss_retriever_css');

function wp_rss_retriever_css() {
    wp_enqueue_style('rss-retriever', plugin_dir_url( __FILE__) . 'inc/css/rss-retriever.css', $deps = false, $ver = WP_RSS_RETRIEVER_VER);
}

add_shortcode( 'wp_rss_retriever', 'wp_rss_retriever_func' );

function wp_rss_retriever_func( $atts, $content = null ){
	extract( shortcode_atts( array(
		'url' => '#',
		'items' => '10',
        'orderby' => 'default',
        'title' => 'true',
        'rssfield' => 'description',
		'excerpt' => '0',
		'read_more' => 'true',
		'new_window' => 'true',
        'thumbnail' => 'false',
        'source' => 'true',
        'label' => 'Published',
        'date' => 'true',
        'cache' => '43200',
        'dofollow' => 'false',
        'credits' => 'false'
	), $atts ) );

    update_option( 'wp_rss_cache', $cache );

    //multiple urls
     if( strpos($url, ',') !== false ) {
        $urls = explode(',', $url);
     } else {
        $urls = $url;
     }

    add_filter( 'wp_feed_cache_transient_lifetime', 'wp_rss_retriever_cache' );

    $rss = fetch_feed( $urls );

    remove_filter( 'wp_feed_cache_transient_lifetime', 'wp_rss_retriever_cache' );

    if ( !is_wp_error( $rss ) ) {
        if ($orderby == 'date' || $orderby == 'date_reverse') {
            $rss->enable_order_by_date(true);
        }
        $maxitems = $rss->get_item_quantity( $items ); 
        $rss_items = $rss->get_items( 0, $maxitems );
        if ( $new_window != 'false' ) {
            $newWindowOutput = 'target="_blank" ';
        } else {
            $newWindowOutput = NULL;
        }

        if ($orderby == 'date_reverse') {
            $rss_items = array_reverse($rss_items);
        }

        if ($orderby == 'random') {
            shuffle($rss_items);
        }
    }

    $output = '<div class="wp_rss_retriever">';
        $output .= '<ul class="wp_rss_retriever_list">';
            if ( !isset($maxitems) ) : 
                $output .= '<li>' . __( 'No items', 'wp-rss-retriever' ) . '</li>';
            else : 
                //loop through each feed item and display each item.
                foreach ( $rss_items as $item ) :
                    //variables
					if ($rssfield == 'content') {
						$content = $item->get_content();
					} else {
						$content = $item->get_description();
					}
                    $the_title = $item->get_title();
                    $enclosure = $item->get_enclosure();

                    //build output
                    $output .= '<li class="wp_rss_retriever_item"><div class="wp_rss_retriever_item_wrapper">';
                        //title
                        if ($title == 'true') {
                          //  $output .= '<a class="wp_rss_retriever_title" ' . $newWindowOutput . 'href="' . esc_url( $item->get_permalink() ) . '"' .
                           //     ($dofollow === 'false' ? ' rel="nofollow" ' : '') .
                             //   'title="' . $the_title . '">';
                                $output .= "<h3>" . $the_title . "</h3>";
                            //$output .= '</a>';   
                        }
                        //metadata
                        if ($source == 'true' || $date == 'true') {
                            $output .= '<div class="wp_rss_retriever_metadata">';
                                $source_title = $item->get_feed()->get_title();
                                $time = wp_rss_retriever_convert_timezone($item->get_date());

                                if ($source == 'true' && $source_title) {
                                    $output .= '<span class="wp_rss_retriever_source">' . sprintf( __( 'Site', 'wp-rss-retriever' ) . ': %s', $source_title ) . '</span>';
                                }
                                if ($source == 'true' && $date == 'true') {
                                    $output .= ' | ';
                                }
                                if ($date == 'true' && $time) {
                                    $output .= '<span class="wp_rss_retriever_date">' . sprintf( __( $label, 'wp-rss-retriever' ) . ': %s', $time ) . '</span>';
                                }
                            $output .= '</div>';
                        }
						$output .= '<blockquote>';
                        //thumbnail
                        if ($thumbnail != 'false' && $enclosure) {
                            $thumbnail_image = $enclosure->get_thumbnail();                     
                            if ($thumbnail_image) {
                                //use thumbnail image if it exists
                                $resize = wp_rss_retriever_resize_thumbnail($thumbnail);
                                $class = "";// wp_rss_retriever_get_image_class($thumbnail_image);
                                $output .= '<div class="wp_rss_retriever_image_mw"' . $resize . '><img' . $class . ' src="' . $thumbnail_image . '" alt="' . $title . '"></div>';
                            } else {
                                //if not than find and use first image in content
                                preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $content, $first_image);
                                if ($first_image){    
                                    $resize = wp_rss_retriever_resize_thumbnail($thumbnail);                                
                                    $class = wp_rss_retriever_get_image_class($first_image["src"]);
                                    $output .= '<div class="wp_rss_retriever_image_mw"' . $resize . '><img' . $class . ' src="' . $first_image["src"] . '" alt="' . $title . '"></div>';
                                }
                            }
                        }
                        //content
                        $output .= '<div class="wp_rss_retriever_container">';
                        if ( $excerpt != 'none' ) {
                            if ( $excerpt > 0 ) {
                                $output .= wp_trim_words(wp_strip_all_tags($content), $excerpt);
                            } else {
                                $output .= ($content);
                            }
                            if( $read_more == 'true' ) {
                                $output .= ' <a class="wp_rss_retriever_readmore" ' . $newWindowOutput . 'href="' . esc_url( $item->get_permalink() ) . '"' .
                                        ($dofollow === 'false' ? ' rel="nofollow" ' : '') .
                                        'title="' . sprintf( __( 'Posted', 'wp-rss-retriever' ) . ' %s', wp_rss_retriever_convert_timezone($item->get_date()) ) . '">';
                                        $output .= __( 'Read more', 'wp-rss-retriever' ) . '&nbsp;&raquo;';
                                $output .= '</a>';
                            }
                        }
                    $output .= '</div><blockquote></div></li>';
                endforeach;
            endif;
        $output .= '</ul>';
        if ($credits == 'true') {
            $output .= wp_rss_retriever_get_credits();
        }
    $output .= '</div>';

    return $output;
}

add_option( 'wp_rss_cache', 43200 );

function wp_rss_retriever_cache() {
    //change the default feed cache
    $cache = get_option( 'wp_rss_cache', 43200 );
    return $cache;
}

function wp_rss_retriever_get_image_class($image_src) {
    return ' class="portrait"';
}

function wp_rss_retriever_resize_thumbnail($thumbnail) {
    if ($thumbnail){
        // check if $thumbnail contains width and height separated by x
        if (strpos($thumbnail, 'x') !== false) {
            list($thumbnail_width, $thumbnail_height) = explode('x', $thumbnail);
        } else {
            $thumbnail_width = $thumbnail;
            $thumbnail_height = $thumbnail;
        }

        $resize = ' style="padding-bottom: 20px; max-width:' . $thumbnail_width . 'px;"';
    } else {
        $resize = '';
    }
    return $resize;
}

function wp_rss_retriever_get_credits() {
    $lang = array(
        'Theme Mason'                   => __('Theme Mason', 'wp-rss-retriever'),
        'thememason.com'                => __('thememason.com', 'wp-rss-retriever'),
        'WordPress RSS Feed Retriever'  => __('WordPress RSS Feed Retriever', 'wp-rss-retriever'),
        'WordPress RSS Feed'            => __('WordPress RSS Feed', 'wp-rss-retriever'),
        'WordPress RSS'                 => __('WordPress RSS', 'wp-rss-retriever'),
        'WordPress Feed'                => __('WordPress Feed', 'wp-rss-retriever'),
        'RSS Feed WordPress'            => __('RSS Feed WordPress', 'wp-rss-retriever'),
        'WordPress RSS Feed Plugin'     => __('WordPress RSS Feed Plugin', 'wp-rss-retriever'),
        'RSS Feed Aggregator'           => __('RSS Feed Aggregator', 'wp-rss-retriever'),
        'RSS Aggregator'                => __('RSS Aggregator', 'wp-rss-retriever'),
        'RSS Feed Plugin'               => __('RSS Feed Plugin', 'wp-rss-retriever'),
        'Custom RSS Feed'               => __('Custom RSS Feed', 'wp-rss-retriever'),
        'Custom News Feed'              => __('Custom News Feed', 'wp-rss-retriever'),
        'Powered'                       => __('Powered', 'wp-rss-retriever'),
        'by'                            => __('by', 'wp-rss-retriever'),
    );

    $plugin = array(
        array('19'  => wp_rss_retriever_concat_credit($lang['WordPress RSS Feed Retriever'] . ' ' . $lang['by'], $lang['Theme Mason'])),
        array('10'  => wp_rss_retriever_concat_credit($lang['WordPress RSS Feed'] . ' ' . $lang['by'], $lang['Theme Mason'])),
        array('9'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['Theme Mason'])),
        array('9'   => wp_rss_retriever_concat_credit($lang['WordPress RSS Feed Retriever'] . ' ' . $lang['by'], $lang['thememason.com'])),
        array('5'   => wp_rss_retriever_concat_credit($lang['WordPress RSS Feed'] . ' ' . $lang['by'], $lang['thememason.com'])),
        array('17'  => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['WordPress RSS Feed Retriever'])),
        array('7'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['WordPress RSS Feed'])),
        array('2'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['WordPress RSS'])),
        array('1'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['WordPress Feed'])),
        array('2'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['RSS Feed WordPress'])),
        array('5'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['WordPress RSS Feed Plugin'])),
        array('4'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['RSS Feed Aggregator'])),
        array('1'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['RSS Aggregator'])),
        array('4'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['RSS Feed Plugin'])),
        array('3'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['Custom RSS Feed'])),
        array('2'   => wp_rss_retriever_concat_credit($lang['Powered'] . ' ' . $lang['by'], $lang['Custom News Feed'])),
    );

    $newPlugin = array();
    foreach ($plugin as $array) {
        $newPlugin = array_merge($newPlugin, array_fill(0, key($array), $array[key($array)]));
    }

    mt_srand(crc32(get_bloginfo('url')));
    $num = mt_rand(0, count($newPlugin) - 1);

    $output  = '<div class="wp_rss_retriever_credits">';
        $output .= $newPlugin[$num];
    $output .= '</div>';

    return $output;
}

function wp_rss_retriever_concat_credit($prepend, $title) {
    $url = 'https://thememason.com/plugins/rss-retriever/';
    return $prepend . ' <a href="' . $url . '" title="' . $title . '">' . $title . '</a>';
}

function wp_rss_retriever_convert_timezone($timestamp) {
    $date = new DateTime($timestamp);

    // Timezone string set (ie: America/New York)
    if (get_option('timezone_string')) {
        $tz = get_option('timezone_string');
    // GMT offset string set (ie: -5). Convert value to timezone string
    } elseif (get_option('gmt_offset')) {
        $tz = timezone_name_from_abbr('', get_option('gmt_offset') * 3600, 0 );
    } else {
        $tz = 'GMT';
    }

    try {
        $date->setTimezone(new DateTimeZone($tz)); 
    } catch (Exception $e) {
        $date->setTimezone(new DateTimeZone('GMT')); 
    }

    return date_i18n(get_option('date_format') .' - ' . get_option('time_format'), strtotime($date->format('Y-m-d H:i:s')));
}

function wp_rss_retriever_load_textdomain() {
  load_plugin_textdomain( 'wp-rss-retriever', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}

add_action( 'init', 'wp_rss_retriever_load_textdomain' );



register_activation_hook( __FILE__, 'wp_rss_retriever_activate' );
function wp_rss_retriever_activate() {
  set_transient( '_wp_rss_retriever_activation_redirect', true, 30 );
}


// add action link under plugins list
function wp_rss_retriever_add_action_links ( $links ) {
  $mylinks = array(
    '<a href="' . admin_url( 'index.php?page=wp-rss-retriever-welcome' ) . '">Get Started</a>',
  );
  return array_merge( $links, $mylinks );
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wp_rss_retriever_add_action_links' );