<?php

/**
 * Plugin Name: POD Marketing Analytics
 * Description: This adds the POD Marketing Analytics Portal integration to your website.
 * Version: 0.2.17
 * Author: jumpdemand
 * Author URI: http://www.JumpDEMAND.me
 * License:GPL-2.0+
 * License URI:http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace PodMarketing;

define(__NAMESPACE__.'\ACTIVEDEMAND_VER', '0.2.17');
define(__NAMESPACE__."\PLUGIN_VENDOR", "Pod Marketing");
define(__NAMESPACE__."\PLUGIN_VENDOR_LINK", "http://www.podmarketinginc.com/");
define(__NAMESPACE__."\PREFIX", 'pod');

include plugin_dir_path(__FILE__).'class-SCCollector.php';
include plugin_dir_path(__FILE__).'linked-forms.php';
include plugin_dir_path(__FILE__).'settings.php';


//--------------- AD update path --------------------------------------------------------------------------
function activedemand_update()
{

    //get ensure a cookie is set. This call creates a cookie if one does not exist
    activedemand_get_cookie_value();

    $key = PREFIX.'_version';
    $version = get_option($key);

    if (ACTIVEDEMAND_VER === $version) return;
    activedemand_plugin_activation();
    update_option($key, ACTIVEDEMAND_VER);


}

add_action('init', __NAMESPACE__.'\activedemand_update');



function activedemand_gutenberg_blocks()
{
    if (!function_exists('register_block_type')) {
        return false;
    }

    if (get_option(PREFIX.'_show_gutenberg_blocks', TRUE)) {
        $available_blocks = array(
            array(
                'label' => 'Select a block',
                'value' => 0
            )
        );

        $available_forms = array(
            array(
                'label' => 'Select a form',
                'value' => 0
            )        
        );

        $available_storyboard = array(
            array(
                'label' => 'Select a story board',
                'value' => 0
            )
        );

        if ( is_admin() ) {
            $blocks_cache_key = 'activedemand_blocks';
            $forms_cache_key = 'activedemand_forms';
            $storyboard_cache_key = 'activedemand_storyboard';

            $blocks = get_option($blocks_cache_key);
            $forms = get_option($forms_cache_key);
            $storyboard = get_option($storyboard_cache_key);

            if (!$blocks) {
                $url = "https://api.activedemand.com/v1/smart_blocks.json";
                $blocks = activedemand_getHTML($url, 10);
                update_option($blocks_cache_key, $blocks);
            }

            if (!$forms) {
                $url = "https://api.activedemand.com/v1/forms.json";
                $forms = activedemand_getHTML($url, 10);
                update_option($forms_cache_key, $forms);
            }

            if (!$storyboard) {
                $url = "https://api.activedemand.com/v1/dynamic_story_boards.json";
                $storyboard = activedemand_getHTML($url, 10);
                update_option($storyboard_cache_key, $storyboard);
            }

            $activedemand_blocks = json_decode($blocks);
            $activedemand_forms = json_decode($forms);
            $activedemand_storyboard = json_decode($storyboard);

            if (is_array($activedemand_blocks)) {
                foreach ($activedemand_blocks as $block) {
                    $available_blocks[] = array(
                        'label' => $block->name,
                        'value' => $block->id
                    );
                }
            }

            if (is_array($activedemand_forms)) {
                foreach ($activedemand_forms as $form) {
                    $available_forms[] = array(
                        'label' => $form->name,
                        'value' => $form->id
                    );
                }
            }

            if (is_array($activedemand_storyboard)) {
                foreach ($activedemand_storyboard as $storyboard) {
                    $available_storyboard[] = array(
                        'label' => $storyboard->name,
                        'value' => $storyboard->id
                    );
                }
            }
        }

        /*register js for dynamic blocks block*/
        wp_register_script(
            'pod_blocks',
            plugins_url( 'gutenberg-blocks/dynamic-content-blocks/block.build.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element' )
        );

        /*pass dynamic blocks list to js*/
        wp_localize_script( 'pod_blocks', 'activedemand_blocks', $available_blocks);

        /* pass vendor name to js*/
        wp_localize_script( 'pod_blocks', 'activedemand_vendor', array(PLUGIN_VENDOR));

        /*register gutenberg block for dynamic blocks*/
        register_block_type( 'pod/content-block', array(
            'attributes' => array(
                'block_id' => array(
                    'type' => 'number'                
                )
            ),
            'render_callback' => __NAMESPACE__.'\activedemand_render_dynamic_content_block',
            'editor_script' => 'pod_blocks',
        ));


        /*register js for forms block*/
        wp_register_script(
            'pod_forms',
            plugins_url( 'gutenberg-blocks/forms/block.build.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element' )
        );

        /*pass forms list to js*/
        wp_localize_script( 'pod_forms', 'activedemand_forms', $available_forms);
        
        /*register gutenberg block for forms*/
        register_block_type( 'pod/form', array(
            'attributes' => array(
                'form_id' => array(
                    'type' => 'number'                
                )
            ),
            'render_callback' => __NAMESPACE__.'\activedemand_render_form',
            'editor_script' => 'pod_forms'
        ));


         /*register js for storyboard block*/
        wp_register_script(
            'pod_storyboard',
            plugins_url( 'gutenberg-blocks/storyboard/block.build.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element' )
        );

        /*pass storyboard list to js*/
        wp_localize_script( 'pod_storyboard', 'activedemand_storyboard', $available_storyboard);

        /*register gutenberg block for storyboard*/
        register_block_type( 'pod/storyboard', array(
            'attributes' => array(
                'storyboard_id' => array(
                    'type' => 'number'
                )
            ),
            'render_callback' => __NAMESPACE__.'\activedemand_render_storyboard',
            'editor_script' => 'pod_storyboard'
        ));



        /*register gutenberg block category (ActiveDemand Blocks)*/
        add_filter( 'block_categories', __NAMESPACE__.'\activedemand_block_category', 10, 2);
    }
}

