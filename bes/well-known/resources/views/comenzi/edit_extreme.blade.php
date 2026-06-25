@extends('layouts.mainapp')
<style>
    #comanda_edit >.btn-group-sm>.btn, .btn-sm {
        padding: 8px 10px !important;
    }

</style>
@section('content')
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4><i class="glyphicon glyphicon-edit"></i> Editare Factura</h4>
        </div>
        <div class="panel-body">
            <!-- Main Form -->
            <form class="form-horizontal" role="form" id="comanda_edit" method="POST" action="{{ route('generate.extreme.invoice.pdf', $comanda->idcomanda) }}">
                @csrf
                @method('POST')
                <input type="hidden" name="from_edit_extreme" value="1">
				<input type="hidden" id="invoice_type" name="invoice_type" value="smartbill">
				<input type="hidden" id="payment_method_hidden" name="payment_method_hidden" value="ordin_plata">
                <div class="form-group row">
                    <label for="client_search" class="col-md-1 control-label">Client</label>
                    <div class="col-md-3">
                        <div class="input-group">
                            <input type="text" class="form-control input-sm" id="client_search" name="client_search" placeholder="Selecteaza un client" value="{{ $client->nume ?? '' }}" required>
                            <input type="hidden" id="id_client" name="id_client" value="{{ $comanda->idclient }}">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#client_nou" style="padding: 5px 10px;">
                                    <span class="glyphicon glyphicon-plus"></span>
                                </button>
                            </span>
                        </div>
                    </div>
                    <label for="telefon" class="col-md-1 control-label">Telefon</label>
                    <div class="col-md-2">
                        <input type="text" class="form-control input-sm" id="telefon" name="telefon" placeholder="Telefon" value="{{ $client->telefon ?? '' }}" readonly>
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
                                @if($employee->EmployeeId === $facturiData['EmployeeID'])
                                    <option selected value="{{ $employee->EmployeeId }}">{{ $employee->FirstName }} {{ $employee->LastName }}</option>
                                @else
                                    <option value="{{ $employee->EmployeeId }}">{{ $employee->FirstName }} {{ $employee->LastName }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <label for="data" class="col-md-1 control-label">Data</label>
                    <div class="col-md-2">
                        <input type="text" class="form-control input-sm" id="data" name="data" placeholder="Data" value="{{ $currentDate }}" readonly>
                    </div>
                    <label for="datascadenta" class="col-md-2 control-label">Data scadenta</label>
                    <div class="col-md-2">
                        <input type="text" class="form-control input-sm" id="datascadenta" name="datascadenta" placeholder="Data scadenta" value="{{ $dueDate }}" readonly>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="id_incasare" class="col-md-1 control-label">Tip incasare</label>
                    <div class="col-md-3">
                        <select class="form-control input-sm" id="id_incasare" name="id_incasare">
                            @foreach($tipPlatas as $tipPlata)
								@if(in_array($tipPlata->id_plata, [3,4]))
									@if($tipPlata->id_plata === $facturiData['tip_incas'])
										<option selected value="{{ $tipPlata->id_plata}}">{{ $tipPlata->denumire }}</option>
									@else
										<option value="{{ $tipPlata->id_plata }}">{{ $tipPlata->denumire }}</option>
									@endif
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-12"  style="margin-bottom: 20px;">
                    <div class="pull-right">
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#productSearchModal">
                            <span class="glyphicon glyphicon-search"></span> Adauga produs
                        </button>
                        <button type="submit" class="btn btn-success" id="salv_date"><span class="glyphicon glyphicon-print"></span> Salvare</button>
                    </div>
                </div>
            </form>

            <div class="clearfix"></div>

            <div class="editare_factura" class='col-md-12' style="margin-top:10px"></div>

            <!-- Invoice Items Table -->
            <div class="row">
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
                        <tbody id="invoice-items-body">
                            @if(count($orderDetails) > 0)
                                @php
                                    $suma_total = 0;
                                    $suma_tva = 0;
                                @endphp
                                @foreach($orderDetails as $detail)
                                    @php
                                        $pret_unitar = $detail->pret / (($detail->TVA + 100)/100);

                                        $pret_unitar_f = number_format($pret_unitar, 2);//Formateo variables
                                        $pret_unitar_r = str_replace(",", "", $pret_unitar_f);//Reemplazo las comas
                                        $valoare = $pret_unitar_r * $detail->cantitate;
                                        $valoare_f = number_format($valoare, 2);//Precio total formateado
                                        $valoare_r = str_replace(",", "", $valoare_f);//Reemplazo las comas

                                        $ctva = $valoare_r * $detail->TVA / 100;//Tva

                                        $suma_total +=$valoare_r;//Sumador
                                        $suma_tva +=$ctva;

                                        // order total
                                        $total_factura = $suma_total + $suma_tva;
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $detail->denumire }}</td>
                                        <td>{{ $detail->um }}</td>
                                        <td>{{ $detail->cantitate }}</td>
                                        <td>{{ number_format($pret_unitar, 2) }}</td>
                                        <td>{{ $valoare_f }}</td>
                                        <td>{{ number_format($ctva, 2) }}</td>
                                        <td>{{ $detail->TVA }}</td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-xs delete-item" data-id="{{ $detail->idprodus }}">
                                                <i class="glyphicon glyphicon-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                @php
                                    $suma_total =0;//Sumador
                                    $suma_tva =0;//Sumador TVA
                                @endphp
                                <tr>
                                    <td colspan="9" class="text-center">No items found</td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="text-right"><strong>SUBTOTAL</strong></td>
                                <td><span id="sub-total-amount">{{ number_format($suma_total, 2, '.', '') }}</span></td>
                                <td><span id="total-tva">{{ number_format($suma_tva, 2, '.', '') }}</span></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="text-right"><strong>TOTAL</strong></td>
                                <td><span id="total-amount">{{ number_format($total_factura, 2) }}</span></td>
                                <td>lei</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

	<!--<div class="modal fade" id="invoiceTypeModal" tabindex="-1" role="dialog" aria-labelledby="invoiceTypeModalLabel">
	  <div class="modal-dialog modal-sm" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal">&times;</button>
			<h4 class="modal-title" id="invoiceTypeModalLabel">Selectează tip factură</h4>
		  </div>
		  <div class="modal-body text-center">
			<p>Alege cum vrei să creezi factura:</p>
			<button type="button" class="btn btn-primary btn-block" id="createInternal">Factura Internă</button>
			<button type="button" class="btn btn-info btn-block" id="createSmartBill">Factura SmartBill</button>

			<div id="paymentMethodSection" style="display:none; margin-top:15px;">
			  <label for="payment_method">Metoda de plată:</label>
			  <select id="payment_method" class="form-control">
				<option value="">Selectează metoda</option>
				<option value="chitanta">Chitanță</option>
				<option value="bon">Bon</option>
				<option value="card">Card</option>
				<option value="ordin_plata">Ordin plată</option>
			  </select>
			  <button type="button" class="btn btn-success btn-block" id="submitSmartBill" style="margin-top:10px;">Creează factura</button>
			</div>

		  </div>
		</div>
	  </div>
	</div>-->


    <!-- Modal pentru cautare produs -->
    <div class="modal fade" id="productSearchModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
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
                                    <input type="text" class="form-control" id="q" placeholder="Cauta produs" onkeyup="loadProductSearch(1)">
                                    <span class="input-group-addon">
                                        <a href="javascript:void(0);">
                                            <span class="glyphicon glyphicon-search" onclick="loadProductSearch(1);"></span>
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
        const orderId = {{ $comanda->idcomanda }};

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
                    $('#cui').val(ui.item.cif_client);
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
                        if (response.message === "SUCCESS") {
                            if (response.found && response.found.length > 0) {
                                var companyData = response.found[0];
                                
                                if (companyData.date_generale) {
                                    // Fill form fields with data from ANAF
                                    $('#adresa_nou').val(companyData.date_generale.adresa || '');
                                    $('#companie_nou_cl').val(companyData.date_generale.denumire || '');
                                    $('#telefon_nou').val(companyData.date_generale.telefon || '');
                                    $('#regcom').val(companyData.date_generale.nrRegCom || '');
                                    $('#cont_banca').val(companyData.date_generale.iban || '');
                                    
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
                        } else {
                            // API returned an error
                            $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> CUI eronat sau serverul ANAF nu funcționează.</div>');
                        }
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
                            $('#cui').val(response.client.cif);
                            
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
            //refreshInvoiceItems();
            
            // When product search modal is shown, load products
            $('#productSearchModal').on('shown.bs.modal', function() {
                $('#q').focus();
                loadProductSearch(1);
            });

            // Delete item button click
            $(document).on('click', '.delete-item', function() {
                if (confirm('Sigur doriți să ștergeți acest produs?')) {
                    let productId = $(this).data('id');
                    deleteOrderItem(productId);
                }
            });
            
            // Search on enter key
            $('#q').keypress(function(e) {
                if (e.which == 13) {
                    loadProductSearch(1);
                    return false;
                }
            });

            // submit form and open the invoice PDF in separate window with url response url
            $('#comanda_edit').on('submit', function(e) {
                e.preventDefault();
                
				submitInvoiceForm();
			});
			
			function submitInvoiceForm() {
                $.ajax({
                    url: $('#comanda_edit').attr('action'),
                    type: 'POST',
                    data: $('#comanda_edit').serialize(),
                    success: function(response) {
                        // Open the invoice in a new window
                        $(".editare_factura").html(response.message);

                        if (response.success == true) {
                            window.open(response.invoice_url, '_blank');

                            setTimeout(function() {
                                location.href = '/comenzi';
                            }, 1000);
                        }
                    },
                    error: function(xhr) {
                        $(".editare_factura").html('<div class="alert alert-danger" role="alert"><button type="button" class="close" data-dismiss="alert">&times;</button><strong>Eroare!</strong> ' + xhr.responseText + '</div>');
                    }
                });
			}
            
        });


        // Product search modal functionality
        function loadProductSearch(page = 1) {
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
                        html += '<td class="col-xs-1">';
                        html += '<div class="pull-right">';
                        html += '<input type="text" class="form-control" style="text-align:right" id="cantitate_' + product.idprodus + '" value="1">';
                        html += '</div></td>';
                        html += '<td class="col-xs-2"><div class="pull-right">';
                        html += '<input type="text" class="form-control" style="text-align:right" id="pret_unitar_' + product.idprodus + '" value="' + product.pret + '">';
                        html += '</div></td>';
                        html += '<td class="text-center"><a class="btn btn-info" href="#" onclick="addProductToOrder(\'' + product.idprodus + '\')"><i class="glyphicon glyphicon-plus"></i></a></td>';
                        html += '</tr>';
                    });
                    
                    // Add pagination
                    if(data.products.length > 0) {
                        html += '<tr>';
                        html += '<td colspan="5"><span class="pull-right"><ul class="pagination pagination-large">';
                        
                        // Previous button
                        if (data.pagination.current_page > 1) {
                            html += '<li><span><a href="javascript:void(0);" onclick="loadProductSearch(' + (data.pagination.current_page - 1) + ')">‹ Prev</a></span></li>';
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
                                html += '<li><a href="javascript:void(0);" onclick="loadProductSearch(' + i + ')">' + i + '</a></li>';
                            }
                        }
                        
                        // Ellipsis
                        if (endPage < data.pagination.total_pages) {
                            html += '<li><a>...</a></li>';
                            html += '<li><a href="javascript:void(0);" onclick="loadProductSearch(' + data.pagination.total_pages + ')">' + data.pagination.total_pages + '</a></li>';
                        }
                        
                        // Next button
                        if (data.pagination.current_page < data.pagination.total_pages) {
                            html += '<li><span><a href="javascript:void(0);" onclick="loadProductSearch(' + (data.pagination.current_page + 1) + ')">Next ›</a></span></li>';
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
                    html += '<td class="col-xs-1"><div class="pull-right">';
                    html += '<input type="text" class="form-control" style="text-align:right" id="cantitate_32066" value="1">';
                    html += '</div></td>';
                    html += '<td class="col-xs-2"><div class="pull-right">';
                    html += '<input type="text" class="form-control" style="text-align:right" id="pret_unitar_32066" value="30">';
                    html += '</div></td>';
                    html += '<td class="text-center"><a class="btn btn-info" href="#" onclick="addProductToOrder(\'32066\')"><i class="glyphicon glyphicon-plus"></i></a></td>';
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
                        //$('#searchProductModal').modal('hide');
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

        // Function to refresh invoice items table
        function refreshInvoiceItems() {
            let order_id = {{ $comanda->idcomanda }};
            console.log('Refreshing product list for order:', order_id);
            
            $.ajax({
                // यहाँ URL को सही करें
                url: '/comenzi/get-order-products/' + order_id,
                type: 'GET',
                success: function(response) {
                    if (response.success && response.products) {
                        console.log('Products received:', response.products.length);
                        updateInvoiceTable(response.products);
                    } else {
                        console.error('Invalid response format:', response);
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
            html += '<th>FURNIZOR</th>';
            html += '<th>CANT.</th>';
            html += '<th>PRET</th>';
            html += '<th>VALOARE</th>';
            html += '<th></th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            if (!products || products.length === 0) {
                html += '<tr><td colspan="8" class="text-center">No items found</td></tr>';
            } else {
                let finalTotal = 0;
                
                $.each(products, function(index, item) {
                    // Simple multiplication without any tax calculation
                    let rowTotal = parseFloat(item.cantitate || 1) * parseFloat(item.pret);
                    finalTotal += rowTotal;
                    
                    html += '<tr>';
                    html += '<td>' + (index + 1) + '</td>';
                    html += '<td>' + (item.denumire || 'Produs necunoscut') + '</td>';
                    html += '<td>' + (item.cod_produs || '') + '</td>';
                    html += '<td>' + (item.furnizor || '__') + '</td>';
                    html += '<td>' + (item.cantitate || 1) + '</td>';
                    html += '<td>' + parseFloat(item.pret).toFixed(2) + '</td>';
                    html += '<td>' + rowTotal.toFixed(2) + '</td>';
                    html += '<td><button type="button" class="btn btn-danger btn-xs delete-product" data-id="' + item.idprodus + '"><i class="glyphicon glyphicon-trash"></i></button></td>';
                    html += '</tr>';
                });
                
                // Add total row
                html += '<tr>';
                html += '<td colspan="6" class="text-right"><strong>TOTAL</strong></td>';
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
                
                $.ajax({url: '/comenzi/delete-order-product',

                    type: 'POST',
                    data: {
                        id_comanda: order_id,
                        id_produs: idprodus,
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

                        // Refresh product list
                        if ($('#productSearchModal').is(':visible')) {
                            loadProductSearch(1);
                        }
                    }
                    else {
                        $('#rezultat_ajax_produs').html('<div class="alert alert-danger">Error saving product: ' + response.message + '</div>');
                    }
                },
                error: function(xhr) {
                    $('#rezultat_ajax_produs').html('<div class="alert alert-danger">Error saving product</div>');
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
    </script>
@endsection