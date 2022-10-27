## Zestgeek Software, Solution

[![N|Solid](https://zestgeek.com/ZestGeek%20final%20file1.svg)](https://www.zestgeek.com/)

# Readme
**--------------------------------------------------**

## Stripe 3D WP Plugin

**Contributors:** wp-developers

**Donate link:** https://www.zestgeek.com/

**Tags:** #stripe, #zestgeek

### Introduction
It is a stripe payment gateway, which is used to get online payments from users. It is only for Woocomerce. It can manage users' payment details. It is for the user's and seller's convenience.

### Description

**Stripe 3DSecure** plugin is used the get payment from **Woocomerce**. It is very much simple to use. Just create your account in **stripe** and do all the verification processes. Add the provided publish and secret key in the setting, which is in the **Woocomerce setting's payment menu**. Write the title for your payment gateway that is shown to the user. Select the mode and add both keys provided by stripe. Now enable our payment gateway to get payment from this plugin. The 3DSecure means the user's card support 3D secure, 3D2s secure and not 3D suported card it makes payment of each card acordingly. The seller can filter all the payment details and can export all the data in a .xls file.

### Stripe API Used are:-
1.  **Create Payment Method** 
$stripe->paymentMethods->create([
  'type' => 'card',
  'card' => [
    'number' => '4242424242424242',
    'exp_month' => 10,
    'exp_year' => 2023,
    'cvc' => '314',
  ],
]);
* It is used to create a payment method, which mainly takes card details, cvc number, and expiry date.

2.  **Create Customer**
$stripe->customers->create([
  'description' => 'My First Test Customer (created for API docs at https://www.stripe.com/docs/api)',
]);
* It is used to create a new customer for making the payment which stores the customer details and payment method id (optional). This is used to get information about a card used by which customer. So, that we can have information about the card used by whom.

3.  **Create Payment Intent**
$stripe->paymentIntents->create([
  'amount' => 2000,
  'currency' => 'usd',
  'payment_method_types' => ['card'],
]);
* It creates Intent before payment and after confirming this Intent. We get the payment.

4.  **Confirm Payment Intent**
$stripe->paymentIntents->confirm(
  'pi_1Dse4l2eZvKYlo2CIonDFF4d',
  ['payment_method' => 'pm_card_visa']
);
* It confirms the Intent created for payment. It takes Intent id. Return the 3D Secure or 3D2S Secure URL to authenticate the payment if the card supported the 3D or 3D2S Secure. Then this plugin redirected the user to this link to confirm and after confirmation, if the payment is successful it redirects the user to the thank you page, and if not then it returns back to the checkout page.
##### Command to download stripe API into your project
Installing the library with [Composer](https://getcomposer.org/), a package manager for PHP:
* composer require stripe/stripe-php
Note:- You don't have to worry about these. These all stuff are already managed in this plugin.

Use [stripe](https://stripe.com/in) to get required keys.

# Steps to install it

1.  **Clone the plugin**
git clone https://github.com/programmer-dream/stripe-3d-wp-plugin.git

2. **go into the dir**
run command composer install

And you are good to go! 
