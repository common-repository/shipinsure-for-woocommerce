=== ShipInsure for WooCommerce ===
Requires at least: 6.0
Tested up to: 6.4.3
Requires PHP: 7.0
Stable tag: 1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Ease shipping headaches, increase revenue, elevate your customer checkout experience.
Allow ShipInsure to take care of your shipping headaches at no cost to you. Provide your customers and yourself with peace of mind from checkout to delivery. With full coverage for lost, damaged, or stolen orders, more than half of shoppers opt for our service to enhance their purchasing experience. In the rare event of an issue, ShipInsure promptly refunds your shopper or reorders from your store, doubling your revenue. Our mission: to be the most merchant-centric company in eCommerce.

- Decrease customer support time and costs using smart tech, AI, and our A+ team
- Minimize losses; boost revenue through order protection
- Increase lifetime value with 2-minute claims resolution
- More than half of customers attach ShipInsure to their order
- Revenue Share; choose your own pricing and revenue share.

== Installation ==
On activation, a new user named `shipinsure-api` will be created with the role of `Shop Manager`. After the user is created, we will generate a WooCommerce consumer key/secret pair.

The ShipInsure widget will be placed on your cart once your account is activated.

== Third-Party Service Integration ==

ShipInsure for WooCommerce integrates with the ShipInsure API to offer shipping insurance services. This integration involves communication with ShipInsure's API endpoints to enable features such as account creation, insurance option configuration, and claims management within your WooCommerce store.

**API Service:** 
ShipInsure API

**API Base URL:** 
https://api.shipinsure.io/v1/

**Integration Details:**
The plugin communicates with ShipInsure API at various endpoints to enhance your eCommerce platform with shipping insurance functionalities. Below are the specific API endpoints the plugin interacts with:

**Account Installation and Configuration:**

**POST** https://api.shipinsure.io/v1/woocommerce/install
This endpoint is used for initial plugin installation and configuration, setting up the necessary integration parameters between WooCommerce and ShipInsure.

**GET** https://api.shipinsure.io/v1/merchant/byShopifyDomain/{shopUrl} 
Retrieves merchant configuration based on the WooCommerce shop domain. 

Note: The reference to ShopifyDomain in the API path is a generic term used by ShipInsure's API and applies to WooCommerce shops as well.

**Merchant Configuration:**

**POST** https://api.shipinsure.io/v1/merchants/config
Configures merchant-specific settings for the ShipInsure service.

**Activation Scripts:**

**GET** https://cdn.shipinsure.io/woocommerce/scripts/activateShipInsure.js
Loads the activation script necessary for enabling the ShipInsure widget on your WooCommerce cart. This script is essential for the correct functioning of the insurance options presented to the customers.

== Data Privacy and Security: ==

When interacting with the ShipInsure API, certain information about your orders and shipments is transmitted to facilitate the insurance coverage process. This may include order IDs, shipment details, product values, and customer information relevant to the insurance policy.

**Service URL:**
https://shipinsure.io

**Terms of Use:**
https://shipinsure.io/terms

**Privacy Policy:**
https://shipinsure.io/privacy

By utilizing ShipInsure for WooCommerce, you acknowledge and consent to the transmission of order and shipment details to ShipInsure for the purpose of obtaining shipping insurance. It is imperative to review ShipInsure\'s Terms of Use and Privacy Policy to ensure compliance with data protection regulations and to understand how your data is handled.

== REST API Endpoints ==

**Script Tags:**
**Endpoint:** /shipinsure/v1/script_tags (POST)
**Description:** Saves the script details, such as the URL and version, as WordPress options.

**Endpoint:** /shipinsure/v1/script_tags (DELETE)
**Description:** Removes the saved script details from WordPress options.

== Screenshots ==
1. ShipInsure Authorization on Activation
2. ShipInsure Dashboard
3. ShipInsure Product in Cart

== Changelog ==
= 1.0 =
* Initial release.