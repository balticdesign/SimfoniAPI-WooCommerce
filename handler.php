<?php 	// This is the destination for Simfoni's webhook
		// They send us the Order ID and hash
		// We use his hash to request 'Issued Info'
		// 'Issued Info' contains encrypted data, which we decrypt and then store against the order line item

		require ( '../../../wp-load.php' );

		$json = file_get_contents('php://input');

		wp_mail ( 'info@rocketsites.co.uk', 'Simfoni', $json );

		$decoded = json_decode( $json );
		$event = $decoded->event;
		$simfoni_order_id = $decoded->data->order_id;
		$simfoni_hash = $decoded->data->hash;

		echo 'Order ID: ' . $simfoni_order_id . '<br />';
		echo 'Hash: ' . $simfoni_hash . '<br />';

		if ( $event == 'order.complete' ) {

			$issuedinfo = simfoni_request ( 'order/' . $simfoni_hash . '/issuedinfo' );

			if ( $issuedinfo['status'] == 1 ) {

				foreach ($issuedinfo['response']->data as $card) {

					$order_item_id = $wpdb->get_var( "SELECT order_item_id FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_synced_with_simfoni' AND meta_value = '" . $card->item_id . "'" );

					echo 'Card ID: ' . $card->item_id . '<br />';
					echo 'Line Item ID: ' . $order_item_id . '<br />';

					wc_update_order_item_meta ( $order_item_id, '_simfoni_complete', 1 );

					if ( $card->url && $card->url != '' ) {

						$gift_card_url = simfoni_decode ( $card->url );
				
						wc_add_order_item_meta ( $order_item_id, '_simfoni_url', $gift_card_url );
						wc_add_order_item_meta ( $order_item_id, '_simfoni_pin', simfoni_decode ( $card->pin ) );

						$recipient_email = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE order_item_id = " . $order_item_id . " AND meta_key = '_delivery_email_address'" );
						$message = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE order_item_id = " . $order_item_id . " AND meta_key = '_baltic_message'" );
						//$recipient_first_name = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE order_item_id = " . $order_item_id . " AND meta_key = '_delivery_first_name'" );
						//$recipient_last_name = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE order_item_id = " . $order_item_id . " AND meta_key = '_delivery_last_name'" );

						$body = '<tr>';
							$body .= '<td valign="top" class="mcnTextContent" style="padding-top:0; padding-right:18px; padding-bottom:9px; padding-left:18px;text-align: center;">';
								if ( $message != '' ) {
									$body .= '<p style="text-align:center;">' . $message . '</p>';
								} else {
									$simfoni_configs = get_option ( 'simfoni_settings' );
									$email_message = $simfoni_configs['simfoni_digital_email_content'];
									$body .= str_replace( '<p>', '<p style="text-align:center;">', $email_message );
								}
							$body .= '</td>';
						$body .= '</tr>';

						$body .= '<tr>';
							$body .= '<td align="center" valign="top" style="padding-top:0; padding-right:18px; padding-bottom:18px; padding-left:18px;">';
								$body .= '<table border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate !important;border-radius: 5px;background-color:' . get_theme_mod('accent_color1') . ';">';
									$body .= '<tbody>';
										$body .= '<tr>';
											$body .= '<td valign="middle" style="font-family: Lato, Helvetica, Arial, sans-serif; font-size: 16px; padding: 15px;">';
												$body .= '<a class="mcnButton" title="Click here" href="' . $gift_card_url . '" target="_blank" style="font-weight: bold;letter-spacing: normal;line-height: 100%;text-align: center;text-decoration: none;color: #FFFFFF;">Click here to access your gift card!</a>';
											$body .= '</td>';
										$body .= '</tr>';
									$body .= '</tbody>';
								$body .= '</table>';
							$body .= '</td>';
						$body .= '</tr>';

						simfoni_customer_email ( $recipient_email, 'Your Gift Card!', $body, 'Your gift card!' );

					}

				}

			}

			$woo_order_id = $wpdb->get_var( "SELECT order_id FROM " . $wpdb->prefix . "woocommerce_order_itemmeta oim, " . $wpdb->prefix . "woocommerce_order_items oi WHERE oi.order_item_id = oim.order_item_id AND meta_key = '_simfoni_order_id' AND meta_value = '" . $simfoni_order_id . "'" );
			$not_yet_complete = $wpdb->get_results( "SELECT * FROM " . $wpdb->prefix . "woocommerce_order_itemmeta oim, " . $wpdb->prefix . "woocommerce_order_items oi WHERE oi.order_item_id = oim.order_item_id AND order_id = " . $woo_order_id . " AND meta_key = '_simfoni_complete' AND meta_value = 0" );
			if ( sizeof ( $not_yet_complete ) == 0 ) {
				$order = new WC_Order( $woo_order_id );
				$order->update_status('wc-completed');
			}

		} else if ( $event == 'order.cancel' ) {

			$order_items_in_cancelled_order = $wpdb->get_results( "SELECT order_item_id FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_simfoni_order_id' AND meta_value = " . $simfoni_order_id );

			if ( sizeof ( $order_items_in_cancelled_order ) > 0 ) {
				foreach ($order_items_in_cancelled_order as $order_item_in_cancelled_order) {
					wc_update_order_item_meta ( $order_item_in_cancelled_order->order_item_id, '_simfoni_complete', 0 );
					wc_update_order_item_meta ( $order_item_in_cancelled_order->order_item_id, '_simfoni_cancelled', 1 );
				}
			}

		}

?>