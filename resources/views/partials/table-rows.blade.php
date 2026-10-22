@php
    $serverRows = $tableRecords instanceof \Illuminate\Contracts\Pagination\Paginator;
    $rowLimit = $serverRows ? $tableRecords->perPage() : \App\Support\TablePagination::perPage(10);
@endphp
<div class="dtc-table-rows" data-table-rows-control data-server="{{ $serverRows ? 'true' : 'false' }}" @if(!empty($tableId)) data-table-target="{{ $tableId }}" @endif @if(!empty($fixedRows)) data-fixed-placement="true" @endif>
    @if(empty($fixedRows))
    <details class="dtc-table-rows-options" data-table-rows-options>
    <summary>Filters</summary>
    @endif
    <label data-table-rows-field>Rows per page
        <select data-table-rows-select aria-label="Rows per page" @if(!empty($fixedRows)) name="per_page" @endif>
            @unless(in_array($rowLimit, \App\Support\TablePagination::OPTIONS, true))
                <option value="{{ $rowLimit }}" selected>{{ $rowLimit }}</option>
            @endunless
            @foreach(\App\Support\TablePagination::OPTIONS as $limit)
                <option value="{{ $limit }}" {{ $rowLimit === $limit ? 'selected' : '' }}>{{ $limit }}</option>
            @endforeach
        </select>
    </label>
    @if(empty($fixedRows))</details>@endif
    <span data-table-rows-status aria-live="polite"></span>
    @unless($serverRows)
        <div class="dtc-table-rows-nav">
            <button type="button" data-table-rows-prev aria-label="Previous rows">Previous</button>
            <button type="button" data-table-rows-next aria-label="Next rows">Next</button>
        </div>
    @endunless
</div>
