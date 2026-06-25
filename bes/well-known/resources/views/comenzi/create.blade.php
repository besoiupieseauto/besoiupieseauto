<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Comanda noua</title>
	
		@php
			$theme = \App\Helpers\GlobalHelper::get_setting('theme', 'blue');
		@endphp
		<link rel="stylesheet" href="{{ asset('aftb-theme/' . $theme . '.css') }}">
    <!-- Bootstrap 3 CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <!-- jQuery UI CSS for datepicker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <style>
        .main-container { padding: 0; }
        .top-navbar {
            background-color: #f8f8f8;
            border-bottom: 1px solid #e7e7e7;
            padding: 10px 0;
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
	min-width:85px;
	font-size:12px;
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
</head>
<body>
    <!-- Top Navigation Bar -->
    @include('partials.navbar')
    
    <!-- main content area -->
    <div class="jumbotron">
        <!-- Main Content -->
        <div class="container-fluid">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4><i class="glyphicon glyphicon-edit"></i>Comanda noua</h4>
                </div>
                <div class="panel-body">
                    @if (session('autopartner_order_warning'))
                        <div class="alert alert-warning">
                            <strong>AutoPartner warning:</strong>
                            <br>
                            {!! nl2br(e(session('autopartner_order_warning'))) !!}
                        </div>
                    @endif

                    <!-- Main Form -->
                    <form class="form-horizontal" role="form" id="comanda_noua" method="POST" action="{{ route('comenzi.store') }}">
                        @csrf
                        <div class="form-group row">
                            <label for="client_search" class="col-md-1 control-label">Client</label>
                            <div class="col-md-3">
                                <div class="input-group">
                                    <input type="text" class="form-control input-sm" id="client_search" name="client_search" placeholder="Selecteaza un client" required>
                                    <input type="hidden" id="id_client" name="id_client">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#client_nou">
                                            <span class="glyphicon glyphicon-plus"></span>
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <label for="telefon" class="col-md-1 control-label">Telefon</label>
                            <div class="col-md-2">
                                <input type="text" class="form-control input-sm" id="telefon" name="telefon" placeholder="Telefon" readonly>
                            </div>
                            <label for="adresa" class="col-md-1 control-label">Adresa</label>
                            <div class="col-md-3">
                                <input type="text" class="form-control input-sm" id="adresa" name="adresa" placeholder="adresa" readonly>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="data" class="col-md-1 control-label">Data</label>
                            <div class="col-md-3">
								<div style="display: flex; gap: 10px;">
									<div class="input-group datepicker" id="datascadenta1" data-date-format="dd/mm/yyyy">
										<input type="text" class="form-control" id="data" name="data" value="{{ $currentDate }}" readonly>
										<div class="input-group-addon">
											<span class="glyphicon glyphicon-th"></span>
										</div>
									</div>
									<input type="text" class="form-control" id="created_time" name="created_time" value="{{ date('H:i A') }}" readonly style="width:40%;">
								</div>
                            </div>
							
                            <label for="marca" class="col-md-1 control-label">Marca masina</label>
                            <div class="col-md-2">
                                <input type="text" class="form-control input-sm" id="marca" placeholder="Marca masina" readonly>
                                <input id="idmasina_cmd" name="idmasina_cmd" type="hidden">
                            </div>
                            <label for="stare" class="col-md-1 control-label">Stare</label>
                            <div class="col-md-3">
                                <select class="form-control input-sm" id="idstare" name="idstare">
                                    <option value="1">Comandat</option>
                                    <option value="2">Sosit</option>
                                    <option value="3">Expediat</option>
                                    <option value="4">Achitat</option>
                                </select>
                            </div>
                        </div>
						
						<div class="row" style="display:flex; justify-content:center;">
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
							<div class="col-md-6 row">
								<label for="observations" class="col-md-2 control-label">Observații comandă</label>
								<div class="col-md-6">
									<textarea class="form-control" id="observations" name="observations" placeholder="Adăugați observații sau comentarii despre comandă..."></textarea>
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
                                <button type="submit" class="btn btn-success">
                                    <span class="glyphicon glyphicon-floppy-disk"></span> Salvare
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Invoice Items Table -->
                    <div class="row">
                        <div class="col-md-12" id="invoice-items-container">
                            <div id="invoice-items">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr class="warning">
                                            <th>Nr. Crt.</th>
                                            <th>PRODUS</th>
                                            <th>COD PRODUS</th>
                                            <th>CANT.</th>
                                            <th>PRET</th>
                                            <th>VALOARE.</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="5" class="text-right"><strong>TOTAL</strong></td>
                                            <td>0.00</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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
                    <form class="form-horizontal" id="frmclient_nou" name="nou_client">
                        @csrf <!-- Important: Add CSRF token -->
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
                                            <a href="javascript:void(0);" id="cauta_anaf">
                                                <span class="glyphicon glyphicon-search"></span>
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
                                <input type="text" class="form-control" id="nume_nou_cl" name="nume_nou_cl" placeholder="Denumire client" required>
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
                                    <select id="localitate_nou_cl" name="localitate_nou_cl" class="form-control" required>
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
                            <label for="marca_masina" class="col-sm-3 control-label">Marca masina</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="marca_masina" name="marca_masina" placeholder="Marca masina">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="sasiu_masina" class="col-sm-3 control-label">Serie sasiu</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="sasiu_masina" name="sasiu_masina" placeholder="Serie Sasiu">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="nrmat_masina" class="col-sm-3 control-label">Nr. inmatriculare</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="nrmat_masina" name="nrmat_masina" placeholder="Nr. inmatriculare">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
                            <button type="submit" class="btn btn-success" id="salveaza_client">Salvare</button>
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
			
			var nume = '{{$duplicateOrder->nume ?? ""}}';
			var idclienti = '{{$duplicateOrder->idclienti ?? ""}}';
			var marca = '{{$duplicateOrder->marca ?? ""}}';
			var telefon = '{{$duplicateOrder->telefon ?? ""}}';
			var adresa = '{{$duplicateOrder->adresa ?? ""}}';
			var clientidmasina = '{{$duplicateOrder->clientidmasina ?? ""}}';
			if (nume.length > 0) {
				$('#id_client').val(idclienti);
				$('#client_search').val(nume);
				$('#telefon').val(telefon);
				$('#adresa').val(adresa);
				$('#marca').val(marca);
				$('#idmasina_cmd').val(data.clientidmasina);
			}

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
                var judet = $(this).val();
                var localitateSelect = $('#localitate_nou_cl');
                
                // Clear current options
                localitateSelect.html('<option value="">Localitate</option>');
                
                if (judet) {
                    // Loading indicator
                    $('#rezultat_ajax_client_nou').html('<div class="alert alert-info">Se încarcă localitățile...</div>');
                    
                    // AJAX request to get localities - MATCH THE CORRECT FORMAT FROM index.blade.php
                    $.ajax({
                        // This is the key difference - use the same URL format as in index.blade.php
                        url: '/get-localities/' + encodeURIComponent(judet),
                        type: 'GET',
                        success: function(data) {
                            localitateSelect.empty();
                            localitateSelect.append('<option value="">Localitate</option>');
                            
                            $.each(data, function(key, value) {
                                // Make sure you're accessing the right property
                                localitateSelect.append('<option value="' + value.idlocatie + '">' + value.localitate + '</option>');
                            });
                            
                            // Clear loading message
                            $('#rezultat_ajax_client_nou').html('');
                        },
                        error: function(xhr) {
                            console.error('Error loading localities:', xhr.responseText);
                            $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Eroare la încărcarea localităților.</div>');
                        }
                    });
                }
            });
			
            // County selection changes
            $('#judet_facturare').change(function() {
                var judet = $(this).val();
                var localitateFacturareSelect = $('#localitate_facturare');
                
                // Clear current options
                localitateFacturareSelect.html('<option value="">Localitate</option>');
                
                if (judet) {
                    // Loading indicator
                    $('#rezultat_ajax_client_nou').html('<div class="alert alert-info">Se încarcă localitățile...</div>');
                    
                    // AJAX request to get localities - MATCH THE CORRECT FORMAT FROM index.blade.php
                    $.ajax({
                        // This is the key difference - use the same URL format as in index.blade.php
                        url: '/get-localities/' + encodeURIComponent(judet),
                        type: 'GET',
                        success: function(data) {
                            localitateFacturareSelect.empty();
                            localitateFacturareSelect.append('<option value="">Localitate</option>');
                            
                            $.each(data, function(key, value) {
                                // Make sure you're accessing the right property
                                localitateFacturareSelect.append('<option value="' + value.idlocatie + '">' + value.localitate + '</option>');
                            });
                            
                            // Clear loading message
                            $('#rezultat_ajax_client_nou').html('');
                        },
                        error: function(xhr) {
                            console.error('Error loading localities:', xhr.responseText);
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
                        }
                        else {
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
			
			
			
			document.getElementById('same_as_delivery').addEventListener('change', function() {
				document.getElementById('billing_section').style.display = this.checked ? 'none' : 'block';
			});
		
			var billingSameCheckbox = document.getElementById('same_as_delivery');
			billingSameCheckbox.checked = true;
			billingSameCheckbox.dispatchEvent(new Event('change'));
        });

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
                        }
                        else {
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


        // Function to add product to invoice
        function adauga(idprodus) {
            let cantitate = $('#cantitate_' + idprodus).val();
            let pret = $('#pret_unitar_' + idprodus).val();
            //let tva = $('#tva_' + idprodus).val();
            let tva = 21;
            // Add furnizor field - this is important!
            let furnizor = $('#furnizor_' + idprodus).val() || '__';
			let disponibilitate = $('#disponibilitate_' + idprodus).val() || '__';
            
            console.log('Adding product:', idprodus, cantitate, pret, tva, furnizor);
            
            $.ajax({
                url: '/orders/add-tmp-product',
                type: 'POST',
                data: {
                    id_produs: idprodus,
                    cantitate: cantitate,
                    pret: pret,
                    tva: tva,
                    furnizor: furnizor,
					disponibilitate: disponibilitate,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    console.log('Success response:', response);
                    if (response.success) {
                        // do not Close the modal
                        // $('#myModal').modal('hide');
                        
                        // Refresh the invoice items table
                        refreshInvoiceItems();
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
            $.ajax({
                url: '/orders/get-tmp-products',
                type: 'GET',
                success: function(response) {
                    console.log("Get tmp products response:", response);
                    updateInvoiceTable(response.products, response.total);
                },
                error: function(xhr, status, error) {
                    console.error('Error refreshing invoice items:', xhr.responseText);
                }
            });
        }


        // Function to update invoice table with products
        function updateInvoiceTable(products, total) {
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

            let finalTotal = 0;
            if (!products || products.length === 0) {
                html += '<tr><td colspan="7" class="text-center">No items found</td></tr>';
            }
            else {
                $.each(products, function(index, item) {
                    // Simple multiplication without any tax calculation
                    let rowTotal = parseFloat(item.cantitate_tmp || 1) * parseFloat(item.pret_tmp);
                    finalTotal += rowTotal;

                    html += '<tr>';
                    html += '<td>' + (index + 1) + '</td>';
                    html += '<td>' + item.ProductName + '</td>';
                    html += '<td>' + (item.cod_produs || '') + '</td>';
                    html += '<td>' + (item.cantitate_tmp || 1) + '</td>';
                    html += '<td>' + parseFloat(item.pret_tmp).toFixed(2) + '</td>';
                    html += '<td>' + rowTotal.toFixed(2) + '</td>';
                    html += '<td><button type="button" class="btn btn-danger btn-xs delete-tmp" data-id="' + item.id_tmp + '"><i class="glyphicon glyphicon-trash"></i></button></td>';
                    html += '</tr>';
                });

                // Add total row - using direct sum of item values only, no tax included
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
            $('.delete-tmp').on('click', function() {
                let id = $(this).data('id');
                deleteTmpProduct(id);
            });
        }

        // Function to delete temporary product
        function deleteTmpProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                $.ajax({
                    url: '/facturi/delete-tmp-product',
                    type: 'POST',
                    data: {
                        id: id,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            refreshInvoiceItems();
                        } else {
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
		
		document.addEventListener("DOMContentLoaded", function () {
			const url = new URL(window.location.href);
			const params = url.searchParams;
			if (params.has("from")) {
				refreshInvoiceItems();
				
				params.delete("from");
				window.history.replaceState({}, document.title, url.pathname + (params.toString() ? '?' + params.toString() : ''));
			}
		});
    </script>
</body>
</html>