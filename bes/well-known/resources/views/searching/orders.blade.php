@extends('layouts.mainappv1')
@section('title', 'Supplier Orders')
<style>
td.dataTables_empty {
    text-align: center;
}
.length-control{
	margin-left: auto;
	margin-top: 8px;
}
#ordersTable_wrapper .pagination{
	margin:0!important;
	float:right;
}
.dataTables_wrapper .dataTables_filter { display: none; }
.dataTables_wrapper .dataTables_length { float: right; margin-top: 8px; }
.dataTables_wrapper .top, .dataTables_wrapper .bottom {
	padding: 8px 0; display: flex; justify-content: space-between;
}
.table-bordered { border: 1px solid #ddd; }
.table-striped > tbody > tr:nth-of-type(odd) { background-color: #f9f9f9; }
.table th { background-color: #d9edf7; color: #31708f; }
.pagination > .active > a { background-color: #337ab7; border-color: #337ab7; }
.btn-group .btn { margin-right: 2px; }
.custom-search-container { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
#loader { text-align: center; padding: 20px; display: none; }
#ordersTable td { vertical-align:middle; }
.supplier-badge {
	padding: 4px 8px;
	border-radius: 4px;
	font-weight: bold;
	text-transform: uppercase;
	font-size: 11px;
}
.supplier-materom { background-color: #5cb85c; color: white; }
.supplier-autopartner { background-color: #337ab7; color: white; }
.supplier-autonet { background-color: #f0ad4e; color: white; }
.supplier-autototal { background-color: #d9534f; color: white; }
.supplier-elit { background-color: #5bc0de; color: white; }
</style>

@section('content')
<div class="jumbotron">
    <div class="container">
        <div class="panel panel-info">
            <div class="panel-heading clearfix">
                <h4 class="pull-left" style="margin:0;"><i class="glyphicon glyphicon-list-alt"></i> Comenzi furnizori</h4>
				
				<a href="{{ route('searching.index') }}" class="btn btn-default btn-sm pull-right">
					<i class="glyphicon glyphicon-arrow-left"></i> Înapoi
				</a>
            </div>
			
			<div class="panel-body">
				<div class="custom-search-container">
					<!-- Suppliers Filter Dropdown -->
					<div class="dropdown">
						<button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">
							Furnizori <span class="caret"></span>
						</button>
						<ul class="dropdown-menu" id="supplierFilterMenu">
							@foreach(['materom','autopartner','autonet','autototal','elit'] as $s)
								@if(in_array($s, $availableSuppliers))
								<li>
									<label style="padding:5px 10px; display:flex; align-items:center;">
										<input type="checkbox" class="supplier-filter-checkbox" value="{{ $s }}" 
											{{ empty($selectedSuppliers) || in_array($s, $selectedSuppliers) ? 'checked' : '' }} 
											style="margin-right:5px;">
										{{ ucfirst($s) }}
									</label>
								</li>
								@endif
							@endforeach
						</ul>
					</div>
					
					<button id="applyFilter" class="btn btn-primary">
						<i class="glyphicon glyphicon-filter"></i> Aplică filtrul
					</button>
					
					<div class="length-control">
						<!-- DataTables length control will be moved here via JavaScript -->
					</div>
				</div>
				
				<table id="ordersTable" class="table table-bordered table-striped">
					<thead>
						<tr>
							<th>Număr comandă</th>
							<th>Furnizor</th>
							<th>Stare</th>
							<th>Data comenzii</th>
							<th>Total</th>
							<th>Acțiuni</th>
						</tr>
					</thead>
					<tbody>
						@forelse($orders as $order)
							<tr>
								<td>{{ $order['order_number'] ?? '-' }}</td>
								<td>
									<span class="supplier-badge supplier-{{ strtolower($order['supplier']) }}">
										{{ ucfirst($order['supplier']) }}
									</span>
								</td>
								<td>{{ $order['status'] ?? '-' }}</td>
								<td>{{ $order['order_date'] ?? '-' }}</td>
								<td>
									@if(isset($order['total']) && $order['total'] > 0)
										{{ number_format($order['total'], 2) }} RON
									@else
										-
									@endif
								</td>
								<td>
									<button class="btn btn-sm btn-info view-order-details" 
										data-order-id="{{ $order['id'] }}"
										data-supplier="{{ $order['supplier'] }}"
										data-order-number="{{ $order['order_number'] }}"
										title="View Order Details">
										<i class="glyphicon glyphicon-eye-open"></i> View
									</button>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="6" class="text-center">Nu au fost găsite comenzi</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Detalii comandă</h4>
            </div>
            <div class="modal-body" id="orderDetailsBody">
                <div id="orderDetailsLoader" style="text-align:center; padding:20px;">
                    <i class="glyphicon glyphicon-refresh glyphicon-spin"></i> Loading...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include Bootstrap & jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    var table = $('#ordersTable').DataTable({
        pageLength: 10,
        order: [[3, 'desc']],
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
	
	// Apply filter
	$('#applyFilter').on('click', function() {
		var selectedSuppliers = $('.supplier-filter-checkbox:checked').map(function() {
			return $(this).val();
		}).get();
		
		var url = "{{ route('searching.getOrders') }}";
		if (selectedSuppliers.length > 0) {
			url += '?suppliers=' + selectedSuppliers.join(',');
		}
		
		window.location.href = url;
	});
	
	// Store orders data in JavaScript
	var ordersData = @json($orders);
	
	// View order details
	$('#ordersTable').on('click', '.view-order-details', function() {
		var orderId = $(this).data('order-id');
		
		$('#orderDetailsModal').modal('show');
		$('#orderDetailsBody').html('<div id="orderDetailsLoader" style="text-align:center; padding:20px;"><i class="glyphicon glyphicon-refresh glyphicon-spin"></i> Loading...</div>');
		
		// Find the order in the stored data
		var order = ordersData.find(function(o) {
			return o.id == orderId;
		});
		
		if (order) {
			setTimeout(function() {
				displayOrderDetails(order);
			}, 100);
		} else {
			$('#orderDetailsBody').html('<div class="alert alert-danger">Comanda nu a fost găsită</div>');
		}
	});
	
	function displayOrderDetails(order) {
		var html = '<div class="row">';
		html += '<div class="col-md-12">';
		html += '<h4>Order Information</h4>';
		html += '<table class="table table-bordered">';
		html += '<tr><th>Order Number:</th><td>' + (order.order_number || '-') + '</td></tr>';
		html += '<tr><th>Supplier:</th><td><span class="supplier-badge supplier-' + order.supplier.toLowerCase() + '">' + order.supplier.charAt(0).toUpperCase() + order.supplier.slice(1) + '</span></td></tr>';
		html += '<tr><th>Status:</th><td>' + (order.status || '-') + '</td></tr>';
		html += '<tr><th>Order Date:</th><td>' + (order.order_date || '-') + '</td></tr>';
		if (order.external_order_number) {
			html += '<tr><th>External Order Number:</th><td>' + order.external_order_number + '</td></tr>';
		}
		html += '<tr><th>Total:</th><td>' + (order.total > 0 ? parseFloat(order.total).toFixed(2) + ' RON' : '-') + '</td></tr>';
		html += '</table>';
		
		// Display items
		if (order.items && order.items.length > 0) {
			html += '<h4>Produse comandate</h4>';
			html += '<table class="table table-bordered table-striped">';
			html += '<thead><tr><th>Cod produs</th><th>Nume produs</th><th>Cantitate</th><th>Preț</th><th>Total</th></tr></thead>';
			html += '<tbody>';
			
			var totalAmount = 0;
			order.items.forEach(function(item) {
				var productCode = item.product_code || item.ProductCode || item.PartNo || item.order_code || '-';
				var productName = item.product_name || item.ProductName || item.name || '-';
				var quantity = item.quantity || item.Quantity || item.qty || 0;
				var price = item.price || item.Price || item.unit_price || 0;
				var itemTotal = quantity * price;
				totalAmount += itemTotal;
				
				html += '<tr>';
				html += '<td>' + productCode + '</td>';
				html += '<td>' + productName + '</td>';
				html += '<td>' + quantity + '</td>';
				html += '<td>' + parseFloat(price).toFixed(2) + ' RON</td>';
				html += '<td>' + parseFloat(itemTotal).toFixed(2) + ' RON</td>';
				html += '</tr>';
			});
			
			html += '</tbody>';
			html += '<tfoot><tr><th colspan="4" style="text-align:right;">Total:</th><th>' + parseFloat(totalAmount).toFixed(2) + ' RON</th></tr></tfoot>';
			html += '</table>';
		} else {
			html += '<div class="alert alert-info">Nu au fost găsite produse în această comandă</div>';
		}
		
		// Display raw response (collapsible)
		html += '<h4>Raw Response <small>(Click to expand)</small></h4>';
		html += '<div class="panel panel-default">';
		html += '<div class="panel-heading" style="cursor:pointer;" onclick="$(this).next().toggle();">';
		html += '<i class="glyphicon glyphicon-chevron-down"></i> View Raw Data';
		html += '</div>';
		html += '<div class="panel-body" style="display:none; max-height:400px; overflow-y:auto;">';
		html += '<pre>' + JSON.stringify(order.raw_response, null, 2) + '</pre>';
		html += '</div>';
		html += '</div>';
		
		html += '</div></div>';
		
		$('#orderDetailsBody').html(html);
	}
});

$('.dropdown-toggle').dropdown();
</script>
@endsection