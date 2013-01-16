<?php
/* 
Plugin Name: WP Gravatar Cache
Plugin URI: https://github.com/xjpvictor/wp-gravatar-cache
Version: 0.0.1
Author: xjpvictor
Description: A wordpress plugin to cache gravatar images.
*/

function wp_gravatar_cache($text) {
  if (!$text)
    return $text;

  $cache_dir = get_option('wpgc_dir');
  if (substr($cache_dir, -1) !== '/')
    $cache_dir .= '/';
  
  $wpgc_cc = get_option('wpgc_cc');
  if (time() - get_option('wpgc_cc_ts') >= $wpgc_cc * 86400) {
    update_option('wpgc_cc_ts', time());
    $files = scandir($cache_dir);
    foreach ($files as $file) {
      if ($file !== '.' && $file !== '..' && time() - filemtime($cache_dir.$file) > $wpgc_cc * 86400) {
        unlink($cache_dir.$file);
      }
    }
  }

  if (is_admin())
    return $text;
  preg_match('/http(?:s*):\/\/(?:[a-z0-9]+).gravatar.com\/avatar\/([a-z0-9]+)\?s=(\d+)(?:\S*)&amp;r=(\w*)/',$text,$match);
  $ourl = $match[0];
  $email_hash = $match[1];
  $size = $match[2];
  $rate = $match[3];
  if ( empty($rate) ) {
    $rate = get_option('avatar_rating');
  }

  $default = get_option('avatar_default');
  switch($default) {
    case 'mystery':
      $default = 'mm';
      break;
    case 'gravatar_default':
      $default = urlencode("https://secure.gravatar.com/avatar/?s={$size}");
      break;
  }
  $file = $cache_dir.$email_hash.'_'.$size.'_'.$rate;
  if ( !file_exists($file) || time() - filemtime($file) > 86400 * get_option(wpgc_exp) ) {
    $img = file_get_contents("https://secure.gravatar.com/avatar/{$email_hash}?s={$size}&d={$default}");
    file_put_contents($file,$img);
  }
  $url = get_option('wpgc_url');
  if (substr($url, -1) !== '/')
    $url .= '/';
  $url .= $email_hash.'_'.$size.'_'.$rate;
  return preg_replace('/src=\'\S+\'/',"src='{$url}'",$text);
}

function wp_gravatar_cache_ini(){
	global $wpdb, $options, $message;

	$options_default = array(__DIR__.'/cache/', site_url().'/wp-content/plugins/wp-gravatar-cache/cache/', '7', '30', time());
	foreach ($options as $key => $option) {
		if (!get_option($option)) {
			update_option($option, $options_default[$key]);
			if ($option == 'wpgc_dir' && !file_exists($options_default[$key])) {
				if (!mkdir($options_default[$key])) {
					$message = 'Error create directory';
				}
			}
		}
	}
}

function wp_gravatar_cache_options_page(){
	wp_gravatar_cache_ini();
	add_options_page('WP Gravatar Cache Option', 'WP Gravatar Cache', 'manage_options', 'wp-gravatar-cache.php', 'options_page');
}
function options_page(){
	global $message;
	$dir = get_option('wpgc_dir');
	if (substr($dir, -1) !== '/')
		$dir .= '/';
?>
<div class="wrap">
	<h2>WP Gravatar Cache</h2>
	<p><strong>A wordpress plugin to cache gravatar images.</strong></p>
		<fieldset name="wp_basic_options"  class="options">
	<form method="post" action="">
		<?php if (isset($message)) echo '<p style="color:red;">'.$message.'</p>'; ?>
		<p>Cache directory:</p>
		<input required type="text" class="regular-text" name="wpgc_dir" value="<?php echo htmlentities($dir); ?>" />
		<p>Base URL for cached gravatar images:</p>
		<input required type="text" class="regular-text" name="wpgc_url" value="<?php echo htmlentities(get_option('wpgc_url')); ?>" />
		<p>Cache expire time: <input required type="text" size="3" name="wpgc_exp" value="<?php echo get_option('wpgc_exp'); ?>" /> days (Update images in case user changes avatar.)</p>
		<p>Cache clean time: <input required type="text" size="3" name="wpgc_cc" value="<?php echo get_option('wpgc_cc'); ?>" /> days (Delete cache files in case user's comments are deleted. Recommended to be long enough especially when html cache plugins are activated, eg. wp-supercache.)</p>
		<input type="submit" class="button button-primary" name="wpgc_update" /><br/><br/>
<?php
	$files = scandir($dir);
	$size = 0;
	$i = 0;
	foreach ($files as $file) {
		if ($file !== '.' && $file !== '..') {
			$size += filesize($dir.$file);
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

function wp_gravatar_cache_deactivate() {
	global $options;
	foreach ($options as $option) {
		delete_option($option);
	}
}

$options = array('wpgc_dir', 'wpgc_url', 'wpgc_exp', 'wpgc_cc', 'wpgc_cc_ts');

if (!empty($_POST) && isset($_POST['wpgc_update'])) {
	if (array_key_exists('wpgc_dir', $_POST) && array_key_exists('wpgc_url', $_POST) && array_key_exists('wpgc_exp', $_POST) && array_key_exists('wpgc_cc', $_POST)) {
		foreach ($options as $option) {
			if (isset($_POST[$option]) && $_POST[$option] !== get_option($option)) {
				if ($option == 'wpgc_dir') {
					if (substr($_POST[$option], -1) !== '/')
						$_POST[$option] .= '/';
					if (!file_exists($_POST[$option])) {
						if (!mkdir($_POST[$option])) {
							$message = 'Error create directory';
						}
					}
				}
				if ($option == 'wpgc_url') {
					if (substr($_POST[$option], -1) !== '/')
						$_POST[$option] .= '/';
				}
				update_option($option, $_POST[$option]);
			}
		}
	}
} elseif (!empty($_POST) && isset($_POST['wpgc_clean'])) {
	$dir = get_option('wpgc_dir');
	if (substr($dir, -1) !== '/')
		$dir .= '/';
	$files = scandir($dir);
	foreach ($files as $file) {
		if ($file !== '.' && $file !== '..') {
			unlink($dir.$file);
		}
	}
}

add_filter('get_avatar','wp_gravatar_cache');
add_action('admin_menu', 'wp_gravatar_cache_options_page');
register_deactivation_hook( __FILE__, 'wp_gravatar_cache_deactivate');