add_action('init', __NAMESPACE__.'\activedemand_gutenberg_blocks');

function activedemand_render_dynamic_content_block($params)
{
    $block_id = isset($params['block_id']) ? (int)$params['block_id'] : 0;
    if ($block_id) {
        return do_shortcode("[pod_block id='$block_id']");
    }
}

function activedemand_block_category( $categories, $post ) {
    return array_merge(
        $categories,
        array(
            array(
                'slug' => 'pod-blocks',
                'title' => PLUGIN_VENDOR.' '.__( 'Blocks', 'pod-blocks' ),
            ),
        )
    );
}

function activedemand_render_form($params)
{
    $form_id = isset($params['form_id']) ? (int)$params['form_id'] : 0;
    if ($form_id) {
        return do_shortcode("[pod_form id='$form_id']");
    }
}

function activedemand_render_storyboard($params)
{
    $storyboard_id = isset($params['storyboard_id']) ? (int)$params['storyboard_id'] : 0;
    if ($storyboard_id) {
        return do_shortcode("[pod_storyboard id='$storyboard_id']");
    }
}

//---------------Version Warning---------------------------//
/**function phpversion_warning_notice(){
    if(!((int)phpversion()<7)) return;
    $class='notice notice-warning is-dismissible';

    $message=(__(PLUGIN_VENDOR.' will deprecate PHP5 support soon -- we recommend updating to PHP7.'));
    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}
add_action('admin_notices', __NAMESPACE__.'\phpversion_warning_notice');
*/
//--------------- AD Server calls -------------------------------------------------------------------------

function activedemand_getHTML($url, $timeout, $args = array())
{
    $result = false;
    $fields_string = activedemand_field_string($args);
    $response = wp_remote_get($url."?".$fields_string,
        array(
            'timeout'   => $timeout,
            'sslverify' => false,
        )
    );

    if ( is_array($response) && isset($response['body']) && isset($response['response']['code']) && (int)$response['response']['code'] == 200 ) {
        $result = $response['body'];
    }

    return $result;
}

