@extends('layouts.mainappv1')
@section('title', 'Facturi')
<style>
    .dataTables_wrapper .dataTables_filter {
        display: none;
    }
	.table{
		width:100% !important;
	}
    .dataTables_wrapper .dataTables_length {
        float: right;
        margin-top: 8px;
    }
    .dataTables_wrapper .top {
        padding: 0px 0;
        display: flex;
        justify-content: space-between;
    }
    .dataTables_wrapper .bottom {
        padding: 8px 0;
        display: flex;
        justify-content: space-between;
    }
    .table-bordered {
        border: 1px solid #ddd;
    }
    .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: #f9f9f9;
    }
    .table th {
        background-color: #d9edf7;
        color: #31708f;
    }
    .pagination > .active > a {
        background-color: #337ab7;
        border-color: #337ab7;
    }
    .btn-group .btn {
        margin-right: 2px;
    }
    .custom-search-container {
        display: flex;
        justify-content: end;
        align-items: center;
     
    }
    .custom-search {
        display: flex;
        align-items: center;
        margin-left: 6%;
    }
    .custom-search label {
        font-weight: bold;
        margin-right: 15px;
        min-width: 50px;
    }
    .custom-search input {
        height: 34px;
        padding: 6px 12px;
        font-size: 14px;
        line-height: 1.42857143;
        color: #555;
        background-color: #fff;
        background-image: none;
        border: 1px solid #ccc;
        border-radius: 4px;
        width: 500px;
        margin-right: 0;
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }
    .custom-search button {
        height: 34px;
        padding: 6px 12px;
        border-radius: 4px;
        background-color: #eee;
        border: 1px solid #ccc;
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
    .action-buttons .btn {
        margin-right: 5px;
    }
    .bg-info {
        background-color: #d9edf7;
    }
	.length-control{
		margin-right:15px;
	}
</style>

@section('content')
<!--<div class="container mt-4" style="width:100%;">-->
    <div class="jumbotron">
        <div class="container-fluid">
            <div class="panel panel-info">
                <div class="panel-heading d-flex justify-content-between align-items-center" style="display:flex; justify-content:space-between;">
                    <h4><i class="glyphicon glyphicon-search"></i> Facturi</h4>
                    <button type="button" class="btn btn-info" id="newInvoiceBtn">
                        <span class="glyphicon glyphicon-plus"></span> Factura noua
                    </button>

                    <script>
                        document.getElementById('newInvoiceBtn').addEventListener('click', function() {
                            window.location.href = '{{ route('facturi.create') }}';
                        });
                    </script>

                </div>
                <div class="panel-body">
                    <div id="actionMessage"></div>

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
					
						<!-- Custom Search Box Container -->
						<div class="custom-search-container">
							<div class="custom-search" style="margin-left:0;">
								<label for="customSearch">Cauta</label>
								<input type="text" id="customSearch" placeholder="Client, nr. factura">
								<button type="button" id="searchButton"><i class="glyphicon glyphicon-search" style="color: #337ab7;"></i></button>
							</div>
							
							<!-- New filter select field -->
							<select class="form-control" id="filterType" style="margin:0px 10px; max-width:12%;">
								<option value="">Toate</option>
								<option value="smartbill">Smartbill</option>
								<option value="manual">Manual</option>
							</select>
							
							<select class="form-control" id="tip_incasare" style="margin-right:10px; max-width:12%;">
								<option value="">Tip incasare</option>
								@foreach($tipuriPlata as $tipPlata)
									<option value="{{ $tipPlata->id_plata }}">
										{{ $tipPlata->denumire }}
									</option>
								@endforeach
							</select>
							
							<div class="length-control">
								<!-- DataTables length control will be moved here via JavaScript -->
							</div>
						</div>
					</div>

                    <!-- DataTables Container -->
                    <div class="table-responsive">
                        <table id="facturiTable" class="table table-bordered table-striped">
                            <thead>
                                <tr class="info">
                                    <th>Nr.</th>
                                    <th>Client</th>
                                    <th>Data</th>
                                    <th>Scadenta</th>
                                    <th>Total</th>
                                    <th>Incasare</th>
                                    <th>Actiune</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!--</div>-->
<script>
var table;
$(document).ready(function() {
	// initialize datepicker with Romanian locale
/* 	if($.fn.fdatepicker) {
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
		  ]
		}
	  }, function(start, end, label) {
		datesChanged = true;
		$('#loader').removeClass('hidden');
		table.ajax.reload(function() {
			$('#loader').addClass('hidden');
		});
	  });
	});
	
	// When date changes manually
/* 	$('#from_date, #to_date').change(function() {
		let today = new Date();
		let dd = String(today.getDate()).padStart(2, '0');
		let mm = String(today.getMonth() + 1).padStart(2, '0');
		let yyyy = today.getFullYear();
		let formattedToday = `${dd}/${mm}/${yyyy}`;
		datesChanged = true;

		let changedField = $(this).attr('id');
		if(changedField == "from_date"){
			$('#to_date').fdatepicker('show');
		}
		
		$('#loader').removeClass('hidden');
		table.ajax.reload(function() {
			$('#loader').addClass('hidden');
		});
	}); */
		
    // Initialize DataTable with server-side processing
    table = $('#facturiTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('facturi.data') }}",
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
                d.search_value = $('#customSearch').val();
				d.invoiceType = $('#filterType').val();
				d.tip_incasare = $('#tip_incasare').val();
            },
			error: function(xhr, error, thrown) {
				console.error('DataTables error:', error);
				console.error('Server response:', xhr.responseJSON);
				alert('Error loading data. See console for details.');
				$('#loader').addClass('hidden');
			}
        },
        columns: [
                { data: 'numar_factura', name: 'numar_factura' },
                { data: 'client_name', name: 'clienti.nume' },
                { data: 'OrderDate', name: 'facturi.OrderDate' },
                { data: 'RequiredDate', name: 'facturi.RequiredDate' },
                { data: 'total', name: 'total' },
                { data: 'tip_incasare', name: 'tip_plata.denumire' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        language: {
            "sProcessing": "Procesează...",
            "sLengthMenu": "", // Removed "Afișează 10 înregistrări pe pagină"
            "sZeroRecords": "Nu s-a găsit nimic",
            "sInfo": "Afișate de la _START_ la _END_ din _TOTAL_ înregistrări",
            "sInfoEmpty": "Afișate de la 0 la 0 din 0 înregistrări",
            "sInfoFiltered": "(filtrate din _MAX_ înregistrări totale)",
            "sInfoPostFix": "",
            "sSearch": "Caută:",
            lengthMenu: "Arată înregistrări _MENU_",
            "sUrl": "",
            "oPaginate": {
                "sFirst": "Prima",
                "sPrevious": "&lt; Prev",
                "sNext": "Next &gt;",
                "sLast": "Ultima"
            }
        },
        dom: '<"top"fl>rt<"bottom"ip>',
		pageLength: 50,
		lengthMenu: [10, 25, 50, 100, 500],
        pagingType: "simple_numbers"
    });
    
    // Move the length control to our custom position
    $('.dataTables_length').detach().appendTo('.length-control');

    // Custom search functionality
    $('#searchButton').on('click', function() {
        table.draw();
    });
    
    $('#customSearch').on('keyup', function() {
        table.draw();
    });
	
	$('#filterType').on('change', function() {
        table.draw();
    });
	
	$('#tip_incasare').on('change', function() {
        table.draw();
    });
    
    // Show invoice modal on button click
    $('#newInvoiceBtn').on('click', function() {
        resetInvoiceForm();
        $('#modalTitle').text('Factura noua');
        $('#method').val('POST');
        $('#invoiceModal').modal('show');
    });
    
    // Save invoice
    $('#saveInvoice').on('click', function() {
        var formData = $('#frmfactura_nou').serialize();
        var method = $('#method').val();
        var url = "{{ route('facturi.store') }}";
        var id = $('#idfactura').val();
        
        if(method === 'PUT') {
            url = "/facturi/" + id;
            formData += "&_method=PUT";
        }
        
        $.ajax({
            url: url,
            type: "POST",
            data: formData,
            success: function(response) {
                if(response.success) {
                    $('#invoiceModal').modal('hide');
                    $('#actionMessage').html('<div class="alert alert-success">' + response.message + '</div>');
                    table.ajax.reload();
                    
                    // Hide success message after 3 seconds
                    setTimeout(function() {
                        $('#actionMessage').html('');
                    }, 3000);
                } else {
                    $('#rezultat_ajax_factura_nou').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr) {
                var errors = xhr.responseJSON.errors;
                var errorMsg = '<div class="alert alert-danger"><ul>';
                
                $.each(errors, function(key, value) {
                    errorMsg += '<li>' + value + '</li>';
                });
                
                errorMsg += '</ul></div>';
                $('#rezultat_ajax_factura_nou').html(errorMsg);
            }
        });
    });
    
    // Delete invoice
    $(document).on('click', '.delete-btn', function() {
        var invoiceId = $(this).data('id');
        
        if(confirm('Ești sigur că vrei să ștergi această factură?')) {
            $.ajax({
                url: '/facturi/' + invoiceId,
                type: 'DELETE',
                data: {
                    "_token": "{{ csrf_token() }}"
                },
                success: function(response) {
                    $('#actionMessage').html('<div class="alert alert-success">' + response.message + '</div>');
                    table.ajax.reload();
                    
                    // Hide success message after 3 seconds
                    setTimeout(function() {
                        $('#actionMessage').html('');
                    }, 3000);
                }
            });
        }
    });
    
    // Print invoice
    $(document).on('click', '.print-btn', function() {
        var invoiceId = $(this).data('id');
        window.open('/print-invoice/' + invoiceId, '_blank');
    });
    
    // Reset invoice form
    function resetInvoiceForm() {
        $('#frmfactura_nou')[0].reset();
        $('#idfactura').val('');
        $('#rezultat_ajax_factura_nou').html('');
        $('#data_factura').val("{{ date('Y-m-d') }}");
        $('#data_scadenta').val("{{ date('Y-m-d', strtotime('+30 days')) }}");
    }
    
    // Reset and close modal on close button click
    $('[data-dismiss="modal"]').on('click', function() {
        resetInvoiceForm();
        $('#invoiceModal').modal('hide');
    });
});

// date change function
function changeDate(inputId, days) {
	//const dateInput = $('#date');
/* 	const dateInput = $('#' + inputId);
	const dateParts = dateInput.val().split('/');
	
	const currentDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
	currentDate.setDate(currentDate.getDate() + days);
	
	const day = String(currentDate.getDate()).padStart(2, '0');
	const month = String(currentDate.getMonth() + 1).padStart(2, '0');
	const year = currentDate.getFullYear();
	
	dateInput.val(`${day}/${month}/${year}`);
	
	datesChanged = true;
	
	$('#loader').removeClass('hidden');
	window.table.ajax.reload(function() {
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
	window.table.ajax.reload(function() {
		$('#loader').addClass('hidden');
	});
}
</script>
@endsection
