<!--begin::Navbar-->
<div class="d-flex align-items-stretch" id="kt_header_nav">
    <!--begin::Menu wrapper-->
    <div class="header-menu align-items-stretch" data-kt-drawer="true" data-kt-drawer-name="header-menu" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_header_menu_mobile_toggle" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_body', lg: '#kt_header_nav'}">
        <!--begin::Menu-->
        <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch" id="#kt_header_menu" data-kt-menu="true">
            <!-- Dashboard Menu Item -->
            <div class="menu-item here show menu-lg-down-accordion me-lg-1">
                <a href="{{ route('dashboard') }}" class="menu-link py-3">
                    <span class="menu-title">Dashboards</span>
                </a> 
            </div>
            <!-- TM Order Menu Item -->
            <div class="menu-item menu-lg-down-accordion me-lg-1">
                <a href="{{ route('orders.index') }}" class="menu-link py-3">
                    <span class="menu-title">TM Order</span>
                </a>
            </div>
            <!-- External Controls Menu Item -->
            <div class="menu-item menu-lg-down-accordion me-lg-1">
                <a href="{{ route('orders.index') }}" class="menu-link py-3">
                    <span class="menu-title">External Controls</span>
                    <span class="menu-arrow d-lg-none"></span>
                </a>
            </div>
            <!-- Clients Menu Item -->
            <div class="menu-item menu-lg-down-accordion me-lg-1">
                <a href="{{ route('clients.index') }}" class="menu-link py-3">
                    <span class="menu-title">Clients</span>
                    <span class="menu-arrow d-lg-none"></span>
                </a>
            </div>
            <!-- Invoices Menu Item -->
            <div class="menu-item menu-lg-down-accordion me-lg-1">
                <a href="{{ route('orders.index') }}" class="menu-link py-3">
                    <span class="menu-title">Invoices</span>
                    <span class="menu-arrow d-lg-none"></span>
                </a>
            </div>
            <!-- Collections Menu Item -->
            <div class="menu-item menu-lg-down-accordion me-lg-1">
                <a href="{{ route('orders.index') }}" class="menu-link py-3">
                    <span class="menu-title">Collections</span>
                    <span class="menu-arrow d-lg-none"></span>
                </a>
            </div>
        </div>
        <!--end::Menu-->
    </div>
    <!--end::Menu wrapper-->
</div>
<!--end::Navbar-->