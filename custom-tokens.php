<?php
/**
 * Plugin Name: Custom Tokens
 * Description: A plugin to create and manage custom tokens (shortcodes)
 * Version: 1.0.1
 * Author: Kyle Weidner
 */

// Prevent direct file access for security.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class
 */
final class Custom_Tokens_Plugin {

    private static $instance;
    // The key used to store token data in the wp_options table.
    private const OPTION_NAME = 'custom_tokens_data';
    // In-memory cache to reduce database reads.
    private $tokens_cache = null;

    /**
     * Ensures only one instance of the plugin class is loaded.
     */
    public static function instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }

    /**
     * Registers all necessary WordPress hooks.
     */
    private function setup_hooks() {
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
        add_action( 'admin_init', [ $this, 'handle_form_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Retrieves all tokens from the database, using a cache to prevent redundant queries.
     */
    private function get_all_tokens() {
        if ( $this->tokens_cache === null ) {
            $this->tokens_cache = get_option( self::OPTION_NAME, [] );
        }
        return $this->tokens_cache;
    }

    /**
     * Updates the tokens in the database and clears the cache.
     */
    private function update_all_tokens( array $tokens ) {
        update_option( self::OPTION_NAME, $tokens );
        $this->tokens_cache = $tokens;
    }

    public function handle_form_actions() {
        if ( ! isset( $_POST['token_action'], $_POST['_token_nonce'] ) || ! wp_verify_nonce( $_POST['_token_nonce'], 'token_actions_nonce' ) ) {
            return;
        }

        $action        = sanitize_key( $_POST['token_action'] );
        $redirect_slug = '';
        $message_key   = '';

        switch ( $action ) {
            case 'add_token':
                $message_key   = $this->add_token(); // Capture the result here
                $redirect_slug = 'custom-tokens';
                break;
            case 'remove_token':
                $this->remove_token();
                $message_key   = 'remove_success'; // For consistency
                $redirect_slug = 'custom-tokens';
                break;
            case 'import_tokens':
                $this->import_tokens();
                $message_key   = 'import_success'; // For consistency
                $redirect_slug = 'custom-tokens-import-export';
                break;
        }

        if ( ! empty( $redirect_slug ) ) {
            // Redirect with the correct message key now
            wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_slug . '&message=' . $message_key ) );
            exit;
        }
    }

    /**
     * Enqueues the admin JavaScript file and passes data to it.
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load the script on our plugin's pages.
        if ( 'toplevel_page_custom-tokens' !== $hook && 'tokens_page_custom-tokens-import-export' !== $hook ) {
            return;
        }
        wp_enqueue_script( 'custom-tokens-admin-js', plugin_dir_url( __FILE__ ) . 'admin.js', [ 'jquery' ], '1.6.0', true );
        // Pass PHP data (like nonces and export data) to the JavaScript file.
        wp_localize_script( 'custom-tokens-admin-js', 'customTokens', [
            'nonce'       => wp_create_nonce( 'token_actions_nonce' ),
            'export_data' => $this->get_export_data(),
            'export_csv_data' => $this->get_export_csv_data()
        ] );
    }

    /**
     * Adds the plugin's pages to the WordPress admin menu.
     */
    public function add_admin_page() {
        // Create the top-level "Tokens" menu item.
        add_menu_page( 'Tokens', 'Tokens', 'manage_options', 'custom-tokens', [ $this, 'render_tokens_page' ], 'dashicons-admin-network' );

        // Create the "Manage Tokens" submenu page.
        add_submenu_page( 'custom-tokens', 'Manage Tokens', 'Manage Tokens', 'manage_options', 'custom-tokens', [ $this, 'render_tokens_page' ] );

        // Create the "Import/Export" submenu page.
        add_submenu_page( 'custom-tokens', 'Import/Export Tokens', 'Import/Export', 'manage_options', 'custom-tokens-import-export', [ $this, 'render_import_export_page' ] );
    }

    /**
     * Initializes the WordPress settings API for the token management page.
     */
    public function settings_init() {
        register_setting( 'tokens_settings_group', self::OPTION_NAME, [ 'sanitize_callback' => [ $this, 'sanitize_token_data' ] ] );
        add_settings_section( 'tokens_section', 'Manage Tokens', null, 'custom-tokens' );

        // Add a settings field for each existing token.
        foreach ( (array) $this->get_all_tokens() as $name => $data ) {
            $label = $data['label'] ?? $name;
            add_settings_field( 'token_' . $name, $label, [ $this, 'render_field' ], 'custom-tokens', 'tokens_section', [ 'name' => $name ] );
        }

        // Add the fields for creating a new token.
        add_settings_field( 'add_new_token', 'Add New Token', [ $this, 'render_add_new_field' ], 'custom-tokens', 'tokens_section' );
    }

    public function render_tokens_page() {
        ?>
        <div class="wrap">
            <h1>Manage Tokens</h1>
            <?php
            if ( ! empty( $_GET['message'] ) ) {
                $message_key  = sanitize_key( $_GET['message'] );
                $message_text = $this->get_user_message( $message_key );
                // Use 'error' class for errors, 'updated' (which becomes notice-success) for success
                $notice_class = ( strpos( $message_key, 'error' ) !== false ) ? 'error' : 'updated';
                printf( '<div id="message" class="%s notice is-dismissible"><p>%s</p></div>', esc_attr( $notice_class ), esc_html( $message_text ) );
            }
            ?>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'tokens_settings_group' );
                do_settings_sections( 'custom-tokens' );
                submit_button( 'Save Token Values' );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renders the "Import/Export" admin page.
     */
    public function render_import_export_page() {
        ?>
        <div class="wrap">
            <h1>Import/Export Tokens</h1>
            <?php if ( isset( $_GET['message'] ) ) : ?>
                <div id="message" class="updated notice is-dismissible"><p><?php echo esc_html( $this->get_user_message( $_GET['message'] ) ); ?></p></div>
            <?php endif; ?>

            <p>Import tokens from a JSON or CSV file, or export current tokens for backup.</p>

            <!-- Import Section -->
            <div id="import-section" style="padding:15px; background:#fff; border:1px solid #ccd0d4; border-radius: 4px; max-width: 600px;">
                <h4>Import Tokens</h4>
                <input type="file" id="token_import_file" accept=".json,.csv" style="margin-bottom: 10px;" /> <br>
                <label><input type="checkbox" id="replace_existing" /> Replace existing tokens if a token with the same name already exists.</label>
                <p style="margin-top:15px;"><button type="button" id="import_tokens_btn" class="button button-primary">Import Tokens</button></p>
                <p style="margin-top:10px; font-size:12px; color:#666;">
                    <strong>CSV Format:</strong> name,label,value (with optional header row)<br>
                    <strong>JSON Format:</strong> {"tokens": [{"name": "token_name", "label": "Token Label", "value": "Token Value"}]}
                </p>
            </div>

            <hr style="margin: 20px 0;">

            <!-- Export Section -->
            <div id="export-section">
                <h4>Export Tokens</h4>
                <p>Click a button below to download your current tokens.</p>
                <button type="button" id="export_tokens_btn" class="button button-secondary">Export All Tokens as JSON</button>
                <button type="button" id="export_tokens_csv_btn" class="button button-secondary" style="margin-left: 10px;">Export All Tokens as CSV</button>
            </div>
        </div>
        <?php
    }

    /**
     * Sanitizes token data before saving to the database.
     */
    public function sanitize_token_data( $input ) {
        $clean_data = [];
        if ( ! is_array( $input ) ) return $clean_data;

        foreach ( $input as $name => $data ) {
            $sanitized_name = sanitize_text_field( stripslashes( $name ) );
            // Ensure the token name has a valid format and a label exists.
            if ( preg_match( '/^[a-zA-Z0-9_]+$/', $sanitized_name ) && ! empty( $data['label'] ) ) {
                $clean_data[ $sanitized_name ] = [
                    'label' => sanitize_text_field( $data['label'] ),
                    'value' => sanitize_text_field( $data['value'] ?? '' ),
                ];
            }
        }
        return $clean_data;
    }

    /**
     * Renders the input field for a single token's value.
     */
    public function render_field( $args ) {
        $all_tokens = $this->get_all_tokens();
        $name = $args['name'];
        $token_data = $all_tokens[ $name ] ?? [ 'value' => '', 'label' => '' ];

        // Hidden field to preserve the token's label when saving.
        printf( '<input type="hidden" name="%1$s[%2$s][label]" value="%3$s" />', self::OPTION_NAME, esc_attr( $name ), esc_attr( $token_data['label'] ) );
        ?>
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="text" name="<?php printf('%s[%s][value]', self::OPTION_NAME, esc_attr( $name ))?>" value="<?php echo esc_attr( $token_data['value'] ); ?>" style="width: 300px" />
            <button type="button" class="button button-secondary remove-token-btn" data-token="<?php echo esc_attr( $name ); ?>">Remove</button>
            <code style="font-size: 12px; color: #666;">[<?php echo esc_html( $name ); ?>]</code>
        </div>
        <?php
    }

    /**
     * Renders the form fields for adding a new token.
     */
    public function render_add_new_field() {
        ?>
        <h4>Add Individual Token</h4>
        <table class="form-table" style="margin-top:0;">
            <tr valign="top"><th scope="row"><label for="new_token_label">Token Label</label></th><td><input type="text" id="new_token_label" placeholder="My Custom Token" style="width: 200px" /></td></tr>
            <tr valign="top"><th scope="row"><label for="new_token_value">Token Value</label></th><td><input type="text" id="new_token_value" placeholder="$199/year" style="width: 300px" /></td></tr>
            <tr valign="top"><th scope="row"><label for="new_token_name">Token Name (Shortcode)</label></th><td><input type="text" id="new_token_name" placeholder="FLIP_Custom_Token" style="width: 200px" /></td></tr>
        </table>
        <button type="button" id="add_token_btn" class="button button-primary">Add Token</button>
        <?php
    }

    /**
     * Registers a WordPress shortcode for each custom token.
     */
    public function register_shortcodes() {
        foreach ( (array) $this->get_all_tokens() as $name => $data ) {
            if ( ! shortcode_exists( $name ) ) {
                add_shortcode( $name, function() use ( $data ) { return $data['value'] ?? ''; } );
            }
        }
    }

    /**
     * Prepares the token data for JSON export format.
     */
    private function get_export_data() {
        $all_tokens = $this->get_all_tokens();
        $export_data = [];
        // Convert the associative array to an indexed array of objects.
        foreach ( $all_tokens as $name => $data ) {
            $export_data[] = [ 'name' => $name, 'label' => $data['label'], 'value' => $data['value'] ];
        }
        return $export_data;
    }

    /**
     * Handles the logic for adding a new token.
     * Returns a message key for the result.
     */
    private function add_token() {
        if ( empty( $_POST['new_token'] ) || ! is_array( $_POST['new_token'] ) ) {
            return 'add_error_general';
        }

        $new_token = stripslashes_deep( $_POST['new_token'] );
        $name      = sanitize_text_field( $new_token['name'] );

        if ( empty( $name ) || ! preg_match( '/^[a-zA-Z0-9_]+$/', $name ) ) {
            return 'add_error_invalid_name';
        }

        $all_tokens                 = $this->get_all_tokens();
        $existing_token_names_lower = array_map( 'strtolower', array_keys( $all_tokens ) );

        if ( in_array( strtolower( $name ), $existing_token_names_lower, true ) ) {
            return 'add_error_duplicate';
        }

        $all_tokens[ $name ] = [
            'label' => sanitize_text_field( $new_token['label'] ),
            'value' => sanitize_text_field( $new_token['value'] ),
        ];
        $this->update_all_tokens( $all_tokens );

        return 'add_success';
    }

    /**
     * Handles the logic for removing a token.
     */
    private function remove_token() {
        if ( empty( $_POST['remove_token_name'] ) ) return;

        $token_to_remove = sanitize_text_field( stripslashes( $_POST['remove_token_name'] ) );
        $all_tokens = $this->get_all_tokens();
        unset( $all_tokens[ $token_to_remove ] );
        $this->update_all_tokens( $all_tokens );
    }

    /**
     * Handles the logic for importing tokens from a JSON payload.
     */
    private function import_tokens() {
        if ( empty( $_POST['import_tokens_data'] ) ) {
            return;
        }

        $import_data = json_decode( stripslashes( $_POST['import_tokens_data'] ), true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $import_data ) || ! isset( $import_data['tokens'] ) || ! is_array( $import_data['tokens'] ) ) {
            return;
        }

        $replace        = ! empty( $import_data['replace_existing'] );
        $updated_tokens = $this->get_all_tokens();

        $import_array    = array_column( $import_data['tokens'], null, 'name' );
        $imported_tokens = $this->sanitize_token_data( $import_array );

        // Create a map of lowercase token names to their original cased version for comparison.
        $lowercase_map = [];
        foreach ( array_keys( $updated_tokens ) as $token_name ) {
            $lowercase_map[ strtolower( $token_name ) ] = $token_name;
        }

        foreach ( $imported_tokens as $name => $data ) {
            $name_lower = strtolower( $name );

            // Check if a case-insensitive match exists.
            if ( isset( $lowercase_map[ $name_lower ] ) ) {
                // A token with the same name (but maybe different case) already exists.
                if ( $replace ) {
                    // Get the original, cased key and remove the old entry.
                    $original_key = $lowercase_map[ $name_lower ];
                    unset( $updated_tokens[ $original_key ] );

                    // Add the new token with its specified casing.
                    $updated_tokens[ $name ] = $data;
                    // Update the map to handle other potential duplicates in the same import file.
                    $lowercase_map[ $name_lower ] = $name;
                }
                // If not replacing, we do nothing, preserving the existing token.
            } else {
                // This is a completely new token.
                $updated_tokens[ $name ] = $data;
                // Add it to the map to handle potential duplicates within the same import file.
                $lowercase_map[ $name_lower ] = $name;
            }
        }

        $this->update_all_tokens( $updated_tokens );
    }

    /**
     * Prepares the token data for CSV export format.
     */
    private function get_export_csv_data() {
        $all_tokens = $this->get_all_tokens();
        // CSV Header Row.
        $csv_data = "name,label,value\n";

        // Add each token as a new line in the CSV.
        foreach ( $all_tokens as $name => $data ) {
            // Enclose values in quotes and escape any existing quotes to create a valid CSV.
            $csv_data .= sprintf(
                '"%s","%s","%s"' . "\n",
                str_replace( '"', '""', $name ),
                str_replace( '"', '""', $data['label'] ),
                str_replace( '"', '""', $data['value'] )
            );
        }

        return $csv_data;
    }

    /**
     * Translates message keys from the URL into user-friendly strings.
     *
     * @param string $key The message key from the URL.
     * @return string The display message.
     */
    private function get_user_message( $key ) {
        $messages = [
            'add_success'            => 'Token added successfully.',
            'remove_success'         => 'Token removed successfully.',
            'import_success'         => 'Tokens imported successfully.',
            'add_error_duplicate'    => 'Error: A token with that name already exists. Token names must be unique.',
            'add_error_invalid_name' => 'Error: Invalid token name format. Please use only letters, numbers, and underscores.',
        ];
        return $messages[ $key ] ?? 'An unknown action occurred.';
    }
}

// Initialize the plugin.
Custom_Tokens_Plugin::instance();