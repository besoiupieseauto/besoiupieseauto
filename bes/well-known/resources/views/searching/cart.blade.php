@extends('layouts.mainappv1')
@section('title', 'My Cart')
<style>
#cartTable td {
    vertical-align: middle;
}
</style>
@section('content')
<div class="jumbotron">
    <div class="container" style="width: 1250px;">
		<div class="panel panel-info">
			<div class="panel-heading clearfix">
				<h4 class="pull-left" style="margin:0;">
					<i class="glyphicon glyphicon-shopping-cart"></i> Cart
				</h4>
				
				<a href="{{ url()->previous() }}" class="btn btn-default btn-sm pull-right">
					<i class="glyphicon glyphicon-arrow-left"></i> Înapoi
				</a>
				<button
					type="button"
					class="btn btn-info btn-sm pull-right"
					style="margin-right:8px;"
					data-toggle="modal"
					data-target="#excludedAutototalItemsModal">
					<i class="glyphicon glyphicon-list-alt"></i> Excluse AutoTotal
				</button>
			</div>

			<div class="panel-body">
				@if(session('success'))
					<div class="alert alert-success">{{ session('success') }}</div>
				@endif
				@if(session('warning'))
					<div class="alert alert-warning">
						<div><strong>{{ session('warning') }}</strong></div>
						@php
							$skippedItems = session('skipped_items', []);
						@endphp
						@if(is_array($skippedItems) && !empty($skippedItems))
							<ul style="margin-top:8px; margin-bottom:0; padding-left:18px;">
								@foreach($skippedItems as $skippedItem)
									<li>{{ $skippedItem }}</li>
								@endforeach
							</ul>
						@endif
						@php
							$skippedApiDebug = session('skipped_api_debug', []);
						@endphp
						@if(is_array($skippedApiDebug) && !empty($skippedApiDebug))
							<div style="margin-top:10px;">
								<strong>API raw debug (wishlist load)</strong>
								@foreach($skippedApiDebug as $idx => $debugEntry)
									<details style="margin-top:6px;">
										<summary style="cursor:pointer;">
											#{{ $idx + 1 }}
											| supplier={{ $debugEntry['supplier'] ?? '-' }}
											| reason={{ $debugEntry['reason'] ?? '-' }}
											| search={{ $debugEntry['search_code'] ?? '-' }}
										</summary>
										<pre style="margin-top:6px; max-height:300px; overflow:auto;">{{ json_encode($debugEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
									</details>
								@endforeach
							</div>
						@endif
					</div>
				@endif
				@if(session('error'))
					<div class="alert alert-danger">{{ session('error') }}</div>
				@endif
				@if(empty($cart))
					<div class="alert alert-info">
						Coșul tău este gol
					</div>
				@else
					<table class="table table-bordered" id="cartTable">
						<thead>
							<tr>
								<th width="100">Comandă</th>
								<th>Marcă</th>
								<th>Produs</th>
								<th>Cod</th>
								<th>Disponibilitate</th>
								<!--<th>Punct livrare</th>
								<th>Variant</th>-->
								<th>Preț</th>
								<th width="120">Cantitate</th>
								<th>Total</th>
								<th width="80">Elimină</th>
								<th width="70">Exclus</th>
							</tr>
						</thead>
							<tbody>
							@foreach($cart as $supplier => $items)
								<tr class="info">
									<td colspan="10">
										<strong>@if($supplier === 'site_produse')Magazin propriu (Produse)@else{{ strtoupper($supplier) }}@endif</strong>
									</td>
								</tr>

								@foreach($items as $key => $item)
									@php
										$manufacturerRaw = (string) ($item['manufacturer'] ?? '');
										$manufacturerDisplay = preg_replace('/\s*-\s*(OEM|AM)\s*$/i', '', $manufacturerRaw) ?? $manufacturerRaw;
									@endphp
									<tr>
										<td class="text-center">
											<input
												type="checkbox"
												class="order-item-checkbox"
												checked
												value="{{ $supplier }}|{{ $key }}"
												title="Bifează pentru a include acest produs la comandă">
										</td>
										<td>
											<input
												type="text"
												class="form-control manufacturer-input"
												data-supplier="{{ $supplier }}"
												data-key="{{ $key }}"
												value="{{ $manufacturerDisplay }}"
											>
										</td>
										<td>
											<input
												type="text"
												class="form-control product-name-input"
												data-supplier="{{ $supplier }}"
												data-key="{{ $key }}"
												value="{{ $item['product_name'] ?? '' }}"
											>
										</td>
										<td>{{ $item['product_code'] }}</td>
										<td>{!! $item['plantname'] !!} 
											@php
												$livrare = $item['livrare'] ?? '-';
												$depozit = $item['depozit'] ?? '-';
												$lineQty = max(1, (int) ($item['qty'] ?? 1));
												$supplierLower = strtolower((string) ($item['supplier'] ?? ''));

												// Autototal / Autonet: show first N warehouses where N = min(qty, number of slots).
												// Qty 1 → first department only; qty 2+ → up to all listed (same as full depozit when qty covers them).
												if (in_array($supplierLower, ['autototal', 'autonet'], true) && $depozit !== '-' && $depozit !== '') {
													$parts = preg_split('/\s*\+\s*/u', $depozit) ?: [];
													$parts = array_values(array_filter(array_map('trim', $parts), static function ($p) {
														return $p !== '';
													}));
													if (count($parts) > 1) {
														$take = min($lineQty, count($parts));
														$depozit = implode(' + ', array_slice($parts, 0, $take));
													}
												}

												// Capitalize first letter of livrare
												if (!empty($livrare) && $livrare !== '-') {
													$livrare = ucfirst(trim(ltrim($livrare, '/')));
												}

												// Keep Materom display consistent with search page formatting.
												if ($supplierLower === 'materom') {
													$livrareNormalized = mb_strtolower((string) $livrare);
													$livrareAscii = str_replace(
														['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'],
														['a', 'a', 'i', 's', 's', 't', 't'],
														$livrareNormalized
													);
													if (str_contains($livrareAscii, 'azi') || str_contains($livrareAscii, 'astazi')) {
														$livrare = 'Azi';
													} elseif (str_contains($livrareAscii, 'maine')) {
														if (preg_match('/\b(\d{1,2}:\d{2})\b/u', $livrare, $timeMatch)) {
															$hour = str_pad($timeMatch[1], 5, '0', STR_PAD_LEFT);
															$livrare = "Mâine {$hour}";
														} else {
															$livrare = 'Mâine';
														}
													}

													$depozitNormalized = mb_strtolower(trim((string) $depozit));
													$depozitAscii = str_replace(
														['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'],
														['a', 'a', 'i', 's', 's', 't', 't'],
														$depozitNormalized
													);
													if ($depozitAscii === 'timisoara') {
														$depozit = 'Tm';
													} elseif ($depozitAscii === 'centru logistic') {
														$depozit = 'Mureș';
													}
												}

												// Format: Livrare / Depozit
												$displayText = $livrare . ' / ' . $depozit;
											@endphp
											{{ $displayText }}
										</td>
										<!--<td>{{ $item['delivery'] }}</td>
										<td>{{ $item['variant_code'] }}</td>-->
										<td>{{ $item['price'] }} {{ $item['currency'] }}</td>
										<td>
											<input type="number"
											   min="1"
											   class="form-control qty-input"
											   data-supplier="{{ $supplier }}"
											   data-key="{{ $key }}"
											   value="{{ $item['qty'] }}">
										</td>
										<td>
											{{ $item['price'] * $item['qty'] }} {{ $item['currency'] }}
										</td>
										<td class="text-center">
											<button class="btn btn-danger btn-sm remove-item"
												data-supplier="{{ $supplier }}"
												data-key="{{ $key }}">
												<i class="glyphicon glyphicon-trash"></i>
											</button>
										</td>
										<td class="text-center">
											@if(($item['supplier'] ?? '') === 'autototal')
												<input
													type="checkbox"
													class="autototal-skip-checkbox"
													name="autototal_skip_ui[]"
													value="{{ $key }}"
													checked
													title="Skip this item for AutoTotal API order">
											@endif
										</td>
									</tr>
								@endforeach
							@endforeach
							</tbody>
					</table>

					<div class="text-right" style="margin-top:50px;">
						<h4>
							Total:
							<strong>{{ number_format($total, 2) }}</strong>
						</h4>
					</div>

					<div class="text-right">
						<div class="row">
							<div class="col-sm-12 col-sm-offset-8 text-right pull-right">
								<div class="form-group">
									<label>Locatie livrare</label>
									<select id="order_from_location" class="form-control" required style="width:200px;margin-left:auto;">
										<option value="">Select</option>
										<option value="UTVIN">UTVIN</option>
										<option value="TIMISOARA">TIMIȘOARA</option>
									</select>
								</div>

								<div class="clearfix">
									@if(!empty($canAddSiteProduse))
									<button
										type="button"
										class="btn btn-success pull-left"
										data-toggle="modal"
										data-target="#siteProduseCartModal"
										title="Adaugă din Produse (magazin)"
										aria-label="Adaugă din Produse (magazin)">
										<i class="glyphicon glyphicon-plus"></i>
									</button>
									@endif
									<div class="pull-right">
										<button class="btn btn-default" style="margin-right:10px;" data-toggle="modal" data-target="#saveCartModal">
											Salveaza oferta
										</button>
										<button class="btn btn-success"
											data-toggle="modal"
											data-target="#importModal"
											id="saveCartBtn"
											disabled>
											Comanda
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				@endif
			</div>
		</div>
    </div>
</div>

<div class="modal fade" id="saveCartModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('searching.wishlistSave') }}">
            @csrf
            <div id="wishlist_item_inputs"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Salvează coșul</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Numele listei de dorințe</label>
                        <input type="text" name="name" class="form-control" placeholder="Enter a name" required>
                    </div>
                    <div class="form-group">
                        <label>Număr de telefon</label>
                        <input type="text" name="phone" class="form-control" placeholder="Enter phone number">
                    </div>
                    <div class="form-group">
                        <label>Număr VIN</label>
                        <input type="text" name="vin" class="form-control" placeholder="Enter VIN number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Anulează</button>
                    <button type="submit" class="btn btn-success">Salvează</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="{{ route('searching.placeOrder') }}">
			@csrf
			<input type="hidden" name="order_from" id="order_from_input">
			<div id="autototal_excluded_inputs"></div>
			<div id="order_item_inputs"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Importă comanda în</h4>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label>Selectează locația</label>
                        <select name="import_from" class="form-control" required>
                            <option value="">Select</option>
                            <option value="UTVIN">UTVIN</option>
                            <option value="TIMISOARA">TIMIȘOARA</option>
                            <option value="EXTERNE">EXTERNE</option>
                            <option value="NuImporta">Nu Importa</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Anulează
                    </button>
                    <button type="submit" class="btn btn-success">
                        Continuă
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@if(!empty($canAddSiteProduse))
<div class="modal fade" id="siteProduseCartModal" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Produse din magazin (/produse)</h4>
			</div>
			<div class="modal-body">
				<p class="text-muted">Aceste produse nu sunt comandate la furnizori externi; se importă la fel ca liniile din coș în comanda aleasă. Caută după cod sau denumire — se afișează câte 10 rezultate.</p>
				<div id="siteProduseCartAlert" class="alert" style="display:none;"></div>
				<div class="form-inline" style="margin-bottom:12px;">
					<div class="form-group" style="width:100%; max-width:420px;">
						<label class="sr-only" for="siteProduseSearchInput">Caută</label>
						<input type="text" class="form-control" id="siteProduseSearchInput" placeholder="Cod sau denumire…" style="width:70%; min-width:180px;">
					</div>
					<button type="button" class="btn btn-primary" id="siteProduseSearchBtn" style="margin-left:6px;">Caută</button>
				</div>
				<p class="text-muted small" id="siteProduseMeta" style="margin-bottom:8px;"></p>
				<div class="table-responsive" style="max-height:420px; overflow-y:auto;">
					<table class="table table-striped table-bordered table-condensed" id="siteProduseCartTable">
						<thead>
							<tr>
								<th>Cod</th>
								<th>Denumire</th>
								<th>Preț</th>
								<th width="90">Cant.</th>
								<th width="100"></th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="5" class="text-center text-muted">Se încarcă…</td></tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer clearfix">
				<div class="pull-left" id="siteProdusePager" style="margin-top:6px;">
					<button type="button" class="btn btn-default btn-sm" id="siteProdusePrev" disabled>« Anterior</button>
					<span class="text-muted" style="margin:0 10px;" id="siteProdusePageLabel"></span>
					<button type="button" class="btn btn-default btn-sm" id="siteProduseNext" disabled>Următor »</button>
				</div>
				<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			</div>
		</div>
	</div>
</div>
@endif

<div class="modal fade" id="excludedAutototalItemsModal" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<h4 class="modal-title">Produse excluse din comenzi AutoTotal</h4>
			</div>
			<div class="modal-body">
				@if(isset($autototalExcludedDailySummary) && $autototalExcludedDailySummary->count() > 0)
					<div class="table-responsive">
						<table class="table table-bordered table-striped">
							<thead>
								<tr>
									<th>Data</th>
									<th>Produse excluse</th>
									<th>Total valoare</th>
									<th>Monedă</th>
									<th>Locație salvată</th>
									<th width="180">Acțiune</th>
								</tr>
							</thead>
							<tbody>
								@foreach($autototalExcludedDailySummary as $summary)
									<tr>
										<td>{{ $summary['date'] }}</td>
										<td>{{ $summary['items_count'] }}</td>
										<td>{{ number_format($summary['total_amount'], 2) }}</td>
										<td>{{ $summary['currencies'] }}</td>
										<td>{{ $summary['locations'] }}</td>
										<td>
											<form method="POST" action="{{ route('searching.excludedAutototalCartLoadByDay') }}" style="display:inline;">
												@csrf
												<input type="hidden" name="date" value="{{ $summary['date'] }}">
												<input type="hidden" name="order_from" value="{{ $summary['location_key'] }}">
												<button type="submit" class="btn btn-primary btn-xs">
													Add to excluded cart
												</button>
											</form>
											<form method="POST" action="{{ route('searching.excludedAutototalSavedDayDelete') }}" style="display:inline; margin-left:4px;"
												onsubmit="return confirm('Ștergeți toate produsele excluse salvate pentru această dată și locație?');">
												@csrf
												<input type="hidden" name="date" value="{{ $summary['date'] }}">
												<input type="hidden" name="order_from" value="{{ $summary['location_key'] }}">
												<button type="submit" class="btn btn-danger btn-xs" title="Șterge intrările salvate pentru această zi">
													<i class="glyphicon glyphicon-trash"></i>
												</button>
											</form>
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				@else
					<div class="alert alert-info" style="margin-bottom:0;">
						Nu există produse excluse AutoTotal salvate.
					</div>
				@endif
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			</div>
		</div>
	</div>
</div>
<script>
@if(!empty($canAddSiteProduse))
(function () {
	var siteProduseState = { q: '', page: 1, perPage: 10, searchTimer: null };

	function renderSiteProduseRows(res) {
		var $tb = $('#siteProduseCartTable tbody');
		$tb.empty();
		if (!res || !res.success) {
			$tb.html('<tr><td colspan="5" class="text-center text-danger">Răspuns invalid.</td></tr>');
			return;
		}
		var total = res.total != null ? parseInt(res.total, 10) : 0;
		var cur = res.current_page != null ? parseInt(res.current_page, 10) : 1;
		var last = res.last_page != null ? parseInt(res.last_page, 10) : 1;
		$('#siteProduseMeta').text(
			total === 0
				? 'Niciun rezultat.'
				: ('Afișare ' + (res.from || 0) + '–' + (res.to || 0) + ' din ' + total + ' (pag. ' + cur + '/' + last + ')')
		);
		$('#siteProdusePrev').prop('disabled', cur <= 1);
		$('#siteProduseNext').prop('disabled', cur >= last);
		$('#siteProdusePageLabel').text(cur + ' / ' + last);

		if (!res.products || !res.products.length) {
			$tb.html('<tr><td colspan="5" class="text-center text-muted">Niciun produs. Încearcă alt termen de căutare.</td></tr>');
			return;
		}
		res.products.forEach(function (p) {
			var id = parseInt(p.idprodus, 10);
			var row = $('<tr>');
			row.append($('<td>').text(p.cod_produs || ''));
			row.append($('<td>').text(p.denumire || ''));
			var defaultPret = p.pret != null ? parseFloat(p.pret, 10) : 0;
			if (isNaN(defaultPret) || defaultPret < 0) {
				defaultPret = 0;
			}
			var $priceInput = $('<input type="number" min="0" step="0.01" class="form-control input-sm site-produse-price" style="max-width:100px; display:inline-block;">').val(defaultPret);
			var $priceTd = $('<td>');
			$priceTd.append($priceInput);
			if (p.um) {
				$priceTd.append($('<span class="text-muted small" style="margin-left:4px;">').text('/ ' + p.um));
			}
			row.append($priceTd);
			var $qty = $('<input type="number" min="1" value="1" class="form-control input-sm site-produse-qty" style="max-width:80px;">');
			var $btn = $('<button type="button" class="btn btn-primary btn-xs">Adaugă</button>');
			$btn.on('click', function () {
				var qn = parseInt($qty.val(), 10) || 1;
				var priceNum = parseFloat($priceInput.val(), 10);
				if (isNaN(priceNum) || priceNum < 0) {
					var $alert0 = $('#siteProduseCartAlert');
					$alert0.removeClass('alert-success').addClass('alert-danger').text('Introduceți un preț valid (≥ 0).').show();
					return;
				}
				var $alert = $('#siteProduseCartAlert');
				$alert.hide().removeClass('alert-success alert-danger');
				$.post("{{ route('searching.cartAddSiteProduse') }}", {
					_token: "{{ csrf_token() }}",
					idprodus: id,
					qty: qn,
					price: priceNum
				}).done(function (r) {
					if (r && r.success) {
						$alert.addClass('alert-success').text('Produs adăugat în coș.').show();
						setTimeout(function () { location.reload(); }, 600);
					} else {
						$alert.addClass('alert-danger').text((r && r.message) ? r.message : 'Eroare').show();
					}
				}).fail(function (xhr) {
					var msg = 'Eroare la adăugare';
					if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
					$alert.addClass('alert-danger').text(msg).show();
				});
			});
			row.append($('<td>').append($qty));
			row.append($('<td>').append($btn));
			$tb.append(row);
		});
	}

	function loadSiteProdusePage() {
		var $tb = $('#siteProduseCartTable tbody');
		$tb.html('<tr><td colspan="5" class="text-center text-muted">Se încarcă…</td></tr>');
		$('#siteProduseMeta').text('');
		$.get("{{ route('searching.siteProduseList') }}", {
			q: siteProduseState.q,
			page: siteProduseState.page,
			per_page: siteProduseState.perPage
		}).done(renderSiteProduseRows).fail(function () {
			$tb.html('<tr><td colspan="5" class="text-center text-danger">Nu s-a putut încărca lista.</td></tr>');
			$('#siteProduseMeta').text('');
		});
	}

	$('#siteProduseCartModal').on('show.bs.modal', function () {
		$('#siteProduseCartAlert').hide().removeClass('alert-success alert-danger');
		siteProduseState.q = '';
		siteProduseState.page = 1;
		$('#siteProduseSearchInput').val('');
		loadSiteProdusePage();
	});

	$('#siteProduseSearchBtn').on('click', function () {
		siteProduseState.q = $.trim($('#siteProduseSearchInput').val() || '');
		siteProduseState.page = 1;
		loadSiteProdusePage();
	});

	$('#siteProduseSearchInput').on('keydown', function (e) {
		if (e.which === 13) {
			e.preventDefault();
			$('#siteProduseSearchBtn').click();
		}
	});

	$('#siteProduseSearchInput').on('input', function () {
		clearTimeout(siteProduseState.searchTimer);
		siteProduseState.searchTimer = setTimeout(function () {
			siteProduseState.q = $.trim($('#siteProduseSearchInput').val() || '');
			siteProduseState.page = 1;
			loadSiteProdusePage();
		}, 400);
	});

	$('#siteProdusePrev').on('click', function () {
		if (siteProduseState.page > 1) {
			siteProduseState.page -= 1;
			loadSiteProdusePage();
		}
	});
	$('#siteProduseNext').on('click', function () {
		siteProduseState.page += 1;
		loadSiteProdusePage();
	});
})();
@endif

$('#order_from_location').on('change', function () {
    let val = $(this).val();

    $('#order_from_input').val(val);

    // Enable button only when selected
    $('#saveCartBtn').prop('disabled', val === '');
});

// Safety sync when modal opens
$('#importModal').on('show.bs.modal', function () {
    $('#order_from_input').val($('#order_from_location').val());

	// Rebuild hidden inputs for excluded AutoTotal items
	let container = $('#autototal_excluded_inputs');
	container.empty();

	$('.autototal-skip-checkbox:checked').each(function () {
		let key = $(this).val();
		container.append(
			$('<input>').attr({
				type: 'hidden',
				name: 'autototal_excluded_keys[]',
				value: key
			})
		);
	});

	// Send only checked rows to supplier APIs.
	let orderContainer = $('#order_item_inputs');
	orderContainer.empty();
	let selectedOrderItems = $('.order-item-checkbox:checked');
	selectedOrderItems.each(function () {
		let compound = $(this).val();
		orderContainer.append(
			$('<input>').attr({
				type: 'hidden',
				name: 'order_item_keys[]',
				value: compound
			})
		);
	});

	// Explicit marker so backend will not order all if user unchecked everything.
	if (selectedOrderItems.length === 0) {
		orderContainer.append(
			$('<input>').attr({
				type: 'hidden',
				name: 'order_item_keys[]',
				value: 'NONE'
			})
		);
	}
});

// Save wishlist with only checked "Comandă" rows.
$('#saveCartModal').on('show.bs.modal', function () {
	let container = $('#wishlist_item_inputs');
	container.empty();

	let selectedItems = $('.order-item-checkbox:checked');
	selectedItems.each(function () {
		let compound = $(this).val();
		container.append(
			$('<input>').attr({
				type: 'hidden',
				name: 'wishlist_item_keys[]',
				value: compound
			})
		);
	});

	// Explicit marker so backend knows user intentionally selected none.
	if (selectedItems.length === 0) {
		container.append(
			$('<input>').attr({
				type: 'hidden',
				name: 'wishlist_item_keys[]',
				value: 'NONE'
			})
		);
	}
});

$('.qty-input').on('change', function () {
    $.post("{{ route('searching.cartUpdate') }}", {
        _token: "{{ csrf_token() }}",
        supplier: $(this).data('supplier'),
        key: $(this).data('key'),
        qty: $(this).val()
    }, function () {
        location.reload();
    });
});

$('.product-name-input').on('change', function () {
    $.post("{{ route('searching.cartUpdateProductName') }}", {
        _token: "{{ csrf_token() }}",
        supplier: $(this).data('supplier'),
        key: $(this).data('key'),
        product_name: $(this).val()
    }, function () {
        location.reload();
    });
});

$('.manufacturer-input').on('change', function () {
    $.post("{{ route('searching.cartUpdateManufacturer') }}", {
        _token: "{{ csrf_token() }}",
        supplier: $(this).data('supplier'),
        key: $(this).data('key'),
        manufacturer: $(this).val()
    }, function () {
        location.reload();
    });
});

$('.remove-item').on('click', function () {
    if (!confirm('Eliminați acest produs?')) return;

    $.post("{{ route('searching.cartRemove') }}", {
        _token: "{{ csrf_token() }}",
        supplier: $(this).data('supplier'),
        key: $(this).data('key')
    }, function () {
        location.reload();
    });
});

$('.variant-location-select').on('change', function () {
    let select = $(this);

    let location = select.val();

    // mapping
    let prefixMap = {
        'UTVIN': '2441501',
        'TIMISOARA': '24415'
    };

    let newPrefix = prefixMap[location];

    let fullVariant = select.data('full-variant');
    let parts = fullVariant.split('#');

    // replace first part only
    parts[0] = newPrefix;

    let updatedVariant = parts.join('#');

    $.post("{{ route('searching.cartUpdateVariant') }}", {
        _token: "{{ csrf_token() }}",
        supplier: select.data('supplier'),
        key: select.data('key'),
        variant_code: updatedVariant
    }, function () {
        location.reload();
    });
});
</script>
@endsection