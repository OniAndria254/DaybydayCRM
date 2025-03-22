<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\PaymentRequest;
use App\Models\Integration;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Invoice\GenerateInvoiceStatus;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use \Illuminate\Support\Facades\Log;

class PaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Payment $payment
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(Payment $payment)
    {
        if (!auth()->user()->can('payment-delete')) {
            session()->flash('flash_message', __("You don't have permission to delete a payment"));
            return redirect()->back();
        }
        $api = Integration::initBillingIntegration();
        if ($api) {
            $api->deletePayment($payment);
        }

        $payment->delete();
        session()->flash('flash_message', __('Payment successfully deleted'));
        return redirect()->back();
    }

    
    public function addPayment(PaymentRequest $request, Invoice $invoice)
    {
        if (!$invoice->isSent()) {
            session()->flash('flash_message_warning', __("Can't add payment on Invoice"));
            return redirect()->route('invoices.show', $invoice->external_id);
        }

        // Calculer le montant total de la facture à partir des lignes
        // $totalAmount = $invoice->invoiceLines()->sum('price') * $invoice->invoiceLines()->sum('quantity');

        $totalAmount = $invoice->invoiceLines()->sum(\Illuminate\Support\Facades\DB::raw('price * quantity'));

        // Calculer le montant déjà payé
        $totalPaid = $invoice->payments()->sum('amount');

        // Calculer le montant restant à payer
        $remainingAmount = $totalAmount - $totalPaid;

        // Logs pour déboguer
        \Log::debug("Montant demandé (en centimes): " . ($request->amount * 100));
        \Log::debug("Montant total de la facture (en centimes): " . $totalAmount);
        \Log::debug("Total déjà payé (en centimes): " . $totalPaid);
        \Log::debug("Montant restant à payer (en centimes): " . $remainingAmount);

        // Vérifier si la facture est déjà entièrement payée
        if ($remainingAmount <= 0) {
            session()->flash('flash_message_warning', __('Error: This invoice is already fully paid.'));
    
            // Store the payment data in session to use it if confirmed
            session()->flash('payment_data', [
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'source' => $request->source,
                'description' => $request->description,
                'invoice_id' => $invoice->id,
                'exceeds_amount' => true
            ]);
    
            return redirect()->back()->with('show_confirmation_popup', true);
        }

        // Vérifier si le montant du paiement dépasse le montant restant
        if ($request->amount * 100 > $remainingAmount) {
            // Flash a warning message
            session()->flash('flash_message_warning', __('Error: The payment amount exceeds the remaining balance.'));
    
            // Store the payment data in session to use it if confirmed
            session()->flash('payment_data', [
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'source' => $request->source,
                'description' => $request->description,
                'invoice_id' => $invoice->id,
                'exceeds_amount' => true
            ]);
    
            // Redirect back with a query parameter to trigger the popup
            return redirect()->back()->with('show_confirmation_popup', true);
        }

        // Si le montant est valide ou si la confirmation est reçue, créer le paiement
        $payment = Payment::create([
            'external_id' => Uuid::uuid4()->toString(),
            'amount' => $request->amount * 100,
            'payment_date' => Carbon::parse($request->payment_date),
            'payment_source' => $request->source,
            'description' => $request->description,
            'invoice_id' => $invoice->id
        ]);

        $api = Integration::initBillingIntegration();
        if ($api && $invoice->integration_invoice_id) {
            $result = $api->createPayment($payment);
            $payment->integration_payment_id = $result["Guid"];
            $payment->integration_type = get_class($api);
            $payment->save();
        }

        app(GenerateInvoiceStatus::class, ['invoice' => $invoice])->createStatus();

        session()->flash('flash_message', __('Payment successfully added'));
        return redirect()->back();
    }

    // // Add a new method to handle the confirmation
    // public function confirmExcessPayment(Request $request, Invoice $invoice)
    // {
    //     $paymentData = session('payment_data');
        
    //     if (!$paymentData) {
    //         return redirect()->route('invoices.show', $invoice->external_id);
    //     }
        
    //     $payment = Payment::create([
    //         'external_id' => Uuid::uuid4()->toString(),
    //         'amount' => $paymentData['amount'] * 100,
    //         'payment_date' => Carbon::parse($paymentData['payment_date']),
    //         'payment_source' => $paymentData['source'],
    //         'description' => $paymentData['description'],
    //         'invoice_id' => $invoice->id
    //     ]);
        
    //     $api = Integration::initBillingIntegration();
    //     if ($api && $invoice->integration_invoice_id) {
    //         $result = $api->createPayment($payment);
    //         $payment->integration_payment_id = $result["Guid"];
    //         $payment->integration_type = get_class($api);
    //         $payment->save();
    //     }
        
    //     app(GenerateInvoiceStatus::class, ['invoice' => $invoice])->createStatus();

    //     session()->flash('flash_message', __('Payment successfully added'));
    //     return redirect()->route('invoices.show', $invoice->external_id);
    // }
}