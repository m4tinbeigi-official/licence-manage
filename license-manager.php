<?php
/*
Plugin Name: License Manager
Description: Manage email and licenses, provide a shortcode to display user license.
Version: 1.2
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

        if ($license) {
            ob_start(); // Start output buffering
            ?>
            <div>
                Your License: <span id="license-text"><?php echo esc_html($license); ?></span>
                <button id="copy-license" style="margin-left: 10px;">Copy</button>
            </div>
            <script>
                document.getElementById('copy-license').onclick = function() {
                    var licenseText = document.getElementById('license-text').innerText;
                    navigator.clipboard.writeText(licenseText).then(function() {
                        alert('License copied to clipboard!');
                    }, function(err) {
                        alert('Failed to copy: ', err);
                    });
                };
            </script>
            <?php
            return ob_get_clean(); // Return the buffered content
        }
        return "No license found for your email.";
    }
    return "Please log in to view your license.";
}
add_shortcode('user_license', 'lm_license_shortcode');

// Add admin menu
function lm_add_admin_menu() {
    add_menu_page('License Manager', 'License Manager', 'manage_options', 'license-manager', 'lm_admin_page');
}
add_action('admin_menu', 'lm_add_admin_menu');

// Admin page for adding, importing, and managing licenses
function lm_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'licenses';

    // Handle add, edit, and delete actions
    if (isset($_POST['lm_add_license'])) {
        if (!wp_verify_nonce($_POST['lm_add_license_nonce'], 'lm_add_license_action')) {
            die('Security check failed');
        }
        lm_add_license($_POST['lm_email'], $_POST['lm_license']);
        echo "<div class='updated'><p>License added successfully!</p></div>";
    }

    if (isset($_POST['lm_delete_license'])) {
        $id = intval($_POST['license_id']);
        $wpdb->delete($table_name, ['id' => $id]);
        echo "<div class='updated'><p>License deleted successfully!</p></div>";
    }

    if (isset($_POST['lm_edit_license'])) {
        if (!wp_verify_nonce($_POST['lm_edit_license_nonce'], 'lm_edit_license_action')) {
            die('Security check failed');
        }
        $id = intval($_POST['license_id']);
        $email = sanitize_email($_POST['lm_email']);
        $license = sanitize_text_field($_POST['lm_license']);
        $wpdb->update($table_name, ['email' => $email, 'license' => $license], ['id' => $id]);
        echo "<div class='updated'><p>License updated successfully!</p></div>";
    }

    // Fetch all licenses after any action
    $licenses = $wpdb->get_results("SELECT * FROM $table_name");

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

        <h2>Download Sample CSV</h2>
        <p>
            You can download a sample CSV file to help you format your license data:
            <a href="<?php echo plugin_dir_url(__FILE__) . 'sample.csv'; ?>" class="button">Download Sample CSV</a>
        </p>

        <h2>Manage Licenses</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>License</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($licenses): ?>
                    <?php foreach ($licenses as $license): ?>
                        <tr>
                            <td><?php echo esc_html($license->email); ?></td>
                            <td><?php echo esc_html($license->license); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('lm_edit_license_action', 'lm_edit_license_nonce'); ?>
                                    <input type="hidden" name="license_id" value="<?php echo intval($license->id); ?>">
                                    <input type="email" name="lm_email" value="<?php echo esc_attr($license->email); ?>" required>
                                    <input type="text" name="lm_license" value="<?php echo esc_attr($license->license); ?>" required>
                                    <input type="submit" name="lm_edit_license" value="Edit" class="button button-secondary">
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="license_id" value="<?php echo intval($license->id); ?>">
                                    <input type="submit" name="lm_delete_license" value="Delete" class="button button-danger" onclick="return confirm('Are you sure you want to delete this license?');">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No licenses found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}