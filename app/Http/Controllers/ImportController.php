<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    protected $importService;
    
    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
        $this->middleware('auth');
    }
    
    /**
     * Affiche le formulaire d'importation
     */
    public function showImportForm()
    {
        return view('import.form');
    }
    
    /**
     * Traite l'importation des fichiers CSV
    */
    public function processImport(Request $request)
    {
        $request->validate([
            'projects_file' => 'nullable|file|mimes:csv,txt|max:10240',
            'tasks_file' => 'nullable|file|mimes:csv,txt|max:10240',
            'leads_file' => 'nullable|file|mimes:csv,txt|max:10240',
        ]);

        $stats = [
            'total' => 0,
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];

        DB::beginTransaction(); // DÉMARRAGE DE LA TRANSACTION GLOBALE

        try {
            // Importer les projets si un fichier est fourni
            if ($request->hasFile('projects_file')) {
                $projectsPath = $request->file('projects_file')->getRealPath();
                $projectStats = $this->importService->importProjects($projectsPath);
                $this->mergeStats($stats, $projectStats);
            }

            // Importer les tâches si un fichier est fourni
            if ($request->hasFile('tasks_file')) {
                $tasksPath = $request->file('tasks_file')->getRealPath();
                $taskStats = $this->importService->importTasks($tasksPath);
                $this->mergeStats($stats, $taskStats);
            }

            // Importer les leads, offres et factures si un fichier est fourni
            if ($request->hasFile('leads_file')) {
                $leadsPath = $request->file('leads_file')->getRealPath();
                $leadStats = $this->importService->importLeadsAndInvoices($leadsPath);
                $this->mergeStats($stats, $leadStats);
            }

            // Vérification des erreurs : si une seule erreur est détectée, on annule tout
            if ($stats['errors'] > 0) {
                throw new \Exception("Errors detected during import, rolling back...");
            }

            DB::commit(); // Validation des changements

            return redirect()->route('import.result')->with('import_stats', $stats);

        } catch (\Exception $e) {
            DB::rollBack(); // ANNULATION TOTALE EN CAS D'ERREUR
            Log::error('Global import error: ' . $e->getMessage());

            $stats['errors']++;
            $stats['details'][] = 'ALL CHANGES WERE ROLLED BACK DUE TO ERRORS';
            $stats['details'][] = 'Global import error: ' . $e->getMessage();

            return redirect()->route('import.result')->with('import_stats', $stats);
        }
    }

    /**
     * Fusionne les statistiques d'importation
     */
    private function mergeStats(array &$combinedStats, array $newStats)
    {
        foreach ($newStats as $key => $value) {
            if (is_int($value) && isset($combinedStats[$key])) {
                $combinedStats[$key] += $value;
            } elseif ($key === 'details') {
                $combinedStats[$key] = array_merge($combinedStats[$key], $value);
            }
        }
    }



    
    /**
     * Affiche les résultats de l'importation
     */
    public function showImportResult()
    {
        $stats = session('import_stats');
        
        if (!$stats) {
            return redirect()->route('import.form')
                ->with('flash_message_warning', __('No import statistics available'));
        }
        
        return view('import.result', compact('stats'));
    }
}
