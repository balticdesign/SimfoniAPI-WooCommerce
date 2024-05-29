<?php

	/* 	Plugin Name: Simfoni Integration
		Description: Integration with Simfoni for processing of gift card orders.
		Version: 1.0
		Author: Baltic Design
		Author URI: https://balticdesign.uk/
	*/

	add_action( 'admin_menu', 'simfoni_add_admin_menu' );
	add_action( 'admin_init', 'simfoni_settings_init' );

	function simfoni_settings_link($links) { 
		$settings_link = '<a href="options-general.php?page=simfoni_settings">Settings</a>'; 
		array_unshift ( $links, $settings_link ); 
	  	return $links; 
	}
	$plugin = plugin_basename(__FILE__); 
	add_filter ( "plugin_action_links_$plugin", 'simfoni_settings_link' );

	function simfoni_add_admin_menu () { 
		add_options_page ( 'Simfoni Settings', 'Simfoni Settings', 'manage_options', 'simfoni_settings', 'simfoni_options_page' );
	}

	function simfoni_settings_init () { 

		register_setting ( 'simfoni_settings_page', 'simfoni_settings' );

		add_settings_section (
			'simfoni_page_simfoni_section', 
			'Simfoni API Credentials', 
			'simfoni_settings_section_callback', 
			'simfoni_settings_page'
		);

		add_settings_field ( 
			'simfoni_api_token', 
			'API Token', 
			'simfoni_api_token_render', 
			'simfoni_settings_page', 
			'simfoni_page_simfoni_section' 
		);

		add_settings_field ( 
			'simfoni_decryption_key', 
			'Decryption Key', 
			'simfoni_decryption_key_render', 
			'simfoni_settings_page', 
			'simfoni_page_simfoni_section' 
		);

		add_settings_field ( 
			'simfoni_endpoint_base_uri', 
			'Endpoint Base URI', 
			'simfoni_endpoint_base_uri_render', 
			'simfoni_settings_page', 
			'simfoni_page_simfoni_section' 
		);
		
		add_settings_field ( 
			'simfoni_digital_email_content', 
			'Digital Gift Card Email Content', 
			'simfoni_digital_email_content_render', 
			'simfoni_settings_page', 
			'simfoni_page_simfoni_section' 
		);

	}

	function simfoni_api_token_render () { 
		$options = get_option ( 'simfoni_settings' );
		echo '<textarea name="simfoni_settings[simfoni_api_token]" class="regular-text" rows="8">' . $options['simfoni_api_token'] . '</textarea>';
	}

	function simfoni_decryption_key_render () { 
		$options = get_option ( 'simfoni_settings' );
		echo '<input type="text" name="simfoni_settings[simfoni_decryption_key]" value="' . $options['simfoni_decryption_key'] . '" class="regular-text">';
	}

	function simfoni_endpoint_base_uri_render () { 
		$options = get_option ( 'simfoni_settings' );
		echo '<input type="text" name="simfoni_settings[simfoni_endpoint_base_uri]" value="' . $options['simfoni_endpoint_base_uri'] . '" class="regular-text">';
	}

	function simfoni_digital_email_content_render () { 
		$options = get_option ( 'simfoni_settings' );
		echo '<textarea name="simfoni_settings[simfoni_digital_email_content]" class="regular-text" rows="8">' . $options['simfoni_digital_email_content'] . '</textarea>';
	}

	function simfoni_settings_section_callback () {
	}

	function simfoni_options_page () { 

		echo '<div class="wrap">';

			echo '<h1>Simfoni Settings</h1>';

			echo '<form action="options.php" method="post">';

				settings_fields ( 'simfoni_settings_page' );
				do_settings_sections ( 'simfoni_settings_page' );
				submit_button();

			echo '</form>';

		echo '</div>';

	}

	function simfoni_add_extra_product_fields() {
		global $woocommerce, $post;

		woocommerce_wp_text_input( 
			array( 
				'id'          => '_client_urn', 
				'label'       => 'Client URN', 
				'placeholder' => '',
				'desc_tip'    => 'true',
				'description' => 'Enter the URN of the Client that owns this product'
			)
		);
		
	}
	add_action( 'woocommerce_product_options_sku', 'simfoni_add_extra_product_fields' );

	function simfoni_save_extra_product_fields( $post_id ){
		$client_urn = isset( $_POST['_client_urn'] ) ? $_POST['_client_urn'] : '';
  
		$product = wc_get_product( $post_id );
		$product->update_meta_data( '_client_urn', $client_urn );
		$product->save();
	}
	add_action( 'woocommerce_process_product_meta', 'simfoni_save_extra_product_fields' );



	function sinfoni_add_order_statuses( $order_statuses ) {
	    $new_order_statuses = array();
	    foreach ( $order_statuses as $key => $status ) {
	        $new_order_statuses[ $key ] = $status;
	        if ( 'wc-processing' === $key ) {
	            $new_order_statuses['wc-partially-synced'] = 'Partially synced with Simfoni';
	            $new_order_statuses['wc-fully-synced'] = 'Fully synced with Simfoni';
	        }
	    }
	    return $new_order_statuses;
	}
	//add_filter( 'wc_order_statuses', 'sinfoni_add_order_statuses' );

	// Woo checks don't actually correctly check this. Dead handy.
	function simfoni_is_product_virtual ( $product_id ) {

		$product = wc_get_product ( $product_id );

		if ( $product->get_type() == 'simple' ) {

			return $product->get_virtual();

		} else {

			$is_virtual = true;
			$variations = $product->get_available_variations();
			foreach ($variations as $variation) {
				if ( $variation['is_virtual'] != 1 ) {
					$is_virtual = false;
					break;
				}
			}
			return $is_virtual;

		}

	}

	// New fields per-product
	function simfoni_display_delivery_date_field() {
		echo '<div class="woocommerce-product-gallery--columns-4 baltic-gf-delivery">';
			echo '<label for="delivery_date">Dispatch Date:</label>';
			echo '<input type="date" id="delivery_date" name="delivery_date" value="">';
		echo '</div>';
		if ( simfoni_is_product_virtual ( get_the_ID() ) ) {
			echo '<div class="woocommerce-product-gallery--columns-4 baltic-gf-delivery">';
				echo '<label for="delivery_time">Time:</label>';
				echo '<input type="time" id="delivery_time" name="delivery_time" value="">';
			echo '</div>';
		}
	}
	add_action( 'woocommerce_before_add_to_cart_button', 'simfoni_display_delivery_date_field', 30 );

	function simfoni_add_delivery_date_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		if( !empty( $_POST['delivery_date'] ) ) {
			$cart_item_data['delivery_date'] = $_POST['delivery_date'];
		}
		if( !empty( $_POST['delivery_time'] ) ) {
			$cart_item_data['delivery_time'] = $_POST['delivery_time'];
		}
		return $cart_item_data;
	}
	add_filter( 'woocommerce_add_cart_item_data', 'simfoni_add_delivery_date_item_data', 10, 4 );

	function simfoni_record_delivery_date_against_order_product( $item, $cart_item_key, $values, $order ) {
		foreach( $item as $cart_item_key=>$values ) {
			if( isset( $values['delivery_date'] ) ) {
				$item->add_meta_data( '_delivery_date', $values['delivery_date'], true );
			}
			if( isset( $values['delivery_time'] ) ) {
				$item->add_meta_data( '_delivery_time', $values['delivery_time'], true );
			}
		}
	}
	add_action( 'woocommerce_checkout_create_order_line_item', 'simfoni_record_delivery_date_against_order_product', 10, 4 );






	function simfoni_display_delivery_address_fields() {

		echo '<div class="baltic-gf-shipping clear">';
			echo '<div>';
				echo '<input type="text" id="delivery_first_name" name="delivery_first_name" value="" placeholder="First Name" />';
			echo '</div>';
			echo '<div>';
				echo '<input type="text" id="delivery_last_name" name="delivery_last_name" value="" placeholder="Last Name" />';
			echo '</div>';

			if ( simfoni_is_product_virtual ( get_the_ID() ) ) {

				echo '<div>';
					echo '<input type="text" id="delivery_email_address" name="delivery_email_address" value="" placeholder="Email Address" />';
				echo '</div>';

			} else {

				echo '<div>';
					echo '<input type="text" id="delivery_address_1" name="delivery_address_1" value="" placeholder="Address Line 1" />';
				echo '</div>';
				echo '<div>';
					echo '<input type="text" id="delivery_address_2" name="delivery_address_2" value="" placeholder="Address Line 2" />';
				echo '</div>';
				echo '<div>';
					echo '<input type="text" id="delivery_address_3" name="delivery_address_3" value="" placeholder="Town/City" />';
				echo '</div>';
				echo '<div>';
					echo '<input type="text" id="delivery_address_4" name="delivery_address_4" value="" placeholder="County" />';
				echo '</div>';
				echo '<div>';
					echo '<input type="text" id="delivery_address_5" name="delivery_address_5" value="" placeholder="Post Code" />';
				echo '</div>';

			}
			
		echo '</div>';
	}
	add_action( 'woocommerce_before_add_to_cart_button', 'simfoni_display_delivery_address_fields', 30 );

	function simfoni_display_manual_price_field () {

		echo '<div>';
			echo '<input type="text" id="manual_price" name="manual_price" value="" placeholder="Specify Price" />';
		echo '</div>';

	}
	//add_action( 'woocommerce_after_variations_form', 'simfoni_display_manual_price_field', 30 );

	function simfoni_add_delivery_address_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		if( !empty( $_POST['delivery_first_name'] ) ) {
			$cart_item_data['delivery_first_name'] = $_POST['delivery_first_name'];
		}
		if( !empty( $_POST['delivery_last_name'] ) ) {
			$cart_item_data['delivery_last_name'] = $_POST['delivery_last_name'];
		}
		if( !empty( $_POST['delivery_address_1'] ) ) {
			$cart_item_data['delivery_address_1'] = $_POST['delivery_address_1'];
		}
		if( !empty( $_POST['delivery_address_2'] ) ) {
			$cart_item_data['delivery_address_2'] = $_POST['delivery_address_2'];
		}
		if( !empty( $_POST['delivery_address_3'] ) ) {
			$cart_item_data['delivery_address_3'] = $_POST['delivery_address_3'];
		}
		if( !empty( $_POST['delivery_address_4'] ) ) {
			$cart_item_data['delivery_address_4'] = $_POST['delivery_address_4'];
		}
		if( !empty( $_POST['delivery_address_5'] ) ) {
			$cart_item_data['delivery_address_5'] = $_POST['delivery_address_5'];
		}
		if( !empty( $_POST['delivery_email_address'] ) ) {
			$cart_item_data['delivery_email_address'] = $_POST['delivery_email_address'];
		}
		if( !empty( $_POST['manual_price'] ) ) {
			$cart_item_data['manual_price'] = $_POST['manual_price'];
		}
		return $cart_item_data;
	}
	add_filter( 'woocommerce_add_cart_item_data', 'simfoni_add_delivery_address_item_data', 10, 4 );

	function simfoni_display_delivery_details_in_cart( $name, $cart_item, $cart_item_key ) {

		$line_item_shipping_details = array();

		if ( isset ( $cart_item['delivery_first_name'] ) ) {
		  $line_item_shipping_details[] = $cart_item['delivery_first_name'] . ( isset ( $cart_item['delivery_last_name'] ) ? ' ' . $cart_item['delivery_last_name'] : '' );
		}
		if ( isset ( $cart_item['delivery_address_5'] ) ) {
		  $line_item_shipping_details[] = $cart_item['delivery_address_5'];
		} else if ( isset ( $cart_item['delivery_email_address'] ) ) {
		  $line_item_shipping_details[] = $cart_item['delivery_email_address'];
		}
		$name .= '<br />Deliver to: ' . implode ( ', ', $line_item_shipping_details );

		if( isset( $cart_item['delivery_date'] ) ) {
		  $name .= '<br />Delivery Date: ' . date ( 'jS F Y', strtotime ( $cart_item['delivery_date'] ) );
		}
		if( isset( $cart_item['delivery_time'] ) ) {
		  $name .= '<br />Delivery Time: ' . $cart_item['delivery_time'];
		}

		return $name;
	}
	add_filter( 'woocommerce_cart_item_name', 'simfoni_display_delivery_details_in_cart', 10, 3 );

	function simfoni_record_delivery_address_against_order_product( $item, $cart_item_key, $values, $order ) {
		foreach( $item as $cart_item_key=>$values ) {
			if( isset( $values['delivery_first_name'] ) ) {
				$item->add_meta_data( '_delivery_first_name', $values['delivery_first_name'], true );
			}
			if( isset( $values['delivery_last_name'] ) ) {
				$item->add_meta_data( '_delivery_last_name', $values['delivery_last_name'], true );
			}
			if( isset( $values['delivery_address_1'] ) ) {
				$item->add_meta_data( '_delivery_address_1', $values['delivery_address_1'], true );
			}
			if( isset( $values['delivery_address_2'] ) ) {
				$item->add_meta_data( '_delivery_address_2', $values['delivery_address_2'], true );
			}
			if( isset( $values['delivery_address_3'] ) ) {
				$item->add_meta_data( '_delivery_address_3', $values['delivery_address_3'], true );
			}
			if( isset( $values['delivery_address_4'] ) ) {
				$item->add_meta_data( '_delivery_address_4', $values['delivery_address_4'], true );
			}
			if( isset( $values['delivery_address_5'] ) ) {
				$item->add_meta_data( '_delivery_address_5', $values['delivery_address_5'], true );
			}
			if( isset( $values['delivery_email_address'] ) ) {
				$item->add_meta_data( '_delivery_email_address', $values['delivery_email_address'], true );
			}
		}
		$item->add_meta_data( '_synced_with_simfoni', 0, true );
		$item->add_meta_data( '_simfoni_complete', 0, true );
		$item->add_meta_data( '_simfoni_cancelled', 0, true );
	}
	add_action( 'woocommerce_checkout_create_order_line_item', 'simfoni_record_delivery_address_against_order_product', 10, 4 );




	
	
	function simfoni_change_order_item_meta_title( $key, $meta, $item ) {

		switch ( $meta->key ) {
		    case '_delivery_first_name':
		        $key = 'Recipient First Name';
		        break;
		    case '_delivery_last_name':
		        $key = 'Recipient Last Name';
		        break;
		   	case '_delivery_email_address':
		        $key = 'Email Address';
		        break;
		    case '_delivery_date':
		        $key = 'Delivery Date';
		        break;
		    case '_delivery_time':
		        $key = 'Delivery Time';
		        break;
		    case '_delivery_address_1':
		        $key = 'Address 1';
		        break;
		    case '_delivery_address_2':
		        $key = 'Address 2';
		        break;
		    case '_delivery_address_3':
		        $key = 'Town/City';
		        break;
		    case '_delivery_address_4':
		        $key = 'County';
		        break;
		    case '_delivery_address_5':
		        $key = 'Post Code';
		        break;
		    case '_synced_with_simfoni':
		        $key = 'Synced?';
		        break;
		    case '_simfoni_complete':
		        $key = 'Complete?';
		        break;
		    case '_simfoni_cancelled':
		        $key = 'Cancelled?';
		        break;
		    case '_simfoni_order_id':
		        $key = 'Order ID';
		        break;
		  	case '_simfoni_url':
		        $key = 'URL';
		        break;
		    case '_simfoni_pin':
		        $key = 'PIN';
		        break;
		    case '_simfoni_api_error':
		        $key = 'API Error';
		        break;
		    case '_baltic_message':
		        $key = 'Message';
		        break;
		    case '_simfoni_line_item_data':
		        $key = 'API Request';
		        break;
		}

	    return $key;
	}
	add_filter( 'woocommerce_order_item_display_meta_key', 'simfoni_change_order_item_meta_title', 20, 3 );

	function simfoni_change_order_item_meta_value( $value, $meta, $item ) {

	    switch ( $meta->key ) {
		    case '_delivery_date':
		        $value = date ( 'jS F Y', strtotime ( $value ) );
		        break;
		    case '_synced_with_simfoni':
		        $value = ( $value == '0' ? '<span class="dashicons dashicons-no" style="color:#a00;"></span>' : '<span class="dashicons dashicons-yes" style="color:#7ad03a;"></span> (Line Item ID: ' . $value . ')' );
		        break;
		    case '_simfoni_complete':
		        $value = ( $value == '0' ? '<span class="dashicons dashicons-no" style="color:#a00;"></span>' : '<span class="dashicons dashicons-yes" style="color:#7ad03a;"></span>' );
		        break;
		    case '_simfoni_cancelled':
		        $value = ( $value == '0' ? '<span class="dashicons dashicons-no" style="color:#a00;"></span>' : '<span class="dashicons dashicons-yes" style="color:#7ad03a;"></span>' );
		        break;
		    case '_simfoni_url':
		        $value = '<a href="' . $value . '" target="_blank">View</a>';
		        break;
		   	case '_simfoni_line_item_data':
		   		$value = '<a href="' . plugin_dir_url( __FILE__ ) . 'viewer.php?order_item_id=' . $item->get_id() . '&?TB_iframe=true&width=600&height=300" class="thickbox">View</a>';
		        break;
		}

	    return $value;
	}
	add_filter( 'woocommerce_order_item_display_meta_value', 'simfoni_change_order_item_meta_value', 20, 3 );



	function set_custom_cart_item_prices( $cart_data, $cart_item_key ) {
	    
	    if ( isset ( $cart_data['manual_price'] ) ) {
	    	$cart_data['data']->set_price( $cart_data['manual_price'] );
	    	$cart_data['manual_price'] = $cart_data['manual_price'];
	    }

	    return $cart_data;
	}
	add_filter( 'woocommerce_add_cart_item', 'set_custom_cart_item_prices', 20, 2 );

	function set_custom_cart_item_prices_from_session( $session_data, $values, $key ) {
	    if ( isset( $session_data['manual_price'] ) && !empty ( $session_data['manual_price'] ) ) {
		    $session_data['data']->set_price( $session_data['manual_price'] );
		}
		return $session_data;
	}
	add_filter( 'woocommerce_get_cart_item_from_session', 'set_custom_cart_item_prices_from_session', 20, 3 );




	function simfoni_find_orders_to_process ( $debug = false ) {

		$args = array();
		$args['post_type'] = 'shop_order';
		//$args['post_status'] = array ( 'wc-processing', 'wc-partially-synced' );
		$args['post_status'] = array ( 'wc-processing' );

		$orders = new WP_Query( $args );

		if ( $orders->have_posts() ) {
		    while ( $orders->have_posts() ) {
		        $orders->the_post();
		        simfoni_process_order ( get_the_ID(), $debug );
		    }
		}

	}

	function simfoni_process_order ( $order_id, $debug = false ) {
		global $wpdb;

		$logs = array();

		$logs[] = 'Beginning processing order ID ' . $order_id;

		$order = new WC_Order( $order_id );

		$address_fields = array();
		$address_fields[] = '_delivery_first_name';
		$address_fields[] = '_delivery_last_name';
		$address_fields[] = '_delivery_address_1';
		$address_fields[] = '_delivery_address_2';
		$address_fields[] = '_delivery_address_3';
		$address_fields[] = '_delivery_address_4';
		$address_fields[] = '_delivery_address_5';

		$billing_address = $order->get_address();
		$billing_address_array = array();
		$billing_address_array['billing_first_name'] = $billing_address['first_name'];
		$billing_address_array['billing_last_name'] = $billing_address['last_name'];
		$billing_address_array['billing_email'] = $billing_address['email'];
		$billing_address_array['billing_address1'] = $billing_address['address_1'];
		$billing_address_array['billing_address2'] = $billing_address['address_2'];
		$billing_address_array['billing_town_city'] = $billing_address['city'];
		$billing_address_array['billing_county'] = $billing_address['state'];
		$billing_address_array['billing_postcode'] = $billing_address['postcode'];
		$billing_address_array['billing_country'] = $billing_address['country'];

		//$order_data = array();
		$order_data['urn'] = 1;
		$digital_item_ids = array();
		$digital_products = array();
		$physical_item_ids = array();
		$physical_products = array();

		foreach ( $order->get_items() as $item_id => $item ) {

			$synced_with_simfoni = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_synced_with_simfoni' AND order_item_id = " . $item_id ); // Can't use get_meta as it caches too aggressively
			$delivery_date = $item->get_meta( '_delivery_date', true );

			$product = $item->get_product();

			$logs[] = 'Beginning processing line item ID ' . $item_id;

			if ( (string)$synced_with_simfoni == '0' ) {

				$logs[] = 'Line item has NOT been synced (_synced_with_simfoni = ' . $synced_with_simfoni . ')';
				$logs[] = 'Delivery date is ' . $delivery_date;

				if ( $delivery_date == '' || $delivery_date == date ( 'Y-m-d' ) ) {

					$logs[] = 'Delivery date is ' . ( $delivery_date == '' ? 'NULL' : date ( 'Y-m-d' ) . ' (today)' ) . ' - begin syncing';

					$line_item = array();
					$line_item['sku'] = $product->get_sku();
					$line_item['price'] = round ( ( ( $item->get_total() + $item->get_total_tax() ) / $item->get_quantity() ), 2 );
					$line_item['quantity'] = $item->get_quantity();

					if ( $delivery_date != '' ) {
						$line_item['delivery_date'] = $delivery_date . 'T23:59:59+00:00';
						$line_item['activation_date'] = $delivery_date . 'T23:59:59+00:00';
					}
							
					if ( $product->is_virtual() ) {

						$logs[] = 'This line item is DIGITAL';

						$line_item['delivery_email'] = $item->get_meta( '_delivery_email_address', true );

						$line_item['delivery_first_name'] = $item->get_meta( '_delivery_first_name', true );
						$line_item['delivery_last_name'] = $item->get_meta( '_delivery_last_name', true );

						$digital_products[get_post_meta( $item->get_product_id(), '_client_urn', true )][] = $line_item;
						$digital_item_ids[get_post_meta( $item->get_product_id(), '_client_urn', true )][] = $item_id;

					} else {

						$logs[] = 'This line item is PHYSICAL';

						$line_item['delivery_method_id'] = 1;

						$line_item['delivery_first_name'] = $item->get_meta( '_delivery_first_name', true );
						$line_item['delivery_last_name'] = $item->get_meta( '_delivery_last_name', true );
						$line_item['delivery_address1'] = $item->get_meta( '_delivery_address_1', true );
						$line_item['delivery_address2'] = $item->get_meta( '_delivery_address_2', true );
						$line_item['delivery_town_city'] = $item->get_meta( '_delivery_address_3', true );
						$line_item['delivery_county'] = $item->get_meta( '_delivery_address_4', true );
						$line_item['delivery_postcode'] = $item->get_meta( '_delivery_address_5', true );
						$line_item['delivery_country'] = 'GB';

						$physical_products[get_post_meta( $item->get_product_id(), '_client_urn', true )][] = $line_item;
						$physical_item_ids[get_post_meta( $item->get_product_id(), '_client_urn', true )][] = $item_id;

					}

					$logs[] = 'Line item data is as follows:';
					$logs[] = json_encode ( $line_item );

				} else {
					
					$logs[] = 'Delivery date is NOT TODAY, do NOT sync';

				}

			} else {

				$logs[] = 'This line item has already been synced';

			}

			$logs[] = 'End processing line item ID ' . $item_id;

		}


		if ( sizeof ( $digital_products ) > 0 ) {

			$count = sizeof ( $digital_item_ids, 1 ) - sizeof ( $digital_item_ids );

			$logs[] = 'There ' . ( $count == 1 ? 'is' : 'are' ) . ' ' . $count . ' digital line item' . ( $count == 1 ? '' : 's' ) . ' to submit to Simfoni';

			foreach ($digital_products as $client_urn => $item) {

				$logs[] = 'Client URN is ' . $client_urn;

				$order_data = array();
				$order_data['urn'] = $client_urn;
				$order_data['items'] = $digital_products[$client_urn];
				$order_data['reference'] = $order_id . '/' . implode ( '/', $digital_item_ids[$client_urn] );

				$order_data = array_merge ( $billing_address_array, $order_data );

				$logs[] = '$digital_item_ids is as follows:';
				$logs[] = json_encode ( $digital_item_ids[$client_urn] );

				$logs[] = 'Order data is as follows:';
				$logs[] = json_encode ( $order_data );

				$simfoni_order = simfoni_request ( 'order', 'POST', json_encode ( $order_data ), $debug );

				if ( $simfoni_order['status'] == 1 ) {

					$simfoni_order_id = $simfoni_order['response']->data[0]->id;

					$logs[] = 'DIGITAL ORDER CREATED';
					$logs[] = 'Simfoni order ID is ' . $simfoni_order_id;

					$order_items = simfoni_request ( 'order/' . $simfoni_order_id . '/items' );
					if ( $order_items['status'] == 1 ) {
						foreach ($order_items['response']->data as $key => $order_line_item) {
							$logs[] = 'Set Woo order line ID ' . $digital_item_ids[$client_urn][$key] . ' to ' . $order_line_item->id;
							wc_update_order_item_meta( $digital_item_ids[$client_urn][$key], '_synced_with_simfoni', $order_line_item->id );
							wc_update_order_item_meta( $digital_item_ids[$client_urn][$key], '_simfoni_order_id', $simfoni_order_id );
							wc_delete_order_item_meta( $digital_item_ids[$client_urn][$key], '_simfoni_api_error' );
							wc_update_order_item_meta( $digital_item_ids[$client_urn][$key], '_simfoni_line_item_data', json_encode ( $order_data ) );
						}
					}

				} else {

					foreach ($digital_item_ids[$client_urn] as $digital_item_id) {
						wc_update_order_item_meta( $digital_item_id, '_simfoni_api_error', $simfoni_order['errors'] );
						wc_update_order_item_meta( $digital_item_id, '_simfoni_line_item_data', json_encode ( $order_data ) );
					}

				}
			}

		}

		if ( sizeof ( $physical_products ) > 0 ) {

			$count = sizeof ( $physical_item_ids, 1 ) - sizeof ( $physical_item_ids );

			$logs[] = 'There ' . ( $count == 1 ? 'is' : 'are' ) . ' ' . $count . ' physical line item' . ( $count == 1 ? '' : 's' ) . ' to submit to Simfoni';

			foreach ($physical_products as $client_urn => $item) {

				$logs[] = 'Client URN is ' . $client_urn;

				$order_data = array();
				$order_data['urn'] = $client_urn;

				$order_data['items'] = $physical_products[$client_urn];
				$order_data['reference'] = $order_id . '/' . implode ( '/', $physical_item_ids[$client_urn] );

				$order_data = array_merge ( $billing_address_array, $order_data );

				$logs[] = '$physical_item_ids is as follows:';
				$logs[] = json_encode ( $physical_item_ids[$client_urn] );

				$logs[] = 'Order data is as follows:';
				$logs[] = json_encode ( $order_data );

				$simfoni_order = simfoni_request ( 'order', 'POST', json_encode ( $order_data ), $debug );

				if ( $simfoni_order['status'] == 1 ) {

					$simfoni_order_id = $simfoni_order['response']->data[0]->id;

					$logs[] = 'PHYSICAL ORDER CREATED';
					$logs[] = 'Simfoni order ID is ' . $simfoni_order_id;

					$order_items = simfoni_request ( 'order/' . $simfoni_order_id . '/items' );
					if ( $order_items['status'] == 1 ) {
						foreach ($order_items['response']->data as $key => $order_line_item) {
							$logs[] = 'Set Woo order line ID ' . $physical_item_ids[$client_urn][$key] . ' to ' . $order_line_item->id;
							wc_update_order_item_meta( $physical_item_ids[$client_urn][$key], '_synced_with_simfoni', $order_line_item->id );
							wc_update_order_item_meta( $physical_item_ids[$client_urn][$key], '_simfoni_order_id', $simfoni_order_id );
							wc_delete_order_item_meta( $physical_item_ids[$client_urn][$key], '_simfoni_api_error' );
						}
					}

				} else {

					foreach ($physical_item_ids[$client_urn] as $physical_item_id) {
						wc_update_order_item_meta( $physical_item_id, '_simfoni_api_error', $simfoni_order['errors'] );
						wc_update_order_item_meta( $item_id, '_simfoni_line_item_data', json_encode ( $order_data ) );
					}

				}

			}

		}

		$logs[] = 'Finished line items.';
		
		/*$line_item_synced_statuses = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$synced_with_simfoni_status = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_synced_with_simfoni' AND order_item_id = " . $item_id ); // Can't use get_meta as it caches too aggressively
			$logs[] = $item_id . ' = ' . $synced_with_simfoni_status;
			$line_item_synced_statuses[] = $synced_with_simfoni_status;

		}
		$line_item_synced_statuses = array_values ( array_unique ( $line_item_synced_statuses ) );

		$logs[] = '$line_item_synced_statuses is as follows:';
		$logs[] = json_encode ( $line_item_synced_statuses );

		if ( in_array ( '0', $line_item_synced_statuses, true ) ) {
			$logs[] = '$line_item_synced_statuses contains a zero';
			if ( sizeof ( $line_item_synced_statuses ) > 1 ) {
				$logs[] = '$line_item_synced_statuses contains a zero and others - set to PARTIALLY';
				//$order->update_status('wc-partially-synced');
			}
		} else {
			$logs[] = '$line_item_synced_statuses contains NO zeroes - set to FULLY';
			//$order->update_status('wc-fully-synced');
			$order->update_status('wc-complete');
		}*/

		$logs[] = 'End processing order ID ' . $order_id;
		$logs[] = ''; // For spacing
		$logs[] = ''; // For spacing

		if ( $debug ) {

			foreach ($logs as $log) {
				echo '<p style="font-family:Arial,sans-serif;font-size:12px;border:1px solid #333;color:#333;padding:10px;margin:5px 0;">' . $log . '</p>';
			}

		}

	}


	function simfoni_decode ( $encryptedString ) {
    
    	$simfoni_configs = get_option ( 'simfoni_settings' );
		$key = base64_decode( $simfoni_configs['simfoni_decryption_key'] );

		$encryptObject = base64_decode($encryptedString);

		$encryptArray = json_decode($encryptObject);

    	$iv = base64_decode($encryptArray->iv);
    	$encryptObjectValue = $encryptArray->value;

    	return unserialize ( openssl_decrypt($encryptObjectValue, 'AES-256-CBC', $key, 0, $iv) );  
	
	}


	function simfoni_request ( $path, $method = 'GET', $post_data = '', $debug = false ) {

		$simfoni_configs = get_option ( 'simfoni_settings' );
		$base_uri = $simfoni_configs['simfoni_endpoint_base_uri'];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $base_uri . $path);
		if ( $method == 'POST' ) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		} else if ( $method == 'PATCH' ) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		} else if ( $method == 'PUT' ) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$simfoni_configs = get_option ( 'simfoni_settings' );
		$token = $simfoni_configs['simfoni_api_token'];

		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
		    'Authorization: Bearer ' . $token
		];

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($ch, CURLOPT_HEADERFUNCTION,
			function($curl, $header) use (&$response_headers) {
		    	$len = strlen($header);
		    	$header = explode(':', $header, 2);
		    	if (count($header) < 2) {
		      		return $len;
		      	}
		    	$response_headers[strtolower(trim($header[0]))] = trim($header[1]);

		    	return $len;
		  	}
		);

		$response = curl_exec ( $ch );
		$response_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ($ch);
		$decoded_response = json_decode ( $response );
		$final_response = array();

		if ( $debug ) {
			echo '<h1>Status: ' . $response_status_code . '</h1>';
			echo '<h2>Response Headers</h2>';
			echo '<pre>';
				print_r ( $response_headers );
			echo '</pre>';

			echo '<h2>Response</h2>';
			echo '<pre>';
				print_r ( $decoded_response );
			echo '</pre>';
		}

		if ( $response_status_code < 400 ) {

			if ( $decoded_response->errors ) {

				$final_response['status'] = 0;
				$final_response['errors'] = $decoded_response->errors;
				update_option ( 'simfoni_api_error', 'Simfoni API Error: ' . $decoded_response->errors );

			} else {

				$final_response['status'] = 1;
				$final_response['response'] = $decoded_response;
				delete_option ( 'simfoni_api_error' );

			}

			if ( $debug ) {
				echo '<h2>Final Response</h2>';
				echo '<pre>';
					print_r ( $final_response );
				echo '</pre>';
			}

		} else {

			if ( $response_status_code == 429 ) { // Limit has been hit - sit tight, then try again

				sleep ( 2 );
				simfoni_request ( $path, $method, $post_data, $debug );

			} else if ( $response_status_code == 403 ) { // Unauthorized

				update_option ( 'simfoni_api_error', 'Simfoni API Error: ' . $decoded_response->message );
				$final_response['status'] = 0;
				$final_response['errors'] = $decoded_response->message;

			} else {

				$final_response['status'] = 0;
				$final_response['errors'] = $decoded_response->message;

			}

		}

		return $final_response;

	}

	function submit_order_to_simfoni( $order_id ) {
	    if ( !$order_id )
	        return;

	    simfoni_process_order ( $order_id );
	}
	add_action( 'woocommerce_order_status_processing', 'submit_order_to_simfoni' );

	function simfoni_customer_email ( $recipient, $subject, $body, $preview = '' ) {

		$logo_image_data = wp_get_attachment_image_src ( get_theme_mod('custom_logo'), 'full' );

		$email_placeholders = array();
		$email_replacements = array();

		$email_placeholders[] = '%PRIMARY_COLOR%';
		$email_replacements[] = get_theme_mod('primary_color');

		$email_placeholders[] = '%LOGO%';
		$email_replacements[] = $logo_image_data[0];

		$email_placeholders[] = '%LOGO_WIDTH%';
		$email_replacements[] = $logo_image_data[1];

		$email_placeholders[] = '%PREVIEW%';
		$email_replacements[] = $preview;

		$email_placeholders[] = '%BODY%';
		$email_replacements[] = $body;

		$email_placeholders[] = '%COPYRIGHT%';
		$email_replacements[] = '&copy; ' . date ( 'Y' ) . ' ' . get_bloginfo('name');

		$mail_headers = "MIME-Version: 1.0\r\n";
		$mail_headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

		$html = file_get_contents ( plugin_dir_path( __FILE__ ) . 'email/default.html' );

		$html = str_replace ( $email_placeholders, $email_replacements, $html );

		echo wp_mail ( $recipient, $subject, $html, $mail_headers );
	}

	
	function my_mail_from_name( $name ) {
	    return get_bloginfo('name');
	}
	add_filter( 'wp_mail_from_name', 'my_mail_from_name' );

	  
	function simfoni_dashboard_widgets() {
		global $wp_meta_boxes;
		wp_add_dashboard_widget('simfoni_order_export_widget', 'Export Gift Card Orders', 'simfoni_order_export_widget');
	}
	add_action('wp_dashboard_setup', 'simfoni_dashboard_widgets');
	 

	function simfoni_order_export_widget() {
		echo '<form action="' . plugin_dir_url( __FILE__ ) . 'export.php" method="POST">';
			echo '<table class="form-table">';
				echo '<tr>';
					echo '<td>From:</td>';
					echo '<td><input type="text" class="jquery-datepicker" name="order_export_from" value="" autocomplete="off" style="width:100%;"></td>';
				echo '</tr>';
				echo '<tr>';
					echo '<td>To:</td>';
					echo '<td><input type="text" class="jquery-datepicker" name="order_export_to" value="" autocomplete="off" style="width:100%;"></td>';
				echo '</tr>';
				/*echo '<tr>';
					echo '<td>Gift Card Type:</td>';
					echo '<td>';
						echo '<select name="type" style="width:100%;">';
							echo '<option value="all">All</option>';
							echo '<option value="physical">Physcial</option>';
							echo '<option value="digital">Digital</option>';
						echo '</select>';
					echo '</td>';
				echo '</tr>';*/
				echo '<tr>';
					echo '<td></td>';
					echo '<td>' . get_submit_button ( 'Export Orders &rsaquo;', 'primary' ) . '</td>';
				echo '</tr>';
			echo '</table>';
		echo '</form>';

		echo '<script>';
			echo '(function($) {';
  				echo "$('.jquery-datepicker').datepicker( { dateFormat: 'dd/mm/yy' } );";
			echo '}(jQuery));';
		echo '</script>';
		wp_enqueue_style( 'jquery-ui-datepicker-style' , '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css');
	}


