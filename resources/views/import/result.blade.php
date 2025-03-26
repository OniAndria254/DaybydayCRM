@extends('layouts.master')

@section('heading')
    {{ __('Import Results') }}
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="tablet">
            <div class="tablet__head">
                <div class="tablet__head-label">
                    <h3 class="tablet__head-title">{{ __('Import Results') }}</h3>
                </div>
            </div>
            <div class="tablet__body">
                @if(session('import_stats'))
                    <!-- Global Summary -->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">{{ __('Summary') }}</h3>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="stat-card stat-success">
                                        <h4>{{ __('Successful Imports') }}</h4>
                                        <span class="stat-number">{{ session('import_stats.success') }}</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card stat-{{ session('import_stats.errors') > 0 ? 'danger' : 'success' }}">
                                        <h4>{{ __('Errors Detected') }}</h4>
                                        <span class="stat-number">{{ session('import_stats.errors') }}</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card stat-info">
                                        <h4>{{ __('Total Processed') }}</h4>
                                        <span class="stat-number">{{ session('import_stats.total') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error Details Section -->
                    @if(session('import_stats.errors') > 0)
                        <div class="panel panel-danger">
                            <div class="panel-heading">
                                <h3 class="panel-title">
                                    <i class="fa fa-exclamation-triangle"></i>
                                    {{ __('Error Details') }}
                                </h3>
                            </div>
                            <div class="panel-body">
                                <div class="error-details">
                                    @foreach(session('import_stats.details') as $error)
                                        <div class="error-item">
                                            <div class="error-icon">
                                                <i class="fa fa-times-circle"></i>
                                            </div>
                                            <div class="error-message">
                                                {!! $error !!}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Detailed Sections -->
                    <div class="row">
                        @if(isset(session('import_stats')['projects']))
                        <div class="col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h3 class="panel-title">{{ __('Projects') }}</h3>
                                </div>
                                <div class="panel-body">
                                    <ul class="list-group">
                                        <li class="list-group-item">
                                            <span class="badge">{{ session('import_stats.projects.total') }}</span>
                                            {{ __('Total Rows') }}
                                        </li>
                                        <li class="list-group-item list-group-item-success">
                                            <span class="badge">{{ session('import_stats.projects.success') }}</span>
                                            {{ __('Successfully Imported') }}
                                        </li>
                                        <li class="list-group-item list-group-item-info">
                                            <span class="badge">{{ session('import_stats.projects.clients_created', 0) }}</span>
                                            {{ __('Clients Created') }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(isset(session('import_stats')['tasks']))
                        <div class="col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h3 class="panel-title">{{ __('Tasks') }}</h3>
                                </div>
                                <div class="panel-body">
                                    <ul class="list-group">
                                        <li class="list-group-item">
                                            <span class="badge">{{ session('import_stats.tasks.total') }}</span>
                                            {{ __('Total Rows') }}
                                        </li>
                                        <li class="list-group-item list-group-item-success">
                                            <span class="badge">{{ session('import_stats.tasks.success') }}</span>
                                            {{ __('Successfully Imported') }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Commercial Data -->
                    @if(isset(session('import_stats')['leads']))
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">{{ __('Commercial Data') }}</h3>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-blue">
                                            <i class="fa fa-star"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">{{ __('Leads') }}</span>
                                            <span class="info-box-number">{{ session('import_stats.leads.leads_created', 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-green">
                                            <i class="fa fa-file-text"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">{{ __('Offers') }}</span>
                                            <span class="info-box-number">{{ session('import_stats.leads.offers_created', 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-purple">
                                            <i class="fa fa-file-pdf-o"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">{{ __('Invoices') }}</span>
                                            <span class="info-box-number">{{ session('import_stats.leads.invoices_created', 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-aqua">
                                            <i class="fa fa-cube"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">{{ __('Products') }}</span>
                                            <span class="info-box-number">{{ session('import_stats.leads.products_created', 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Action Buttons -->
                    <div class="row mt-4">
                        <div class="col-md-12 text-center">
                            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                                <i class="fa fa-home"></i> {{ __('Back to Dashboard') }}
                            </a>
                            <a href="{{ route('import.form') }}" class="btn btn-default">
                                <i class="fa fa-upload"></i> {{ __('New Import') }}
                            </a>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        {{ __('No import results found. Please try importing again.') }}
                    </div>
                    <div class="text-center">
                        <a href="{{ route('import.form') }}" class="btn btn-primary">
                            <i class="fa fa-upload"></i> {{ __('Go to Import Form') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@stop

@section('css')
<style>
    /* Error Details Styling */
    .error-details {
        margin: 0;
        padding: 0;
    }
    .error-item {
        display: flex;
        align-items: flex-start;
        padding: 10px 0;
        border-bottom: 1px solid #f1f1f1;
    }
    .error-item:last-child {
        border-bottom: none;
    }
    .error-icon {
        color: #dd4b39;
        font-size: 18px;
        padding-right: 15px;
        padding-top: 2px;
    }
    .error-message {
        flex: 1;
        color: #721c24;
        font-size: 14px;
        line-height: 1.5;
    }
    
    /* Stat Cards */
    .stat-card {
        border-radius: 4px;
        padding: 15px;
        text-align: center;
        margin-bottom: 20px;
        background: #fff;
        border: 1px solid #eee;
    }
    .stat-card h4 {
        margin-top: 0;
        color: #666;
        font-size: 14px;
        text-transform: uppercase;
    }
    .stat-number {
        font-size: 24px;
        font-weight: 600;
    }
    .stat-success {
        border-top: 3px solid #00a65a;
    }
    .stat-success .stat-number {
        color: #00a65a;
    }
    .stat-danger {
        border-top: 3px solid #dd4b39;
    }
    .stat-danger .stat-number {
        color: #dd4b39;
    }
    .stat-info {
        border-top: 3px solid #00c0ef;
    }
    .stat-info .stat-number {
        color: #00c0ef;
    }
    
    /* Info Boxes */
    .info-box {
        display: block;
        min-height: 90px;
        background: #fff;
        width: 100%;
        box-shadow: 0 1px 1px rgba(0,0,0,0.1);
        border-radius: 2px;
        margin-bottom: 15px;
    }
    .info-box-icon {
        display: block;
        float: left;
        height: 90px;
        width: 90px;
        text-align: center;
        font-size: 45px;
        line-height: 90px;
        background: rgba(0,0,0,0.2);
        color: #fff;
    }
    .info-box-content {
        padding: 5px 10px;
        margin-left: 90px;
    }
    .info-box-text {
        display: block;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-transform: uppercase;
    }
    .info-box-number {
        display: block;
        font-weight: bold;
        font-size: 18px;
    }
    
    /* Colors */
    .bg-blue {
        background-color: #0073b7 !important;
    }
    .bg-green {
        background-color: #00a65a !important;
    }
    .bg-purple {
        background-color: #605ca8 !important;
    }
    .bg-aqua {
        background-color: #00c0ef !important;
    }
    
    /* Tablet Styling */
    .tablet {
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .tablet__head {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
    }
    .tablet__head-title {
        margin: 0;
        font-size: 18px;
    }
    .tablet__body {
        padding: 20px;
    }
</style>
@stop