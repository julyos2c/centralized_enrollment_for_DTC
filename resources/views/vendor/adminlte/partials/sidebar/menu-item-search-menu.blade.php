<li>

    <div class="form-inline my-2">
        <div class="input-group" data-widget="sidebar-search" data-arrow-sign="&raquo;">

            {{-- Search input --}}
            <input class="form-control form-control-sidebar" type="search"
                @isset($item['id']) id="{{ $item['id'] }}" @endisset
                placeholder="{{ $item['text'] }}"
                aria-label="{{ $item['text'] }}">

            {{-- Search button --}}
            <div class="input-group-append">
                <button class="btn btn-sidebar">
                    <i data-lucide="search" style="width:1.25em;height:1.25em;flex-shrink:0"></i>
                </button>
            </div>

        </div>
    </div>

</li>
