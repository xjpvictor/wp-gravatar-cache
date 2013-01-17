<?php
/* 
Plugin Name: WP Gravatar Cache
Plugin URI: https://github.com/xjpvictor/wp-gravatar-cache
Version: 0.0.3
Author: xjpvictor
Description: A wordpress plugin to cache gravatar images.
*/

if(!class_exists('wp_gravatar_cache')):
class wp_gravatar_cache{

  var $options_key = array('wpgc_dir', 'wpgc_url', 'wpgc_exp', 'wpgc_cc', 'wpgc_cc_ts');
  var $options = array();
  var $message = '';
  
  function wp_gravatar_cache(){
    $this->wpgc_init();
    $this->wpgc_init_hook();
    if (!empty($_POST))
      $this->wpgc_post();
  }
  
  function wpgc_init(){
    $options_default = array(__DIR__.'/cache/', site_url().'/wp-content/plugins/wp-gravatar-cache/cache/', '7', '30', time());
  
    foreach ($this->options_key as $key => $option_key) {
      $this->options[$option_key] = get_option($option_key);
      if (!$this->options[$option_key]) {
        update_option($option_key, $options_default[$key]);
        $this->options[$option_key] = $options_default[$key];
      }
      if ($option_key == 'wpgc_dir') {
        if (substr($this->options[$option_key], -1) !== '/')
          $this->options[$option_key] .= '/';
        if (!file_exists($this->options[$option_key])) {
          if (!mkdir($this->options[$option_key])) {
            $this->message = 'Error create directory';
          }
        }
      } elseif ($option_key == 'wpgc_url') {
        if (substr($this->options[$option_key], -1) !== '/')
          $this->options[$option_key] .= '/';
      }
    }
  }
  
  function wpgc_init_hook(){
    add_filter('get_avatar', array(&$this, 'wpgc_get_avatar'));
    add_action('admin_menu', array(&$this, 'wpgc_options_page'));
    register_deactivation_hook( __FILE__, array(&$this, 'wpgc_deactivate'));
  }
  
  function wpgc_get_avatar($text) {
    if (!$text)
      return $text;
  
    if (time() - $this->options['wpgc_cc_ts'] >= $this->options['wpgc_cc'] * 86400) {
      update_option('wpgc_cc_ts', time());
      $files = scandir($this->options['wpgc_dir']);
      foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && time() - filemtime($this->options['wpgc_dir'].$file) > $this->options['wpgc_cc'] * 86400) {
          unlink($this->options['wpgc_dir'].$file);
        }
      }
    }
  
    if (is_admin())
      return $text;
  
    preg_match('/src=\'\S+\'/', $text, $ourl);
    $ourl = urldecode($ourl[0]);

    preg_match('/http(?:s*):\/\/(?:[a-z0-9]+).gravatar.com\/avatar\/([a-z0-9]+)\?s=(\d+)(?:\S*)&amp;r=(\w*)/',$ourl,$match);
    $email_hash = $match[1];
    $size = $match[2];
    $rate = $match[3];
    $file = $this->options['wpgc_dir'].$email_hash.'_'.$size.'_'.$rate;

    if ( !file_exists($file) || time() - filemtime($file) > 86400 * $this->options['wpgc_exp'] ) {
      $img = file_get_contents($ourl);
      file_put_contents($file,$img);
    }
    $url = $this->options['wpgc_url'];
    if (substr($url, -1) !== '/')
      $url .= '/';
    $url .= $email_hash.'_'.$size.'_'.$rate;
    return str_replace($ourl, $url, $text);
  }
  
  function wpgc_options_page(){
    add_options_page('WP Gravatar Cache Option', 'WP Gravatar Cache', 'manage_options', 'wp-gravatar-cache.php', array(&$this, 'options_page'));
  }
  
  function options_page(){
  ?>
  <div class="wrap">
    <h2>WP Gravatar Cache</h2>
    <p><strong>A wordpress plugin to cache gravatar images.</strong></p>
      <fieldset name="wp_basic_options"  class="options">
    <form method="post" action="">
      <?php if (!empty($this->message)) echo '<p style="color:red;">'.$this->message.'</p>'; ?>
      <p>Cache directory:</p>
      <input required type="text" class="regular-text" name="wpgc_dir" value="<?php echo htmlentities($this->options['wpgc_dir']); ?>" />
      <p>Base URL for cached gravatar images:</p>
      <input required type="text" class="regular-text" name="wpgc_url" value="<?php echo htmlentities($this->options['wpgc_url']); ?>" />
      <p>Cache expire time: <input required type="text" size="3" name="wpgc_exp" value="<?php echo $this->options['wpgc_exp']; ?>" /> days (Update images in case user changes avatar.)</p>
      <p>Cache clean time: <input required type="text" size="3" name="wpgc_cc" value="<?php echo $this->options['wpgc_cc']; ?>" /> days (Delete cache files in case user's comments are deleted. Recommended to be long enough especially when html cache plugins are activated, eg. wp-supercache.)</p>
      <input type="submit" class="button button-primary" name="wpgc_update" /><br/><br/>
  <?php
    $files = scandir($this->options['wpgc_dir']);
    $size = 0;
    $i = 0;
    foreach ($files as $file) {
      if ($file !== '.' && $file !== '..') {
        $size += filesize($this->options['wpgc_dir'].$file);
        $i ++;
      }
    }
    if ($size < 1024) {
        $size = $size .' B';
    } elseif ($size < 1048576) {
        $size = round($size / 1024, 2) .' KiB';
    } elseif ($size < 1073741824) {
        $size = round($size / 1048576, 2) . ' MiB';
    } elseif ($size < 1099511627776) {
        $size = round($size / 1073741824, 2) . ' GiB';
    } elseif ($size < 1125899906842624) {
        $size = round($size / 1099511627776, 2) .' TiB';
    }
  ?>
      <p><?php echo $i; ?> cached images (<?php echo $size; ?>)</p>
      <input type="submit" class="button" name="wpgc_clean" value="Clean all cache" />
    </form>
      </fieldset>
  </div>
  <?php
  }
  
  function wpgc_deactivate() {
    foreach ($this->options_key as $option) {
      delete_option($option);
    }
  }
  
  function wpgc_post() {
    if (isset($_POST['wpgc_update'])) {
      if (array_key_exists('wpgc_dir', $_POST) && array_key_exists('wpgc_url', $_POST) && array_key_exists('wpgc_exp', $_POST) && array_key_exists('wpgc_cc', $_POST)) {
        foreach ($this->options_key as $option) {
          if (isset($_POST[$option]) && $_POST[$option] !== $this->options[$option]) {
            if ($option == 'wpgc_dir') {
              if (substr($_POST[$option], -1) !== '/')
                $_POST[$option] .= '/';
              if (!file_exists($_POST[$option])) {
                if (!mkdir($_POST[$option])) {
                  $this->message = 'Error create directory';
                }
              }
            }
            if ($option == 'wpgc_url') {
              if (substr($_POST[$option], -1) !== '/')
                $_POST[$option] .= '/';
            }
            update_option($option, $_POST[$option]);
            $this->options[$option] = $_POST[$option];
          }
        }
      }
    } elseif (isset($_POST['wpgc_clean'])) {
      $files = scandir($this->options['wpgc_dir']);
      foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
          unlink($this->options['wpgc_dir'].$file);
        }
      }
    }
  }

}
endif;

$new_wp_gravatar_cache = new wp_gravatar_cache;
