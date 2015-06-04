<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

?>

<h3><?php _e('Connected Account', 'xenforo-api-consumer'); ?></h3>

<table class="form-table">

    <?php if (empty($apiRecords)): ?>
        <tr>
            <th>&nbsp;</th>
            <td>
                <p>
                    <a href="<?php echo site_url('wp-login.php?xfac=authorize&redirect_to=' . rawurlencode(admin_url('profile.php')), 'login_post'); ?>">
                        <?php _e('Connect to an account', 'xenforo-api-consumer'); ?>
                    </a>
                </p>
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($apiRecords as $apiRecord): ?>
            <tr>
                <th>
                    <?php if (!empty($apiRecord->profile['links']['avatar'])): ?>
                    <img src="<?php echo $apiRecord->profile['links']['avatar']; ?>" width="32" style="float: left"/>

                    <div style="margin-left: 36px">
                        <?php else: ?>
                        <div>
                            <?php endif; ?>

                            <a href="<?php echo $apiRecord->profile['links']['permalink']; ?>"
                               target="_blank"><?php echo $apiRecord->profile['username']; ?></a><br/>
                            <?php echo $apiRecord->profile['user_email']; ?>
                        </div>
                </th>
                <td valign="top">
                    <p>
                        <a href="<?php echo admin_url('profile.php?xfac=disconnect&id=' . $apiRecord->id); ?>">
                            <?php _e('Disconnect this account', 'xenforo-api-consumer'); ?>
                        </a>
                    </p>

                    <?php if (xfac_user_getAccessTokenForRecord($apiRecord) === null): ?>
                    <p>
                        <a href="<?php echo site_url('wp-login.php?xfac=1&redirect_to=' . rawurlencode(admin_url('profile.php')), 'login_post'); ?>">
                            <?php _e('Refresh connection', 'xenforo-api-consumer'); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>

</table>