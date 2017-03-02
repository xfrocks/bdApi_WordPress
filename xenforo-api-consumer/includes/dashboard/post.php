<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_post_meta_box_info($post)
{
    $config = xfac_option_getConfig();
    /** @noinspection PhpUnusedLocalVariableInspection */
    $meta = xfac_option_getMeta($config);
    /** @noinspection PhpUnusedLocalVariableInspection */
    $records = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'thread', $post->ID);

    /** @noinspection PhpIncludeInspection */
    require(xfac_template_locateTemplate('dashboard_post_meta_box_info.php'));
}

function xfac_add_meta_boxes($postType)
{
    if ($postType !== 'post') {
        return;
    }

    add_meta_box('xfac_post_info', __('XenForo Info', 'xenforo-api-consumer'), 'xfac_post_meta_box_info', null, 'side');
}

if (intval(get_option('xfac_sync_post_wp_xf')) > 0
    || intval(get_option('xfac_sync_post_xf_wp')) > 0
) {
    add_action('add_meta_boxes', 'xfac_add_meta_boxes');
}
