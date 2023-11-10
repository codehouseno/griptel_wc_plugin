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


    // Get customer name
    $customerId = $orderData["customerId"];

    // Get Discount key
    $discountKey = $DISCOUNT_KEYS[$customerId];

    $returnObject->discountKey = $discountKey;

    // Create woocommerce order
    $newOrder = wc_create_order();

    // Set customer // Temp disabled
    // $newOrder->set_customer_id(1);

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
