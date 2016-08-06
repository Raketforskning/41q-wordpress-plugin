<?php

/**
 * Personality Test Plugin for Wordpress.
 *
 * @link              http://41q.com
 *
 * @wordpress-plugin
 * Plugin Name:       Personality Test Plugin for Wordpress
 * Plugin URI:        https://www.41q.com/wordpress-plugin.41q
 * Description:       This is an official Wordpress plugin which enables the 41 Question Personality Test
 * Version:           0.1.0
 * Author:            Raketforskning Sverige AB
 * Author URI:        http://raketforskning.se/
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       41q-personality-test
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The code that runs during plugin activation.
 */
function activate_41q_personality_test()
{
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_41q_personality_test()
{
}

register_activation_hook(__FILE__, 'activate_41q_personality_test');
register_deactivation_hook(__FILE__, 'deactivate_41q_personality_test');

/**
 * The core (external) plugin class.
 */
require plugin_dir_path(__FILE__).'41q-sdk/class-41q.php';

/**
 * Begins execution of the plugin.
 */
function run_41q_personality_test()
{
    Sdk41q::setup(
        get_option('41q_personality_test_client_id', ''),
        get_option('41q_personality_test_client_secret', ''),
        get_option('41q_personality_test_lang', ''),
        json_decode(get_transient('personality_test_questions_cache'))
    );
    Sdk41q::$_ajaxUrl = admin_url('admin-ajax.php');
    Sdk41q::$_urlExtra = '?action=personality_test__action';

    add_shortcode('personality-test', 'personality_test_41q_shortcode');
}

/**
 * Setup shortcode.
 */
function personality_test_41q_shortcode($atts, $content)
{
    $a = shortcode_atts(array(
    'count' => 20,
    'response_parts' => 'question+answer+help_head+help_text',
    ), $atts);

  // Content
  $content = Sdk41q::render_questions($a);

  // Save cache of questions
  if (!Sdk41q::$_previousQuestionCache) {
      set_transient('personality_test_questions_cache', json_encode(Sdk41q::$_questionCache), (1 * WEEK_IN_SECONDS));
  }

    return $content;
}

/**
 * Result handling.
 */
function personality_test_result_ajax_callback()
{
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON);
    do_action('personality_test_result_before', $input);

    $input->response_parts = 'title+description+jobs+famous_people+bars';
    $response = Sdk41q::result($input);

    if ($response->code == 200) {
        header('HTTP/1.1 200');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($response->result);
    } else {
        header('HTTP/1.1 400');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($response);
    }

    do_action('personality_test_result_after', $input);
    wp_die(); // this is required to terminate immediately and return a proper response
}

add_action('wp_ajax_personality_test_result', 'personality_test_result_ajax_callback');
add_action('wp_ajax_nopriv_personality_test_result', 'personality_test_result_ajax_callback');

/**
 * Add admin page.
 */
