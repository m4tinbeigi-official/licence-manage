# License Manager

A simple WordPress plugin to manage emails and licenses. This plugin allows you to add licenses associated with user emails and provides a shortcode to display the user's license, which can be copied with a single click.

## Features

- Add and manage licenses associated with user emails.
- Shortcode to display the user's license.
- Easy import of multiple licenses from a CSV file.
- Copy license to clipboard with a button click.
- Admin interface to manage licenses.

## Installation

1. Download the plugin files.
2. Upload the `license-manager` directory to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Use the shortcode `[user_license]` to display the license on the user’s dashboard.

## Usage

- To add a license, navigate to **License Manager** in the WordPress admin menu.
- You can add a single license or import licenses in bulk from a CSV file.
- For users, the license can be viewed on the frontend using the `[user_license]` shortcode.

## CSV Format for Importing Licenses

The CSV file should have the following format:

email1@example.com,LICENSE_KEY_1 email2@example.com,LICENSE_KEY_2
email3@example.com,LICENSE_KEY_3


Each line should contain an email and its corresponding license, separated by a comma.

## Contributing

Contributions are welcome! Please feel free to submit a pull request or create an issue for any bugs or feature requests.

## License

This plugin is open-source software licensed under the [MIT License](LICENSE).

## Support

For support, please open an issue in this repository or contact the author.

## Author

Rick Sanchez - [RickSanchez.ir](https://RickSanchez.ir)
