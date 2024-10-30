<?php
/*
  Plugin Name: HTACCESS IP Blocker
  Description: Blocks failed attempted IPs in htaccess
  Version:     1.0
  Author:      Taraprasad Swain
  Author URI:  https://www.taraprasad.com
  License:     GPL2

  HTACCESS IP Blocker is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 2 of the License, or
  any later version.

  HTACCESS IP Blocker is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with HTACCESS IP Blocker. If not, see https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('WPINC')) {
    die;
}

define('IPBLOCK_COUNT', 3);

define('IPBLOCK_INTERVAL', 5);

add_action('wp_login_failed', 'ipblock_wp_login_failed');

function ipblock_wp_login_failed($username = '') {

    if (get_option('ipblock_enabled', 0) == 0) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'];

    $option_key = 'ipblock_' . $ip;

    $timer_key = $option_key . '_time';

    $counter = (int) get_option($option_key, 1);

    $current_time = date('Y-m-d H:i:s');

    $_ipblock_interval = (int) get_option('ipblock_maxcount', IPBLOCK_INTERVAL) * 60;

    $_ipblock_maxcount = get_option('ipblock_maxcount', IPBLOCK_COUNT);
    $last_failed_time = get_option($timer_key);

    $current_time_stamp = strtotime($current_time);
    $last_failed_time_stamp = ($last_failed_time != false) ? strtotime($last_failed_time) : $current_time_stamp;

    if ($last_failed_time == false or abs($current_time_stamp - $last_failed_time_stamp) > $_ipblock_interval) {
        update_option($timer_key, $current_time);
        update_option($option_key, 1);
        $counter = 1;
    }

    if ($counter >= $_ipblock_maxcount and abs($current_time_stamp - $last_failed_time_stamp) <= $_ipblock_interval) {
        include(ABSPATH . 'wp-admin/includes/misc.php');
        delete_option($option_key);
        delete_option($timer_key);

        $lines_str = get_option('ipblock_ips', '');

        $lines = ($lines_str != '') ? unserialize($lines_str) : [];

        $lines[] = "Deny from {$ip}";

        $lines = array_unique($lines);

        $htaccess = ABSPATH . ".htaccess";

        insert_with_markers($htaccess, 'IPBlocker', $lines);

        update_option('ipblock_ips', serialize($lines));

        return;
    }

    $counter++;

    update_option($option_key, $counter);
}

function ipblock_setting_page() {
    add_submenu_page(
            'tools.php', 'HTACCESS IP Blocker', 'HTACCESS IP Blocker', 'manage_options', 'ipblockersettings', 'ipblockersettings_callback', 'dashicons-lock', 6
    );
}

function ipblockersettings_callback() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        update_option('ipblock_enabled', (isset($_POST['_ipblock_enabled']) ? (int) $_POST['_ipblock_enabled'] : 0));
        update_option('ipblock_maxcount', (isset($_POST['_ipblock_maxcount']) ? (int) $_POST['_ipblock_maxcount'] : IPBLOCK_COUNT));
        update_option('ipblock_interval', (isset($_POST['_ipblock_interval']) ? (int) $_POST['_ipblock_interval'] : IPBLOCK_INTERVAL));
        $_ipblock_ips_str = isset($_POST['_ipblock_ips']) ? $_POST['_ipblock_ips'] : '';
        $_ipblock_ips_arr = [];
        if ($_ipblock_ips_str != '') {
            $_ipblock_ips_arr = explode("\n", $_ipblock_ips_str);
        }

        $htaccess = ABSPATH . ".htaccess";

        insert_with_markers($htaccess, 'IPBlocker', $_ipblock_ips_arr);

        update_option('ipblock_ips', serialize($_ipblock_ips_arr));
    }
    $_ipblock_maxcount = get_option('ipblock_maxcount', IPBLOCK_COUNT);
    $_ipblock_enabled = get_option('ipblock_enabled', 0);
    $_ipblock_interval = get_option('ipblock_interval', IPBLOCK_INTERVAL);
    $_ipblock_ips = get_option('ipblock_ips', '');
    $_ipblock_ips_arr = ($_ipblock_ips != '') ? unserialize($_ipblock_ips) : [];
    $_ipblock_ips_str = join("\n", $_ipblock_ips_arr);
    ?>
    <div class="wrap">
        <h1>HTACCESS IP Blocker Settings</h1>
        <form method="post">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">Enable</th>
                        <td id="front-static-pages">
                            <fieldset>
                                <p>
                                    <label>
                                        <input name="_ipblock_enabled" value="1" class="tog" type="radio"<?php echo ($_ipblock_enabled == '1') ? ' checked="checked"' : ''; ?> />
                                        Yes
                                    </label>
                                </p>
                                <p>
                                    <label>
                                        <input name="_ipblock_enabled" value="0" class="tog" type="radio"<?php echo ($_ipblock_enabled == '0') ? ' checked="checked"' : ''; ?> />
                                        No
                                    </label>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="_ipblock_ips">Max Failed Attempts</label></th>
                        <td>
                            <input type="text" class="regular-text code" name="_ipblock_maxcount" id="_ipblock_maxcount" value="<?php echo $_ipblock_maxcount; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="_ipblock_interval">Failed Attempts Interval</label></th>
                        <td>
                            <input type="text" class="regular-text code" name="_ipblock_interval" id="_ipblock_interval" value="<?php echo $_ipblock_interval; ?>" />
                            <br />
                            In minutes
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="_ipblock_ips">Blocked IPs</label></th>
                        <td>
                            <textarea class="regular-text code" name="_ipblock_ips" id="_ipblock_ips"><?php echo $_ipblock_ips_str; ?></textarea>
                            <br />
                            Be careful, this is going to be added into .htaccess file.<br />
                            To put IP in block list enter "Deny from 127.0.0.1"
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p>
        </form>
    </div>
    <?php
}

add_action('admin_menu', 'ipblock_setting_page');

function ipblock_settings_link($links) {
    $settings_link = '<a href="' . admin_url('tools.php?page=ipblockersettings') . '">Settings</a>';
    array_push($links, $settings_link);
    return $links;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'ipblock_settings_link');
