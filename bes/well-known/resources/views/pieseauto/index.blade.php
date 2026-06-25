@extends('layouts.mainappv1')
@section('title', 'Piese Auto Orders')

<style>
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
    .custom-search-container { display: flex; justify-content: space-between; align-items: center; }
    .custom-search { display: flex; align-items: center; margin-left: 6%; }
    .custom-search label { font-weight: bold; margin-right: 15px; min-width: 50px; }
    .custom-search input {
        height: 34px; padding: 6px 12px; font-size: 14px;
        color: #555; background-color: #fff; border: 1px solid #ccc;
        border-radius: 4px; width: 500px; border-top-right-radius: 0; border-bottom-right-radius: 0;
    }
    .custom-search button {
        height: 34px; padding: 6px 12px; border-radius: 4px;
        background-color: #eee; border: 1px solid #ccc;
        border-top-left-radius: 0; border-bottom-left-radius: 0;
    }
    .action-buttons .btn { margin-right: 5px; }
    .bg-info { background-color: #d9edf7; }
	
	.edit-fields input {
		width: 90%;
		display: block;
	}
	.edit-fields button {
		margin-right: 5px;
	}
	.product-item .btn-link {
		padding: 0;
		margin-left: 5px;
	}
</style>

@section('content')
<div class="jumbotron">
    <div class="container-fluid">
        <h3 class="mb-4"><i class="glyphicon glyphicon-list-alt"></i> PieseAuto Orders</h3>

        <div class="panel panel-primary">
            <div class="panel-heading clearfix">
                <span><i class="glyphicon glyphicon-th-list"></i> Orders (Status: <strong>new</strong>)</span>
				
				<div class="pull-right">
					<select id="orderStatus" class="form-control input-sm" style="width:auto; display:inline-block; margin-right:10px;">
						<option value="new" selected>New</option>
						<option value="in_progress">Processing</option>
						<option value="finalized">Finalized</option>
						<option value="cancelled">Cancelled</option>
					</select>

					<button id="importBtn" class="btn btn-default btn-sm" disabled>
						<i class="glyphicon glyphicon-cloud-download"></i> Import Selected
					</button>
				</div>
            </div>

            <div class="panel-body">
                <div class="table-responsive">
                    <table id="ordersTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>Order ID</th>
                                <th>Buyer</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>Car</th>
                                <th>Total (RON)</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="ordersBody">
                            <tr>
                                <td colspan="8" class="text-center text-muted">Please select an account...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Account Selection Modal -->
<div class="modal fade" id="accountSelectModal" tabindex="-1" role="dialog" aria-labelledby="accountSelectLabel">
  <div class="modal-dialog" role="document" style="margin-top:10%;">
    <div class="modal-content">
      <div class="modal-header bg-primary panel-heading" style="color:white;">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
        </button>
        <h4 class="modal-title" id="accountSelectLabel">Select PieseAuto Account</h4>
      </div>
      <div class="modal-body text-center">
        <p class="mb-3">Choose which PieseAuto account you want to view orders from:</p>
        <button class="btn btn-success m-2" id="btnBesoiuPieseAuto" data-account="1">BesoiuPieseAuto</button>
        <button class="btn btn-default m-2" id="btnPieseAutoBesoiu" data-account="2">PieseAutoBesoiu</button>
      </div>
    </div>
  </div>
</div>

<!-- Import Destination Modal -->
<div class="modal fade" id="importDestinationModal" tabindex="-1" role="dialog" aria-labelledby="importDestinationLabel">
  <div class="modal-dialog" role="document" style="margin-top:10%;">
    <div class="modal-content">
      <div class="modal-header bg-primary" style="color:white;">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
        </button>
        <h4 class="modal-title" id="importDestinationLabel">Select Destination</h4>
      </div>
      <div class="modal-body text-center">
        <p class="mb-3">Where do you want to add the selected orders?</p>
        <button class="btn btn-success m-2 import-destination" data-dest="UTVIN">UTVIN</button>
        <button class="btn btn-warning m-2 import-destination" data-dest="TM">TM</button>
        <button class="btn btn-info m-2 import-destination" data-dest="External">External</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="selectProductsModal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary" style="color:white;">
        <h4 class="modal-title">Select Products to Import</h4>
      </div>
      <div class="modal-body">
        <div id="productsContainer"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" id="confirmProducts">Next: Select Destination</button>
      </div>
    </div>
  </div>
</div>

<!-- Include Bootstrap & jQuery -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap.min.js"></script>

<script>
$(document).ready(function () {
    $('#accountSelectModal').modal({ backdrop: 'static', keyboard: false }).modal('show');

    const URLs = {
        BesoiuPieseAuto: "{!! url('/pieseauto/fetch?account=1') !!}",
        PieseAutoBesoiu: "{!! url('/pieseauto/fetch?account=2') !!}"
    };

    let ordersData = [];
    let selectedAccount = null;
    let selectedStatus = 'new';
    let ordersTable = null;
    let ordersToImport = [];
    let selectedProducts = [];

    // --- Account selection ---
    $('#btnBesoiuPieseAuto, #btnPieseAutoBesoiu').on('click', function() {
        selectedAccount = $(this).data('account');
        $('#accountSelectModal').modal('hide');
        fetchOrders();
    });

    // --- Status change ---
    $('#orderStatus').on('change', function() {
        selectedStatus = $(this).val();
        if (selectedAccount) fetchOrders();
    });

	let currentRequest = null;
    // --- Fetch Orders ---
    function fetchOrders() {
        const selectedURL = `/pieseauto/fetch?account=${selectedAccount}`;

		if (currentRequest && currentRequest.readyState !== 4) {
			currentRequest.abort();
		}
	
        // Clear globals and UI
        ordersData = [];
        ordersToImport = [];
        $('#importBtn').prop('disabled', true);

        // Destroy existing DataTable
/*         if (ordersTable) {
            ordersTable.clear().destroy();
            ordersTable = null;
        } */

        $('#ordersBody').html(`<tr><td colspan="8" class="text-center text-muted">Loading ${selectedStatus} orders...</td></tr>`);

        $.getJSON(selectedURL, function(data) {
            const filtered = data.orders.filter(o => o.order_status === selectedStatus);
            ordersData = filtered;
            window.allOrders = data.orders; // keep full data

            if (!filtered.length) {
                $('#ordersBody').html(`<tr><td colspan="8" class="text-center text-muted">No ${selectedStatus} orders found.</td></tr>`);
                return;
            }

            const rows = filtered.map(order => {
                const car = order.car_info ? 
                    `${order.car_info.car_maker || ''} ${order.car_info.car_model || ''} ${order.car_info.car_year || ''}` : '';
                const date = new Date(order.order_date * 1000).toLocaleString();
                const total = (parseFloat(order.items_cost_ron) + parseFloat(order.shipping_cost_ron)).toFixed(2);

                return [
                    order.order_id,
                    order.order_id,
                    order.buyer_name,
                    order.buyer_phone,
                    order.shipping_city,
                    car,
                    total,
                    date
                ];
            });

            ordersTable = $('#ordersTable').DataTable({
				destroy: true,
                data: rows,
                columns: [
                    { 
                        title: `<input type="checkbox" id="selectAll">`,
                        orderable: false,
                        render: function(data) {
                            return `<input type="checkbox" class="order-checkbox" data-id="${data}">`;
                        }
                    },
                    { title: 'Order ID' },
                    { title: 'Buyer' },
                    { title: 'Phone' },
                    { title: 'City' },
                    { title: 'Car' },
                    { title: 'Total (RON)' },
                    { title: 'Date' }
                ],
				pageLength: 50,
				lengthMenu: [10, 25, 50, 100, 500],
                order: [[1, 'desc']],
                language: {
                    emptyTable: "No data available",
                    zeroRecords: "No matching records found",
                    paginate: { previous: "&lt; Prev", next: "Next &gt;" }
                }
            });
        });
    }

    // --- Build product list for selected orders ---
	function fetchProductsForOrders(selectedOrders, allOrders) {
		let html = '';

		selectedOrders.forEach(sel => {
			const order = allOrders.find(o => o.order_id == sel.order_id);
			if (!order) return;

			html += `<h5><strong>Order #${order.order_id}</strong> — ${order.buyer_name}</h5><ul class="list-unstyled">`;

			order.items.forEach(item => {
				html += `
					<li class="product-item" data-order="${order.order_id}" data-prod="${item.product_id}" style="margin-bottom:8px;">
						<label>
							<input type="checkbox" class="product-checkbox" data-order="${order.order_id}" data-prod="${item.product_id}" checked>
							<span class="product-name" id="name-display-${item.product_id}">${item.title}</span> — 
							<span class="product-price" id="price-display-${item.product_id}">${item.unit_price_ron}</span> RON
							<button type="button" class="btn btn-xs btn-link edit-product" data-product-id="${item.product_id}">
								<i class="glyphicon glyphicon-pencil"></i>
							</button>
						</label>

						<div class="edit-fields" id="edit-fields-${item.product_id}" style="display:none; margin-top:5px; margin-left:25px;">
							<input type="text" class="form-control input-sm edit-name" 
								   value="${item.title}" placeholder="Edit name" style="margin-bottom:5px;">
							<input type="number" step="0.01" class="form-control input-sm edit-price" 
								   value="${item.unit_price_ron}" placeholder="Edit price" style="margin-bottom:5px;">
							<input type="text" class="form-control input-sm edit-code" 
								   value="${item.product_id}" placeholder="Edit code" style="margin-bottom:5px;">
							<select class="form-control edit-furnizor input-sm" data-prod="${item.product_id}" style="margin-bottom:5px;">
								<option value="ET">ET</option>
								<option value="MA">MA</option>
								<option value="IC">IC</option>
								<option value="AN">AN</option>
								<option value="AT">AT</option>
								<option value="BA">BA</option>
								<option value="AB">AB</option>
								<option value="SZ">SZ</option>
								<option value="AP">AP</option>
								<option value="AD">AD</option>
								<option value="Stoc">Stoc</option>
							</select>
							<select class="form-control edit-disponibilitate input-sm" data-prod="${item.product_id}" style="margin-bottom:5px;">
								<option value="7CFC00">Azi</option>
								<option value="ADD8E6">Maine</option>
								<option value="F5A000">2 zile</option>
								<option value="FF0000">+3 zile</option>
								<option value="FFFFFF">Sosit</option>
							</select>
							<div>
								<button type="button" class="btn btn-success btn-xs save-edit" data-product-id="${item.product_id}">Save</button>
								<button type="button" class="btn btn-default btn-xs cancel-edit" data-product-id="${item.product_id}">Cancel</button>
							</div>
						</div>
					</li>`;
			});

			// 🚚 Add Transport option
			html += `
				<li class="transport-item" data-order="${order.order_id}" style="margin-top:10px; margin-bottom:10px;">
					<label>
						<input type="checkbox" class="transport-checkbox" data-order="${order.order_id}" checked>
						<strong>Transport</strong> — 
						<span class="transport-name" id="transport-name-${order.order_id}">Transport Service</span> —
						<span class="transport-price" id="transport-price-${order.order_id}">30</span> RON
						<button type="button" class="btn btn-xs btn-link edit-transport" data-order="${order.order_id}">
							<i class="glyphicon glyphicon-pencil"></i>
						</button>
					</label>

					<div class="edit-fields-transport" id="edit-transport-fields-${order.order_id}" style="display:none; margin-top:5px; margin-left:25px;">
						<input type="text" class="form-control input-sm edit-transport-name" 
							   value="Transport Service" placeholder="Edit name" style="margin-bottom:5px;">
						<input type="number" step="0.01" class="form-control input-sm edit-transport-price" 
							   value="30" placeholder="Edit price" style="margin-bottom:5px;">
						<div>
							<button type="button" class="btn btn-success btn-xs save-transport-edit" data-order="${order.order_id}">Save</button>
							<button type="button" class="btn btn-default btn-xs cancel-transport-edit" data-order="${order.order_id}">Cancel</button>
						</div>
					</div>
				</li>
			`;

			html += '</ul><hr>';
		});

		$('#productsContainer').html(html);
		$('#selectProductsModal').modal('show');
	}

    // --- Checkbox logic ---
    $(document).on('change', '#selectAll', function() {
        $('.order-checkbox').prop('checked', this.checked);
        toggleImportButton();
    });

    $(document).on('change', '.order-checkbox', function() {
        if (!this.checked) $('#selectAll').prop('checked', false);
        toggleImportButton();
    });

    function toggleImportButton() {
        $('#importBtn').prop('disabled', $('.order-checkbox:checked').length === 0);
    }

    // --- Step 1: Click import selected ---
    $('#importBtn').on('click', function() {
        const selectedIds = $('.order-checkbox:checked').map(function() {
			return String($(this).data('id'));
		}).get();

        ordersToImport = ordersData.filter(o => selectedIds.includes(o.order_id.toString()));
        if (!ordersToImport.length) return alert('No orders selected.');

        fetchProductsForOrders(ordersToImport, window.allOrders);
    });

    // --- Step 2: Confirm selected products ---
    $('#confirmProducts').on('click', function() {
        selectedProducts = $('.product-checkbox:checked').map(function() {
            return {
                order_id: $(this).data('order'),
                product_id: $(this).data('prod')
            };
        }).get();

        if (!selectedProducts.length) {
            alert('Please select at least one product.');
            return;
        }

        $('#selectProductsModal').modal('hide');
        $('#importDestinationModal').modal('show');
    });

    // --- Step 3: Choose destination ---
    $(document).on('click', '.import-destination', function() {
        const destination = $(this).data('dest');
        if (!ordersToImport.length) {
            alert('No orders to import.');
            $('#importDestinationModal').modal('hide');
            return;
        }
		//console.log(ordersToImport,selectedProducts);
		
		// Filter each order's products based on selected checkboxes
		const filteredOrders = ordersToImport.map(order => {
			const selectedForOrder = selectedProducts
				.filter(p => String(p.order_id) === String(order.order_id))
				.map(p => String(p.product_id));

			if (selectedForOrder.length > 0) {
				const updatedItems = (order.items || [])
					.filter(item => selectedForOrder.includes(String(item.product_id)))
					.map(item => {
						const edited = window.editedProducts?.[order.order_id]?.[item.product_id];
						if (edited) {
							item.title = edited.name;
							item.unit_price_ron = edited.price;
							item.product_id = edited.code;
							item.furnizor = edited.furnizor;
							item.disponibilitate = edited.disponibilitate;
						} else {
							const selectF = $(`.edit-furnizor[data-prod="${item.product_id}"]`).val();
							const selectD = $(`.edit-disponibilitate[data-prod="${item.product_id}"]`).val();
							item.furnizor = selectF;
							item.disponibilitate = selectD;
						}
						return item;
					});
					
					// ✅ Add transport if checked
					const transportChecked = $(`.transport-checkbox[data-order="${order.order_id}"]`).is(':checked');
					if (transportChecked) {
						const tData = window.editedTransports?.[order.order_id] || {};
						updatedItems.push({
							product_id: 'transport_' + order.order_id,
							title: tData.name || 'Transport Service',
							unit_price_ron: tData.price || order.shipping_cost_ron || 0,
							quantity: 1,
							furnizor: tData.furnizor || 
									  $(`#edit-transport-fields-${order.order_id} .edit-furnizor`).val(),

							disponibilitate: tData.disponibilitate || 
									  $(`#edit-transport-fields-${order.order_id} .edit-disponibilitate`).val()
						});
					}

				return { ...order, items: updatedItems };
			} else {
				const { items, ...rest } = order;
				return rest;
			}
		});
		
		if (!filteredOrders.length) {
			alert('No products selected to import.');
			$('#importDestinationModal').modal('hide');
			return;
		}

        $.ajax({
            url: "{{ url('/pieseauto/import') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                orders: filteredOrders,
                destination: destination
            },
            success: function(res) {
                alert(res.message || `Orders imported successfully to ${destination}!`);
                $('#importDestinationModal').modal('hide');
	
				if (destination === "UTVIN") {
					window.location.href = "{!! url('/orders?type=utvin') !!}";
				} 
				else if (destination === "TM") {
					window.location.href = "{!! url('/orders') !!}";
				} 
				else if (destination === "External") {
					window.location.href = "{!! url('/comenzi') !!}";
				}
            },
            error: function() {
                alert("Error importing orders!");
                $('#importDestinationModal').modal('hide');
            }
        });
    });
	
	$(document).on('click', '.edit-product', function() {
		const id = $(this).data('product-id');
		$(`#edit-fields-${id}`).slideDown();
	});

	$(document).on('click', '.cancel-edit', function() {
		const id = $(this).data('product-id');
		$(`#edit-fields-${id}`).slideUp();
	});

	$(document).on('click', '.save-edit', function() {
		const id = $(this).data('product-id');
		const newName = $(`#edit-fields-${id} .edit-name`).val();
		const newPrice = $(`#edit-fields-${id} .edit-price`).val();
		const newCode = $(`#edit-fields-${id} .edit-code`).val();
		const newFurnizor = $(`#edit-fields-${id} .edit-furnizor`).val();
		const newDisponibilitate = $(`#edit-fields-${id} .edit-disponibilitate`).val();

		// Update display
		$(`#name-display-${id}`).text(newName);
		$(`#price-display-${id}`).text(newPrice);
		$(`#edit-fields-${id}`).slideUp();

		// Update selectedProducts if already selected
		$('.product-checkbox[data-prod="' + id + '"]').each(function() {
			const orderId = $(this).data('order');
			if (!window.editedProducts) window.editedProducts = {};
			if (!window.editedProducts[orderId]) window.editedProducts[orderId] = {};
			window.editedProducts[orderId][id] = { name: newName, price: newPrice, code: newCode, furnizor: newFurnizor, disponibilitate: newDisponibilitate };
		});
	});
	
	
	// --- Transport Edit Logic ---
	$(document).on('click', '.edit-transport', function() {
		const orderId = $(this).data('order');
		$(`#edit-transport-fields-${orderId}`).slideDown();
	});

	$(document).on('click', '.cancel-transport-edit', function() {
		const orderId = $(this).data('order');
		$(`#edit-transport-fields-${orderId}`).slideUp();
	});

	$(document).on('click', '.save-transport-edit', function() {
		const orderId = $(this).data('order');
		const newName = $(`#edit-transport-fields-${orderId} .edit-transport-name`).val();
		const newPrice = $(`#edit-transport-fields-${orderId} .edit-transport-price`).val();
		const newFurnizor = $(`#edit-transport-fields-${orderId} .edit-furnizor`).val();
		const newDisp = $(`#edit-transport-fields-${orderId} .edit-disponibilitate`).val();

		$(`#transport-name-${orderId}`).text(newName);
		$(`#transport-price-${orderId}`).text(newPrice);
		$(`#edit-transport-fields-${orderId}`).slideUp();

		if (!window.editedTransports) window.editedTransports = {};
		window.editedTransports[orderId] = {
			name: newName, 
			price: newPrice,
			furnizor: newFurnizor,
			disponibilitate: newDisp
		};
	});
});
</script>
@endsection