function activedemand_postHTML($url, $args, $timeout)
{
    $result = false;
    $fields_string = activedemand_field_string($args);
    $response = wp_remote_post(
        $url,
        array(
            'method'        => 'POST',
            'timeout'       => $timeout,
            'body'          => $fields_string,
            'sslverify'     => false            
        )
    );

    if ( is_array($response) && isset($response['body']) && isset($response['response']['code']) && (int)$response['response']['code'] == 200 ) {
        $result = $response['body'];
    }

    return $result;
}

/**
 * Adds ActiveDEMAND popups if API Key isset and activedemand_server_showpopups is true
 *
 * @param string $content
 * @return string $content with popup prefix
 */

function activedemand_api_key()
{
    $options = retrieve_activedemand_options();
    if (is_array($options) && array_key_exists(PREFIX.'_appkey', $options)) {
        $activedemand_appkey = $options[PREFIX."_appkey"];
    } else {
        $activedemand_appkey = "";
    }

    return $activedemand_appkey;
}

function activedemand_field_string($args, $api_key = '')
{

    $fields_string = "";
    $activedemand_appkey = activedemand_api_key();

    if ("" != $api_key) {
        $activedemand_appkey = $api_key;
    }

    if ("" != $activedemand_appkey) {

        $cookievalue = activedemand_get_cookie_value();
        $url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        if (isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        } else {
            $referrer = "";
        }
        if ($cookievalue != "") {
            $fields = array(
                'api-key' => $activedemand_appkey,
                'activedemand_session_guid' => activedemand_get_cookie_value(),
                'url' => $url,
                'ip_address' => activedemand_get_ip_address(),
                'referer' => $referrer,
                'user_agent' => isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : NULL
            );
        } else {
            $fields = array(
                'api-key' => $activedemand_appkey,
                'url' => $url,
                'ip_address' => activedemand_get_ip_address(),
                'referer' => $referrer,
                'user_agent' => isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : NULL
            );

        }
        if (is_array($args)) {
            $fields = array_merge($fields, $args);
        }
        $fields_string = http_build_query($fields);
    }

    return $fields_string;
}

add_action('init', __NAMESPACE__.'\activedemand_get_cookie_value');

function activedemand_get_cookie_value()
{
    //if (is_admin()) return "";

    static $cookieValue = "";

    if(!empty($cookieValue)) return $cookieValue;
        //not editing an options page etc.

        if (!empty($_COOKIE['activedemand_session_guid'])) {
            $cookieValue = $_COOKIE['activedemand_session_guid'];

        } else {
            $server_side = get_option(PREFIX.'_server_side', TRUE);;
            if($server_side){
                $urlParms = $_SERVER['HTTP_HOST'];
                if (NULL != $urlParms) {
                        $cookieValue = activedemand_get_GUID();
                        $basedomain = activedemand_get_basedomain();
                        setcookie('activedemand_session_guid', $cookieValue, time() + (60 * 60 * 24 * 365 * 10), "/", $basedomain);
                }
            }
        }

    return $cookieValue;
}


function activedemand_get_basedomain()
{
    $result = "";

    $urlParms = $_SERVER['HTTP_HOST'];
    if (NULL != $urlParms) {
        $result = str_replace('www.', "", $urlParms);
    }
    return $result;
}

// create a session if one doesn't exist
function activedemand_get_GUID()
{
    if (function_exists('com_create_guid')) {
        return com_create_guid();
    } else {
        mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
        return $uuid;
    }
}


