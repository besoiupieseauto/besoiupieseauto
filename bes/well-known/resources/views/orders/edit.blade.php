@extends('layouts.header_common_order')
@section('title', 'Editare Comanda Timisoara | Comenzi')
<style>
	.navbar-default .navbar-nav>li>a {
		color: #ffffff !important;
	}
	.panel-info>.panel-heading{
		background-color:var(--top-header-color) !important;
		background-image:none !important;
	}
	.panel-heading .pull-right a.btn.btn-success, .panel-heading button.btn.btn-info{
		background-color:var(--box-color) !important;
		background-image:none !important;
		border-color:var(--box-color) !important;
	}
	.btn-group.pull-right .btn, #newInvoiceBtn {
	/*     background-color: var(--box-color) !important;
		background-image:none !important;
		border-color: var(--box-color) !important; */
		color: white!important;
		background-image: none!important;
		padding: 10px 15px!important;
	}
	#orders-table>tbody>tr:nth-of-type(odd), #produseTable>tbody>tr:nth-of-type(odd),  #clientsTable>tbody>tr:nth-of-type(odd),
	#facturiTable>tbody>tr:nth-of-type(odd){
		background-color: var(--td-bg-color) !important;
	}
	#orders-table th, #produseTable th, #clientsTable th, #facturiTable th{
	 border: 2px solid #ddd!important;
    background: var(--main-color) !important;
	color:#455649 !important;
	}
	.navbar-fixed-bottom, .navbar-fixed-top, .navbar-static-top{
		background-color:var(--box-color) !important;
		background-image:none !important;	
		}
		.navbar-default .navbar-nav>.active>a, .navbar-default .navbar-nav>.open>a{
			background-color:var(--highlight-bg-color) !important;
				background-image:none !important;
				color:#455649 !important;
		}
		.navbar-default .navbar-nav>li>a, .navbar-default .navbar-brand{
			color:var(--btn-text-color) !important;
		}
		.table-responsive #orders-table > tbody > tr{
    border-bottom: 4px solid var(--orders-border-color) !important;
	}
	#orders-table > tbody > tr, .table-responsive #orders-table > tbody > tr{
		border-bottom: 4px solid var(--orders-border-color) !important;
	}

	#orders-table > tbody > tr > td {
	  border-bottom: 4px solid var(--orders-border-color) !important;
	}
	.total-wrapper .total-zi, .label-success{
	 background-color:var(--btn-bg-color) !important;
	 }
	 
	 
	 .status-comandat td{
		background-color: var(--td-bg-color) !important;
	}
	.navbar.navbar-inverse.navbar-fixed-bottom, {
		color:#ff !important;
	}
	.pagination>.active>a, .pagination>.active>a:focus, .pagination>.active>a:hover, .pagination>.active>span, .pagination>.active>span:focus, .pagination>.active>span:hover{
		 background-color: var(--pagination-active-bg) !important;
	  border-color: var(--pagination-active-bg) !important;
		color: white!important;
	}
	.navbar-inverse .navbar-text, .navbar-fixed-bottom a, .navbar-fixed-bottom i{
		color:var(--btn-text-color) !important;
	}
	.navbar-right .form-control{
		height:38px !important;
		background-color:var(--highlight-bg-color) !important;
		background-image:none !important;
		border-color:var(--highlight-bg-color) !important;
		box-shadow:none !important;
	}
	@media (min-width:992px) and (max-width:1599px){
ul.nav.navbar-nav li a, .collapse ul.nav.navbar-nav li a{
	        font-size: 12px !important;
        padding: 15px 5px;
    min-width:75px !important;
}
}
.setting-ico{
    display: flex;
    align-items: center;
    justify-content: flex-end;
	}
	.setting-ico a{
    justify-content: end;
    align-items: center;
    display: flex;
    margin-top: 0px !important;
	}
	.setting-ico i{
		font-size:22px !important;
		color:#fff !important;
	}
