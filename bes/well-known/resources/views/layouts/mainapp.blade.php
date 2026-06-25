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
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
        <script src="{{ asset('js/foundation-datepicker.js') }}"></script>
		<script src="https://unpkg.com/map-fanbox-points@latest/umd/map-fanbox-points.js" crossorigin defer></script>
		<script src="https://cdn.sameday.ro/locker-plugin/lockerpluginsdk.js"></script>
		
		<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
		<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
		<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
        
        <!-- हेड स्क्रिप्ट्स -->
        @yield('head_scripts')
    </head>
    <body>
        @include('partials.navbar')
     
        <!-- main content area -->
        <div class="jumbotron">
            <div class="container-fluid">
                @yield('content')
            </div>
        </div>
      
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