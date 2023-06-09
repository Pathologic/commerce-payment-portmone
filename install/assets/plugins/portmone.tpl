//<?php
/**
 * Payment Portmone
 *
 * Portmone payments processing
 *
 * @category    plugin
 * @version     1.0.0
 * @author      Pathologic
 * @internal    @events OnRegisterPayments,OnBeforeOrderSending,OnManagerBeforeOrderRender
 * @internal    @properties &title=Title;text; &payee_id=Payee ID;text; &login=Login;text; &password=Password;text; &debug=Debug;list;No==0||Yes==1;1
 * @internal    @modx_category Commerce
 * @internal    @installset base
*/

if (empty($modx->commerce) && !defined('COMMERCE_INITIALIZED')) {
    return;
}

$isSelectedPayment = !empty($order['fields']['payment_method']) && $order['fields']['payment_method'] == 'portmone';
$commerce = ci()->commerce;
$lang = $commerce->getUserLanguage('portmone');

switch ($modx->event->name) {
    case 'OnRegisterPayments': {
        $class = new \Commerce\Payments\PortmonePayment($modx, $params);

        if (empty($params['title'])) {
            $params['title'] = $lang['portmone.caption'];
        }

        $commerce->registerPayment('portmone', $params['title'], $class);
        break;
    }

    case 'OnBeforeOrderSending': {
        if ($isSelectedPayment) {
            $FL->setPlaceholder('extra', $FL->getPlaceholder('extra', '') . $commerce->loadProcessor()->populateOrderPaymentLink());
        }

        break;
    }

    case 'OnManagerBeforeOrderRender': {
        if (isset($params['groups']['payment_delivery']) && $isSelectedPayment) {
            $params['groups']['payment_delivery']['fields']['payment_link'] = [
                'title'   => $lang['portmone.link_caption'],
                'content' => function($data) use ($commerce) {
                    return $commerce->loadProcessor()->populateOrderPaymentLink('@CODE:<a href="[+link+]" target="_blank">[+link+]</a>');
                },
                'sort' => 50,
            ];
        }

        break;
    }
}
