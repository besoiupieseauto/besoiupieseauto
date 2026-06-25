{{-- resources/views/comenzi/index.blade.php --}}
@extends('layouts.mainapp')
@section('title', 'Comenzi Externe')

@section('additional_styles')
<style>
	.btn-paid {
		background-color: #555 !important; /* Dark grey */
		color: #fff !important; /* White text */
		border-color: #444 !important;
	}
	.btn-paid.active {
		background-color: #444 !important;
	}

	.btn-shipped {
		background-color: #d3d3d3 !important; /* Light grey */
		color: #000 !important; /* Black text for visibility */
		border-color: #ccc !important;
	}
	.btn-shipped.active {
		background-color: #c0c0c0 !important;
	}

    .datepicker {
        top: 200px !important;
        bottom: auto !important;
        transform: translateY(0) !important;
        margin-top: 4px;
    }

    /* Vertical alignment classes */
    .vert-align-center {
        vertical-align: middle !important;
        text-align: center;
    }

    .vert-align-center-fl {
        vertical-align: middle !important;
        text-align: center;
    }

    .vert-align-right {
        vertical-align: middle !important;
        text-align: right;
        padding-right: 15px !important;
    }

    /* Button color classes */
    .btn-maine {
        background-color: #7CFC00;
        color: #333;
    }

    .btn-poimaine {
        background-color: #ADD8E6;
        color: #333;
    }

    .btn-more3 {
        background-color: #FF0000;
        color: white;
    }

    /* Table styling */
    .table-light {
        border: 1px solid #ddd;
    }

    .table-light th {
        background-color: #f5f5f5;
        border: 1px solid #ddd;
    }

    .table-light td {
        border: 1px solid #ddd;
    }

    /* Label styles */
    .label-as-badge {
        border-radius: 1em;
        padding: 0.3em 0.6em;
    }
	.form-horizontal .form-group{
		margin-right:0 !important;
		margin-left:0 !important;
	}
	.drop-label {
		padding-top: 0 !important;
        margin-bottom: 0;
        text-align: right;
        margin-top: -19px;
	}
	#mod_culoare .funkyradio-default{
		margin-bottom: 10px;
		border-radius: 4px;
	}
	#mod_culoare .funkyradio-default label{
		padding: 8.2px;
	}
	#mod_culoare .funkyradio-default input{
		margin-right: 5px;
	}
	#frmeditare_culoare .btn-more3{
		color:#fff;
	}
	#frmeditare_culoare,  #frmeditare_culoare .form-group{
		margin-bottom:0px;
	}
	#frmeditare_culoare .funkyradio-default .btn-default{
		background-color:#9EA5AF !important;
		background-image:none !important;
		text-shadow:none !important;
	}
		.funkyradio-default .btn-default input{
		text-shadow:none !important;
	}	
	#mod_culoare .modal-title, #mod_furnizor .modal-title{
		font-size:16px !important;
	}
	#frmeditare_culoare .funkyradio-default label{
		font-size:14px !important;
	}
	.funkyradio .btn{
		border-radius:4px !important;
	}
	#frmeditare_furnizor .modal-body{
		padding:15px 0px 1px!important;
