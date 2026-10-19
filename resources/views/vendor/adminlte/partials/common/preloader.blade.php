@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

<div class="{{ $preloaderHelper->makePreloaderClasses() }} dtc-skeleton-preloader" style="{{ $preloaderHelper->makePreloaderStyle() }}">

    @hasSection('preloader')

        {{-- Custom preloader content --}}
        @yield('preloader')

    @else

        {{-- DTC Skeleton Preloader --}}
        <div class="dtc-skeleton-wrap">

            {{-- Sidebar skeleton --}}
            <div class="dtc-sk-sidebar">
                <div class="dtc-sk-brand">
                    <div class="dtc-sk-box dtc-sk-avatar"></div>
                    <div class="dtc-sk-box dtc-sk-brand-text"></div>
                </div>
                <div class="dtc-sk-nav">
                    <div class="dtc-sk-box dtc-sk-label"></div>
                    @for ($i = 0; $i < 4; $i++)
                        <div class="dtc-sk-nav-item">
                            <div class="dtc-sk-box dtc-sk-icon"></div>
                            <div class="dtc-sk-box dtc-sk-nav-text"></div>
                        </div>
                    @endfor
                    <div class="dtc-sk-box dtc-sk-label" style="margin-top:18px;"></div>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="dtc-sk-nav-item">
                            <div class="dtc-sk-box dtc-sk-icon"></div>
                            <div class="dtc-sk-box dtc-sk-nav-text"></div>
                        </div>
                    @endfor
                </div>
                <div class="dtc-sk-user-panel">
                    <div class="dtc-sk-box dtc-sk-avatar"></div>
                    <div class="dtc-sk-user-info">
                        <div class="dtc-sk-box dtc-sk-user-name"></div>
                        <div class="dtc-sk-box dtc-sk-user-role"></div>
                    </div>
                </div>
            </div>

            {{-- Main area skeleton --}}
            <div class="dtc-sk-main">

                {{-- Navbar --}}
                <div class="dtc-sk-navbar">
                    <div class="dtc-sk-box dtc-sk-page-title"></div>
                    <div class="dtc-sk-navbar-actions">
                        <div class="dtc-sk-box dtc-sk-action-btn"></div>
                        <div class="dtc-sk-box dtc-sk-action-btn"></div>
                        <div class="dtc-sk-box dtc-sk-action-btn"></div>
                    </div>
                </div>

                {{-- Stat cards --}}
                <div class="dtc-sk-cards">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="dtc-sk-card">
                            <div class="dtc-sk-box dtc-sk-card-label"></div>
                            <div class="dtc-sk-box dtc-sk-card-value"></div>
                            <div class="dtc-sk-box dtc-sk-card-sub"></div>
                        </div>
                    @endfor
                </div>

                {{-- Content rows --}}
                <div class="dtc-sk-content">
                    <div class="dtc-sk-panel">
                        <div class="dtc-sk-box dtc-sk-panel-header"></div>
                        @for ($i = 0; $i < 5; $i++)
                            <div class="dtc-sk-row">
                                <div class="dtc-sk-box dtc-sk-row-cell dtc-sk-cell-sm"></div>
                                <div class="dtc-sk-box dtc-sk-row-cell dtc-sk-cell-lg"></div>
                                <div class="dtc-sk-box dtc-sk-row-cell dtc-sk-cell-md"></div>
                                <div class="dtc-sk-box dtc-sk-row-cell dtc-sk-cell-sm"></div>
                            </div>
                        @endfor
                    </div>
                    <div class="dtc-sk-panel dtc-sk-panel-side">
                        <div class="dtc-sk-box dtc-sk-panel-header"></div>
                        @for ($i = 0; $i < 4; $i++)
                            <div class="dtc-sk-side-item">
                                <div class="dtc-sk-box dtc-sk-icon"></div>
                                <div class="dtc-sk-side-text">
                                    <div class="dtc-sk-box dtc-sk-side-title"></div>
                                    <div class="dtc-sk-box dtc-sk-side-sub"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>

            </div>{{-- /.dtc-sk-main --}}
        </div>{{-- /.dtc-skeleton-wrap --}}

    @endif

</div>