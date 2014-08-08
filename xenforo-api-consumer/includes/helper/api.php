<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_api_getLastErrors()
{
	if (!empty($GLOBALS['_xfac_api_lastErrors']))
	{
		return $GLOBALS['_xfac_api_lastErrors'];
	}

	return false;
}

function xfac_api_getModules($config)
{
	$body = file_get_contents(call_user_func_array('sprintf', array(
		'%s/index.php?oauth_token=%s',
		rtrim($config['root'], '/'),
		rawurlencode(xfac_api_generateOneTimeToken($config)),
	)));

	$parts = @json_decode($body, true);

	if (!empty($parts['system_info']['api_modules']))
	{
		return $parts['system_info']['api_modules'];
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_api_getVersionSuggestionText($config, $meta)
{
	$requiredModules = array(
		'forum' => 2014022602,
		'oauth2' => 2014030701,
	);

	if (empty($meta['modules']))
	{
		return __('Unable to determine API version.', 'xenforo-api-consumer');
	}

	$problems = array();

	foreach ($requiredModules as $module => $moduleVersion)
	{
		if (empty($meta['modules'][$module]))
		{
			$problems[] = call_user_func_array('sprintf', array(
				__('Required module %$1s not found.', 'xenforo-api-consumer'),
				$module
			));
		}
		elseif ($meta['modules'][$module] < $moduleVersion)
		{
			$problems[] = call_user_func_array('sprintf', array(
				__('Module %1$s is too old (%3$s < %2$s).', 'xenforo-api-consumer'),
				$module,
				$moduleVersion,
				$meta['modules'][$module],
			));
		}
	}

	if (!empty($problems))
	{
		return implode('<br />', $problems);
	}
	else
	{
		return __('All required API modules have been found.', 'xenforo-api-consumer');
	}
}

function xfac_api_getAuthorizeUrl($config, $redirectUri)
{
	return call_user_func_array('sprintf', array(
		'%s/index.php?oauth/authorize/&client_id=%s&redirect_uri=%s&response_type=code&scope=%s',
		rtrim($config['root'], '/'),
		rawurlencode($config['clientId']),
		rawurlencode($redirectUri),
		rawurlencode(XFAC_API_SCOPE),
	));
}

function xfac_api_getSdkJsUrl($config)
{
	return call_user_func_array('sprintf', array(
		'%s/index.php?assets/sdk&prefix=xfac',
		rtrim($config['root'], '/'),
	));
}

function xfac_api_getLoginLink($config, $accessToken, $redirectUri)
{
	return call_user_func_array('sprintf', array(
		'%s/index.php?tools/login&oauth_token=%s&redirect_uri=%s',
		rtrim($config['root'], '/'),
		rawurlencode($accessToken),
		rawurlencode($redirectUri),
	));
}

function xfac_api_getLogoutLink($config, $accessToken, $redirectUri)
{
	return call_user_func_array('sprintf', array(
		'%s/index.php?tools/logout&oauth_token=%s&redirect_uri=%s',
		rtrim($config['root'], '/'),
		rawurlencode($accessToken),
		rawurlencode($redirectUri),
	));
}

function xfac_api_getPublicLink($config, $route)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, call_user_func_array('sprintf', array(
		'%s/index.php?tools/link',
		rtrim($config['root'], '/')
	)));

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'oauth_token' => xfac_api_generateOneTimeToken($config),
		'type' => 'public',
		'route' => $route,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);
	curl_close($ch);

	$parts = @json_decode($body, true);

	if (!empty($parts['link']))
	{
		return $parts['link'];
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_api_getAccessTokenFromCode($config, $code, $redirectUri)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, call_user_func_array('sprintf', array(
		'%s/index.php?oauth/token/',
		rtrim($config['root'], '/')
	)));

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'grant_type' => 'authorization_code',
		'client_id' => $config['clientId'],
		'client_secret' => $config['clientSecret'],
		'code' => $code,
		'redirect_uri' => $redirectUri,
		'scope' => XFAC_API_SCOPE,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);
	curl_close($ch);

	return _xfac_api_prepareAccessTokenBody($body);
}

function xfac_api_getAccessTokenFromRefreshToken($config, $refreshToken, $scope)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, call_user_func_array('sprintf', array(
		'%s/index.php?oauth/token/',
		rtrim($config['root'], '/')
	)));

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'grant_type' => 'refresh_token',
		'client_id' => $config['clientId'],
		'client_secret' => $config['clientSecret'],
		'refresh_token' => $refreshToken,
		'scope' => $scope,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);
	curl_close($ch);

	return _xfac_api_prepareAccessTokenBody($body);
}

function xfac_api_getAccessTokenFromUsernamePassword($config, $username, $password)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, call_user_func_array('sprintf', array(
		'%s/index.php?oauth/token/',
		rtrim($config['root'], '/')
	)));

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'grant_type' => 'password',
		'client_id' => $config['clientId'],
		'client_secret' => $config['clientSecret'],
		'username' => $username,
		'password' => $password,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);
	curl_close($ch);

	return _xfac_api_prepareAccessTokenBody($body);
}

function xfac_api_generateOneTimeToken($config, $userId = 0, $accessToken = '', $ttl = 10)
{
	$timestamp = time() + $ttl;
	$once = md5($userId . $timestamp . $accessToken . $config['clientSecret']);

	return sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $config['clientId']);
}

