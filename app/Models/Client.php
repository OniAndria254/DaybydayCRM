<?php
namespace App\Models;

use App\Http\Controllers\ClientsController;
use App\Observers\ElasticSearchObserver;
use App\Traits\SearchableTrait;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed user_id
 * @property mixed company_name
 * @property mixed vat
 * @property mixed id
 */
class Client extends Model
{
    use  SearchableTrait, SoftDeletes;

    protected $searchableFields = ['company_name', 'vat', 'address'];

    protected $fillable = [
        'external_id',
        'name',
        'company_name',
        'vat',
        'email',
        'address',
        'zipcode',
        'city',
        'primary_number',
        'secondary_number',
        'industry_id',
        'company_type',
        'user_id',
        'client_number'];

    public static function boot()
    {
        parent::boot();
        // This makes it easy to toggle the search feature flag
        // on and off. This is going to prove useful later on
        // when deploy the new search engine to a live app.
        //if (config('services.search.enabled')) {
        static::observe(ElasticSearchObserver::class);
        //}
    }

    public function updateAssignee(User $user)
    {
        $this->user_id = $user->id;
        $this->save();

        event(new \App\Events\ClientAction($this, ClientsController::UPDATED_ASSIGN));
    }

    public function displayValue()
    {
        return $this->company_name;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'client_id', 'id')
            ->orderBy('created_at', 'desc');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'client_id', 'id')
            ->orderBy('created_at', 'desc');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'source');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function primaryContact()
    {
        return $this->hasOne(Contact::class)->whereIsPrimary(true);
    }

    public function getprimaryContactAttribute()
    {
        return $this->hasMany(Contact::class)->whereIsPrimary(true)->first();
    }

    public function getAssignedUserAttribute()
    {
        return User::findOrFail($this->user_id);
    }

    public static function whereExternalId($external_id)
    {
        return self::where('external_id', $external_id)->first();
    }

    /**
     * @return array
     */
    public function getSearchableFields(): array
    {
        return $this->searchableFields;
    }

    // Méthode pour récupérer tous les paiements d'un client
    public function payments()
    {
        return $this->hasManyThrough(
            Payment::class,
            Invoice::class,
            'client_id', // Clé étrangère sur la table invoices
            'invoice_id', // Clé étrangère sur la table payments
            'id', // Clé locale sur la table clients
            'id' // Clé locale sur la table invoices
        );
    }

    // Méthode pour calculer le total des paiements d'un client
    public function getTotalPaymentsAttribute()
    {
        return $this->payments()->sum('amount');
    }

    // Méthode pour calculer le total des factures en attente
    public function getPendingInvoicesAmountAttribute()
    {
        $total = 0;
        foreach ($this->invoices as $invoice) {
            if ($invoice->status !== 'paid') {
                // Calculer le montant total de la facture
                $invoiceTotal = $invoice->invoiceLines()->sum(\Illuminate\Support\Facades\DB::raw('price * quantity'));
                
                // Soustraire les paiements déjà effectués
                $paidAmount = $invoice->payments()->sum('amount');
                
                // Ajouter le montant restant au total
                $total += ($invoiceTotal - $paidAmount);
            }
        }
        return $total;
    }
}
