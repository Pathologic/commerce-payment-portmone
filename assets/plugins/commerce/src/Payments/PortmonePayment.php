<?php

namespace Commerce\Payments;

class PortmonePayment extends Payment
{
    public function __construct(\DocumentParser $modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->lang = $modx->commerce->getUserLanguage('portmone');
    }

    public function getMarkup()
    {
        if (empty($this->getSetting('payee_id')) || empty($this->getSetting('login')) || empty($this->getSetting('password'))) {
            return '<span class="error" style="color: red;">' . $this->lang['portmone.error.empty_client_credentials'] . '</span>';
        }

        return '';
    }

    public function getPaymentMarkup()
    {
        $processor = $this->modx->commerce->loadProcessor();
        $order = $processor->getOrder();
        $payment = $this->createPayment($order['id'], $order['amount']);
        $data = [
            'payee_id' => $this->getSetting('payee_id'),
            'shop_order_number' => $order['id'] . '-' . $payment['id'] . '-' . $order['hash'],
            'bill_amount' => $payment['amount'],
            'bill_currency' => $order['currency'],
            'description' => ci()->tpl->parseChunk($this->lang['payments.payment_description'], [
                'order_id'  => $order['id'],
                'site_name' => $this->modx->getConfig('site_name'),
            ]),
            'success_url'      => MODX_SITE_URL . 'commerce/portmone/payment-process?' . http_build_query(['paymentHash' => $payment['hash']]),
            'failure_url'       => MODX_SITE_URL . 'commerce/portmone/payment-failed?' . http_build_query(['paymentHash' => $payment['hash']]),
        ];

        $view = new \Commerce\Module\Renderer($this->modx, null, [
            'path' => 'assets/plugins/commerce/templates/front/',
        ]);

        return $view->render('payment_form.tpl', [
            'url'    => 'https://www.portmone.com.ua/gateway/',
            'method' => 'post',
            'data'   => $data,
        ]);

        return false;
    }

    public function handleCallback()
    {

        if (!isset($_GET['paymentHash']) || !is_string($_GET['paymentHash']) || !preg_match('/^[a-z0-9]+$/',
                $_GET['paymentHash']) || !isset($_POST['SHOPORDERNUMBER']) || !is_scalar($_POST['SHOPORDERNUMBER'])) {
            return false;
        }
        $order = $_POST['SHOPORDERNUMBER'];
        if ($this->getSetting('debug')) {
            $this->modx->logEvent(0, 1, htmlentities(print_r($_POST, true)),
                'Commerce Portmone Payment Callback Start');
        }
        if (empty($order)) {
            $processor = $this->modx->commerce->loadProcessor();

            try {
                $payment = $processor->loadPaymentByHash($_GET['paymentHash']);

                if (!$payment) {
                    throw new Exception('Payment "' . htmlentities(print_r($_GET['paymentHash'],
                            true)) . '" . not found!');
                }

                if ($payment['paid'] == '1') {
                    $this->modx->sendRedirect(MODX_BASE_URL . 'commerce/portmone/payment-success?paymentHash=' . $_GET['paymentHash']);
                } else {
                    $this->modx->sendRedirect(MODX_BASE_URL . 'commerce/portmone/payment-failed?paymentHash=' . $_GET['paymentHash']);
                }
            } catch (Exception $e) {
                $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(),
                    'Commerce Monobank Payment');

                return false;
            }
        }
        $response = $this->request([
            'method' => 'result',
            'payee_id' => $this->getSetting('payee_id'),
            'login' => $this->getSetting('login'),
            'password' => $this->getSetting('password'),
            'shop_order_number' => $order
        ]);
        $xml = simplexml_load_string($response);
        if(empty($xml)) return false;
        $xml = json_decode(json_encode($xml), true) ?? [];
        if (isset($xml['orders']['order']) && $xml['orders']['order']['shop_order_number'] === $order && $xml['orders']['order']['status'] === 'PAYED') {
            $amount = $xml['orders']['order']['bill_amount'];
            $processor = $this->modx->commerce->loadProcessor();
            try {
                $payment = $processor->loadPaymentByHash($_GET['paymentHash']);

                if (!$payment) {
                    throw new Exception('Payment "' . htmlentities(print_r($_GET['paymentHash'],
                            true)) . '" . not found!');
                }

                $processor->processPayment($payment, $amount);
            } catch (Exception $e) {
                $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(),
                    'Commerce Portmone Payment');

                return false;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param  array  $data
     */
    protected function request(array $data = [])
    {
        $url = 'https://www.portmone.com.ua/gateway/';
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
        ];
        $options[CURLOPT_POSTFIELDS] = $data;


        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if ($this->getSetting('debug')) {
            $this->modx->logEvent(0, 1, "URL: <pre>$url</pre>"
                . "\n\nRequest data: <pre>" . htmlentities(print_r($data, true))
                . "</pre>\n\nResponse data: <pre>" . htmlentities(print_r($response, true))
                . "</pre>" . (curl_errno($ch) ? "\n\nError: <pre>" . htmlentities(curl_error($ch)) . "</pre>" : ''),
                'Commerce Portmone Payment Debug: request');
        }

        curl_close($ch);

        return $response;
    }
}
