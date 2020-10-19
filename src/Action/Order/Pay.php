<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Service\OrderManager;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use MercadoPago;
use AppBundle\Service\MercadopagoManager;

class Pay
{
    private $dataManager;
    private $doctrine;
    private $mercadopagoManager;

    public function __construct(OrderManager $dataManager, ManagerRegistry $doctrine, MercadopagoManager $mercadopagoManager)
    {
        $this->orderManager = $dataManager;
        $this->doctrine = $doctrine;
        $this->mercadopagoManager = $mercadopagoManager;
    }

    public function __invoke($data, Request $request)
    {
        $body = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        $payment = $data->getLastPayment(PaymentInterface::STATE_CART);

        if (isset($body['mercadopagoPreferenceId'])) {
            // It enters here when a payment has been done within the app
            $this->mercadopagoManager->configure();
            $preference = new MercadoPago\Preference();
            $preference = $preference->find_by_id($body['mercadopagoPreferenceId']);

            $mPayment = new MercadoPago\Payment();
            $mPayment = $mPayment->find_by_id($body['mercadopagoPaymentId']);

            if ( !is_null($preference->id) && !is_null($mPayment->id) ) {
                $payerEmailMP = $mPayment->payer->email;
                $payerEmailOrder = $data->getCustomer()->getEmail();

                if ( $payerEmailMP !== $payerEmailOrder ) {
                    // This is needed because MP gives you a fixed email that does not validate
                    if (!$_SERVER['APP_DEBUG']) {
                        throw new BadRequestHttpException('Payer email and customer email don\'t match');
                    }
                }

                $payment = new Payment();
                $payment->setCharge($body['mercadopagoPaymentId']);
                
                $payment->setMercadopagoPreference([
                    'mercadopago_preference_id' => $body['mercadopagoPreferenceId'],
                    'mercadopago_payment_id'    => $body['mercadopagoPaymentId']
                ]);
                // TODO: Set $pay data with $mPayment data
                $payment->setCurrencyCode($mPayment->currency_id);
                $payment->setState(Payment::STATE_COMPLETED);
                $data->addPayment($payment);
            } else {
                throw new BadRequestHttpException('Preference or Payment not found');
            }
        } else {
            $checkoutData = [];
            // FIXME: I'm preserving 'stripeToken' key because I don't know how this change will impact in other features.
            $checkoutData = isset($body['stripeToken']) ?
                            [
                                'stripeToken' => $body['stripeToken'],
                                'type'  => 'stripe'
                            ] :
                            (isset($body['mercadopagoToken']) ?
                                [
                                    'stripeToken' => $body['mercadopagoToken'],
                                    'type'  => 'mercadopago'
                                ] : [ 'stripeToken'  => null ] );
            if (null == $checkoutData['stripeToken']) {
                throw new BadRequestHttpException('Payment token is missing');
            }
            // If it doesn't comes from the app proceed with the usual flow
            // I don't think this is the way to do it, but it works :person_shrugging:
            $this->orderManager->checkout($data, $checkoutData);
        }

        $this->doctrine->getManagerForClass(Order::class)->flush();

        if (PaymentInterface::STATE_FAILED === $payment->getState()) {
            throw new BadRequestHttpException($payment->getLastError());
        }

        return $data;
    }
}
