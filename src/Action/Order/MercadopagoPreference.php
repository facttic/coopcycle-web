<?php
// src/Action/Order/MercadopagoPreference.php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\OrderManager;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use MercadoPago;
use AppBundle\Entity\MercadopagoAccount;
use AppBundle\Service\MercadopagoManager;

class MercadopagoPreference
{
    private $dataManager;
    private $doctrine;
    private $mercadopagoManager;
    private $currencyContext;

    public function __construct(
        OrderManager $dataManager,
        ManagerRegistry $doctrine,
        MercadopagoManager $mercadopagoManager,
        CurrencyContextInterface $currencyContext
    )
    {
        $this->orderManager = $dataManager;
        $this->doctrine = $doctrine;
        $this->mercadopagoManager = $mercadopagoManager;
        $this->currencyContext = $currencyContext;
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

        $applicationFee = $applicationFee / 100;

        $customer = $data->getCustomer();
        $orderItems = $data->getItems();

        $this->mercadopagoManager->configure();
        $preference = new MercadoPago\Preference();

        $mpItems = [];
        foreach ($orderItems as $i) {
            $product = $i->getVariant();
            $item = new MercadoPago\Item();
            $item->id = $i->getId(); // or it could be $i->getCode()
            $item->title = $product->getName();
            // $item->description = $product->getDescriptor();
            $item->quantity = $i->getQuantity();
            $item->currency_id = $this->currencyContext->getCurrencyCode();
            $item->unit_price = $i->getUnitPrice() / 100 ;
            $mpItems[] = $item;
        }

        // Option 1 to include applicationFee to the total cost
        $item = new MercadoPago\Item();
        // FIXME: Do not harcode this
        $item->id = "Shipping and tip"; // or it could be $i->getCode()
        $item->title = "Shipping cost and tips";
        // $item->description = $product->getDescriptor();
        $item->quantity = 1;
        $item->currency_id = $this->currencyContext->getCurrencyCode();
        $item->unit_price = $applicationFee;
        $mpItems[] = $item;

        // Option 2 to include applicationFee to the total cost, but I don't know if this is correct
        // $shipment = new Mercadopago\Shipments();
        // $shipment->cost = 0.01;
        // $shipment->mode = "custom";
        // $preference->shipments = $shipment;

        $payer = new MercadoPago\Payer();
        $payer->email = $customer->getEmail();

        $preference->items = $mpItems;
        $preference->payer = $payer;
        $preference->marketplace_fee = $applicationFee;
        // FIXME: Do we need this notification url?
        $preference->notification_url = "http://urlmarketplace.com/notification_ipn";

        $preference->save();

        return $preference->id;
    }
}
