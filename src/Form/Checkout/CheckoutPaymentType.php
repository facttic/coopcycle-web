<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Edenred\Authentication as EdenredAuthentication;
use AppBundle\Edenred\Client as EdenredPayment;
use AppBundle\Form\StripePaymentType;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\MercadopagoManager;
use MercadoPago;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Payment\Context as PaymentContext;
use AppBundle\Utils\OrderTimeHelper;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Webmozart\Assert\Assert;

class CheckoutPaymentType extends AbstractType
{
    private $resolver;
    private $mercadopagoManager;

    public function __construct(
        GatewayResolver $resolver,
        MercadopagoManager $mercadopagoManager,
        OrderTimeHelper $orderTimeHelper,
        EdenredAuthentication $edenredAuthentication,
        EdenredPayment $edenredPayment,
        SettingsManager $settingsManager,
        bool $cashEnabled)
    {
        $this->resolver = $resolver;
        $this->mercadopagoManager = $mercadopagoManager;
        $this->edenredAuthentication = $edenredAuthentication;
        $this->edenredPayment = $edenredPayment;
        $this->settingsManager = $settingsManager;
        $this->cashEnabled = $cashEnabled;

        parent::__construct($orderTimeHelper);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('stripePayment', StripePaymentType::class, [
                'mapped' => false,
            ]);

        // @see https://www.mercadopago.com.br/developers/en/guides/payments/api/receiving-payment-by-card/
        if ('mercadopago' === $this->resolver->resolve()) {
            $builder
                ->add('paymentMethod', HiddenType::class, [
                    'mapped' => false,
                ])
                ->add('installments', HiddenType::class, [
                    'mapped' => false,
                ]);

            $this->mercadopagoManager->configure();

            // For most countries, the customer has to provide
            // @see https://www.mercadopago.com.br/developers/en/guides/localization/identification-types/
            // @see https://www.mercadopago.com.br/developers/en/reference/identification_types/_identification_types/get/
            $identificationTypesResponse = MercadoPago\SDK::get('/v1/identification_types');

            // This will return 404 for Mexico
            if ($identificationTypesResponse !== 404) {
                // TODO Implement identification types for other countries
            }
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $order = $event->getData();

            if (!$order->hasVendor()) {

                return;
            }

            $choices = [];

            if ($this->settingsManager->supportsCardPayments()) {
                $choices['Credit card'] = 'card';
            }

            if ($order->supportsGiropay()) {
                $choices['Giropay'] = 'giropay';
            }

            if ($order->supportsEdenred()) {
                if ($order->getCustomer()->hasEdenredCredentials()) {
                    $amounts = $this->edenredPayment->splitAmounts($order);
                    if ($amounts['edenred'] > 0) {
                        if ($amounts['card'] > 0) {
                            $choices['Edenred'] = PaymentContext::METHOD_EDENRED_PLUS_CARD;
                        } else {
                            $choices['Edenred'] = PaymentContext::METHOD_EDENRED;
                        }
                    }
                } else {
                    // The customer will be presented with the button
                    // to connect his/her Edenred account
                    $choices['Edenred'] = 'edenred';
                }
            }

            if ($this->cashEnabled) {
                $choices['Cash on delivery'] = 'cash_on_delivery';
            }

            $form
                ->add('method', ChoiceType::class, [
                    'label' => 'form.checkout_payment.method.label',
                    'choices' => $choices,
                    'choice_attr' => function($choice, $key, $value) use ($order) {

                        Assert::isInstanceOf($order->getCustomer(), CustomerInterface::class);

                        switch ($value) {
                            case PaymentContext::METHOD_EDENRED:
                            case PaymentContext::METHOD_EDENRED_PLUS_CARD:
                                return [
                                    'data-edenred-is-connected' => $order->getCustomer()->hasEdenredCredentials(),
                                    'data-edenred-authorize-url' => $this->edenredAuthentication->getAuthorizeUrl($order)
                                ];
                        }

                        return [];
                    },
                    'mapped' => false,
                    'expanded' => true,
                    'multiple' => false,
                    'data' => count($choices) === 1 ? 'card' : null
                ]);
        });
    }
}
