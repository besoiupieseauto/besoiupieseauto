@extends('layouts.mainappv1')
@section('title', 'Utilizatori')
<style>
    .dataTables_wrapper .dataTables_filter {
        display: none;
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
    .custom-search-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
     
    }
    .custom-search {
        display: flex;
        align-items: center;
        margin-left: 6%;
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
        width: 500px;
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
    .action-buttons .btn {
        margin-right: 5px;
    }
    .bg-info {
        background-color: #d9edf7;
    }
</style>

@section('content')
<!--<div class="container mt-4" style="width:100%;">-->
    <div class="jumbotron">
        <div class="container-fluid">
            <div class="panel panel-info">
                <div class="panel-heading d-flex justify-content-between align-items-center" style="display:flex; justify-content:space-between;">
                    <h4><i class="glyphicon glyphicon-search"></i> Utilizatori</h4>
                    <button type="button" class="btn btn-info" id="newUserBtn">
                        <span class="glyphicon glyphicon-plus"></span> Utilizatori noi
                    </button>

                    <script>
                        document.getElementById('newUserBtn').addEventListener('click', function() {
                            window.location.href = '{{ route('utilizatori.create') }}';
                        });
                    </script>
                </div>
                <div class="panel-body">
                    <div id="actionMessage"></div>

                    <!-- Custom Search Box Container -->
                    <div class="custom-search-container">
                        <div class="custom-search" style="margin-left:0;">
                            <label for="customSearch">Cauta</label>
                            <input type="text" id="customSearch" placeholder="Utilizatori, nr. factura">
                            <button type="button" id="searchButton"><i class="glyphicon glyphicon-search" style="color: #337ab7;"></i></button>
                        </div>
						
                        <div class="length-control">
                            <!-- DataTables length control will be moved here via JavaScript -->
                        </div>
                    </div>

                    <!-- DataTables Container -->
                    <div class="table-responsive">
                        <table id="utilizatoriTable" class="table table-bordered table-striped">
							<thead>
								<tr class="info">
									<th>ID</th>
									<th>Utilizator</th>
									<th>Nume complet</th>
									<th>Email</th>
									<th>Telefon</th>
									<th>Rol</th>
									<th>Status</th>
									<th>Ultima autentificare</th>
									<th>Acțiune</th>
								</tr>
							</thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!--</div>-->

<!-- Include Bootstrap & jQuery -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with server-side processing
    var table = $('#utilizatoriTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('utilizatori.data') }}",
            data: function(d) {
                d.search_value = $('#customSearch').val();
            }
        },
		columns: [
			{ data: 'Id', name: 'id', title: 'ID' },
			{ data: 'username', name: 'username', title: 'Utilizator' },
			{ data: 'nume_complet', name: 'nume_complet', title: 'Nume complet' },
			{ data: 'email', name: 'email', title: 'Email' },
			{ data: 'telefon', name: 'telefon', title: 'Telefon' },
			{ data: 'rol', name: 'rol', title: 'Rol' },
			{ data: 'active', name: 'active', title: 'Status' },
			{ data: 'last_login', name: 'last_login', title: 'Ultima autentificare' },
			{ data: 'action', name: 'action', title: 'Acțiune', orderable: false, searchable: false }
		],
        order: [[0, 'desc']],
        language: {
            "sProcessing": "Procesează...",
            "sLengthMenu": "", // Removed "Afișează 10 înregistrări pe pagină"
            "sZeroRecords": "Nu s-a găsit nimic",
            "sInfo": "Afișate de la _START_ la _END_ din _TOTAL_ înregistrări",
            "sInfoEmpty": "Afișate de la 0 la 0 din 0 înregistrări",
            "sInfoFiltered": "(filtrate din _MAX_ înregistrări totale)",
            "sInfoPostFix": "",
            "sSearch": "Caută:",
            lengthMenu: "Arată înregistrări _MENU_",
            "sUrl": "",
            "oPaginate": {
                "sFirst": "Prima",
                "sPrevious": "&lt; Prev",
                "sNext": "Next &gt;",
                "sLast": "Ultima"
            }
        },
        dom: '<"top"fl>rt<"bottom"ip>',
		pageLength: 50,
		lengthMenu: [10, 25, 50, 100, 500],
        pagingType: "simple_numbers"
    });
    
    // Move the length control to our custom position
    $('.dataTables_length').detach().appendTo('.length-control');

    // Custom search functionality
    $('#searchButton').on('click', function() {
        table.draw();
    });
    
    $('#customSearch').on('keyup', function() {
        table.draw();
    });
	
	$(document).on('click', '.deleteUser', function(){
		if(!confirm("Ești sigur că vrei să ștergi acest utilizator?")) return;

		let userId = $(this).data('id');

		$.ajax({
			url: "{{ url('utilizatori') }}/" + userId,
			type: 'DELETE',
			data: {
				_token: "{{ csrf_token() }}"
			},
			success: function(response) {
				if(response.success) {
					alert(response.message);
					$('#utilizatoriTable').DataTable().ajax.reload(null, false);
				} else {
					alert('Eroare la ștergere!');
				}
			},
			error: function(xhr) {
				alert('Eroare la ștergere!');
			}
		});
	});
});
</script>
@endsection