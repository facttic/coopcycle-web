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

        $payment = $data->getLastPayment(PaymentInterface::STATE_CART);

        if (isset($body['mercadopagoPreferenceId'])) {
            $this->mercadopagoManager->configure();
            $preference = new MercadoPago\Preference();
            $preference = $preference->find_by_id($body['mercadopagoPreferenceId']);

            if ( !is_null($preference->id) ) {
                $checkoutData['mercadopagoPreferenceId'] = $body['mercadopagoPreferenceId'];

                $pay = new Payment();
                $pay->setOrder($data);
                $pay->setMercadopagoPreference([
                    'mercadopago_preference_id' => $body['mercadopagoPreferenceId'],
                    'mercadopago_payment_id'    => $body['mercadopago_payment_id']
                ]);

            } else {
                throw new BadRequestHttpException('Preference not found');
            }
        }

        // FIXME: This checkout function does something with StripePayment, I should do 'the same' with MP functions, how do I add it to the state machine workflow?
        $this->orderManager->checkout($data, $checkoutData);
        $this->doctrine->getManagerForClass(Order::class)->flush();

        if (PaymentInterface::STATE_FAILED === $payment->getState()) {
            throw new BadRequestHttpException($payment->getLastError());
        }

        return $data;
    }
}
