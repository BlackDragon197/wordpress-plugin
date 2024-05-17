<?php
/*
 * Plugin Name: My table
 * Description: Creating some custom data and sending it to the db
 * Version: 1.0
 * Author: Yurii Tkachenko
 * Author URI: https://yurii-tkachenko.tiiny.site
 * Text Domain: table-plugin
 */

// Activating and creating table
register_activation_hook(__FILE__, 'custom_table_plugin_activate');

function custom_table_plugin_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'some_table';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(50) NOT NULL,
        email varchar(100) NOT NULL,
        message text NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue jQuery and custom script
add_action('wp_enqueue_scripts', 'custom_table_enqueue_scripts');
function custom_table_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('custom-table-ajax', plugin_dir_url(__FILE__) . 'js/custom-table-ajax.js', array('jquery'), null, true);
    wp_localize_script('custom-table-ajax', 'custom_table_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('custom_table_search_nonce')
    ));
}

// Shortcode for input form [show_input_form]
add_shortcode('custom_table_form', 'custom_table_form_shortcode');
function custom_table_form_shortcode() {
    ob_start();
    ?>
    <form id="custom-table-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="custom_table_insert">
        <input type="text" name="name" placeholder="Name" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <textarea name="message" placeholder="Message" required></textarea><br>
        <button type="submit">Submit</button>
    </form>
    <?php
    return ob_get_clean();
}

// Send form and set data to db
add_action('admin_post_custom_table_insert', 'custom_table_insert');
function custom_table_insert() {
    if (isset($_POST['name']) && isset($_POST['email']) && isset($_POST['message'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'some_table';
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $message = sanitize_textarea_field($_POST['message']);
        $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'email' => $email,
                'message' => $message,
            )
        );
    }
    wp_redirect($_SERVER['HTTP_REFERER']);
    exit;
}

// Shortcode for displaying data table and search field [show_data]
add_shortcode('custom_table_list', 'custom_table_list_shortcode');
function custom_table_list_shortcode() {
    ob_start();
    ?>
    <form id="custom-table-search" action="#" method="get">
        <input type="text" name="search" placeholder="Search">
        <button type="submit">Search</button>
    </form>
    <table id="custom-table-results">
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Message</th>
        </tr>
    </table>
    <?php
    return ob_get_clean();
}

// AJAX handler for search functionality
add_action('wp_ajax_search_data', 'handle_search_data');
add_action('wp_ajax_nopriv_search_data', 'handle_search_data');
function handle_search_data() {
    // Check the nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'custom_table_search_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'some_table';
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $sql = "SELECT * FROM $table_name WHERE name LIKE %s OR email LIKE %s OR message LIKE %s";
    $results = $wpdb->get_results($wpdb->prepare($sql, "%{$search}%", "%{$search}%", "%{$search}%"));

    wp_send_json_success($results);
}

// Bonus task. additional WordPress REST APIs
add_action('rest_api_init', 'custom_table_rest_api_init');
function custom_table_rest_api_init() {
    register_rest_route('my-table/v1', '/insert', array(
        'methods' => 'POST',
        'callback' => 'custom_table_rest_insert_callback',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));
    register_rest_route('my-table/v1', '/select', array(
        'methods' => 'GET',
        'callback' => 'custom_table_rest_select_callback',
    ));
}

function custom_table_rest_insert_callback($request) {
    $parameters = $request->get_params();
    if (isset($parameters['name']) && isset($parameters['email']) && isset($parameters['message'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'some_table';
        $name = sanitize_text_field($parameters['name']);
        $email = sanitize_email($parameters['email']);
        $message = sanitize_textarea_field($parameters['message']);
        $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'email' => $email,
                'message' => $message,
            )
        );
    }
    return new WP_REST_Response('Data post completed', 200);
}

function custom_table_rest_select_callback($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'some_table';
    $search = $request->get_param('search') ? sanitize_text_field($request->get_param('search')) : '';
    $sql = "SELECT * FROM $table_name WHERE name LIKE %s OR email LIKE %s OR message LIKE %s";
    $results = $wpdb->get_results($wpdb->prepare($sql, "%{$search}%", "%{$search}%", "%{$search}%"));
    return new WP_REST_Response($results, 200);
}
