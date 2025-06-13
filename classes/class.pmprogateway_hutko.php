<?php
//load classes init method

require_once(dirname(__FILE__) . "/hutko.lib.php");

/**
 * PMProGateway_hutko Class
 *
 * Handles hutko integration.
 *
 */

class PMProGateway_hutko extends PMProGateway
{
    /**
     * @var bool
     */
    private $isTestEnv;

    public function __construct($gateway = NULL)
    {
        $this->isTestEnv = pmpro_getOption( "gateway_environment" ) === 'sandbox';
        $this->gateway = $gateway;
        $this->gateway_environment =  pmpro_getOption("gateway_environment");
    }

    public static function install()
    {
        global $wpdb;

        $wpdb->query("ALTER TABLE $wpdb->pmpro_membership_orders ADD hutko_token TEXT");
    }

    public static function uninstall()
    {
        global $wpdb;

        $wpdb->query("ALTER TABLE $wpdb->pmpro_membership_orders DROP COLUMN hutko_token");
    }

    /**
     * Run on WP init
     *
     * @since 1.8
     */
    public static function init()
    {
        //make sure hutko is a gateway option
        add_filter('pmpro_gateways', array('PMProGateway_hutko', 'pmpro_gateways'));

        //localization
        load_plugin_textdomain( 'pmp-hutko-payment', false, basename(PMPRO_HUTKO_DIR).'/languages/' );

        //add plugin setting button
        add_filter('plugin_action_links_' . plugin_basename(PMPRO_HUTKO_BASE_FILE),
            array('PMProGateway_hutko', 'plugin_action_links')
        );

        //add plugin doc button
        add_filter( 'plugin_row_meta', array('PMProGateway_hutko', 'plugin_row_meta'), 10, 2);

        //add fields to payment settings
        add_filter('pmpro_payment_options', array('PMProGateway_hutko', 'pmpro_payment_options'));
        add_filter('pmpro_payment_option_fields', array('PMProGateway_hutko', 'pmpro_payment_option_fields'), 10, 2);

        //code to add at checkout if hutko is the current gateway
        if (pmpro_getGateway() == "hutko") {
            //add_filter('pmpro_include_billing_address_fields', '__return_false');
            add_filter('pmpro_include_payment_information_fields', '__return_false');
            add_filter('pmpro_required_billing_fields', array('PMProGateway_hutko', 'pmpro_required_billing_fields'));
            add_filter('pmpro_checkout_default_submit_button', array(
                'PMProGateway_hutko',
                'pmpro_checkout_default_submit_button'
            ));
            add_filter('pmpro_checkout_before_change_membership_level', array(
                'PMProGateway_hutko',
                'pmpro_checkout_before_change_membership_level'
            ), 10, 2);

            // add js to some admin pmp pages
            add_filter('pmpro_payment_option_fields', array('PMProGateway_hutko', 'addHutkoAdminPageJS'), 11, 2);
            add_filter('pmpro_membership_level_after_other_settings', array('PMProGateway_hutko', 'addHutkoAdminPageJS'));
        }
    }

    /**
     * Make sure hutko is in the gateways list
     *
     * @since 1.8
     */
    public static function pmpro_gateways($gateways)
    {
        if (empty($gateways['hutko'])) {
            $gateways['hutko'] = __('Hutko', 'pmp-hutko-payment');
        }

        return $gateways;
    }

    public static function pmpro_required_billing_fields($fields)
    {
        //unset($fields['bfirstname']);
        //unset($fields['blastname']);
        unset($fields['baddress1']);
        unset($fields['bcity']);
        unset($fields['bstate']);
        unset($fields['bzipcode']);
        //unset($fields['bphone']);
        unset($fields['bemail']);
        unset($fields['bcountry']);
        unset($fields['CardType']);
        unset($fields['AccountNumber']);
        unset($fields['ExpirationMonth']);
        unset($fields['ExpirationYear']);
        unset($fields['CVV']);

        return $fields;
    }

    /**
     * Get a list of payment options that the hutko gateway needs/supports.
     *
     * @since 1.8
     */
    public static function getGatewayOptions()
    {
        $options = array(
            'sslseal',
            'nuclear_HTTPS',
            'gateway_environment',
            'hutko_merchantid',
            'hutko_securitykey',
            'currency',
            'use_ssl',
            'tax_state',
            'tax_rate',
            'accepted_credit_cards'
        );

        return $options;
    }

