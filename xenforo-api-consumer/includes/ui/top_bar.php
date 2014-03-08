<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_admin_bar_forums_menu($wpAdminBar)
{
	if (is_admin())
	{
		// don't add menu in Dashboard
		return;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// don't add menu for site without configuration
		return;
	}

	$optionTopBarForums = get_option('xfac_top_bar_forums');
	if (!is_array($optionTopBarForums))
	{
		$optionTopBarForums = array();
	}

	$meta = xfac_option_getMeta($config);

	if (in_array(0, $optionTopBarForums))
	{
		$wpAdminBar->add_menu(array(
			'id' => 'xfac-forums',
			'title' => __('Forums', 'xenforo-api-consumer'),
			'href' => $meta['linkIndex'],
		));

		foreach ($optionTopBarForums as $forumId)
		{
			$forum = false;

			if (empty($meta['forums']))
			{
				continue;
			}
			foreach ($meta['forums'] as $_forum)
			{
				if ($_forum['forum_id'] == $forumId)
				{
					$forum = $_forum;
				}
			}

			if (empty($forum))
			{
				continue;
			}

			$wpAdminBar->add_menu(array(
				'parent' => 'xfac-forums',
				'id' => 'xfac-forum-' . $forum['forum_id'],
				'title' => $forum['forum_title'],
				'href' => $forum['links']['permalink'],
			));
		}
	}
}

function xfac_admin_bar_jscount_menu($wpAdminBar)
{
	if (is_admin())
	{
		// don't add menu in Dashboard
		return;
	}

	$wpUser = wp_get_current_user();
	if (empty($wpUser->ID))
	{
		// don't add menu for guests
		return;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// don't add menu for site without configuration
		return;
	}

	$records = xfac_user_getRecordsByUserId($wpUser->ID);
	if (empty($records))
	{
		// don't add menu for not-connected users
		return;
	}
	$record = reset($records);

	$meta = xfac_option_getMeta($config);

	$accessToken = xfac_user_getAccessTokenForRecord($record);
	$ott = xfac_api_generateOneTimeToken($config, $record->profile['user_id'], $accessToken);
	$html = '<script>window.xfacOneTimeToken = "' . $ott . '";</script>';

	if (!!get_option('xfac_top_bar_notifications') AND !empty($meta['linkAlerts']))
	{
		$notificationsTitle = __('Alerts', 'xenforo-api-consumer');
		$notificationsTitle .= ' <span id="xfacNotificationCount" class="xfacJsCount"></span>';

		$wpAdminBar->add_menu(array(
			'id' => 'xfac-notifications',
			'title' => $notificationsTitle,
			'parent' => (!!get_option('xfac_top_bar_replace') ? 'top-secondary' : ''),
			'href' => $meta['linkAlerts'],
			'meta' => array('html' => $html),
		));
	}

	if (!!get_option('xfac_top_bar_conversations') AND !empty($meta['linkConversations']))
	{
		$conversationTitle = __('Conversations', 'xenforo-api-consumer');
		$conversationTitle .= ' <span id="xfacConversationCount" class="xfacJsCount"></span>';

		$wpAdminBar->add_menu(array(
			'id' => 'xfac-conversations',
			'title' => $conversationTitle,
			'parent' => (!!get_option('xfac_top_bar_replace') ? 'top-secondary' : ''),
			'href' => $meta['linkConversations'],
			'meta' => array('html' => $html),
		));
	}
}

function xfac_admin_bar_login_menu($wpAdminBar)
{
	$wpUser = wp_get_current_user();
	if ($wpUser->ID > 0)
	{
		// don't add menu for users
		return;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// don't add menu for site without configuration
		return;
	}

	$meta = xfac_option_getMeta($config);

	if (!empty($meta['linkRegister']))
	{
		$wpAdminBar->add_menu(array(
			'id' => 'xfac-register',
			'parent' => 'top-secondary',
			'title' => __('Register', 'xenforo-api-consumer'),
			'href' => $meta['linkRegister'],
		));
	}

	if (!empty($meta['linkLogin']))
	{
		$wpAdminBar->add_menu(array(
			'id' => 'xfac-login',
			'parent' => 'top-secondary',
			'title' => __('Log in', 'xenforo-api-consumer'),
			'href' => $meta['linkLogin'],
		));

		if (!empty($meta['linkLoginLogin']))
		{
			$loginFormAction = $meta['linkLoginLogin'];
			$redirect = site_url('wp-login.php?xfac=1');

			ob_start();
			require (xfac_template_locateTemplate('top_bar_login_form.php'));
			$loginForm = ob_get_clean();

			$wpAdminBar->add_menu(array(
				'id' => 'xfac-loginForm',
				'parent' => 'xfac-login',
				'title' => $loginForm,
			));
		}
	}
}

function xfac_admin_bar_remove_menus($wpAdminBar)
{
	$nodes = $wpAdminBar->get_nodes();
	$nodeIds = array_keys($nodes);

	foreach ($nodeIds as $nodeId)
	{
		if ($nodes[$nodeId]->group)
		{
			// keep groups
			continue;
		}
		if (strpos($nodeId, 'xfac-') === 0)
		{
			// keep ours
			continue;
		}
		elseif ($nodeId === 'top-secondary' OR $nodeId === 'my-account' OR $nodes[$nodeId]->parent === 'user-actions')
		{
			// keep user related nodes
			continue;
		}
		elseif ($nodeId === 'logo')
		{
			// keep logo
			continue;
		}
		else
		{
			// remove others
			$wpAdminBar->remove_node($nodeId);
		}
	}
}

function xfac_add_admin_bar_menus()
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// don't add menu for site without configuration
		return;
	}

	add_action('admin_bar_menu', 'xfac_admin_bar_forums_menu', 30);

	if (!!get_option('xfac_top_bar_notifications') OR !!get_option('xfac_top_bar_conversations'))
	{
		wp_enqueue_script('jquery');
		wp_enqueue_script('xfac-sdk', xfac_api_getSdkJsUrl($config));
		wp_enqueue_script('xfac-top_bar.js', XFAC_PLUGIN_URL . '/js/top_bar.js');
		wp_enqueue_style('xfac-top_bar.css', XFAC_PLUGIN_URL . '/css/top_bar.css');

		add_action('admin_bar_menu', 'xfac_admin_bar_jscount_menu', !!get_option('xfac_top_bar_replace') ? 0 : 30);
	}

	if (!!get_option('xfac_top_bar_replace'))
	{
		wp_enqueue_style('xfac-top_bar.css', XFAC_PLUGIN_URL . '/css/top_bar.css');
		add_action('admin_bar_menu', 'xfac_admin_bar_login_menu', 7);

		add_action('admin_bar_menu', 'xfac_admin_bar_remove_menus', PHP_INT_MAX);
	}
}

add_action('add_admin_bar_menus', 'xfac_add_admin_bar_menus');

function xfac_show_admin_bar($showAdminBar)
{
	if (!!get_option('xfac_top_bar_always'))
	{
		return true;
	}

	return $showAdminBar;
}

add_filter('show_admin_bar', 'xfac_show_admin_bar');
