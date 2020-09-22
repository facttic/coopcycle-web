<?php
// src/Action/Order/MercadopagoPreference.php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MercadopagoPreference
{
    private $dataManager;
    private $doctrine;

    public function __construct(OrderManager $dataManager, ManagerRegistry $doctrine)
    {
        $this->orderManager = $dataManager;
        $this->doctrine = $doctrine;
    }

    public function __invoke($data, Request $request)
    {
        $applicationFee = 0;
        $restaurant = $data->getRestaurant();
        if (null !== $restaurant) {
            $account = $data->getRestaurant()->getMercadopagoAccount(false);
            if ($account) {
                $applicationFee = $data->getFeeTotal();
                // @see MercadoPago\Manager::processOptions()
                $options['custom_access_token'] = $account->getAccessToken();
            }
        }

        return $applicationFee;
    }
}