<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use App\Models\Client;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use App\Models\Contact;
use Carbon\Carbon;
use App\Services\ClientNumber\ClientNumberService;
use App\Models\Task;
use App\Models\Lead;
use App\Models\Offer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use App\Models\Industry;
use Faker\Factory as Faker;

class ImportService
{
    protected $faker;

    public function __construct()
    {
        $this->faker = Faker::create();
    }

    public function importProjects($filename)
    {
        Log::info('Importing projects from: ' . $filename);
        
        $stats = [
            'total' => 0,
            'success' => 0,
            'errors' => 0,
            'clients_created' => 0,
            'details' => []
        ];
        
        if (!file_exists($filename)) {
            $errorMsg = 'File not found: ' . $filename;
            Log::error($errorMsg);
            $stats['details'][] = $errorMsg;
            $stats['errors']++;
            return $stats;
        }

        try {
            if (($handle = fopen($filename, 'r'))) {
                $header = fgetcsv($handle, 1000, ',');
                
                while (($data = fgetcsv($handle, 1000, ','))) {
                    $stats['total']++;
                    $row = array_combine($header, $data);

                    try {
                        $userId = User::inRandomOrder()->value('id');
                        $industryId = Industry::inRandomOrder()->value('id');

                        $client = Client::firstOrCreate(
                            ['company_name' => $row['client_name']],
                            [
                                'external_id' => Uuid::uuid4()->toString(),
                                'address' => $row['address'] ?? $this->faker->address,
                                'zipcode' => $row['zipcode'] ?? $this->faker->postcode,
                                'city' => $row['city'] ?? $this->faker->city,
                                'company_type' => 'ApS',
                                'industry_id' => $industryId,
                                'user_id' => $userId,
                                'client_number' => app(ClientNumberService::class)->setNextClientNumber(),
                            ]
                        );

                        if ($client->wasRecentlyCreated) {
                            Contact::firstOrCreate(
                                ['client_id' => $client->id],
                                [
                                    'external_id' => Uuid::uuid4()->toString(),
                                    'name' => $row['client_name'],
                                    'email' => $row['email'] ?? $this->faker->email,
                                    'primary_number' => $row['phone'] ?? $this->faker->phoneNumber,
                                    'is_primary' => true,
                                ]
                            );
                            $stats['clients_created']++;
                        }

                        $userAssignedId = User::inRandomOrder()->value('id');
                        $userCreatedId = User::inRandomOrder()->value('id');
                        
                        $status = Status::where('title', 'Open')->where('source_type', Project::class)->first();
                        
                        Project::firstOrCreate(
                            ['title' => $row['project_title']],
                            [
                                'external_id' => Uuid::uuid4()->toString(),
                                'description' => $row['description'] ?? $row['project_title'],
                                'client_id' => $client->id,
                                'status_id' => $status->id,
                                'user_assigned_id' => $userAssignedId,
                                'user_created_id' => $userCreatedId,
                                'deadline' => isset($row['deadline']) ? Carbon::parse($row['deadline']) : Carbon::now()->addMonths(1)
                            ]
                        );

                        $stats['success']++;
                    } catch (\Exception $e) {
                        $errorMsg = 'Error importing project: ' . $e->getMessage();
                        Log::error($errorMsg);
                        $stats['details'][] = $errorMsg;
                        $stats['errors']++;
                    }
                }
                fclose($handle);
            }
            return $stats;
        } catch (\Exception $e) {
            Log::error('Import projects error: ' . $e->getMessage());
            $stats['errors']++;
            $stats['details'][] = 'Import projects error: ' . $e->getMessage();
            return $stats;
        }
    }

