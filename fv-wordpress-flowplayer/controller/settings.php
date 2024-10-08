<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/*
 *  Admin menus and such...
 */
add_action('admin_menu', 'fv_player_admin_menu');

function fv_player_admin_menu() {
  if( function_exists('add_submenu_page') ) {
    add_options_page( 'FV Player', 'FV Player', 'manage_options', 'fvplayer', 'fv_player_admin_page' );
  }
}




function fv_player_admin_page() {
  include dirname( __FILE__ ) . '/../view/admin.php';
}




function fv_player_is_admin_screen() {
  if( (isset($_GET['page']) && $_GET['page'] == 'fvplayer') || apply_filters('fv_player_is_admin_screen', false) ) {
    return true;
  }
  return false;
}




function fv_player_stats_page() {
  include dirname( __FILE__ ) . '/../view/stats.php';
}




add_filter('plugin_action_links', 'fv_wp_flowplayer_plugin_action_links', 10, 2);

function fv_wp_flowplayer_plugin_action_links($links, $file) {
  if( $file == 'fv-wordpress-flowplayer/flowplayer.php') {
    $settings_link = '<a href="https://foliovision.com/pro-support" target="_blank">Premium Support</a>';
    array_unshift($links, $settings_link);
    $settings_link = '<a href="options-general.php?page=fvplayer">Settings</a>';
    array_unshift($links, $settings_link);
  }
  return $links;
}




add_action( 'after_plugin_row', 'fv_wp_flowplayer_after_plugin_row', 10, 3 );

function fv_wp_flowplayer_after_plugin_row( $arg) {
  if( apply_filters('fv_player_skip_ads',false) ) {
    return;
  }
  
  $args = func_get_args();
  
  if( $args[1]['Name'] == 'FV Player' ) {    
    $options = get_option( 'fvwpflowplayer' );
    if( $options['key'] == 'false' || $options['key'] == '' ) :
    ?>
<tr class="plugin-update-tr fv-wordpress-flowplayer-tr">
  <td class="plugin-update colspanchange" colspan="3">
    <div class="update-message">
      <a href="https://foliovision.com/player/download">All Licenses 20% Off</a> - Easter sale!
    </div>
  </td>
</tr>
    <?php
    endif;
  }
}



/**
 * Settings metaboxes close
 */
add_filter( 'get_user_option_closedpostboxes_fv_flowplayer_settings', 'fv_wp_flowplayer_closed_meta_boxes' );

function fv_wp_flowplayer_closed_meta_boxes( $closed ) {
  if ( false === $closed ) {
    $closed = array( 'fv_flowplayer_amazon_options', 'fv_flowplayer_interface_options', 'fv_flowplayer_default_options', 'fv_flowplayer_ads', 'fv_flowplayer_integrations', 'fv_flowplayer_mobile', 'fv_flowplayer_seo', 'fv_flowplayer_privacy');
  }

  return $closed;
}

/**
 * Tools metaboxes close
 */
add_filter( 'get_user_option_closedpostboxes_fv_flowplayer_settings_tools', 'fv_flowplayer_settings_tools_closed_meta_boxes' );

function fv_flowplayer_settings_tools_closed_meta_boxes( $closed ) {
  if ( false === $closed ) {
    $closed = array( 'fv_flowplayer_conversion' );
  }

  return $closed;
}

/**
 * Skin metaboxes close
 */
add_filter( 'get_user_option_closedpostboxes_fv_flowplayer_settings_skin', 'fv_flowplayer_settings_skin_closed_meta_boxes' );

function fv_flowplayer_settings_skin_closed_meta_boxes( $closed ) {
  if ( false === $closed ) {
    global $fv_fp;
    $customCSS = $fv_fp->_get_option('customCSS');

    if( strlen( $customCSS ) === 0 ) {
      $closed = array( 'fv_flowplayer_skin_custom_css' );
    }
  }

  return $closed;
}

/*
 *  Saving settings
 */
add_action('admin_init', 'fv_player_settings_save', 9);

