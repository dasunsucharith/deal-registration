<?php
/*
Plugin Name: Deal Registration
Description: A plugin to create a custom form and handle submissions.
Version: 1.0
Author: Dasun Sucharith Pathinayake
*/

function my_custom_form_enqueue_style()
{
    wp_enqueue_style('my-custom-form-style', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('wp_enqueue_scripts', 'my_custom_form_enqueue_style');

function my_custom_form_shortcode()
{
    ob_start();
?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <!-- Customer Info -->
        <h3>Customer Information</h3>
        <input type="text" name="company_name" placeholder="Company Name" required>
        <input type="text" name="contact_name" placeholder="Contact Name" required>
        <input type="tel" name="phone" placeholder="Phone (with country code)" required>
        <input type="email" name="email" placeholder="Email" required>
        <textarea name="customer_requirement" placeholder="Customer Requirement"></textarea>

        <!-- Partner Info -->
        <h3>Partner Information</h3>
        <input type="text" name="partner_name" placeholder="Partner Name" required>
        <input type="text" name="partner_contact" placeholder="Contact Person" required>
        <input type="tel" name="partner_phone" placeholder="Phone (with country code)" required>
        <input type="email" name="partner_email" placeholder="Email" required>
        <div>
            <p>Area of Interest:</p>
            <label><input type="checkbox" name="interest[]" value="AI"> AI</label>
            <label><input type="checkbox" name="interest[]" value="Digital_Healthcare"> Digital Healthcare</label>
            <label><input type="checkbox" name="interest[]" value="Digital_Telco"> Digital Telco</label>
            <label><input type="checkbox" name="interest[]" value="Intelligent_Healthcare"> Intelligent Healthcare</label>
            <label><input type="checkbox" name="interest[]" value="Network_VAS"> Network VAS</label>
            <label><input type="checkbox" name="interest[]" value="Marketplace"> Marketplace</label>
            <label><input type="checkbox" name="interest[]" value="Devops"> DevOps</label>
            <!-- Add other checkboxes similarly -->
            <!-- Repeat for other interests like Digital Healthcare, etc. -->
        </div>
        <input type="file" name="attachment">
        <textarea name="comments" placeholder="Comments/Remarks"></textarea>

        <input type="hidden" name="action" value="submit_my_custom_form">
        <input type="submit" value="Submit">
    </form>
<?php
    return ob_get_clean();
}
add_shortcode('my_custom_form', 'my_custom_form_shortcode');


function handle_my_custom_form_submission()
{
    // Check if data is posted
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // 1. Validate and Sanitize Input Data
        $company_name = sanitize_text_field($_POST['company_name']);
        $contact_name = sanitize_text_field($_POST['contact_name']);
        $phone = sanitize_text_field($_POST['phone']); // Assuming phone is a text input
        $email = sanitize_email($_POST['email']);
        $customer_requirement = sanitize_textarea_field($_POST['customer_requirement']);

        $partner_name = sanitize_text_field($_POST['partner_name']);
        $partner_contact = sanitize_text_field($_POST['partner_contact']);
        $partner_phone = sanitize_text_field($_POST['partner_phone']);
        $partner_email = sanitize_email($_POST['partner_email']);
        $comments = sanitize_textarea_field($_POST['comments']);

        // Sanitizing checkbox inputs
        $interests = isset($_POST['interest']) ? (array) $_POST['interest'] : [];
        $interests = array_map('sanitize_text_field', $interests);

        // Handling file upload (attachments)
        // Note: Handling file uploads securely in WordPress requires additional considerations
        $attachment = isset($_FILES['attachment']) ? $_FILES['attachment'] : null;


        // 2. Save to Database
        global $wpdb;
        $table_name = $wpdb->prefix . 'my_custom_form'; // Adjust with your table name

        // Preparing data for insertion
        $data = [
            'company_name' => $company_name,
            'contact_name' => $contact_name,
            'phone' => $phone,
            'email' => $email,
            'customer_requirement' => $customer_requirement,
            'partner_name' => $partner_name,
            'partner_contact' => $partner_contact,
            'partner_phone' => $partner_phone,
            'partner_email' => $partner_email,
            'comments' => $comments,
            // 'interests' is an array, so you may want to serialize it or handle it differently
            'interests' => maybe_serialize($interests)
        ];

        // If handling file uploads
        if (
            $attachment && !$attachment['error']
        ) {
            require_once(ABSPATH . 'wp-admin/includes/file.php'); // WordPress function for file handling

            $file = $attachment;
            $upload_overrides = array('test_form' => false); // Disable the form test

            // Handle the upload
            $movefile = wp_handle_upload($file, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                // File is uploaded successfully
                $attachment_url = $movefile['url']; // URL to the uploaded file
                $data['attachment'] = $attachment_url;
            } else {
                // Handle errors
                error_log('File upload error: ' . $movefile['error']);
            }
        }

        $format = [
            '%s', // company_name
            '%s', // contact_name
            '%s', // phone
            '%s', // email
            '%s', // customer_requirement
            '%s', // partner_name
            '%s', // partner_contact
            '%s', // partner_phone
            '%s', // partner_email
            '%s', // comments
            '%s', // interests (serialized array)
            '%s', // attachement url
        ];

        $wpdb->insert($table_name, $data, $format);

        // 3. Send Data to Pardot
        // This part depends on Pardot's API, pseudocode:
        // send_data_to_pardot([
        //    'company_name' => $company_name,
        //    'contact_name' => $contact_name,
        //    ...
        // ]);

        // 4. Redirect or Output a Success Message
        wp_redirect(home_url('/thank-you')); // Redirect to a thank-you page
        exit;
    }
}
add_action('admin_post_nopriv_submit_my_custom_form', 'handle_my_custom_form_submission');
add_action('admin_post_submit_my_custom_form', 'handle_my_custom_form_submission');


function my_custom_form_plugin_activate()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_custom_form';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        company_name varchar(255) NOT NULL,
        contact_name varchar(255) NOT NULL,
        phone varchar(50),
        email varchar(255),
        customer_requirement text,
        partner_name varchar(255),
        partner_contact varchar(255),
        partner_phone varchar(50),
        partner_email varchar(255),
        interests text,  -- Serialized array of interests
        attachment_url varchar(255),  -- URL to the uploaded file
        comments text,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'my_custom_form_plugin_activate');


