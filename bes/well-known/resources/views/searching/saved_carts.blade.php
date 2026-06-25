@extends('layouts.mainappv1')
@section('title', 'Saved Carts')
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
        margin-bottom: 10px;
		margin-top: 13px;
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
            <div class="panel-heading clearfix">
                <h4 class="pull-left" style="margin:0;">Coșuri salvate / Liste de dorințe</h4>
				
				<a href="{{ url()->previous() }}" class="btn btn-default btn-sm pull-right">
					<i class="glyphicon glyphicon-arrow-left"></i> Înapoi
				</a>
            </div>
            
            <div class="panel-body">
                @if($savedCarts->isEmpty())
                    <div class="alert alert-info">
                        Nu au fost găsite coșuri salvate.
                    </div>
                @else
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
				
                    <table class="table table-bordered table-striped" id="wishlistTable">
                        <thead>
                            <tr>
                                <th>Creat la / De</th>
                                <th>Nume</th>
                                <th>Telefon</th>
                                <th>VIN</th>
                                <th>Număr produse</th>
                                <th>Acțiuni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($savedCarts as $cart)
                            <tr class="cart-row" 
                                data-name="{{ strtolower($cart->name) }}" 
                                data-phone="{{ strtolower($cart->phone ?? '') }}" 
                                data-vin="{{ strtolower($cart->vin ?? '') }}">
                                @php
                                    $createdRaw = $cart->created_at;
                                    if (is_numeric($createdRaw)) {
                                        $createdAt = \Carbon\Carbon::createFromTimestamp((int) $createdRaw);
                                    } elseif (!empty($createdRaw)) {
                                        $createdAt = \Carbon\Carbon::parse($createdRaw);
                                    } else {
                                        $createdAt = null;
                                    }

                                    $creatorName = trim((string) (
                                        $cart->user->nume_complet
                                        ?? ($cart->user->nume ?? '')
                                        ?? ($cart->user->username ?? '')
                                    ));
                                    if ($creatorName === '') {
                                        $creatorName = '-';
                                    }
                                @endphp
                                <td>
                                    <div style="font-weight:600;">{{ $creatorName }}</div>
                                    @if($createdAt)
                                        <div>{{ $createdAt->format('d/m/Y') }}</div>
                                        <div>{{ $createdAt->format('H:i') }}</div>
                                    @else
                                        <div>-</div>
                                        <div>-</div>
                                    @endif
                                </td>
                                <td>{{ $cart->name }}</td>
                                <td>{{ $cart->phone ?? '-' }}</td>
                                <td>{{ $cart->vin ?? '-' }}</td>
                                @php
                                    $cartPayload = is_array($cart->cart) ? $cart->cart : [];
                                    $productsCount = 0;
                                    foreach ($cartPayload as $supplierItems) {
                                        if (is_array($supplierItems)) {
                                            $productsCount += count($supplierItems);
                                        }
                                    }
                                @endphp
                                <td>{{ $productsCount }}</td>
                                <td>
									<a href="{{ route('searching.wishlistLoad', $cart->id) }}" class="btn btn-success btn-sm">
										Încarcă
									</a>
									<a href="{{ route('searching.wishlistCreateOffer', $cart->id) }}" class="btn btn-primary {{($cart->alreadygenerated == 0 ? 'btn-sm' : '')}} create-offer-btn" target="_blank">
										@if($cart->alreadygenerated == 0)
											Creează ofertă
										@else
											<i class="glyphicon glyphicon-print"></i>
										@endif
									</a>
									@if($cart->alreadygenerated == 1 && !empty($cart->phone))
										<a href="{{ route('searching.wishlistWhatsApp', $cart->id) }}" 
										   class="btn btn-success" 
										   target="_blank">
											<i class="glyphicon glyphicon-comment" style="color: #fff;"></i>
										</a>
									@endif
                                    <form action="{{ route('searching.wishlistDelete', $cart->id) }}" method="POST" style="display:contents;">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" onclick="return confirm('Ștergeți această listă de dorințe?')">Șterge</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#cartSearchInput').on('keyup', function() {
        var searchText = $(this).val().toLowerCase();
        
        $('.cart-row').each(function() {
            var name = $(this).data('name') || '';
            var phone = $(this).data('phone') || '';
            var vin = $(this).data('vin') || '';
            
            // Check if search text matches name, phone, or VIN
            if (name.indexOf(searchText) !== -1 || 
                phone.indexOf(searchText) !== -1 || 
                vin.indexOf(searchText) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
	
    var produseTable = $('#wishlistTable').DataTable({
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
        pagingType: "simple_numbers",
        // Preserve backend order (latest first) instead of sorting by "Nume".
        order: []
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

    // Offer is generated in a new tab; refresh current list so print/WhatsApp
    // buttons appear once alreadygenerated is updated.
    $(document).on('click', '.create-offer-btn', function() {
        setTimeout(function() {
            window.location.reload();
        }, 1200);
    });
});
</script>
@endsection