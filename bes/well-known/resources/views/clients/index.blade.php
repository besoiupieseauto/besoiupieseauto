@extends('layouts.mainappv1')
@section('title', 'Clienti')
<style>
    /* Add these styles to match the UI in the image */
    .dataTables_wrapper .dataTables_filter {
        display: none; /* Hide the default search box */
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
    /* Custom search box styling with reduced spacing */
    .custom-search-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0 0 10px 0;
    }
    .custom-search {
        display: flex;
        align-items: center;
        margin-left: 12%; /* Reduced left margin */
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
        width: 600px;
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
</style>

@section('content')
<!--<div class="container mt-4" style="width:100%;">-->
    <div class="jumbotron">
        <div class="container-fluid">
            <div class="panel panel-info">
                <div class="panel-heading d-flex justify-content-between align-items-center" style="display:flex; justify-content:space-between;">
                    <h4><i class="glyphicon glyphicon-search"></i> Clienti</h4>
                    <button type="button" class="btn btn-info" onclick="showNewClientModal()">
                        <span class="glyphicon glyphicon-plus"></span> Client Nou
                    </button>
                </div>
                <div class="panel-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div id="actionMessage"></div>

                    <!-- Custom Search Box Container with reduced spacing -->
                    <div class="custom-search-container">
                        <div class="custom-search" style="margin-left:10%;">
                            <label for="customSearch">Cauta</label>
                            <input type="text" id="customSearch" placeholder="Nume, adresa, telefon, marca, sasiu sau nr. inmat" style->
                            <button type="button" id="searchButton"><i class="glyphicon glyphicon-search" style="color: #337ab7;"></i></button>
                        </div>
                        <div class="length-control">
                            <!-- DataTables length control will be moved here via JavaScript -->
                        </div>
                    </div>

                    <!-- DataTables Container -->
                    <div class="table-responsive">
                        <table id="clientsTable" class="table table-bordered table-striped">
                            <thead>
                                <tr class="info">
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Adresa</th>
                                    <th>Telefon</th>
                                    <th>Marca</th>
                                    <th>Serie Sasiu</th>
                                    <th>Nr. inmat.</th>
                                    <th>Actiune</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTables will populate this -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!--</div>-->

<!-- Client Modal -->
<div class="modal" id="clientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="modalTitle">Client nou</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" id="frmclient_nou">
                    @csrf
                    <input type="hidden" name="_method" id="method" value="POST">
                    <input type="hidden" name="client_id" id="client_id" value="">
                    
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
                                        <a href="#"><span class="glyphicon glyphicon-search" id="cauta_anaf"></span>
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
                                <select name="judet_nou_cl" class="form-control county-select"
									data-locality-target="localitate_nou_cl"
									data-rezultat-target="rezultat_ajax_client_nou"
									id="judet_nou_cl" required>
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
                        <label for="judet_nou_cl" class="col-sm-3 control-label"></label>
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
									<select name="judet_facturare" class="form-control county-select"
										data-locality-target="localitate_facturare"
										data-rezultat-target="rezultat_ajax_client_nou"
										id="judet_facturare" required>
										<option value="">-- Judet --</option>
										@foreach($counties as $county)
											<option value="{{ $county->judet }}">{{ $county->judet }}</option>
										@endforeach
									</select>
								</div>
								<div class="col-sm-5">
									<select id="localitate_facturare" name="localitate_facturare" class="form-control" required>
										<option value="">Localitate</option>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label for="judet_nou_cl" class="col-sm-3 control-label"></label>
							<div class="col-sm-8" style="margin-top:5px;">
								<textarea class="form-control" id="adresa_facturare" name="adresa_facturare" placeholder="Str., nr., ..." required></textarea>
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
					
                    <!--<div class="form-group">
                        <label for="judet_livrare" class="col-sm-3 control-label">Detalii de livrare</label>
                        <div class="row-sm-8">
                            <div class="col-sm-3">
                                <select name="judet_livrare" class="form-control county-select"
									data-locality-target="localitate_livrare"
									data-rezultat-target="rezultat_ajax_client_nou"
									id="judet_livrare" required>
                                    <option value="">-- Judet --</option>
                                    @foreach($counties as $county)
                                        <option value="{{ $county->judet }}">{{ $county->judet }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <select id="localitate_livrare" name="localitate_livrare" class="form-control" required>
                                    <option value="">Localitate</option>
                                </select>
                            </div>
                        </div>
                    </div>
					<div class="form-group">
                        <label for="judet_nou_cl" class="col-sm-3 control-label"></label>
						<div class="col-sm-8" style="margin-top:5px;">
                            <textarea class="form-control" id="adresa_livrare" name="adresa_livrare" placeholder="Str., nr., ..." required></textarea>
                        </div>
                    </div>-->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
                <button type="button" class="btn btn-success" id="saveClient">Salvare</button>
            </div>
        </div>
    </div>
</div>

{{-- DataTables CSS and JS --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap.min.css">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap.min.js"></script>

{{-- AJAX & JavaScript Code --}}
<script>
    // Function to show client modal for new client
    function showNewClientModal() {
        resetClientForm();
        document.getElementById('modalTitle').textContent = 'Client nou';
        
        // Show modal using vanilla JavaScript (matching your edit client function)
        var clientModal = document.getElementById('clientModal');
        var modalBackdrop = document.createElement('div');
        modalBackdrop.className = 'modal-backdrop fade in';
        document.body.appendChild(modalBackdrop);
        clientModal.className = 'modal fade in';
        clientModal.style.display = 'block';
        document.body.classList.add('modal-open');
		
		var billingSameCheckbox = document.getElementById('same_as_delivery');
		billingSameCheckbox.checked = true;
		billingSameCheckbox.dispatchEvent(new Event('change'));
    }
    
    // Function to reset the client form
    function resetClientForm() {
        document.getElementById('frmclient_nou').reset();
        document.getElementById('method').value = 'POST';
        document.getElementById('client_id').value = '';
        document.getElementById('rezultat_ajax_client_nou').innerHTML = '';
        document.getElementById('localitate_nou_cl').innerHTML = "";
    }
    
    // Function to edit client
    function editClient(id) {
        document.getElementById('rezultat_ajax_client_nou').innerHTML = '';
        document.getElementById('method').value = 'PUT';
        document.getElementById('client_id').value = id;
        document.getElementById('modalTitle').textContent = 'Edit Client';
        
        // Fetch client data with regular JavaScript
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/clients/' + id + '/edit', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var data = JSON.parse(xhr.responseText);
				console.log(data);
                
                // Populate form fields
                document.getElementById('nume_nou_cl').value = data.nume || '';
                document.getElementById('adresa_nou').value = data.adresa || '';
                document.getElementById('telefon_nou').value = data.telefon || '';
                document.getElementById('marca_masina').value = data.marca || '';
                document.getElementById('sasiu_masina').value = data.sasiu || '';
                document.getElementById('nrmat_masina').value = data.nr_inmat || '';
                document.getElementById('companie_nou_cl').value = data.companie || '';
                document.getElementById('cif_nou_cl').value = data.cif || '';
                document.getElementById('regcom').value = data.regcom || '';
                document.getElementById('cont_banca').value = data.cont_banca || '';
                document.getElementById('nume_banca').value = data.nume_banca || '';
                
                // Set the county and locality if available
/*                 var judetSelect = document.getElementById('judet_nou_cl');
                if (data.judet) {
                    for (var i = 0; i < judetSelect.options.length; i++) {
                        if (judetSelect.options[i].value === data.judet) {
                            judetSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
                
                var localitateSelect = document.getElementById('localitate_nou_cl');
                localitateSelect.innerHTML = ""; // Removes all options
                if (data.localitate) {
                    // Here you would need to populate the localities dropdown based on the selected county
                    // This would be a separate API call usually
                    
                    // As a placeholder:
                    var option = document.createElement('option');
                    option.value = data.idlocalitate;
                    option.text = data.localitate;
                    option.selected = true;
                    localitateSelect.appendChild(option);
                } */
				
				
				// ------------------ MAIN ADDRESS ------------------
				var judetSelect = document.getElementById('judet_nou_cl');
				if (data.judet_nou_cl) {
					for (var i = 0; i < judetSelect.options.length; i++) {
						if (judetSelect.options[i].value == data.judet_nou_cl) {
							judetSelect.selectedIndex = i;
							break;
						}
					}
				}

				var localitateSelect = document.getElementById('localitate_nou_cl');
				localitateSelect.innerHTML = "";
				if (data.localitate_nou_cl) {
					var option = document.createElement('option');
					option.value = data.idlocalitate;          // from server
					option.text = data.localitate_nou_cl;      // from server
					option.selected = true;
					localitateSelect.appendChild(option);
				}

				// ------------------ BILLING ADDRESS ------------------
				document.getElementById('adresa_facturare').value = data.adresa_facturare || '';

				var judetFacturareSelect = document.getElementById('judet_facturare');
				if (data.judet_facturare) {
					for (var i = 0; i < judetFacturareSelect.options.length; i++) {
						console.log(judetFacturareSelect.options[i].value,data.judet_facturare);
						if (judetFacturareSelect.options[i].value == data.judet_facturare) {
							judetFacturareSelect.selectedIndex = i;
							break;
						}
					}

					judetFacturareSelect.dispatchEvent(new Event('change'));

					setTimeout(function () {
						var localitateFacturareSelect = document.getElementById('localitate_facturare');
						var option = document.createElement('option');
						option.value = data.localitate_facturare;             // from server
						option.text = data.localitate_facturare_nume || '';  // from server
						option.selected = true;
						localitateFacturareSelect.appendChild(option);
					}, 500);
				}

				// ------------------ BILLING SAME AS MAIN ------------------
				var billingSameCheckbox = document.getElementById('same_as_delivery');
				if(billingSameCheckbox){
					var hasBillingValues =
						(data.adresa_facturare && data.adresa_facturare.trim() !== "") ||
						(data.localitate_facturare && data.localitate_facturare.trim() !== "");
					
					if (hasBillingValues) {
						billingSameCheckbox.checked = false;
					} else {
						billingSameCheckbox.checked = true;
					}

					billingSameCheckbox.dispatchEvent(new Event('change'));
				}
				
				
                
                // Show modal using pure JavaScript
                var clientModal = document.getElementById('clientModal');
                var modalBackdrop = document.createElement('div');
                modalBackdrop.className = 'modal-backdrop fade in';
                document.body.appendChild(modalBackdrop);
                clientModal.className = 'modal fade in';
                clientModal.style.display = 'block';
                document.body.classList.add('modal-open');
            } else {
                console.error('Error loading client data:', xhr.statusText);
                alert('A apărut o eroare la încărcarea datelor clientului.');
            }
        };
        xhr.onerror = function() {
            console.error('Request failed');
            alert('A apărut o eroare la încărcarea datelor clientului.');
        };
        xhr.send();
    }
    
    // Function to delete client
    function deleteClient(id) {
        if (confirm('Sigur doriți să ștergeți acest client?')) {
            var xhr = new XMLHttpRequest();
            xhr.open('DELETE', '{{ url("clients") }}/' + id, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Reload the table after successful deletion
                        $('#clientsTable').DataTable().ajax.reload();
                        showMessage('Client șters cu succes!', 'success');
                    }
                } else {
                    console.error('Error deleting client:', xhr.statusText);
                    alert('A apărut o eroare la ștergere.');
                }
            };
            xhr.onerror = function() {
                console.error('Request failed');
                alert('A apărut o eroare la ștergere.');
            };
            xhr.send('_method=DELETE&_token={{ csrf_token() }}');
        }
    }
    
    // Function to show messages
    function showMessage(message, type) {
        var messageDiv = document.getElementById('actionMessage');
        messageDiv.innerHTML = '<div class="alert alert-' + type + '">' + message + '</div>';
        setTimeout(function() {
            messageDiv.innerHTML = '';
        }, 3000);
    }
    
    // Add ANAF search functionality
    document.addEventListener('DOMContentLoaded', function() {
        // ANAF search icon click event
        document.getElementById('cauta_anaf').addEventListener('click', function(e) {
            e.preventDefault();
            
            var cui = document.getElementById('cif_nou_cl').value;
            if (!cui) {
                document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Introduceți CUI/CNP pentru căutare.</div>';
                return;
            }
            
            // Display loading message
            document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-info">Se încarcă datele de la ANAF...</div>';
            
            // Create FormData object
            var formData = new FormData();
            formData.append('cui', cui);
            formData.append('_token', '{{ csrf_token() }}');
            
            // Create and send the request
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '{{ route("anaf.info") }}', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            
			xhr.onload = function() {
				if (xhr.status === 200) {
					var response = JSON.parse(xhr.responseText);

					if (response.found && response.found.length > 0) {
						var companyData = response.found[0];

						if (companyData.date_generale) {
							// Fill form fields with data from ANAF
							document.getElementById('adresa_nou').value = companyData.date_generale.adresa || '';
							document.getElementById('companie_nou_cl').value = companyData.date_generale.denumire || '';
							document.getElementById('telefon_nou').value = companyData.date_generale.telefon || '';
							document.getElementById('regcom').value = companyData.date_generale.nrRegCom || '';
							document.getElementById('cont_banca').value = companyData.date_generale.iban || '';

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
				
						
							document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-success alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Success!</strong> Datele au fost obținute cu succes.</div>';

							setTimeout(function() {
								document.getElementById('rezultat_ajax_client_nou').innerHTML = '';
							}, 3000);
						} else {
							document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-warning alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Atenție!</strong> Nu s-au găsit toate datele pentru acest CUI/CNP.</div>';
						}
					} else {
						document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Nu s-au găsit date pentru acest CUI/CNP.</div>';
					}
				} else {
					document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> A apărut o eroare la conexiunea cu serverul.</div>';
				}
			};
            
            xhr.onerror = function() {
                // Network error
                document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> A apărut o eroare la conexiunea cu serverul.</div>';
            };
            
            // Create url encoded form data
            var params = new URLSearchParams();
            params.append('cui', cui);
            params.append('_token', '{{ csrf_token() }}');
            
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(params.toString());
        });
        
        // County change event to load localities
/*         document.getElementById('judet_nou_cl').addEventListener('change', function() {
            var judet = this.value;
            var localitateSelect = document.getElementById('localitate_nou_cl');
            
            // Clear current options
            localitateSelect.innerHTML = '<option value="">Localitate</option>';
            
            if (judet) {
                // Loading indicator
                document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-info">Se încarcă localitățile...</div>';
                
                // AJAX request to get localities
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '/get-localities/' + encodeURIComponent(judet), true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        var localities = JSON.parse(xhr.responseText);
                        
                        // Add localities to dropdown
                        localities.forEach(function(locality) {
                            var option = document.createElement('option');
                            option.value = locality.idlocatie;
                            option.textContent = locality.localitate;
                            localitateSelect.appendChild(option);
                        });
                        
                        // Clear loading message
                        document.getElementById('rezultat_ajax_client_nou').innerHTML = '';
                    } else {
                        document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Eroare la încărcarea localităților.</div>';
                    }
                };
                
                xhr.onerror = function() {
                    document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Eroare la conexiunea cu serverul.</div>';
                };
                
                xhr.send();
            }
        }); */
		
		var countySelects = document.querySelectorAll('.county-select');
		countySelects.forEach(function(countySelect) {
			countySelect.addEventListener('change', function() {
				var judet = this.value;
				var targetLocalityId = this.dataset.localityTarget; // get id of corresponding locality select
				var localitateSelect = document.getElementById(targetLocalityId);
				var rezultatDivId = this.dataset.rezultatTarget;   // get id of message div
				var rezultatDiv = document.getElementById(rezultatDivId);

				// Clear current options
				localitateSelect.innerHTML = '<option value="">Localitate</option>';

				if (judet) {
					// Loading indicator
					rezultatDiv.innerHTML = '<div class="alert alert-info">Se încarcă localitățile...</div>';

					var xhr = new XMLHttpRequest();
					xhr.open('GET', '/get-localities/' + encodeURIComponent(judet), true);
					xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

					xhr.onload = function() {
						if (xhr.status === 200) {
							var localities = JSON.parse(xhr.responseText);

							localities.forEach(function(locality) {
								var option = document.createElement('option');
								option.value = locality.idlocatie;
								option.textContent = locality.localitate;
								localitateSelect.appendChild(option);
							});

							rezultatDiv.innerHTML = '';
						} else {
							rezultatDiv.innerHTML = '<div class="alert alert-danger">Eroare la încărcarea localităților.</div>';
						}
					};

					xhr.onerror = function() {
						rezultatDiv.innerHTML = '<div class="alert alert-danger">Eroare la conexiunea cu serverul.</div>';
					};

					xhr.send();
				}
			});
		});
        
        // Initialize DataTable
        var clientsTable = $('#clientsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('clients.data') }}",
                type: "GET"
            },
            columns: [
                { data: 'idclienti', name: 'idclienti' },
                { data: 'nume', name: 'nume' },
                { data: 'adresa', name: 'adresa' },
                { data: 'telefon', name: 'telefon' },
                { data: 'marca', name: 'marca' },
                { data: 'sasiu', name: 'sasiu' },
                { data: 'nr_inmat', name: 'nr_inmat' },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<div class="btn-group">' +
                            '<button type="button" onclick="editClient(' + row.idclienti + ')" class="btn btn-default btn-sm" style="background-color: #f8f8f8;"><i class="glyphicon glyphicon-pencil text-primary"></i></button>' +
                            '<button type="button" onclick="deleteClient(' + row.idclienti + ')" class="btn btn-default btn-sm" style="background-color: #f8f8f8;"><i class="glyphicon glyphicon-trash text-danger"></i></button>' +
                            '</div>';
                    }
                }
            ],
            language: {
                processing: "Se încarcă...",
                search: "Cauta:",
                lengthMenu: "Arată înregistrări _MENU_",
                info: "Afișarea _START_ până la _END_ din _TOTAL_ înregistrări",
                infoEmpty: "Afișarea 0 până la 0 din 0 înregistrări",
                infoFiltered: "(filtrate din _MAX_ înregistrări totale)",
                infoPostFix: "",
                loadingRecords: "Se încarcă...",
                zeroRecords: "Nu au fost găsite înregistrări",
                emptyTable: "Nu există date disponibile în tabel",
                paginate: {
                    first: "Prima",
                    previous: "&lt; Prev",
                    next: "Next &gt;",
                    last: "Ultima"
                }
            },
            dom: '<"top"fl>rt<"bottom"ip>',
            pageLength: 50,
			lengthMenu: [10, 25, 50, 100, 500],
            pagingType: "simple_numbers"
        });
        
        // Move the length control to our custom position
        $('.dataTables_length').detach().appendTo('.length-control');
        
        // Setup custom search
        $('#customSearch').on('keyup', function() {
            clientsTable.search($(this).val()).draw();
        });
        
        $('#searchButton').on('click', function() {
            clientsTable.search($('#customSearch').val()).draw();
        });
        
        // Close modal function using pure JavaScript
        var closeButtons = document.querySelectorAll('[data-dismiss="modal"]');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                closeModal();
            });
        });
        
        function closeModal() {
            var clientModal = document.getElementById('clientModal');
            clientModal.className = 'modal fade';
            clientModal.style.display = 'none';
            
            var modalBackdrops = document.querySelectorAll('.modal-backdrop');
            modalBackdrops.forEach(function(backdrop) {
                document.body.removeChild(backdrop);
            });
            
            document.body.classList.remove('modal-open');
        }
        
        // Handle save client button click
        document.getElementById('saveClient').addEventListener('click', function(e) {
            e.preventDefault();
            
            var method = document.getElementById('method').value;
            var clientId = document.getElementById('client_id').value;
            var url = method === 'POST' ? '/saveClient' : '/clients/' + clientId;
            
            // Create form data
            var formData = new FormData(document.getElementById('frmclient_nou'));
            
            // AJAX request
            var xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        closeModal();
                        showMessage('Client salvat cu succes!', 'success');
                        clientsTable.ajax.reload();
                    }
                } else {
                    console.error('Error saving client:', xhr.statusText);
                    document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Eroare la salvare.</div>';
                }
            };
            
            xhr.onerror = function() {
                console.error('Request failed');
                document.getElementById('rezultat_ajax_client_nou').innerHTML = '<div class="alert alert-danger alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><strong>Eroare!</strong> Eroare la salvare.</div>';
            };
            
            // Convert FormData to URL-encoded string
            var params = new URLSearchParams();
            for (var pair of formData.entries()) {
                params.append(pair[0], pair[1]);
            }
            
            // Add _method parameter for PUT requests
            if (method === 'PUT') {
                params.append('_method', 'PUT');
            }
            
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(params.toString());
        });

		
		document.getElementById('same_as_delivery').addEventListener('change', function() {
			document.getElementById('billing_section').style.display = this.checked ? 'none' : 'block';
		});
    });
</script>
@endsection