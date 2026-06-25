<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Comenzi Timisoara')</title>
	
	@php
		$theme = \App\Helpers\GlobalHelper::get_setting('theme', 'blue');
	@endphp
	<link rel="stylesheet" href="{{ asset('aftb-theme/' . $theme . '.css') }}">
		
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css">
   <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
  
    
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    


<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>


    
   
    
    <style>
        body { padding-top: 60px; }
        
        .totals-box {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 5px;
            margin: 5px;
            color: white;
            font-size: 18px;
        }
        .navbar-default .navbar-nav>.active>a, .navbar-default .navbar-nav>.open>a {
    background-color: var(--highlight-bg-color) !important;
    background-image: none !important;
    color: #455649 !important;
		}
        .total-zi {
            background-color: #5bc0de;
        }
		.navbar-default .navbar-nav>li>a, .navbar-default .navbar-brand {
    color: var(--btn-text-color) !important;
}
		.navbar{
		background-color: var(--box-color) !important;
    background-image: none !important;
}
       .form-group.sizes-fields{
		  margin:0 15px !important;
	   }
        .total-other {
            background-color: #5cb85c;
        }
		 .panel-info{
	 margin-top:25px !important;
 }
ul.nav.navbar-nav li a{
	display:flex;
	align-items:center;
	gap:0 !important;
	flex-flow:column;
	min-width:108px;
}
ul.nav.navbar-nav a i{
	margin-right:5px !important;
}
 .panel-info{
	 margin-top:25px !important;
 }

.navbar-right{
	height:40px !important;
}
.dataTables_length, .form-horizontal .length-dropdown{
	float:right;
}
.navbar-right{
	height:40px !important;
}
.dataTables_length, .form-horizontal .length-dropdown{
	float:right;
}
label.drop-label.control-label{
	font-size:12px !important;
}
.form-horizontal .length-dropdown{
	width:97px;
	}
.dataTables_length label{
    display: block;
    margin-top: -17px !important;
	width:97px;
	font-size:12px!important;
	text-align:left;
	}
	.dataTables_length .form-control {
		height:33.99px !important;
	}
#themeForm{
	margin-bottom:0px !important;
	margin-top:8px;
}
.navbar-brand{
	margin-top:8px;
}
.navbar-fixed-bottom, .navbar-fixed-top, .navbar-static-top, #navbar{
	height:64px !important;
}
#navbar{
	    background-color: transparent!important;
    background-image: none !important;
}
.navbar-right .form-control {
    height: 38px !important;
    background-color: var(--highlight-bg-color) !important;
    background-image: none !important;
    border-color: var(--highlight-bg-color) !important;
    box-shadow: none !important;
}
.custom-search{
	margin-left:0 !important;
}
.navbar-default .navbar-nav>.active>a, .navbar-default .navbar-nav>.open>a {
    background-color: var(--highlight-bg-color) !important;
    background-image: none !important;
    color: #455649 !important;
}
.navbar-right li {
    margin-right: 15px !important;
    margin-top: 6px !important;
}
    </style>
</head>
<body>
    @include('partials.navbar')
    
    
    
</body>
</html>