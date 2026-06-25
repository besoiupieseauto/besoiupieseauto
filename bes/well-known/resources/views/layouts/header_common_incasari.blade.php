<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Comenzi Timisoara')</title>
        <!-- fevicon -->
        <link rel="icon" href="{{ asset('image/rtf.ico') }}" sizes="32x32" type="image/ico">
         
		@php
			$theme = \App\Helpers\GlobalHelper::get_setting('theme', 'blue');
		@endphp
		<link rel="stylesheet" href="{{ asset('aftb-theme/' . $theme . '.css') }}">		 
		 
        <!-- jQuery UI, Bootstrap, custom CSS-->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css">
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
        <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
        <link rel="stylesheet" href="{{ asset('css/datepicker.css') }}" />
        <link rel="stylesheet" href="{{ asset('css/foundation-datepicker.css') }}">

        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">

        <!-- Scripts -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
        <script src="{{ asset('js/foundation-datepicker.js') }}"></script>
        <script src="{{ asset('js/foundation-datepicker.ro.js') }}"></script>  
        <style>
            .totals-box {
                display: inline-block;
                padding: 8px 15px;
                border-radius: 5px;
                margin: 5px;
                color: white;
                font-size: 18px;
            }
            
            .total-zi {
                background-color: #5bc0de;
            }
            
            .total-other {
                background-color: #5cb85c;
            }
        </style>
    </head>
    <body>
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
                                    <i class="glyphicon glyphicon-log-out"></i> {{ __('Deconectare') }}
                                </a>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- main content area -->
        <div class="jumbotron">
            @yield('content')
        </div>

        <!-- footer -->
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

            // additional scripts
            @yield('page_scripts')
        </script>
    </body>
</html>