function fv_player_settings_save() {
  //  Trick media uploader to show video only, while making sure we use our custom type; Also save options
  if( isset($_GET['type']) ) {
    if( $_GET['type'] == 'fvplayer_video' || $_GET['type'] == 'fvplayer_video_1' || $_GET['type'] == 'fvplayer_video_2' || $_GET['type'] == 'fvplayer_mobile' ) {
      $_GET['post_mime_type'] = 'video';
    }
    else if( $_GET['type'] == 'fvplayer_splash' || $_GET['type'] == 'fvplayer_logo' ) {
      $_GET['post_mime_type'] = 'image';
    }
  }
  
  if( isset($_POST['fv-wp-flowplayer-submit']) ) {
    check_admin_referer('fv_flowplayer_settings_nonce','fv_flowplayer_settings_nonce');
    
    global $fv_fp;
    if( method_exists($fv_fp,'_set_conf') ) {
      if( 
          // pro not installed or
          !function_exists('FV_Player_Pro') ||
           // pro installed and version is at least 7.5.25.728
          ( function_exists('FV_Player_Pro') && version_compare( str_replace( '.beta','',FV_Player_Pro()->version ),'7.5.25.728', '>=') )
        ) {
        $to_save = fv_player_handle_secrets($_POST, $fv_fp->conf);
      } else {
        $to_save = $_POST;
      }

      $fv_fp->_set_conf($to_save);
    } else {
      echo 'Error saving FV Flowplayer options.';
    }
  }
}




function fv_player_handle_secrets($new, $old) {
  foreach( $new as $k => $v ) {
    if (is_array($v) && strpos($k, '_is_secret_') !== 0 && isset($old[$k]) ) {
      // recursive call for nested settings
      $v = fv_player_handle_secrets($v, $old[$k]);
      $new[$k] = $v;
    }

    if(strpos($k, '_is_secret_') === 0 ) {
      $key = str_replace('_is_secret_', '', $k);

      if( isset($old[$key]) ) {
        if(is_array($v)) { // array of values, 1 - keep original, 0 - use new
          foreach( $v as $a_k => $a_v ) {
            if( $a_v == '1' ) {
              $new[$key][$a_k] = $old[$key][$a_k];
            }
          }
        } else if($v == '1')  { // single value,  1 - keep original, 0 - use new
          $new[$key] = $old[$key]; 
        }
      } 

      unset($new[$k]); // remove _is_secret_
    }
  }

  return $new;
}




/*
 *  Pointer boxes
 */
add_action('admin_init', 'fv_player_admin_pointer_boxes');

