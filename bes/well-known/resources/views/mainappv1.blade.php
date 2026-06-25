<!DOCTYPE html>
<html lang="ro">
    <head>
        <!-- मेटा टैग -->
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Comenzi Timisoara')</title>
		
		@php
			$theme = \App\Helpers\GlobalHelper::get_setting('theme', 'blue');
		@endphp
		<link rel="stylesheet" href="{{ asset('aftb-theme/' . $theme . '.css') }}">

        <!-- CSS लिंक्स -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css">
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
		<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
        <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
		<link rel="stylesheet" href="{{ asset('css/datepicker.css') }}" />
        <link rel="stylesheet" href="{{ asset('css/foundation-datepicker.css') }}">
        
        <!-- फॉन्ट अवसम -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">

        <!-- अतिरिक्त स्टाइल -->
        @yield('additional_styles')

        <!-- फेविकॉन -->
        <link rel="icon" href="{{ asset('image/rtf.ico') }}" sizes="32x32" type="image/ico">
        
        <!-- जावास्क्रिप्ट - सिर्फ जरूरी स्क्रिप्ट्स ही रखें -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
        <script src="{{ asset('js/foundation-datepicker.js') }}"></script>
		<script src="{{ asset('js/foundation-datepicker.ro.js') }}"></script>
		
		<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
		<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
		<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
        
        <!-- DataTables Scripts -->
        <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
		
        <!-- हेड स्क्रिप्ट्स -->
        @yield('head_scripts')
    </head>
    <body>
        <nav role="navigation" class="navbar navbar-default navbar-fixed-top">
            <div class="container-fluid">
                <!-- Brand and toggle get grouped for better mobile display -->
                <div class="navbar-header">
                    <button type="button" data-target="#navbarCollapse" data-toggle="collapse" class="navbar-toggle">
                        <span class="sr-only">Navigare</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="{{ route('orders.index') }}">Comenzi</a>
                </div>

                <!-- Collect the nav links, forms, and other content for toggling -->
                <div id="navbarCollapse" class="collapse navbar-collapse">
                    <ul class="nav navbar-nav">
						@if (Auth::user()->hasPermission('comenzi_tm'))
							<li class="{{ ((Request::is('/') || Request::is('orders*') || Request::is('edit-factura*')) && request('type') !== 'utvin') ? 'active' : '' }}">
								<a href="{{ route('orders.index') }}">
									<i class='glyphicon glyphicon-list-alt'></i> Comenzi TM
								</a>
							</li>
						@endif
						
						@if (Auth::user()->hasPermission('comenzi_utvin'))
							<li class="{{ ((Request::is('/') || Request::is('orders*') || Request::is('edit-factura*')) && request('type') === 'utvin') ? 'active' : '' }}">
								<a href="/orders?type=utvin"><i class='glyphicon glyphicon-list-alt'></i> Comenzi UTVIN</a>
							</li>
						@endif
						
						@if (Auth::user()->hasPermission('comenzi_externe'))
							<li class="{{ request()->routeIs('comenzi*') ? 'active' : '' }}">
								<a href="{{ route('comenzi.index') }}">
									<i class='glyphicon glyphicon-list-alt'></i> Comenzi externe
								</a>
							</li>
						@endif
						
						@if (Auth::user()->hasPermission('produse'))
							<li class="{{ request()->routeIs('produse*') ? 'active' : '' }}">
								<a href="{{ route('produse.index') }}">
									<i class='glyphicon glyphicon-barcode'></i> Produse
								</a>
							</li>
						@endif
						
						@if (Auth::user()->hasPermission('clienti'))
							<li class="{{ request()->routeIs('clients*') ? 'active' : '' }}">
								<a href="{{ route('clients.index') }}">
									<i class='glyphicon glyphicon-user'></i> Clienti
								</a>
							</li>
						@endif
						
						@if (Auth::user()->hasPermission('facturi'))
							<li class="{{ request()->routeIs('facturi*') ? 'active' : '' }}">
								<a href="{{ route('facturi.index') }}">
									<i class='glyphicon glyphicon-gbp'></i> Facturi
								</a>
							</li>
						@endif
						
						@if (Auth::user()->hasPermission('incasari'))
							<li class="{{ request()->routeIs('incasari*') ? 'active' : '' }}">
								<a href="{{ route('incasari.index') }}">
									<i class='glyphicon glyphicon-usd'></i> Incasari
								</a>
							</li>
						@endif
						
						@if (Auth::user()->hasPermission('ultilizatori'))
							<li class="{{ request()->routeIs('utilizatori*') ? 'active' : '' }}">
								<a href="{{ route('utilizatori.index') }}">
									<i class='glyphicon glyphicon-user'></i> Utilizatori
								</a>
							</li>
						@endif
						
						@if (Auth::user()->hasPermission('pieseauto'))
							<!--<li class="{{ Request::is('pieseauto*') ? 'active' : '' }}">
								<a href="/pieseauto"><i class='glyphicon glyphicon-share'></i> Pieseauto</a>
							</li>-->
						@endif
						
						@if (Auth::user()->hasPermission('searching'))
							<!--<li class="{{ Request::is('searching-new*') ? 'active' : '' }}">
								<a href="/searching-new"><i class='glyphicon glyphicon-flash'></i> Supplier Search New</a>
							</li>-->		
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
                        <li role="presentation">
                            <form method="POST" action="{{ route('logout') }}" style="margin-top: 8px;">
                                @csrf
                                <a href="#" onclick="event.preventDefault(); this.closest('form').submit();" style="color: #fff; text-decoration: none; padding: 5px 15px;">
                                    <i class='glyphicon glyphicon-off'></i> {{ __('Deconectare') }}
                                </a>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
     
        <!-- मुख्य कंटेंट क्षेत्र -->
        <!--<div class="container-fluid" style="margin-top: 70px;">-->
            @yield('content')
        <!--</div>-->
      
        <!-- फुटर -->
        <div class="navbar navbar-inverse navbar-fixed-bottom">
            <div class="container-fluid">
                <span class="navbar-text pull-left">&copy; {{ date('Y') }} - Sistem comenzi.</span>
                <span class="navbar-text pull-right">   
                    <a href="#">
                        <i class='glyphicon glyphicon-save-file'></i> Backup
                    </a>
                </span>
            </div>
        </div>
        
        <script>
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // अतिरिक्त स्क्रिप्ट्स
            @yield('page_scripts')
        </script>
    </body>
</html>
