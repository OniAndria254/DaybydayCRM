<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Project;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\User;

class DataController extends Controller
{
    public function management()
    {
        // Récupérer quelques statistiques pour afficher sur la page
        $stats = [
            'clients' => Client::count(),
            'leads' => Lead::count(),
            'tasks' => Task::count(),
            'projects' => Project::count(),
            'invoices' => Invoice::count(),
            'offers' => Offer::count(),
            'payments' => Payment::count(),
            'users' => User::count(),
        ];
        
        return view('data.management', compact('stats'));
    }
    
    public function resetAndImportData()
    {
        // Vider les tables principales et générer de nouvelles données
        $this->resetDatabase();
        $this->generateData();
        
        return redirect()->route('data.management')
            ->with('flash_message', __('Database has been reset and new data has been generated'));
    }
    
    public function resetDatabase()
    {
        // Exécuter la commande via shell_exec
        $output = shell_exec('cd ' . base_path() . ' && php artisan migrate:fresh --seed 2>&1');
        \Log::info('Migration output: ' . $output);
        
        return redirect()->route('data.management')
            ->with('flash_message', __('Database has been reset'));
    }


    
    public function generateData()
    {
        // Utiliser la commande Artisan db:seed avec le seeder spécifique DummyDatabaseSeeder
        // Cette commande génère des données de démonstration
        Artisan::call('db:seed', ['--class' => 'DummyDatabaseSeeder']);
        
        return redirect()->route('data.management')
            ->with('flash_message', __('New data has been generated'));
    }

    /**
     * Affiche le formulaire d'importation CSV
     */
    public function showImportForm()
    {
        // Liste des tables disponibles pour l'importation
        $tables = [
            'clients' => 'Clients',
            'leads' => 'Leads',
            'tasks' => 'Tasks',
            'projects' => 'Projects',
            'invoices' => 'Invoices',
            'offers' => 'Offers',
            'payments' => 'Payments',
            'products' => 'Products',
            'industries' => 'Industries',
        ];
        
        return view('data.import', compact('tables'));
    }

    /**
     * Traite l'importation du fichier CSV
     */
    public function importCsv(Request $request)
    {
        // Validation
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
            'table' => 'required|string',
            'has_header' => 'boolean'
        ]);
        
        // Récupérer le fichier
        $file = $request->file('csv_file');
        $hasHeader = $request->has('has_header');
        $table = $request->input('table');
        
        // Vérifier que la table existe
        if (!in_array($table, ['clients', 'leads', 'tasks', 'projects', 'invoices', 'offers', 'payments', 'products'])) {
            return redirect()->back()->with('flash_message_warning', __('Invalid table selected'));
        }
        
        // Ouvrir le fichier
        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            return redirect()->back()->with('flash_message_warning', __('Could not open file'));
        }
        
        // Lire l'en-tête si nécessaire
        $header = null;
        if ($hasHeader) {
            $header = fgetcsv($handle, 0, ',');
        }
        
        // Préparer les colonnes en fonction de la table
        $columns = $this->getTableColumns($table);
        
        // Si pas d'en-tête, utiliser les colonnes de la table
        if (!$header) {
            $header = $columns;
        }
        
        // Vérifier la correspondance des colonnes
        $invalidColumns = array_diff($header, $columns);
        if (!empty($invalidColumns)) {
            return redirect()->back()->with('flash_message_warning', __('Invalid columns in CSV: ') . implode(', ', $invalidColumns));
        }
        
        // Commencer la transaction
        DB::beginTransaction();
        
        try {
            $rowCount = 0;
            
            // Lire les données ligne par ligne
            while (($data = fgetcsv($handle, 0, ',')) !== false) {
                if (count($data) != count($header)) {
                    continue; // Ignorer les lignes avec un nombre incorrect de colonnes
                }
                
                $rowData = array_combine($header, $data);
                
                // Ajouter external_id si nécessaire
                if (in_array('external_id', $columns) && !isset($rowData['external_id'])) {
                    $rowData['external_id'] = Uuid::uuid4()->toString();
                }
                
                // Insérer dans la table
                DB::table($table)->insert($rowData);
                $rowCount++;
            }
            
            fclose($handle);
            DB::commit();
            
            return redirect()->route('data.management')->with('flash_message', __('Successfully imported ') . $rowCount . __(' rows into ') . $table);
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            
            \Log::error('CSV import error: ' . $e->getMessage());
            return redirect()->back()->with('flash_message_warning', __('Error importing data: ') . $e->getMessage());
        }
    }

    /**
     * API pour récupérer les colonnes d'une table
     */
    public function getTableColumns($table)
    {
        // Vérifier que la table existe et est autorisée
        $allowedTables = ['clients', 'leads', 'tasks', 'projects', 'invoices', 'offers', 'payments', 'products', 'industries'];
        
        if (!in_array($table, $allowedTables)) {
            return response()->json(['error' => 'Invalid table'], 400);
        }
        
        try {
            $columns = \Schema::getColumnListing($table);
            return response()->json($columns);
        } catch (\Exception $e) {
            \Log::error('Error getting columns for table ' . $table . ': ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Importe les données d'industries à partir d'un fichier CSV
     * 
     * @param string $filename Chemin complet vers le fichier CSV
     * @return int Nombre d'enregistrements importés
     */
    public function import_industry($filename) 
    {
        \Log::info('Importing industry data from file: ' . $filename);
        
        $count = 0;
        
        // Vérifier que le fichier existe
        if (!file_exists($filename)) {
            \Log::error('File not found: ' . $filename);
            return $count;
        }
        
        // Ouvrir le fichier CSV
        if (($handle = fopen($filename, 'r')) !== false) {
            // Lire la ligne d'en-tête
            $header = fgetcsv($handle, 1000, ';');
            
            // Vérifier que les colonnes nécessaires sont présentes
            if (!in_array('external_id', $header) || !in_array('name', $header)) {
                \Log::error('CSV file is missing required columns (external_id, name)');
                fclose($handle);
                return $count;
            }
            
            // Parcourir le fichier ligne par ligne
            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                // Créer un tableau associatif avec l'en-tête comme clés
                $row = array_combine($header, $data);
                
                try {
                    // Insérer les données dans la table industries
                    \App\Models\Industry::create([
                        'external_id' => $row['external_id'] ?: \Ramsey\Uuid\Uuid::uuid4()->toString(),
                        'name' => $row['name'],
                    ]);
                    
                    $count++;
                } catch (\Exception $e) {
                    \Log::error('Error importing industry: ' . $e->getMessage());
                    \Log::error('Row data: ' . json_encode($row));
                }
            }
            
            // Fermer le fichier
            fclose($handle);
            \Log::info("Successfully imported $count industries");
        } else {
            \Log::error('Could not open file: ' . $filename);
        }
        
        return $count;
    }

    public function importIndustries(Request $request)
    {
        // Validation du fichier
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);
        
        // Récupérer le chemin temporaire du fichier
        $tempPath = $request->file('csv_file')->getRealPath();
        
        // Importer les données directement depuis le fichier temporaire
        $count = $this->import_industry($tempPath);
        
        return redirect()->route('data.management')
            ->with('flash_message', __('Successfully imported ') . $count . __(' industries'));
    }

    

}
