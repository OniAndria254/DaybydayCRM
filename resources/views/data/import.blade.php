@extends('layouts.master')

@section('heading')
    {{ __('Import CSV Data') }}
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="tablet">
            <div class="tablet__head">
                <div class="tablet__head-label">
                    <h3 class="tablet__head-title">{{ __('CSV Import') }}</h3>
                </div>
            </div>
            <div class="tablet__body">
            <h4>{{ __('Import Industries') }}</h4>
                <form action="{{ route('data.import.industries') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="csv_file">{{ __('Industries CSV File') }}</label>
                        <input type="file" name="csv_file" id="csv_file" class="form-control" required accept=".csv,.txt">
                        <small class="form-text text-muted">{{ __('CSV file with columns: external_id, name') }}</small>
                    </div>
                    <button type="submit" class="btn btn-brand">
                        <i class="fa fa-upload"></i> {{ __('Import Industries') }}
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Afficher les colonnes disponibles lorsqu'une table est sélectionnée
        $('#table').change(function() {
            var table = $(this).val();
            if (table) {
                $.ajax({
                    url: '{{ url("api/table-columns") }}/' + table,
                    type: 'GET',
                    success: function(data) {
                        var columnsList = '<ul>';
                        $.each(data, function(index, column) {
                            columnsList += '<li>' + column + '</li>';
                        });
                        columnsList += '</ul>';
                        
                        $('#columns-list').html(columnsList);
                        $('#columns-info').show();
                    },
                    error: function() {
                        $('#columns-list').html('<p>{{ __("Error loading columns") }}</p>');
                        $('#columns-info').show();
                    }
                });
            } else {
                $('#columns-info').hide();
            }
        });
    });
</script>
@endpush
@stop
