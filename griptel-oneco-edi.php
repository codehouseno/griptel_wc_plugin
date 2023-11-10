<?php
/**
 * Plugin Name:       Griptel OneCo EDI
 * Description:       Creates woocommerce orders from EDI files
 * Version:           0.0.2
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Codehouse AS
 * Author URI:        https://codehouse.no
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       griptel-oneco-edi
 * Domain Path:       /languages
 */


function createOrderFromEDI($data)
{
  // Get data from request
  $data = $data->get_params();

  // Discount keys
  $DISCOUNT_KEYS  = [
    "TELIA" => "regular_price_telia", 
    "ICE" => "regular_price_ice", 
    "TELENOR" => "regular_price_telenor",
    "NOKIA" => "regular_price_nokia",
    "ONECO" => "regular_price_oneco"
  ];

  // Create a return object for logging
  $return = [];

  // Loop through data and create orders
  foreach ($data as $orderData) {
    // Added for logginng
    $returnObject = new stdClass();

    // Check if order with same edi_number already exists
    $args = array(
      'post_type'  => 'shop_order',
      'posts_per_page' => -1,
      'post_status' => 'any',
      'meta_query' => array(
        array(
          'key' => 'edi_number',
          'value' => $orderData["edi_number"],
          'compare' => '=',
        ),
      )
    );
  
    $orders = get_posts( $args );

    if (!empty($orders)) {
      // Order already exists
      $returnObject->error = "Order already exists";
      $returnObject->response = $order;
      array_push($return, $returnObject);
      continue;
    }

    $order_email = $orderData["billing_information"]["billing_email"];

    // Check if there are any users with the billing email as user or email
    $email = email_exists( $order_email );
    $user = username_exists( $order_email );

    // If user exists, get user id
    if(!empty($user)) {
      $user_id = $user;
    } else if(!empty($email)) {
      $user_id = $email;
    } else {
      // Create user
      
      // Random password with 12 chars
      $random_password = wp_generate_password();

      // Firstname
      $first_name = $orderData["billing_information"]["billing_first_name"];
   
      // Lastname
      $last_name = $orderData["billing_information"]["billing_last_name"];
   
      // Role
      $role = 'customer';
   
      // Create new user with email as username, newly created password and user role
      $user_id = wp_insert_user(
        array(
          'user_email' => $order_email,
          'user_login' => $order_email,
          'user_pass'  => $random_password,
          'first_name' => $first_name,
          'last_name'  => $last_name,
          'role'       => $role,
        )
      );
   
      // (Optional) WC guest customer identification
      update_user_meta( $user_id, 'guest', 'yes' );
   
      // User's billing data
      update_user_meta( $user_id, 'billing_address_1', $orderData["billing_information"]["billing_address_1"] );
      update_user_meta( $user_id, 'billing_address_2', $orderData["billing_information"]["billing_address_2"] );
      update_user_meta( $user_id, 'billing_city',  $orderData["billing_information"]["billing_city"] );
      update_user_meta( $user_id, 'billing_company', $orderData["billing_information"]["billing_company"] );
      update_user_meta( $user_id, 'billing_country', $orderData["billing_information"]["billing_country"] );
      update_user_meta( $user_id, 'billing_email', $order_email);
      update_user_meta( $user_id, 'billing_first_name', $firstName );
      update_user_meta( $user_id, 'billing_last_name', $last_name);
      update_user_meta( $user_id, 'billing_phone', $orderData["billing_information"]["billing_phone"]);
      update_user_meta( $user_id, 'billing_postcode', $orderData["billing_information"]["billing_postcode"] );
      update_user_meta( $user_id, 'billing_organization_number', $orderData["organization_number"] );
   
               // User's shipping data
      update_user_meta( $user_id, 'shipping_address_1', $orderData["shipping_information"]["shipping_address_1"] );
      update_user_meta( $user_id, 'shipping_address_2', $orderData["shipping_information"]["shipping_address_2"] );
      update_user_meta( $user_id, 'shipping_city', $orderData["shipping_information"]["shipping_city"] );
      update_user_meta( $user_id, 'shipping_company', $orderData["shipping_information"]["shipping_company"]);
      update_user_meta( $user_id, 'shipping_country', $orderData["shipping_information"]["shipping_country"] );
      update_user_meta( $user_id, 'shipping_first_name', $orderData["shipping_information"]["shipping_first_name"] );
      update_user_meta( $user_id, 'shipping_last_name', $orderData["shipping_information"]["shipping_last_name"] );
      update_user_meta( $user_id, 'shipping_postcode', $orderData["shipping_information"]["shipping_postcode"] );
   
      // Link past orders to this newly created customer
      wc_update_new_customer_past_orders( $user_id );
    }


    // Get customer name
    $customerId = $orderData["customerId"];

    // Get Discount key
    $discountKey = $DISCOUNT_KEYS[$customerId];

    $returnObject->discountKey = $discountKey;

    // Create woocommerce order
    $newOrder = wc_create_order();

    // Set customer // Temp disabled
    $newOrder->set_customer_id($user_id);

    // Set billing_information address
    $newOrder->set_billing_first_name($orderData["billing_information"]["billing_first_name"]);
    $newOrder->set_billing_last_name($orderData["billing_information"]["billing_last_name"]);
    $newOrder->set_billing_company($orderData["billing_information"]["billing_company"]);
    $newOrder->set_billing_address_1($orderData["billing_information"]["billing_address_1"]);
    $newOrder->set_billing_address_2($orderData["billing_information"]["billing_address_2"]);
    $newOrder->set_billing_city($orderData["billing_information"]["billing_city"]);
    $newOrder->set_billing_state($orderData["billing_information"]["billing_state"]);
    $newOrder->set_billing_postcode($orderData["billing_information"]["billing_postcode"]);
    $newOrder->set_billing_country($orderData["billing_information"]["billing_country"]);
    $newOrder->set_billing_phone($orderData["billing_information"]["billing_phone"]);
    $newOrder->set_billing_email($orderData["billing_information"]["billing_email"]);
    // $newOrder->set_billing_organization_number($orderData["organization_number"]);

    // set custom meta data organization_number
    $newOrder->update_meta_data("_billing_organisasjonsnummer", $orderData["organization_number"]);

    if(isset($discountKey)) {
      $newOrder->update_meta_data("_billing_prisavtale", $customerId);
    }
    // set custom meta data _billing_prisavtale

    // Set shipping address
    $newOrder->set_shipping_first_name($orderData["shipping_information"]["shipping_first_name"]);
    $newOrder->set_shipping_last_name($orderData["shipping_information"]["shipping_last_name"]);
    $newOrder->set_shipping_company($orderData["shipping_information"]["shipping_company"]);
    $newOrder->set_shipping_address_1($orderData["shipping_information"]["shipping_address_1"]);
    $newOrder->set_shipping_address_2($orderData["shipping_information"]["shipping_address_2"]);
    $newOrder->set_shipping_city($orderData["shipping_information"]["shipping_city"]);
    $newOrder->set_shipping_state($orderData["shipping_information"]["shipping_state"]);
    $newOrder->set_shipping_postcode($orderData["shipping_information"]["shipping_postcode"]);
    $newOrder->set_shipping_country($orderData["shipping_information"]["shipping_country"]);

    // Set currency
    // $newOrder->set_currency($orderData["currency"]);

    // set payment method
    $newOrder->set_payment_method("cod");

    // Set payment method title
    $newOrder->set_payment_method_title("Invoice");

    // Set order status
    $newOrder->set_status("processing");

    // Add products by looping through EDI file
    foreach ($orderData["line_items"] as $product) {
      $product_sku = $product["product_sku"];
      $quantity = $product["quantity"];
      $unit_price = 0;
      
      // Get the product id based on SKU
      $product_id = wc_get_product_id_by_sku($product_sku);

      // Get the product
      $product_obj = wc_get_product($product_id);
      
      $returnObject->response = $product_obj;

      // Handle if product is not found
      if(empty($product_obj)) {
        // Product not found
        $returnObject->error = "Product not found";
        $returnObject->response = $product_obj;
        array_push($return, $returnObject);
        continue;
      }
        
      // Check discountKey and set custom price
      if(!empty($discountKey)) {
        $custom_price = $product_obj->get_meta($discountKey);
       
        if(!empty($custom_price)) {
          $unit_price = $custom_price;
        } else {
          $unit_price = $product_obj->get_price();
        }
      } else {
        $unit_price = $product_obj->get_price();
      }
        
      // Create a line item and set custom price
      $line_item = new WC_Order_Item_Product();
      $line_item->set_props(array(
        'product' => $product_obj,
        'quantity' => $quantity,
        'total' => $unit_price * $quantity,
        // currency is optional, and defaults to the order's currency if not set
      ));
        
      // Add the line item to the order
      $newOrder->add_item($line_item);      
    }

    // Calculate totals
    $newOrder->calculate_totals();
    
    // add meta data to order
    $newOrder->update_meta_data("edi_number", $orderData["edi_number"]);
    
    // Save order
    $newOrder->save();

    //! ADDED for better logging
    // Adds to return object 
    $returnObject->orderData = $newOrder->get_data();    
    // add return object to return array
    array_push($return, $returnObject);

  }

  return $return;
}

add_action("rest_api_init", function () {
  register_rest_route("codehouse/v1", "/createorderfromedi", [
    "methods" => "POST",
    "callback" => "createOrderFromEDI",
    "permission_callback" => "__return_true",
  ]);
});

add_action('rest_api_init', function () {
  register_rest_route('codehouse/v1', '/updatePlugin', array(
      'methods' => 'POST',
      'callback' => 'plugin_update',
  ));
});



function plugin_update(WP_REST_Request $request) {
  
  // The directory of your plugin in your WordPress installation
  $plugin_directory = plugin_dir_path( __FILE__ );
  
  // Verify the request is from GitHub
  // You'd usually do this by checking against your secret key.
  // For clarity, that step has been omitted here.
  
  // Navigate to the plugin directory
  chdir($plugin_directory);
    
  // Execute the `git pull` command
  $output = shell_exec('git pull');
  
  // Return the output of the shell command
  return new WP_REST_Response($output, 200);
}