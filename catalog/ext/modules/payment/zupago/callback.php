<?php
  chdir('../../../../');
  require('includes/application_top.php');

  function payment_error($msg = '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Status: 500 Internal Server Error');
    
    echo "Error! " . $msg;
    error_log("ZuPago HyBrid (HD) Wallet payment confirmation error: " . $msg);
  }
  
  global $currencies;
  
  $passphrase = strtoupper(md5(MODULE_PAYMENT_ZUPAYEE_ACC_KEY));
  
  $string=
      $HTTP_POST_VARS['PAYMENT_REF'].':'.$HTTP_POST_VARS['ZUPAYEE_ACC'].':'.$HTTP_POST_VARS['ZUPAYEE_ACC_BTC'].':'.
      $HTTP_POST_VARS['PAYMENT_AMOUNT'].':'.$HTTP_POST_VARS['CURRENCY_TYPE'].':'.
      $HTTP_POST_VARS['tokan'].':'.
      $HTTP_POST_VARS['ZUPAYER_ACC'].':'.$passphrase.':'.
      $HTTP_POST_VARS['TIMESTAMPGMT'];

  $hash=strtoupper(md5($string));

  if ($hash==$HTTP_POST_VARS['V2_HASH']) {  // proccessing payment if only hash is valid
      $order_query = tep_db_query("select orders_status, currency, currency_value from " . TABLE_ORDERS . " where orders_id = '" . (int)$HTTP_POST_VARS['PAYMENT_REF'] . "'");

    if (tep_db_num_rows($order_query) > 0) {

      $total_query = tep_db_query("select value from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$HTTP_POST_VARS['PAYMENT_REF'] . "' and class = 'ot_total' limit 1");
      $total = tep_db_fetch_array($total_query);
            
      // final check of payment validity
      if (number_format($total['value'] * $currencies->get_value('USD'), $currencies->currencies['USD']['decimal_places'], '.', '') == $HTTP_POST_VARS['PAYMENT_AMOUNT'] && MODULE_PAYMENT_ZUPAGO_PAYEE_ACC == $HTTP_POST_VARS['ZUPAYEE_ACC'] && MODULE_PAYMENT_ZUPAGO_PAYEE_ACC_BTC == $HTTP_POST_VARS['ZUPAYEE_ACC_BTC'] && MODULE_PAYMENT_ZUPAGO_CURRENCY_TYPE == $HTTP_POST_VARS['CURRENCY_TYPE']) {
        $comment_status = $HTTP_POST_VARS['tokan'] . '; ' . $currencies->format($HTTP_POST_VARS['PAYMENT_AMOUNT'], false, $HTTP_POST_VARS['CURRENCY_TYPE']);

        $order_status_id = (MODULE_PAYMENT_ZUPAGO_ORDER_STATUS_ID > 0 ? (int)MODULE_PAYMENT_ZUPAGO_ORDER_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID);

        tep_db_query("update " . TABLE_ORDERS . " set orders_status = '" . $order_status_id . "', last_modified = now() where orders_id = '" . (int)$HTTP_POST_VARS['PAYMENT_REF'] . "'");

        $sql_data_array = array('orders_id' => $HTTP_POST_VARS['PAYMENT_REF'],
                                'orders_status_id' => $order_status_id,
                                'date_added' => 'now()',
                                'customer_notified' => '0',
                                'comments' => 'ZuPago HyBrid (HD) Wallet Verified [' . $comment_status . ']');

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

      } else {
        payment_error("Payment details are not valid.");
      }
    } else {
      payment_error("Order not found.");
    }
  } else {
    payment_error("Hashes are not valid. Our hash: " . $hash . ", ZuPago hash: " .$HTTP_POST_VARS['V2_HASH']);
  }

//  require('includes/application_bottom.php');
?>
