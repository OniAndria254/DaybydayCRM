@extends('layouts.master')

@section('heading')
    {{ __('Import Data') }}
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="tablet">
            <div class="tablet__head">
                <div class="tablet__head-label">
                    <h3 class="tablet__head-title">{{ __('Import CSV Files') }}</h3>
                </div>
            </div>
            <div class="tablet__body">
                <form action="{{ route('import.process') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="form-group">
                        <label for="projects_file">{{ __('Projects CSV File') }}</label>
                        <input type="file" name="projects_file" id="projects_file" class="form-control" accept=".csv,.txt">
                        <small class="form-text text-muted">{{ __('CSV file with columns: project_title, client_name') }}</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="tasks_file">{{ __('Tasks CSV File') }}</label>
                        <input type="file" name="tasks_file" id="tasks_file" class="form-control" accept=".csv,.txt">
                        <small class="form-text text-muted">{{ __('CSV file with columns: project_title, task_title') }}</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="leads_file">{{ __('Leads, Offers & Invoices CSV File') }}</label>
                        <input type="file" name="leads_file" id="leads_file" class="form-control" accept=".csv,.txt">
                        <small class="form-text text-muted">{{ __('CSV file with columns: client_name, lead_title, type, produit, prix, quantite') }}</small>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-brand">
                            <i class="fa fa-upload"></i> {{ __('Import Data') }}
                        </button>
                        <a href="{{ route('dashboard') }}" class="btn btn-default">
                            {{ __('Cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@stop
