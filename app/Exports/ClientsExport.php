<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;

class ClientsExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // return Client::all(); // Pour exporter tous les clients
        // OU pour un client spécifique :
        return Client::where('id', 26)->get();
    }
}
