<!-- Navigation -->
<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="{{ route('orders.index') }}">Comenzi</a>
        </div>
        <div class="collapse navbar-collapse" id="navbar">
            <ul class="nav navbar-nav">
				@if (Auth::user()->hasPermission('comenzi_tm'))
					<li class="{{ ((Request::is('/') || Request::is('orders*') || Request::is('edit-factura*')) && request('type') !== 'utvin') ? 'active' : '' }}">
						<a href="/"><i class='glyphicon glyphicon-list-alt'></i> Comenzi TM</a>
					</li>
				@endif
				
				@if (Auth::user()->hasPermission('comenzi_utvin'))
					<li class="{{ ((Request::is('/') || Request::is('orders*') || Request::is('edit-factura*')) && request('type') === 'utvin') ? 'active' : '' }}">
						<a href="/orders?type=utvin"><i class='glyphicon glyphicon-list-alt'></i> Comenzi UTVIN</a>
					</li>
				@endif
				
				@if (Auth::user()->hasPermission('comenzi_externe'))
					<li class="{{ Request::is('comenzi*') ? 'active' : '' }}">
						<a href="/comenzi"><i class='glyphicon glyphicon-list-alt'></i> Comenzi externe</a>
					</li>
				@endif
				
				@if (Auth::user()->hasPermission('produse'))
					<li class="{{ Request::is('produse*') ? 'active' : '' }}">
						<a href="/produse"><i class='glyphicon glyphicon-barcode'></i> Produse</a>
					</li>
				@endif
				
				@if (Auth::user()->hasPermission('clienti'))
					<li class="{{ Request::is('clients*') ? 'active' : '' }}">
						<a href="/clients" style="display: flex; align-items: center; gap: 5px;">
							<i class='glyphicon glyphicon-user'></i>
							<span>Clienti</span>
						</a>
					</li>
				@endif
				
				@if (Auth::user()->hasPermission('facturi'))
					<li class="{{ Request::is('facturi*') ? 'active' : '' }}">
						<a href="/facturi"><i class='glyphicon glyphicon-gbp'></i> Facturi</a>
					</li>
				@endif
				
				@if (Auth::user()->hasPermission('incasari'))
					<li class="{{ Request::is('incasari*') ? 'active' : '' }}">
						<a href="/incasari"><i class='glyphicon glyphicon-usd'></i> Incasari</a>
					</li>
				@endif
				
				@if (Auth::user()->hasPermission('ultilizatori'))
					<li class="{{ Request::is('utilizatori*') ? 'active' : '' }}">
						<a href="/utilizatori"><i class='glyphicon glyphicon-user'></i> Utilizatori</a>
					</li>
				@endif
				
				@if (Auth::user()->hasPermission('pieseauto'))
					<!--<li class="{{ Request::is('pieseauto*') ? 'active' : '' }}">
						<a href="/pieseauto"><i class='glyphicon glyphicon-share'></i> Pieseauto</a>
					</li>-->
				@endif
				
				@if (Auth::user()->hasPermission('searching'))
<li class="{{ Request::is('searching-new*') ? 'active' : '' }}">
                                <a href="/searching-new"><i class='glyphicon glyphicon-flash'></i> Supplier Search</a>
                            </li>
				@endif
            </ul>
		
			<ul class="nav navbar-nav navbar-right">
				@if (Auth::user()->hasPermission('apicredentials'))
					<li class="{{ request()->routeIs('apicredentials*') ? 'active' : '' }} setting-ico">
						<a href="{{ route('apicredentials.index') }}">
							<i class='glyphicon glyphicon-cog'></i>
						</a>
					</li>
				@endif
				<li>
					<form id="themeForm" method="POST" action="{{ route('theme.change') }}">
						@csrf
						<select name="theme" id="basicSelect" class="form-control" onchange="document.getElementById('themeForm').submit()">
							<option value="">Select Theme</option>
							<option value="blue" {{ \App\Helpers\GlobalHelper::get_setting('theme', 'blue') == 'blue' ? 'selected' : '' }}>Day mode</option>
							<option value="green" {{ \App\Helpers\GlobalHelper::get_setting('theme', 'blue') == 'green' ? 'selected' : '' }}>Green</option>
							<option value="black" {{ \App\Helpers\GlobalHelper::get_setting('theme', 'blue') == 'black' ? 'selected' : '' }}>Night mode</option>
						</select>
					</form>
				</li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" style="margin-top: 10%;">
                        @csrf
                        <a href="javascript:void(0);" onclick="event.preventDefault(); this.closest('form').submit();" style="color: #fff; text-decoration: none;margin-right: 7px; font-size: 14px;">
                            <i class="glyphicon glyphicon-log-out"></i> Deconectare
                        </a>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>