    public function importTasks($filename)
    {
        Log::info('Importing tasks from: ' . $filename);
        
        $stats = [
            'total' => 0,
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        if (!file_exists($filename)) {
            $errorMsg = 'File not found: ' . $filename;
            Log::error($errorMsg);
            $stats['details'][] = $errorMsg;
            $stats['errors']++;
            return $stats;
        }
        
        try {
            if (($handle = fopen($filename, 'r'))) {
                $header = fgetcsv($handle, 1000, ',');
                
                while (($data = fgetcsv($handle, 1000, ','))) {
                    $stats['total']++;
                    $row = array_combine($header, $data);

                    try {
                        $project = Project::where('title', $row['project_title'])->first();
                        if (!$project) {
                            throw new \Exception("Project not found: " . $row['project_title']);
                        }

                        $userAssignedId = User::inRandomOrder()->value('id');
                        $userCreatedId = User::inRandomOrder()->value('id');
                        
                        $status = Status::where('title', 'Open')->where('source_type', Task::class)->first();
                        
                        Task::firstOrCreate(
                            ['title' => $row['task_title'],
                             'project_id' => $project->id],
                            [
                                'external_id' => Uuid::uuid4()->toString(),
                                'description' => $row['description'] ?? $this->faker->sentence,
                                'client_id' => $project->client_id,
                                'status_id' => $status->id,
                                'user_assigned_id' => $userAssignedId,
                                'user_created_id' => $userCreatedId,
                                'deadline' => isset($row['deadline']) ? Carbon::parse($row['deadline']) : Carbon::now()->addDays(rand(1, 30))
                            ]
                        );

                        $stats['success']++;
                    } catch (\Exception $e) {
                        $errorMsg = 'Error importing task: ' . $e->getMessage();
                        Log::error($errorMsg);
                        $stats['details'][] = $errorMsg;
                        $stats['errors']++;
                    }
                }
                fclose($handle);
            }
            return $stats;
        } catch (\Exception $e) {
            Log::error('Import tasks error: ' . $e->getMessage());
            $stats['errors']++;
            $stats['details'][] = 'Import tasks error: ' . $e->getMessage();
            return $stats;
        }
    }

    public function importLeadsAndInvoices($filename)
    {
        Log::info('Importing leads and invoices from: ' . $filename);
        
        $stats = [
            'success' => 0,
            'total' => 0,
            'leads_created' => 0,
            'offers_created' => 0,
            'invoices_created' => 0,
            'products_created' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        if (!file_exists($filename)) {
            $errorMsg = 'File not found: ' . $filename;
            Log::error($errorMsg);
            $stats['details'][] = $errorMsg;
            $stats['errors']++;
            return $stats;
        }
        
        try {
            if (($handle = fopen($filename, 'r'))) {
                $header = fgetcsv($handle, 1000, ',');
                $lineNumber = 1;
                
                $requiredColumns = ['client_name', 'lead_title', 'type', 'produit', 'prix', 'quantite'];
                $missingColumns = array_diff($requiredColumns, $header);
                
                if (!empty($missingColumns)) {
                    $errorMsg = 'CSV file is missing required columns: ' . implode(', ', $missingColumns);
                    Log::error($errorMsg);
                    $stats['details'][] = $errorMsg;
                    $stats['errors']++;
                    fclose($handle);
                    return $stats;
                }
                
                while (($data = fgetcsv($handle, 1000, ','))) {
                    $lineNumber++;
                    $stats['total']++;
                    $row = array_combine($header, $data);

                    try {
                        // Validation
                        if ($row['prix'] < 0) {
                            throw new \Exception("Price cannot be negative");
                        }

                        if ($row['quantite'] < 0) {
                            throw new \Exception("Quantity cannot be negative");
                        }

                        if (!in_array(strtolower($row['type']), ['offers', 'invoice'])) {
                            throw new \Exception("Type must be either 'offers' or 'invoice'");
                        }

                        // Get or create client
                        $client = Client::where('company_name', $row['client_name'])->first();
                        if (!$client) {
                            throw new \Exception("Client not found: " . $row['client_name']);
                        }

                        $userAssignedId = User::inRandomOrder()->value('id');
                        $userCreatedId = User::inRandomOrder()->value('id');

                        // Get or create product
                        $product = Product::firstOrCreate(
                            ['name' => $row['produit']],
                            [
                                'external_id' => Uuid::uuid4()->toString(),
                                'price' => $row['prix'],
                                'description' => 'Imported product',
                                'default_type' => 'hours'
                            ]
                        );

                        if ($product->wasRecentlyCreated) {
                            $stats['products_created']++;
                        }

                        // Get or create lead
                        $lead = Lead::firstOrCreate(
                            ['title' => $row['lead_title']],
                            [
                                'external_id' => Uuid::uuid4()->toString(),
                                'description' => $this->faker->sentence,
                                'status_id' => Status::where('title', 'Open')->where('source_type', Lead::class)->value('id'),
                                'user_assigned_id' => $userAssignedId,
                                'user_created_id' => $userCreatedId,
                                'client_id' => $client->id,
                                'deadline' => Carbon::now()->addMonth()
                            ]
                        );

                        if ($lead->wasRecentlyCreated) {
                            $stats['leads_created']++;
                        }

                        // Create offer
                        $statusOffer = strtolower($row['type']) == 'invoice' ? 'won' : 'in-progress';
                        if ($row['type'] == 'offers') {
                            $offer = Offer::create([
                                'status' => $statusOffer,
                                'source_id' => $lead->id,
                                'external_id' => Uuid::uuid4()->toString(),
                                'source_type' => Lead::class,
                                'client_id' => $client->id,
                            ]);
                            $stats['offers_created']++; 

                            InvoiceLine::create([
                                'external_id' => Uuid::uuid4()->toString(),
                                'title' => $product->name,
                                'type' => $product->default_type,
                                'comment' => '',
                                'quantity' => $row['quantite'],
                                'price' => $row['prix'],
                                'product_id' => $product->id,
                                'offer_id' => $offer->id
                            ]);

                            $stats['invoices_created']++;
    
                        }
                        

                        // Create invoice line for offer
                        // InvoiceLine::create([
                        //     'external_id' => Uuid::uuid4()->toString(),
                        //     'title' => $product->name,
                        //     'type' => $product->default_type,
                        //     'comment' => '',
                        //     'quantity' => $row['quantite'],
                        //     'price' => $row['prix'],
                        //     'product_id' => $product->id,
                        //     'offer_id' => $offer->id
                        // ]);

                        // If type is invoice, create invoice and line
                        if (strtolower($row['type']) == 'invoice') {
                            $invoice = Invoice::create([
                                'external_id' => Uuid::uuid4()->toString(),
                                'status' => 'draft',
                                'invoice_number' => app(\App\Services\InvoiceNumber\InvoiceNumberService::class)->setNextInvoiceNumber(),
                                'source_type' => Lead::class,
                                'source_id' => $lead->id,
                                'due_at' => Carbon::now()->addMonth(),
                                // 'offer_id' => $offer->id,
                                'client_id' => $client->id,
                            ]);

                            InvoiceLine::create([
                                'external_id' => Uuid::uuid4()->toString(),
                                'title' => $product->name,
                                'type' => $product->default_type,
                                'comment' => '',
                                'quantity' => $row['quantite'],
                                'price' => $row['prix'],
                                'product_id' => $product->id,
                                'invoice_id' => $invoice->id
                            ]);

                            $stats['invoices_created']++;
                        }

                        $stats['success']++;
                    } catch (\Exception $e) {
                        $errorMsg = sprintf('Error in file %s line %d: %s', $filename, $lineNumber, $e->getMessage());
                        Log::error($errorMsg);
                        $stats['details'][] = $errorMsg;
                        $stats['errors']++;
                    }
                }
                fclose($handle);
            }
            return $stats;
        } catch (\Exception $e) {
            Log::error('Import leads error: ' . $e->getMessage());
            $stats['errors']++;
            $stats['details'][] = 'Import leads error: ' . $e->getMessage();
            return $stats;
        }
    }
}