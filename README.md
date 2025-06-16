# Custom Tokens for WordPress

**Contributors:** Kyle Weidner\
**Tags:** shortcode, token, variable, custom fields, content management\
**Requires at least:** 5.0\
**Tested up to:** 6.4\
**Stable tag:** 1.0.0\
**License:** GPLv2 or later\
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A simple and effective way to create and manage global, reusable content snippets (tokens/variables/shortcodes) for your WordPress site.

## Description

Custom Tokens provides an admin interface to define a set of "tokens"—essentially key/value pairs that are automatically registered as shortcodes. This allows you to insert dynamic or frequently used information like phone numbers, email addresses, business hours, pricing, or any other text content into your posts, pages, widgets, and theme files with ease.

## Features

*   **Simple Token Management:** A clean "Manage Tokens" page to add, edit, and remove tokens.
*   **Automatic Shortcode Creation:** Each token you create is automatically available as a shortcode (e.g., `[my_token]`).
*   **Import/Export:** Easily back up your tokens or migrate them between sites using the JSON and CSV import/export tools.
*   **Bulk Management:** Import a list of tokens from a CSV or JSON file to get set up quickly.

## Installation

1.  Download the plugin `.zip` file from the repository.
2.  In your WordPress admin dashboard, navigate to **Plugins > Add New**.
3.  Click **Upload Plugin** and select the `.zip` file you downloaded.
4.  Click **Install Now** and then **Activate Plugin**.

Alternatively, you can manually install the plugin:
1.  Unzip the downloaded file.
2.  Upload the `custom-tokens` folder to the `/wp-content/plugins/` directory on your server.
3.  Navigate to the **Plugins** page in your WordPress admin and activate the Custom Tokens plugin.

## How to Use

### Creating a Token

1.  After activating the plugin, a new **Tokens** menu item will appear in your WordPress admin sidebar.
2.  Navigate to **Tokens > Manage Tokens**.
3.  Under the "Add Individual Token" section, fill in the fields:
    *   **Token Name:** The name for your shortcode (e.g., `contact_email`). This must only contain letters, numbers, and underscores.
    *   **Token Label:** A friendly, descriptive name for your token (e.g., "Contact Email Address").
    *   **Token Value:** The content you want the shortcode to display (e.g., `info@example.com`).
4.  Click the **Add Token** button.
5.  Your new token will appear in the list above. You can update its value at any time and click "Save Token Values".

### Using a Token

Once a token is created, you can use its corresponding shortcode anywhere shortcodes are processed in WordPress (posts, pages, text widgets, etc.).

For a token named `contact_email`, you would use `[contact_email]` to display its value.

### Importing and Exporting Tokens

1.  Navigate to **Tokens > Import/Export**.
2.  **To Export:** Click "Export All Tokens as JSON" or "Export All Tokens as CSV" to download a file containing all your current tokens.
3.  **To Import:**
    *   Click "Choose File" and select a valid JSON or CSV file.
    *   **CSV Format:** The file should have three columns: `name`, `label`, `value`. A header row is optional.
    *   **JSON Format:** The file must contain a single root object with a `tokens` key, which holds an array of token objects (e.g., `{"tokens": [{"name": "token_name", "label": "Token Label", "value": "Token Value"}]}`).
    *   Check the "Replace existing tokens" box if you want the imported tokens to overwrite any existing tokens that have the same name.
    *   Click "Import Tokens".

## Changelog

### 1.0.0 - 2025-06-16

*   Initial release.
*   Core functionality for creating, managing, and deleting tokens.
*   Automatic shortcode registration.
*   JSON and CSV import/export functionality.
