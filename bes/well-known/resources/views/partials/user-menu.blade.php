<!--begin::User Menu-->
<div class="d-flex align-items-center ms-2">
    <div class="dropdown">
        <button class="btn btn-icon btn-bg-transparent btn-active-color-primary" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="symbol symbol-30px symbol-circle">
                <img src="{{ asset('assets/media/avatars/150-1.jpg') }}" alt="user" />
            </span>
        </button>
        <ul class="dropdown-menu p-0 m-0 dropdown-menu-end">
            <li class="py-3">
                <a href="{{ route('dashboard') }}" class="menu-link px-5">Profile</a>
            </li>
            <li>
                <a href="{{ route('login') }}" class="menu-link px-5" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </li>
        </ul>
    </div>
</div>
<!--end::User Menu-->