// get the ip address
function activedemand_get_ip_address()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
    {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

//--------------- Admin Menu -------------------------------------------------------------------------
function activedemand_menu()
{
    global $activedemand_plugin_hook;
    $activedemand_plugin_hook = add_options_page(PLUGIN_VENDOR.' options', PLUGIN_VENDOR, 'manage_options', PREFIX.'_options', __NAMESPACE__.'\activedemand_plugin_options');
    add_action('admin_init', __NAMESPACE__.'\register_activedemand_settings');

}

function retrieve_activedemand_options(){
  $options = is_array(get_option(PREFIX.'_options_field'))? get_option(PREFIX.'_options_field') : array();
  $woo_options=is_array(get_option(PREFIX.'_woocommerce_options_field'))? get_option(PREFIX.'_woocommerce_options_field') : array();
  if(!empty($options) && !empty($woo_options)){
    return \array_merge($options, $woo_options);
  }
  return $options;
}

function register_activedemand_settings()
{
    register_setting(PREFIX.'_options', PREFIX.'_options_field');
    register_setting(PREFIX.'_woocommerce_options', PREFIX.'_woocommerce_options_field');
    register_setting(PREFIX.'_options', PREFIX.'_server_showpopups');
    register_setting(PREFIX.'_options', PREFIX.'_show_tinymce');
    register_setting(PREFIX.'_options', PREFIX.'_show_gutenberg_blocks');
    register_setting(PREFIX.'_options', PREFIX.'_server_side');
    register_setting(PREFIX.'_options', PREFIX.'_v2_script_url');

    register_setting(PREFIX.'_woocommerce_options', PREFIX.'_stale_cart_map');
    register_setting(PREFIX.'_woocommerce_options', PREFIX.'_wc_actions_forms');
}


function activedemand_enqueue_scripts()
{
    $script_url = get_option(PREFIX.'_v2_script_url');
    if (!isset($script_url) || "" == $script_url) {
        $activedemand_appkey = activedemand_api_key();
        if ("" != $activedemand_appkey) {
            $script_url = activedemand_getHTML("https://api.activedemand.com/v1/script_url", 10);
            update_option(PREFIX.'_v2_script_url', $script_url);

        }
    }
    if (!isset($script_url) || "" == $script_url) {
        $script_url = 'https://static.activedemand.com/public/javascript/ad.collect.min.js.jgz#adtoken';
    }
    wp_enqueue_script('ActiveDEMAND-Track', $script_url);
}


function activedemand_admin_enqueue_scripts()
{
    global $pagenow;

    if ('post.php' == $pagenow || 'post-new.php' == $pagenow) {
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');

    }
}

function activedemand_plugin_action_links($links, $file)
{
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page='.PREFIX.'_options">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}



function get_base_url()
{
    return plugins_url(null, __FILE__);
}

function activedemand_register_tinymce_javascript($plugin_array)
{
    $plugin_array['activedemand'] = plugins_url('/js/tinymce-plugin.js', __FILE__);
    return $plugin_array;
}


function activedemand_buttons()
{
    add_filter("mce_external_plugins", __NAMESPACE__.'\activedemand_add_buttons');
    add_filter('mce_buttons', __NAMESPACE__.'\activedemand_register_buttons');
}

function activedemand_add_buttons($plugin_array)
{
    $plugin_array['activedemand'] = get_base_url() . '/includes/activedemand-plugin.js';
    return $plugin_array;
}

function activedemand_register_buttons($buttons)
{
    array_push($buttons, 'insert_form_shortcode');
    return $buttons;
}


function activedemand_add_editor()
{
    global $pagenow;

    // Add html for shortcodes popup
    if ('post.php' == $pagenow || 'post-new.php' == $pagenow) {
        // echo "Including Micey!";
        include plugin_dir_path(__FILE__).'partials/tinymce-editor.php';
    }

}

function activedemand_clean_url($url)
{


    if (TRUE == strpos($url, '#adtoken'))
    {
        return str_replace('#adtoken', '', $url)."' defer='defer' async='async";
    }
    if (TRUE == strpos($url, '/load.js'))
    {
        return "$url' async defer";
    }

    return $url;

}

//Constant used to track stale carts
define(__NAMESPACE__.'\AD_CARTTIMEKEY', 'ad_last_cart_update');

/**
 * Adds cart timestamp to usermeta
 */
function activedemand_woocommerce_cart_update()
{
    $user_id = get_current_user_id();
    update_user_meta($user_id, AD_CARTTIMEKEY, time());
}

add_action('woocommerce_cart_updated', __NAMESPACE__.'\activedemand_woocommerce_cart_update');

/**
 * Deletes timestamp from current user meta
 */
function activedemand_woocommerce_cart_emptied()
{
    $user_id = get_current_user_id();
    delete_user_meta($user_id, AD_CARTTIMEKEY);
}

add_action('woocommerce_cart_emptied', __NAMESPACE__.'\activedemand_woocommerce_cart_emptied');

/**Periodically scans, and sends stale carts to activedemand
 *
 * @global object $wpdb
 *
 * @uses activedemand_send_stale_carts function to process and send
 */

function activedemand_woocommerce_scan_stale_carts()
{
    if(!class_exists('WooCommerce')) return;

    global $wpdb;
    $options = retrieve_activedemand_options();
    $hours = $options['woocommerce_stalecart_hours'];

    $stale_secs = $hours * 60 * 60;

    $carts = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $wpdb->usermeta . ' WHERE meta_key=%s', AD_CARTTIMEKEY));
    $blog_id = get_current_blog_id();

    $stale_carts = array();
    $i = 0;
    foreach ($carts as $cart) {
        if ((time() - (int)$cart->meta_value) > $stale_secs) {
            $stale_carts[$i]['user_id'] = $cart->user_id;
            $meta = get_user_meta($cart->user_id, '_woocommerce_persistent_cart', TRUE);
            if (empty($meta)) {
                $meta = get_user_meta($cart->user_id, '_woocommerce_persistent_cart_'.$blog_id, TRUE);
        }
            $stale_carts[$i]['cart'] = $meta;
            $i++;
    }
    }

    activedemand_send_stale_carts($stale_carts);
}

