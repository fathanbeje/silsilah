<nav class="navbar navbar-default navbar-static-top">
    <div class="container">
        <div class="navbar-header">

            <!-- Collapsed Hamburger -->
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse"
                data-target="#app-navbar-collapse">
                <span class="sr-only">Toggle Navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>

            <!-- Branding Image -->
            <a class="navbar-brand" href="{{ url('/') }}">
                {{ config('app.name', 'Laravel') }}
            </a>
        </div>

        <div class="collapse navbar-collapse" id="app-navbar-collapse">
            <!-- Left Side Of Navbar -->
            <ul class="nav navbar-nav">
                <li><a href="{{ route('users.search') }}">{{ __('app.search_your_family') }}</a></li>
                <li><a href="{{ route('birthdays.index') }}">{{ __('birthday.birthday') }}</a></li>
            </ul>

            <!-- Right Side Of Navbar -->
            <ul class="nav navbar-nav navbar-right">
                <!-- Authentication Links -->
                <?php $mark = (preg_match('/\?/', url()->current())) ? '&' : '?';?>
                <li><a href="{{ url(url()->current() . $mark . 'lang=en') }}">en</a></li>
                <li><a href="{{ url(url()->current() . $mark . 'lang=id') }}">id</a></li>
                <li><a href="{{ url(url()->current() . $mark . 'lang=ur') }}">ur</a></li>
                @if (Auth::guest())
                    <li><a href="{{ route('login') }}">Login</a></li>
                @else
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
                                {{ Auth::user()->display_name }}
                            @if (!empty($pendingReviewCount))
                                <span class="badge">{{ $pendingReviewCount }}</span>
                            @endif
                            <span class="caret"></span>
                        </a>

                        <ul class="dropdown-menu" role="menu">
                            @if (is_system_admin(auth()->user()))
                                <li><a href="{{ route('backups.index') }}">{{ __('backup.list') }}</a></li>
                                <li><a href="{{ route('birth-orders.index') }}">{{ __('app.birth_order_management') }}</a></li>
                                <li><a href="{{ route('registration-requests.index') }}">Permintaan Registrasi @if (!empty($pendingRegistrationRequestCount))<span class="badge">{{ $pendingRegistrationRequestCount }}</span>@endif</a></li>
                                <li><a href="{{ route('user-edit-requests.index') }}">Peninjauan Edit @if (!empty($pendingUserEditRequestCount))<span class="badge">{{ $pendingUserEditRequestCount }}</span>@endif</a></li>
                                <li><a href="{{ route('deploy-sync.index') }}">Deploy Sync</a></li>
                            @endif
                            <li><a href="{{ route('profile') }}">{{ __('app.my_profile') }}</a></li>
                            <li><a href="{{ route('gedcom.index') }}">Import GEDCOM</a></li>
                            <li><a href="{{ route('password_change') }}">{{ __('auth.change_password') }}</a></li>
                            <li>
                                <a href="{{ route('logout') }}" onclick="event.preventDefault();
                                                 document.getElementById('logout-form').submit();">
                                    Logout
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                    {{ csrf_field() }}
                                </form>
                            </li>
                        </ul>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</nav>
