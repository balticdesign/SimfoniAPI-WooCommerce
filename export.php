<?php 	require ( '../../../wp-load.php' );

		$from_date = explode ( '/', $_POST['order_export_from'] );
		$to_date = explode ( '/', $_POST['order_export_to'] );

		// Headers
		$csv_rows[0][] = 'Order ID';
		$csv_rows[0][] = 'Simfoni Order ID';
		$csv_rows[0][] = 'Order Type';
		$csv_rows[0][] = 'Status';
		$csv_rows[0][] = 'Email Address';
		$csv_rows[0][] = 'Recipient First Name';
		$csv_rows[0][] = 'Recipient Last Name';
		$csv_rows[0][] = 'Recipient Email Address';
		$csv_rows[0][] = 'Delivery Address Line 1';
		$csv_rows[0][] = 'Delivery Address Line 2';
		$csv_rows[0][] = 'Delivery Address Line 3';
		$csv_rows[0][] = 'Delivery Address Line 4';
		$csv_rows[0][] = 'Delivery Address Line 5';
		$csv_rows[0][] = 'Message';
		$csv_rows[0][] = 'Product';
		$csv_rows[0][] = 'SKU';
		$csv_rows[0][] = 'Delivery Method';
		$csv_rows[0][] = 'Price';
		$csv_rows[0][] = 'Quantity';
		$csv_rows[0][] = 'Order Date';
		$csv_rows[0][] = 'Delivery Date';

		$args = array();
		$args['post_type'] = 'shop_order';
		$args['post_status'] = array ( 'wc-processing', 'wc-partially-synced' );
		$args['date_query']['after']['year'] = $from_date[2];
		$args['date_query']['after']['month'] = $from_date[1];
		$args['date_query']['after']['day'] = $from_date[0];
		$args['date_query']['before']['year'] = $to_date[2];
		$args['date_query']['before']['month'] = $to_date[1];
		$args['date_query']['before']['day'] = $to_date[0];

		$orders = new WP_Query( $args );

		if ( $orders->have_posts() ) {

			$row = 1;

		    while ( $orders->have_posts() ) {

		        $orders->the_post();

		        $order_id = get_the_ID();

		        $order = new WC_Order( $order_id );

		        //echo '<pre>';
		        //	print_r ( $order );
		        //echo '</pre>';

		        foreach ( $order->get_items() as $item_id => $item ) {

		        	$product = $item->get_product();
		        	$billing_address = $order->get_address();
		        	$synced_with_simfoni = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_synced_with_simfoni' AND order_item_id = " . $item_id ); // Can't use get_meta as it caches too aggressively

		        	$csv_rows[$row][] = $order_id;
		        	$csv_rows[$row][] = $synced_with_simfoni;
		        	$csv_rows[$row][] = ( $product->is_virtual() ? 'Email' : 'Physical' );
		        	$csv_rows[$row][] = ( strlen ( $synced_with_simfoni ) > 1 ? 'Synced' : 'Un-synced' );
		        	$csv_rows[$row][] = $billing_address['email'];
			        $csv_rows[$row][] = $item->get_meta( '_delivery_first_name', true );
					$csv_rows[$row][] = $item->get_meta( '_delivery_last_name', true );
					$csv_rows[$row][] = $item->get_meta( '_delivery_email_address', true );
					for ($k = 1; $k <= 5; $k++){
						$csv_rows[$row][] = $item->get_meta( '_delivery_address_' . $k, true );
					}
					
					$csv_rows[$row][] = $item->get_meta( '_baltic_message', true );
					$csv_rows[$row][] = $product->get_name();
					$csv_rows[$row][] = $product->get_sku();
					$csv_rows[$row][] = $order->get_shipping_method();
					$csv_rows[$row][] = round ( ( ( $item->get_total() + $item->get_total_tax() ) / $item->get_quantity() ), 2 );
					$csv_rows[$row][] = $item->get_quantity();
					$csv_rows[$row][] = $product->get_date_created();
					$csv_rows[$row][] = $item->get_meta( '_delivery_date', true );

					$row++;

			    }

			}

		}

		header('Content-Type: application/csv');
    	header('Content-Disposition: attachment; filename="Order Export.csv";');

    	$file = fopen('php://output', 'w');
	    foreach ($csv_rows as $csv_row) {
	        fputcsv($file, $csv_row);
	    }

?>