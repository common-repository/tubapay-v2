<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (isset($_GET['call']) && $_GET['call'] == 'check_connection') {
    $tubapay = new WC_Gateway_TubaPay2();
    $response = $tubapay->getAPIConnectionStatus();
    die();
}

if (isset($_GET['call']) && $_GET['call'] == 'get_installments') {
    global $woocommerce;
    $amount = $woocommerce->cart->total;

    $tuba_gw = new WC_Gateway_TubaPay2();
    $html = $tuba_gw->getSelectInstallmentsInputForAmount($amount);

    echo wp_kses(
        $html,
        array(
            'div'      => array(
                'class' => array(),
                'style' => array(),
            ),
            'p'      => array(
                'style' => array(),
            ),
            'span'      => array(
                'class'  => array(),
            ),
            'input'     => array(
                'type'      => array(),
                'class'     => array(),
                'value'     => array(),
                'name'      => array(),
                'id'        => array(),
                'style'     => array(),
                'checked'   => array(),
                'required'  => array(),
            ),
            'label'     => array(
                'style' => array(),
                'for'   => array(),
                'class' => array(),
            ),
            'a'     => array(
                'href'      => array(),
                'target'    => array(),
                'class'     => array(),
            ),
        )
    );

    die();
}

$dataPOST = sanitize_text_field(trim(file_get_contents('php://input')));
$dataPOST = json_decode($dataPOST);

if (!is_object($dataPOST)) {
    echo "invalid JSON";
    die();
}

$metadata = $dataPOST->metaData;
$payload = $dataPOST->payload;

