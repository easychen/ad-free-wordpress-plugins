<?php
/**
 * Plugin Name: BCWP Slug
 * Description: A plugin to generate article slugs using OpenAI API.
 * Version: 1.0.4
 * Author: Easy
 * Author URI: https://ft07.com
 * License: GPL2+
 * Text Domain: bcwp-slug
 */

defined('ABSPATH') || exit;

// Register Settings Page
function bcwp_slug_register_settings_page()
{
    add_options_page(
        'BCWP Slug Settings',
        'BCWP Slug',
        'manage_options',
        'bcwp-slug',
        'bcwp_slug_render_settings_page'
    );
}
add_action('admin_menu', 'bcwp_slug_register_settings_page');

// Render Settings Page
function bcwp_slug_render_settings_page()
{
    ?>
<div class="wrap">
	<h1>BCWP Slug Settings</h1>
	<form method="post" action="options.php">
		<?php
            settings_fields('bcwp_slug_settings_group');
    do_settings_sections('bcwp-slug');
    submit_button();
    ?>
	</form>
</div>
<?php
}

// Register Settings
function bcwp_slug_register_settings()
{
    register_setting('bcwp_slug_settings_group', 'bcwp_slug_api_key');
    register_setting('bcwp_slug_settings_group', 'bcwp_slug_model');
    register_setting('bcwp_slug_settings_group', 'bcwp_slug_api_base');

    add_settings_section('bcwp_slug_settings_section', '', null, 'bcwp-slug');

    add_settings_field(
        'bcwp_slug_api_key',
        'OpenAI API Key',
        'bcwp_slug_render_api_key_field',
        'bcwp-slug',
        'bcwp_slug_settings_section'
    );

    add_settings_field(
        'bcwp_slug_model',
        'OpenAI Model',
        'bcwp_slug_render_model_field',
        'bcwp-slug',
        'bcwp_slug_settings_section'
    );

    add_settings_field(
        'bcwp_slug_api_base',
        'OpenAI API Base',
        'bcwp_slug_render_api_base_field',
        'bcwp-slug',
        'bcwp_slug_settings_section'
    );
}
add_action('admin_init', 'bcwp_slug_register_settings');

// Field Rendering Functions
function bcwp_slug_render_api_key_field()
{
    $api_key = get_option('bcwp_slug_api_key');
    echo "<input type='text' name='bcwp_slug_api_key' value='" . esc_attr($api_key) . "' />";
}

function bcwp_slug_render_model_field()
{
    $model = get_option('bcwp_slug_model', 'gpt-4o');
    echo "<input type='text' name='bcwp_slug_model' value='" . esc_attr($model) . "' />";
}

function bcwp_slug_render_api_base_field()
{
    $api_base = get_option('bcwp_slug_api_base', 'https://api.openai.com');
    echo "<input type='text' name='bcwp_slug_api_base' value='" . esc_attr($api_base) . "' />";
}

// Enqueue Scripts for Block Editor
function bcwp_slug_enqueue_block_editor_assets()
{
    wp_enqueue_script(
        'bcwp-slug-editor-script',
        plugin_dir_url(__FILE__) . 'js/editor.js',
        array('wp-blocks', 'wp-element', 'wp-edit-post'),
        filemtime(plugin_dir_path(__FILE__) . 'js/editor.js')
    );

    wp_localize_script('bcwp-slug-editor-script', 'bcwpSlug', array(
        'restUrl' => esc_url_raw(rest_url('bcwp-slug/v1/generate-slug')),
        'nonce' => wp_create_nonce('wp_rest')
    ));
}
add_action('enqueue_block_editor_assets', 'bcwp_slug_enqueue_block_editor_assets');

// Register REST API Endpoint
function bcwp_slug_register_rest_route()
{
    register_rest_route('bcwp-slug/v1', '/generate-slug', array(
        'methods' => 'POST',
        'callback' => 'bcwp_slug_generate_slug',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('rest_api_init', 'bcwp_slug_register_rest_route');

// Generate Slug Callback
function bcwp_slug_generate_slug(WP_REST_Request $request)
{
    $title = sanitize_text_field($request->get_param('title'));

    if (empty($title)) {
        return new WP_Error('no_title', 'Title is required', array('status' => 400));
    }

    $api_key = get_option('bcwp_slug_api_key');
    $model = get_option('bcwp_slug_model', 'gpt-4');
    $api_base = get_option('bcwp_slug_api_base', 'https://api.openai.com');

    require_once __DIR__ . '/vendor/autoload.php';

    try {
        $client = OpenAI::factory()
            ->withApiKey($api_key)
            ->withBaseUri($api_base)
            ->make();
        $result = $client->chat()->create([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => "Please generate a short and concise WordPress article slug using English words based on the article title <title>$title</title>, less than 50 chars, here is the slug:"],
            ],
        ]);

        $slug = sanitize_title($result->choices[0]->message->content);

        if (empty($slug)) {
            return new WP_Error('empty_slug', 'Generated slug is empty', array('status' => 500));
        }

        return array('slug' => $slug);
    } catch (Exception $e) {
        return new WP_Error('api_error', $e->getMessage(), array('status' => 500));
    }
}

// Plugin Action Links
function bcwp_slug_action_links($links)
{
    $settings_link = '<a href="options-general.php?page=bcwp-slug">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bcwp_slug_action_links');
?>