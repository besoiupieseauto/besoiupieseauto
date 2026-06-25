@extends('layouts.mainappv1')

@section('title', 'Produse')

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
<div class="jumbotron">
    <div class="container-fluid">
        <div class="panel panel-info">
            <div class="panel-heading d-flex justify-content-between align-items-center" style="display:flex; justify-content:space-between;">
                <h4><i class="glyphicon glyphicon-search"></i> Produse</h4>
                <div>
                    <button type="button" class="btn btn-info" onclick="showNewProductModal()" style="margin-right: 10px;">
                        <span class="glyphicon glyphicon-plus"></span> Produs Nou
                    </button>
                    <button type="button" class="btn btn-success" onclick="showTVAModal()">
                        <span class="glyphicon glyphicon-tag"></span> TVA
                    </button>
                </div>
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
                        <input type="text" id="customSearch" placeholder="ID, Denumire, Cod Produs, Pret">
                        <button type="button" id="searchButton"><i class="glyphicon glyphicon-search" style="color: #337ab7;"></i></button>
                    </div>
                    <div class="length-control">
                        <!-- DataTables length control will be moved here via JavaScript -->
                    </div>
                </div>

                <!-- DataTables Container -->
                <div class="table-responsive">
                    <table id="produseTable" class="table table-bordered table-striped">
                        <thead>
                            <tr class="info">
                                <th>ID</th>
                                <th>Produs</th>
                                <th>Cod Produs</th>
                                <th>Pret</th>
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

<!-- Product Modal -->
<div class="modal" id="productModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="modalTitle">Produs nou</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" id="frmprodus_nou">
                    @csrf
                    <input type="hidden" name="_method" id="method" value="POST">
                    <input type="hidden" name="idprodus" id="idprodus" value="">
                    
                    <div id="rezultat_ajax_produs_nou"></div>
                    
                    <div class="form-group">
                        <label for="denumire" class="col-sm-3 control-label">Denumire</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="denumire" name="denumire" placeholder="Denumire produs" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cod_produs" class="col-sm-3 control-label">Cod Produs</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="cod_produs" name="cod_produs" placeholder="Cod produs" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="pret" class="col-sm-3 control-label">Pret</label>
                        <div class="col-sm-8">
                            <input type="number" step="0.01" class="form-control" id="pret" name="pret" placeholder="Pret" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
                <button type="button" class="btn btn-success" id="saveProduct">Salvare</button>
            </div>
        </div>
    </div>
</div>

<!-- TVA Modal -->
<div class="modal" id="tvaModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Actualizare TVA</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" id="frmTVA">
                    @csrf
                    <div id="rezultat_ajax_tva"></div>
                    
                    <div class="form-group">
                        <label for="tva_value" class="col-sm-3 control-label">TVA</label>
                        <div class="col-sm-8">
                            <input type="number" step="0.01" class="form-control" id="tva_value" name="tva_value" placeholder="Valoare TVA" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
                <button type="button" class="btn btn-success" id="saveTVA">Salvare</button>
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

// Function to show product modal for new product
function showNewProductModal() {
    resetProductForm();
    document.getElementById('modalTitle').textContent = 'Produs nou';
    
    // Show modal using vanilla JavaScript
    var productModal = document.getElementById('productModal');
    
    // Remove existing backdrop if any
    var existingBackdrop = document.querySelector('.modal-backdrop');
    if (existingBackdrop) {
        existingBackdrop.remove();
    }
    
    var modalBackdrop = document.createElement('div');
    modalBackdrop.className = 'modal-backdrop fade in';
    document.body.appendChild(modalBackdrop);
    productModal.className = 'modal fade in';
    productModal.style.display = 'block';
    document.body.classList.add('modal-open');
}

