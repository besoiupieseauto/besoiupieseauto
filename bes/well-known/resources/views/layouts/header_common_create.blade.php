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

        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" />
            
        <!-- jQuery UI CSS -->
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css" />

		@php
			$theme = \App\Helpers\GlobalHelper::get_setting('theme', 'blue');
		@endphp
		<link rel="stylesheet" href="{{ asset('aftb-theme/' . $theme . '.css') }}">
		
        <!-- custom css -->
        <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
        <link rel="stylesheet" href="{{ asset('css/datepicker.css') }}" />
        <link rel="stylesheet" href="{{ asset('css/foundation-datepicker.css') }}" />
        <link rel="stylesheet" href="{{ asset('css/glDatePicker.default.css') }}">
        @yield('head')

        <!-- JavaScript Libraries -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.1/jquery.min.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
        <script src="{{ asset('js/foundation-datepicker.js') }}"></script>
        <script src="{{ asset('js/foundation-datepicker.ro.js') }}"></script>
    </head>
    <body>
        @include('partials.navbar')
    
        <!-- main content area -->
        <div class="jumbotron">
            <div class="container-fluid">
                @yield('content')
            </div>
        </div>


        <!-- footer -->
        @include('partials.footer')
    
        <script>
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
        </script>
        <!-- additional scripts -->
        @yield('page_scripts')
    </body>
</html>