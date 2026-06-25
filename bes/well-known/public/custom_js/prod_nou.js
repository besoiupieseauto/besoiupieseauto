// Function to save new product
function saveProduct() {
    let denumire = $('#denumire_input').val();
    let cod = $('#cod_input').val();
    let pret = $('#pret_input').val();
    let tva = 19; // Default to 19%
    
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
                    loadProducts(1);
                }, 1500);
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