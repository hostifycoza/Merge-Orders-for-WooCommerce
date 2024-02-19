# Merge Orders for WooCommerce - HPOS Compatible

This WordPress plugin merges multiple pending payment orders from the same customer into one, enhancing the management and processing efficiency in WooCommerce stores. It ensures compatibility with WooCommerce HPOS and supports integration with YITH WooCommerce Auctions.

## Features

- **Merge Pending Orders**: Automatically combine multiple pending orders from the same customer.
- **HPOS Compatibility**: Ensures seamless operation with WooCommerce High Performance Order Storage (HPOS).
- **YITH WooCommerce Auctions Integration**: Extends support to auction-based products, consolidating auction wins into a single order.

## Requirements

- WooCommerce 8.0 or higher.

## Installation

1. Download the plugin zip file.
2. Go to your WordPress Dashboard > Plugins > Add New and click 'Upload Plugin' at the top.
3. Upload the zip file and click Install Now.
4. Once installed, activate the plugin.

## Usage

After activation, the plugin automatically monitors for multiple pending orders from the same customer and merges them. To configure:

1. Navigate to WooCommerce > Merge Orders in your WordPress dashboard to access the plugin settings.
2. Use the provided options to customize the merging process according to your needs.

## Hooks and Filters

The plugin provides various actions and filters to further customize its functionality. For developers:

- `init`: Register custom merged order status.
- `admin_menu`: Adds a submenu for plugin settings under WooCommerce menu.
- `plugins_loaded`: Checks for WooCommerce activation and version requirement.

## Changelog

### 1.1

- Enhanced HPOS compatibility.
- Support for YITH WooCommerce Auctions integration.

## Support

For support, visit [Hostify Support](https://hostify.co.za).

## Author

Developed by Hostify. Visit us at [https://hostify.co.za](https://hostify.co.za) for more information.