    /**
     * Set payment options for payment settings page.
     *
     * @since 1.8
     */
    public static function pmpro_payment_options($options)
    {
        //get hutko options
        $hutko_options = PMProGateway_hutko::getGatewayOptions();

        //merge with others.
        $options = array_merge($hutko_options, $options);

        return $options;
    }

    /**
     * Display fields for hutko options.
     *
     * @param $values
     * @param $gateway
     */
    public static function pmpro_payment_option_fields($values, $gateway)
    {
        include( PMPRO_HUTKO_DIR .'/views/payment-option-fields.php' );
    }

    /**
     * Swap in our submit buttons.
     *
     * @since 1.8
     */
    public static function pmpro_checkout_default_submit_button($show)
    {
        $text_domain = 'pmpro';

        if (version_compare('1.8.13.6', PMPRO_VERSION, '<=')) {
            $text_domain = 'paid-memberships-pro';
        }

        include( PMPRO_HUTKO_DIR .'/views/submit-button.php' );

        //don't show the default
        return false;
    }

    /**
     * @param $user_id
     * @param $morder
     */
    public static function pmpro_checkout_before_change_membership_level($user_id, $morder)
    {
        global $discount_code_id, $wpdb;

        //if no order, no need to pay
        if (empty($morder)) {
            return;
        }

        $morder->user_id = $user_id;
        $morder->saveOrder();

        //save discount code use
        if (!empty($discount_code_id)) {
            $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())");
        }

        do_action("pmpro_before_send_to_hutko", $user_id, $morder);

