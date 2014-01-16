<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_wp_update_comment_count($postId, $new, $old)
{
	if (!empty($GLOBALS['XFAC_SKIP_xfac_wp_update_comment_count']))
	{
		return;
	}

	if ($new > $old)
	{
		$threadIds = array();
		$syncDate = array();

		$postSyncRecords = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'thread', $postId);
		foreach ($postSyncRecords as $postSyncRecord)
		{
			$threadIds[$postSyncRecord->syncData['forumId']] = $postSyncRecord->provider_content_id;
			$syncDate[$postSyncRecord->provider_content_id] = $postSyncRecord->sync_date;
		}
		if (empty($threadIds))
		{
			return;
		}

		$comments = get_approved_comments($postId);
		$newSyncDate = array();

		foreach ($comments as $comment)
		{
			$commentDateGmt = xfac_sync_mysqlDateToGmtTimestamp($comment->comment_date_gmt);
			foreach ($threadIds as $forumId => $threadId)
			{
				if ($commentDateGmt > $syncDate[$threadId])
				{
					// this comment hasn't been pushed yet, do it now
					$xfPost = xfac_syncComment_pushComment($threadId, $comment);

					if (!empty($xfPost['post']['post_id']))
					{
						xfac_sync_updateRecord('', 'post', $xfPost['post']['post_id'], $comment->comment_ID, 0, array(
							'forumId' => $forumId,
							'threadId' => $threadId,
							'post' => $xfPost['post'],
							'direction' => 'push',
						));

						$newSyncDate[$threadId] = true;
					}
				}
			}
		}

		if (!empty($newSyncDate))
		{
			foreach ($newSyncDate as $threadId => $tmp)
			{
				foreach ($postSyncRecords as $postSyncRecord)
				{
					if ($threadId == $postSyncRecord->provider_content_id)
					{
						xfac_sync_updateRecordDate($postSyncRecord);
					}
				}
			}
		}
	}
}

add_action('wp_update_comment_count', 'xfac_wp_update_comment_count', 10, 3);

function xfac_syncComment_pushComment($xfThreadId, $wfComment)
{
	$accessToken = xfac_user_getAccessToken($wfComment->user_id);

	if (empty($accessToken))
	{
		return false;
	}

	$root = get_option('xfac_root');
	$clientId = get_option('xfac_client_id');
	$clientSecret = get_option('xfac_client_secret');

	if (empty($root) OR empty($clientId) OR empty($clientSecret))
	{
		return false;
	}

	return xfac_api_postPost($root, $clientId, $clientSecret, $accessToken, $xfThreadId, $wfComment->comment_content);
}

function xfac_syncComment_cron()
{
	$root = get_option('xfac_root');
	$clientId = get_option('xfac_client_id');
	$clientSecret = get_option('xfac_client_secret');

	if (empty($root) OR empty($clientId) OR empty($clientSecret))
	{
		return;
	}

	$postSyncRecords = xfac_sync_getRecordsByProviderTypeAndRecent('', 'thread');

	foreach ($postSyncRecords as $postSyncRecord)
	{
		$page = 1;
		$pulledSomething = false;
		
		if (time() - $postSyncRecord->sync_date < 60)
		{
			// do not try to sync every minute...
			continue;
		}

		while (true)
		{
			$xfPosts = xfac_api_getPostsInThread($root, $clientId, $clientSecret, $postSyncRecord->provider_content_id, $page);

			// increase page for next request
			$page++;

			if (empty($xfPosts['posts']))
			{
				break;
			}

			$xfPostIds = array();
			foreach ($xfPosts['posts'] as $xfPost)
			{
				$xfPostIds[] = $xfPost['post_id'];
			}
			$commentSyncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'post', $xfPostIds);

			foreach ($xfPosts['posts'] as $xfPost)
			{
				if (!empty($xfPost['post_is_first_post']))
				{
					// do not pull first post
					continue;
				}

				foreach ($commentSyncRecords as $commentSyncRecord)
				{
					if ($commentSyncRecord->provider_content_id == $xfPost['post_id'] AND !empty($commentSyncRecord->syncData['direction']) AND $commentSyncRecord->syncData['direction'] === 'pull')
					{
						// stop the foreach and the outside while too
						break 3;
					}
				}

				$commentId = xfac_syncPost_pullComment($xfPost, $postSyncRecord->sync_id);

				if ($commentId > 0)
				{
					$pulledSomething = true;
				}
			}
		}

		if ($pulledSomething)
		{
			xfac_sync_updateRecordDate($postSyncRecord);
		}
	}
}

add_action('xfac_cron_hourly', 'xfac_syncComment_cron');

function xfac_syncPost_pullComment($xfPost, $wfPostId)
{
	$wfUserData = xfac_user_getUserDataByApiData(get_option('xfac_root'), $xfPost['poster_user_id']);
	if (empty($wfUserData))
	{
		return 0;
	}

	$commentDateGmt = gmdate('Y-m-d H:i:s', $xfPost['post_create_date']);
	$commentDate = get_date_from_gmt($commentDateGmt);

	$comment = array(
		'comment_post_ID' => $wfPostId,
		'comment_author' => $wfUserData->display_name,
		'comment_author_email' => $wfUserData->user_email,
		'comment_author_url' => $wfUserData->user_url,
		'comment_content' => $xfPost['post_body'],
		'user_id' => $wfUserData->ID,
		'comment_date_gmt' => $commentDateGmt,
		'comment_date' => $commentDate,
		'comment_approved' => 1,
	);

	$GLOBALS['XFAC_SKIP_xfac_wp_update_comment_count'] = true;
	$commentId = wp_insert_comment($comment);
	$GLOBALS['XFAC_SKIP_xfac_wp_update_comment_count'] = false;

	if ($commentId > 0)
	{
		xfac_sync_updateRecord('', 'post', $xfPost['post_id'], $commentId, 0, array(
			'post' => $xfPost,
			'direction' => 'pull',
		));
	}

	return $commentId;
}
