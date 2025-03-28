@extends('layouts.master')

@section('heading')
    {{ __('Data Management') }}
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="tablet">
            <div class="tablet__head">
                <div class="tablet__head-label">
                    <h3 class="tablet__head-title">{{ __('Database Statistics') }}</h3>
                </div>
            </div>
            <div class="tablet__body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4>{{ __('Clients') }}</h4>
                            <p class="stat-number">{{ $stats['clients'] }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4>{{ __('Leads') }}</h4>
                            <p class="stat-number">{{ $stats['leads'] }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4>{{ __('Tasks') }}</h4>
                            <p class="stat-number">{{ $stats['tasks'] }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4>{{ __('Projects') }}</h4>
                            <p class="stat-number">{{ $stats['projects'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4>{{ __('Invoices') }}</h4>
                            <p class="stat-number">{{ $stats['invoices'] }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4>{{ __('Offers') }}</h4>
                            <p class="stat-number">{{ $stats['offers'] }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4>{{ __('Payments') }}</h4>
                            <p class="stat-number">{{ $stats['payments'] }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4>{{ __('Users') }}</h4>
                            <p class="stat-number">{{ $stats['users'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="tablet">
            <div class="tablet__head">
                <div class="tablet__head-label">
                    <h3 class="tablet__head-title">{{ __('Reset Database') }}</h3>
                </div>
            </div>
            <div class="tablet__body">
                <p class="text-danger">{{ __('Warning: This will delete all data in the database!') }}</p>
                <p>{{ __('This action will remove all data.') }}</p>
                <form action="{{ route('data.reset') }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to reset the database? All data will be lost!') }}')">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-md">
                        <i class="fa fa-trash"></i> {{ __('Reset Database') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
    <div class="tablet">
        <div class="tablet__head">
            <div class="tablet__head-label">
                <h3 class="tablet__head-title">{{ __('Generate Demo Data') }}</h3>
            </div>
        </div>
        <div class="tablet__body">
            <p>{{ __('Generate demo data') }}</p>
            <p>{{ __('This will create data.') }}</p>
            
            <!-- Placez votre formulaire ici -->
            <form action="{{ route('data.generate') }}" method="GET">
                <button type="submit" class="btn btn-brand btn-md">
                    <i class="fa fa-database"></i> {{ __('Generate Demo Data') }}
                </button>
            </form>
        </div>
    </div>
</div>
<!-- 
<div class="row mt-4">
    <div class="col-md-12">
        <div class="tablet">
            <div class="tablet__head">
                <div class="tablet__head-label">
                    <h3 class="tablet__head-title">{{ __('Data Import/Export') }}</h3>
                </div>
            </div>
            <div class="tablet__body">
                <div class="row">
                    <div class="col-md-6">
                        <h4>{{ __('Import Data') }}</h4>
                        <p>{{ __('Import data from CSV files into the system.') }}</p>
                        <a href="{{ route('data.import.form') }}" class="btn btn-brand">
                            <i class="fa fa-upload"></i> {{ __('Import CSV') }}
                        </a>
                        
                        <hr>
                        
                        
                    </div>
                    <div class="col-md-6">
                        <h4>{{ __('Export Data') }}</h4>
                        <p>{{ __('Export data from the system to CSV files.') }}</p>
                        <button class="btn btn-brand" disabled>
                            <i class="fa fa-download"></i> {{ __('Export CSV') }}
                            <small>({{ __('Coming soon') }})</small>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
 -->


</div>

<style>
    .stat-box {
        background-color: #f8f9fa;
        border-radius: 5px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #3c8dbc;
    }
</style>
@stop
