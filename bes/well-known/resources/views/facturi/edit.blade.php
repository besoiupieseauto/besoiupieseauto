@extends('layouts.header_common')
    <style>
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
    <!-- Main Content Area -->
    <div class="clint">
        <div class="panel panel-info" style="margin-left: 20px; margin-right: 20px;">
            <div class="panel-heading">
                <h4><i class="glyphicon glyphicon-edit"></i> Editare Factura</h4>
            </div>
            <div class="panel-body">
                <!-- Main Invoice Form -->
                <form class="form-horizontal" role="form" id="factura_edit" method="POST" action="{{ route('facturi.update_details', $client->OrderID) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group row">
                        <label for="client_search" class="col-md-1 control-label">Client</label>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" class="form-control input-sm" id="client_search" name="client_search" placeholder="Selecteaza un client" value="{{ $client->nume }}" required>
                                <input type="hidden" id="id_client" name="id_client" value="{{ $client->idclienti }}">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#client_nou" style="padding: 8px 10px;">
                                        <span class="glyphicon glyphicon-plus"></span>
                                    </button>
                                </span>
                            </div>
                        </div>
                        <label for="telefon" class="col-md-1 control-label">Telefon</label>
                        <div class="col-md-2">
                            <input type="text" class="form-control input-sm" id="telefon" name="telefon" placeholder="Telefon" value="{{ $client->telefon }}" readonly>
                        </div>
                        <label for="cui" class="col-md-1 control-label">CUI</label>
                        <div class="col-md-3">
                            <input type="text" class="form-control input-sm" id="cui" name="cui" placeholder="CUI" value="{{ $client->cif }}" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label for="vanzator_nou" class="col-md-1 control-label">Agent</label>
                        <div class="col-md-3">
                            <select class="form-control input-sm" id="vanzator_nou" name="vanzator_nou">
                                @foreach($employees as $employee)
                                    @if($employee->EmployeeId === $client->EmployeeID)
                                        <option selected value="{{ $employee->EmployeeId }}">{{ $employee->FirstName }} {{ $employee->LastName }}</option>
                                    @else
                                        <option value="{{ $employee->EmployeeId }}">{{ $employee->FirstName }} {{ $employee->LastName }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <label for="data_factura" class="col-md-1 control-label">Data</label>
                        <div class="col-md-2">
                            <input type="text" class="form-control" id="data_factura" name="data_factura" value="{{ $data_factura }}" readonly>
                        </div>
                        <label for="data_scadenta" class="col-md-2 control-label">Data scadenta</label>
                        <div class="col-md-2">
                            <input type="text" class="form-control" id="data_scadenta" name="data_scadenta" value="{{ $datascadenta }}" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label for="tip_incasare" class="col-md-1 control-label">Tip incasare</label>
                        <div class="col-md-3">
                            <select class="form-control input-sm" id="tip_incasare" name="tip_incasare">
                                @foreach($tipPlatas as $tipPlata)
                                    <option value="{{ $tipPlata->id_plata }}"
                                        {{ $tipPlata->id_plata == $client->tip_incas ? 'selected' : '' }}>
                                        {{ $tipPlata->denumire }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="pull-right">
                            <button type="button" class="btn btn-default" id="adaugaProdusBtnModal">
                                <span class="glyphicon glyphicon-search"></span> Adauga produs
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <span class="glyphicon glyphicon-print"></span> Salveaza
                            </button>
                        </div>
                    </div>
                </form>
                            
                <div class="clearfix"></div>

                <div class="editare_factura" class='col-md-12' style="margin-top:10px"></div><!-- Date ajax -->
                
                <!-- Invoice Items Table -->
                <div class="row" style="margin-top: 20px;">
                    <div class="col-md-12" id="invoice-items-container">
                        <table class="table table-bordered">
                            <thead>
                                <tr class="warning">
                                    <th>Nr. Crt.</th>
                                    <th>PRODUS</th>
                                    <th>UM</th>
                                    <th>CANT.</th>
                                    <th>PRET UNIT.</th>
                                    <th>VALOARE.</th>
                                    <th>TVA</th>
                                    <th>Cota TVA</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $subtotal = 0;
                                $total_tva = 0;
                                $total = 0;
                                @endphp
                                
                                @if($facturidetails->count() > 0)
                                    @foreach($facturidetails as $index => $detail)
                                        @php
                                            //$quantity = abs($detail->Quantity);
											$quantity = $detail->Quantity < 0 ? $detail->Quantity : abs($detail->Quantity);
											
                                            $valoare_f = $detail->UnitPrice * $quantity;
                                            $rowTva = $detail->tva;
                                            $rowTotal = $valoare_f + $rowTva;
                                            
                                            $subtotal += $valoare_f;
                                            $total_tva += $rowTva;
                                            $total += $rowTotal;
                                        @endphp
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                {{ $detail->denumire ?? '' }}
                                            </td>
                                            <td>buc</td>
                                            <td>{{ $quantity }}</td>
                                            <td>{{ number_format($detail->UnitPrice, 2, '.', '') }}</td>
                                            <td>{{ number_format($valoare_f, 2, '.', '') }}</td>
                                            <td>{{ number_format($rowTva, 2, '.', '') }}</td>
                                            <td>{{ $detail->cota_tva }}</td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-xs delete-item" data-id="{{ $detail->ProductId }}">
                                                    <i class="glyphicon glyphicon-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="9" class="text-center">No items found</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td colspan="5" class="text-right"><strong>SUBTOTAL</strong></td>
                                    <td><strong>{{ number_format($subtotal, 2, '.', '') }}</strong></td>
                                    <td><strong>{{ number_format($total_tva, 2, '.', '') }}</strong></td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr>
                                    <td colspan="6" class="text-right"><strong>TOTAL</strong></td>
                                    <td><strong>{{ number_format($total, 2, '.', '') }}</strong></td>
                                    <td colspan="2"><strong>lei</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
               
            </div>
        </div>
    </div>


    <!-- Product Search Modal -->
    <div class="modal fade" id="searchProductModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">Cauta produs</h4>
                </div>
                <div class="modal-body">
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="search-product-input" placeholder="Cauta produs">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" id="search-product-btn" style="padding: 8px 12px;">
                                        <i class="glyphicon glyphicon-search"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#addProductModal">
                                <i class="glyphicon glyphicon-plus"></i> Produs nou
                            </button>
                        </div>
                    </div>
                    
                    <div id="search-results" class="mt-3">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr class="warning">
                                    <th>Produs</th>
                                    <th>Cod Produs</th>
                                    <th><span class="pull-right">Cant.</span></th>
                                    <th><span class="pull-right">Pret</span></th>
                                    <th class="text-center">Adauga</th>
                                </tr>
                            </thead>
                            <tbody id="search-results-body">
                                <!-- Search results will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

        
    <!-- Add New Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title"><i class="glyphicon glyphicon-edit"></i> Produs nou</h4>
                </div>
                <div class="modal-body">
                    <div id="add-product-alerts"></div>
                    <form id="add-product-form" class="form-horizontal">
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Produs</label>
                            <div class="col-sm-9">
                                <textarea class="form-control" id="product-name" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Cod produs</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="product-code" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Pret</label>
                            <div class="col-sm-9">
                                <input type="number" step="0.01" class="form-control" id="product-price" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="save-product-btn">Salveaza</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal Client nou -->
    <div class="modal fade" id="client_nou" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Client nou</h4>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal" method="post" id="frmclient_nou" name="nou_client">
                        <div id="rezultat_ajax_client_nou"></div>
                        
                        <div class="form-group">
                            <label for="companie_nou_cl" class="col-sm-3 control-label">Societate</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="companie_nou_cl" name="companie_nou_cl" placeholder="Denumire" aria-label="Nume societate">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cif_nou_cl" class="col-sm-3 control-label">CUI / CNP</label>
                            <div class="row-sm-12">
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <input type="text" class="form-control input-sm" id="cif_nou_cl" name="cif_nou_cl" placeholder="Cui/CNP" aria-label="Cui/CNP">
                                        <span class="input-group-addon">
                                            <a href="#">
                                                <span class="glyphicon glyphicon-search" id="cauta_anaf"></span>
                                            </a>
                                        </span>
                                    </div>
                                </div>
                                <label for="regcom" class="col-sm-1 control-label">J</label>
                                <div class="col-sm-3">
                                    <input type="text" class="form-control" id="regcom" name="regcom" placeholder="Reg.Com" aria-label="Reg.Com">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cont_banca" class="col-sm-3 control-label">Cont bancar</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="cont_banca" name="cont_banca" placeholder="Cont bancar">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="nume_banca" class="col-sm-3 control-label">Banca</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="nume_banca" name="nume_banca" placeholder="Banca">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="nume_nou_cl" class="col-sm-3 control-label">Nume / Contact</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="nume_nou_cl" name="nume_nou_cl" placeholder="Denumire client" required="">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefon_nou" class="col-sm-3 control-label">Telefon</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="telefon_nou" name="telefon_nou" placeholder="Telefon">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="judet_nou_cl" class="col-sm-3 control-label">Adresa</label>
                            <div class="row-sm-8">
                                <div class="col-sm-3">
                                    <select name="judet_nou_cl" class="form-control" id="judet_nou_cl" aria-label="Judet" required>
                                        <option value="">-- Judet --</option>
                                        @foreach($counties as $county)
                                            <option value="{{ $county->judet }}">{{ $county->judet }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-5">
                                    <select id="localitate_nou_cl" name="localitate_nou_cl" class="form-control input-value" required>
                                        <option value="">Localitate</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="adresa_nou" name="adresa_nou" value="">
                        
                        <div class="form-group">
                            <label for="marca_nou" class="col-sm-3 control-label">Marca masina</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="marca_masina" name="marca_masina" placeholder="Marca masina">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="sasiu_nou" class="col-sm-3 control-label">Serie sasiu</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="sasiu_masina" name="sasiu_masina" placeholder="Serie Sasiu">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="nrmat_nou" class="col-sm-3 control-label">Nr. inmatriculare</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="nrmat_masina" name="nrmat_masina" placeholder="Nr. inmatriculare">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success" id="cauta_date">Salvare</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

    <!-- Main JavaScript -->
    <script>
        const orderId = {{ $client->OrderID }};
        let currentPage = 1;
        let searchTerm = '';

        // Document ready function
        $(document).ready(function() {
			const companie = @json($client->companie);
			if(companie && companie.length > 0){
				$("#tip_incasare option[value='1']").show();
			}else{
				$("#tip_incasare").val("2");
				$("#tip_incasare option[value='1']").hide();
			}
			
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


            // Delete item button click
            $(document).on('click', '.delete-item', function() {
                if (confirm('Sigur doriți să ștergeți acest produs?')) {
                    const productId = $(this).data('id');
                    deleteOrderItem(productId);
                }
            });

            // Save invoice details
            // submit form and show error or success message
            $('#factura_edit').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    beforeSend: function (objeto) {
                        $(".editare_factura").html("Mesaj: Se incarca...");
                    },
                    success: function(response) {
                        // Open the invoice in a new window
                        $(".editare_factura").html(response.message);

                        if (response.success == true) {
                            window.open(response.invoice_url, '_blank');

                            setTimeout(function() {
                                location.href = '/facturi';
                            }, 1000);
                        }
                    },
                    error: function(xhr) {
                        $(".editare_factura").html('Error generating invoice: ' + xhr.responseText);
                    }
                });
            });

            
            // Client search autocomplete
            $("#client_search").autocomplete({
                source: "/ajax/clients/search",
                minLength: 1,
                select: function (event, ui) {
                    event.preventDefault();
                    $('#id_client').val(ui.item.id_client);
                    $('#client_search').val(ui.item.nume_client);
                    $('#telefon').val(ui.item.telefon_client);
                    $('#cui').val(ui.item.cif_client);
					
					if(ui.item.companie_nou_cl && ui.item.companie_nou_cl.length > 0){
						$("#tip_incasare option[value='1']").show();
					}else{
						$("#tip_incasare").val("2");
						$("#tip_incasare option[value='1']").hide();
					}
                }
            });


            // ANAF search functionality
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
            
            // County selection changes
            $('#judet_nou_cl').change(function() {
                let judet = $(this).val();
                if (judet) {
                    $.ajax({
                        url: '/get-localities',
                        type: 'GET',
                        data: { judet: judet },
                        success: function(data) {
                            $('#localitate_nou_cl').empty();
                            $('#localitate_nou_cl').append('<option value="">Localitate</option>');
                            $.each(data, function(key, value) {
                                $('#localitate_nou_cl').append('<option value="' + value + '">' + value + '</option>');
                            });
                        }
                    });
                } else {
                    $('#localitate_nou_cl').empty();
                    $('#localitate_nou_cl').append('<option value="">Localitate</option>');
                }
            });
            
            // New client form submit
            $('#frmclient_nou').on('submit', function(e) {
                e.preventDefault();
                
                // Set address value
                let judet = $('#judet_nou_cl').val();
                let localitate = $('#localitate_nou_cl').val();
                $('#adresa_nou').val('Judet: ' + judet + ', Localitate: ' + localitate);
                
                $.ajax({
                    url: '/storeclient',
                    type: 'POST',
                    data: $(this).serialize() + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            $('#rezultat_ajax_client_nou').html('<div class="alert alert-success">Client added successfully</div>');
                            
                            // Update client fields in main form
                            $('#client_search').val(response.client.nume);
                            $('#id_client').val(response.client.idclienti);
                            $('#telefon').val(response.client.telefon);
                            $('#cui').val(response.client.cif);
                            
                            // Close modal after delay
                            setTimeout(function() {
                                $('#client_nou').modal('hide');
                                $('#rezultat_ajax_client_nou').html('');
                                $('#frmclient_nou')[0].reset();
                            }, 1500);
							
							if(response.client.companie && response.client.companie.length > 0){
								$("#tip_incasare option[value='1']").show();
							}else{
								$("#tip_incasare").val("2");
								$("#tip_incasare option[value='1']").hide();
							}
                        } else {
                            $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger">Error adding client</div>');
                        }
                    },
                    error: function() {
                        $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger">Error adding client</div>');
                    }
                });
            });
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
                    $('#search-results-body').html('<tr><td colspan="4" class="text-center">Se încarcă...</td></tr>');
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
                            html += '<td class="col-xs-1"><div class="pull-right"><input type="text" class="form-control" style="text-align:right" id="cantitate_' + product.idprodus + '" value="1"></div></td>';
                            html += '<td class="col-xs-2"><div class="pull-right"><input type="text" class="form-control pret_unitar_inp" style="text-align:right" id="pret_unitar_' + product.idprodus + '" value="' + product.pret + '"></div></td>';
                            html += '<td class="text-center"><button class="btn btn-info add-product-btn" data-id="' + product.idprodus + '"><i class="glyphicon glyphicon-plus"></i></button></td>';
                            html += '</tr>';
                        });
                        
                        // Add pagination
                        if(data.products.length > 0) {
                            html += '<tr>';
                            html += '<td colspan="5"><span class="pull-right"><ul class="pagination pagination-large">';
                            
                            // Previous button
                            if (data.pagination.current_page > 1) {
                                html += '<li><span><a href="javascript:void(0);" onclick="searchProducts(' + (data.pagination.current_page - 1) + ')">‹ Prev</a></span></li>';
                            } else {
                                html += '<li class="disabled"><span><a>‹ Prev</a></span></li>';
                            }
                            
                            // Page numbers
                            let startPage = Math.max(1, data.pagination.current_page - 2);
                            let endPage = Math.min(data.pagination.total_pages, data.pagination.current_page + 2);
                            
                            for (let i = startPage; i <= endPage; i++) {
                                if (i == data.pagination.current_page) {
                                    html += '<li class="active"><a>' + i + '</a></li>';
                                } else {
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
                            } else {
                                html += '<li class="disabled"><span><a>Next ›</a></span></li>';
                            }
                            
                            html += '</ul></span></td>';
                            html += '</tr>';
                        }

                        // Add transport costs as a fixed option
                        html += '<tr>';
                        html += '<td>CHELTUIELI TRANSPORT</td>';
                        html += '<td> - </td>';
                        html += '<td class="col-xs-1"><div class="pull-right"><input type="text" class="form-control" style="text-align:right" id="cantitate_32066" value="1"></div></td>';
                        html += '<td class="col-xs-2"><div class="pull-right"><input type="text" class="form-control pret_unitar_inp" style="text-align:right" id="pret_unitar_32066" value="30"></div></td>';
                        html += '<td class="text-center"><button class="btn btn-info add-product-btn" data-id="32066"><i class="glyphicon glyphicon-plus"></i></button></td>';
                        html += '</tr>';
                    }
                    else {
                        html = '<tr><td colspan="4" class="text-center">Nu s-au găsit produse</td></tr>';
                    }
                    
                    $('#search-results-body').html(html);
                    
                    // Add event listener for add product buttons
                    $('.add-product-btn').on('click', function() {
                        const productId = $(this).data('id');
                        addProductToOrder(productId);
                    });
                    

                },
                error: function() {
                    $('#search-results-body').html('<tr><td colspan="5" class="text-center text-danger">Eroare la încărcarea produselor</td></tr>');
                    $('#pagination-container').html('');
                }
            });
        }


        // Function to add product to order
        function addProductToOrder(productId) {
            const quantity = $('#cantitate_' + productId).val();
            const price = $('#pret_unitar_' + productId).val();
            const vat = 21; // Default VAT value
            
            $.ajax({
                url: '/orders/' + orderId + '/update-product-tmp',
                type: 'POST',
                data: {
                    id_produs: productId,
                    cantitate: quantity,
                    pret: price,
                    tva: vat,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        $('#searchProductModal').modal('hide');
                        // Refresh updated order items
                        $.ajax({
                            type: "GET",
                            url: '/reload-order-factura-product/1',
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
                            url: '/reload-order-factura-product/1',
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


        function updateInvoiceDetails(invoiceId) {
            $.ajax({
                url: '/facturi/' + invoiceId + '/update-details',
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        alert('Invoice saved successfully');
                        window.location.href = '/facturi';
                    } else {
                        alert('Error updating invoice details: ' + response.message);
                        $('#save-invoice').prop('disabled', false).html('<span class="glyphicon glyphicon-floppy-disk"></span> Salvare');
                    }
                },
                error: function(xhr) {
                    alert('Error updating invoice details');
                    $('#save-invoice').prop('disabled', false).html('<span class="glyphicon glyphicon-floppy-disk"></span> Salvare');
                }
            });
        }

		$(document).ready(function() {
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
		});
    </script>
</body>
</html>