function send_data_to_pardot($data)
{
    // Use wp_remote_post or similar to send data to Pardot
}

function my_custom_form_admin_menu()
{
    add_menu_page(
        'Deal Registrations',      // Page title
        'Deal Registrations',      // Menu title
        'manage_options',        // Capability
        'customer_deal_registrations',  // Menu slug
        'customer_deal_registrations_display_submissions'  // Function to display the page
    );
}
add_action('admin_menu', 'my_custom_form_admin_menu');

function customer_deal_registrations_display_submissions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_custom_form';

    // Retrieve data from the database
    $submissions = $wpdb->get_results("SELECT * FROM $table_name");

    // Check if there are any submissions
    if ($submissions) {
        // Use WordPress admin table styles
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Form Submissions</h1>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr><th>ID</th><th>Company Name</th><th>Contact Name</th><th>Phone</th><th>Email</th><th>Customer Requirement</th><th>Partner Name</th><th>Partner Contact</th><th>Partner Phone</th><th>Partner Email</th><th>Interests</th><th>Attachment URL</th><th>Comments</th></tr>';
        echo '</thead>';
        echo '<tbody id="the-list">';
        foreach ($submissions as $submission) {
            echo '<tr>';
            echo '<td>' . esc_html($submission->id) . '</td>';
            echo '<td>' . esc_html($submission->company_name) . '</td>';
            echo '<td>' . esc_html($submission->contact_name) . '</td>';
            echo '<td>' . esc_html($submission->phone) . '</td>';
            echo '<td>' . esc_html($submission->email) . '</td>';
            echo '<td>' . esc_html($submission->customer_requirement) . '</td>';
            echo '<td>' . esc_html($submission->partner_name) . '</td>';
            echo '<td>' . esc_html($submission->partner_contact) . '</td>';
            echo '<td>' . esc_html($submission->partner_phone) . '</td>';
            echo '<td>' . esc_html($submission->partner_email) . '</td>';
            $interests = maybe_unserialize($submission->interests);
            if (is_array($interests)) {
                echo '<td>' . esc_html(implode(', ', $interests)) . '</td>';
            } else {
                echo '<td>' . esc_html($submission->interests) . '</td>'; // Fallback if not serialized
            }
            echo '<td>' . esc_html($submission->attachment_url) . '</td>';
            echo '<td>' . esc_html($submission->comments) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<div class="wrap"><h1 class="wp-heading-inline">Form Submissions</h1><p>No submissions found.</p></div>';
    }
}