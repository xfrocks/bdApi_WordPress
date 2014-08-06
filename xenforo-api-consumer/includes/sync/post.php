<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_transition_post_status($newStatus, $oldStatus, $post)
{
	if (!empty($GLOBALS['XFAC_SKIP_xfac_transition_post_status']))
	{
		return;
	}

	if ($newStatus == 'publish')
	{
		$tagForumMappings = get_option('xfac_tag_forum_mappings');
		if (empty($tagForumMappings))
		{
			return;
		}

		$forumIds = array();

		foreach ($tagForumMappings as $tagForumMapping)
		{
			if (!empty($tagForumMapping['term_id']))
			{
				if (is_object_in_term($post->ID, 'post_tag', $tagForumMapping['term_id']))
				{
					$forumIds[] = $tagForumMapping['forum_id'];
				}
			}
		}

		if (!empty($forumIds))
		{
			$accessToken = xfac_user_getAccessToken($post->post_author);

			if (!empty($accessToken))
			{
				$config = xfac_option_getConfig();

				if (!empty($config))
				{
					$postSyncRecords = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'thread', $post->ID);
					foreach (array_keys($forumIds) as $key)
					{
						foreach ($postSyncRecords as $postSyncRecord)
						{
							if (!empty($postSyncRecord->syncData['forumId']) AND $postSyncRecord->syncData['forumId'] == $forumIds[$key])
							{
								unset($forumIds[$key]);
							}
						}
					}

					foreach ($forumIds as $forumId)
					{
						$postBody = _xfac_syncPost_getPostBody($post);

						$thread = xfac_api_postThread($config, $accessToken, $forumId, $post->post_title, $postBody);

						if (!empty($thread['thread']['thread_id']))
						{
							xfac_sync_updateRecord('', 'thread', $thread['thread']['thread_id'], $post->ID, 0, array(
								'forumId' => $forumId,
								'thread' => $thread['thread'],
								'direction' => 'push',
							));
						}
					}
				}
			}
		}
	}
}

if (intval(get_option('xfac_sync_post_wp_xf')) > 0)
{
	add_action('transition_post_status', 'xfac_transition_post_status', 10, 3);
}

function xfac_syncPost_cron()
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return;
	}

	$systemTags = get_terms('post_tag', array('hide_empty' => false));
	if (empty($systemTags))
	{
		return;
	}

	$tagForumMappings = get_option('xfac_tag_forum_mappings');
	if (empty($tagForumMappings))
	{
		return;
	}

	$mappedTags = array();
	foreach ($tagForumMappings as $tagForumMapping)
	{
		if (!empty($tagForumMapping['forum_id']))
		{
			if (empty($mappedTags[$tagForumMapping['forum_id']]))
			{
				$mappedTags[$tagForumMapping['forum_id']] = array();
			}

			foreach ($systemTags as $systemTag)
			{
				if ($systemTag->term_id == $tagForumMapping['term_id'])
				{
					$mappedTags[$tagForumMapping['forum_id']][] = $systemTag->name;
				}
			}

		}
	}

	if (empty($mappedTags))
	{
		return;
	}

	foreach ($mappedTags as $forumId => $tagNames)
	{
		// sync sticky threads first
		$stickyThreads = xfac_api_getThreadsInForum($config, $forumId, 1, '', 'sticky=1');
		if (!empty($stickyThreads['threads']))
		{
			$threadIds = array();
			foreach ($stickyThreads['threads'] as $thread)
			{
				$threadIds[] = $thread['thread_id'];
			}
			$syncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'thread', $threadIds);

			foreach ($stickyThreads['threads'] as $thread)
			{
				$synced = false;

				foreach ($syncRecords as $syncRecord)
				{
					if ($syncRecord->provider_content_id == $thread['thread_id'])
					{
						$synced = true;
					}
				}

				if (!$synced)
				{
					$wpPostId = xfac_syncPost_pullPost($thread, $tagNames);
				}
			}
		}

		// now start syncing normal threads
		$page = 1;

		while (true)
		{
			$threads = xfac_api_getThreadsInForum($config, $forumId, $page);

			// increase page for next request
			$page++;

			if (empty($threads['threads']))
			{
				break;
			}

			$threadIds = array();
			foreach ($threads['threads'] as $thread)
			{
				$threadIds[] = $thread['thread_id'];
			}
			$syncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'thread', $threadIds);

			foreach ($threads['threads'] as $thread)
			{
				$synced = false;

				foreach ($syncRecords as $syncRecord)
				{
					if ($syncRecord->provider_content_id == $thread['thread_id'])
					{
						$synced = true;

						if (!empty($syncRecord->syncData['direction']) AND $syncRecord->syncData['direction'] === 'pull' AND empty($syncRecord->syncData['sticky']))
						{
							// reach where we were pulling before
							// stop the foreach and the outside while too
							break 3;
						}
					}
				}

				if (!$synced)
				{
					$wpPostId = xfac_syncPost_pullPost($thread, $tagNames);
				}
			}

			if (empty($threads['links']['next']))
			{
				// there is no next page, stop
				break;
			}
		}
	}
}

