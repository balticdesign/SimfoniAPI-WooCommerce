<?php 	require ( '../../../wp-load.php' );
		$meta = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_simfoni_line_item_data' AND order_item_id = " . $_GET['order_item_id'] );
		echo '<pre>';
			print_r ( json_decode ( $meta ) );
		echo '<pre>';

?>