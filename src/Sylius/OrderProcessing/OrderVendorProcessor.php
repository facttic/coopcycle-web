<?php

namespace AppBundle\Sylius\OrderProcessing;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class OrderVendorProcessor implements OrderProcessorInterface
{
    private $adjustmentFactory;
    private $translator;
    private $iriConverter;
    private $logger;

    public function __construct(
        AdjustmentFactoryInterface $adjustmentFactory,
        TranslatorInterface $translator,
        IriConverterInterface $iriConverter,
        LoggerInterface $logger)
    {
        $this->adjustmentFactory = $adjustmentFactory;
        $this->translator = $translator;
        $this->iriConverter = $iriConverter;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if (!$order->hasVendor()) {
            return;
        }

        $order->removeAdjustments(AdjustmentInterface::VENDOR_FEE_ADJUSTMENT);
        $order->removeAdjustments(AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT);

        $vendor = $order->getVendor();

        if (!$vendor->isHub()) {
            return;
        }

        $subVendors = $order->getVendors();

        if (count($subVendors) === 1) {
            return;
        }

        $hub = $vendor->getHub();

        foreach ($subVendors as $subVendor) {

            $vendorFee =
                $order->getFeeTotal() * $hub->getPercentageForRestaurant($order, $subVendor);

            $vendorFeeAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::VENDOR_FEE_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.vendor_fee'),
                $vendorFee,
                $neutral = true
            );
            $vendorFeeAdjustment->setOriginCode(
                $this->iriConverter->getIriFromItem($subVendor)
            );

            $order->addAdjustment($vendorFeeAdjustment);

            // ---

            $transferAmount =
                $hub->getItemsTotalForRestaurant($order, $subVendor) - $vendorFee;

            $transferAmountAdjustment = $this->adjustmentFactory->createWithData(
                AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.transfer_amount'),
                $transferAmount,
                $neutral = true
            );
            $transferAmountAdjustment->setOriginCode(
                $this->iriConverter->getIriFromItem($subVendor)
            );

            $order->addAdjustment($transferAmountAdjustment);
        }
    }
}