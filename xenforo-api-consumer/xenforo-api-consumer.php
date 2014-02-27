<?php
/*
 Plugin Name: XenForo API Consumer
 Plugin URI: https://xfrocks.com/api-support/
 Description: Connects to XenForo API system.
 Version: 1.0.1
 Author: XFROCKS
 Author URI: https://xfrocks.com
 Text Domain: xenforo-api-consumer
 Domain Path: /lang
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

define('XFAC_API_SCOPE', 'read post conversate');
define('XFAC_PLUGIN_PATH', WP_PLUGIN_DIR . '/xenforo-api-consumer');
define('XFAC_PLUGIN_URL', WP_PLUGIN_URL . '/xenforo-api-consumer');

function xfac_activate()
{
	if (!function_exists('is_multisite'))
	{
		// requires WordPress v3.0+
		deactivate_plugins(basename(dirname(__FILE__)) . '/' . basename(__FILE__));
		wp_die(__("XenForo API Consumer plugin requires WordPress 3.0 or newer.", 'xenforo-api-consumer'));
	}

	xfac_install();

	do_action('xfac_activate');
}

register_activation_hook(__FILE__, 'xfac_activate');

function xfac_init()
{
	$loaded = load_plugin_textdomain('xenforo-api-consumer', false, 'xenforo-api-consumer/lang/');
}

add_action('init', 'xfac_init');

require_once (dirname(__FILE__) . '/includes/helper/api.php');
require_once (dirname(__FILE__) . '/includes/helper/dashboard.php');
require_once (dirname(__FILE__) . '/includes/helper/installer.php');
require_once (dirname(__FILE__) . '/includes/helper/option.php');
require_once (dirname(__FILE__) . '/includes/helper/template.php');
require_once (dirname(__FILE__) . '/includes/helper/user.php');

if (is_admin())
{
	require_once (dirname(__FILE__) . '/includes/dashboard/options.php');
	require_once (dirname(__FILE__) . '/includes/dashboard/profile.php');
}
else
{
	require_once (dirname(__FILE__) . '/includes/ui/login.php');
	require_once (dirname(__FILE__) . '/includes/ui/top_bar.php');
	require_once (dirname(__FILE__) . '/includes/sync/login.php');
}

require_once (dirname(__FILE__) . '/includes/helper/sync.php');
require_once (dirname(__FILE__) . '/includes/sync/avatar.php');
require_once (dirname(__FILE__) . '/includes/sync/post.php');
require_once (dirname(__FILE__) . '/includes/sync/comment.php');

require_once (dirname(__FILE__) . '/includes/widget/threads.php');