add_action(PREFIX.'_hourly', __NAMESPACE__.'\activedemand_woocommerce_scan_stale_carts');

register_activation_hook(__FILE__, __NAMESPACE__.'\activedemand_plugin_activation');

function activedemand_plugin_activation()
{
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $table_name = $wpdb->prefix . 'cart';

    $charset_collate = $wpdb->get_charset_collate();

    $cart_table_sql = "CREATE TABLE $table_name (
      `id_cart` int(10) NOT NULL AUTO_INCREMENT,
      `cookie_cart_id` varchar(32) NOT NULL,
      `id_customer` int(10) NOT NULL,
      `currency` varchar(32) NOT NULL,
      `language` varchar(32) NOT NULL,
      `date_add` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id_cart`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

    dbDelta( $cart_table_sql );

    $table_name_two = $wpdb->prefix . 'cart_product';

    $cart_product_table_sql = "CREATE TABLE $table_name_two (
      `id_cart` int(10) NOT NULL,
      `id_product` int(10) NOT NULL,
      `quantity` int(10) NOT NULL,
      `id_product_variation` int(10) NOT NULL,
      `date_add` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

    dbDelta( $cart_product_table_sql );

    if (!wp_next_scheduled(PREFIX.'_hourly')) wp_schedule_event(time(), 'hourly', PREFIX.'_hourly');
}

register_deactivation_hook(__FILE__, __NAMESPACE__.'\activedemand_plugin_deactivation');

function activedemand_plugin_deactivation()
{
    wp_clear_scheduled_hook(__NAMESPACE__.'\\'.PREFIX.'_hourly');
    wp_clear_scheduled_hook(PREFIX.'_hourly');
}

/**Processes and send stale carts
 * Delete the timestamp so carts are only used once
 *
 * @param array $stale_carts
 *
 * @used-by activedemand_woocommerce_scan_stale_carts
 * @uses    function _activedemand_send_stale cart to send each cart individually
 */
function activedemand_send_stale_carts($stale_carts)
{
  //$setting=get_setting(PREFIX.'_stale_cart_map');
  //$setting=get_option(PREFIX.'_stale_cart_map');

  $setting=get_option(PREFIX.'_form_'.PREFIX.'_stale_cart_map');

  if(!$setting || empty($setting)) return;
  if(!isset($setting['id']) || !isset($setting['map'])) return;
  $activedemand_form_id=$setting['id'];
  //$url="https://submit.activedemand.com/submit/form/$activedemand_form_id";
  $url="https://api.activedemand.com/v1/forms/$activedemand_form_id";
    foreach ($stale_carts as $cart) {
        $user = new \WP_User($cart['user_id']);
        $form_data=FormLinker::map_field_keys($setting['map'], array(
          'user'=>$user,
          'cart'=>$cart
        ));

        $response=wp_remote_post($url, array(
          'headers' => array(
            'x-api-key' => activedemand_api_key()
          ),
          'body'=>$form_data
        ));

        if(is_wp_error($response)){
          $msg=$response->get_error_message();
          new WP_Error($msg);
        }

        delete_user_meta($user->ID, AD_CARTTIMEKEY);
    }
}


add_filter('clean_url', __NAMESPACE__.'\activedemand_clean_url', 11, 1);
add_action('wp_enqueue_scripts', __NAMESPACE__.'\activedemand_enqueue_scripts');

add_action('admin_enqueue_scripts', __NAMESPACE__.'\activedemand_admin_enqueue_scripts');

add_action('admin_menu', __NAMESPACE__.'\activedemand_menu');
add_filter('plugin_action_links', __NAMESPACE__.'\activedemand_plugin_action_links', 10, 2);


//widgets
// add new buttons

if (get_option(PREFIX.'_show_tinymce', TRUE)) {
    add_action('init', __NAMESPACE__.'\activedemand_buttons');
    add_action('in_admin_footer', __NAMESPACE__.'\activedemand_add_editor');
}


/*
 * Include module for Landing Page delivery
 */

include plugin_dir_path(__FILE__).'landing-pages.php';

add_action('woocommerce_after_checkout_form', function(){
  echo <<<SNIP
  <script type="text/javascript">
    jQuery(document).ready(function($){
      $('script[src$="ad.collect.min.js.jgz"]').load(function(){
        AD.ready(function(){
            AD.flink();
          });
      });
    });
    </script>
SNIP;
});

function api_delete_post($request) 
{
    $parameters = $request->get_params();
    $post_id = $parameters['id'];

    if (!isset($parameters['api_key']) || $parameters['api_key'] != activedemand_api_key()) {
        return array('error' => 1, 'message' => 'Invalid Api Key');   
    }

    if (empty($parameters['id'])) {
        return array('error' => 1, 'message' => 'Post Id is empty');
    }

    if (wp_delete_post($post_id, true )) {
        return array('error' => 0);
    } else {
        return array('error' => 1); 
    }
}

function api_save_post($request) 
{
    $success = false;
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $parameters = $request->get_params();
    
    if (!isset($parameters['api_key']) || $parameters['api_key'] != activedemand_api_key()) {
       return array('error' => 1, 'message' => 'Invalid Api Key');   
    }
    //create slug from title when slug is empty
    $parameters['slug'] = empty($parameters['slug']) ? sanitize_title($parameters['title']) : $parameters['slug'];

    if (empty($parameters['title']) || empty($parameters['content']) || empty($parameters['slug'])) {
        return array('error' => 1, 'message' => 'Invalid request');        
    }
    $category = get_cat_ID($parameters['categories']);

    $post = array(
        'post_type' => 'post',
        'post_title' =>  $parameters['title'],
        'post_content' => $parameters['content'],
        'post_status' => 'draft',
        'post_author' => 0,
        'post_date' => $parameters['date'],
        'post_slug' => $parameters['slug'],
        'post_excerpt'=> $parameters['excerpt'],
        'post_category' => array($category),
        'tags_input' => $parameters['tags']
    );

    if (isset($parameters['id']) && $post_id = $parameters['id']) {        
        $post['ID'] = $parameters['id'];
        if (isset($post['post_status']) && !empty($post['post_status'])) {
            $post['post_status'] = $parameters['status'];
        }
        $success = wp_update_post( $post );           
    } else {
        if ($post_id = wp_insert_post($post)) {
            $success = true;
        }
    }

    $image_url = $parameters['thumbnail_url'];
    if (!empty($image_url)) {
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url); 
        $filename   = basename( $image_url);
        if ( wp_mkdir_p( $upload_dir['path'] ) ) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
        file_put_contents( $file, $image_data );
        $wp_filetype = wp_check_filetype( $filename, null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name( $filename ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        set_post_thumbnail( $post_id, $attach_id ); 
    }
  
    if ($post_id && $success) {
        return array('error' => 0, 'id' => $post_id, 'slug' => $post['post_slug']);
    } else {
       return  array('error' => 1);
    }   
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'activedemand/v1', '/create-post/', array(
        'methods' => 'POST',
        'callback' => __NAMESPACE__.'\api_save_post',
        'permission_callback' => '__return_true'
    ) );

    register_rest_route( 'activedemand/v1', '/update-post/', array(
        'methods' => 'POST',
        'callback' => __NAMESPACE__.'\api_save_post',
        'permission_callback' => '__return_true'
    ) );

    register_rest_route( 'activedemand/v1', '/delete-post/', array(
        'methods' => 'POST',
        'callback' => __NAMESPACE__.'\api_delete_post',
        'permission_callback' => '__return_true'
    ) );

} );

function set_active_demand_cookie() {
    if ( ! isset( $_COOKIE['active_demand_cookie_cart'] ) ) {
        setcookie( 'active_demand_cookie_cart', uniqid(), time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
    }
}
add_action( 'init', __NAMESPACE__.'\set_active_demand_cookie');

function activedemand_save_add_to_cart() {
    global $wpdb;

    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $id_product = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        $variation_id = $cart_item['variation_id'];

        $user_id = get_current_user_id();
        $lang = get_bloginfo("language");
        $currency = get_option('woocommerce_currency');
        $active_demand_cookie_cart = $_COOKIE['active_demand_cookie_cart'];
        $cart_link = esc_url( plugins_url( 'recover-cart.php?cart-key="'.$_COOKIE['active_demand_cookie_cart'].'"', __FILE__ ) );

        $id_cart = $wpdb->get_var('SELECT id_cart FROM '.$wpdb->prefix.'cart WHERE id_customer = '.(int)$user_id.' AND cookie_cart_id = "'.$_COOKIE['active_demand_cookie_cart'].'"');

        $cart_product_id = $wpdb->get_var('SELECT cp.id_cart FROM '.$wpdb->prefix.'cart_product cp LEFT JOIN '.$wpdb->prefix.'cart c ON cp.id_cart = c.id_cart WHERE cp.id_product = '.(int)$id_product.' AND cp.id_product_variation = '.(int)$variation_id.' AND c.cookie_cart_id = "'.$_COOKIE['active_demand_cookie_cart'].'"');

        $current_url = home_url($_SERVER['REQUEST_URI']);

        if(strpos($current_url, 'cart-key') == false) {

            if(!$id_cart) {
                $save_cart_details = array(
                    'cookie_cart_id' => $_COOKIE['active_demand_cookie_cart'],
                    'id_customer' => $user_id,
                    'currency' => $currency,
                    'language' => $lang,
                    'date_add' => current_time( 'mysql' ),

                );

                $wpdb->insert($wpdb->prefix . "cart", $save_cart_details );
            }

            $cart_id = $wpdb->get_var('SELECT id_cart FROM '.$wpdb->prefix.'cart ORDER BY id_cart DESC LIMIT 1');

            if(!$cart_product_id) {
                $cart_products = array(
                    'id_cart' => isset($id_cart) ? $id_cart : $cart_id,
                    'id_product' => $id_product,
                    'quantity' => $quantity,
                    'id_product_variation' => $variation_id,
                    'date_add' => current_time( 'mysql' ),
                );
                $wpdb->insert($wpdb->prefix . "cart_product", $cart_products );
            }
            else {
                $wpdb->query("UPDATE ".$wpdb->prefix."cart_product SET quantity = ".$quantity." WHERE  id_product = ".$id_product.' AND id_product_variation = '.(int)$variation_id.' AND id_cart = '.$id_cart);
            }
        }
    }
}
add_action( 'woocommerce_add_to_cart', __NAMESPACE__.'\activedemand_save_add_to_cart', 10, 2 );

//delete cookie
function activedemand_delete_cookie_cart($order_id)
{
    setcookie( 'active_demand_cookie_cart', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
}

add_action('woocommerce_thankyou', __NAMESPACE__.'\activedemand_delete_cookie_cart');