if ($metadata->commandType == 'TRANSACTION_STATUS_CHANGED') {

    $transaction = $payload->transaction;
    $order_id = intval(sanitize_text_field($transaction->externalRef));
    $order = wc_get_order( $order_id );
    $status = esc_html(sanitize_text_field($transaction->agreementStatus));
    $agreementNumber = intval(sanitize_text_field($transaction->agreementNumber));

    if (!$order) {
        http_response_code(400);
        die("missing order number ".esc_html($order_id)." ");
    } else {
        if ($status == 'registered') {
            update_post_meta($order_id, 'tubapay2Response', $status);
            echo "OK";
        } elseif ($status == 'signed') {
            update_post_meta($order_id, 'tubapay2Response', $status);
            echo "OK";
        } elseif ($status == 'accepted') {
            $order->update_status(tubapay2_get_status('wc-tubapay2-accepted' ), __('Zaakceptowano przez TubaPay', 'tubapay-v2'));
            update_post_meta($order_id, 'agreementNumber', $agreementNumber);
            update_post_meta($order_id, 'tubapay2Response', $status);
            echo "OK";
        } elseif ($status == 'rejected') {
            $order->update_status(tubapay2_get_status('wc-tubapay2-rejected' ), __('Odrzucono przez TubaPay', 'tubapay-v2'));
            update_post_meta($order_id, 'agreementNumber', $agreementNumber);
            update_post_meta($order_id, 'tubapay2Response', $status);
            echo "OK";
        } elseif ($status == 'canceled') {
            update_post_meta($order_id, 'tubapay2Response', $status);
            echo "OK";
        } elseif ($status == 'terminated' || $status == 'terminatedBySystem') {
            $order->update_status(tubapay2_get_status('wc-tubapay2-terminated' ), __('Wypowiedziana przez TubaPay', 'tubapay-v2'));
            update_post_meta($order_id, 'agreementNumber', $agreementNumber);
            update_post_meta($order_id, 'tubapay2Response', $status);
            echo "OK";
        } elseif ($status == 'withdrew') {
            update_post_meta($order_id, 'tubapay2Response', $status);
            echo "OK";
        } elseif ($status == 'repaid') {
            update_post_meta($order_id, 'tubapay2Response', $status);
            echo "OK";
        } elseif ($status == 'closed') {
            update_post_meta($order_id, 'tubapay2Response', $status);
            echo "OK";
        } else {
            update_post_meta($order_id, 'tubapay2Response', $status);
            http_response_code(400);
            echo "status ".esc_html($status)." unknown";
        }

    }

} elseif ($metadata->commandType == 'CUSTOMER_RECURRING_ORDER_REQUEST') {
    $tuba_gw = new WC_Gateway_TubaPay2();
    if ($tuba_gw->get_option( 'partial_orders' ) !== 'yes') {
        die('partial orders not enabled');
    }

    $transaction = $payload->transaction;
    $order_id = intval(sanitize_text_field($transaction->externalRef));
    $order = wc_get_order( $order_id );

    if (!$order) {
        http_response_code(400);
        die("missing order number: ".esc_html($order_id)." ");
    } else {
        ## Changing main status
        $order->update_status('wc-tubapay2-p-pend', '', false);
        $order->save();

        ### New partial order
        $shipping_address = $order->get_address('shipping');
        $billing_address = $order->get_address('billing');

        $partial_order = wc_create_order();

        $partial_order->set_created_via( 'rest-api' );
        $partial_order->set_customer_id( $order->get_customer_id());

        $partial_order->set_payment_method( 'tubapay2' );
        $partial_order->set_payment_method_title( 'Tubapay częściowa płatność' );

        $partial_order->save();

        $partial_order->set_address( $shipping_address, 'shipping' );
        $partial_order->set_address( $billing_address, 'billing' );
        
        $main_total = $order->get_total();
        $partial_total = $transaction->requestTotalAmount;
        
        $tax_rates = WC_Tax::find_rates( array( 'country' => 'PL', 'state' => '', 'postcode' => '', 'tax_class' => '', ) );
        if ( ! empty( $tax_rates ) ) {
            foreach ( $tax_rates as $rate ) {
                $tax_rate_percent = $rate['rate'];
            }
        }
        if ($tax_rate_percent > 0) {
            $tax_rate = $tax_rate_percent / 100;
        } else {
            $tax_rate = 0;
        }
        
        $products_array = array();
        $proportions_calc = 0;
        $products = $order->get_items();
        foreach ($products as $product) {
            $product_total = $product->get_total() + $product->get_total_tax();
            $proportions = $product_total / $main_total;
            $products_array[] = array(
                'id' => $product->get_id(),
                'product' => $product,
                'proportions' => $product_total / $main_total,
            );
            $proportions_calc += round($proportions,2);
        }
        
        foreach ($products_array as $product) {
            $proportions = $product['proportions'];
            $product = $product['product'];
            $product = $product->get_product();
            $product_name = $product->get_title();
            $payment_name = $product_name." - cz. ".$transaction->requestPositions[0]->rateNumber;
            
            $fee_amount = $partial_total * $proportions;
            
            $fee = new WC_Order_Item_Fee();
            $fee->set_name( $payment_name );
            $fee->set_tax_class( '' );
            $fee->set_tax_status( 'taxable' );

            $fee_net_amount = $fee_amount / (1 + $tax_rate);
            $fee_tax_amount = $fee_amount - $fee_net_amount;
            $fee_net_amount = round($fee_net_amount, 2);;
            
            $fee->set_amount( $fee_net_amount );
            $fee->set_total( $fee_net_amount );
            $fee->set_total_tax($fee_tax_amount);
            
            $partial_order->add_item( $fee );
        }
        $partial_order->calculate_totals();
        
        $porder_net_amount = $partial_total / (1 + $tax_rate);
        $porder_tax_amount = $partial_total - $porder_net_amount;
        $porder_net_amount = round($porder_net_amount, 2);
        $porder_tax_amount = round($porder_tax_amount, 2);

        
        $partial_order->set_cart_tax( $porder_tax_amount );
        $partial_order->set_total( $partial_total );
        
        $existing_taxes = $partial_order->get_taxes();
        
        foreach ( $existing_taxes as $tax_item ) {
            $tax_item->set_tax_total( $porder_tax_amount );
            $tax_item->set_shipping_tax_total( 0 );
            $tax_item->save();
        }
        
        $partial_order->set_status("completed", 'Tubapay order');

        $partial_order->update_meta_data( 'tubapay2_main_order', $order_id );
        $partial_order->save();

        ## Adding partials to main order:
        $partial_orders = $order->get_meta('tubapay2_partial_orders');
        $partial_orders_array = json_decode($partial_orders, true);
        $partial_orders_array[] = $partial_order->get_id();
        $partial_orders = wp_json_encode($partial_orders_array);
        $order->update_meta_data( 'tubapay2_partial_orders', $partial_orders );
        $order->save();

        ## Suming up partial orders
        $partials_sum = 0.0;
        foreach ($partial_orders_array as $partial_order) {
            $partials_sum += wc_get_order( $partial_order )->get_total();
        }
        $partials_sum = round($partials_sum,0);

        $main_sum = floatval($order->get_total());

        // If main is paid in partials
        if ($partials_sum >= $main_sum) {
            $order->update_status('wc-tubapay2-p-paid', '', false);
            $order->save();
        }

    }

}