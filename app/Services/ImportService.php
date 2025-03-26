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

class ImportService
{
    /**
     * Importe les projets à partir d'un fichier CSV
     */
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
                $lineNumber = 1;

                if (!in_array('project_title', $header) || !in_array('client_name', $header)) {
                    throw new \Exception('CSV file is missing required columns (project_title, client_name)');
                }

                $defaultStatus = Status::where('title', 'Open')->where('source_type', 'App\Models\Project')->first();
                $industries = \App\Models\Industry::all();
                $defaultIndustry = $industries->isNotEmpty() ? $industries->random() : null;
                $users = User::all();
                $defaultUser = $users->isNotEmpty() ? $users->random() : User::first();
                $faker = \Faker\Factory::create();

                if (!$defaultUser) {
                    throw new \Exception('No users found in the system');
                }

                while (($data = fgetcsv($handle, 1000, ','))) {
                    $lineNumber++;
                    $stats['total']++;
                    $row = array_combine($header, $data);

                    try {
                        $client = Client::where('company_name', $row['client_name'])->first();

                        if (!$client) {
                            $client = Client::create([
                                'external_id' => Uuid::uuid4()->toString(),
                                'company_name' => $row['client_name'],
                                'vat' => isset($row['vat']) ? $row['vat'] : $faker->numerify('##########'),
                                'address' => isset($row['address']) ? $row['address'] : $faker->streetAddress,
                                'zipcode' => isset($row['zipcode']) ? $row['zipcode'] : $faker->postcode,
                                'city' => isset($row['city']) ? $row['city'] : $faker->city,
                                'user_id' => $defaultUser->id,
                                'industry_id' => isset($row['industry_id']) ? $row['industry_id'] : ($defaultIndustry ? $defaultIndustry->id : null),
                                'client_number' => app(ClientNumberService::class)->setNextClientNumber(),
                                'company_type' => 'ApS',
                            ]);

                            Contact::create([
                                'external_id' => Uuid::uuid4()->toString(),
                                'name' => $row['client_name'],
                                'email' => isset($row['email']) ? $row['email'] : $faker->email,
                                'primary_number' => isset($row['phone']) ? $row['phone'] : null,
                                'client_id' => $client->id,
                                'is_primary' => true
                            ]);

                            $stats['clients_created']++;
                        }

                        $existingProject = Project::where('title', $row['project_title'])
                            ->where('client_id', $client->id)
                            ->first();

                        if ($existingProject) {
                            $stats['details'][] = 'Project already exists: ' . $row['project_title'];
                            continue;
                        }

                        Project::create([
                            'external_id' => Uuid::uuid4()->toString(),
                            'title' => $row['project_title'],
                            'description' => isset($row['description']) ? $row['description'] : $row['project_title'],
                            'status_id' => $defaultStatus->id,
                            'client_id' => $client->id,
                            'user_id' => $client->user_id,
                            'user_assigned_id' => $client->user_id,
                            'user_created_id' => $client->user_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'deadline' => isset($row['deadline']) ? Carbon::parse($row['deadline']) : Carbon::now()->addMonths(1)
                        ]);

                        $stats['success']++;
                    } catch (\Exception $e) {
                        throw new \Exception(sprintf('Error in file %s line %d: %s', $filename, $lineNumber, $e->getMessage()));
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

    /**
     * Importe les tâches à partir d'un fichier CSV
     */
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
                $lineNumber = 1;
                
                if (!in_array('project_title', $header) || !in_array('task_title', $header)) {
                    $errorMsg = 'CSV file is missing required columns (project_title, task_title)';
                    Log::error($errorMsg);
                    $stats['details'][] = $errorMsg;
                    $stats['errors']++;
                    fclose($handle);
                    return $stats;
                }
                
                $defaultStatus = Status::where('title', 'Open')->where('source_type', 'App\Models\Task')->first();
                $users = User::all();
                $defaultUser = $users->isNotEmpty() ? $users->random() : User::first();
                $faker = \Faker\Factory::create();
                
                while (($data = fgetcsv($handle, 1000, ','))) {
                    $lineNumber++;
                    $stats['total']++;
                    $row = array_combine($header, $data);
                    
                    try {
                        $project = Project::where('title', $row['project_title'])->first();
                        if (!$project) {
                            throw new \Exception(sprintf('Project not found: %s', $row['project_title']));
                        }
                        
                        $existingTask = Task::where('title', $row['task_title'])
                            ->where('project_id', $project->id)
                            ->first();
                        
                        if ($existingTask) {
                            $stats['details'][] = 'Task already exists: ' . $row['task_title'];
                            continue;
                        }
                        
                        Task::create([
                            'external_id' => Uuid::uuid4()->toString(),
                            'title' => $row['task_title'],
                            'description' => isset($row['description']) ? $row['description'] : $faker->paragraph,
                            'status_id' => $defaultStatus->id,
                            'user_assigned_id' => isset($row['user_assigned_id']) ? $row['user_assigned_id'] : $defaultUser->id,
                            'user_created_id' => $defaultUser->id,
                            'client_id' => $project->client_id,
                            'project_id' => $project->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'deadline' => isset($row['deadline']) ? Carbon::parse($row['deadline']) : Carbon::now()->addDays(rand(1, 30))
                        ]);
                        
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
            Log::error('Import tasks error: ' . $e->getMessage());
            $stats['errors']++;
            $stats['details'][] = 'Import tasks error: ' . $e->getMessage();
            return $stats;
        }
    }

    /**
     * Importe les leads, offres et factures à partir d'un fichier CSV
     */
    public function importLeadsAndInvoices($filename)
    {
        Log::info('Importing leads, offers and invoices from: ' . $filename);
        
        $stats = [
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
                
                $defaultLeadStatus = Status::where('title', 'Open')->where('source_type', 'App\Models\Lead')->first();
                $users = User::all();
                $defaultUser = $users->isNotEmpty() ? $users->random() : User::first();
                $faker = \Faker\Factory::create();
                $groupedData = [];
                
                while (($data = fgetcsv($handle, 1000, ','))) {
                    $lineNumber++;
                    $stats['total']++;
                    $row = array_combine($header, $data);
                    
                    try {
                        if (isset($row['prix']) && $row['prix'] < 0) {
                            throw new \Exception("Negative price not allowed: " . $row['prix']);
                        }
                        
                        if (isset($row['quantite']) && $row['quantite'] < 0) {
                            throw new \Exception("Negative quantity not allowed: " . $row['quantite']);
                        }
                        
                        $type = strtolower($row['type']);
                        
                        if (!in_array($type, ['offers', 'invoice'])) {
                            throw new \Exception(sprintf("Invalid type '%s'. Must be 'offers' or 'invoice'", $type));
                        }
                        
                        if (!isset($groupedData[$row['client_name']][$row['lead_title']][$type])) {
                            $groupedData[$row['client_name']][$row['lead_title']][$type] = [];
                        }
                        
                        $groupedData[$row['client_name']][$row['lead_title']][$type][] = $row;
                    } catch (\Exception $e) {
                        $errorMsg = sprintf('Error in file %s line %d: %s', $filename, $lineNumber, $e->getMessage());
                        Log::error($errorMsg);
                        $stats['details'][] = $errorMsg;
                        $stats['errors']++;
                    }
                }
                
                foreach ($groupedData as $clientName => $leads) {
                    try {
                        $client = Client::where('company_name', $clientName)->first();
                        if (!$client) {
                            throw new \Exception('Client not found: ' . $clientName);
                        }
                        
                        foreach ($leads as $leadTitle => $types) {
                            try {
                                $lead = Lead::where('title', $leadTitle)
                                    ->where('client_id', $client->id)
                                    ->first();
                                
                                if (!$lead) {
                                    $lead = Lead::create([
                                        'external_id' => Uuid::uuid4()->toString(),
                                        'title' => $leadTitle,
                                        'description' => $faker->paragraph,
                                        'status_id' => $defaultLeadStatus->id,
                                        'user_id' => $defaultUser->id,
                                        'user_assigned_id' => $defaultUser->id,
                                        'client_id' => $client->id,
                                        'user_created_id' => $client->user_id,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                        'deadline' => Carbon::now()->addDays(rand(7, 30))
                                    ]);
                                    
                                    $stats['leads_created']++;
                                }
                                
                                if (isset($types['offers'])) {
                                    $this->processOffers($types['offers'], $lead, $client, $defaultUser, $stats, $filename);
                                }
                                
                                if (isset($types['invoice'])) {
                                    $this->processInvoices($types['invoice'], $lead, $client, $defaultUser, $stats, $filename);
                                }
                            } catch (\Exception $e) {
                                $errorMsg = sprintf('Error processing lead %s: %s', $leadTitle, $e->getMessage());
                                Log::error($errorMsg);
                                $stats['details'][] = $errorMsg;
                                $stats['errors']++;
                            }
                        }
                    } catch (\Exception $e) {
                        $errorMsg = sprintf('Error processing client %s: %s', $clientName, $e->getMessage());
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

    /**
     * Traite les lignes d'offre pour un lead
     */
    private function processOffers($offerLines, $lead, $client, $defaultUser, &$stats, $filename)
    {
        try {
            $offer = Offer::create([
                'external_id' => Uuid::uuid4()->toString(),
                'status' => \App\Enums\OfferStatus::inProgress()->getStatus(),
                'client_id' => $client->id,
                'source_id' => $lead->id,
                'source_type' => Lead::class,
            ]);
            
            foreach ($offerLines as $line) {
                try {
                    if (isset($line['prix']) && $line['prix'] < 0) {
                        throw new \Exception("Negative price not allowed: " . $line['prix']);
                    }
                    
                    if (isset($line['quantite']) && $line['quantite'] < 0) {
                        throw new \Exception("Negative quantity not allowed: " . $line['quantite']);
                    }
                    
                    $product = $this->findOrCreateProduct($line['produit'], $stats);
                    
                    InvoiceLine::create([
                        'external_id' => Uuid::uuid4()->toString(),
                        'title' => $product->name,
                        'comment' => '',
                        'quantity' => $line['quantite'],
                        'type' => 'hours',
                        'price' => $line['prix'],
                        'product_id' => $product->id,
                        'offer_id' => $offer->id
                    ]);
                } catch (\Exception $e) {
                    $errorMsg = sprintf('Error processing offer line: %s', $e->getMessage());
                    Log::error($errorMsg);
                    $stats['details'][] = $errorMsg;
                    $stats['errors']++;
                }
            }
            
            $stats['offers_created']++;
        } catch (\Exception $e) {
            $errorMsg = sprintf('Error creating offer: %s', $e->getMessage());
            Log::error($errorMsg);
            $stats['details'][] = $errorMsg;
            $stats['errors']++;
            throw $e;
        }
    }

    /**
     * Traite les lignes de facture pour un lead
     */
    private function processInvoices($invoiceLines, $lead, $client, $defaultUser, &$stats, $filename)
    {
        try {
            $offer = Offer::create([
                'external_id' => Uuid::uuid4()->toString(),
                'status' => \App\Enums\OfferStatus::won()->getStatus(),
                'client_id' => $client->id,
                'source_id' => $lead->id,
                'source_type' => Lead::class,
            ]);

            $invoice = Invoice::create([
                'external_id' => Uuid::uuid4()->toString(),
                'status' => \App\Enums\InvoiceStatus::draft()->getStatus(),
                'client_id' => $client->id,
                'source_id' => $lead->id,
                'source_type' => Lead::class,
                'invoice_number' => app(\App\Services\InvoiceNumber\InvoiceNumberService::class)->setNextInvoiceNumber(),
                'offer_id' => $offer->id,
            ]);
            
            foreach ($invoiceLines as $line) {
                try {
                    if (isset($line['prix']) && $line['prix'] < 0) {
                        throw new \Exception("Negative price not allowed: " . $line['prix']);
                    }
                    
                    if (isset($line['quantite']) && $line['quantite'] < 0) {
                        throw new \Exception("Negative quantity not allowed: " . $line['quantite']);
                    }
                    
                    $product = $this->findOrCreateProduct($line['produit'], $stats);
                    
                    InvoiceLine::create([
                        'external_id' => Uuid::uuid4()->toString(),
                        'title' => $product->name,
                        'comment' => '',
                        'quantity' => $line['quantite'],
                        'type' => 'hours',
                        'price' => $line['prix'],
                        'product_id' => $product->id,
                        'offer_id' => $offer->id
                    ]);

                    InvoiceLine::create([
                        'external_id' => Uuid::uuid4()->toString(),
                        'title' => $product->name,
                        'comment' => '',
                        'quantity' => $line['quantite'],
                        'type' => 'hours',
                        'price' => $line['prix'],
                        'product_id' => $product->id,
                        'invoice_id' => $invoice->id
                    ]);
                } catch (\Exception $e) {
                    $errorMsg = sprintf('Error processing invoice line: %s', $e->getMessage());
                    Log::error($errorMsg);
                    $stats['details'][] = $errorMsg;
                    $stats['errors']++;
                }
            }
            
            $stats['invoices_created']++;
        } catch (\Exception $e) {
            $errorMsg = sprintf('Error creating invoice: %s', $e->getMessage());
            Log::error($errorMsg);
            $stats['details'][] = $errorMsg;
            $stats['errors']++;
            throw $e;
        }
    }

    /**
     * Trouve ou crée un produit
     */
    private function findOrCreateProduct($productName, &$stats)
    {
        $product = Product::where('name', $productName)->first();
        
        if (!$product) {
            $product = Product::create([
                'external_id' => Uuid::uuid4()->toString(),
                'name' => $productName,
                'description' => 'Imported product',
                'price' => 0,
            ]);
            
            $stats['products_created']++;
        }
        
        return $product;
    }
}