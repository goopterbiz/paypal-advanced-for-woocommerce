=== Goopter advanced integration for PayPal Complete Payments and for WooCommerce ===
Contributors: goopter
Tags: woocommerce, paypal, apple pay, google play, credit card
Tested up to: 6.8
Stable tag: 1.0.9
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrate PayPal with WooCommerce, allowing payments via PayPal, credit/debit cards, Apple Pay, Google Pay, Venmo, or Pay Later at checkout.

== Description ==

Easily integrate PayPal with your WooCommerce store. This plugin helps you connect your PayPal account so customers can pay using PayPal, their preferred credit/debit cards, Apple Pay, Google Pay, or even Venmo, Pay Later at checkout.

== Installation ==

= Setup =

1. Open PayPal Complete Payments Settings:
   Go to Settings > PayPal Complete Payments in your WordPress admin.

2. Start Connecting Your PayPal Account:
   Click the "Start Now" button to begin the connection process.

3. Log In to PayPal:
   A PayPal popup window will open. Log in with your PayPal credentials and follow 
   the instructions.

4. Complete the Connection:
   After successfully connecting, the popup will close and a green checkmark will 
   confirm a successful setup.

5. Modify Settings (Optional):
   To adjust configuration details later, click the "Modify Setup" button in the 
   PayPal Complete Payments settings.

Congratulations! Your WooCommerce store is now ready to accept PayPal payments.

= Minimum Requirements =

* WooCommerce 3.0 or higher

== External Services ==
This plugin communicates with PayPal’s API to facilitate payment transactions. 
The PayPal API may collect information required for processing payments, such as user details, payment method, and transaction information.
By using this plugin, you agree to the following terms and conditions related to PayPal’s services:

*Service Provider: PayPal
*Terms of Use: [PayPal Terms of Service](https://www.paypal.com/us/legalhub/paypal/home)
*Privacy Policy: [PayPal Privacy Policy](https://www.paypal.com/us/legalhub/paypal/privacy-full)

== Changelog ==

= 1.0.0 - 2025-02-21 =
* New: Initial Release

= 1.0.1 - 2025-02-24 =
* New: Added 3ds on product and cart pages

= 1.0.2 - 2025-02-27 =
* Fix: Resolved UI issues(#14748)

= 1.0.3 - 2025-02-28 =
* Fix: Resolved additional UI issues(#14748)

= 1.0.4 - 2025-03-06 =
* Fix: checkout billing address issue when the payapl is disabled(#14753)

= 1.0.5 - 2025-03-13 =
* Fix: checkout billing address issue when the payapl and 3ds are both disabled(#14753)

= 1.0.6 - 2025-03-17 =
* Update: updated goopter server end point

= 1.0.7 - 2025-03-19 =
* Fix: Corrected the maximum soft descriptor length from 21 to 22(#14760)

= 1.0.8 - 2025-03-14 =
* Fix: Fixed missing error message for apple, google, 3ds in new checkout block page(#14763)

= 1.0.9 - 2025-05-14 =
* Update: Enhanced theme compatibility and buttons display logic for product variation