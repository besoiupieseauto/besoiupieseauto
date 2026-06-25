@extends('layouts.header_common')

<style>
    #comanda_edit >.btn-group-sm>.btn, .btn-sm {
        padding: 8px 10px !important;
    }
	.navbar-default .navbar-nav>li>a {
		color: #ffffff !important;
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
    <!-- Main Content Area -->
    <div class="clint">
        <div class="panel panel-info" style="margin-left: 20px; margin-right: 20px;">
            <div class="panel-heading">
                <h4><i class="glyphicon glyphicon-edit"></i>Editare Comanda Externa</h4>
            </div>
            <div class="panel-body">
                <!-- Main Form -->
                <form class="form-horizontal" role="form" id="comanda_edit" method="POST" action="{{ route('comenzi.update', $comanda->idcomanda) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group row">
                        <label for="client_search" class="col-md-1 control-label">Client</label>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" class="form-control input-sm" id="client_search" name="client_search" placeholder="Selecteaza un client" value="{{ $client->nume ?? '' }}" required>
                                <input type="hidden" id="id_client" name="id_client" value="{{ $comanda->idclient }}">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#client_nou">
                                        <span class="glyphicon glyphicon-plus"></span>
                                    </button>
                                </span>
                            </div>
                        </div>
                        <label for="telefon" class="col-md-1 control-label">Telefon</label>
                        <div class="col-md-2">
                            <input type="text" class="form-control input-sm" id="telefon" name="telefon" placeholder="Telefon" value="{{ $client->telefon ?? '' }}" readonly>
                        </div>
                        <label for="adresa" class="col-md-1 control-label">Adresa</label>
                        <div class="col-md-3">
                            <input type="text" class="form-control input-sm" id="adresa" name="adresa" placeholder="adresa" value="{{ $client->adresa ?? '' }}" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label for="data" class="col-md-1 control-label">Data</label>
                        <div class="col-md-2">
                            <div class="input-group datepicker" id="datascadenta1" data-date-format="dd/mm/yyyy">
                                <!-- Change input type to text and add the readonly attribute -->
                                <input type="text" class="form-control" id="data" name="data" value="{{ $comanda->data ? \Carbon\Carbon::parse($comanda->data)->format('d/m/Y') : $currentDate }}" readonly>
                                <div class="input-group-addon">
                                    <span class="glyphicon glyphicon-th"></span>
                                </div>
                            </div>
                        </div>
                        <label for="marca" class="col-md-2 control-label">Marca masina</label>
                        <div class="col-md-2">
                            <input type="text" class="form-control input-sm" id="marca" placeholder="Marca masina" value="{{ $client->marca ?? '' }}" readonly>
                            <input id="idmasina_cmd" name="idmasina_cmd" type="hidden" value="{{ $comanda->idmasina ?? 0 }}">
                        </div>
                        <label for="stare" class="col-md-1 control-label">Stare</label>
                        <div class="col-md-3">
                            <select class="form-control input-sm" id="idstare" name="idstare">
                                <option value="1" {{ $comanda->stare == 1 ? 'selected' : '' }}>Comandat</option>
                                <option value="2" {{ $comanda->stare == 2 ? 'selected' : '' }}>Sosit</option>
                                <option value="3" {{ $comanda->stare == 3 ? 'selected' : '' }}>Expediat</option>
                                <option value="4" {{ $comanda->stare == 4 ? 'selected' : '' }}>Achitat</option>
                                <option value="5" {{ $comanda->stare == 5 ? 'selected' : '' }}>Avans</option>
                                <option value="6" {{ $comanda->stare == 6 ? 'selected' : '' }}>Retur</option>
                            </select>
                        </div>
                    </div>
					<div class=" row" style="display:flex; justify-content:center;">
						@if (!$hasRelations)
							<div class="col-md-4 row">
								<label class="col-md-2 control-label">Magazin</label>
								<div class="col-md-4">
									<div class="radio">
										<label>
											<input type="radio" name="locatie_mgz" value="1">
											Timisoara
										</label>
									</div>
									<div class="radio">
										<label>
											<input type="radio" name="locatie_mgz" value="2">
											Utvin
										</label>
									</div>
									<div class="radio">
										<label>
											<input type="radio" name="locatie_mgz" value="3" checked>
											Externe
										</label>
									</div>
								</div>
							</div>
						@endif
						<div class="col-md-6 row">
							<label for="observations" class="col-md-2 control-label">Observații comandă</label>
							<div class="col-md-6">
								<textarea class="form-control" id="observations" name="observations" placeholder="Adăugați observații sau comentarii despre comandă...">{{ $comanda->observations }}</textarea>
							</div>
						</div>
						<div class="col-md-2 row">
						</div>
					</div>
                    
                    <div class="col-md-12" style="margin-bottom: 20px;">
                        <div class="pull-right">
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#myModal">
                                <span class="glyphicon glyphicon-search"></span> Adauga produs
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <span class="glyphicon glyphicon-floppy-disk"></span> Actualizare Date
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Invoice Items Table -->
                <div class="row">
                    <div class="col-md-12" id="invoice-items-container">
                        <div id="invoice-items">
                            <!-- Products will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal pentru cautare produs -->
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel">Cauta produs</h4>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal">
                        <div class="form-group">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="q" placeholder="Cauta produs" onkeyup="load(1)">
                                    <span class="input-group-addon">
                                        <a href="javascript:void(0);">
                                            <span class="glyphicon glyphicon-search" onclick="load(1);"></span>
                                        </a>
                                    </span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#produs_nou">
                                <span class="glyphicon glyphicon-plus"></span> Produs nou
                            </button>
                        </div>
                        <div id="loader" style="position: absolute; text-align: center; top: 55px; width: 100%;"></div>
                        <div class="outer_div">
                            <!-- Product search results will load here -->
                        </div>
                    </form>
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

                        <div class="form-group">
                            <label for="adresa_nou" class="col-sm-3 control-label"></label>
                            <div class="col-sm-8" style="margin-top:5px;">
                                <textarea class="form-control" id="adresa_nou" name="adresa_nou" placeholder="Str., nr., ..." required></textarea>
                            </div>
                        </div>
						
					<div class="form-group">
						<label for="judet_nou_cl" class="col-sm-3 control-label"></label>
						<div class="form-check mb-3">
							<input class="form-check-input" type="checkbox" value="1" name="billing_same_as_delivery" id="same_as_delivery">
							<label class="form-check-label" for="same_as_delivery">
							  Adresa de livrare si facturare sunt la fel
							</label>
						</div>
					</div>
					
					<div id="billing_section">
						<div class="form-group">
							<label for="judet_facturare" class="col-sm-3 control-label">Adresa livrare</label>
							<div class="row-sm-8">
								<div class="col-sm-3">
									<select name="judet_facturare" class="form-control county-select" id="judet_facturare">
										<option value="">-- Judet --</option>
										@foreach($counties as $county)
											<option value="{{ $county->judet }}">{{ $county->judet }}</option>
										@endforeach
									</select>
								</div>
								<div class="col-sm-5">
									<select id="localitate_facturare" name="localitate_facturare" class="form-control">
										<option value="">Localitate</option>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label for="adresa_facturare" class="col-sm-3 control-label"></label>
							<div class="col-sm-8" style="margin-top:5px;">
								<textarea class="form-control" id="adresa_facturare" name="adresa_facturare" placeholder="Str., nr., ..."></textarea>
							</div>
						</div>
                    </div>

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

    <!-- Modal produs nou-->
    <div class="modal fade" id="produs_nou" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="form-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Produs nou</h4>
                </div>
                <div class="modal-body">
                    <div class="form-horizontal">
                        <div id="rezultat_ajax_produs"></div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Produs</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" id="denumire_input" placeholder="Denumire produs" required></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">Cod produs</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="cod_input" placeholder="Cod produs" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Pret</label>
                            <div class="col-sm-8">
                                <input type="number" step="any" class="form-control" id="pret_input" placeholder="Pret unitar" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-success" onclick="saveProduct()">Salveaza</button>
                        </div>
                    </div>
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
        // Product search modal functionality
        function load(page = 1) {
            let query = $('#q').val();
            $('#loader').show();
            
            $.ajax({
                url: '/search-products',
                type: 'GET',
                data: {
                    query: query,
                    page: page
                },
                success: function(data) {
                    let html = '';
                    
                    html += '<div class="table-responsive">';
                    html += '<table class="table table-bordered" style="width:100%">';
                    html += '<tr class="warning">';
                    html += '<th>Produs</th>';
                    html += '<th>Cod Produs</th>';
					html += '<th><span class="pull-right">Furnizor</span></th>';
					html += '<th><span class="pull-right">Disponibilitate</span></th>';
                    html += '<th><span class="pull-right">Cant.</span></th>';
                    html += '<th><span class="pull-right">Pret</span></th>';
                    html += '<th class="text-center" style="width: 36px;">Adauga</th>';
                    html += '</tr>';
                    
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
                        html += '<td class="col-xs-1"><div class="pull-right">';
                        html += '<input type="text" class="form-control" style="text-align:right" id="cantitate_' + product.idprodus + '" value="1">';
                        html += '</div></td>';
                        html += '<td class="col-xs-2"><div class="pull-right">';
                        html += '<input type="text" class="form-control pret_unitar_inp" style="text-align:right" id="pret_unitar_' + product.idprodus + '" value="' + product.pret + '">';
                        html += '</div></td>';
                        html += '<td class="text-center"><a class="btn btn-info" href="#" onclick="adauga(\'' + product.idprodus + '\')"><i class="glyphicon glyphicon-plus"></i></a></td>';
                        html += '</tr>';
                    });
                    
                    // Add pagination
                    if(data.products.length > 0) {
                        html += '<tr>';
                        html += '<td colspan="7"><span class="pull-right"><ul class="pagination pagination-large">';
                        // Previous button
                        if (data.pagination.current_page > 1) {
                            html += '<li><span><a href="javascript:void(0);" onclick="load(' + (data.pagination.current_page - 1) + ')">‹ Prev</a></span></li>';
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
                                html += '<li><a href="javascript:void(0);" onclick="load(' + i + ')">' + i + '</a></li>';
                            }
                        }
                        
                        // Ellipsis
                        if (endPage < data.pagination.total_pages) {
                            html += '<li><a>...</a></li>';
                            html += '<li><a href="javascript:void(0);" onclick="load(' + data.pagination.total_pages + ')">' + data.pagination.total_pages + '</a></li>';
                        }
                        
                        // Next button
                        if (data.pagination.current_page < data.pagination.total_pages) {
                            html += '<li><span><a href="javascript:void(0);" onclick="load(' + (data.pagination.current_page + 1) + ')">Next ›</a></span></li>';
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
					html += `<td class="col-xs-1">-</td>`;
					html += `<td class="col-xs-1">-</td>`;
					html += `<td class="col-xs-1">-<input type="hidden" class="form-control" style="text-align:right" id="cantitate_32066" value="1"></td>`;
/*                     html += '<td class="col-xs-1"><div class="pull-right">';
                    html += '<input type="text" class="form-control" style="text-align:right" id="cantitate_32066" value="1">';
                    html += '</div></td>'; */
                    html += '<td class="col-xs-2"><div class="pull-right">';
                    html += '<input type="text" class="form-control pret_unitar_inp" style="text-align:right" id="pret_unitar_32066" value="30">';
                    html += '</div></td>';
                    html += '<td class="text-center"><a class="btn btn-info" href="#" onclick="adauga(\'32066\')"><i class="glyphicon glyphicon-plus"></i></a></td>';
                    html += '</tr>';
                    html += '</table>';
                    html += '</div>';
                    
                    $('.outer_div').html(html);
                    $('#loader').hide();
                },
                error: function() {
                    $('#loader').hide();
                    alert('Error loading products');
                }
            });
        }

        // Function to add product to invoice with furnizor
        function adauga(idprodus) {
            let cantitate = $('#cantitate_' + idprodus).val();
            let pret = $('#pret_unitar_' + idprodus).val();
            //let tva = $('#tva_' + idprodus).val();
            let tva = 21; // Default to 19% if not provided
            let furnizor = $('#furnizor_' + idprodus).val() || '__';
			let disponibilitate = $('#disponibilitate_' + idprodus).val() || '__';
            let order_id = {{ $comanda->idcomanda }};
            
            // console.log('Adding product:', idprodus, cantitate, pret, tva, furnizor);
            
            $.ajax({
                url: '/orders/add-tmp-product',
                type: 'POST',
                data: {
                    id_comanda: order_id,
                    id_produs: idprodus,
                    cantitate: cantitate,
                    pret: pret,
                    tva: tva,
                    furnizor: furnizor,
					disponibilitate: disponibilitate,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    // console.log('Success response:', response);
                    if (response.success) {
                        //$('#myModal').modal('hide');
                        refreshInvoiceItems();
                        // Manually remove the backdrop if not auto-removed
                        // document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                    }
                    else {
                        alert('Error adding product: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error details:', xhr.responseText);
                    alert('Error adding product. Status: ' + status + ', Error: ' + error);
                }
            });
        }

        // Function to refresh invoice items table
        function refreshInvoiceItems() {
            let order_id = {{ $comanda->idcomanda }};
            console.log('Refreshing product list for order:', order_id);
            
            $.ajax({
                url: '/orders/get-tmp-products',
                type: 'GET',
                success: function(response) {
                    if (response.success && response.products) {
                        // console.log('Products received:', response.products.length);
                        updateInvoiceTable(response.products);
                    }
                    else {
                        // console.error('Invalid response format:', response);
                        alert('Error refreshing product list');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error refreshing invoice items:', xhr.responseText);
                    alert('Error refreshing product list');
                }
            });
        }
        
        // Function to update invoice table with products
        function updateInvoiceTable(products) {
            // For debugging
            console.log("Products data structure:", products.length > 0 ? products[0] : "No products");
            
            let html = '<table class="table table-bordered">';
            html += '<thead>';
            html += '<tr class="warning">';
            html += '<th>Nr. Crt.</th>';
            html += '<th>PRODUS</th>';
            html += '<th>COD PRODUS</th>';
            html += '<th>CANT.</th>';
            html += '<th>PRET</th>';
            html += '<th>VALOARE</th>';
            html += '<th></th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            if (!products || products.length === 0) {
                html += '<tr><td colspan="7" class="text-center">No items found</td></tr>';
            }
            else {
                let finalTotal = 0;
                
                $.each(products, function(index, item) {
                    // Simple multiplication without any tax calculation
                    let rowTotal = parseFloat(item.cantitate_tmp || 1) * parseFloat(item.pret_tmp);
                    finalTotal += rowTotal;
                    
                    html += '<tr>';
                    html += '<td>' + (index + 1) + '</td>';
                    html += '<td>' + (item.ProductName || 'Produs necunoscut') + '</td>';
                    html += '<td>' + (item.cod_produs || '') + '</td>';
                    html += '<td>' + (item.cantitate_tmp || 1) + '</td>';
                    html += '<td>' + parseFloat(item.pret_tmp).toFixed(2) + '</td>';
                    html += '<td>' + rowTotal.toFixed(2) + '</td>';
                    html += '<td><button type="button" class="btn btn-danger btn-xs delete-product" data-id="' + item.id_tmp + '"><i class="glyphicon glyphicon-trash"></i></button></td>';
                    html += '</tr>';
                });
                
                // Add total row
                html += '<tr>';
                html += '<td colspan="5" class="text-right"><strong>TOTAL</strong></td>';
                html += '<td>' + finalTotal.toFixed(2) + '</td>';
                html += '<td></td>';
                html += '</tr>';
            }
            
            html += '</tbody>';
            html += '</table>';
            
            $('#invoice-items').html(html);
            
            // Add event listener for delete buttons
            $('.delete-product').on('click', function() {
                let id = $(this).data('id');
                deleteOrderProduct(id);
            });
        }

        // Function to delete product from order
        function deleteOrderProduct(idprodus) {
            if (confirm('Are you sure you want to delete this product?')) {
                let order_id = {{ $comanda->idcomanda }};
                
                $.ajax({
                    url: '/facturi/delete-tmp-product',
                    type: 'POST',
                    data: {
                        id: idprodus,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            refreshInvoiceItems();
                        }
                        else {
                            alert('Error deleting product: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error deleting product');
                    }
                });
            }
        }

        // Function to save new product
        function saveProduct() {
            let denumire = $('#denumire_input').val();
            let cod = $('#cod_input').val();
            let pret = $('#pret_input').val();
            let tva = 21; // Default to 19%
            
            // Validate inputs
            if (!denumire || !cod || !pret) {
                $('#rezultat_ajax_produs').html('<div class="alert alert-danger">Please fill all fields</div>');
                return;
            }
            
            // Show loading message
            $('#rezultat_ajax_produs').html('<div class="alert alert-info">Saving product...</div>');
            
            $.ajax({
                url: '/storepro',
                type: 'POST',
                data: {
                    denumire: denumire,
                    cod_produs: cod,
                    pret: pret,
                    TVA: tva,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        $('#rezultat_ajax_produs').html('<div class="alert alert-success">Product saved successfully</div>');
                        
                        // Reset form fields
                        $('#denumire_input').val('');
                        $('#cod_input').val('');
                        $('#pret_input').val('');
                        $('#tva_input').val('');
                        
                        // Close modal after delay
                        setTimeout(function() {
                            $('#produs_nou').modal('hide');
                            
                            // Refresh product list
                            if ($('#myModal').is(':visible')) {
                                load(1);
                            }
                        }, 1500);
                    } else {
                        $('#rezultat_ajax_produs').html('<div class="alert alert-danger">Error saving product: ' + response.message + '</div>');
                    }
                },
                error: function(xhr) {
                    $('#rezultat_ajax_produs').html('<div class="alert alert-danger">Error saving product</div>');
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
        
        // Document ready function
        $(document).ready(function() {
            // Client search autocomplete
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
                } else {
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
            
            // New client form submit
            $('#frmclient_nou').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: '/saveClient',
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
                            $('#adresa').val(response.client.adresa);
                            $('#marca').val(response.client.marca);
                            $('#idmasina_cmd').val(0);
                            
                            // Close modal after delay
                            setTimeout(function() {
                                $('#client_nou').modal('hide');
                                $('#rezultat_ajax_client_nou').html('');
                                $('#frmclient_nou')[0].reset();
                            }, 1500);
                        } else {
                            $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger">Error adding client</div>');
                        }
                    },
                    error: function() {
                        $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger">Error adding client</div>');
                    }
                });
            });
            
            // Initialize products when page loads
            refreshInvoiceItems();
            
            // When product search modal is shown, load products
            $('#myModal').on('shown.bs.modal', function() {
                $('#q').focus();
                load(1);
            });

            // Search on enter key
            $('#q').keypress(function(e) {
                if (e.which == 13) {
                    load(1);
                    return false;
                }
            });
			
			
			document.getElementById('same_as_delivery').addEventListener('change', function() {
				document.getElementById('billing_section').style.display = this.checked ? 'none' : 'block';
			});
		
			var billingSameCheckbox = document.getElementById('same_as_delivery');
			billingSameCheckbox.checked = true;
			billingSameCheckbox.dispatchEvent(new Event('change'));
        });
    </script>
</body>
</html>