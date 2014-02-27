<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_login_redirect($redirectTo, $redirectToRequested, $wpUser)
{
	$config = xfac_option_getConfig();

	if (!defined('XFAC_SYNC_LOGIN_SKIP_REDIRECT') AND !empty($config) AND !empty($wpUser->ID))
	{
		$records = xfac_user_getApiRecordsByUserId($wpUser->ID);
		if (!empty($records))
		{
			$record = reset($records);

			$accessToken = xfac_user_getAccessTokenForRecord($record);
			$ott = xfac_api_generateOneTimeToken($config, $record->identifier, $accessToken);

			$redirectTo = xfac_api_getLoginLink($config, $ott, $redirectTo);
		}
	}

	return $redirectTo;
}

function xfac_allowed_redirect_hosts($hosts)
{
	$config = xfac_option_getConfig();
	if (!empty($config))
	{
		$rootParsed = parse_url($config['root']);
		if (!empty($rootParsed['host']))
		{
			$hosts[] = $rootParsed['host'];
		}
	}

	return $hosts;
}

function xfac_wp_logout()
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// do nothing
		return;
	}

	$wpUser = wp_get_current_user();

	if (empty($wpUser->ID))
	{
		// hmm, how could guest perform log out?
		return;
	}

	$records = xfac_user_getApiRecordsByUserId($wpUser->ID);
	if (!empty($records))
	{
		$record = reset($records);

		$accessToken = xfac_user_getAccessTokenForRecord($record);
		$ott = xfac_api_generateOneTimeToken($config, $record->identifier, $accessToken);

		$redirectTo = !empty($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : site_url('wp-login.php?loggedout=true');
		$newRedirectTo = xfac_api_getLogoutLink($config, $ott, $redirectTo);

		$_REQUEST['redirect_to'] = $newRedirectTo;
	}
}

if (!!get_option('xfac_sync_login'))
{
	add_filter('login_redirect', 'xfac_login_redirect', 10, 3);

	add_filter('allowed_redirect_hosts', 'xfac_allowed_redirect_hosts', 10, 1);
	add_action('wp_logout', 'xfac_wp_logout');
}