        $morder->Gateway->sendToHutko($morder);
    }


    /**
     * add plugin setting button
     *
     * @param $links
     * @return mixed
     */
    public static function plugin_action_links($links)
    {
        $settings_link = '<a href="'. admin_url('admin.php?page=pmpro-paymentsettings') .'">'. __("Settings") .'</a>';
        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * add plugin row buttons
     *
     * @param $links
     * @param $file
     * @return array
     */
    public static function plugin_row_meta($links, $file)
    {
        if(strpos($file, basename(PMPRO_HUTKO_BASE_FILE)) !== false) {
            $row_links = array(
                '<a href="https://hutko.org/en/cms/wordpress/wordpress-paid-membership-pro/" title="' . __('View Documentation', 'pmp-hutko-payment') . '">' . __('Docs', 'pmp-hutko-payment') . '</a>',
            );
            $links = array_merge( $links, $row_links );
        }

        return $links;
    }

    /**
     * add js to admin page
     *
     * @since 1.0.6
     */
    public static function addHutkoAdminPageJS()
    {
        wp_enqueue_script(
            'hutko-pmp',
            plugins_url('assets/js/hutko.js', plugin_basename(PMPRO_HUTKO_BASE_FILE)),
            [],
            PMPRO_HUTKO_VERSION,
            true
        );

        if (sanitize_text_field($_REQUEST['page']) === 'pmpro-membershiplevels') {
            wp_localize_script('hutko-pmp', 'hutko_param', [
                'trialDescriptionText' => 'Hutko integration currently does not support trial amounts greater than 0.', //todo l10n
            ]);
        }
    }

    /**
     * @param $order
     * @return bool
     */
    public function process(&$order)
    {
        if (empty($order->code)) {
            $order->code = $order->getRandomCode();
        }

        //clean up a couple values
        $order->payment_type = "Hutko";
        $order->CardType = "";
        $order->cardtype = "";


        $order->status = "review";
        $order->saveOrder();

        return true;
    }

    /**
     * @param MemberOrder $order
     */
    public function sendToHutko(&$order)
    {
        global $pmpro_currency;

        //taxes on initial amount
        $initial_payment = $order->InitialPayment;
        $initial_payment_tax = $order->getTaxForPrice($initial_payment);
        $initial_payment = round((float)$initial_payment + (float)$initial_payment_tax, 2);

        if (empty($order->code))
            $order->code = $order->getRandomCode();

        $hutko_args = array(
            'merchant_data' => json_encode(array(
                'name' => $order->billing->name,
                'phone' => $order->billing->phone
            )),
            'product_id' => $order->membership_id,
            'order_id' => $order->code,
            'merchant_id' => $this->isTestEnv ? HutkoForm::TEST_MERCHANT_ID : pmpro_getOption("hutko_merchantid"),
            'order_desc' => substr($order->membership_level->name . " at " . get_bloginfo("name"), 0, 127),
            'amount' => round($initial_payment * 100),
            'currency' => $pmpro_currency,
            'response_url' => admin_url("admin-ajax.php") . "?action=hutko-ins",
            'server_callback_url' => admin_url("admin-ajax.php") . "?action=hutko-ins",
            'sender_email' => $order->Email,
            'verification' => $order->InitialPayment === 0.0 ? 'Y' : 'N',
        );

        if (pmpro_isLevelRecurring($order->membership_level)) {
            $hutko_args['required_rectoken'] = 'Y';
            $hutko_args['recurring_data'] = $this->getRecurringData($order);
            $hutko_args['subscription'] = 'Y';
            $hutko_args['subscription_callback_url'] = admin_url("admin-ajax.php") . "?action=hutko-ins";

            //filter order before subscription. use with care.
            $order = apply_filters("pmpro_subscribe_order", $order, $this);
            $order->subscription_transaction_id = $order->code;
        }

        $order->status = "pending";
        $order->payment_transaction_id = $order->code;
        //update order
        $order->saveOrder();

        $response = $this->sendRequest($hutko_args);
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $message = wp_remote_retrieve_response_message($response);
        $out = json_decode($body, true);

        if ($code === 200 && $message === 'OK') {
            if (is_string($out)) {
                wp_parse_str($out, $out);
            }

            if (isset($out['response']['error_message'])) {
                $error = '<p>' . __('Error message: ', 'pmp-hutko-payment') . ' ' . $out['response']['error_message'] . '</p>';
                $error .= '<p>' . __('Error code: ', 'pmp-hutko-payment') . ' ' . $out['response']['error_code'] . '</p>';

                wp_die($error, __('Error'), array('response' => '401'));
            } else {
                $url = json_decode(base64_decode($out['response']['data']), true)['order']['checkout_url'];
                wp_redirect($url);
                exit;
            }
        }

        exit; //mb add error handler
    }

    /**
     * @param MemberOrder $order
     * @return array
     */
    private function getRecurringData($order)
    {
        $every = intval($order->BillingFrequency);
        $period = strtolower($order->BillingPeriod);
        $startTS = strtotime('+ ' . $every . ' ' . $period);

        if ($order->BillingPeriod === 'Year'){ // hutko doesn't have 'year' period
            $every *= 12;
            $period = 'month';
            $startTS = strtotime('+ 1 month');
        }

        $recurringData =  array(
            'start_time' => date('Y-m-d', $startTS),
            'amount' => round($order->PaymentAmount * 100),
            'every' => $every,
            'period' => $period,
            'state' => 'shown_readonly',
            'readonly' => 'Y'
        );

        if (!empty($order->TotalBillingCycles)){
            $recurringData["quantity"] = intval($order->TotalBillingCycles);
        }

        if (pmpro_isLevelTrial($order->membership_level)){
            $trialPeriod = strtolower($order->TrialBillingPeriod);
            $trialQuantity = intval($order->TrialBillingCycles);

            if ($order->TrialBillingPeriod === 'Year'){
                $trialPeriod = 'month';
                $trialQuantity *= 12;
            }

            //$recurringData["trial_amount"] = $order->TrialAmount; // w8 api realisation
            $recurringData["trial_period"] = $trialPeriod;
            $recurringData["trial_quantity"] = $trialQuantity;
        }

        return $recurringData;
    }

    /**
     * @param array $args
     * @return array|WP_Error
     */
    private function sendRequest($args)
    {
        $secretKey = $this->isTestEnv ? HutkoForm::TEST_MERCHANT_KEY : pmpro_getOption("hutko_securitykey");

        $fields = [
            "version" => "2.0",
            "data" => base64_encode(json_encode(array('order' => $args))),
            "signature" => HutkoForm::getSignature($args, $secretKey)
        ];

        $response = wp_remote_post(HutkoForm::API_CHECKOUT_URL, array(
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 45,
                'method' => 'POST',
                'sslverify' => true,
                'httpversion' => '1.1',
                'body' => json_encode(array('request' => $fields))
            )
        );

        if (is_wp_error($response)) {
            $error = '<p>' . __('An unidentified error occurred.', 'pmp-hutko-payment') . '</p>';
            $error .= '<p>' . $response->get_error_message() . '</p>';

            wp_die($error, __('Error'), array('response' => '401'));
        }

        return $response;
    }
}