</style>
@endsection

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
@section('content')
<div class="panel panel-info">
    <div class="panel-heading">
        <div class="btn-group pull-right" style="gap:20px; display:flex;">
			@if (Auth::user()->hasPermission('pieseauto'))
				<a href="/pieseauto" class="btn btn-info">
					<span class="glyphicon glyphicon-plus"></span> Import comenzi pieseauto.ro
				</a>
			@endif
            <a href="{{ route('comenzi.create') }}" class="btn btn-info">
                <span class="glyphicon glyphicon-plus"></span> Comanda noua
            </a>
        </div>
        <h4><i class="glyphicon glyphicon-search"></i> Comenzi externe</h4>
    </div>
    <div class="panel-body comenzi-externe" style=" padding: 15px;">
        @include('comenzi.partials.modals')
        
        <form class="form-horizontal" role="form" id="date_cotizacion">
            <div class="form-group row-fluid" style=" margin-top: 0.5%;">
                <div class="col-sm-2 left-cal">
                    <!--<div class="input-group">
                        <span class="input-group-addon">
                            <a href="#">
                                <span class="glyphicon glyphicon-chevron-left" onclick="obtine_data(-1)"></span>
                            </a>
                        </span>
                        <input class="form-control" id="date" name="date" placeholder="DD/MM/YYYY" type="text" value="{{ $currentDate }}" onchange="load(1);" readonly>
                        <span class="input-group-addon">
                            <a href="#">
                                <span class="glyphicon glyphicon-chevron-right" onclick="obtine_data(1)"></span>
                            </a>
                        </span>
                    </div>-->
					
					<div class="input-group">
						<span class="input-group-addon">
							<a href="javascript:void(0);">
								<span class="glyphicon glyphicon-chevron-left" onclick="obtine_data('range_date', -1)"></span>
							</a>
						</span>
						<input type="text" class="form-control cstmdatepicker" id="range_date" name="range_date" placeholder="DD/MM/YYYY" value="{{ $currentDate ?? date('d/m/Y') }}" readonly/>
						<span class="input-group-addon">
							<a href="javascript:void(0);">
								<span class="glyphicon glyphicon-chevron-right" onclick="obtine_data('range_date', 1)"></span>
							</a>
						</span>
					</div>
					<!--<span class="cal-text">To </span>-->
                </div>
				
				<!--<div class="col-sm-2">
					<div class="input-group">
						<span class="input-group-addon">
							<a href="javascript:void(0);">
								<span class="glyphicon glyphicon-chevron-left" onclick="obtine_data('to_date', -1)"></span>
							</a>
						</span>
						<input type="text" class="form-control cstmdatepicker" id="to_date" name="to_date" placeholder="DD/MM/YYYY" value="" onchange="load(1);" readonly/>
						<span class="input-group-addon">
							<a href="javascript:void(0);">
								<span class="glyphicon glyphicon-chevron-right" onclick="obtine_data('to_date', 1)"></span>
							</a>
						</span>
					</div>
				</div>-->
				<div class="col-md-1">
					<div class="dropdown">
					  <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">
						Status <span class="caret"></span>
					  </button>
					  <ul class="dropdown-menu">
						<!-- First section -->
						<li class="dropdown-header">Status</li>
						<li>
						  <label class="checkbox">
							<input type="checkbox" class="status-checkbox" value="1"> Comandat
						  </label>
						</li>
						<li>
						  <label class="checkbox">
							<input type="checkbox" class="status-checkbox" value="2"> Sosit
						  </label>
						</li>
						<li>
						  <label class="checkbox">
							<input type="checkbox" class="status-checkbox" value="3"> Expediat
						  </label>
						</li>
						<li>
						  <label class="checkbox">
							<input type="checkbox" class="status-checkbox" value="4"> Achitat
						  </label>
						</li>
						<li>
						  <label class="checkbox">
							<input type="checkbox" class="status-checkbox" value="5"> Avans
						  </label>
						</li>
						<li>
						  <label class="checkbox">
							<input type="checkbox" class="status-checkbox" value="6"> Retur
						  </label>
						</li>
					  </ul>
					</div>
				</div>
				
                <label for="q" class="col-md-2 control-label">Cauta </label>
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" id="q" placeholder="Nume, telefon, marca, adresa, awb, cod" onkeyup="load(1);">
                        <span class="input-group-addon">
                            <a href="javascript:void(0);">
                                <span class="glyphicon glyphicon-search" onclick="load(1);"></span>
                            </a>
                        </span>
                    </div>
                </div>
                <div class="col-md-1" style="padding-right: 0;">
                    <div class="form-group length-dropdown">
                        <label class="drop-label control-label">Arată înregistrări</label>
                        <select class="form-control" id="per_page" onchange="load(1);">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-1">
                    <span id="loader"></span>
                </div>
            </div>
        </form>
        
        <div id="rezultat"></div><!-- Date ajax -->
        <div class="outer_div">
            @include('comenzi.partials.results')
        </div>
    </div>
