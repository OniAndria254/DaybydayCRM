<?php
namespace App\Services\Invoice;

use App\Models\Offer;
use App\Models\Invoice;
use App\Repositories\Tax\Tax;
use App\Repositories\Money\Money;

class InvoiceCalculator
{
/**
     * @var Invoice
     */

    private $invoice;
    /**
     * @var Tax
     */

    private $tax;

    public function __construct($invoice)
    {
        if(!$invoice instanceof Invoice && !$invoice instanceof Offer ) {
            throw new \Exception("Not correct type for Invoice Calculator");
        }
        $this->tax = new Tax();
        $this->invoice = $invoice;
    }

    public function getVatTotal()
    {
        $price = $this->getSubTotal()->getAmount();
        return new Money($price * $this->tax->vatRate());
    }


    public function getTotalPrice(): Money
    {
        $price = 0;
        $invoiceLines = $this->invoice->invoiceLines;

        foreach ($invoiceLines as $invoiceLine) {
            $price += $invoiceLine->quantity * $invoiceLine->price;
        }

        // Vérifier si une remise est appliquée
        if (isset($this->invoice->apply_global_discount) && $this->invoice->apply_global_discount && 
            isset($this->invoice->discount_rate) && $this->invoice->discount_rate > 0) {
                
            $totalPrice = $price;
            $discountAmount = $totalPrice * ($this->invoice->discount_rate / 100);
                
            // Soustraire la remise du prix total
            $price = $price - $discountAmount;
        }

        return new Money($price);
    }

    public function getTotalPriceInit(): Money
    {
        $price = 0;
        $invoiceLines = $this->invoice->invoiceLines;

        foreach ($invoiceLines as $invoiceLine) {
            $price += $invoiceLine->quantity * $invoiceLine->price;
        }

        return new Money($price);
    }

    public function getSubTotal(): Money
    {
        $price = 0;
        $invoiceLines = $this->invoice->invoiceLines;

        foreach ($invoiceLines as $invoiceLine) {
            $price += $invoiceLine->quantity * $invoiceLine->price;
        }
        return new Money($price / $this->tax->multipleVatRate());
    }

    public function getAmountDue()
    {
        return new Money($this->getTotalPrice()->getAmount() - $this->invoice->payments()->sum('amount'));
    }

    public function getInvoice()
    {
        return $this->invoice;
    }

    public function getTax()
    {
        return $this->tax;
    }

    public function getDiscountAmount()
    {
        if (!isset($this->invoice->apply_global_discount) || !$this->invoice->apply_global_discount || !isset($this->invoice->discount_rate) || !$this->invoice->discount_rate) {
            // Utilisez la même approche que les autres méthodes pour créer un objet Money avec valeur 0
            return app(Money::class, ['amount' => 0]);
        }
        
        $totalPrice = $this->getTotalPriceInit();
        // Au lieu d'utiliser multiply(), calculez directement le montant de la remise
        $discountAmount = new Money($totalPrice->getAmount() * ($this->invoice->discount_rate / 100));
        
        return $discountAmount;
    }

}
