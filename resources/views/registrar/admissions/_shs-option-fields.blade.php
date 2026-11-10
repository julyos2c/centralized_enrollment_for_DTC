<div class="form-row">
    <div class="form-group col-md-4">
        <label for="{{ $prefix }}-track">Track</label>
        <input id="{{ $prefix }}-track" name="track" class="form-control" maxlength="100" required value="{{ $option?->track }}" placeholder="e.g. Academic Track">
    </div>
    <div class="form-group col-md-5">
        <label for="{{ $prefix }}-name">Name</label>
        <input id="{{ $prefix }}-name" name="name" class="form-control" maxlength="255" required value="{{ $option?->name }}">
    </div>
    <div class="form-group col-md-2">
        <label for="{{ $prefix }}-offering">Matches school offering</label>
        <select id="{{ $prefix }}-offering" name="offering_key" class="form-control">
            <option value="">None</option>
            @foreach($offeringKeys as $key)
                <option value="{{ $key }}" @selected($option?->offering_key === $key)>{{ $key }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-1">
        <label for="{{ $prefix }}-order">Order</label>
        <input id="{{ $prefix }}-order" type="number" name="sort_order" class="form-control" min="0" max="9999" value="{{ $option?->sort_order ?? 100 }}">
    </div>
</div>
<div class="form-group">
    <label class="mb-0 font-weight-normal"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($option?->is_active ?? true)> Offered on the application form</label>
</div>
