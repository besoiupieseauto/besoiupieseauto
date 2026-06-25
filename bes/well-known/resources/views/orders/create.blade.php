@extends('layouts.header_common_create')
@section('title', 'Comanda Noua Timisoara| Comenzi')
@section('head')
    <style>
        .main-container { padding: 0; }
        .top-navbar {
            background-color: #f8f8f8;
            border-bottom: 1px solid #e7e7e7;
            padding: 10px 0;
        }
        .nav-btn {
            color: #777;
            padding: 10px 15px;
            text-decoration: none;
            display: inline-block;
        }
        .nav-btn:hover {
            color: #333;
            text-decoration: none;
        }
        .form-panel {
            background-color: #d9edf7;
            border: 1px solid #bce8f1;
            border-radius: 0;
            margin-bottom: 20px;
        }
        .panel-title {
            color: #31708f;
            font-size: 18px;
            padding: 10px 15px;
            background-color: #d9edf7;
            border-bottom: 1px solid #bce8f1;
        }
        .form-container {
            background-color: white;
            padding: 20px;
        }
        .control-label {
            text-align: right;
            padding-top: 7px;
        }
        .form-group { margin-bottom: 15px; }
        .form-control {
            border-radius: 0;
            height: 34px;
        }
        .input-group-btn .btn {
            border-radius: 0;
            height: 34px;
        }
        .action-btn {
            border-radius: 0;
            margin-left: 10px;
        }
        .factura-title {
            background-color: #d9edf7;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
        .factura-title h4 {
            margin: 0;
            color: #31708f;
        }
        .clint {
            background-color:#EEEEEE;
            padding-bottom:50px;
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
		
		.table-bordered>tbody>tr>td, .table-bordered>tbody>tr>th, .table-bordered>tfoot>tr>td, .table-bordered>tfoot>tr>th, .table-bordered>thead>tr>td, .table-bordered>thead>tr>th
		{
			border: 1px solid #ddd !important;
		} 
    </style>
@endsection

@section('content')
    <!-- Main Content Area -->
    <div class="clint">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h4><i class="glyphicon glyphicon-edit"></i> Comanda noua</h4>
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
                <!-- Replace the form tag in create.blade.php -->
                <form class="form-horizontal" role="form" id="comanda_noua" method="POST" action="{{ route('orders.store') }}">
                    @csrf
					<input type="hidden" value="{{$Ordtype ?? ''}}" name="ordertype">
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
								<div class="input-group" id="datascadenta1" data-date-format="dd/mm/yyyy">
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
                            <input type="text" class="form-control input-sm" id="marca" name="marca" placeholder="Marca masina">
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
						<div class=" row" style="display:flex; justify-content:center;">
				
					<div class="col-md-4 row">
						
						<label class="col-md-2 control-label">Magazin</label>
						<div class="col-md-4">
							<div class="radio">
								<label>
									<input type="radio" name="locatie_mgz" value="1" {{ (!empty($Ordtype) && $Ordtype === 'utvin') ? '' : 'checked' }}>
									Timisoara
								</label>
							</div>
							<div class="radio">
								<label>
									<input type="radio" name="locatie_mgz" value="2" {{ (!empty($Ordtype) && $Ordtype === 'utvin') ? 'checked' : '' }}>
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
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#searchProduct">
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
    @include('clients.modals.client_nou')
    @include('produse.modals.cauta_produs')
    @include('produse.modals.produs_nou')
@endsection

@section('page_scripts')
    <!-- Main JavaScript -->
    <script>
        // Document ready function
        $(document).ready(function() {
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
			
			document.getElementById('same_as_delivery').addEventListener('change', function() {
				document.getElementById('billing_section').style.display = this.checked ? 'none' : 'block';
			});
		
			var billingSameCheckbox = document.getElementById('same_as_delivery');
			billingSameCheckbox.checked = true;
			billingSameCheckbox.dispatchEvent(new Event('change'));
			
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
        });
    </script>
    <script type="text/javascript" src="{{ asset('custom_js/comanda_noua.js') }}"></script>
    <script type="text/javascript" src="{{ asset('custom_js/cauta_produs.js') }}"></script>
    <script type="text/javascript" src="{{ asset('custom_js/client_nou.js') }}"></script>
    <script type="text/javascript" src="{{ asset('custom_js/prod_nou.js') }}"></script>
@endsection