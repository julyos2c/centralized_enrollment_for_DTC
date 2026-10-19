@section('content_top_nav_left')
    <li class="nav-item" style="margin-left:8px;">
        <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute; left:12px; top:50%;
               transform:translateY(-50%); color:#94A3B8; font-size:12px; z-index:1;"></i>
            <input type="text" placeholder="Search anything..."
                style="padding:8px 16px 8px 36px; border-radius:10px;
                       border:1.5px solid #E2E8F0; font-family:'Poppins',sans-serif;
                       font-size:13px; color:#1E293B; background:#F8FAFC;
                       outline:none; width:280px;">
        </div>
    </li>
@endsection

@section('content_top_nav_right')
    @php
        //Compute once — avoids N+1 and ensures $unread is always available
        $unread = auth()->check() ? auth()->user()->unreadNotificationsCount() : 0;
    @endphp

    {{-- Notification Bell --}}
    <li class="nav-item mr-2">
        <a href="{{ route('notifications.index') }}"
           style="position:relative; display:flex; align-items:center;
                  padding:8px 10px; text-decoration:none;">
            <i class="fas fa-bell" style="font-size:18px; color:#64748B;"></i>
            @if($unread > 0)
                <span style="position:absolute; top:4px; right:6px;
                             background:#EF4444; color:#fff; font-size:9px;
                             font-weight:700; border-radius:50%; width:16px; height:16px;
                             display:flex; align-items:center; justify-content:center;
                             font-family:'Poppins',sans-serif;">
                    {{ $unread }}
                </span>
            @endif
        </a>
    </li>

    {{-- User Profile Dropdown --}}
    <li class="nav-item dropdown mr-2">
        <a href="#" class="nav-link dropdown-toggle d-flex align-items-center"
           data-toggle="dropdown" style="gap:10px; padding:4px 8px;">
            <div style="width:36px; height:36px; border-radius:50%;
                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        display:flex; align-items:center; justify-content:center;
                        color:#fff; font-weight:700; font-size:14px;
                        font-family:'Poppins',sans-serif; flex-shrink:0;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div style="line-height:1.2;">
                <div style="font-size:13px; font-weight:600; color:#1E293B;">
                    {{ auth()->user()->name }}
                </div>
                <div style="font-size:11px; color:#64748B;">
                    {{ ucfirst(str_replace('_', ' ', auth()->user()->getRoleNames()->first() ?? '')) }}
                </div>
            </div>
        </a>
        <div class="dropdown-menu dropdown-menu-right"
             style="border:none; border-radius:14px;
                    box-shadow:0 8px 30px rgba(0,0,0,0.12);
                    padding:8px; min-width:200px; margin-top:8px;">

            {{-- Profile info header --}}
            <div style="padding:10px 14px 12px; border-bottom:1px solid #F1F5F9; margin-bottom:6px;">
                <div style="font-size:13px; font-weight:600; color:#1E293B;">
                    {{ auth()->user()->name }}
                </div>
                <div style="font-size:11px; color:#64748B;">
                    {{ auth()->user()->email }}
                </div>
            </div>

            <a class="dropdown-item" href="{{ route('notifications.index') }}"
               style="border-radius:8px; font-size:13px; padding:10px 14px;
                      font-family:'Poppins',sans-serif; color:#1E293B;">
                <i class="fas fa-bell mr-2" style="color:#0F4CDB; width:16px;"></i>
                Notifications
                @if($unread > 0)
                    <span class="float-right"
                          style="background:#EF4444; color:#fff; font-size:9px;
                                 font-weight:700; border-radius:50%; width:18px; height:18px;
                                 display:inline-flex; align-items:center; justify-content:center;">
                        {{ $unread }}
                    </span>
                @endif
            </a>

            <div style="border-top:1px solid #F1F5F9; margin:6px 0;"></div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-item"
                        style="border-radius:8px; font-size:13px; padding:10px 14px;
                               font-family:'Poppins',sans-serif; color:#EF4444;
                               background:none; border:none; width:100%;
                               text-align:left; cursor:pointer;">
                    <i class="fas fa-sign-out-alt mr-2" style="width:16px;"></i>
                    Logout
                </button>
            </form>
        </div>
    </li>
@endsection