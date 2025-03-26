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
use App\Services\Invoice\InvoiceCalculator;

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
    
        // Utiliser InvoiceCalculator pour obtenir le montant total de la facture
        $invoiceCalculator = new \App\Services\Invoice\InvoiceCalculator($invoice);
        $totalAmount = $invoiceCalculator->getTotalPrice()->getAmount();
    
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

    /**
     * API: Récupérer le montant total des paiements
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTotalPayments()
    {
        $total = Payment::sum('amount');
        return response()->json(['total' => $total]);
    }

   /**
     * API: Récupérer tous les paiements avec informations sur les factures
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllPayments()
    {
        $payments = Payment::with('invoice')->get()->map(function ($payment) {
            return [
                'external_id' => $payment->external_id,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : null,
                'payment_source' => $payment->payment_source,
                'description' => $payment->description,
                'invoice_number' => $payment->invoice ? $payment->invoice->invoice_number : null
            ];
        });
        
        return response()->json(['payments' => $payments]);
    }

    /**
     * API: Mettre à jour un paiement
     * 
     * @param Request $request
     * @param string $external_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePaymentApi(Request $request, $external_id)
    {
        $payment = Payment::where('external_id', $external_id)->firstOrFail();
        $invoice = $payment->invoice;
        
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found for this payment'], 404);
        }
        
        // Calculer le montant total de la facture à partir des lignes
        $totalAmount = $invoice->invoiceLines()->sum(\Illuminate\Support\Facades\DB::raw('price * quantity'));
        
        // Calculer le montant déjà payé (en excluant le paiement actuel)
        $totalPaid = $invoice->payments()
                        ->where('id', '!=', $payment->id)
                        ->sum('amount');
        
        // Calculer le montant restant à payer
        $remainingAmount = $totalAmount - $totalPaid;
        
        // Montant demandé pour la mise à jour
        $requestedAmount = $request->amount * 100; // Convertir en centimes
        
        // Logs pour déboguer
        \Log::debug("Montant demandé pour mise à jour (en centimes): " . $requestedAmount);
        \Log::debug("Montant total de la facture (en centimes): " . $totalAmount);
        \Log::debug("Total déjà payé sans ce paiement (en centimes): " . $totalPaid);
        \Log::debug("Montant restant à payer (en centimes): " . $remainingAmount);
        
        // Vérifier si le montant du paiement dépasse le montant restant
        if ($requestedAmount > $remainingAmount) {
            return response()->json([
                'success' => false, 
                'message' => 'Error: The payment amount exceeds the remaining balance.',
                'requested_amount' => $requestedAmount,
                'remaining_amount' => $remainingAmount
            ], 422);
        }
        
        // Si le montant est valide, procéder à la mise à jour
        $payment->amount = $requestedAmount;
        $payment->payment_date = Carbon::parse($request->payment_date);
        $payment->payment_source = $request->source;
        $payment->description = $request->description;
        $payment->save();
        
        // Mettre à jour le statut de la facture
        app(GenerateInvoiceStatus::class, ['invoice' => $invoice])->createStatus();
        
        return response()->json(['success' => true, 'message' => 'Payment updated successfully']);
    }


    /**
     * API: Supprimer un paiement
     * 
     * @param string $external_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePaymentApi($external_id)
    {
        $payment = Payment::where('external_id', $external_id)->firstOrFail();
        $invoice = $payment->invoice;
        
        // Supprimer le paiement
        $payment->delete();
        
        // Mettre à jour le statut de la facture
        if ($invoice) {
            app(GenerateInvoiceStatus::class, ['invoice' => $invoice])->createStatus();
        }
        
        return response()->json(['success' => true, 'message' => 'Payment deleted successfully']);
    } 

    /**
     * API: Récupérer l'évolution des paiements par mois
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentsByMonth()
    {
        $paymentsByMonth = Payment::selectRaw('DATE_FORMAT(payment_date, "%Y-%m") as month')
            ->selectRaw('SUM(amount) as total')
            ->whereRaw('payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'total' => $item->total / 100 // Convertir en euros
                ];
            });
        
        return response()->json(['data' => $paymentsByMonth]);
    }

}