// Function to show TVA modal
function showTVAModal() {
    // Clear error message
    document.getElementById('rezultat_ajax_tva').innerHTML = '';
    
    // Show modal using vanilla JavaScript
    var tvaModal = document.getElementById('tvaModal');
    
    // Remove existing backdrop if any
    var existingBackdrop = document.querySelector('.modal-backdrop');
    if (existingBackdrop) {
        existingBackdrop.remove();
    }
    
    var modalBackdrop = document.createElement('div');
    modalBackdrop.className = 'modal-backdrop fade in';
    document.body.appendChild(modalBackdrop);
    tvaModal.className = 'modal fade in';
    tvaModal.style.display = 'block';
    document.body.classList.add('modal-open');
    
    // Fetch current TVA value from database
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '{{ url("produse/get-current-tva") }}', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                console.log('TVA Response:', response); // Debug log
                
                var tvaInput = document.getElementById('tva_value');
                if (response && 'tva_value' in response) {
                    // Set the value, even if it's 0 or empty
                    var tvaValue = response.tva_value !== null && response.tva_value !== undefined 
                        ? response.tva_value 
                        : '';
                    tvaInput.value = tvaValue;
                } else {
                    // If response doesn't have tva_value, set to empty
                    tvaInput.value = '';
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                document.getElementById('tva_value').value = '';
            }
        } else {
            console.error('Error loading TVA value. Status:', xhr.status, xhr.statusText);
            document.getElementById('tva_value').value = '';
        }
    };
    xhr.onerror = function() {
        console.error('Request failed');
        document.getElementById('tva_value').value = '';
    };
    xhr.send();
}

// Function to close TVA modal
function closeTVAModal() {
    var tvaModal = document.getElementById('tvaModal');
    tvaModal.className = 'modal fade';
    tvaModal.style.display = 'none';
    document.body.classList.remove('modal-open');
    var modalBackdrop = document.querySelector('.modal-backdrop');
    if (modalBackdrop) {
        modalBackdrop.remove();
    }
}

// Function to reset the product form
function resetProductForm() {
    document.getElementById('frmprodus_nou').reset();
    document.getElementById('method').value = 'POST';
    document.getElementById('idprodus').value = '';
    document.getElementById('rezultat_ajax_produs_nou').innerHTML = '';
}

// Function to edit product
function editProduct(id) {
    document.getElementById('rezultat_ajax_produs_nou').innerHTML = '';
    document.getElementById('method').value = 'PUT';
    document.getElementById('idprodus').value = id;
    document.getElementById('modalTitle').textContent = 'Edit Produs';
    
    // Fetch product data with regular JavaScript
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '{{ url("produse") }}/' + id + '/edit', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        if (xhr.status === 200) {
            var data = JSON.parse(xhr.responseText);
            
            // Populate form fields
            document.getElementById('denumire').value = data.denumire || '';
            document.getElementById('cod_produs').value = data.cod_produs || '';
            document.getElementById('pret').value = data.pret || '';
            
            // Show modal using pure JavaScript
            var productModal = document.getElementById('productModal');
            
            // Remove existing backdrop if any
            var existingBackdrop = document.querySelector('.modal-backdrop');
            if (existingBackdrop) {
                existingBackdrop.remove();
            }
            
            var modalBackdrop = document.createElement('div');
            modalBackdrop.className = 'modal-backdrop fade in';
            document.body.appendChild(modalBackdrop);
            productModal.className = 'modal fade in';
            productModal.style.display = 'block';
            document.body.classList.add('modal-open');
        } else {
            console.error('Error loading product data:', xhr.statusText);
            alert('A apărut o eroare la încărcarea datelor produsului.');
        }
    };
    xhr.onerror = function() {
        console.error('Request failed');
        alert('A apărut o eroare la încărcarea datelor produsului.');
    };
    xhr.send();
}

