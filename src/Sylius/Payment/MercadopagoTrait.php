<?php

namespace AppBundle\Sylius\Payment;

trait MercadopagoTrait
{
    public function setMercadopagoPaymentMethod($paymentMethod)
    {
        $this->details = array_merge($this->details, [
            'mercadopago_payment_method' => $paymentMethod
        ]);
    }

    public function getMercadopagoPaymentMethod()
    {
        if (isset($this->details['mercadopago_payment_method'])) {

            return $this->details['mercadopago_payment_method'];
        }
    }

    public function setMercadopagoInstallments($installments)
    {
        $this->details = array_merge($this->details, [
            'mercadopago_installments' => $installments
        ]);
    }

    public function getMercadopagoInstallments()
    {
        if (isset($this->details['mercadopago_installments'])) {

            return $this->details['mercadopago_installments'];
        }
    }

    public function setMercadopagoPreference($preference)
    {
        $this->details = array_merge($this->details, [
            'mercadopago_preference_id' => $preference['mercadopago_payment_id'],
            'mercadopago_payment_id'    => $preference['mercadopago_payment_id']
        ]);
    }

    public function getMercadopagoPreference()
    {
        if (isset($this->details['mercadopago_preference_id'])) {
            return [
                'mercadopago_preference_id' => $this->details['mercadopago_preference_id'],
                'mercadopago_payment_id'    => $this->details['mercadopago_payment_id']
            ];
        }
    }
}
