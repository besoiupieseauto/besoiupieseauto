@extends('layouts.header_index_order')
@section('title', 'Comenzi Timisoara UTVIN')
@section('head')
<style>
    .datepicker {
        top: 165px !important;
        bottom: auto !important;
        transform: translateY(0) !important;
        margin-top: 4px;
    }
    
    /* Table styling 14*/
    #orders-table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 1rem;
        background-color: transparent;
    }

    #orders-table th {
        vertical-align: bottom;
        text-align: center;
        /*border-bottom: 2px solid #dee2e6;*/
        padding: 0.75rem;
        vertical-align: top;
        /*border-top: 1px solid #dee2e6;*/
        border: 1px solid #ddd;
    }

    #orders-table td {
        padding: 0 10px !important;
        vertical-align: middle;
        text-align: center;
        border: 1px solid #ddd;
    }

    #orders-table td.produs, #orders-table td.cod, #orders-table td.furnizor, #orders-table td.cantitate, #orders-table td.pret  {
        padding: 0 0 !important;
        vertical-align: middle;
        text-align: center;
        border: 1px solid #ddd;
    }

    /* Fix vertical align in cells */
    #orders-table td > div {
        min-height: 36px;
    }

    /* Remove last border bottom from last item */
    #orders-table td > div > div:last-child {
        border-bottom: none !important;
    }

    /* Better hover effect */
    #orders-table tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }

    /* Status button colors */
    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }

    .btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #212529;
    }

    .btn-info {
        background-color: #17a2b8;
        border-color: #17a2b8;
        color: white;
    }

    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
        color: white;
    }

    .btn-secondary {
        background-color: #6c757d;
        border-color: #6c757d;
        color: white;
    }

    /* Force white text for ALL buttons in all states */
    #search-btn, #search-btn:hover, #search-btn:focus, #search-btn:active, #search-btn.active {
        color: #337ab7 !important;
    }

    /* Smaller buttons */
    .btn-xs {
        padding: .2rem .4rem;
        font-size: .875rem;
        line-height: 1.5;
        border-radius: .2rem;
        margin-bottom: 2px;
    }

    /* Total values styling */
    .total-wrapper {
        padding: 10px;
        font-weight: bold;
        font-size: 16px;
    }

    .total-luna {
        margin-right: 20px;
    }

    /* Spinner styling */
    .spinner {
        border: 4px solid rgba(0, 0, 0, 0.1);
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border-top: 4px solid #007bff;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .hidden {
        display: none;
    }

    /* Improved pointer styling */
    .pointer {
        cursor: pointer;
    }

    /* Total boxes styling to match your current UI */
    .total-wrapper {
        margin-top: 10px;
        margin-bottom: 10px;
    }

    .total-luna, .total-zi, .total-cautare {
        display: inline-block;
        padding: 8px 15px;
        border-radius: 4px;
        font-weight: bold;
        color: white;
        margin-right: 10px;
    }

    .total-luna {
        background-color: #5bc0de;
    }

    .total-zi, .total-cautare {
        background-color: #5cb85c !important;
    }

    /* Pagination styling */
    .pagination-container {
        margin-top: 10px;
    }

    .pagination-wrapper {
        display: inline-block;
    }

    .pagination-prev, .pagination-next, .pagination-page {
        display: inline-block;
        padding: 6px 12px;
        margin-left: -1px;
        line-height: 1.42857143;
        color: #337ab7;
        text-decoration: none;
        background-color: #fff;
        border: 1px solid #ddd;
    }

    .pagination-page.active {
        background-color: #337ab7;
        color: white;
        border-color: #337ab7;
    }

    /* Spinner */
    .spinner {
        display: inline-block;
        border: 3px solid rgba(0,0,0,0.1);
        border-radius: 50%;
        border-top: 3px solid #007bff;
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .hidden {
        display: none;
    }

    .pointer {
        cursor: pointer;
    }

    /* Table styling */
    .table > thead > tr > th {
        vertical-align: middle;
    }

    /* Status button styles */
    .btn-comandat {
        background-color: #f0ad4e;
        border-color: #eea236;
    }

    .btn-sosit {
        background-color:#9EA5AF;
        border-color: #46b8da;
    }

    /* Action buttons styling */
    .btn-group-vertical {
        width: 100%;
    }

    .btn-group-vertical .btn {
        margin-bottom: 3px;
    }

    /* Row colors */
    /* Fix for Retur status */
    .table-striped > tbody > tr.status-retur,
    .table-striped > tbody > tr.status-retur:nth-of-type(odd),
    .table-striped > tbody > tr.status-retur:nth-of-type(even) {
        background-color: #6D7177 !important;
        color: black !important;
    }
	
	#orders-table_wrapper .table-striped > tbody > tr.avans-card.odd,
	#orders-table_wrapper .table-striped > tbody > tr.avans-card.even,
	
	#orders-table_wrapper .table-striped > tbody > tr.avans-cash.odd,
	#orders-table_wrapper .table-striped > tbody > tr.avans-cash.even,
	
	#orders-table_wrapper .table-striped > tbody > tr.avans-fd.odd,
	#orders-table_wrapper .table-striped > tbody > tr.avans-fd.even,
	
	#orders-table_wrapper .table-striped > tbody > tr.avans-op.odd,
	#orders-table_wrapper .table-striped > tbody > tr.avans-op.even {
		background-color: #6D7177 !important;
		color: black !important;
	}
	
    /* Row colors */
    /* Fix for status-anulat */
    .table-striped > tbody > tr.status-anulat,
    .table-striped > tbody > tr.status-anulat:nth-of-type(odd),
    .table-striped > tbody > tr.status-anulat:nth-of-type(even) {
        background-color: #6D7177 !important;
        color: black !important;
    }
	
    /* Row colors */
    /* Fix for status-op */
    .table-striped > tbody > tr.status-op,
    .table-striped > tbody > tr.status-op:nth-of-type(odd),
    .table-striped > tbody > tr.status-op:nth-of-type(even) {
        background-color: #6D7177 !important;
        color: black !important;
    }
	
	table#orders-table tr.status-op{
		 background-color: #6D7177 !important;
        color: black !important;
	}

    /* Add separation line between rows */
    .table-striped > tbody > tr {
        border-bottom: 1px solid #f0f0f0;
    }

    /* Different color for separation based on row background */
    .table-striped > tbody > tr.status-comandat {
        border-bottom: 1px solid #e0e0e0;
    background-color: white !important;
    }

    .table-striped > tbody > tr.status-sosit {
        border-bottom: 1px solid #c5d9e8;
    }

    .table-striped > tbody > tr.status-cash,
    .table-striped > tbody > tr.status-card,
    .table-striped > tbody > tr.status-fd {
        border-bottom: 1px solid #c5e8c5;
    }

    .table-striped > tbody > tr.status-retur {
        border-bottom: 1px solid #8d8d8d;
    }

    /* Make sure the border is visible */
    .table-striped > tbody > tr > td {
        padding-top: 6px;
        padding-bottom: 6px;
    }
    /* Add thin divider lines between product rows */
    .table > tbody > tr {
        border-bottom: 1px solid rgba(210, 210, 210, 0.5) !important;
    }

    /* For darker backgrounds, use a lighter line */
    .table > tbody > tr.status-retur {
        border-bottom: 1px solid rgba(255, 255, 255, 0.3) !important;
    }

    /* Enhance visibility of borders for rows with color backgrounds */
    .table > tbody > tr.status-comandat {
        border-bottom: 1px solid rgba(240, 173, 78, 0.4) !important;
    }

    .table > tbody > tr.status-sosit {
        border-bottom: 1px solid rgba(91, 192, 222, 0.4) !important;
    }

    .table > tbody > tr.status-cash,
    .table > tbody > tr.status-card,
    .table > tbody > tr.status-fd {
        border-bottom: 1px solid rgba(92, 184, 92, 0.4) !important;
    }


    /* Target all rows except the first one */
    #orders-table > tbody > tr {
        border-bottom: 5px solid #000 !important;
    }
    #orders-table > tbody > tr > td > .btn {
        padding: 6px 12px !important;
        font-size: 14px; !important;
    }
    /* Add some vertical padding to cells to make the lines more visible */
    .table > tbody > tr > td {
        padding-top: 8px !important;
        padding-bottom: 8px !important;
    }

    /* Add a more visible separator between product rows */
    .table > tbody > tr td {
        position: relative;
    }

    /* Add separator line using pseudo-element for better visibility */
    .table > tbody > tr:not(:last-child) td:after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 1px;
        background-color: rgba(0, 0, 0, 0.1); /* Dark line for light backgrounds */
    }

    /* For rows with darker backgrounds, use a lighter line color */
    .table > tbody > tr.status-retur:not(:last-child) td:after {
        background-color: rgba(255, 255, 255, 0.25); /* Light line for dark backgrounds */
    }

    /* Add slightly more spacing between rows */
    .table > tbody > tr td {
        padding-top: 8px !important;
        padding-bottom: 8px !important;
    }

    #orders-table > tbody > tr > td > a {
        color: #000000 !important;
    }

    #orders-table > tbody > tr > td > a:hover {
        color: #000000 !important;
        text-decoration: underline !important;
    }

    #orders-table > tbody > tr > td > div> div > a {
        color: #000;
    }

    #orders-table > tbody > tr > td > div> div > a:hover {
        color: #000000 !important;
        text-decoration: underline !important;
    }

    #orders-table > tbody > tr > td > a.btn {
        color: #fff !important;
    }

    #orders-table > tbody > tr > td > a.btn:hover {
        color: #fff !important;
        text-decoration: none !important;
    }

    a[onclick*="obtineCuloare"] {
        transition: all 0.3s ease;
        border-radius: none !important;
        padding: 3px 6px;
        display: inline-block;
        text-decoration: none;
    }

    a[onclick*="obtineCuloare"]:hover {
        opacity: 0.9;
        text-decoration: none;
    }

    /* Target all icon buttons in the last column specifically */
    .table-responsive .table td:last-child .btn,
    .table-responsive .table td:last-child a[class*="btn"],
    .table-responsive .table td:last-child a i.glyphicon,
    .table-responsive .table td:last-child button {
        width: 32px !important;
        height: 32px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 2px !important;
        border-radius: 4px !important;
        color:black;
    }

    /* Ensure the icons inside are centered */
    .table-responsive .table td:last-child .btn i,
    .table-responsive .table td:last-child a i,
    .table-responsive .table td:last-child button i {
        margin: 0 auto !important;
        line-height: 1 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    /* These specific selectors should catch those particular icons */
    a[class*="btn"] i.glyphicon-trash,
    button i.glyphicon-trash,
    a i.glyphicon-trash,
    a[class*="btn"] i.glyphicon-user,
    button i.glyphicon-user,
    a i.glyphicon-user,
    a[class*="btn"] i.glyphicon-file,
    button i.glyphicon-file,
    a i.glyphicon-file {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 100% !important;
        height: 100% !important;
    }

    /* Target the parent elements of these icons */
    a[class*="btn"] i.glyphicon-trash,
    a[class*="btn"] i.glyphicon-user,
    a[class*="btn"] i.glyphicon-file,
    button i.glyphicon-trash,
    button i.glyphicon-user,
    button i.glyphicon-file {
        width: 32px !important;
        height: 32px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .dataTables_wrapper .dataTables_paginate,
    .dataTables_wrapper .dataTables_info {
        text-align: right !important;
        float: right !important;
    }
</style>
@endsection

@section('content')
    <!-- Main Content -->
    <div class="container-fluid">
        @include('orders.modals.status')
        @include('orders.modals.color')
        @include('orders.modals.total')
        @include('orders.modals.sms')
        @include('orders.modals.supplier')
        @include('orders.modals.location')
        
        <div class="panel panel-info">
            <div class="panel-heading">
                <div class="pull-right">
                    <a href="{{ route('orders.create', ['type' => 'utvin']) }}" class="btn btn-success">
                        <i class="glyphicon glyphicon-plus"></i> Comanda noua
                    </a>
                </div>
                <h4><i class="glyphicon glyphicon-search"></i> Comenzi Timisoara</h4>
            </div>
            <div class="panel-body">
                <div class="row" style="margin-top: 0.3%;">
                    <div class="col-sm-2 left-cal">
                        <div class="input-group">
                            <span class="input-group-addon">
                                <a href="javascript:void(0);">
                                    <span class="glyphicon glyphicon-chevron-left" onclick="changeDate('range_date', -1)"></span>
                                </a>
                            </span>
                            <input type="text" class="form-control cstmdatepicker" id="range_date" name="range_date" placeholder="DD/MM/YYYY" value="{{ $currentDate ?? date('d/m/Y') }}" readonly/>
                            <span class="input-group-addon">
                                <a href="javascript:void(0);">
                                    <span class="glyphicon glyphicon-chevron-right" onclick="changeDate('range_date', 1)"></span>
                                </a>
                            </span>
                        </div>
						<!--<span class="cal-text">To </span>-->
                    </div>
					
					<!--<div class="col-sm-2">
						<div class="input-group">
                            <span class="input-group-addon">
                                <a href="javascript:void(0);">
                                    <span class="glyphicon glyphicon-chevron-left" onclick="changeDate('to_date', -1)"></span>
                                </a>
                            </span>
                            <input type="text" class="form-control cstmdatepicker" id="to_date" name="to_date" placeholder="DD/MM/YYYY" value="" readonly/>
                            <span class="input-group-addon">
                                <a href="javascript:void(0);">
                                    <span class="glyphicon glyphicon-chevron-right" onclick="changeDate('to_date', 1)"></span>
                                </a>
                            </span>
                        </div>
                    </div>-->
					<div class="col-md-2">
						<div class="dropdown">
						  <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">
							Status <span class="caret"></span>
						  </button>
						  <ul class="dropdown-menu">
							<!-- First section -->
							<li class="dropdown-header">Status comanda</li>
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
								<input type="checkbox" class="status-checkbox" value="8"> Anulat
							  </label>
							</li>
							<li>
							  <label class="checkbox">
								<input type="checkbox" class="status-checkbox" value="5"> Retur
							  </label>
							</li>

							<!-- Second section -->
							<li class="dropdown-header">Incasare comanda</li>
							<li>
							  <label class="checkbox">
								<input type="checkbox" class="status-checkbox" value="2"> Cash
							  </label>
							</li>
							<li>
							  <label class="checkbox">
								<input type="checkbox" class="status-checkbox" value="6"> Card
							  </label>
							</li>
							<li>
							  <label class="checkbox">
								<input type="checkbox" class="status-checkbox" value="7"> FD
							  </label>
							</li>
							<li>
							  <label class="checkbox">
								<input type="checkbox" class="status-checkbox" value="4"> Avans
							  </label>
							</li>
							<li>
							  <label class="checkbox">
								<input type="checkbox" class="status-checkbox" value="9"> OP
							  </label>
							</li>
						  </ul>
						</div>
					</div>					
					
                    <label for="q" class="col-md-1 control-label" style="padding-left: 6%; padding-top: 5px;">Cauta </label>
                    <div class="col-md-4">
                        <div class="input-group" id="search-container">
                            <input type="text" class="form-control" id="search" name="search"
                                placeholder="Nume, telefon, marca, adresa, cod">
                            <span class="input-group-btn">
                                <span class="btn btn-default" id="search-btn">
                                    <i class="glyphicon glyphicon-search"></i>
                                </span>
                            </span>
                        </div>
						
                    </div>
					 <div class="col-md-1">
					 <div class="length-control">
                            <!-- DataTables length control will be moved here via JavaScript -->
                        </div>
						</div>
                    <div class="col-sm-5">
                        <div id="loader" class="spinner hidden"></div>
                    </div>
                </div>
                <br>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="orders-table" >
                        <thead>
                            <tr class="info">
                                <th>Data</th>
                                <th>Client</th>
                                <!--<th>Telefon</th>-->
                                <th>Marca</th>
                                <!--<th>Magazin</th>-->
                                <th>Produs</th>
                                <th>Cod</th>
                                <th>Furnizor</th>
                                <th>Cant.</th>
                                <th>Pret</th>
                                <th>Total</th>
                                <th>Status</th>
								<th>Notita</th>
                                <th>Actiune</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Totals and Pagination Section -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="total-wrapper">
                            <span class="total-luna">Total luna: <span id="monthly-total">0</span></span>
                            <span class="total-zi">Total zi: <span id="daily-total">0</span></span>
                            <span class="total-cautare">Total cautare: <span id="cautare-total">0</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_scripts')
<script>
	let datesChanged = false;
	let statusesChanged = false;
    $(document).ready(function() {
        // initialize datepicker with Romanian locale
/*         if($.fn.fdatepicker) {
            $('.cstmdatepicker').fdatepicker({
                format: 'dd/mm/yyyy',
                language: 'ro'
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
			$('#loader').removeClass('hidden');
			ordersTable.ajax.reload(function() {
				$('#loader').addClass('hidden');
			});
		  });
		});

        // When date changes manually
/* 		$('#from_date, #to_date').change(function() {
			datesChanged = true;
			let changedField = $(this).attr('id');
			if(changedField == "from_date"){
				$('#to_date').fdatepicker('show');
			}
			
			$('#loader').removeClass('hidden');
			ordersTable.ajax.reload(function() {
				$('#loader').addClass('hidden');
			});
		}); */
		
		$('.status-checkbox').change(function() {
			statusesChanged = true;
			$('#loader').removeClass('hidden');
			ordersTable.ajax.reload(function() {
				$('#loader').addClass('hidden');
			});
		});

        // datatable initialization
        let ordersTable = $('#orders-table').DataTable({
            processing: true,
            serverSide: true,
            searching: false,
            paging: true,
            lengthChange: true,
            pageLength: 50,
			lengthMenu: [10, 25, 50, 100, 500],
            ordering: false,
            info: false,
            dom: 'l t<"bottom"p>',
            pagingType: 'simple_numbers',
            ajax: {
                url: "{{ route('orders.data') }}",
                data: function(d) {
					var drp = $('input[name="range_date"]').data('daterangepicker');
					if (drp) {
						d.from_date = drp.startDate.format('DD/MM/YYYY');
						d.to_date   = drp.endDate.format('DD/MM/YYYY');
					} else {
						var today = moment().format('DD/MM/YYYY');
						d.from_date = today;
						d.to_date   = today;
					}
                    d.search = $('#search').val();
					d.locatie_magazin = 2;
					
					let selectedStatuses = [];
					$('.status-checkbox:checked').each(function() {
						selectedStatuses.push($(this).val());
					});
					d.filtered_statuses = selectedStatuses;
					
					let isInitialLoad = !datesChanged && !statusesChanged && !$('#search').val().trim(); 
					d.is_initial_load = isInitialLoad ? 1 : 0;
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTables error:', error);
                    console.error('Server response:', xhr.responseJSON);
                    alert('Error loading data. See console for details.');
                    $('#loader').addClass('hidden');
                }
            },
            columns: [
                { data: 'data', name: 'data' },
                { data: 'client', name: 'client' },
                //{ data: 'telefon', name: 'telefon' },
                { data: 'marca', name: 'marca' },
                //{ data: 'magazin', name: 'magazin' },
                { data: 'produs', name: 'produs' },
                { data: 'cod', name: 'cod' },
                { data: 'furnizor', name: 'furnizor' },
                { data: 'cantitate', name: 'cantitate' },
                { data: 'pret', name: 'pret' },
                { data: 'total', name: 'total'},
                { data: 'status', name: 'status' },
				{ data: 'observations', name: 'observations' },
                { data: 'actiune', name: 'actiune', orderable: false, searchable: false }
            ],
            createdRow: function(row, data, dataIndex) {
                let statusClass = '';
                
                if (data.stare == 1) {
                    statusClass = 'status-comandat';
                } else if (data.stare == 2) {
                    statusClass = 'status-sosit';
                } else if ([3, 6, 7].includes(parseInt(data.stare))) {
                    statusClass = 'status-cash';
                } else if (data.stare == 4) {
                    statusClass = 'status-avans';
                } else if (data.stare == 5) {  // Add this condition for Retur
                    statusClass = 'status-retur';
                } else if (data.stare == 8) {
					statusClass = 'status-anulat';
				} else if (data.stare == 9) {
					statusClass = 'status-op';
				} else if (data.stare == 10 && data.last_old_status == 2) {
					statusClass = 'avans-fd';
				} else if (data.stare == 11 && data.last_old_status == 2) {
					statusClass = 'avans-cash';
				} else if (data.stare == 12 && data.last_old_status == 2) {
					statusClass = 'avans-card';
				} else if (data.stare == 13 && data.last_old_status == 2) {
					statusClass = 'avans-op';
				}
        
                $(row).addClass(statusClass);

                //back ground color and text color solution for COD column
                $.each(data, function(index, value) {
                    if(index == 'produs') {
                        $('td', row).eq(3).attr('style', 'vertical-align: top;');
                        $('td', row).eq(3).attr('class', 'produs');
                    }
                    else if(index == 'cod') {
                        // Parse the HTML string
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(value, 'text/html');

                        // Find the div by id
                        const divElement = doc.getElementById('codDiv');

                        // Retrieve the data-value attribute
                        if (divElement) {
                            const attributeValue = divElement.getAttribute('data-value');

                            if(attributeValue == 1) {
                                const background = divElement.getAttribute('background-color');
                                const color = divElement.getAttribute('color');

                                $('td', row).eq(4).attr('style', 'background-color: ' + background + ' !important; color: ' + color + ' !important;vertical-align: top;');
                            }
                            else {
                                $('td', row).eq(4).attr('style', 'vertical-align: top;');
                            }
                        }
                        else {
                            // If the div is not found, set a default style
                            $('td', row).eq(4).attr('style', 'vertical-align: top;');
                        }
                        $('td', row).eq(4).attr('class', 'cod');
                    }
                    else if(index == 'furnizor') {
                        $('td', row).eq(5).attr('class', 'furnizor');
                    }
                    else if(index == 'cantitate') {
                        $('td', row).eq(6).attr('class', 'cantitate');
                    }
                    else if(index == 'pret') {
                        $('td', row).eq(7).attr('class', 'pret');
                    }
                });
            },
            drawCallback: function(settings) {
                if (settings.json) {
                   $('#monthly-total').text(
                        parseFloat(settings.json.monthlyTotal).toLocaleString('ro-RO', {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        })
                    );
                    
                    $('#daily-total').text(
                        parseFloat(settings.json.dailyTotal).toLocaleString('ro-RO', {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        })
                    );
					
					$('#cautare-total').text(
                        parseFloat(settings.json.filteredTotal).toLocaleString('ro-RO', {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        })
                    );
                }
                
                updateCustomPagination(settings);
            },
            language: {
                processing: '<div class="spinner">Processing...</div>',
                emptyTable: "No data available",
				lengthMenu: "Arată înregistrări _MENU_",
                zeroRecords: "No matching records found",
                paginate: {
                    previous: "&lt; Prev",
                    next: "Next &gt;"
                }
            }
        });
		
		
		
		function escapeRegex(text) {
			return text.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
		}

		function highlightText(element, searchTerm, regex, wrapper) {
			element.contents().each(function() {
				if (this.nodeType === 3) { // text node
					let text = this.nodeValue;
					if (regex.test(text)) {
						let highlighted = text.replace(regex, wrapper);
						$(this).replaceWith(highlighted);
					}
				} else {
					highlightText($(this), searchTerm, regex, wrapper); // recurse into child elements
				}
			});
		}

		ordersTable.on('draw', function () {
			let searchTerm = $('#search').val().trim();
			if (searchTerm.length > 0) {
				let safeTerm = escapeRegex(searchTerm);
				let regex = new RegExp("(" + safeTerm + ")", "gi");

				// Highlight all cells (red bold)
				$('#orders-table tbody td').each(function () {
					highlightText($(this), searchTerm, regex, '<span style="color:red;font-weight:bold;">$1</span>');
				});

				// Special highlight for COD column (yellow mark)
				$('#orders-table tbody tr').each(function () {
					let codCell = $(this).find('td').eq(5);
					if (codCell.length > 0) {
						highlightText(codCell, searchTerm, regex, '<mark style="background-color:#ffff99;color:#000;">$1</mark>');
					}
				});
			}
		});


		
		// Move the length control to our custom position
        $('.dataTables_length').detach().appendTo('.length-control');


        // make variable available for global scope
        window.ordersTable = ordersTable;


        // search button handler
        $('#search-btn').on('click', function() {
            $('#loader').removeClass('hidden');
            ordersTable.ajax.reload(function() {
                $('#loader').addClass('hidden');
            });
        });


        let searchTimeout;
        $('#search').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                $('#loader').removeClass('hidden');
                ordersTable.ajax.reload(function() {
                    $('#loader').addClass('hidden');
                });
            }, 500);
        });


        $('#search').on('keypress', function(e) {
            if (e.keyCode === 13) {
                clearTimeout(searchTimeout);
                $('#loader').removeClass('hidden');
                ordersTable.ajax.reload(function() {
                    $('#loader').addClass('hidden');
                });
            }
        });


        // pagination handlers
        $(document).on('click', '.pagination-prev', function(e) {
            e.preventDefault();
            ordersTable.page('previous').draw('page');
        });


        $(document).on('click', '.pagination-next', function(e) {
            e.preventDefault();
            ordersTable.page('next').draw('page');
        });


        $(document).on('click', '.pagination-page', function(e) {
            e.preventDefault();
            var page = parseInt($(this).text()) - 1;
            ordersTable.page(page).draw('page');
        });


        // tootip initialization
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }


        // Form submission handler for location update
        $('#frmeditare_adresa').on('submit', function(e) {
            e.preventDefault();
            
            // Get order ID using correct ID
            var orderId = $('#mod_id_cmd_adr').val();
            var selectedLocation = $('input[name="location"]:checked').val();
            
            console.log("Form submission - Order ID:", orderId, "Location:", selectedLocation);
            
            if (!selectedLocation) {
                $('#rezultat_ajax_adr').html('<div class="alert alert-danger">Please select a location!</div>');
                return;
            }
            
            $('#rezultat_ajax_adr').html('<div class="alert alert-info">Updating...</div>');
            
            $.ajax({
                url: '/orders/update-location',
                type: 'POST',
                data: {
                    order_id: orderId,
                    location: selectedLocation,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    console.log("AJAX success response:", response);
                    if (response.success) {
                        $('#rezultat_ajax_adr').html('<div class="alert alert-success">Locația a fost actualizată cu succes!</div>');

                        $('#mod_adresa').modal('hide');
                        window.ordersTable.ajax.reload(null, false);
                    }
                    else {
                        $('#rezultat_ajax_adr').html('<div class="alert alert-danger">' + (response.message || 'Update error!') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Location update error:', error);
                    $('#rezultat_ajax_adr').html('<div class="alert alert-danger">Update error!</div>');
                }
            });
        });


        //status update form handler
        $('#frmeditare_status').on('submit', function(e) {
            e.preventDefault();
            
            var formData = {
                order_id: $('#mod_id_cmd').val(),
                stare: $('input[name="stare"]:checked').val()
            };
            
            // console.log("Form data:", formData); // for debugging
            
            if (!formData.stare) {
                $('#rezultat_ajax_status').html('<div class="alert alert-danger">Vă rugăm să selectați starea!</div>');
                return;
            }

            $('#rezultat_ajax_status').html('<div class="alert alert-info">Se actualizează...</div>');

            $.ajax({
                url: '/orders/update-status',
                type: 'POST',
                data: formData,
                success: function(response) {
                    // console.log("AJAX response:", response); // for debugging
                    
                    if (response.success) {
                        $('#rezultat_ajax_status').html('<div class="alert alert-success">Starea a fost actualizată cu succes!</div>');

                        ordersTable.ajax.reload(null, false);
                        $('#mod_status').modal('hide');
                    }
                    else {
                        $('#rezultat_ajax_status').html('<div class="alert alert-danger">Eroare: ' + response.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    // console.error('Eroare la actualizarea stării:', xhr, status, error); // Pentru depanare
        
                    var errorMessage = 'A apărut o eroare la actualizarea stării!';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    $('#rezultat_ajax_status').html('<div class="alert alert-danger">Eroare: ' + errorMessage + '</div>');
                }
            });
        });


        // updated color form handler and removed the duplicate code
        //$('#frmeditare_culoare').on('submit', function(e) {
		$(document).on('change', 'input[name="color"]', function (e) {
            e.preventDefault();
            
            // Get form data
            var orderId = $('#mod_id_cmd_culoare').val();
            var productId = $('#mod_id_prod_culoare').val();
            var selectedColor = $('input[name="color"]:checked').val();
            
            // Validate input
            if (!selectedColor) {
                $('#rezultat_ajax_culoare').html('<div class="alert alert-danger">Vă rugăm să selectați o culoare!</div>');
                return;
            }

            // Show loading message
            $('#rezultat_ajax_culoare').html('<div class="alert alert-info">Se actualizează...</div>');

            // Prepare data
            var formData = {
                order_id: orderId,
                product_id: productId,
                color: selectedColor
            };
            
            // AJAX call to update color
            $.ajax({
                url: '/orders/update-color',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Update successful
                        $('#rezultat_ajax_culoare').html('<div class="alert alert-success">Culoarea a fost actualizată cu succes!</div>');

                        //hide modal and reload table without delay
                        $('#mod_culoare').modal('hide');
                        window.ordersTable.ajax.reload(null, false);
                    }
                    else {
                        // Error in update
                        $('#rezultat_ajax_culoare').html('<div class="alert alert-danger">' + (response.message || 'Eroare la actualizare!') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#rezultat_ajax_culoare').html('<div class="alert alert-danger">Eroare la actualizare!</div>');
                }
            });
        });


        //fixed supplier form handler
        //$('#frmeditare_furnizor').on('submit', function(e) {
		$(document).on('change', 'input[name="supplier"]', function (e) {
            e.preventDefault();
            
            // Get form data
            var orderId = $('#mod_id_cmd_fur').val();
            var productId = $('#mod_id_prod_fur').val();
            var selectedSupplier = $('input[name="supplier"]:checked').val();
            
            // Validate input
            if (!selectedSupplier) {
                $('#rezultat_ajax_fur').html('<div class="alert alert-danger">Vă rugăm să selectați un furnizor!</div>');
                return;
            }
            
            // Show loading message
            $('#rezultat_ajax_fur').html('<div class="alert alert-info">Se actualizează...</div>');

            
            // AJAX call to update supplier
            $.ajax({
                url: '/orders/update-supplier',
                type: 'POST',
                data: {
                    order_id: orderId,
                    product_id: productId,
                    supplier: selectedSupplier
                },
                success: function(response) {
                    if (response.success) {
                        // Update successful
                        $('#rezultat_ajax_fur').html('<div class="alert alert-success">Furnizorul a fost actualizat cu succes!</div>');
                        
                        // Hide modal
                        $('#mod_furnizor').modal('hide');
                        
                        // Reload DataTable to show updated data
                        window.ordersTable.ajax.reload(null, false);
                    }
                    else {
                        // Error in update
                        $('#rezultat_ajax_fur').html('<div class="alert alert-danger">' + (response.message || 'अपडेट में त्रुटि!') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Supplier update error:', error);
                    $('#rezultat_ajax_fur').html('<div class="alert alert-danger">अपडेट में त्रुटि!</div>');
                }
            });
        });


        $('#frmeditare_total').on('submit', function(e) {
            e.preventDefault();
            
            // Obțineți datele formularului
            var orderId = $('#mod_id_cmd_total').val();
            var transport = $('#mod_total_nou_cmd').val() || 0;
            var currentTotal = parseFloat($('#mod_total_cmd').val());
            
            console.log("Se actualizează totalul pentru comanda:", {
                orderId: orderId,
                currentTotal: currentTotal,
                transport: transport
            });
            
            // Calculați noul total
            var newTotal = currentTotal + parseFloat(transport);
            
            // Afișați mesajul de încărcare
            $('#rezultat_ajax_total').html('<div class="alert alert-info">Se actualizează totalul...</div>');
            
            // Apel AJAX pentru actualizarea totalului
            $.ajax({
                url: '/orders/update-total',
                type: 'POST',
                data: {
                    order_id: orderId,
                    total: newTotal,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        $('#rezultat_ajax_total').html('<div class="alert alert-success">Total actualizat cu succes!</div>');
                        
                        // Închideți fereastra modală după un timp scurt
                        $('#mod_total').modal('hide');

                        // Reload DataTable to show updated data
                        window.ordersTable.ajax.reload(null, false);
                    }
                    else {
                        $('#rezultat_ajax_total').html('<div class="alert alert-danger">' + (response.message || 'Eroare în actualizare.') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Eroare în actualizare total:', error);
                    $('#rezultat_ajax_total').html('<div class="alert alert-danger">Eroare în actualizare</div>');
                }
            });
        });


        // Fix for modals immediately closing
        // Prevent modal backdrop click from closing modals
        $('.modal').data('backdrop', 'static');
        $('.modal').data('keyboard', false);


        // SMS form submission handler
        $('#frmeditare_sms').on('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            var orderId = $('#mod_id_sms').val();
            var phone = $('#mod_tel_sms').val();
            var message = $('#mod_mesaj').val();
            
            console.log("Sending SMS for order ID:", orderId);
            
            // Validate input
            if (!phone || !message) {
                $('#rezultat_ajax_sms').html('<div class="alert alert-danger">Vă rugăm să completați toate câmpurile!</div>');
                return;
            }
            
            // Show loading message
            $('#rezultat_ajax_sms').html('<div class="alert alert-info">Se trimite SMS-ul...</div>');
            
            // AJAX call to send SMS
            $.ajax({
                url: '/orders/send-sms',
                type: 'POST',
                data: {
                    order_id: orderId,
                    phone: phone,
                    message: message,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        // Update successful
                        $('#rezultat_ajax_sms').html('<div class="alert alert-success">SMS-ul a fost trimis cu succes!</div>');
                        
                        // Hide modal after a short delay
                        setTimeout(function() {
                            $('#mod_sms').modal('hide');
                            
                            // Reload DataTable to show updated data
                            if (window.ordersTable) {
                                window.ordersTable.ajax.reload(null, false);
                            }
                        }, 1500);
                    } else {
                        // Error in sending
                        $('#rezultat_ajax_sms').html('<div class="alert alert-danger">' + (response.message || 'Eroare la trimiterea SMS-ului!') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('SMS sending error:', error);
                    $('#rezultat_ajax_sms').html('<div class="alert alert-danger">Eroare la trimiterea SMS-ului!</div>');
                }
            });
        });


        // Ensure modal doesn't auto-close when clicking inside
        $('#mod_sms').on('click', function(e) {
            if ($(e.target).closest('.modal-content').length) {
                e.stopPropagation();
            }
        });


        // Prevent Bootstrap's default behavior of closing modal on backdrop click
        $('#mod_adresa').data('bs.modal', null);


        // Ensure modal doesn't auto-close when clicking inside it
        $('#mod_adresa').on('click', function(e) {
            if ($(e.target).closest('.modal-content').length) {
                e.stopPropagation();
            }
        });


        // Fix for Bootstrap 3 modal event handling
        $('#mod_adresa').on('shown.bs.modal', function() {
            console.log('Location modal is now visible');
            // Ensure the backdrop is properly applied
            $('.modal-backdrop').css('z-index', 1040);
            $(this).css('z-index', 1050);
        });


        // Fix for all modals to prevent immediate closing
        $('.modal').each(function() {
            $(this).data('bs.modal', null);
        });


        // Properly configure the location modal with Bootstrap 3 syntax
        $('#mod_adresa').modal({
            show: false,
            backdrop: 'static',
            keyboard: false
        });
    });


    // date change function
    function changeDate(inputId, days) {
        //const dateInput = $('#date');
/* 		const dateInput = $('#' + inputId);
        const dateParts = dateInput.val().split('/');
        
        const currentDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
        currentDate.setDate(currentDate.getDate() + days);
        
        const day = String(currentDate.getDate()).padStart(2, '0');
        const month = String(currentDate.getMonth() + 1).padStart(2, '0');
        const year = currentDate.getFullYear();
        
        dateInput.val(`${day}/${month}/${year}`);
		
		datesChanged = true;
        
        $('#loader').removeClass('hidden');
        window.ordersTable.ajax.reload(function() {
            $('#loader').addClass('hidden');
        }); */
		var drp = $('input[name="range_date"]').data('daterangepicker');
		var newStart = drp.startDate.clone().add(days, 'days');
		var newEnd = drp.endDate.clone().add(days, 'days');

		// Update the picker
		drp.setStartDate(newStart);
		drp.setEndDate(newEnd);

		// Optionally update the input value if autoUpdateInput is false
		$('input[name="range_date"]').val(newStart.format('MM/DD/YYYY') + ' - ' + newEnd.format('MM/DD/YYYY'));
		
		datesChanged = true;
		$('#loader').removeClass('hidden');
		window.ordersTable.ajax.reload(function() {
            $('#loader').addClass('hidden');
        });
    }


    // Example in your JavaScript table rendering logic
    function renderOrderRows(orderData) {
        let lastOrderId = null;
        
        orderData.forEach(function(product) {
            // Render the regular product row
            renderProductRow(product);
            
            // If this is the last product in the order, add a separator
            if (product.isLastProductInOrder) {
            renderSeparatorRow();
            }
        });
    }


    function renderSeparatorRow() {
        // Add a thin spacer row with a height of 3-5px
        const separatorHTML = '<tr class="order-separator"><td colspan="13" style="padding: 0; height: 3px; background-color: rgba(220, 220, 220, 0.5);"></td></tr>';
        $('#orders-table tbody').append(separatorHTML);
    }


    // Function to open SMS modal
    function obtineSms(orderId, orderStatus) {
        console.log("Opening SMS modal for order ID:", orderId, "with status:", orderStatus);
        
        // Reset form and clear previous messages
        $('#frmeditare_sms')[0].reset();
        $('#rezultat_ajax_sms').html('');
        
        // Set order ID in hidden field
        $('#mod_id_sms').val(orderId);
        
        // Show loading message
        $('#rezultat_ajax_sms').html('<div class="alert alert-info">Se încarcă datele...</div>');
        
        // Get customer information via AJAX
        $.ajax({
            url: '/get-customer-info',
            type: 'GET',
            data: {
                order_id: orderId
            },
            success: function(response) {
                console.log("Customer info response:", response);
                
                // Clear loading message
                $('#rezultat_ajax_sms').html('');
                
                if (response.success) {
                    // Set customer name and phone
                    $('#mod_nume_sms').val(response.client_name);
                    $('#mod_tel_sms').val(response.phone);
                    
                    // Set default message from server
                    if (response.default_message) {
                        $('#mod_mesaj').val(response.default_message);
                    }
                } else {
                    console.error("Error getting customer info:", response.message);
                    $('#rezultat_ajax_sms').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error getting customer info:", error);
                $('#rezultat_ajax_sms').html('<div class="alert alert-danger">Eroare la încărcarea datelor!</div>');
            }
        });
        
        // Show the modal with options to prevent auto-close
        $('#mod_sms').modal({
            backdrop: 'static',
            keyboard: false,
            show: true
        });
        
        return false;
    }


    // pagination update function
    function updateCustomPagination(settings) {
        if (!settings.aanFeatures.p || settings.aanFeatures.p.length === 0) return;
        
        var api = new $.fn.dataTable.Api(settings);
        var pageInfo = api.page.info();
        
        $('.pagination-wrapper').empty();
        
        if (pageInfo.page > 0) {
            $('.pagination-wrapper').append('<a href="javascript:void(0);" class="pagination-prev">&lt; Prev</a>');
        } else {
            $('.pagination-wrapper').append('<a href="javascript:void(0);" class="pagination-prev disabled">&lt; Prev</a>');
        }
        
        for (var i = 0; i < pageInfo.pages; i++) {
            var activeClass = (i === pageInfo.page) ? 'active' : '';
            $('.pagination-wrapper').append('<a href="javascript:void(0);" class="pagination-page ' + activeClass + '">' + (i + 1) + '</a>');
        }
        
        if (pageInfo.page < pageInfo.pages - 1) {
            $('.pagination-wrapper').append('<a href="javascript:void(0);" class="pagination-next">Next &gt;</a>');
        } else {
            $('.pagination-wrapper').append('<a href="javascript:void(0);" class="pagination-next disabled">Next &gt;</a>');
        }
    }


    // event listener functions
    function setupEventListeners() {
        $('.order-row').hover(
            function() {
                $(this).addClass('light-hover');
                $(this).nextUntil('.order-row').addClass('light-hover');
            },
            function() {
                $(this).removeClass('light-hover');
                $(this).nextUntil('.order-row').removeClass('light-hover');
            }
        );
    }
    setupEventListeners();


    // message display function
    function showError(message) {
        if (typeof toastr !== 'undefined') {
            toastr.error(message);
        } else {
            alert(message);
        }
    }


    function showSuccess(message) {
        if (typeof toastr !== 'undefined') {
            toastr.success(message);
        } else {
            alert(message);
        }
    }


    // utility functions
    function formatCurrency(value) {
        return parseFloat(value).toFixed(2);
    }


    function formatDate(dateString) {
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        
        return `${day}/${month}/${year}`;
    }


    function handleAjaxError(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        
        let errorMessage = 'A apﾄビut o eroare la comunicarea cu serverul.';
        
        if (xhr.responseJSON && xhr.responseJSON.message) {
            errorMessage = xhr.responseJSON.message;
        }
        
        showError(errorMessage);
    }


    // fixed supplier modal function
    function obtineFurnizor(orderId, productId, currentSupplier) {
        console.log("Opening supplier modal for order ID:", orderId, "product ID:", productId, "current supplier:", currentSupplier);
        
        // Reset form and clear previous messages
        $('#frmeditare_furnizor')[0].reset();
        $('#rezultat_ajax_fur').html('');
        
        // Set hidden fields
        $('#mod_id_cmd_fur').val(orderId);
        $('#mod_id_prod_fur').val(productId);
        
        // Check the current supplier radio button if provided
        if (currentSupplier && currentSupplier !== '__') {
            $('input[name="supplier"][value="' + currentSupplier + '"]').prop('checked', true);
        }
        
        // Show the modal with options to prevent auto-close
        $('#mod_furnizor').modal({
            backdrop: 'static',
            keyboard: false,
            show: true
        });
        
        // Prevent default action of the click
        return false;
    }


    // modal functions
    // Function to open status modal with specific button configuration
    function obtineStare(orderId, initialStatus, oldStatus = 0) {
        console.log(oldStatus,"Opening status modal for order ID:", orderId, "with initial status:", initialStatus);
        
        // Reset form and clear previous results
        $('#frmeditare_status')[0].reset();
        $('#rezultat_ajax_status').html('');
        
        // Set order ID in hidden field
        $('#mod_id_cmd').val(orderId);
        
        // Show all buttons first (to reset any previous hiding)
        $('.status-btn-group label.btn').show();
        
        // Configure which buttons to show based on initialStatus
        switch(initialStatus) {
            case 'comandat':
                // For Comandat, we show Comandat, Sosit and Avans
                $('.status-btn-group:eq(0)').show(); // Status group
				$('#mod_stare5').closest('label').hide();
                $('#mod_stare1').prop('checked', true).closest('label').addClass('active');

                // Hide all payment buttons except Avans
                $('.status-btn-group:eq(1)').show(); // Payment group
                $('.status-btn-group:eq(1) label.btn').hide();
				$('#mod_stare10, #mod_stare11, #mod_stare12, #mod_stare13').closest('label').show();
                break;

            case 'sosit':
                // first group - Show only sosit without checked
                $('.status-btn-group:eq(0)').show();
                $('.status-btn-group:eq(0) label.btn').hide();
                $('#mod_stare8').closest('label').show();
                $('#mod_stare2').closest('label').show();
                $('#mod_stare2').prop('checked', true).closest('label').addClass('active');

                // Show all payment buttons in second group
                $('.status-btn-group:eq(1)').show();
				$('#mod_stare10, #mod_stare11, #mod_stare12, #mod_stare13, #mod_stare4').closest('label').hide();
				if(oldStatus == 10 || oldStatus == 11 || oldStatus == 12 || oldStatus == 13){
					$('.status-btn-group:eq(1)').show();
					$('.status-btn-group:eq(1) label.btn').hide();
					$('#mod_stare'+oldStatus).closest('label').show();
				}  
				break;

            case 'cash':
				$('.status-btn-group:eq(0) label.btn').hide();
				$('.status-btn-group:eq(1) label.btn').hide();
				
				$('#mod_stare3, #mod_stare5').closest('label').show();
				$('#mod_stare3').prop('checked', true).closest('label').addClass('active');
                break;

            case 'card':
				$('.status-btn-group:eq(0) label.btn').hide();
				$('.status-btn-group:eq(1) label.btn').hide();
				
				$('#mod_stare6, #mod_stare5').closest('label').show();
				$('#mod_stare6').prop('checked', true).closest('label').addClass('active');
                break;

            case 'fd':
				$('.status-btn-group:eq(0) label.btn').hide();
				$('.status-btn-group:eq(1) label.btn').hide();
				
				$('#mod_stare7, #mod_stare5').closest('label').show();
				$('#mod_stare7').prop('checked', true).closest('label').addClass('active');
                break;

            case 'retur':
                // Hide first group
                $('.status-btn-group:eq(0)').hide();

                // In second group, show only Retur
                $('.status-btn-group:eq(1)').show();
                $('.status-btn-group:eq(1) label.btn').hide();
                $('#mod_stare5').closest('label').show();
                $('#mod_stare5').prop('checked', true).closest('label').addClass('active');
                break;

            case 'avans':
                // first group - Show only sosit without checked
                $('.status-btn-group:eq(0)').hide();
                //$('.status-btn-group:eq(0) label.btn').hide();
                //$('#mod_stare2').closest('label').show();

                // Show all payment buttons in second group
                $('.status-btn-group:eq(1)').show();
				$('#mod_stare5').closest('label').hide();
                $('#mod_stare4').prop('checked', true).closest('label').addClass('active');
                break;

			case 'anulat':
				$('#mod_stare8').prop('checked', true).closest('label').addClass('active');
				break;
				
			case 'op':
				$('.status-btn-group:eq(0) label.btn').hide();
				$('.status-btn-group:eq(1) label.btn').hide();
				
				$('#mod_stare9, #mod_stare5').closest('label').show();
				$('#mod_stare9').prop('checked', true).closest('label').addClass('active');
				break;
				
			case 'avans fd':
                $('.status-btn-group:eq(0)').show(); // Status group
				$('#mod_stare5').closest('label').hide();
				$('#mod_stare10').prop('checked', true).closest('label').addClass('active');
				
				$('.status-btn-group:eq(1) label.btn').hide();
				$('#mod_stare10, #mod_stare11, #mod_stare12, #mod_stare13').closest('label').show();
				
				if(oldStatus == 2){
					$('.status-btn-group:eq(0) label.btn, .status-btn-group:eq(1) label.btn').hide();
					$('#mod_stare5, #mod_stare10').closest('label').show();
				}
				break;
			case 'avans cash':
                $('.status-btn-group:eq(0)').show(); // Status group
				$('#mod_stare5').closest('label').hide();
				$('#mod_stare11').prop('checked', true).closest('label').addClass('active');
				
				$('.status-btn-group:eq(1) label.btn').hide();
				$('#mod_stare10, #mod_stare11, #mod_stare12, #mod_stare13').closest('label').show();
				
				if(oldStatus == 2){
					$('.status-btn-group:eq(0) label.btn, .status-btn-group:eq(1) label.btn').hide();
					$('#mod_stare5, #mod_stare11').closest('label').show();
				}
				break;
			case 'avans card':
                $('.status-btn-group:eq(0)').show(); // Status group
				$('#mod_stare5').closest('label').hide();
				$('#mod_stare12').prop('checked', true).closest('label').addClass('active');
				
				$('.status-btn-group:eq(1) label.btn').hide();
				$('#mod_stare10, #mod_stare11, #mod_stare12, #mod_stare13').closest('label').show();
				
				if(oldStatus == 2){
					$('.status-btn-group:eq(0) label.btn, .status-btn-group:eq(1) label.btn').hide();
					$('#mod_stare5, #mod_stare12').closest('label').show();
				}
				break;
			case 'avans op':
                $('.status-btn-group:eq(0)').show(); // Status group
				$('#mod_stare5').closest('label').hide();
				$('#mod_stare13').prop('checked', true).closest('label').addClass('active');
				
				$('.status-btn-group:eq(1) label.btn').hide();
				$('#mod_stare10, #mod_stare11, #mod_stare12, #mod_stare13').closest('label').show();
				
				if(oldStatus == 2){
					$('.status-btn-group:eq(0) label.btn, .status-btn-group:eq(1) label.btn').hide();
					$('#mod_stare5, #mod_stare13').closest('label').show();
				}
				break;

            default:
                // No initialStatus or unrecognized status
                // Show all buttons, don't pre-select anything
                $('.status-btn-group').show();
        }
        
        // Show the modal
        $('#mod_status').modal({
            backdrop: 'static',
            keyboard: false,
            show: true
        });
        
        return false;
    }


    // Fixed color update function with additional console logging
    function obtineCuloare(orderId, productId, currentColor) {
        console.log("Opening color modal for order ID:", orderId, "product ID:", productId);
        
        if (!orderId || !productId) {
            console.error("Invalid parameters: orderId or productId is missing or invalid");
            alert("Error: Missing order or product information. Please contact support.");
            return false;
        }
        
        // Reset form and clear previous messages
        $('#frmeditare_culoare')[0].reset();
        $('#rezultat_ajax_culoare').html('');
        
        // Set hidden fields
        $('#mod_id_cmd_culoare').val(orderId);
        $('#mod_id_prod_culoare').val(productId);
        
        if (currentColor === "7CFC00") {
            $("#mod_cul1").prop("checked", "checked");
        }
        else if (currentColor === "ADD8E6") {
            $("#mod_cul2").prop("checked", "checked");
        }
        else if (currentColor === "FF0000") {
            $("#mod_cul3").prop("checked", "checked");
        }
		else if (currentColor === "F5A000") {
			$("#mod_cul5").prop("checked", "checked");
		}
        else if (currentColor === "FFFFFF") {
            $("#mod_cul4").prop("checked", "checked");
        }

        // Show the modal with options to prevent auto-close
        $('#mod_culoare').modal({
            backdrop: 'static',
            keyboard: false,
            show: true
        });
        
        // Prevent default action of the click
        return false;
    }


    // fixed address modal function
    // Function to open address modal with specific order ID and current location
    function obtineAdresa(orderId, currentLocation) {
        console.log("Opening location modal for order ID:", orderId, "current location:", currentLocation);
        
        $('#rezultat_ajax_adr').html('');
        
        // Set hidden field with order ID
        $('#mod_id_cmd_adr').val(orderId);
        
        // Check the current location radio button if provided
        if (currentLocation) {
            $('input[name="location"][value="' + currentLocation + '"]').prop('checked', true);
        }
        
        // Show the modal
        $('#mod_adresa').modal({
            backdrop: 'static',
            keyboard: false,
            show: true
        });
        
        return false;
    }


    // Add this function to your JavaScript code
    function stergeComanda(orderId) {
        if (!confirm('Ești sigur că vrei să ștergi această comandă?')) {
            return false;
        }

        $('#loader').removeClass('hidden');
        
        $.ajax({
            url: '/orders/' + orderId,
            type: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Comanda a fost ștearsă cu succes!');
                    
                    // Reload the table to remove the deleted order
                    if (window.ordersTable) {
                        window.ordersTable.ajax.reload();
                    }
                }
                else {
                    showError('Eroare la ștergerea comenzii: ' + (response.message || 'Eroare necunoscută'));
                }
                $('#loader').addClass('hidden');
            },
            error: function(xhr, status, error) {
                console.error('Error deleting order:', error);
                showError('Eroare la ștergerea comenzii: ' + error);
                $('#loader').addClass('hidden');
            }
        });
        
        return false;
    }


    function obtineTotalComanda(orderId, currentTotal) {
        console.log("Opening total update modal for order ID:", orderId);
        
        // Reset form and clear previous messages
        $('#frmeditare_total')[0].reset();
        $('#rezultat_ajax_total').html('');
        
        // Set order ID in hidden field
        $('#mod_id_cmd_total').val(orderId);
        
        // Set current total in readonly field
        $('#mod_total_cmd').val(parseFloat(currentTotal).toFixed(2));
        
        // Show the modal with options to prevent auto-close
        $('#mod_total').modal({
            backdrop: 'static',
            keyboard: false,
            show: true
        });
        
        return false;
    }

    //if using toastr, configure it
    if (typeof toastr !== 'undefined') {
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "3000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
    }
</script>
@endsection

