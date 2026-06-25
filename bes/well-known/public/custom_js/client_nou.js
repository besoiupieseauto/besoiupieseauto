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
			//if (response.status === 200) {
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
/*             } else {
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

// County selection changes - load localities
$('#judet_nou_cl').change(function() {
    var judet = $(this).val();
    var localitateSelect = $('#localitate_nou_cl');
    
    // Clear current options
    localitateSelect.html('<option value="">Localitate</option>');
    
    if (judet) {
        // Loading indicator
        $('#rezultat_ajax_client_nou').html('<div class="alert alert-info">Se încarcă localitățile...</div>');
        
        // AJAX request to get localities
        $.ajax({
            url: '/get-localities/' + encodeURIComponent(judet),
            type: 'GET',
            //data: { judet: judet },
            success: function(data) {
                localitateSelect.empty();
                localitateSelect.append('<option value="">Localitate</option>');
                
                $.each(data, function(key, value) {
                    localitateSelect.append('<option value="' + value.idlocatie + '">' + value.localitate + '</option>');
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

// Handle new client form submission
$('#frmclient_nou').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: '/saveClient', // Make sure this matches your store client route
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
                $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger">Error adding client: ' + (response.message || '') + '</div>');
            }
        },
        error: function(xhr) {
            console.error('Error details:', xhr.responseText);
            $('#rezultat_ajax_client_nou').html('<div class="alert alert-danger">Error adding client. Please check console for details.</div>');
        }
    });
});