</style>
@section('content')
    <!-- Main Content Area -->
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4><i class="glyphicon glyphicon-edit"></i> Editare Comanda Timisoara</h4>
        </div>
        <div class="panel-body">
            @include('orders.modals.search_product')
            @include('orders.modals.add_product')
            @include('orders.modals.client_nou')

            <!-- Main Form -->
            <form class="form-horizontal" role="form" id="comanda_edit" method="POST" action="{{ route('orders.update', $order->idcomanda) }}">
                @csrf
                @method('PUT')
                <div class="form-group row">
                    <label for="client_search" class="col-md-1 control-label">Client</label>
                    <div class="col-md-3">
                        <div class="input-group">
                            <input type="text" class="form-control input-sm" id="client_search" name="client_search" placeholder="Selecteaza un client" value="{{ $order->nume }}" required>
                            <input type="hidden" id="id_client" name="id_client" value="{{ $order->idclienti }}">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#client_nou">
                                    <span class="glyphicon glyphicon-plus"></span>
                                </button>
                            </span>
                        </div>
                    </div>
                    <label for="telefon" class="col-md-1 control-label">Telefon</label>
                    <div class="col-md-2">
                        <input type="text" class="form-control input-sm" id="telefon" name="telefon" placeholder="Telefon" value="{{ $order->telefon }}" readonly>
                    </div>
                    <label for="adresa" class="col-md-1 control-label">Adresa</label>
                    <div class="col-md-3">
                        <input type="text" class="form-control input-sm" id="adresa" name="adresa" placeholder="adresa" value="{{ $order->adresa }}" readonly>
                    </div>
                </div>
                
                <div class="form-group row">
                    <label for="data" class="col-md-1 control-label">Data</label>
                    <div class="col-md-2">
                        <div class="input-group datepicker" id="datascadenta1" data-date-format="dd/mm/yyyy">
                            <input type="text" class="form-control" id="data" name="data" value="{{ \Carbon\Carbon::parse($order->data)->format('d/m/Y') }}" readonly>
                            <div class="input-group-addon">
                                <span class="glyphicon glyphicon-th"></span>
                            </div>
                        </div>
                    </div>
                    <label for="marca" class="col-md-2 control-label">Marca masina</label>
                    <div class="col-md-2">
                        <input type="text" class="form-control input-sm" id="marca" name="marca" placeholder="Marca masina" value="{{ $order->marca }}" readonly>
                        <input id="idmasina_cmd" name="idmasina_cmd" type="hidden" value="{{ $order->idmasina }}">
                    </div>
                    <label for="stare" class="col-md-1 control-label">Stare</label>
                    <div class="col-md-3">
                        <select class="form-control input-sm" id="idstare" name="idstare">
                            <option value="1" {{ $order->stare == 1 ? 'selected' : '' }}>Comandat</option>
                            <option value="2" {{ $order->stare == 2 ? 'selected' : '' }}>Sosit</option>
                            <option value="3" {{ $order->stare == 3 ? 'selected' : '' }}>Expediat</option>
                            <option value="4" {{ $order->stare == 4 ? 'selected' : '' }}>Achitat</option>
                        </select>
                    </div>
                </div>
				<div class="row" style="display:flex; justify-content:center;">
					@if (!$hasRelations)
						<div class="col-md-4 row">
							<label class="col-md-2 control-label">Magazin</label>
							<div class="col-md-4">
								<div class="radio">
									<label>
										<input type="radio" name="locatie_mgz" value="1" {{ $order->locatie_mgz == 1 ? 'checked' : '' }}>
										Timisoara
									</label>
								</div>
								<div class="radio">
									<label>
										<input type="radio" name="locatie_mgz" value="2" {{ $order->locatie_mgz == 2 ? 'checked' : '' }}>
										Utvin
									</label>
								</div>
								<div class="radio">
									<label>
										<input type="radio" name="locatie_mgz" value="3">
										Externe
									</label>
								</div>
							</div>
						</div>
					@endif
					<div class="col-md-6 row">
						<label for="observations" class="col-md-2 control-label">Observații comandă</label>
						<div class="col-md-6">
							<textarea class="form-control" id="observations" name="observations" placeholder="Adăugați observații sau comentarii despre comandă...">{{ $order->observations }}</textarea>
						</div>
					</div>
					<div class="col-md-2 row">
					</div>
                </div>
                <div class="col-md-12">
                    <div class="pull-right">
                        <button type="button" class="btn btn-default" id="adaugaProdusBtnModal">
                            <span class="glyphicon glyphicon-search"></span> Adauga produs
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-floppy-disk"></span> Actualizare Date
                        </button>
                    </div>
                </div>
            </form>

            <div class="clearfix"></div>

            <!-- Invoice Items Table -->
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12" id="invoice-items-container">
                    <table class="table table-bordered">
                        <thead>
                            <tr class="warning">
                                <th class='text-center'>Nr. Crt.</th>
                                <th>PRODUS</th>
                                <th>COD PRODUS</th>
                                <th>CANT.</th>
                                <th>PRET</th>
                                <th>VALOARE</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="invoice-items-body">
                            @if(count($orderDetails) > 0)
                                @foreach($orderDetails as $index => $detail)
                                    <tr>
                                        <td class='text-center'>{{ $index + 1 }}</td>
                                        <td>{{ $detail->denumire }}</td>
                                        <td>{{ $detail->cod_produs }}</td>
                                        <td>{{ $detail->cantitate }}</td>
                                        <td>{{ number_format($detail->pret, 2) }}</td>
                                        <td>{{ number_format($detail->cantitate * $detail->pret, 2) }}</td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-xs delete-item" data-id="{{ $detail->idprodus }}">
                                                <i class="glyphicon glyphicon-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="7" class="text-center">No items found</td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right"><strong>TOTAL</strong></td>
                                <td><span id="total-amount">{{ number_format($order->total, 2) }}</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Main JavaScript -->
    <script>
        const orderId = {{ $order->idcomanda }};
        let currentPage = 1;
        let searchTerm = '';
        
        $(document).ready(function() {
            // Open product search modal when clicking the button
            $('#adaugaProdusBtnModal').on('click', function() {
                $('#searchProductModal').modal('show');
                searchProducts(1);
            });
            
            // Product search
            $('#search-product-btn').on('click', function() {
                searchTerm = $('#search-product-input').val();
                searchProducts(1);
            });
            
            // Search when input changes
            $('#search-product-input').on('keyup', function(e) {
                searchTerm = $(this).val();
                searchProducts(1);
                return false;
            });
            
            // Add new product
            $('#save-product-btn').on('click', function() {
                const productName = $('#product-name').val();
                const productCode = $('#product-code').val();
                const productPrice = $('#product-price').val();
                const productVat = 21;
                
                if (!productName || !productCode || !productPrice) {
                    $('#add-product-alerts').html('<div class="alert alert-danger">Completați toate câmpurile obligatorii.</div>');
                    return;
                }
                
                $.ajax({
                    url: '/storepro',
                    type: 'POST',
                    data: {
                        denumire: productName,
                        cod_produs: productCode,
                        pret: productPrice,
                        TVA: productVat,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#add-product-alerts').html('<div class="alert alert-success">Produsul a fost adăugat cu succes.</div>');
                            setTimeout(function() {
                                $('#addProductModal').modal('hide');
                                $('#add-product-form')[0].reset();
                                $('#add-product-alerts').html('');
                                searchProducts(1);
                            }, 1500);
                        } else {
                            $('#add-product-alerts').html('<div class="alert alert-danger">Eroare: ' + response.message + '</div>');
                        }
                    },
                    error: function(xhr) {
                        $('#add-product-alerts').html('<div class="alert alert-danger">A apărut o eroare. Încercați din nou.</div>');
                    }
                });
            });
            
            $('#cauta_anaf').on('click', function(e) {
                e.preventDefault();
                
                var cui = $('#cif_nou_cl').val();
                
                if (!cui) {
                    $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Introduceți CUI/CNP pentru căutare.</div>');
                    return;
                }
                
                // Display loading message
                $('#rezultat_ajax_client_nou').html('<div class="alert alert-info">Se încarcă datele de la ANAF...</div>');
                
                // AJAX request to get ANAF data
                $.ajax({
                    url: '/anaf-info', // Make sure this matches your route
                    type: 'POST',
                    data: {
                        cui: cui,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        //if (response.message === "SUCCESS") {
                            if (response.found && response.found.length > 0) {
                                var companyData = response.found[0];
                                
                                if (companyData.date_generale) {
                                    // Fill form fields with data from ANAF
                                    $('#adresa_nou').val(companyData.date_generale.adresa || '');
                                    $('#companie_nou_cl').val(companyData.date_generale.denumire || '');
                                    $('#telefon_nou').val(companyData.date_generale.telefon || '');
                                    $('#regcom').val(companyData.date_generale.nrRegCom || '');
                                    $('#cont_banca').val(companyData.date_generale.iban || '');
									
									
									// ------------------ MAIN ADDRESS ------------------
									var judetSelect = document.getElementById('judet_nou_cl');
									if (companyData.coduri_postale.dcod_Postal.judet) {
										var county = companyData.coduri_postale.dcod_Postal.judet.normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // remove diacritics
										for (var i = 0; i < judetSelect.options.length; i++) {
											if (judetSelect.options[i].value == county) {
												judetSelect.selectedIndex = i;
												
												var event = new Event('change', { bubbles: true });
												judetSelect.dispatchEvent(event);
												break;
											}
										}
									}
									
									var localitateSelect = document.getElementById('localitate_nou_cl');
									if (companyData.coduri_postale.dcod_Postal.localitate) {
										setTimeout(function () {
											var localitate = companyData.coduri_postale.dcod_Postal.localitate.normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // remove diacritics
											for (var i = 0; i < localitateSelect.options.length; i++) {
												if (localitateSelect.options[i].text == localitate) {
													localitateSelect.selectedIndex = i;
													
													var event = new Event('change', { bubbles: true });
													localitateSelect.dispatchEvent(event);
													break;
												}
											}
										}, 800);
									}
							
                                    
                                    // Success message
                                    $('#rezultat_ajax_client_nou').html('<div class="alert alert-success alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Success!</strong> Datele au fost obținute cu succes.</div>');
                                    
                                    // Clear message after 3 seconds
                                    setTimeout(function() {
                                        $('#rezultat_ajax_client_nou').html('');
                                    }, 3000);
                                } else {
                                    // Display warning if general data not found
                                    $('#rezultat_ajax_client_nou').html('<div class="alert alert-warning alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Atenție!</strong> Nu s-au găsit toate datele pentru acest CUI/CNP.</div>');
                                }
                            } else {
                                // No data found for this CUI
                                $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Nu s-au găsit date pentru acest CUI/CNP.</div>');
                            }
                        /* } else {
                            // API returned an error
                            $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> CUI eronat sau serverul ANAF nu funcționează.</div>');
                        } */
                    },
                    error: function() {
                        // Network error
                        $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> A apărut o eroare la conexiunea cu serverul.</div>');
                    }
                });
            });

            // Initialize autocomplete for client search
            $("#client_search").autocomplete({
                source: "/ajax/clients/search",
                minLength: 1,
                select: function (event, ui) {
                    event.preventDefault();
                    $('#id_client').val(ui.item.id_client);
                    $('#client_search').val(ui.item.nume_client);
                    $('#telefon').val(ui.item.telefon_client);
                    $('#adresa').val(ui.item.adresa_client);
                    $('#marca').val(ui.item.marca_client);
                    $('#idmasina_cmd').val(ui.item.idmasina_client);
                }
            });


            // County selection changes
            $('#judet_nou_cl').change(function() {
                let judet = $(this).val();
                if (judet) {
                    $.ajax({
                        url: '/get-localities/' + encodeURIComponent(judet),
                        type: 'GET',
                        success: function(data) {
                            $('#localitate_nou_cl').empty();
                            $('#localitate_nou_cl').append('<option value="">Localitate</option>');
                            $.each(data, function(key, value) {
                                $('#localitate_nou_cl').append('<option value="' + value.idlocatie + '">' + value.localitate + '</option>');
                            });
                        }
                    });
                }
                else {
                    $('#localitate_nou_cl').empty();
                    $('#localitate_nou_cl').append('<option value="">Localitate</option>');
                }
            });
			
			// County selection changes - load localities
			$('#judet_facturare').change(function() {
				var judet = $(this).val();
				var localitateFacturareSelect = $('#localitate_facturare');
				
				// Clear current options
				localitateFacturareSelect.html('<option value="">Localitate</option>');
				
				if (judet) {
					// Loading indicator
					$('#rezultat_ajax_client_nou').html('<div class="alert alert-info">Se încarcă localitățile...</div>');
					
					// AJAX request to get localities
					$.ajax({
						url: '/get-localities/' + encodeURIComponent(judet),
						type: 'GET',
						//data: { judet: judet },
						success: function(data) {
							localitateFacturareSelect.empty();
							localitateFacturareSelect.append('<option value="">Localitate</option>');
							
							$.each(data, function(key, value) {
								localitateFacturareSelect.append('<option value="' + value.idlocatie + '">' + value.localitate + '</option>');
							});
							
							// Clear loading message
							$('#rezultat_ajax_client_nou').html('');
						},
						error: function() {
							$('#rezultat_ajax_client_nou').html('<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Eroare la încărcarea localităților.</div>');
						}
					});
				}
			});
            
            // Client form submission
            $('#frmclient_nou').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: '/saveClient',
                    type: 'POST',
                    data: $(this).serialize() + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
                    success: function(response) {
                        if (response.success) {
                            $('#rezultat_ajax_client_nou').html('<div class="alert alert-success">Client adăugat cu succes</div>');
                            
                            $('#client_search').val(response.client.nume);
                            $('#id_client').val(response.client.idclienti);
                            $('#telefon').val(response.client.telefon);
                            $('#adresa').val(response.client.adresa);
                            $('#marca').val(response.client.marca);
                            $('#idmasina_cmd').val(0);

                            setTimeout(function() {
                                $('#client_nou').modal('hide');
                                $('#rezultat_ajax_client_nou').html('');
                                $('#frmclient_nou')[0].reset();
                            }, 1500);
                        }
                        else {
                            $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger">Eroare la adăugarea clientului</div>');
                        }
                    },
                    error: function() {
                        $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger">Eroare la adăugarea clientului</div>');
                    }
                });
            });
            
            // Delete item button click
            $(document).on('click', '.delete-item', function() {
                if (confirm('Sigur doriți să ștergeți acest produs?')) {
                    const productId = $(this).data('id');
                    deleteOrderItem(productId);
                }
            });
			
			document.getElementById('same_as_delivery').addEventListener('change', function() {
				document.getElementById('billing_section').style.display = this.checked ? 'none' : 'block';
			});
		
			var billingSameCheckbox = document.getElementById('same_as_delivery');
			billingSameCheckbox.checked = true;
			billingSameCheckbox.dispatchEvent(new Event('change'));
        });
        
        // Function to search products
        function searchProducts(page) {
            currentPage = page;
            
            $.ajax({
                url: '/search-products',
                type: 'GET',
                data: {
                    query: searchTerm,
                    page: page
                },
                beforeSend: function() {
                    $('#search-results-body').html('<tr><td colspan="7" class="text-center">Se încarcă...</td></tr>');
                },
                success: function(data) {
                    let html = '';
                    
                    if (data.products && data.products.length > 0) {
                        $.each(data.products, function(index, product) {
                            // Use product's TVA value or default to 19
                            let tva = product.TVA !== null ? product.TVA : 21;
                            
                            html += '<tr>';
                            html += '<td>' + product.denumire + '</td>';
                            html += '<td>' + product.cod_produs + '</td>';
                            html += `<td class="col-xs-1">
										<div class="pull-right">
											<select class="form-control" id="furnizor_` + product.idprodus + `">
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
										</div>
									</td>`;
                            html += `<td class="col-xs-1">
										<div class="pull-right">
											<select class="form-control" id="disponibilitate_` + product.idprodus + `">
												<option value="7CFC00">Azi</option>
												<option value="ADD8E6">Maine</option>
												<option value="F5A000">2 zile</option>
												<option value="FF0000">+3 zile</option>
												<option value="FFFFFF">Sosit</option>
											</select>
										</div>
									</td>`;
                            html += '<td class="col-xs-1"><div class="pull-right"><input type="text" class="form-control" style="text-align:right" id="cantitate_' + product.idprodus + '" value="1"></div></td>';
                            html += '<td class="col-xs-2"><div class="pull-right"><input type="text" class="form-control pret_unitar_inp" style="text-align:right" id="pret_unitar_' + product.idprodus + '" value="' + product.pret + '"></div></td>';
                            html += '<td class="text-center"><button class="btn btn-info add-product-btn" data-id="' + product.idprodus + '"><i class="glyphicon glyphicon-plus"></i></button></td>';
                            html += '</tr>';
                        });
                        
                        //pagination starts
                        html += '<tr>';
                        html += '<td colspan="7"><span class="pull-right"><ul class="pagination pagination-large">';
                        
                        // Previous button
                        if (data.pagination.current_page > 1) {
                            html += '<li><span><a href="javascript:void(0);" onclick="searchProducts(' + (data.pagination.current_page - 1) + ')">‹ Prev</a></span></li>';
                        }
                        else {
                            html += '<li class="disabled"><span><a>‹ Prev</a></span></li>';
                        }
                        
                        // Page numbers
                        let startPage = Math.max(1, data.pagination.current_page - 2);
                        let endPage = Math.min(data.pagination.total_pages, data.pagination.current_page + 2);
                        
                        for (let i = startPage; i <= endPage; i++) {
                            if (i == data.pagination.current_page) {
                                html += '<li class="active"><a>' + i + '</a></li>';
                            }
                            else {
                                html += '<li><a href="javascript:void(0);" onclick="searchProducts(' + i + ')">' + i + '</a></li>';
                            }
                        }
                        
                        // Ellipsis
                        if (endPage < data.pagination.total_pages) {
                            html += '<li><a>...</a></li>';
                            html += '<li><a href="javascript:void(0);" onclick="searchProducts(' + data.pagination.total_pages + ')">' + data.pagination.total_pages + '</a></li>';
                        }
                        
                        // Next button
                        if (data.pagination.current_page < data.pagination.total_pages) {
                            html += '<li><span><a href="javascript:void(0);" onclick="searchProducts(' + (data.pagination.current_page + 1) + ')">Next ›</a></span></li>';
                        }
                        else {
                            html += '<li class="disabled"><span><a>Next ›</a></span></li>';
                        }
                        
                        html += '</ul></span></td>';
                        html += '</tr>';
                        //pagination ends

                        // Add transport costs as a fixed option
                        /* html += '<tr>';
                        html += '<td>CHELTUIELI TRANSPORT</td>';
                        html += '<td>-</td>';
						html += `<td class="col-xs-1">
									<div class="pull-right">
										<select class="form-control" id="furnizor_32066">
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
										</select>
									</div>
								</td>`;
						html += `<td class="col-xs-1">
									<div class="pull-right">
										<select class="form-control" id="disponibilitate_32066">
											<option value="7CFC00">Azi</option>
											<option value="ADD8E6">Maine</option>
											<option value="FF0000">&gt;2 zile</option>
											<option value="FFFFFF">Sosit</option>
										</select>
									</div>
								</td>`;
                        html += '<td class="col-xs-1"><div class="pull-right"><input type="text" class="form-control" style="text-align:right" id="cantitate_32066" value="1"></div></td>';
                        html += '<td class="col-xs-2"><div class="pull-right"><input type="text" class="form-control pret_unitar_inp" style="text-align:right" id="pret_unitar_32066" value="30"></div></td>';
                        html += '<td class="text-center"><button class="btn btn-info add-product-btn" data-id="32066"><i class="glyphicon glyphicon-plus"></i></button></td>';
                        html += '</tr>'; */
                    }
                    else {
                        html = '<tr><td colspan="7" class="text-center">Nu s-au găsit produse</td></tr>';
                    }
                    
                    $('#search-results-body').html(html);
                    
                    // Add event listener for add product buttons
                    $('.add-product-btn').on('click', function() {
                        const productId = $(this).data('id');
                        addProductToOrder(productId);
                    });
                },
                error: function() {
                    $('#search-results-body').html('<tr><td colspan="7" class="text-center text-danger">Eroare la încărcarea produselor</td></tr>');
                }
            });
        }
        
        // Function to add product to order
        function addProductToOrderNOTUSED(productId) {
            const quantity = $('#cantitate_' + productId).val();
            const price = $('#pret_unitar_' + productId).val();
            const furnizor = $('#furnizor_' + productId).val();
            const disponibilitate = $('#disponibilitate_' + productId).val();
            const vat = 21;//$('#tva_' + productId).val();
            
            $.ajax({
                url: '/orders/' + orderId + '/update-product',
                type: 'POST',
                data: {
                    id_produs: productId,
                    cantitate: quantity,
                    pret: price,
                    furnizor: furnizor,
                    disponibilitate: disponibilitate,
                    tva: vat,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        $('#searchProductModal').modal('hide');
                        // Refresh page to show updated order items
                        window.location.reload();
                    } else {
                        alert('Eroare: ' + response.message);
                    }
                },
                error: function(xhr) {
                    alert('Eroare la adăugarea produsului');
                    console.error(xhr.responseText);
                }
            });
        }


        // Function to add product to order
        function addProductToOrder(productId) {
            const quantity = $('#cantitate_' + productId).val();
            const price = $('#pret_unitar_' + productId).val();
            const furnizor = $('#furnizor_' + productId).val();
            const disponibilitate = $('#disponibilitate_' + productId).val();
            const vat = 21; // Default VAT value
            
            $.ajax({
                url: '/orders/' + orderId + '/update-product-tmp',
                type: 'POST',
                data: {
                    id_produs: productId,
                    cantitate: quantity,
                    pret: price,
					furnizor: furnizor,
                    disponibilitate: disponibilitate,
                    tva: vat,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        // Refresh updated order items
                        $.ajax({
                            type: "GET",
                            url: '/reload-order-product',
                            beforeSend: function (objeto) {
                                $("#invoice-items-container").html("Mesaj: Se incarca...");
                            },
                            success: function (date) {
                                $("#invoice-items-container").html(date);
                            }
                        });
                    }
                    else {
                        alert('Eroare: ' + response.message);
                    }
                },
                error: function(xhr) {
                    alert('Eroare la adăugarea produsului');
                    console.error(xhr.responseText);
                }
            });
        }


        // Function to delete order item
        function deleteOrderItem(productId) {
            $.ajax({
                url: '/orders/' + orderId + '/delete-product-tmp',
                type: 'POST',
                data: {
                    id_produs: productId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        // Refresh updated order items
                        $.ajax({
                            type: "GET",
                            url: '/reload-order-product',
                            beforeSend: function (objeto) {
                                $("#invoice-items-container").html("Mesaj: Se incarca...");
                            },
                            success: function (date) {
                                $("#invoice-items-container").html(date);
                            }
                        });
                    } else {
                        alert('Eroare: ' + response.message);
                    }
                },
                error: function(xhr) {
                    alert('Eroare la ștergerea produsului');
                    console.error(xhr.responseText);
                }
            });
        }
		
		$(document).on('input', '.pret_unitar_inp', function(){
			var fullId = $(this).attr('id');
			var idprodus = fullId.replace('pret_unitar_', '');
			
			$.ajax({
				url: '/update-produse-price',
				type: 'POST',
				data: {
					idprodus: idprodus,
					price: $(this).val().trim() ? $(this).val().trim() : 0,
					_token: $('meta[name="csrf-token"]').attr('content')
				},
				beforeSend: function() {
					// console.log('Sending request with token:', $('meta[name="csrf-token"]').attr('content'));
				},
				success: function(response) {
					// console.log('Success response:', response);
					if (!response.success) {
						alert('Error adding product: ' + response.message);
					}
				},
				error: function(xhr, status, error) {
					let message = 'Unknown error';
					
					// If the response is JSON, parse it
					if (xhr.responseJSON) {
						if (xhr.responseJSON.message) {
							message = xhr.responseJSON.message;
						} else if (xhr.responseJSON.errors) {
							// Grab first error message from validation errors
							const firstField = Object.keys(xhr.responseJSON.errors)[0];
							message = xhr.responseJSON.errors[firstField][0];
						}
					}

					alert('Error adding product: ' + message);
				}
			});
		});
    </script>
@endsection