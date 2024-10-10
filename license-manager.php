<?php
/*
Plugin Name: License Manager
Description: Manage email and licenses, provide a shortcode to display user licenses.
Version: 1.5
Author: Rick Sanchez
*/

// Create a custom table to store emails and licenses
function lm_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_licenses'; // Changed table name to avoid conflicts
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        license varchar(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_switch_theme', 'lm_create_table');

// Add a license
function lm_add_license($email, $license) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_licenses'; // Use the new table name
    $wpdb->insert($table_name, [
        'email' => sanitize_email($email),
        'license' => sanitize_text_field($license)
    ]);
}

// Shortcode to display user licenses
function lm_license_shortcode() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $email = $current_user->user_email; // Get the user's email address

        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_licenses'; // Use the new table name
        $licenses = $wpdb->get_results($wpdb->prepare("SELECT license FROM $table_name WHERE email = %s", $email));

        if ($licenses) {
            ob_start(); // Start output buffering
            ?>
            <div>
                <h3>Your Licenses:</h3>
                <ul>
                    <?php foreach ($licenses as $license): ?>
                        <li>
                            <span id="license-text"><?php echo esc_html($license->license); ?></span>
                            <button class="copy-license" style="margin-left: 10px;">Copy</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <script>
                const copyButtons = document.querySelectorAll('.copy-license');
                copyButtons.forEach(button => {
                    button.onclick = function() {
                        const licenseText = this.previousElementSibling.innerText;
                        navigator.clipboard.writeText(licenseText).then(function() {
                            alert('License copied to clipboard!');
                        }, function(err) {
                            alert('Failed to copy: ', err);
                        });
                    };
                });
            </script>
            <?php
            return ob_get_clean(); // Return the buffered content
        }
        return "No licenses found for your account.";
    }
    return "Please log in to view your licenses.";
}
add_shortcode('user_license', 'lm_license_shortcode');

// Add admin menu
function lm_add_admin_menu() {
    add_menu_page('License Manager', 'License Manager', 'manage_options', 'license-manager', 'lm_admin_page');
}
add_action('admin_menu', 'lm_add_admin_menu');

// Admin page for adding, importing, and managing licenses
function lm_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.'); // Check if the user is an admin
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_licenses'; // Use the new table name

    // Handle add, edit, and delete actions
    if (isset($_POST['lm_add_license'])) {
        if (!wp_verify_nonce($_POST['lm_add_license_nonce'], 'lm_add_license_action')) {
            die('Security check failed');
        }

        // Check if email is provided
        if (empty($_POST['lm_email'])) {
            echo "<div class='error'><p>Email is required!</p></div>";
        } else {
            lm_add_license($_POST['lm_email'], $_POST['lm_license']);
            echo "<div class='updated'><p>License added successfully!</p></div>";
        }
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
        $license = sanitize_text_field($_POST['lm_license']);
        $wpdb->update($table_name, ['license' => $license], ['id' => $id]);
        echo "<div class='updated'><p>License updated successfully!</p></div>";
    }

    // Fetch all licenses after any action
    $licenses = $wpdb->get_results("SELECT * FROM $table_name");

    ?>
    <div class="wrap">
        <h1>License Manager</h1>

        <h2>Add License</h2>
        <form method="post">
            <?php wp_nonce_field('lm_add_license_action', 'lm_add_license_nonce'); ?>
            <label for="lm_email">Email:</label>
            <input type="email" name="lm_email" required>
            <label for="lm_license">License:</label>
            <input type="text" name="lm_license" required>
            <input type="submit" name="lm_add_license" value="Add License" class="button button-primary">
        </form>

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