function fv_player_admin_pointer_boxes() {
  global $fv_fp;
  global $fv_wp_flowplayer_ver, $fv_wp_flowplayer_core_ver;

  if(
    isset($fv_fp->conf['disable_videochecker']) && $fv_fp->conf['disable_videochecker'] == 'false' &&
    ( !isset($fv_fp->conf['video_checker_agreement']) || $fv_fp->conf['video_checker_agreement'] != 'true' )
  ) {
    $fv_fp->pointer_boxes['fv_flowplayer_video_checker_service'] = array(
      'id' => '#wp-admin-bar-new-content',
      'pointerClass' => 'fv_flowplayer_video_checker_service',
      'heading' => __('FV Player Video Checker', 'fv-wordpress-flowplayer'),
      'content' => __("<p>FV Player includes a <a href='https://foliovision.com/player/basic-setup/how-to-use-video-checker' target='_blank'>free video checker</a> which will check your videos for any encoding errors and helps ensure smooth playback of all your videos. To work its magic, our video checker must contact our server.</p><p>Would you like to enable the video encoding checker?</p>", 'fv-wordpress-flowplayer'),
      'position' => array( 'edge' => 'top', 'align' => 'center' ),
      'button1' => __('Allow', 'fv-wordpress-flowplayer'),
      'button2' => __('Disable the video checker', 'fv-wordpress-flowplayer')
    );
  }
  
  if( !$fv_fp->_get_option('notice_7_5') ) {
    $fv_fp->pointer_boxes['fv_flowplayer_notice_7_5'] = array(
      'id' => '#wp-admin-bar-new-content',
      'pointerClass' => 'fv_flowplayer_notice_7_5',
      'heading' => __('FV Player 7.5', 'fv-wordpress-flowplayer'),
      'content' => __("<p>This new version includes several improvements:</p>
      <ul style='list-style-type: revert; margin-left: 3em'>
        <li>Editor autosave and preview improvements</li>
        <li>FV Player Video Play Stats</li>
        <li>Improved player JavaScript loading</li>
        <li>Multiple video playback improvements</li>
      </ul>
      <p>You can read more about it in our <a href='https://foliovision.com/?p=137472' target='_blank'>blog announcement</a>.</p>", 'fv-wordpress-flowplayer'),
      'position' => array( 'edge' => 'top', 'align' => 'center' ),
      'button1' => __('Thanks for letting me know!', 'fv-wordpress-flowplayer')
    );
  }
  
  if( !$fv_fp->_get_option('notice_new_lightbox') ) {
    $fv_fp->pointer_boxes['fv_flowplayer_new_lightbox'] = array(
      'id' => '#wp-admin-bar-new-content',
      'pointerClass' => 'fv_flowplayer_new_lightbox',
      'heading' => __('FV Player Video Lightbox', 'fv-wordpress-flowplayer'),
      'content' => __("<p>The lightbox technology has been changed from <a href='http://www.jacklmoore.com/colorbox/' target='_blank'>Colorbox</a> to <a href='https://fancyapps.com/fancybox/3/' target='_blank'>fancyBox</a></p><p>Please <a href='https://foliovision.com/support/fv-wordpress-flowplayer/bug-reports#new-post' target='_blank'>let us know</a> in case you notice any issues. You can check <a href='https://foliovision.com/player/demos/fv-flowplayer-lightbox' target='_blank'>our FV Player demo page</a> of it too.</p>", 'fv-wordpress-flowplayer'),
      'position' => array( 'edge' => 'top', 'align' => 'center' ),
      'button1' => __('Thanks for letting me know!', 'fv-wordpress-flowplayer')
    );
  }
  
  if( !$fv_fp->_get_option('notice_db') ) {
    $fv_fp->pointer_boxes['fv_flowplayer_db'] = array(
      'id' => '#wp-admin-bar-new-content',
      'pointerClass' => 'fv_flowplayer_db',
      'heading' => __('FV Player database storage is here!', 'fv-wordpress-flowplayer'),
      'content' => __("<p>Any new or updated FV Player instances will be stored in database. This simplifies the shortcodes and increases FV Player reliability. You can read the full announcement <a href='https://foliovision.com/2018/11/video-database/' target='_blank'>here</a></p><p>Please <a href='https://foliovision.com/support/fv-wordpress-flowplayer/bug-reports#new-post' target='_blank'>let us know</a> in case you notice any issues. Advanced users can keep using the old shortcodes, but from now on the FV Player editor works with database only.</p>", 'fv-wordpress-flowplayer'),
      'position' => array( 'edge' => 'top', 'align' => 'center' ),
      'button1' => __('Thanks for letting me know!', 'fv-wordpress-flowplayer')
    );
  }
  
  if( $fv_fp->_get_option('video_sitemap') && !$fv_fp->_get_option('disableembedding') && !$fv_fp->_get_option('notice_xml_sitemap_iframes') ) {
    $fv_fp->pointer_boxes['fv_flowplayer_notice_xml_sitemap_iframes'] = array(
      'id' => '#wp-admin-bar-new-content',
      'pointerClass' => 'fv_flowplayer_notice_xml_sitemap_iframes',
      'heading' => __('FV Player Video Sitemap coverage', 'fv-wordpress-flowplayer'),
      'content' => __("<p>The XML Video Sitemap now includes a lot more videos as it uses the individual player iframe embed links. Until now it was only possible to put in videos using MP4 format without any kind of download protection.</p><p>Please <a href='https://foliovision.com/support/fv-wordpress-flowplayer/bug-reports#new-post' target='_blank'>let us know</a> in case you notice any issues. Your members only videos stay protected and won't open, but let us know if they appear in sitemap.</p>", 'fv-wordpress-flowplayer').'<script>jQuery(".fv_flowplayer_notice_xml_sitemap_iframes .button2").click()</script>',
      'position' => array( 'edge' => 'top', 'align' => 'center' ),
      'button1' => __('Thanks for letting me know!', 'fv-wordpress-flowplayer'),
      'button2' => __('Go to setting', 'fv-wordpress-flowplayer'),
      'function2' => 'location.href = "'.admin_url('options-general.php?page=fvplayer').'#fv_flowplayer_seo"',
    );
  }  
  
  if( !$fv_fp->_get_option('notice_db') && !$fv_fp->_get_option('nag_fv_player_7') ) {
    $fv_fp->pointer_boxes['fv_flowplayer_fv_player_7'] = array(
      'id' => '#wp-admin-bar-new-content',
      'pointerClass' => 'fv_flowplayer_fv_player_7',
      'heading' => __('FV Player 7', 'fv-wordpress-flowplayer'),
      'content' => '<p>Welcome to the brand new FV Player 7! Improvements include:</p>'.
        '<ul style="list-style: circle; padding-left: 3em;"><li>New player design and skin options</li>
<li>New Flowplayer core video engine</li>
<li>Support for autoplay on latest Chrome and Safari versions</li>
<li>Support for autoplay on mobile</li>
<li>New lightbox look</li>
<li>Improved video buffering</li></ul>'.
        '<p>More information in our <a href="https://foliovision.com/2018/09/fv-player-7" target="_blank">blog announcement</a>.</p>',
      'position' => array( 'edge' => 'top', 'align' => 'center' ),
      'button1' => __('Thanks for letting me know!', 'fv-wordpress-flowplayer'),
    );
  }
  
  if( 
    (stripos( $_SERVER['REQUEST_URI'], '/plugins.php') !== false ||fv_player_is_admin_screen() ) 
    && $pnotices = get_option('fv_wordpress_flowplayer_persistent_notices') 
  ) {  
    $fv_fp->pointer_boxes['fv_flowplayer_license_expired'] = array(
      'id' => '#wp-admin-bar-new-content',
      'pointerClass' => 'fv_flowplayer_license_expired',
      'pointerWidth' => 340,
      'heading' => __('FV Flowplayer License Expired', 'fv-wordpress-flowplayer'),
      'content' => __( $pnotices ),
      'position' => array( 'edge' => 'top', 'align' => 'center' ),
      'button1' => __('Hide this notice', 'fv-wordpress-flowplayer'),
      'button2' => __('I\'ll check this later', 'fv-wordpress-flowplayer')
    );    
  }
  
  /*if( !$fv_fp->_get_option('disable_video_hash_links') && !$fv_fp->_get_option('notification_video_links') ) {
    $fv_fp->pointer_boxes['fv_player_notification_video_links'] = array(
      'id' => '#wp-admin-bar-new-content',
      'pointerClass' => 'fv_player_notification_video_links',
      'heading' => __('FV Player Video Links', 'fv-wordpress-flowplayer'),
      'content' => $fv_fp->_get_option('disableembedding') ? __("<p>Now you can enable Video Links to allow people to share exact location in your videos. Clicking that link gives them a link to play that video at the exact time.</p>", 'fv-wordpress-flowplayer') : __("<p>Each video player now contains a link in the top bar. Clicking that link gives your visitors a link to play that video at the exact time where they are watching it.</p>", 'fv-wordpress-flowplayer'),
      'position' => array( 'edge' => 'top', 'align' => 'center' ),
      'button1' => __('Open Settings', 'fv-wordpress-flowplayer'),
      'button2' => __('Dismiss', 'fv-wordpress-flowplayer')
    );
    
    add_action( 'admin_print_footer_scripts', 'fv_player_pointer_scripts' );
  }*/
}




add_action( 'wp_ajax_fv_foliopress_ajax_pointers', 'fv_wp_flowplayer_pointers_ajax' );

function fv_wp_flowplayer_pointers_ajax() {
  
  if( isset($_POST['key']) && $_POST['key'] == 'fv_flowplayer_video_checker_service' && isset($_POST['value']) ) {
    check_ajax_referer('fv_flowplayer_video_checker_service');
    $conf = get_option( 'fvwpflowplayer' );
    if( $conf ) {
      if( $_POST['value'] == 'true' ) {
        $conf['disable_videochecker'] = 'false';
        $conf['video_checker_agreement'] = 'true';
      } else {
        $conf['disable_videochecker'] = 'true';
      }
      update_option( 'fvwpflowplayer', $conf );
    }
    die();
  }
  
  if( isset($_POST['key']) && $_POST['key'] == 'fv_flowplayer_license_expired' && isset($_POST['value']) && $_POST['value'] === 'true' ) {
    check_ajax_referer('fv_flowplayer_license_expired');
    delete_option("fv_wordpress_flowplayer_persistent_notices");
    die();
  }  
  
  $notices = array(
    'fv_flowplayer_notice_7_5'                 => 'notice_7_5',
    'fv_flowplayer_new_lightbox'               => 'notice_new_lightbox',
    'fv_flowplayer_notice_xml_sitemap_iframes' => 'notice_xml_sitemap_iframes',
    'fv_flowplayer_db'                         => 'notice_db',
    'fv_flowplayer_fv_player_7'                => 'nag_fv_player_7',
    'fv_player_notification_video_links'       => 'notification_video_links',
  );
  
  if( isset($_POST['key']) && isset($_POST['value']) && in_array($_POST['key'], array_keys($notices) ) ) {
    check_ajax_referer($_POST['key']);
    $conf = get_option( 'fvwpflowplayer' );
    if( $conf ) {
      $conf[$notices[$_POST['key']]] = 'true';
      update_option( 'fvwpflowplayer', $conf );
    }
    die();
  }
  
}




function fv_player_pointer_scripts() {
  ?>
  <script>
    (function ($) {
      $(document).on('click', '.fv_player_notification_video_links .button-primary', function(e) {
        $(document).ajaxComplete( function() {
          window.location = '<?php echo site_url('wp-admin/options-general.php?page=fvplayer'); ?>#playlist_advance';
        });
      });
    })(jQuery);        
  </script>
  <?php
}




/*
 *  Making sure FV Player appears properly on settings screen
 */
add_action('admin_enqueue_scripts', 'fv_flowplayer_admin_scripts');

function fv_flowplayer_admin_scripts() {
  global $fv_wp_flowplayer_ver;
  if( fv_player_is_admin_screen() ) {
    wp_enqueue_media();
  
    wp_enqueue_script('wp-theme-plugin-editor');
    wp_enqueue_style('wp-codemirror');

    wp_enqueue_script('common');
    wp_enqueue_script('wp-lists');
    wp_enqueue_script('postbox');
    
    wp_enqueue_script('jquery-minicolors', flowplayer::get_plugin_url().'/js/jquery-minicolors/jquery.minicolors.min.js',array('jquery'), $fv_wp_flowplayer_ver );
    wp_enqueue_script('fv-player-admin', flowplayer::get_plugin_url().'/js/admin.js',array('jquery','jquery-minicolors'), filemtime( (__DIR__).'/../js/admin.js' ), true );

    if( function_exists('wp_enqueue_code_editor') ) {
      wp_localize_script('fv-player-admin', 'cm_settings', wp_enqueue_code_editor(array('type' => 'text/css')) );
    }
  }
}




add_action('admin_head', 'flowplayer_admin_head');

function flowplayer_admin_head() {  
  if( !fv_player_is_admin_screen() ) return;

  global $fv_wp_flowplayer_ver;
  wp_enqueue_style('fv-player-admin', flowplayer::get_plugin_url().'/css/license.css',array(), $fv_wp_flowplayer_ver );
  wp_enqueue_style('jquery-minicolors', flowplayer::get_plugin_url().'/js/jquery-minicolors/jquery.minicolors.css',array(), $fv_wp_flowplayer_ver );
  ?>      
    <script>
    jQuery(window).on('unload', function(){
      window.fv_flowplayer_wp = window.wp;
    });
    </script>
  <?php
}




add_action('admin_footer', 'flowplayer_admin_footer');

function flowplayer_admin_footer() {
  if( !fv_player_is_admin_screen() ) return;
  
  flowplayer_prepare_scripts();
  flowplayer_display_scripts();
}




add_action('admin_print_footer_scripts', 'flowplayer_admin_footer_wp_js_restore', 999999 );

function flowplayer_admin_footer_wp_js_restore() {
  if( !fv_player_is_admin_screen() ) return; 
  
  ?>
  <script>
  jQuery(window).on('unload', function(){
    window.wp = window.fv_flowplayer_wp;
  });
  </script>
  <?php
}



function fv_player_get_aws_regions($translation_domain = 'fv-wordpress-flowplayer') {

  return array(
    'af-south-1'     => __('Africa (Cape Town)', $translation_domain),
    'ap-east-1'      => __('Asia Pacific (Hong Kong)', $translation_domain),
    'ap-south-1'     => __('Asia Pacific (Mumbai)', $translation_domain),
    'ap-northeast-3' => __('Asia Pacific (Osaka-Local)', $translation_domain),
    'ap-northeast-2' => __('Asia Pacific (Seoul)', $translation_domain),
    'ap-southeast-1' => __('Asia Pacific (Singapore)', $translation_domain),
    'ap-southeast-2' => __('Asia Pacific (Sydney)', $translation_domain),
    'ap-northeast-1' => __('Asia Pacific (Tokyo)', $translation_domain),
    
    'ca-central-1'   => __('Canada (Central)', $translation_domain),
    
    'cn-north-1'     => __('China (Beijing)', $translation_domain),
    'cn-northwest-1' => __('China (Ningxia)', $translation_domain),
    
    'eu-central-1'   => __('Europe (Frankfurt)', $translation_domain),
    'eu-west-1'      => __('Europe (Ireland)', $translation_domain),
    'eu-west-2'      => __('Europe (London)', $translation_domain),
    'eu-south-1'     => __('Europe (Milan)', $translation_domain),
    'eu-west-3'      => __('Europe (Paris)', $translation_domain),
    'eu-north-1'     => __('Europe (Stockholm)', $translation_domain),
    
    'me-south-1'     => __('Middle East (Bahrain)', $translation_domain),
    
    'sa-east-1'      => __('South America (S&atilde;o Paulo)', $translation_domain),
    
    'us-west-1'      => __('US West (N. California)', $translation_domain),
    'us-east-1'      => __('US East (N. Virginia)', $translation_domain),
    'us-east-2'      => __('US East (Ohio)', $translation_domain),
    'us-west-2'      => __('US West (Oregon)', $translation_domain)
    
  );
}