// Function to delete product
function deleteProduct(id) {
    if (confirm('Sigur doriți să ștergeți acest produs?')) {
        var xhr = new XMLHttpRequest();
        xhr.open('DELETE', '{{ url("produse") }}/' + id, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Reload the table after successful deletion
                    $('#produseTable').DataTable().ajax.reload();
                    showMessage('Produs șters cu succes!', 'success');
                }
            } else {
                console.error('Error deleting product:', xhr.statusText);
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

// Function to close modal
function closeModal() {
    var productModal = document.getElementById('productModal');
    productModal.className = 'modal fade';
    productModal.style.display = 'none';
    document.body.classList.remove('modal-open');
    var modalBackdrop = document.querySelector('.modal-backdrop');
    if (modalBackdrop) {
        modalBackdrop.remove();
    }
}

// Initialize everything when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    var produseTable = $('#produseTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('produse.data') }}",
            type: "GET"
        },
        columns: [
            { data: 'idprodus', name: 'idprodus' },
            { data: 'denumire', name: 'denumire' },
            { data: 'cod_produs', name: 'cod_produs' },
            { data: 'pret', name: 'pret' },
            {
                data: 'action',
                name: 'action',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    return data;
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
        produseTable.search($(this).val()).draw();
    });
    
    $('#searchButton').on('click', function() {
        produseTable.search($('#customSearch').val()).draw();
    });
    
    // Close modal function using pure JavaScript
    var closeButtons = document.querySelectorAll('[data-dismiss="modal"]');
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            // Check which modal is open
            var productModal = document.getElementById('productModal');
            var tvaModal = document.getElementById('tvaModal');
            
            if (productModal && productModal.style.display === 'block') {
                closeModal();
            } else if (tvaModal && tvaModal.style.display === 'block') {
                closeTVAModal();
            }
        });
    });
    
    // Save Product button event listener
    document.getElementById('saveProduct').addEventListener('click', function() {
        // Get form data
        var form = document.getElementById('frmprodus_nou');
        var formData = new FormData(form);
        
        // Get the method and ID
        var method = document.getElementById('method').value;
        var id = document.getElementById('idprodus').value;
        
        // Define the URL based on whether it's a create or update
        var url = '{{ url("produse") }}';
        if (method === 'PUT') {
            url += '/' + id;
            formData.append('_method', 'PUT');
        }
        
        // Send AJAX request
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Close the modal
                    closeModal();
                    
                    // Reload the DataTable
                    $('#produseTable').DataTable().ajax.reload();
                    
                    // Show success message
                    showMessage(method === 'PUT' ? 'Produs actualizat cu succes!' : 'Produs adăugat cu succes!', 'success');
                }
            } else if (xhr.status === 422) {
                // Validation errors
                var errors = JSON.parse(xhr.responseText);
                var errorHtml = '<div class="alert alert-danger"><ul>';
                
                for (var field in errors.errors) {
                    errorHtml += '<li>' + errors.errors[field][0] + '</li>';
                }
                
                errorHtml += '</ul></div>';
                document.getElementById('rezultat_ajax_produs_nou').innerHTML = errorHtml;
            } else {
                console.error('Error:', xhr.statusText);
                document.getElementById('rezultat_ajax_produs_nou').innerHTML =
                    '<div class="alert alert-danger">A apărut o eroare. Verificați datele introduse.</div>';
            }
        };
        
        xhr.onerror = function() {
            console.error('Request failed');
            document.getElementById('rezultat_ajax_produs_nou').innerHTML =
                '<div class="alert alert-danger">A apărut o eroare de conexiune.</div>';
        };
        
        xhr.send(formData);
    });
    
    // Save TVA button event listener
    document.getElementById('saveTVA').addEventListener('click', function() {
        // Get TVA value
        var tvaValue = document.getElementById('tva_value').value;
        
        if (!tvaValue || tvaValue === '') {
            document.getElementById('rezultat_ajax_tva').innerHTML =
                '<div class="alert alert-danger">Vă rugăm introduceți o valoare TVA.</div>';
            return;
        }
        
        // Confirm action
        if (!confirm('Sunteți sigur că doriți să actualizați TVA pentru toate produsele? Această acțiune va actualiza toate înregistrările din tabelul produse.')) {
            return;
        }
        
        // Prepare form data
        var formData = new FormData();
        formData.append('tva_value', tvaValue);
        formData.append('_token', '{{ csrf_token() }}');
        
        // Send AJAX request
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '{{ url("produse/update-all-tva") }}', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Close the modal
                    closeTVAModal();
                    
                    // Reload the DataTable
                    $('#produseTable').DataTable().ajax.reload();
                    
                    // Show success message
                    showMessage('TVA actualizat cu succes pentru toate produsele!', 'success');
                } else {
                    document.getElementById('rezultat_ajax_tva').innerHTML =
                        '<div class="alert alert-danger">' + (response.message || 'A apărut o eroare.') + '</div>';
                }
            } else if (xhr.status === 422) {
                // Validation errors
                var errors = JSON.parse(xhr.responseText);
                var errorHtml = '<div class="alert alert-danger"><ul>';
                
                for (var field in errors.errors) {
                    errorHtml += '<li>' + errors.errors[field][0] + '</li>';
                }
                
                errorHtml += '</ul></div>';
                document.getElementById('rezultat_ajax_tva').innerHTML = errorHtml;
            } else {
                console.error('Error:', xhr.statusText);
                document.getElementById('rezultat_ajax_tva').innerHTML =
                    '<div class="alert alert-danger">A apărut o eroare. Verificați datele introduse.</div>';
            }
        };
        
        xhr.onerror = function() {
            console.error('Request failed');
            document.getElementById('rezultat_ajax_tva').innerHTML =
                '<div class="alert alert-danger">A apărut o eroare de conexiune.</div>';
        };
        
        xhr.send(formData);
    });
});
</script>
@endsection