if (intval(get_option('xfac_sync_post_xf_wp')) > 0)
{
	add_action('xfac_cron_hourly', 'xfac_syncPost_cron');
}

function xfac_syncPost_pullPost($thread, $tags)
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return 0;
	}

	$postAuthor = 0;
	$wpUserData = xfac_user_getUserDataByApiData($config['root'], $thread['creator_user_id']);
	if (empty($wpUserData))
	{
		return 0;
	}
	$postAuthor = $wpUserData->ID;

	$wpUser = new WP_User($wpUserData);
	$postTypeObj = get_post_type_object('post');
	if (empty($postTypeObj))
	{
		// no post type object?!
		return 0;
	}
	if (!$wpUser->has_cap($postTypeObj->cap->create_posts))
	{
		// no permission to create posts
		return 0;
	}

	$postDateGmt = gmdate('Y-m-d H:i:s', $thread['thread_create_date']);
	$postDate = get_date_from_gmt($postDateGmt);

	$postStatus = 'draft';
	if (intval(get_option('xfac_sync_post_xf_wp_publish')) > 0)
	{
		$postStatus = 'publish';
	}

	$postContent = xfac_api_filterHtmlFromXenForo($thread['first_post']['post_body_html']);

	$wpPost = array(
		'post_author' => $postAuthor,
		'post_content' => $postContent,
		'post_date' => $postDate,
		'post_date_gmt' => $postDateGmt,
		'post_status' => $postStatus,
		'post_title' => $thread['thread_title'],
		'post_type' => 'post',
		'tags_input' => implode(', ', $tags),
	);

	$GLOBALS['XFAC_SKIP_xfac_transition_post_status'] = true;
	$wpPostId = wp_insert_post($wpPost);
	$GLOBALS['XFAC_SKIP_xfac_transition_post_status'] = false;

	if ($wpPostId > 0)
	{
		xfac_sync_updateRecord('', 'thread', $thread['thread_id'], $wpPostId, $thread['thread_create_date'], array(
			'forumId' => $thread['forum_id'],
			'thread' => $thread,
			'direction' => 'pull',
			'sticky' => !empty($thread['thread_is_sticky']),
		));
	}

	return $wpPostId;
}

function _xfac_syncPost_getPostBody($post)
{
	if (!!get_option('xfac_sync_post_wp_xf_excerpt'))
	{
		// this method is implemented with ideas from get_the_excerpt()
		$text = $post->post_excerpt;

		if (empty($text))
		{
			$text = $post->post_content;

			$text = strip_shortcodes($text);

			$text = apply_filters('the_content', $text);
			$text = str_replace(']]>', ']]&gt;', $text);

			$excerptLength = apply_filters('excerpt_length', 55);

			$excerptMore = apply_filters('excerpt_more', ' ' . '[&hellip;]');
			$text = wp_trim_words($text, $excerptLength, $excerptMore);
		}

		$text = apply_filters('wp_trim_excerpt', $text, $raw_excerpt);
	}
	else
	{
		$text = $post->post_content;

		$text = apply_filters('the_content', $text);
		$text = str_replace(']]>', ']]&gt;', $text);
	}

	// fix paragraph spacing from WordPress to XenForo
	$text = str_replace('</p>', '</p><br />', $text);

	if (!!get_option('xfac_sync_post_wp_xf_link'))
	{
		$text .= '<br /><a href="' . get_permalink($post->ID) . '">' . __('Read the whole post here.', 'xenforo-api-consumer') . '</a>';
	}

	return $text;
}
