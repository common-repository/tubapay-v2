<?php

//if(!class_exists('TubaPay2_REST_API')) {
    class TubaPay2_REST_API
    {

        public $domain = 'tubapay-v2';

        private $apiURL;
        private $clientId;
        private $clientSecret;

        private $apiTestURL = 'https://tubapay-test.bacca.pl';
        private $apiProdURL = 'https://tubapay.pl';

        private $sendVersion = true;

        private $client;


        /**
         * Constructor for the gateway.
         */
        public function __construct($clientId, $clientSecret, $APItype = 'test')
        {
            if ($APItype == 'prod') {
                $this->apiURL = $this->apiProdURL;
            } else {
                $this->apiURL = $this->apiTestURL;
            }

            $this->clientId = $clientId;
            $this->clientSecret = $clientSecret;

            $this->auth();
        }

        public function get_version_array()
        {
            $return = array();
            if ($this->sendVersion) {
                $tubapay2_plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/tubapay-v2/tubapay2.php');
                $version = $tubapay2_plugin_data['Version'];
                $return = array(
                    'appVersion' => 'test_appversion',
                    'appDetailedVersion' => $version,
                    'source' => 'woocommerce'
                );
            }
            return $return;
        }

        public function callAPI($path, $data = array(), $authcall = false, $noretry = false, $get = false)
        {
            $url = $this->apiURL . $path;

            $args = [
                'headers' => [
                    'user-agent' => null,
                    'Content-Type' => "application/json",
                    'X-Content-Type-Options' => "nosniff",
                    'Accept' => "application/json",
                    'Cache-Control' => "no-cache",
                ],
            ];

            if ($get) {
                $args['method'] = "GET";
            }

            if (!$authcall) {
                $token = get_option('tubapay2_token');
                $args['headers']['Authorization'] = "Bearer " . $token;
            }

            if ($data) {
                $args['body'] = $data;
            }

            $result = wp_remote_post($url, $args);
            $result = wp_remote_retrieve_body($result);

            $result = json_decode($result);

            $error_msg = false;
            if (isset($result->error)) {
                $error_msg = $result->error;
            }
            $status = '200';
            if (isset($result->status)) {
                $status = $result->status;
            }

            if ((!$authcall && $status == '401' && !$noretry) || ($error_msg == 'Unauthorized' && !$noretry)) {
                $this->doAuth();
                $result = $this->callAPI($path, $data, false, true);
            }
            
            return $result;
        }

        private function auth()
        {
            $token = get_option('tubapay2_token');
            $refresh_token = get_option('tubapay2_refresh_token');
            $expires = get_option('tubapay2_token_expires');

            $expires = substr($expires, 0, 19);

            if (empty($token) || strtotime($expires) <= time()) {
                $this->doAuth();
            }
        }

        public function doAuth()
        {
            $path = '/api/v1/partner/auth/token';

            $data = array(
                "grantType" => "PARTNER_CLIENT_CREDENTIALS",
                "clientId" => $this->clientId,
                "clientSecret" => $this->clientSecret
            );
            $data = wp_json_encode($data);

            $auth_status = array(
                'status' => ''
            );

            if (!empty($this->clientId) && !empty($this->clientSecret)) {
                $auth = $this->callAPI($path, $data, true);

                if (isset($auth->token)) {
                    $this->updateAuthData($auth->token, $auth->refreshToken, $auth->expires);
                    $auth_status['status'] = 'Autoryzacja poprawna';
                } else {
                    $this->updateAuthData("", "", "");
                    $auth_status['status'] = 'Błąd autoryzacji';
                    $auth_status['response'] = $auth;
                }
            } else {
                //empty credentials
                $this->updateAuthData("", "", "");
                $auth_status['status'] = 'Brakujące dane';
            }
            return $auth_status;
        }

        private function updateAuthData($token, $refreshToken, $expires)
        {
            if (get_option('tubapay2_token') !== false) {
                update_option('tubapay2_token', $token);
            } else {
                add_option('tubapay2_token', $token);
            }

            if (get_option('tubapay2_refresh_token') !== false) {
                update_option('tubapay2_refresh_token', $refreshToken);
            } else {
                add_option('tubapay2_refresh_token', $refreshToken);
            }

            if (get_option('tubapay2_token_expires') !== false) {
                update_option('tubapay2_token_expires', $expires);
            } else {
                add_option('tubapay2_token_expires', $expires);
            }


        }

        /**
         * @return mixed
         */
        public function getCalculations($netAmount, $simplified = true)
        {
            $result = $this->_getCalculationsCall($netAmount);

            if (isset($result->error)) {
                return array(
                    'error' => $result->error
                );
            }

            $installments = array();
            $consents = array();

            if (isset($result->result->response->offer)) {
                $offer = $result->result->response->offer;
                if (is_object($offer)) {
                    if (isset($offer->offerItems)) {
                        $calculations = $offer->offerItems;

                        foreach ($calculations as $calculation) {
                            $installments[] = $calculation->installmentsNumber;
                        }
                    }
                    if (isset($offer->consents)) {
                        $apiconsents = $offer->consents;

                        foreach ($apiconsents as $apiconsent) {
                            $required = false;
                            if ($apiconsent->optional == false) {
                                $required = true;
                            }
                            $consent = array(
                                'label' => $apiconsent->title,
                                'type' => $apiconsent->type,
                                'required' => $required,

                            );
                            $consents[] = $consent;
                        }
                    }
                }
            }
            if ($simplified) {
                return $installments;
            }

            $return['installments'] = $installments;
            $return['consents'] = $consents;

            return $return;
        }

        public function _getCalculationsCall($netAmount)
        {
            $path = '/api/v1/external/transaction/create-offer';
            $data = array(
                'totalValue' => $netAmount,
                'type' => 'client'
            );
            $data = wp_json_encode($data);

            $request = $this->callAPI($path, $data);

            return $request;
        }

        public function createCustomerAgreement($data)
        {

            $params = array(
                'customer' => array(
                    'firstName' => $data['imie'],
                    'lastName' => $data['nazwisko'],
                    'street' => $data['ulica'],
//                'streetNumber'      => $data['nrdomu'],
//                'flatNumber'        => $data['nrlokalu'],
                    'zipCode' => $data['kodpocztowy'],
                    'town' => $data['miejscowosc'],
                    'phone' => $data['telefon'],
                    'email' => $data['email'],
                ),
                'order' => array(
                    'item' => array(
                        'name' => $data['agreementSubject'],
                        'brand' => get_bloginfo('name'),
                        'description' => '',
                        'totalValue' => $data['totalValue'],
                    ),
                    'externalRef' => $data['externalRef'],
                    'callbackUrl' => get_site_url() . "/tubapay_endpoint",
                    'returnUrl' => $data['return_url'],
                    'acceptedConsents' => array()
                ),
                'offer' => array(
                    'installmentsNumber' => $data['raty']
                )
            );

            if ($data['RODO_BP'] == 1) {
                $params['order']['acceptedConsents'][] = 'RODO_BP';
            }

            $params['order'] = array_merge($params['order'], $this->get_version_array());

            $result = $this->_createCustomerAgreementCall($params);

            return $result;
        }

        private function _createCustomerAgreementCall($data)
        {
            $path = '/api/v1/external/transaction/create';

            $data = wp_json_encode($data);

            $request = $this->callAPI($path, $data);

            return $request;
        }

        public function getLabels()
        {
            $path = '/api/v1/external/transaction/query/get-texts-for-ui-elements';

            $result = $this->callAPI($path, null, false, false, true);

            if (isset($result->result->response)) {
                $return = (array)$result->result->response;
            } else {
                $return = array();
            }

            return $return;
        }
    }
//}