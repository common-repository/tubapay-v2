<?php

if(!class_exists('WC_Gateway_TubaPay2')) {
    class WC_Gateway_TubaPay2 extends WC_Payment_Gateway
    {

        public $domain = 'tubapay-v2';
        public $api;

        private $tubapay2_instructions = 'Hej, tu TubaPay!

Jestem Twoją płatnością miesięczną za zakupy, co oznacza, że nie musisz wybierać zapłaty jednorazowej lub zadłużać się ratami i kredytami. Nie ponosisz żadnych dodatkowych kosztów takich jak prowizja, czy odsetki i nie martwisz się oprocentowaniem. Działam 24h/7 w sposób cyfrowy, nie zadaję głupich pytań i nie marnuje Twego czasu. Nie działam jak banki i bezduszne korporacje – jestem FinTech.

Podchodzę z szacunkiem do Twoich danych- nie wysyłam niechcianych mailingów oraz reklam. Nie będę wciskać Ci żadnych dodatkowych usług takich jak ubezpieczenia, karty kredytowe itp., bo jestem FairPlay.

Oczekujesz bezpieczeństwa i doświadczenia? Właścicielem marki TubaPay jest Bacca Sp. z o.o. realizująca swoje usługi od ponad 11 lat. Jestem wpisana do rejestru instytucji pożyczkowych (RIP000190) oraz posiadam licencję Małej Instytucji Płatniczej (MIP34/2019) polskiego regulatora Komisji Nadzoru Finansowego. Świadczę najwyższej jakości usługi, w oparciu o wszelkie regulacje KNF oraz z pełnym poszanowaniem praw Konsumenta- Twoich praw.

Wpadnij na kawkę: https://tubapay.pl/';

        private $tubapay2_default_labels = array(
            "TP_CHECKOUT_BUTTON" => "Płać miesięcznie w abonamencie TubaPay",
            "TP_DISCLIMER_TEXT" => "Podziel płatność na miesięczny abonament, bez dodatkowych kosztów i odsetek.",
            "TP_CHOOSE_RATES_TITLE" => "Wybór płatności",
            "TP_FAST_TRACK_BUTTON" => "Kup w abonamencie Tubapay za \${monthlyRateValue} miesięcznie",
        );

        private $tubapay2_clientId;
        private $tubapay2_clientSecret;
        public $tubapay2_APItype;
        public $instructions;
        private $order_status;
        private $direct_checkout;
        private $partial_orders;


        /**
         * Constructor for the gateway.
         */
        public function __construct()
        {
            $this->id = 'tubapay2';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = __('TubaPay', 'tubapay-v2');
            $this->method_description = __('Zezwalaj na płatności przy użyciu TubaPay.', 'tubapay-v2');

            $this->tubapay2_clientId = $this->get_option('tubapay2_clientId');
            $this->tubapay2_clientSecret = $this->get_option('tubapay2_clientSecret');
            $this->tubapay2_APItype = $this->get_option('tubapay2_APItype');

            $this->api = new TubaPay2_REST_API($this->tubapay2_clientId, $this->tubapay2_clientSecret, $this->tubapay2_APItype);

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            $this->init_labels();

            // Define user set variables
            $this->title = $this->get_label('TP_CHECKOUT_BUTTON');
            $this->description = $this->get_label('TP_DISCLIMER_TEXT');
            $this->instructions = $this->tubapay2_instructions;
            $this->order_status = $this->get_option('order_status', 'completed');
            $this->direct_checkout = false;
            $this->partial_orders = false;

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

            // Customer Emails
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }

        private function init_labels()
        {
            $labels = $this->get_option('tubapay2_labels');
            if (empty($labels) && $this->update_labels() === false) {
                $labels = $this->tubapay2_default_labels;
                $labels = wp_json_encode($labels);
                $this->update_option('tubapay2_labels', $labels);
            }
        }

        public function get_label($label)
        {
            $labels = $this->get_option('tubapay2_labels');
            $labels = json_decode($labels, true);
            return $labels[$label];
        }

        public function update_labels()
        {
            $api_labels = $this->api->getLabels();
            if (!empty($api_labels)) {
                $api_labels = wp_json_encode($api_labels);
                $this->update_option('tubapay2_labels', $api_labels);

                return true;
            } else {
                return false;
            }
        }

        public function process_admin_options()
        {
            parent::process_admin_options();
            $this->api->doAuth();
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields()
        {
            $statuses = wc_get_order_statuses();

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Włącz/Wyłącz', 'tubapay-v2'),
                    'type' => 'checkbox',
                    'label' => __('Włącz płatności TubaPay', 'tubapay-v2'),
                    'default' => 'yes'
                ),
                'api-title' => array(
                    'title' => __('Połączenie z API', 'tubapay-v2'),
                    'type' => 'title'
                ),
                'tubapay2_APItype' => array(
                    'title' => __('Wersja API TubaPay', 'tubapay-v2'),
                    'type' => 'select',
                    'description' => __('Wybierz odpowiedni typ. Pamiętaj, że wersja produkcyjna i testowa używają innych danych klienta.', 'tubapay-v2'),
                    'options' => array('prod' => 'Produkcyjna', 'test' => 'Testowa',),
                    'default' => 'prod',
                    'desc_tip' => true,
                ),
                'tubapay2_clientId' => array(
                    'title' => __('clientId', 'tubapay-v2'),
                    'type' => 'text',
                    'description' => __('clientId', 'tubapay-v2'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'tubapay2_clientSecret' => array(
                    'title' => __('clientSecret', 'tubapay-v2'),
                    'type' => 'text',
                    'description' => __('clientSecret', 'tubapay-v2'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'connection_status' => array(
                    'title' => __('Status połączenia', 'tubapay-v2'),
                    'type' => 'text',
                    'description' => __('Zapisz zmiany przed sprawdzeniem', 'tubapay-v2'),
                    'default' => "test",
                    'disabled' => true
                ),
                'status-title' => array(
                    'title' => __('Statusy zamówień', 'tubapay-v2'),
                    'type' => 'title'
                ),
                'status-tubapay2-pending' => array(
                    'title' => __('Własny status zamówienia dla<br>oczekiwania na podpis Klienta', 'tubapay-v2'),
                    'type' => 'select',
                    'description' => __('Wybierz odpowiedni status zamówienia. Jeśli nie wykorzystujesz własnych statusów to pozostaw bez zmian', 'tubapay-v2'),
                    'options' => $statuses,
                    'default' => 'wc-tubapay2-pending',
                    'desc_tip' => true,
                ),
                'status-tubapay2-fail' => array(
                    'title' => __('Własny status zamówienia dla<br>umowy odrzuconej przez TubaPay', 'tubapay-v2'),
                    'type' => 'select',
                    'description' => __('Wybierz odpowiedni status zamówienia. Jeśli nie wykorzystujesz własnych statusów to pozostaw bez zmian', 'tubapay-v2'),
                    'options' => $statuses,
                    'default' => 'wc-tubapay2-fail',
                    'desc_tip' => true,
                ),
                'status-tubapay2-accepted' => array(
                    'title' => __('Własny status zamówienia dla<br>umowy zaakceptowanej przez TubaPay', 'tubapay-v2'),
                    'type' => 'select',
                    'description' => __('Wybierz odpowiedni status zamówienia. Jeśli nie wykorzystujesz własnych statusów to pozostaw bez zmian', 'tubapay-v2'),
                    'options' => $statuses,
                    'default' => 'wc-tubapay2-accepted',
                    'desc_tip' => true,
                ),
                'status-tubapay2-terminated' => array(
                    'title' => __('Własny status zamówienia dla<br>umowy wypowiedzianej przez TubaPay', 'tubapay-v2'),
                    'type' => 'select',
                    'description' => __('Wybierz odpowiedni status zamówienia. Jeśli nie wykorzystujesz własnych statusów to pozostaw bez zmian', 'tubapay-v2'),
                    'options' => $statuses,
                    'default' => 'wc-tubapay2-terminated',
                    'desc_tip' => true,
                ),
                'partial-title' => array(
                    'title' => __('Podzamówienia miesięczne', 'tubapay-v2'),
                    'type' => 'title'
                ),
                'partial_orders' => array(
                    'title' => __('Włącz generowanie podzamówień miesięcznych TubaPay', 'tubapay-v2'),
                    'type' => 'checkbox',
                    'label' => __('Włącz zamówienia cząstkowe TubaPay', 'tubapay-v2'),
                    'default' => 'no'
                ),
                'status-tubapay2-partial-paid' => array(
                    'title' => __('Wybór statusu na jaki ma być zmienione pojedyncze<br>podzamówienie miesięczne po zaksięgowaniu płatności ', 'tubapay-v2'),
                    'type' => 'select',
                    'description' => __('Wybierz odpowiedni status zamówienia. Jeśli nie wykorzystujesz własnych statusów to pozostaw bez zmian', 'tubapay-v2'),
                    'options' => $statuses,
                    'default' => 'wc-completed',
                    'desc_tip' => true,
                ),
//
                'other-title' => array(
                    'title' => __('Pozostałe ustawienia', 'tubapay-v2'),
                    'type' => 'title'
                ),
                'direct_checkout' => array(
                    'title' => __('Włącz/Wyłącz', 'tubapay-v2'),
                    'type' => 'checkbox',
                    'label' => __('Włącz Szybkie zamówienie TubaPay', 'tubapay-v2'),
                    'default' => 'yes'
                ),
            );
        }

        public function getAPIConnectionStatus()
        {
            $response = $this->api->doAuth();

            echo "Status połączenia: " . esc_html($response['status']);

            if (isset($response['response'])) {
                echo "<pre>";
                var_dump($response['response']);
                echo "</pre>";
            }
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page()
        {
            if ($this->instructions)
                echo esc_html(wpautop(wptexturize($this->instructions)));
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {
            if ($this->instructions && !$sent_to_admin && 'custom' === $order->payment_method && $order->has_status('on-hold')) {
                echo esc_html(wpautop(wptexturize($this->instructions))) . PHP_EOL;
            }
        }

        public function getTubaPayForm($order_id)
        {
            $order = wc_get_order($order_id);

            $data = array(
                'imie' => '',
                'nazwisko' => '',
                'email' => '',
                'telefon' => '',
                'ulica' => '',
                'kodpocztowy' => '',
                'miejscowosc' => '',
                'agreementSubject' => '',
                'totalValue' => '',
                'externalRef' => '',
                'raty' => ''
            );

            $data['imie'] = $order->get_billing_first_name();
            $data['nazwisko'] = $order->get_billing_last_name();
            $data['email'] = $order->get_billing_email();
            $data['telefon'] = substr($order->get_billing_phone(), -9, 9);
            $data['ulica'] = $order->get_billing_address_1();
            $data['kodpocztowy'] = $order->get_billing_postcode();
            $data['miejscowosc'] = $order->get_billing_city();

            $data['raty'] = $order->get_meta('tubapay_installments');

            $data['agreementSubject'] = 'Zamowienie nr ' . $order_id;
            $data['externalRef'] = $order_id;

            $amount = $order->get_total();
            $amount = floatval($amount);
//        $amount = $amount*100; //na grosze
            $data['totalValue'] = $amount;

            if (empty($data['raty'])) {
                $data['raty'] = $this->getRaty($amount);
            }
            $data['RODO_BP'] = $order->get_meta('tubapay2_RODO_BP');

            $order->update_status(tubapay2_get_status('wc-tubapay2-pending'), __('Oczekiwanie na dokonanie płatności TubaPay', 'tubapay-v2'));
            $this->process_tubapay2_order($order_id, $data);
        }

        public function getLabelForCalc($installmentsNumber)
        {
            if ($installmentsNumber == 1) {
                $label = "1 płatność";
            } else {
                $label = $installmentsNumber . " płatności";
            }
            return $label;
        }

        private function getRaty($amount)
        {
            $installments = $this->api->getCalculations($amount);

            $raty = 1;
            if (isset($installments['error'])) {

            } else {
                $first_option = $installments[0];
                if (intval($first_option)) {
                    $raty = $first_option;
                }
            }

            return $raty;
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            // Ustawienie statusu
            $order->update_status(tubapay2_get_status('wc-tubapay2-pending'), __('Oczekiwanie na dokonanie płatności TubaPay', 'tubapay-v2'));

            // or call the Payment complete
//             $order->payment_complete();

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => tubapay2_get_checkout_payment_url($order)
            ];

        }

        public function process_tubapay2_order($order_id, $data)
        {
            $order = wc_get_order($order_id);
            $this->save_post_meta($order_id, $data);
            $data['return_url'] = $this->get_return_url(wc_get_order());

            $result = $this->api->createCustomerAgreement($data);

            if (isset($result->result->response->transaction->transactionLink)) {
                header('Location: ' . $result->result->response->transaction->transactionLink);
                die();
            } else {
                $order->update_status(tubapay2_get_status('wc-tubapay2-error'), __('Błąd komunikacji z TubaPay', 'tubapay-v2'));
                update_post_meta($order_id, 'tubapay2Response', "error");
                echo "<pre>";
                var_dump($result);
                echo "</pre>";
                die();
            }
        }

        private function save_post_meta($order_id, $data)
        {
            $raty = $data['raty'];
            $RODO_BP = $data['RODO_BP'];

            update_post_meta($order_id, 'raty', $raty);
            update_post_meta($order_id, 'tubapay2_RODO_BP', $RODO_BP);

            return true;
        }

        public function getCartInstallmentsOptions($amount)
        {
            $calc = $this->api->getCalculations($amount, false);

            return $calc;
        }

        public function checkIfAvailableForAmount($amount)
        {
            $check = $this->api->getCalculations($amount);

            if (isset($check['error'])) {
                return false;
            }

            return true;
        }

        public function getLowestInstallment($amount)
        {
            $calc = $this->api->getCalculations($amount);
            
            if (!is_array($calc) || empty($calc)) {
                return '';
            }

            $lowest = max($calc);
            $lowest = intval($lowest);

            return $lowest;
        }

        public function getSelectInstallmentsInputForAmount($amount)
        {
            $amount = ceil($amount);
            $amount = intval($amount);

            $result = $this->getCartInstallmentsOptions($amount);
            $installments = $result['installments'];

            if (isset($installments['error'])) {
                die('error');
            }
            
            if (!is_array($installments) || empty($installments)) {
                return '';
            }
            
            $mce_= array(2,3,4,22,23,24,32,33,34);

            $html = '<div class="tubapay-checkout-select">
                    <p style="padding:10px 0;">
                        ' . $this->get_label('TP_CHOOSE_RATES_TITLE') . '
                    </p>
                    <span class="woocommerce-input-wrapper">';
            $i = 0;
            $max = max($installments);
            foreach ($installments as $installment) {
                $i++;
                $installment_amount = $amount / $installment;
                $installment_amount = ceil($installment_amount);
                $installment_amount = wc_price($installment_amount);
                $installment_amount = wp_strip_all_tags($installment_amount);
                
                if (in_array($installment, $mce_)) {
                    $miesiace_label = 'miesiące';
                } else {
                    $miesiace_label = 'miesięcy';
                }

                $selected = '';
                if ($installment == $max) {
                    $selected = ' checked="checked"';
                }

                $installment_label = $installment . ' ' . $miesiace_label . ' – ' . $installment_amount . ' miesięcznie';

                $html .= '<div style="align-items: flex-start !important;gap: 10px !important;margin-bottom: 10px !important;display:flex !important;">
                            <input type="radio" class="input-radio " value="' . $installment . '" name="tubapay_installments" id="tubapay_installments_' . $installment . '" ' . $selected . '
                                    style="margin: 0 5px 0 0  !important;">    
                              <label style="margin-bottom: 0 !important;font-size: 15px !important;line-height: 15px !important;"
                                    for="tubapay_installments_' . $installment . '" class="radio ">' . $installment_label . '</label>
                        </div>';
            }

            $html .= '</span>
                </div>';

            $consents = $result['consents'];
            foreach ($consents as $consent) {
                ob_start();
                woocommerce_form_field($consent['type'], array(
                    'type' => 'checkbox',
                    'label' => $consent['label'],
                    'required' => $consent['required'],
                ), '');
                $html .= ob_get_clean();
            }

            return $html;
        }

        public function getQuickOrderInfobox($price, $product)
        {
            $tuba_gw = new WC_Gateway_TubaPay2();
            $installments = $tuba_gw->getLowestInstallment($price);
            
            if ($installments == 0 || empty($installments)) {
                return "";
            }

            $product_id = $product->get_id();
            $product_price = floatval($product->get_price());
            $installment = $product_price / $installments;
            $installment = ceil($installment);
            $installment = wc_price($installment);

            $checkout_url = get_permalink(wc_get_page_id('checkout'));
            $url = $checkout_url . '?add-to-cart=' . $product_id . '&quantity=1&tubapay=direct_checkout';

            $label = $this->get_label('TP_FAST_TRACK_BUTTON');
            $price_html = '<span class="woocommerce-Price-amount amount"><bdi>' . $installment . '<span class="woocommerce-Price-currencySymbol"></span></bdi></span>';
            $label = str_replace('${monthlyRateValue}', $price_html, $label);


            $textbox = '<br>
                <div class="tubapay2-calc-box" onclick="location.href = \'' . $url . '\';"
                style="cursor:pointer;background:#FC8BF6;color:#ffffff;text-decoration:none;font-size: 13px;font-weight:500;padding:8px 15px;border-radius:5px;margin: 20px 0;max-width: 355px;text-align: center;">
                    ' . $label . '
                </div>
                ';

            return $textbox;
        }
    }
}