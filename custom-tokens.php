<?php
/**
 * Plugin Name: Custom Tokens
 * Description: A plugin to create and manage custom tokens (shortcodes) for use throughout your WordPress site.
 * Version: 1.0.6
 * Author: Kyle Weidner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

final class Custom_Tokens_Plugin {

    private static $instance;
    private const OPTION_NAME = 'custom_tokens_data';
    private $tokens_cache = null;

    public static function instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }

    private function setup_hooks() {
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
        add_action( 'admin_init', [ $this, 'handle_form_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    private function get_all_tokens() {
        if ( $this->tokens_cache === null ) {
            $this->tokens_cache = get_option( self::OPTION_NAME, [] );
        }
        return $this->tokens_cache;
    }

    private function update_all_tokens( array $tokens ) {
        update_option( self::OPTION_NAME, $tokens );
        $this->tokens_cache = $tokens;
    }

    public function handle_form_actions() {
        if ( ! isset( $_POST['token_action'], $_POST['_token_nonce'] ) || ! wp_verify_nonce( $_POST['_token_nonce'], 'token_actions_nonce' ) ) {
            return;
        }

        $action = sanitize_key( $_POST['token_action'] );
        $redirect_slug = '';

        switch ( $action ) {
            case 'add_token':
                $this->add_token();
                $redirect_slug = 'custom-tokens';
                break;
            case 'remove_token':
                $this->remove_token();
                $redirect_slug = 'custom-tokens';
                break;
            case 'import_tokens':
                $this->import_tokens();
                $redirect_slug = 'custom-tokens-import-export';
                break;
        }

        if ( ! empty( $redirect_slug ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_slug . '&message=' . $action . '_success' ) );
            exit;
        }
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_custom-tokens' !== $hook && 'tokens_page_custom-tokens-import-export' !== $hook ) {
            return;
        }
        wp_enqueue_script( 'custom-tokens-admin-js', plugin_dir_url( __FILE__ ) . 'admin.js', [ 'jquery' ], '1.6.0', true );
        wp_localize_script( 'custom-tokens-admin-js', 'customTokens', [
            'nonce'       => wp_create_nonce( 'token_actions_nonce' ),
            'export_data' => $this->get_export_data()
        ] );
    }

    public function add_admin_page() {
        // This creates the top-level menu item.
        add_menu_page(
            'Tokens',                         // The title that appears on the page itself.
            'Tokens',                         // The text for the menu item.
            'manage_options',                 // The capability required to see this menu.
            'custom-tokens',                  // The menu slug.
            [ $this, 'render_tokens_page' ],  // The function to render the page.
            'dashicons-admin-network'               // The icon.
        );

        // Explicitly define the first submenu item, using the SAME slug as the parent.
        // This becomes the default page for the "Tokens" menu.
        add_submenu_page(
            'custom-tokens',                  // Parent slug.
            'Manage Tokens',                  // Page title.
            'Manage Tokens',                  // Menu title.
            'manage_options',                 // Capability.
            'custom-tokens',                  // Menu slug (must match parent).
            [ $this, 'render_tokens_page' ]   // Render function.
        );

        // Now, add any other submenu pages.
        add_submenu_page(
            'custom-tokens',                  // Parent slug.
            'Import/Export Tokens',           // Page title.
            'Import/Export',                  // Menu title.
            'manage_options',                 // Capability.
            'custom-tokens-import-export',    // A unique menu slug.
            [ $this, 'render_import_export_page' ] // Render function.
        );
    }


    public function settings_init() {
        register_setting( 'tokens_settings_group', self::OPTION_NAME, [ 'sanitize_callback' => [ $this, 'sanitize_token_data' ] ] );
        add_settings_section( 'tokens_section', 'Manage Tokens', null, 'custom-tokens' );

        foreach ( (array) $this->get_all_tokens() as $name => $data ) {
            $label = $data['label'] ?? $name;
            add_settings_field( 'token_' . $name, $label, [ $this, 'render_field' ], 'custom-tokens', 'tokens_section', [ 'name' => $name ] );
        }

        add_settings_field( 'add_new_token', 'Add New Token', [ $this, 'render_add_new_field' ], 'custom-tokens', 'tokens_section' );
    }

    public function render_tokens_page() {
        ?>
        <div class="wrap">
            <h1>Manage Tokens</h1>
            <?php if ( isset( $_GET['message'] ) ) : ?>
                <div id="message" class="updated notice is-dismissible"><p><?php echo esc_html( $this->get_user_message( $_GET['message'] ) ); ?></p></div>
            <?php endif; ?>
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

    public function render_import_export_page() {
        ?>
        <div class="wrap">
            <h1>Import/Export Tokens</h1>
            <?php if ( isset( $_GET['message'] ) ) : ?>
                <div id="message" class="updated notice is-dismissible"><p><?php echo esc_html( $this->get_user_message( $_GET['message'] ) ); ?></p></div>
            <?php endif; ?>

            <p>Import tokens from a JSON file or export current tokens for backup.</p>

            <div id="import-section" style="padding:15px; background:#fff; border:1px solid #ccd0d4; border-radius: 4px; max-width: 500px;">
                <h4>Import Tokens</h4>
                <input type="file" id="token_import_file" accept=".json" style="margin-bottom: 10px;" /> <br>
                <label><input type="checkbox" id="replace_existing" /> Replace existing tokens if a token with the same name already exists.</label>
                <p style="margin-top:15px;"><button type="button" id="import_tokens_btn" class="button button-primary">Import Tokens</button></p>
            </div>

            <hr style="margin: 20px 0;">

            <div id="export-section">
                <h4>Export Tokens</h4>
                <p>Click the button below to download a JSON file of all your current tokens.</p>
                <button type="button" id="export_tokens_btn" class="button button-secondary">Export All Tokens as JSON</button>
            </div>
        </div>
        <?php
    }

    public function sanitize_token_data( $input ) {
        $clean_data = [];
        if ( ! is_array( $input ) ) return $clean_data;

        foreach ( $input as $name => $data ) {
            $sanitized_name = sanitize_text_field( stripslashes( $name ) );
            if ( preg_match( '/^[a-zA-Z0-9_]+$/', $sanitized_name ) && ! empty( $data['label'] ) ) {
                $clean_data[ $sanitized_name ] = [
                    'label' => sanitize_text_field( $data['label'] ),
                    'value' => sanitize_text_field( $data['value'] ?? '' ),
                ];
            }
        }
        return $clean_data;
    }

    public function render_field( $args ) {
        $all_tokens = $this->get_all_tokens();
        $name = $args['name'];
        $token_data = $all_tokens[ $name ] ?? [ 'value' => '', 'label' => '' ];

        printf( '<input type="hidden" name="%1$s[%2$s][label]" value="%3$s" />', self::OPTION_NAME, esc_attr( $name ), esc_attr( $token_data['label'] ) );
        ?>
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="text" name="<?php printf('%s[%s][value]', self::OPTION_NAME, esc_attr( $name ))?>" value="<?php echo esc_attr( $token_data['value'] ); ?>" style="width: 300px" />
            <button type="button" class="button button-secondary remove-token-btn" data-token="<?php echo esc_attr( $name ); ?>">Remove</button>
            <code style="font-size: 12px; color: #666;">[<?php echo esc_html( $name ); ?>]</code>
        </div>
        <?php
    }

    public function render_add_new_field() {
        ?>
        <h4>Add Individual Token</h4>
        <table class="form-table" style="margin-top:0;">
            <tr valign="top"><th scope="row"><label for="new_token_name">Token Name</label></th><td><input type="text" id="new_token_name" placeholder="e.g., my_custom_token" style="width: 200px" /></td></tr>
            <tr valign="top"><th scope="row"><label for="new_token_label">Token Label</label></th><td><input type="text" id="new_token_label" placeholder="e.g., My Custom Token" style="width: 200px" /></td></tr>
            <tr valign="top"><th scope="row"><label for="new_token_value">Token Value</label></th><td><input type="text" id="new_token_value" placeholder="e.g., The token value" style="width: 300px" /></td></tr>
        </table>
        <button type="button" id="add_token_btn" class="button button-primary">Add Token</button>
        <?php
    }

    public function register_shortcodes() {
        foreach ( (array) $this->get_all_tokens() as $name => $data ) {
            if ( ! shortcode_exists( $name ) ) {
                add_shortcode( $name, function() use ( $data ) { return $data['value'] ?? ''; } );
            }
        }
    }

    private function get_export_data() {
        $all_tokens = $this->get_all_tokens();
        $export_data = [];
        foreach ( $all_tokens as $name => $data ) {
            $export_data[] = [ 'name' => $name, 'label' => $data['label'], 'value' => $data['value'] ];
        }
        return $export_data;
    }

    private function add_token() {
        if ( empty( $_POST['new_token'] ) || ! is_array( $_POST['new_token'] ) ) return;

        $new_token = stripslashes_deep( $_POST['new_token'] );
        $name = sanitize_text_field( $new_token['name'] );

        if ( empty( $name ) || ! preg_match( '/^[a-zA-Z0-9_]+$/', $name ) ) return;

        $all_tokens = $this->get_all_tokens();
        if ( isset( $all_tokens[ $name ] ) ) return;

        $all_tokens[ $name ] = [
            'label' => sanitize_text_field( $new_token['label'] ),
            'value' => sanitize_text_field( $new_token['value'] ),
        ];
        $this->update_all_tokens( $all_tokens );
    }

    private function remove_token() {
        if ( empty( $_POST['remove_token_name'] ) ) return;

        $token_to_remove = sanitize_text_field( stripslashes( $_POST['remove_token_name'] ) );
        $all_tokens = $this->get_all_tokens();
        unset( $all_tokens[ $token_to_remove ] );
        $this->update_all_tokens( $all_tokens );
    }

    private function import_tokens() {
        if ( empty( $_POST['import_tokens_data'] ) ) return;
        $import_data = json_decode( stripslashes( $_POST['import_tokens_data'] ), true );
        if ( empty( $import_data['tokens'] ) ) return;

        $replace = $import_data['replace_existing'] ?? false;
        $current_tokens = $replace ? [] : $this->get_all_tokens();

        $import_array = array_column( $import_data['tokens'], null, 'name' );
        $imported_tokens = $this->sanitize_token_data($import_array);

        $updated_tokens = array_merge( $current_tokens, $imported_tokens );
        $this->update_all_tokens( $updated_tokens );
    }

    private function get_user_message( $message_code ) {
        $messages = [
            'add_token_success' => 'Token added successfully.',
            'remove_token_success' => 'Token removed successfully.',
            'import_tokens_success' => 'Tokens imported successfully.',
        ];
        return $messages[ sanitize_key( $message_code ) ] ?? 'Settings saved.';
    }
}

Custom_Tokens_Plugin::instance();