function xfac_api_getForums($config, $accessToken = '', $extraParams = '')
{
	$body = file_get_contents(call_user_func_array('sprintf', array(
		'%s/index.php?forums/&oauth_token=%s%s',
		rtrim($config['root'], '/'),
		rawurlencode($accessToken),
		!empty($extraParams) ? '&' . $extraParams : '',
	)));

	$parts = @json_decode($body, true);

	if (!empty($parts['forums']))
	{
		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_api_getUsersMe($config, $accessToken)
{
	$body = file_get_contents(call_user_func_array('sprintf', array(
		'%s/index.php?users/me/&oauth_token=%s',
		rtrim($config['root'], '/'),
		rawurlencode($accessToken)
	)));

	$parts = @json_decode($body, true);

	if (!empty($parts['user']))
	{
		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_api_getThreadsInForum($config, $forumId, $page = 1, $accessToken = '', $extraParams = '')
{
	$body = file_get_contents(call_user_func_array('sprintf', array(
		'%s/index.php?threads/&forum_id=%s&page=%d&order=thread_create_date_reverse&oauth_token=%s%s',
		rtrim($config['root'], '/'),
		$forumId,
		$page,
		rawurlencode($accessToken),
		!empty($extraParams) ? '&' . $extraParams : ''
	)));

	$parts = @json_decode($body, true);

	if (!empty($parts['threads']))
	{
		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_api_getPostsInThread($config, $threadId, $page = 1, $accessToken = '')
{
	$body = file_get_contents(call_user_func_array('sprintf', array(
		'%s/index.php?posts/&thread_id=%d&page=%d&order=natural_reverse&oauth_token=%s',
		rtrim($config['root'], '/'),
		$threadId,
		$page,
		rawurlencode($accessToken)
	)));

	$parts = @json_decode($body, true);

	if (!empty($parts['posts']))
	{
		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_api_postThread($config, $accessToken, $forumId, $threadTitle, $postBody)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, call_user_func_array('sprintf', array(
		'%s/index.php?threads/',
		rtrim($config['root'], '/')
	)));

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'oauth_token' => $accessToken,
		'forum_id' => $forumId,
		'thread_title' => $threadTitle,
		'post_body_html' => $postBody,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);
	curl_close($ch);

	$parts = @json_decode($body, true);

	if (!empty($parts['thread']))
	{
		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_api_postPost($config, $accessToken, $threadId, $postBody, array $extraParams = array())
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, call_user_func_array('sprintf', array(
		'%s/index.php?posts/',
		rtrim($config['root'], '/')
	)));

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query( array(
		'oauth_token' => $accessToken,
		'thread_id' => $threadId,
		'post_body_html' => $postBody,
	) + $extraParams));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);
	curl_close($ch);

	$parts = @json_decode($body, true);

	if (!empty($parts['post']))
	{
		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_api_postUser($config, $email, $username, $password, $accessToken = '', array $extraParams = array())
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, call_user_func_array('sprintf', array(
		'%s/index.php?users/',
		rtrim($config['root'], '/')
	)));

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$params = array(
		'email' => $email,
		'username' => $username,
	) + $extraParams;
	if (empty($accessToken))
	{
		$params['client_id'] = $config['clientId'];
	}
	else
	{
		$params['oauth_token'] = $accessToken;
	}
	$params = _xfac_api_encrypt($config, $params, 'password', $password);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

	$body = curl_exec($ch);
	curl_close($ch);

	$parts = @json_decode($body, true);

	if (!empty($parts['user']))
	{
		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_api_filterHtmlFromXenForo($html)
{
	$offset = 0;
	while (true)
	{
		if (preg_match('#<img[^>]+mceSmilie[^>]+alt="([^"]+)"[^>]+>#', $html, $matches, PREG_OFFSET_CAPTURE, $offset))
		{
			// replace smilies with their text representation
			$html = substr_replace($html, $matches[1][0], $matches[0][1], strlen($matches[0][0]));
			$offset = $matches[0][1] + 1;
		}
		else
		{
			break;
		}
	}

	return $html;
}

function _xfac_api_getFailedResponse($json)
{
	if (isset($json['errors']))
	{
		$GLOBALS['_xfac_api_lastErrors'] = $json['errors'];
	}
	elseif (isset($json['error']))
	{
		$GLOBALS['_xfac_api_lastErrors'] = array($json['error']);
	}
	else
	{
		$GLOBALS['_xfac_api_lastErrors'] = $json;
	}

	return false;
}

function _xfac_api_prepareAccessTokenBody($body)
{
	$parts = @json_decode($body, true);

	if (!empty($parts['access_token']))
	{
		if (!empty($parts['expires_in']))
		{
			$parts['expire_date'] = time() + $parts['expires_in'];
		}

		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function _xfac_api_encrypt($config, $array, $arrayKey, $data)
{
	if (!function_exists('mcrypt_encrypt'))
	{
		$array[$arrayKey] = $data;
		return $array;
	}

	$encryptKey = $config['clientSecret'];
	$encryptKey = md5($encryptKey, true);
	$padding = 16 - (strlen($data) % 16);
	$data .= str_repeat(chr($padding), $padding);

	$encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $encryptKey, $data, MCRYPT_MODE_ECB);
	$encrypted = base64_encode($encrypted);

	$array[$arrayKey] = $encrypted;
	$array[sprintf('%s_algo', $arrayKey)] = 'aes128';
	return $array;
}
