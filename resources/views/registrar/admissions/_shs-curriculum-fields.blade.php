<div class="form-row">
    <div class="form-group col-md-5">
        <label for="{{ $prefix }}-name">Name</label>
        <input id="{{ $prefix }}-name" name="name" class="form-control" maxlength="150" required value="{{ $curriculum?->name }}">
    </div>
    <div class="form-group col-md-3">
        <label for="{{ $prefix }}-choice">Applicants choose a</label>
        <select id="{{ $prefix }}-choice" name="choice_label" class="form-control">
            @foreach(['Strand', 'Elective cluster', 'Specialization'] as $label)
                <option value="{{ $label }}" @selected(($curriculum?->choice_label ?? 'Strand') === $label)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-2">
        <label for="{{ $prefix }}-first">First graduates (SY start)</label>
        <input id="{{ $prefix }}-first" type="number" name="first_graduation_year" class="form-control" min="1990" max="2100" value="{{ $curriculum?->first_graduation_year }}" placeholder="e.g. 2017">
    </div>
    <div class="form-group col-md-2">
        <label for="{{ $prefix }}-last">Last graduates (blank = ongoing)</label>
        <input id="{{ $prefix }}-last" type="number" name="last_graduation_year" class="form-control" min="1990" max="2100" value="{{ $curriculum?->last_graduation_year }}">
    </div>
    <div class="form-group col-md-10">
        <label for="{{ $prefix }}-description">Description</label>
        <input id="{{ $prefix }}-description" name="description" class="form-control" maxlength="500" value="{{ $curriculum?->description }}">
    </div>
    <div class="form-group col-md-2">
        <label for="{{ $prefix }}-order">Display order</label>
        <input id="{{ $prefix }}-order" type="number" name="sort_order" class="form-control" min="0" max="9999" value="{{ $curriculum?->sort_order ?? 100 }}">
    </div>
</div>
<div class="form-group">
    <label class="mb-0 font-weight-normal"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($curriculum?->is_active ?? true)> Offered on the application form</label>
</div>
