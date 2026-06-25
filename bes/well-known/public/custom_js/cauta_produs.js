// Product search modal functionality
function loadProducts(page = 1) {
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
            html += '<table class="table table-bordered table-hover" style="width:100%">';
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
                let tva = product.TVA !== null ? product.TVA : 19;
                
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
                    html += '<li><span><a href="javascript:void(0);" onclick="loadProducts(' + (data.pagination.current_page - 1) + ')">‹ Prev</a></span></li>';
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
                        html += '<li><a href="javascript:void(0);" onclick="loadProducts(' + i + ')">' + i + '</a></li>';
                    }
                }
                
                // Ellipsis
                if (endPage < data.pagination.total_pages) {
                    html += '<li><a>...</a></li>';
                    html += '<li><a href="javascript:void(0);" onclick="loadProducts(' + data.pagination.total_pages + ')">' + data.pagination.total_pages + '</a></li>';
                }
                
                // Next button
                if (data.pagination.current_page < data.pagination.total_pages) {
                    html += '<li><span><a href="javascript:void(0);" onclick="loadProducts(' + (data.pagination.current_page + 1) + ')">Next ›</a></span></li>';
                }
                else {
                    html += '<li class="disabled"><span><a>Next ›</a></span></li>';
                }
                
                html += '</ul></span></td>';
                html += '</tr>';
            }
            
            // Add transport costs as a fixed option
/*             html += '<tr>';
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
            html += '<td class="col-xs-1"><div class="pull-right">';
            html += '<input type="text" class="form-control" style="text-align:right" id="cantitate_32066" value="1">';
            html += '</div></td>';
            html += '<td class="col-xs-2"><div class="pull-right">';
            html += '<input type="text" class="form-control pret_unitar_inp" style="text-align:right" id="pret_unitar_32066" value="30">';
            html += '</div></td>';
            html += '<td class="text-center"><a class="btn btn-info" href="#" onclick="adauga(\'32066\')"><i class="glyphicon glyphicon-plus"></i></a></td>';
            html += '</tr>'; */
            
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
    let tva = $('#tva_' + idprodus).val();
    let furnizor = $('#furnizor_' + idprodus).val() || '__';
    let disponibilitate = $('#disponibilitate_' + idprodus).val() || '__';
    
    // console.log('Adding product:', idprodus, cantitate, pret, tva, furnizor);
    
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
        beforeSend: function() {
            // console.log('Sending request with token:', $('meta[name="csrf-token"]').attr('content'));
        },
        success: function(response) {
            // console.log('Success response:', response);
            if (response.success) {
                //$('#searchProduct').modal('hide');
                refreshInvoiceItems();
            }
            else {
                alert('Error adding product: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            // console.error('Error details:', xhr.responseText);
            alert('Error adding product. Status: ' + status + ', Error: ' + error);
        }
    });
}

// Function to refresh invoice items table
function refreshInvoiceItems() {
    $.ajax({
        url: '/orders/get-tmp-products', // इसे बदला गया है
        type: 'GET',
        success: function(response) {
            // console.log("Get tmp products response:", response);
            updateInvoiceTable(response.products, response.total, response.subtotal, response.tva);
        },
        error: function(xhr, status, error) {
            // console.error('Error refreshing invoice items:', xhr.responseText);
        }
    });
}

// Function to delete a temporary product from the invoice
function deleteTmpProduct(id) {
    if (confirm('Are you sure you want to delete this product?')) {
        $.ajax({
            url: '/orders/delete-tmp-product',
            type: 'POST',
            data: {
                id: id,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            beforeSend: function (objeto) {
                $("#invoice-items").html("Mesaj: Se incarca...");
            },
            success: function(response) {
                if (response.success) {
                    refreshInvoiceItems();
                }
                else {
                    $("#invoice-items").html('Error deleting product: ' + response.message);
                }
            },
            error: function() {
                $("#invoice-items").html('Error deleting product!!!');
            }
        });
    }
}

// Function to update invoice table with products
function updateInvoiceTable(products, total) {
    // For debugging
    // console.log("Products data structure:", products.length > 0 ? products[0] : "No products");
    
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
        $.each(products, function(index, item) {
            // Simple multiplication without any tax calculation
            let rowTotal = parseFloat(item.cantitate_tmp || 1) * parseFloat(item.pret_tmp);
            
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
    }
    
    // Just use the raw total provided, or calculate from products without tax
    // This ensures no TVA/VAT is accidentally included
    let finalTotal = 0;
    if (products && products.length > 0) {
        $.each(products, function(index, item) {
            finalTotal += parseFloat(item.cantitate_tmp || 1) * parseFloat(item.pret_tmp || 0);
        });
    }
    
    // Add total row - using direct sum of item values only, no tax included
    html += '<tr>';
    html += '<td colspan="5" class="text-right"><strong>TOTAL</strong></td>';
    html += '<td>' + finalTotal.toFixed(2) + '</td>';
    html += '<td></td>';
    html += '</tr>';
    
    html += '</tbody>';
    html += '</table>';
    
    $('#invoice-items').html(html);
    
    // Add event listener for delete buttons
    $('.delete-tmp').on('click', function() {
        let id = $(this).data('id');
        deleteTmpProduct(id);
    });
}

// When product search modal is shown, load products
$('#searchProduct').on('shown.bs.modal', function() {
    $('#q').focus();
    loadProducts(1);
});

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
	
    if (params.has("duplicate")) {
        refreshInvoiceItems();
    }
    if (params.has("from")) {
        refreshInvoiceItems();
		
		params.delete("from");
        window.history.replaceState({}, document.title, url.pathname + (params.toString() ? '?' + params.toString() : ''));
    }
});