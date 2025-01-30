=== Wappi – Send Notifications via personal WhatsApp and Telegram ===
Contributors: wappipro
Tags: woocommerce, whatsapp, telegram, notifications, woocommerce whatsapp
Requires at least: 3.8
Tested up to: 6.7.1
Stable tag: 1.0.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce

        
Send WhatsApp and Telegram notifications for Woocommerce orders by connecting your personal Whatsapp or Telegram via QR code.

== Description ==

Using the "Wappi – Send Notifications via personal WhatsApp and Telegram” plugin You can send automatic Whatsapp, Telegram and SMS notifications about order statuses to administrators and customers of an online store running on the WordPress WooCommerce platform.

This plugin requires you to have an account at [Wappi.pro](https://wappi.pro/). Create your account now by [clicking here](https://wappi.pro/registration). The free trial period is 5 days, then the cost starts from $5 per month 

*Please note - this is NOT the Whatsapp Business API (WABA), but the connection of your own Whatsapp account through scanning a QR code.*

Features:

* Wordpress WooCommerce module support.

* Sending notifications from your own Whatsapp account. Connect your personal Whatsapp account by scanning the QR code and send automatic messages from it.

* Sending notifications from your own Telegram account. Connect your personal Telegram account (not a bot) and send automatic messages from it.

* Supports cascade sending of Whatsapp, Telegram and SMS notifications. If the client does not have one channel, the message will go through another one.

* Automatic notifications to administrators. Sending notifications to the administrator or the seller about new orders and changing order statuses. You can specify several arbitrary numbers.

* Automatic notifications to customers. Sending notifications to customers about order confirmation and order status changes.

* Setting individual notification templates with variables. For each notification, you can set your own text using variables: products, quantity, price, name, phone number, address, arbitrary fields (track number).

[SITE](https://wappi.pro/) | [Support](https://t.me/wappi_support) | [User agreement](https://wappi.pro/oferta) | [Privacy policy](https://wappi.pro/privacy)

== Installation ==
1. Make sure that you have the latest version of the plugin installed [WooCommerce](http://www.woothemes.com/woocommerce).
2. There are several installation options:
    2.1 Via the plugin catalog:
        * in the admin panel, go to the *Plugins* page and click *Add New*
        * find the plugin "Wappi – Send Notifications via personal WhatsApp and Telegram"
        * click the button *Install*
    2.2 Via the console:
        * download the plugin here - [Download](https://wordpress.org/plugins/wappi/)
        * in the admin panel, go to the *Plugins* page and click *Add New*
        * go to the *Download* tab, click Browse and select the archive with the plugin. Click *Install*
    2.3 Via FTP:
        * download the plugin here - [Download](https://wordpress.org/plugins/wappi/)
        * unzip the archive and upload the contents via FTP to folder your-domain/wp-content/plugins
        * in the admin panel, go to the *Plugins* page and click *Install* next to the plugin that appears
3. After the plugin is installed, click *Activate Plugin*.
4. Hover over the menu item *WooCommerce* and select *Wappi*.
5. In the settings, enter the API Token and profile id (you can find it in [dashboard](https://wappi.pro/dashboard)), as well as the seller's whatsapp number.
6. If necessary, specify the statuses for each type of notification and the text.
7. Click the Save button.

== Changelog ==
= 1.0 =
The first version
= 1.0.1 =
Added plugin requirement WooCommerce
Added variables for "Orders Tracking for WooCommerce" plugin
Added line multiplicity in templates
Tested up to 6.6.1 WordPress version
= 1.0.2 =
Added custom order variables processing
= 1.0.3 =
Update max message length from 670 to 5000 charaters
= 1.0.4 =
Added SHIPPING_METHOD and PAYMENT_METHOD into variables
Tested up to: 6.6.2 WordPress version
= 1.0.5 =
Added validation for non-Russian phone numbers
Tested up to: 6.7.1 WordPress version
= 1.0.6 =
Updated get order method
= 1.0.7 =
Сascades added
= 1.0.8 =
Fixed php 7.4 error from last version