</div>
<script>
	let datesChanged = false;
	let statusesChanged = false;
    $(document).ready(function() {
        // initialize datepicker if available
/*         if($.fn.fdatepicker) {
            $('.cstmdatepicker').fdatepicker({
                format: 'dd/mm/yyyy',
                language: 'ro',
            });
        } */
		
		$(function() {
		  $('input[name="range_date"]').daterangepicker({
			opens: 'left',
			ranges: {
			  'Această săptămână': [
				moment().startOf('week'),
				moment().endOf('week')
			  ],
			  'Săptămâna trecută': [
				moment().subtract(1, 'weeks').startOf('week'),
				moment().subtract(1, 'weeks').endOf('week')
			  ],
			  'Luna trecută': [
				moment().subtract(1, 'months').startOf('month'),
				moment().subtract(1, 'months').endOf('month')
			  ],
			  'Anul trecut': [
				moment().subtract(1, 'years').startOf('year'),
				moment().subtract(1, 'years').endOf('year')
			  ],
			  'Această lună': [
				moment().startOf('month'),
				moment().endOf('month')
			  ],
			  'Acest an': [
				moment().startOf('year'),
				moment().endOf('year')
			  ],
			  'Tot Istoricul': [
				moment().subtract(25, 'years').startOf('year'),
				moment().endOf('day')
			  ]
			}
		  }, function(start, end, label) {
			//console.log("A new date selection was made: " + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD'));
			datesChanged = true;
			load(1);
		  });
		});

        // set current date from session if available
        @if(session('fromDate'))
            $("#from_date").val("{{ session('fromDate') }}");
        @endif
		
        @if(session('toDate'))
            $("#to_date").val("{{ session('toDate') }}");
        @endif
        
        // if redirected from save/update, reload data
        @if(session('redirect_from_save'))
            console.log("Redirected from save/update");
            setTimeout(function() {
                load(1);
            }, 300);
        @else
            // load initial data
            load(1);
        @endif
        
        // date change event
/*         $('#from_date, #to_date').on('change', function() {
			datesChanged = true;
			let changedField = $(this).attr('id');
			if(changedField == "from_date"){
				$('#to_date').fdatepicker('show');
			}
            load(1);
        }); */


        // Check if we should reload the page to get fresh data
        const urlParams = new URLSearchParams(window.location.search);
        const cacheBuster = urlParams.get('cache_buster');
        
        if (cacheBuster) {
           console.log('Page loaded with cache buster:', cacheBuster);
            // Remove the parameter to avoid infinite refreshes
            urlParams.delete('cache_buster');
            
            // Use AJAX to refresh the results container
            const date = urlParams.get('date') || '{{ $currentDate }}';
            const search = urlParams.get('search_value') || '';
            
            $.ajax({
                url: '/comenzi/get-data',
                type: 'GET',
                data: {
                    date: date,
                    search_value: search,
                    cache_buster: new Date().getTime()
                },
                success: function(response) {
                    $('#results-container').html(response);
                }
            });
        }
		
		$('.status-checkbox').change(function() {
			statusesChanged = true;
			load(1);
		});
    });


    // AJAX functions for data loading
	let currentRequest = null;
    function load(page) {
        page = page || 1; // Default to page 1 if not specified

		if (currentRequest) {
			currentRequest.abort(); // Cancel previous request
		}
	
		var from_date = '';
		var to_date   = '';
		var drp = $('input[name="range_date"]').data('daterangepicker');
		if (drp) {
			from_date = drp.startDate.format('DD/MM/YYYY');
			to_date   = drp.endDate.format('DD/MM/YYYY');
		} else {
			var today = moment().format('DD/MM/YYYY');
			from_date = today;
			to_date   = today;
		}
        var searchValue = $("#q").val();
        var perPage = $('#per_page').val() || 50;
		
		let selectedStatuses = [];
		$('.status-checkbox:checked').each(function() {
			selectedStatuses.push($(this).val());
		});
		
		let isInitialLoad = !datesChanged && !statusesChanged && !$('#q').val().trim();
        
        $("#rezultat").html('<div class="text-center"><i class="glyphicon glyphicon-refresh fa-spin"></i> Se încarcă datele...</div>');
        
        currentRequest = $.ajax({
            url: "{{ route('comenzi.get-data') }}",
            type: "GET",
            data: {
                from_date: from_date,
                to_date: to_date,
                search_value: searchValue,
				filtered_statuses: selectedStatuses,
                page: page,
                per_page: perPage,
				is_initial_load: isInitialLoad ? 1 : 0
            },
            success: function(response) {
                $("#rezultat").html('');
                $(".outer_div").html(response);
            },
            error: function(xhr) {
                $("#rezultat").html('<div class="alert alert-danger">Eroare la încărcarea datelor!</div>');
            }
        });
    }

    // Function to load specific page
    function loadPage(page) {
        // Scroll to top for better UX
        $('html, body').animate({scrollTop: 0}, 300);
        load(page);
    }


    function obtine_data(inputId, direction) {
        //var currentDate = $('#' + inputId).val();
        //datesChanged = true;
		
/*         $.ajax({
            url: "{{ route('comenzi.get-date') }}",
            type: "GET",
            data: {
                current_date: newStart.format('MM/DD/YYYY'),
                direction: direction
            },
            dataType: "json",
            success: function(response) {
                $('#' + inputId).val(response.new_date);
                load(1);
            },
            error: function(xhr) {
                alert("Eroare la schimbarea datei!");
            }
        }); */
		
		var drp = $('input[name="range_date"]').data('daterangepicker');
		var newStart = drp.startDate.clone().add(direction, 'days');
		var newEnd = drp.endDate.clone().add(direction, 'days');

		// Update the picker
		drp.setStartDate(newStart);
		drp.setEndDate(newEnd);

		// Optionally update the input value if autoUpdateInput is false
		$('input[name="range_date"]').val(newStart.format('MM/DD/YYYY') + ' - ' + newEnd.format('MM/DD/YYYY'));
		
		datesChanged = true;
		load(1);
    }
</script>
@endsection