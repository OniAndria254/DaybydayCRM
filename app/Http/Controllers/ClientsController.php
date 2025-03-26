<?php
namespace App\Http\Controllers;

use App\Enums\Country;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Status;
use App\Models\Task;
use App\Repositories\FilesystemIntegration\FilesystemIntegration;
use App\Repositories\Money\MoneyConverter;
use App\Services\ClientNumber\ClientNumberService;
use App\Services\Invoice\InvoiceCalculator;
use App\Services\Search\SearchService;
use App\Services\Storage\GetStorageProvider;
use Carbon\Carbon;
use Config;
use Dinero;
use Datatables;
use App\Models\Client;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\User;
use App\Models\Integration;
use App\Models\Industry;
use Ramsey\Uuid\Uuid;
use App\Models\Contact;

class ClientsController extends Controller
{
    const CREATED = 'created';
    const UPDATED_ASSIGN = 'updated_assign';

    protected $users;
    protected $clients;
    protected $settings;
    /**
     * @var FilesystemIntegration
     */
    private $filesystem;

    public function __construct()
    {
        $this->middleware('client.create', ['only' => ['create']]);
        $this->middleware('client.update', ['only' => ['edit']]);
        $this->middleware('is.demo', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('clients.index');
    }

    /**
     * API: Récupérer le nombre total de clients
     * 
     * @return JsonResponse
     */
    public function getClientCount()
    {
        $count = Client::count();
        return response()->json(['count' => $count]);
    }

    /**
     * API: Récupérer la liste paginée de tous les clients avec informations de base
     * 
     * @return JsonResponse
     */
    public function getAllClients(Request $request)
    {
        // Paginer les résultats avec 10 clients par page
        $clients = Client::select([
                'external_id', 
                'company_name', 
                'vat', 
                'address', 
                'city', 
                'zipcode'
            ])
            ->paginate(10);  // 10 clients par page
        
        // Retourner les résultats paginés
        return response()->json($clients);
    }


    /**
     * API: Récupérer les détails d'un client spécifique avec ses factures et paiements
     * 
     * @param string $external_id
     * @return JsonResponse
     */
    public function getClientDetails($external_id)
    {
        $client = $this->findByExternalId($external_id);
        
        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }
        
        // Récupérer les factures avec leurs lignes et paiements
        $invoices = $client->invoices()->with(['invoiceLines', 'payments'])->get();
        
        // Calculer le total des paiements
        $totalPayments = 0;
        foreach ($invoices as $invoice) {
            $totalPayments += $invoice->payments()->sum('amount');
        }
        
        // Calculer le total des factures en attente
        $pendingInvoices = [];
        $pendingAmount = 0;
        
        foreach ($invoices as $invoice) {
            if ($invoice->status !== 'paid') {
                $invoiceTotal = $invoice->invoiceLines()->sum(\DB::raw('price * quantity'));
                $paidAmount = $invoice->payments()->sum('amount');
                $remainingAmount = $invoiceTotal - $paidAmount;
                
                if ($remainingAmount > 0) {
                    $pendingAmount += $remainingAmount;
                    $pendingInvoices[] = [
                        'external_id' => $invoice->external_id,
                        'invoice_number' => $invoice->invoice_number,
                        'sent_at' => $invoice->sent_at,
                        'due_at' => $invoice->due_at,
                        'total_amount' => $invoiceTotal,
                        'paid_amount' => $paidAmount,
                        'remaining_amount' => $remainingAmount,
                        'status' => $invoice->status
                    ];
                }
            }
        }
        
        // Récupérer les paiements
        $payments = [];
        foreach ($invoices as $invoice) {
            foreach ($invoice->payments as $payment) {
                $payments[] = [
                    'external_id' => $payment->external_id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date,
                    'payment_source' => $payment->payment_source,
                    'description' => $payment->description,
                    'invoice_number' => $invoice->invoice_number
                ];
            }
        }
        
        return response()->json([
            'client' => [
                'external_id' => $client->external_id,
                'company_name' => $client->company_name,
                'vat' => $client->vat,
                'address' => $client->address,
                'city' => $client->city,
                'zipcode' => $client->zipcode,
                'email' => $client->email,
                'primary_number' => $client->primary_number,
                'secondary_number' => $client->secondary_number
            ],
            'total_payments' => $totalPayments,
            'pending_amount' => $pendingAmount,
            'payments' => $payments,
            'pending_invoices' => $pendingInvoices
        ]);
    }

