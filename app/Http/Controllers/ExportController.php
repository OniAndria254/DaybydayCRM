<?php

namespace App\Http\Controllers;

use App\Exports\ClientsExport;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{

    // public function exportClient()
    // {
    //     $id = 26;
        
    //     // 1. Récupération des données (correction des requêtes)
    //     $client = Client::find($id); // Utilisez find() au lieu de where()->first()->get()
    //     // $projects = Project::where('id_client', $id)->get(); // Ajout de ->get()
    //     // $invoices = Invoice::where('id_client', $id)->get(); // Ajout de ->get()
    //     // $tasks = Task::where('id_client', $id)->get(); // Ajout de ->get()

    //     // 2. Préparation des données CSV
    //     $csvData = [];
        
    //     // En-tête du CSV
    //     $csvData[] = ['Type', 'Nom', 'Client', 'Date'];
        
    //     // // Ajout des projets
    //     // foreach ($projects as $project) {
    //     //     $csvData[] = [
    //     //         'Projet',
    //     //         $project->name,
    //     //         $client->name,
    //     //         $project->created_at->format('Y-m-d')
    //     //     ];
    //     // }
        
    //     // Ajout des factures
    //     // foreach ($invoices as $invoice) {
    //     //     $csvData[] = [
    //     //         'Facture',
    //     //         $invoice->name,
    //     //         $client->name,
    //     //         $invoice->created_at->format('Y-m-d')
    //     //     ];
    //     // }
        
    //     // Ajout des tâches (exemple)
    //     foreach ($tasks as $task) {
    //         $csvData[] = [
    //             'Tâche',
    //             $task->name,
    //             $client->name,
    //             $task->created_at->format('Y-m-d')
    //         ];
    //     }

    //     // 3. Génération du fichier CSV
    //     $filename = "client_{$client->name}_export_" . date('Y-m-d') . ".csv";
        
    //     // $headers = [
    //     //     'Content-Type' => 'text/csv',
    //     //     'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    //     // ];

    //     // // 4. Création du fichier CSV
    //     // $callback = function() use ($csvData) {
    //     //     $file = fopen('php://output', 'w');
            
    //     //     // Écriture des lignes
    //     //     foreach ($csvData as $row) {
    //     //         fputcsv($file, $row);
    //     //     }
            
    //     //     fclose($file);
    //     // };

    //     // return Response::stream($callback, 200, $headers);
    //     // return Excel::download($csvData, 'clients.csv');

    // }

    
     /**
    * @return \Illuminate\Support\Collection
    */
    public function exportClient() 
    {
        return Excel::download(new ClientsExport, 'clients.csv');
    }
 
}