function personality_test_admin_page()
{
    echo '<h1>'._('41Q Personality Test Settings').'</h1>';

    echo '<style type="text/css">';
    echo '.q41q-admin-form label, #enter-details label { display:inline-block; width:200px; }';
    echo '.q41q-admin-form input[type="text"], #enter-details input[type="text"] { display:inline-block; width:500px; }';
    echo '.q41q-admin-form .shortcode { display:inline-block; background-color:white; padding:4px; font-family:Courier;margin:0;font-size:150%; }';
    echo '</style>';

    if (!empty($_POST['41q_personality_test_client_id'])) {
        update_option('41q_personality_test_client_id', $_POST['41q_personality_test_client_id']);
        update_option('41q_personality_test_client_secret', $_POST['41q_personality_test_client_secret']);
        update_option('41q_personality_test_lang', $_POST['41q_personality_test_lang']);
    }

    $client_id = get_option('41q_personality_test_client_id', '');
    $client_secret = get_option('41q_personality_test_client_secret', '');
    $lang = get_option('41q_personality_test_lang', 'en');

    if (!empty($_GET['show-options']) || !empty($client_id)) {
        echo '<form action="admin.php?page=personality-test&show-options=1" method="POST" class="q41q-admin-form">';

        if (!empty($client_id)) {
            echo '<p>'._('You can now use the 41Q shortcode almost wherever you want in your Wordpress installation (e.g in your posts or pages, widgets or in your theme). Use the following shortcode').':</p>';
            echo '<p class="shortcode">[personality-test]</p>';
            echo '<p>'._('Read more about shortcodes here').': <a href="https://codex.wordpress.org/Shortcode" target="_blank">https://codex.wordpress.org/Shortcode</a></p>';

            echo '<hr />';

            echo '<p>'._('Edit your API information below').':</p>';
        } else {
            echo '<p>'._('Enter your API information below').':</p>';
        }

        echo '<p><label>'._('Client ID').'</label><input type="text" name="41q_personality_test_client_id" placeholder="'._('Client ID').'" value="'.$client_id.'" /><p>';
        echo '<p><label>'._('Client Secret').'</label><input type="text" name="41q_personality_test_client_secret" placeholder="'._('Client Secret').'" value="'.$client_secret.'" /><p>';
        echo '<p><label>'._('Language').'</label><select name="41q_personality_test_lang" disabled="disabled"><option value="en">English</option></select><p>';
        echo '<p><input type="submit" value="'._('Save').'" /><p>';

        echo '<br /><br /><br /><hr />';

        echo '<p>'._('Manage your 41Q API account here').': <a href="http://api.41q.com/wp-admin/" target="_blank">http://api.41q.com/wp-admin/</a>. Use the details below.</p>';
        echo '<p><label>'._('41Q API username').'</label>'.get_option('41q_personality_test_username').'<p>';
        echo '<p><label>'._('41Q API email').'</label>'.get_option('41q_personality_test_email').'<p>';

        echo '</form>';
    } else {
        echo '<div id="have-details" style="display:block;">';
        echo '<p>'._('Do you already have authentication information for the 41Q Personality Test API?').'</p>';
        echo '<span class="button btn" onclick="document.getElementById(\'have-details\').style = \'display:none;\'; document.getElementById(\'enter-details\').style = \'display:block;\';">'._('No, get it for me').'</span>';
        echo ' &nbsp; ';
        echo '<a class="button btn" id="yes-have-details" href="admin.php?page=personality-test&show-options=1">'._('Yes').'</a>';
        echo '</div>';

        global $current_user;
        get_currentuserinfo();

        echo '<div id="enter-details" style="display:none;">';
        echo '<p><label>'._('Your email address').': </label><input type="text" id="your-email" placeholder="'._('Your email').'" value="'.$current_user->user_email.'" /><p>';
        echo '<a class="button btn" onclick="window.location.href = \'admin-post.php?action=personality_test_create_api_details&email=\' + encodeURI(document.getElementById(\'your-email\').value);">'._('Get my details').'</a>';
        echo ' &nbsp; ';
        echo '<a href="#" onclick="document.getElementById(\'have-details\').style = \'display:block;\'; document.getElementById(\'enter-details\').style = \'display:none;\';">'._('Cancel').'</a>';
        echo '</div>';
    }
}

function q41q_message_notice()
{
    if ($_GET['ms'] === '0') {
        ?>
    <div class="error notice">
        <p><?php echo $_GET['msg'];
        ?></p>
    </div>
    <?php

    } elseif ($_GET['ms'] === '1') {
        ?>
    <div class="updated notice">
        <p><?php echo $_GET['msg'];
        ?></p>
    </div>
    <?php

    }
}
add_action('admin_notices', 'q41q_message_notice');

function personality_test_menu()
{
    add_menu_page(_('Personality test'), 'Personality test', 'publish_posts', 'personality-test', 'personality_test_admin_page');
}

add_action('admin_menu', 'personality_test_menu');

/**
 * Admin, create API details.
 */
function personality_test_create_api_details()
{
    $data = Sdk41q::request_api_details(urldecode($_GET['email']), $_SERVER['HTTP_HOST'], 'wp');

    if (!$data->result->error) {
        update_option('41q_personality_test_client_id', $data->result->client_id);
        update_option('41q_personality_test_client_secret', $data->result->client_secret);
        update_option('41q_personality_test_client_public_key', $data->result->client_public_key);
        update_option('41q_personality_test_username', $data->result->username);
        update_option('41q_personality_test_email', $data->result->email);
        update_option('41q_personality_test_lang', 'en');

        wp_redirect('admin.php?page=personality-test&ms=1&msg='.urlencode(_('Your API account request was accepted! You need to activate your account (check your email inbox), but then you are ready to go!')));
        exit();
    } else {
        wp_redirect('admin.php?page=personality-test&ms=0&msg='.urlencode(_('Failed with code ').$data->result->code.': '.$data->result->text.'<br /><br />'._('Read more here').': <a target="_blank" href="https://github.com/Raketforskning/41q-documentation/wiki/API-documentation#4-errors">https://github.com/Raketforskning/41q-documentation/wiki/API-documentation#4-errors</a>.'));
        exit();
    }

    exit();
}

add_action('admin_post_personality_test_create_api_details', 'personality_test_create_api_details');

/*
 * Do the setup
 */
run_41q_personality_test();