function woo_order_date_column($format) {
    return __('g:ia d/m/Y', 'woocommerce');   
}
add_filter('woocommerce_admin_order_date_format', 'woo_order_date_column');


function add_simfoni_line_items_order_column_header( $columns ) {

    $new_columns = array();

    foreach ( $columns as $column_name => $column_info ) {

        $new_columns[$column_name] = $column_info;

        if ( 'order_status' === $column_name ) {
            $new_columns['simfoni_digital_line_items'] = 'Digital';
            $new_columns['simfoni_physical_line_items'] = 'Physcial';
        }
    }

    return $new_columns;
}
add_filter( 'manage_edit-shop_order_columns', 'add_simfoni_line_items_order_column_header', 20 );

function simfoni_line_items_order_column_content( $column ) {
    global $post, $wpdb;  

    if ( 'simfoni_digital_line_items' === $column ) {

    	$digital_line_items = 0;
    	$digital_line_items_synced = 0;
    	$digital_line_items_cancelled = 0;
    	$digital_line_items_completed = 0;
    	$order = new WC_Order( $post->ID );
    	foreach ( $order->get_items() as $item_id => $item ) {
    		$product = $item->get_product();
    		if ( $product->is_virtual() ) {

    			$digital_line_items += $item->get_quantity();

    			$synced_with_simfoni = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_synced_with_simfoni' AND order_item_id = " . $item_id );
    			if ( $synced_with_simfoni != 0 || $synced_with_simfoni != '0' ) {
    				$digital_line_items_synced += $item->get_quantity();
    			}

    			$cancelled = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_simfoni_cancelled' AND meta_value = 1 AND order_item_id = " . $item_id );
    			if ( $cancelled ) {
    				$digital_line_items_cancelled += $item->get_quantity();
    			}

    			$completed = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_simfoni_complete' AND meta_value = 1 AND order_item_id = " . $item_id );
    			if ( $completed ) {
    				$digital_line_items_completed += $item->get_quantity();
    			}

    		}
    	}

    	echo $digital_line_items;

    	$digital_line_items_status = array();

    	$digital_line_items_status[] = $digital_line_items_synced . ' Synced';

    	if ( $digital_line_items_cancelled > 0 ) {
    		$digital_line_items_status[] = '<span style="color:red;">' . $digital_line_items_cancelled . ' Cancelled</span>';
    	}

    	if ( $digital_line_items_completed > 0 ) {
    		$digital_line_items_status[] = '<span style="color:green;">' . $digital_line_items_completed . ' Completed</span>';
    	}

    	if ( $digital_line_items > 0 ) {
    		echo '&nbsp;<span style="color:#afafaf;">(' . implode( ' / ', $digital_line_items_status ) . ')</span>';
		}

    } else if ( 'simfoni_physical_line_items' === $column ) {

    	$physical_line_items = 0;
    	$physical_line_items_synced = 0;
    	$physical_line_items_cancelled = 0;
    	$physical_line_items_completed = 0;
    	$order = new WC_Order( $post->ID );
    	foreach ( $order->get_items() as $item_id => $item ) {
    		$product = $item->get_product();
    		if ( !$product->is_virtual() ) {

    			$physical_line_items += $item->get_quantity();

    			$synced_with_simfoni = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_synced_with_simfoni' AND order_item_id = " . $item_id );
    			if ( $synced_with_simfoni != 0 || $synced_with_simfoni != '0' ) {
    				$physical_line_items_synced += $item->get_quantity();
    			}

    			$cancelled = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_simfoni_cancelled' AND meta_value = 1 AND order_item_id = " . $item_id );
    			if ( $cancelled ) {
    				$physical_line_items_cancelled += $item->get_quantity();
    			}

    			$cancelled = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_simfoni_cancelled' AND meta_value = 1 AND order_item_id = " . $item_id );
    			if ( $cancelled ) {
    				$digital_line_items_cancelled += $item->get_quantity();
    			}

    			$completed = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_simfoni_complete' AND meta_value = 1 AND order_item_id = " . $item_id );
    			if ( $completed ) {
    				$physical_line_items_completed += $item->get_quantity();
    			}

    		}
    	}

    	echo $physical_line_items;

    	$physical_line_items_status = array();

    	$physical_line_items_status[] = $physical_line_items_synced . ' Synced';

    	if ( $physical_line_items_cancelled > 0 ) {
    		$physical_line_items_status[] = '<span style="color:red;">' . $physical_line_items_cancelled . ' Cancelled</span>';
    	}

    	if ( $physical_line_items_completed > 0 ) {
    		$physical_line_items_status[] = '<span style="color:green;">' . $physical_line_items_completed . ' Completed</span>';
    	}

    	if ( $physical_line_items > 0 ) {
    		echo '&nbsp;<span style="color:#afafaf;">(' . implode( ' / ', $physical_line_items_status ) . ')</span>';
		}

    }
}
add_action( 'manage_shop_order_posts_custom_column', 'simfoni_line_items_order_column_content' );

/* - - BOF Scheduling - - */

	if ( !wp_next_scheduled ( 'submit_order_to_simfoni_hook' ) ) {
		wp_schedule_event ( time(), 'hourly', 'submit_order_to_simfoni_hook' );
	}
	add_action ( 'submit_order_to_simfoni_hook', 'simfoni_find_orders_to_process' );

	function remove_submit_order_to_simfoni_hook () {
		wp_clear_scheduled_hook( 'submit_order_to_simfoni_hook' );
	}
	register_deactivation_hook ( __FILE__, 'remove_submit_order_to_simfoni_hook' );

/* - - EOF Scheduling - - */


function simfoni_admin_notice(){
    if ( get_option ( 'simfoni_api_error' ) ) {
      	echo '<div class="notice notice-error">';
        	echo '<p>' . get_option ( 'simfoni_api_error' ) . '</p>';
       	echo '</div>';
    }
}
add_action ( 'admin_notices', 'simfoni_admin_notice' );

?>