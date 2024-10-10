<?php
/*
Plugin Name: License Manager
Description: Manage email and licenses, provide a shortcode to display user license.
Version: 1.1
Author: Rick Sanchez
*/

// Create table to store emails and licenses
function lm_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'licenses';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        license varchar(100) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_switch_theme', 'lm_create_table');

// Add a license
function lm_add_license($email, $license) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'licenses';
    $wpdb->insert($table_name, [
        'email' => sanitize_email($email),
        'license' => sanitize_text_field($license)
    ]);
}

// Shortcode to display user license
function lm_license_shortcode() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;

        global $wpdb;
        $table_name = $wpdb->prefix . 'licenses';
        $license = $wpdb->get_var($wpdb->prepare("SELECT license FROM $table_name WHERE email = %s", $email));

        return $license ? "Your License: " . esc_html($license) : "No license found for your email.";
    }
    return "Please log in to view your license.";
}
add_shortcode('user_license', 'lm_license_shortcode');

// Add admin menu
function lm_add_admin_menu() {
    add_menu_page('License Manager', 'License Manager', 'manage_options', 'license-manager', 'lm_admin_page');
}
add_action('admin_menu', 'lm_add_admin_menu');

// Admin page for adding and importing licenses
function lm_admin_page() {
    ?>
    <div class="wrap">
        <h1>License Manager</h1>
        
        <h2>Add Single License</h2>
        <form method="post">
            <?php wp_nonce_field('lm_add_license_action', 'lm_add_license_nonce'); ?>
            <label for="lm_email">Email:</label>
            <input type="email" name="lm_email" required>
            <label for="lm_license">License:</label>
            <input type="text" name="lm_license" required>
            <input type="submit" name="lm_add_license" value="Add License" class="button button-primary">
        </form>

        <h2>Import Licenses from CSV</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('lm_import_csv_action', 'lm_import_csv_nonce'); ?>
            <input type="file" name="lm_csv_file" accept=".csv" required>
            <input type="submit" name="lm_import_csv" value="Import Licenses" class="button button-primary">
        </form>
    </div>
    <?php

    // Process adding a license
    if (isset($_POST['lm_add_license'])) {
        if (!wp_verify_nonce($_POST['lm_add_license_nonce'], 'lm_add_license_action')) {
            die('Security check failed');
        }
        lm_add_license($_POST['lm_email'], $_POST['lm_license']);
        echo "<div class='updated'><p>License added successfully!</p></div>";
    }

    // Process importing licenses from CSV
    if (isset($_POST['lm_import_csv'])) {
        if (!wp_verify_nonce($_POST['lm_import_csv_nonce'], 'lm_import_csv_action')) {
            die('Security check failed');
        }
        if (!empty($_FILES['lm_csv_file']['tmp_name'])) {
            $file = $_FILES['lm_csv_file']['tmp_name'];
            if (mime_content_type($file) === 'text/plain' || mime_content_type($file) === 'text/csv') {
                if (($handle = fopen($file, 'r')) !== FALSE) {
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) == 2) {
                            lm_add_license($data[0], $data[1]);
                        }
                    }
                    fclose($handle);
                    echo "<div class='updated'><p>Licenses imported successfully!</p></div>";
                } else {
                    echo "<div class='error'><p>Failed to open the file.</p></div>";
                }
            } else {
                echo "<div class='error'><p>Invalid file type. Please upload a CSV file.</p></div>";
            }
        }
    }
}