    /**
     * API: Récupérer la répartition des clients par industrie avec montants facturés
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientsByIndustry()
    {
        $clientsByIndustry = Industry::select('industries.id', 'industries.name')
            ->leftJoin('clients', 'industries.id', '=', 'clients.industry_id')
            ->leftJoin('invoices', 'clients.id', '=', 'invoices.client_id')
            ->leftJoin('invoice_lines', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->selectRaw('COUNT(DISTINCT clients.id) as client_count')
            ->selectRaw('SUM(COALESCE(invoice_lines.price * invoice_lines.quantity, 0)) as total_amount')
            ->groupBy('industries.id', 'industries.name')
            ->orderBy('industries.name')
            ->get()
            ->map(function ($industry) {
                return [
                    'name' => $industry->name,
                    'client_count' => $industry->client_count,
                    'amount' => $industry->total_amount / 100, // Convertir en euros
                    // Générer une couleur aléatoire mais cohérente pour chaque industrie
                    'color' => 'hsl(' . (crc32($industry->name) % 360) . ', 70%, 60%)'
                ];
            });
        
        return response()->json(['data' => $clientsByIndustry]);
    }

    
    /**
     * Make json respnse for datatables
     * @return mixed
     */
    public function anyData()
    {
        $clients = Client::select(['external_id', 'company_name', 'vat', 'address']);
        return Datatables::of($clients)       
            ->addColumn('namelink', '<a href="{{ route("clients.show",[$external_id]) }}">{{$company_name}}</a>')
            ->addColumn('view', '
                <a href="{{ route(\'clients.show\', $external_id) }}" class="btn btn-link" >'  . __('View') . '</a>')
            ->addColumn('edit', '
                <a href="{{ route(\'clients.edit\', $external_id) }}" class="btn btn-link" >'  . __('Edit') . '</a>')
            ->addColumn('delete', '
                <form action="{{ route(\'clients.destroy\', $external_id) }}" method="POST">
            <input type="hidden" name="_method" value="DELETE">
            <input type="submit" name="submit" value="' . __('Delete') . '" class="btn btn-link" onClick="return confirm(\'Are you sure? All the clients tasks, leads, projects, etc will be deleted as well\')"">
            {{csrf_field()}}
            </form>')
            ->rawColumns(['namelink', 'view','edit', 'delete'])
            ->make(true);
    }



    public function taskDataTable($external_id)
    {
        $client = Client::where('external_id', $external_id)->firstOrFail();
        $tasks = $client->tasks()->with(['status'])->select(
            ['id', 'external_id', 'title', 'created_at', 'deadline', 'user_assigned_id', 'client_id', 'status_id']
        )->get();


        return Datatables::of($tasks)
            ->addColumn('titlelink', '<a href="{{ route("tasks.show",[$external_id]) }}">{{$title}}</a>')
            ->editColumn('created_at', function ($tasks) {
                return $tasks->created_at ? with(new Carbon($tasks->created_at))
                    ->format(carbonDate()) : '';
            })
            ->editColumn('deadline', function ($tasks) {
                return $tasks->deadline ? with(new Carbon($tasks->deadline))
                    ->format(carbonDate()) : '';
            })
            ->editColumn('status_id', function ($tasks) {
                return '<span class="label label-success" style="background-color:' . $tasks->status->color . '"> ' .$tasks->status->title . '</span>';
            })
            ->editColumn('assigned', function ($tasks) {
                return $tasks->assigned_user->name;
            })
            ->rawColumns(['titlelink','status_id'])
            ->make(true);
    }

    public function projectDataTable($external_id)
    {
        $client = Client::where('external_id', $external_id)->firstOrFail();
        $projects = $client->projects()->with(['status'])->select(
            ['id', 'external_id', 'title', 'created_at', 'deadline', 'user_assigned_id', 'client_id', 'status_id']
        )->get();

        return Datatables::of($projects)
            ->addColumn('titlelink', '<a href="{{ route("projects.show",[$external_id]) }}">{{$title}}</a>')
            ->editColumn('created_at', function ($projects) {
                return $projects->created_at ? with(new Carbon($projects->created_at))
                    ->format(carbonDate()) : '';
            })
            ->editColumn('deadline', function ($projects) {
                return $projects->deadline ? with(new Carbon($projects->deadline))
                    ->format(carbonDate()) : '';
            })
            ->editColumn('status_id', function ($projects) {
                return '<span class="label label-success" style="background-color:' . $projects->status->color . '"> ' .$projects->status->title . '</span>';
            })
            ->editColumn('assigned', function ($projects) {
                return $projects->assignee->name;
            })
            ->rawColumns(['titlelink','status_id'])
            ->make(true);
    }

    public function leadDataTable($external_id)
    {
        $client = Client::where('external_id', $external_id)->firstOrFail();
        $leads = $client->leads()->with(['status'])->select(
            ['id', 'external_id', 'title', 'created_at', 'deadline', 'user_assigned_id', 'client_id', 'status_id']
        )->get();
        return Datatables::of($leads)
            ->addColumn('titlelink', '<a href="{{ route("leads.show",[$external_id]) }}">{{$title}}</a>')
            ->editColumn('created_at', function ($leads) {
                return $leads->created_at ? with(new Carbon($leads->created_at))
                    ->format(carbonDate()) : '';
            })
            ->editColumn('deadline', function ($leads) {
                return $leads->deadline ? with(new Carbon($leads->deadline))
                    ->format(carbonDate()) : '';
            })
            ->editColumn('status_id', function ($leads) {
                return '<span class="label label-success" style="background-color:' . $leads->status->color . '"> ' .
                    $leads->status->title . '</span>';
            })
            ->editColumn('assigned', function ($leads) {
                return $leads->assigned_user->name;
            })
            ->rawColumns(['titlelink','status_id'])
            ->make(true);
    }

    public function invoiceDataTable($external_id)
    {
        $client = Client::where('external_id', $external_id)->firstOrFail();

        $invoices = $client->invoices()->select(
            ['id', 'external_id', 'sent_at', 'status', 'invoice_number']
        );

        return Datatables::of($invoices)
            ->editColumn('invoice_number', function ($invoices) {
                return '<a href="' . url('invoices', $invoices->external_id) . '">' . ($invoices->invoice_number ?: 'X') . '</a>';
            })
            ->editColumn('total_amount', function ($invoices) {
                $totalPrice = app(InvoiceCalculator::class, ['invoice' => $invoices])->getTotalPrice();
                return app(MoneyConverter::class, ['money' => $totalPrice])->format();
            })
            ->editColumn('invoice_sent', function ($invoices) {
                return $invoices->sent_at ? __('yes'): __('no');
            })
            ->editColumn('status', function ($invoices) {
                return __(InvoiceStatus::fromStatus($invoices->status)->getDisplayValue());
            })
            ->rawColumns(['invoice_number'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return mixed
     */
    public function create()
    {
        return view('clients.create')
            ->withUsers(User::with('department')->get()->pluck('nameAndDepartmentEagerLoading', 'id'))
            ->withIndustries($this->listAllIndustries())
            ->withCountry(Country::fromCode(Setting::first()->country));
    }

    /**
     * @param StoreClientRequest $request
     * @return mixed
     */
    public function store(StoreClientRequest $request)
    {
        $client = Client::create([
            'external_id' => Uuid::uuid4()->toString(),
            'vat' => $request->vat,
            'company_name' => $request->company_name,
            'address' => $request->address,
            'zipcode' => $request->zipcode,
            'city' => $request->city,
            'company_type' => $request->company_type,
            'industry_id' => $request->industry_id,
            'user_id' => $request->user_id,
            'client_number' => app(ClientNumberService::class)->setNextClientNumber(),
        ]);

        $contact = Contact::create([
            'external_id' => Uuid::uuid4()->toString(),
            'name' => $request->name,
            'email' => $request->email,
            'primary_number' => $request->primary_number,
            'secondary_number' => $request->secondary_number,
            'client_id' => $client->id,
            'is_primary' => true
        ]);

        Session()->flash('flash_message', __('Client successfully added'));
        event(new \App\Events\ClientAction($client, self::CREATED));
        return redirect()->route('clients.index');
    }

    /**
     * @param Request $vatRequest
     * @return mixed
     */
    public function cvrapiStart(Request $request)
    {
        $vat = $request->input('vat');

        $country = $request->input('country');
        $company_name = $request->input('company_name');

        // Strip all other characters than numbers
        $vat = preg_replace('/[^0-9]/', '', $vat);

        $result = $this->cvrApi($vat, 'dk');


        return redirect()->back()
            ->with('data', $result);
    }

    public function cvrApi($vat)
    {
        if (empty($vat)) {
            // Print error message

            return ('Please insert VAT');
        } else {
            // Start cURL
            $ch = curl_init();

            // Set cURL options
            curl_setopt($ch, CURLOPT_URL, 'http://cvrapi.dk/api?search=' . $vat . '&country=dk');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Daybyday');

            // Parse result
            $result = curl_exec($ch);

            // Close connection when done
            curl_close($ch);

            // Return our decoded result
            return json_decode($result, 1);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int $external_id
     * @return mixed
     */
    public function show($external_id)
    {
        $client = $this->findByExternalId($external_id);
        //dd($client->appointments);
        return view('clients.show')
            ->withClient($client)
            ->withCompanyname(Setting::first()->company)
            ->withInvoices($this->getInvoices($client))
            ->withUsers(User::with('department')->get()->pluck('nameAndDepartmentEagerLoading', 'id'))
            ->with('filesystem_integration', Integration::whereApiType('file')->first())
            ->with('documents', $client->documents()->where('integration_type', get_class(GetStorageProvider::getStorage()))->get())
            ->with('lead_statuses', Status::typeOfLead()->get())
            ->with('task_statuses', Status::typeOfTask()->get())
            ->withRecentAppointments($client->appointments()->orderBy('start_at', 'desc')->where('end_at', '>', now()->subMonths(3))->limit(7)->get());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $external_id
     * @return mixed
     */
    public function edit($external_id)
    {
        $client = $this->findByExternalId($external_id);
        $contact = $client->primaryContact;
        $client = (object)array_merge($contact->toArray(), $client->toArray());

        return view('clients.edit')
            ->withClient($client)
            ->withUsers(User::with('department')->get()->pluck('nameAndDepartmentEagerLoading', 'id'))
            ->withIndustries($this->listAllIndustries());
    }

    /**
     * @param $external_id
     * @param UpdateClientRequest $request
     * @return mixed
     */
    public function update($external_id, UpdateClientRequest $request)
    {
        $client = $this->findByExternalId($external_id);
        $client->fill([
            'vat' => $request->vat,
            'company_name' => $request->company_name,
            'address' => $request->address,
            'zipcode' => $request->zipcode,
            'city' => $request->city,
            'company_type' => $request->company_type,
            'industry_id' => $request->industry_id,
            'user_id' => $request->user_id,
            ])->save();

        $client->primaryContact->fill([
            'name' => $request->name,
            'email' => $request->email,
            'primary_number' => $request->primary_number,
            'secondary_number' => $request->secondary_number,
            'client_id' => $client->id,
            'is_primary' => true
        ])->save();

        Session()->flash('flash_message', __('Client successfully updated'));
        return redirect()->route('clients.index');
    }

    /**
     * @param $external_id
     * @return mixed
     */
    public function destroy($external_id)
    {
        try {
            $client = $this->findByExternalId($external_id);
            $client->delete();
            Session()->flash('flash_message', __('Client successfully deleted'));
        } catch (\Exception $e) {
            Session()->flash('flash_message_warning', __('Client could not be deleted, contact Daybyday support'));
        }

        return redirect()->route('clients.index');
    }

    /**
     * @param $external_id
     * @param Request $request
     * @return mixed
     */
    public function updateAssign($external_id, Request $request)
    {
        if (!auth()->user()->can('client-update')) {
            Session()->flash('flash_message_warning', __("Not authorized"));
            return back();
        }

        $user = User::where('external_id', $request->user_external_id)->first();
        $client = Client::with('user')->where('external_id', $external_id)->first();
        $client->updateAssignee($user);

        Session()->flash('flash_message', __('New user is assigned'));
        return redirect()->back();
    }


    /**
     * @param $client
     * @return mixed
     */
    public function getInvoices($client)
    {
        $invoice = $client->invoices()->with('invoiceLines')->get();

        return $invoice;
    }

    public function findByExternalId($external_id)
    {
        return Client::where('external_id', $external_id)->firstOrFail();
    }

    /**
     * @return mixed
     */
    public function listAllClients()
    {
        return Client::pluck('company_name', 'id');
    }

    /**
     * @return int
     */
    public function getAllClientsCount()
    {
        return Client::all()->count();
    }

    /**
     * @return mixed
     */
    public function listAllIndustries()
    {
        return Industry::pluck('name', 'id');
    }
}
