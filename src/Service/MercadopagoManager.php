<?php

namespace AppBundle\Service;

use Psr\Log\LoggerInterface;
use MercadoPago;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see https://www.mercadopago.com.mx/developers/es/guides/payments/api/other-features
 */
class MercadopagoManager
{
    private $settingsManager;
    private $urlGenerator;
    private $secret;
    private $logger;

    public function __construct(
        SettingsManager $settingsManager,
        UrlGeneratorInterface $urlGenerator,
        string $secret,
        LoggerInterface $logger)
    {
        $this->settingsManager = $settingsManager;
        $this->urlGenerator = $urlGenerator;
        $this->secret = $secret;
        $this->logger = $logger;
    }

    public function configure()
    {
        MercadoPago\SDK::setAccessToken($this->settingsManager->get('mercadopago_access_token'));
    }

    /**
     * @return MercadoPago\Payment
     */
    public function authorize(PaymentInterface $payment)
    {
        $this->configure();

        $order = $payment->getOrder();

        $options = [];

        $applicationFee = 0;
        if (null !== $order->getRestaurant()) {
            $account = $order->getRestaurant()->getMercadopagoAccount(false);
            if ($account) {
                $applicationFee = $order->getFeeTotal();
                // @see MercadoPago\Manager::processOptions()
                $options['custom_access_token'] = $account->getAccessToken();
            }
        }

        $order = $payment->getOrder();

        $p = new MercadoPago\Payment();

        $p->transaction_amount = ($payment->getAmount() / 100);
        $p->token = $payment->getStripeToken(); // FIXME change this to MercadoPagoToken/MercadoPagoPaymentId.
        $p->description = sprintf('Order %s', $order->getNumber());
        $p->installments = $payment->getMercadopagoInstallments() ?? 1;
        $p->payment_method_id = $payment->getMercadopagoPaymentMethod();
        $p->payer = array(
            'email' => $order->getCustomer()->getEmail()
            // On development we should use the buyer testing e-mail
            // Documentation: https://www.mercadopago.com.mx/developers/en/guides/online-payments/marketplace/checkout-pro/testing-marketplace/
        );
        $p->capture = false;

        if ($applicationFee > 0) {
            $p->application_fee = ($applicationFee / 100);
        }

        if (!$p->save($options)) {
            throw new \Exception((string) $p->error);
        }

        return $p;
    }

    /**
     * @return MercadoPago\Payment
     */
    public function capture(PaymentInterface $payment)
    {
        $this->configure();

        // FIXME: should be refactored

        $order = $payment->getOrder();

        $options = [];

        if (null !== $order->getRestaurant()) {
            $account = $order->getRestaurant()->getMercadopagoAccount(false);
            if ($account) {
                // @see MercadoPago\Manager::processOptions()
                $options['custom_access_token'] = $account->getAccessToken();
            }
        }

        $payment = MercadoPago\Payment::read(["id" => $payment->getCharge()], ["custom_access_token" => $options['custom_access_token']]);
        $payment->capture = true;

        if (!$payment->update()) {
            throw new \Exception((string) $payment->error);
        }

        return $payment;
    }

    /**
     * @return MercadoPago\Refund
     */
    public function refund(PaymentInterface $payment, $amount = null)
    {
        // FIXME
        // Check if the charge was made in test or live mode
        // To achieve this, we need to store a "livemode" key in payment details
        $this->configure();

        $order = $payment->getOrder();
        $restaurant = $order->getRestaurant();
        $account = $restaurant->getMercadopagoAccount(false);

        $options = [];
        $options['custom_access_token'] = $account->getAccessToken();

        // MercadoPago Payment
        // $mPayment = MercadoPago\Payment::read(["id" => $payment->getCharge()], ["custom_access_token" => $options['custom_access_token']]);
        $mPayment = MercadoPago\Payment::find_by_id($payment->getCharge());
        $refunds = null;

        if ( ($mPayment->status === "pending") || ($mPayment->status === "in_process") ) { // you can cancel the order
            $mPayment->status = "cancelled";
            $refunds = $mPayment->update();

        } elseif ($mPayment->status === "approved") { // you need to refund
            $refunds = $mPayment->refund();
        }

        // FIXME fake response

        $fakeResponse = [
            "id" => 666777,
            "payment_id" =>  $payment->getCharge(),
            "amount"=> 73.48,
            "metadata"=> [],
            "source" => [
                "id"=> "130379930",
                "name"=> "Firstname Lastname",
                "type"=> "collector"
            ],
            "date_created"=> "2014-12-11T11:26:40.537-04:00"
        ];

        $refunds = json_decode(json_encode($fakeResponse));

        return $refunds; //refund coopcycle
    }
}
