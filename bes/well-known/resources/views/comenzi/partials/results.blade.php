{{-- resources/views/comenzi/partials/results.blade.php --}}

<div class="panel panel-default">
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="orders-table">
                <thead>
                    <tr class="info">
                        <th class="text-center">Data</th>
                        <th class="text-center">Client</th>
                        <!--<th class="text-center">Telefon</th>-->
                        <!--<th class="text-center">Marca</th>-->
                        <th class="text-center">Adresa</th>
                        <th class="text-center">Produs</th>
                        <th class="text-center">Cod</th>
                        <th class="text-center">Furnizor</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-center">Pret</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">AWB</th>
                        <!--<th class="text-center">Status Curier</th>-->
                        <th class="text-center">Status</th>
						<th class="text-center">Notita</th>
                        <th class="text-center">Actiune</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders ?? [] as $orderId => $orderData)
                        @php
                            $order = $orderData['order'];
                            $products = $orderData['products'];
                            $rowCount = count($products);
                            $bgColor = 'var(--td-bg-color)';
                            
                            // Set background color based on status
                            /*
                            if ($order['stare'] == 2) { // Sosit
                                $bgColor = '#9EA5AF';
                            }
                            elseif ($order['stare'] == 6) { // Retur
                                $bgColor = '#6D7177';  // Darker Gray
                            }
                            */

                            // Set background color based on status
                            if ($order['stare'] == 0 || $order['stare'] == 1 || $order['stare'] == 5) {
                                // Comandat // Expediat // Avans //
                                $bgColor = "var(--td-bg-color)";
                            }
                            elseif ($order['stare'] == 2 || $order['stare'] == 3) {
                                // Sosit
                                $bgColor = "#9ea5af";
                            }
                            elseif ($order['stare'] == 4 || $order['stare'] == 6) {
                                // Retur // Achitat
                                $bgColor = "#6d7177";
                            }

                            // Set status button class
                            $statusClasses = [
                                1 => 'btn-warning',  // Comandat
                                2 => 'btn-info',     // Sosit
                                3 => 'btn-primary',  // Expediat
                                4 => 'btn-success',  // Achitat
                                5 => 'btn-danger',   // Avans
                                6 => 'btn-success',  // Retur
                                7 => 'btn-danger',  // Retur
                            ];
                            $statusLabel = $statusClasses[$order['stare']] ?? 'btn-default';
                            
                            // Set status text
                            $statusText = [
                                1 => 'Comandat',
                                2 => 'Sosit',
                                3 => 'Expediat',
                                4 => 'Achitat',
                                5 => 'Avans',
                                6 => 'Retur',
                                7 => 'Anulat',
                            ][$order['stare']] ?? 'Necunoscut';
                            
                            // Calculate total from all products
                            $orderTotal = $order['total'];
                            /*foreach($products as $product) {
                                $orderTotal += $product->cantitate * $product->pret;
                            }*/
                        @endphp
                        
                        <!-- Hidden inputs for JavaScript functionality -->
                        <input type="hidden" value="{{ $orderId }}" id="id_cmd{{ $orderId }}">
                        <input type="hidden" value="{{ $orderId }}" id="id_prod{{ $orderId }}">
                        <input type="hidden" value="{{ $order['stare'] }}" id="stare_cmd{{ $orderId }}">
                        <input type="hidden" value="{{ $statusText }}" id="stare_text_cmd{{ $orderId }}">
                        <input type="hidden" value="{{ $statusLabel }}" id="label_cmd{{ $orderId }}">
                        <input type="hidden" value="{{ $bgColor }}" id="cul_cmd{{ $orderId }}">
                        <input type="hidden" value="{{ $orderTotal }}" id="total_cmd{{ $orderId }}">
                        <input type="hidden" value="{{ $order['awb'] ?? '' }}" id="awb_cmd{{ $orderId }}">
                        <input type="hidden" value="{{ $order['cont_awb'] ?? 'Utvin' }}" id="cont_awb_cmd{{ $orderId }}">
						@foreach($products as $index => $product)
                            @php
                                // Set product color based on status
								$productColor = ($product->culoare && strtoupper($product->culoare) !== 'FFFFFF') ? '#' . $product->culoare : '';
                                //$productColor = $product->culoare ? '#' . $product->culoare : '#FFFFFF';
                                
                                if ($order['stare'] == 4 || $order['stare'] == 6) { // Achitat or Retur
                                    $productColor = '#6d7177';
                                }
                                elseif ($order['stare'] == 2 || $order['stare'] == 3) { // Sosit
                                    $productColor = '#9EA5AF';
                                }
                            @endphp
                            @if($rowCount == 1)
                            <tr id="{{ $orderId }}">
                            @else
                                @if($index != $rowCount -1)
                            <tr id="{{ $orderId }}" class="products"> 
                                @endif
                            @endif
								
                                @if($index === 0)
                                    <td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}">
										<div style="display:flex;flex-direction:column;line-height:1.5;padding: 4px 0px;">
											<span>{{$order['user_name'] ?? ''}}</span>
											{{ \Carbon\Carbon::parse($order['data'])->format('d/m/Y') }}
											<span>{{ \Carbon\Carbon::parse($order['created_at'])->format('H:i') }}</span>
										</div>
                                    </td>
                                    <td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}">
                                        {{ $order['client_name'] ?? 'Necunoscut' }} {!! $order['companie'] ? '<br><small class="text-muted">'.$order['companie'].'</small>' : '' !!}
										<span style="display:block;">{{ $order['telefon'] ?? '' }}</span>
                                    </td>
                                    <!--<td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}">
                                        {{ $order['telefon'] ?? '-' }}
                                    </td>-->
                                    <!--<td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}">
                                        {{ $order['marca'] ?? '-' }}
                                    </td>-->
                                    <td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}">
                                        {{ $order['localitate'] ?? '-' }}
                                    </td>
                                @endif

                                <td class="vert-align-center" style="background-color: {{ $bgColor }}">
                                    {{ $product->denumire ?? 'Produs necunoscut' }}
                                </td>
                                <td class="vert-align-center" style="background-color: {{ $productColor }}">
                                    <a href="javascript:void(0);" class="product-code-link" title="cod produs" onclick="obtine_culoare('{{ $orderId }}', '{{ $product->idprodus }}', '{{ $product->culoare }}');">
                                        <strong>{{ $product->cod_produs ?? '-' }}</strong>
                                    </a>
                                </td>
                                <td class="vert-align-center" style="background-color: {{ $bgColor }}">
                                    <a href="javascript:void(0);" class="furnizor" title="furnizor" onclick="obtine_furnizor('{{ $orderId }}', '{{ $product->idprodus }}', '{{ $product->furnizor ?? '__' }}');">
                                        <strong>{{ $product->furnizor ?? '__' }}</strong>
                                    </a>
                                </td>
                                <td class="vert-align-center" style="background-color: {{ $bgColor }}">
                                    {{ $product->cantitate ?? '0' }}
                                </td>
                                <td class="vert-align-center" style="background-color: {{ $bgColor }}">
                                    {{ number_format($product->pret ?? 0, 2) }}
                                </td>

                                @if($index === 0)
                                    <td rowspan="{{ $rowCount }}" class="vert-align-right" style="background-color: {{ $bgColor }}">
                                        <a href="javascript:void(0);" class="total-link" title="Total" onclick="obtine_total('{{ $orderId }}');" data-toggle="modal" data-target="#mod_total">
                                            <strong>{{ number_format($orderTotal, 2) }}</strong>
                                        </a>
                                    </td>
                                    <td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}">
                                        @if($order['awb'] == "___" || empty($order['awb']))
                                            <a href="javascript:void(0);" class="awb-link" title="AWB" onclick="obtine_awb('{{ $orderId }}');" data-toggle="modal" data-target="#mod_awb">
                                                <!--<strong>{{ $order['awb'] ?? '___' }}</strong>-->
                                                <strong>{{ 'Emitere AWB' }}</strong>
                                            </a>
                                        @else
											<a href="{{ route('awb.print', $order['awb']) }}?cont_awb={{ $order['cont_awb'] ?? 'Utvin' }}" class="awb-link" title="AWB" target="_new">
												<strong>{{ $order['awb'] }}</strong>
											</a>
											@if(ctype_digit($order['awb']))
												<a href="https://www.fancourier.ro/awb-tracking/?tracking={{ $order['awb'] }}" class="btn btn-sm btn-default action-button" title="Urmărește colet" target="_blank">
													<i class="glyphicon glyphicon-new-window"></i>
												</a>
											@else
												<a href="https://sameday.ro/#awb={{ $order['awb'] }}" class="btn btn-sm btn-default action-button" title="Urmărește colet" target="_blank">
													<i class="glyphicon glyphicon-new-window"></i>
												</a>
											@endif
											@if(!empty($order['swap_awb']) && $order['swap_awb'] != '___')
												<div style="margin-top: 10px;">
													<a href="{{ route('awb.print', $order['swap_awb']) }}?cont_awb={{ $order['cont_awb'] ?? 'Utvin' }}" class="awb-link" title="AWB" target="_new">
														<strong>{{ $order['swap_awb'] }}</strong>
													</a>
													<a href="https://sameday.ro/#awb={{ $order['swap_awb'] }}" class="btn btn-sm btn-default action-button" title="Urmărește colet" target="_blank">
														<i class="glyphicon glyphicon-new-window"></i>
													</a>
												</div>
											@endif
											<a href="javascript:void(0);" class="show-courier-status" style="display:block; margin-top:17px;" data-order-id="{{ $orderId }}" data-toggle="modal" data-target="#courierStatusModal">
												Vezi status colet
											</a>
                                        @endif
										
                                    </td>
                                    <!--<td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}; width:130px;">
										<a href="javascript:void(0);" class="show-courier-status" data-order-id="{{ $orderId }}" data-toggle="modal" data-target="#courierStatusModal">
											Vedere
										</a>
									</td>-->
                                    <td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}">
                                        <button type="button" class="btn btn-sm {{ $statusLabel }}" title="Stare" onclick="obtine_stare('{{ $orderId }}');" data-toggle="modal" data-target="#mod_status">
                                            {{ $statusText }}
                                        </button>
                                    </td>
									<td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}">
										@if(!empty($order['observations']))
											<a href="javascript:void(0)" class="btn btn-danger" title="{{$order['observations']}}"><i class="fa fa-info-circle" style="color: #fff; font-size: 18px;"></i></a>
										@else
											<b>__</b>
										@endif
                                    </td>
									<td rowspan="{{ $rowCount }}" class="vert-align-center" style="background-color: {{ $bgColor }}">
                                        <div class="action-buttons-container">
                                            @if($order['stare'] == 3 || $order['stare'] == 5)
                                                @if(empty($order['idcomanda_ext']))
                                                    <button type="button" class="btn btn-sm btn-success action-button" title="SMS" onclick="obtine_sms('{{ $orderId }}');">
                                                        <i class="glyphicon glyphicon-earphone"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-danger action-button" title="SMS trimis" onclick="obtine_sms('{{ $orderId }}');">
                                                        <i class="glyphicon glyphicon-earphone"></i>
                                                    </button>
                                                @endif
                                            @endif
                                            
											@if($order['stare'] != 3)
												<a href="{{ route('comenzi.edit', $orderId) }}" class="btn btn-sm btn-default action-button" title="Editare">
													<i class="glyphicon glyphicon-edit"></i>
												</a>
											@endif
                                            
                                            @if($order['stare'] == 1)
                                                <!-- Comandat -->
                                                <button type="button" class="btn btn-sm btn-warning action-button" title="Sterge" onclick="sterge('{{ $orderId }}')">
                                                    <i class="glyphicon glyphicon-trash"></i>
                                                </button>
                                            @endif
                                            
                                            @if(isset($order['id_factura']) && $order['id_factura'])
                                                <a href="{{ route('print.invoice', $order['id_factura']) }}" class="btn btn-sm btn-info action-button" title="Tipareste factura" target="_blank">
                                                    <i class="glyphicon glyphicon-print"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('comenzi.edit_extreme', $orderId) }}" class="btn btn-sm btn-default action-button" title="Factureaza" target="_blank">
                                                    <i class="glyphicon glyphicon-share-alt"></i>
                                                </a>
                                            @endif
											
											@if(!empty($order['awb']) && $order['stare'] != 1)
												<a href="{{route('comenzi.sendWhatsapp', $orderId)}}" class="btn {{($order['whatsapp_sent'] == 0 ? 'btn-success' : 'btn-danger')}}" title="Trimite WhatsApp" target="_blank"><i class="glyphicon glyphicon-comment" style="color: #fff;"></i></a>
											@endif
											
											@if($order['stare'] == 6)
												<a href="/facturi/{{$order['id_factura']}}/edit-sub" class="btn btn-default btn-sm" target="_blank"><i class="glyphicon glyphicon-minus"></i></a>
											@endif
											
											@if($order['stare'] == 3 || $order['stare'] == 4 || $order['stare'] == 5 || $order['stare'] == 6)
												<a href="{{route('comenzi.create', ['duplicate' => $orderId])}}" class="btn btn-default" title="Duplicate"><i class="glyphicon glyphicon-copy"></i></a>
											@endif	
										</div>
                                    </td>
                                @endif
                            </tr>
                            
                            <!-- Hidden input for product color -->
                            <input type="hidden" value="{{ $productColor }}" id="cul1_cmd{{ $orderId }}">
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="16" class="text-center">Nu există comenzi pentru selecția curentă</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="panel-footer">
        <div class="row">
            <div class="col-md-6">
                <div class="totals-row">
                    <div class="total-badge">
                        <span class="label label-info label-as-badge">Total luna: {{ number_format($totalLuna ?? 0, 2) }}</span>
                    </div>
                    <div class="total-badge">
                        <span class="label label-success label-as-badge">Total zi: {{ number_format($totalZi ?? 0, 2) }}</span>
                    </div>
					<div class="total-badge">
                        <span class="label label-success label-as-badge">Total cautare: {{ number_format($filteredTotal ?? 0, 2) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                @if(isset($totalPages) && $totalPages > 1)
                <div class="pagination-info text-right">
                    <span class="text-muted">
                        Pagina {{ $currentPage ?? 1 }} din {{ $totalPages ?? 1 }} 
                        ({{ $totalRecords ?? 0 }} înregistrări totale)
                    </span>
                </div>
                @endif
            </div>
        </div>
        
        @if(isset($totalPages) && $totalPages > 1)
        <div class="row">
            <div class="col-md-12">
                <div class="pagination-controls text-center" style="margin-top: 15px;">
                    <ul class="pagination" style="margin: 0;">
                        @if($hasPrevPage ?? false)
                        <li>
                            <a href="javascript:void(0);" onclick="loadPage({{ ($currentPage ?? 1) - 1 }})" 
                               style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #337ab7;">
                                &laquo; Prev
                            </a>
                        </li>
                        @else
                        <li class="disabled">
                            <span style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #777;">
                                &laquo; Prev
                            </span>
                        </li>
                        @endif
                        
                        @php
                            $start = max(1, ($currentPage ?? 1) - 2);
                            $end = min(($totalPages ?? 1), ($currentPage ?? 1) + 2);
                        @endphp
                        
                        @if($start > 1)
                        <li>
                            <a href="javascript:void(0);" onclick="loadPage(1)" 
                               style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #337ab7;">
                                1
                            </a>
                        </li>
                        @if($start > 2)
                        <li class="disabled">
                            <span style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #777;">
                                ...
                            </span>
                        </li>
                        @endif
                        @endif
                        
                        @for($i = $start; $i <= $end; $i++)
                            @if($i == ($currentPage ?? 1))
                            <li>
                                <span style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; background-color: #337ab7; color: white;">
                                    {{ $i }}
                                </span>
                            </li>
                            @else
                            <li>
                                <a href="javascript:void(0);" onclick="loadPage({{ $i }})" 
                                   style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #337ab7;">
                                    {{ $i }}
                                </a>
                            </li>
                            @endif
                        @endfor
                        
                        @if($end < ($totalPages ?? 1))
                        @if($end < ($totalPages ?? 1) - 1)
                        <li class="disabled">
                            <span style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #777;">
                                ...
                            </span>
                        </li>
                        @endif
                        <li>
                            <a href="javascript:void(0);" onclick="loadPage({{ $totalPages }})" 
                               style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #337ab7;">
                                {{ $totalPages }}
                            </a>
                        </li>
                        @endif
                        
                        @if($hasNextPage ?? false)
                        <li>
                            <a href="javascript:void(0);" onclick="loadPage({{ ($currentPage ?? 1) + 1 }})" 
                               style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #337ab7;">
                                Next &raquo;
                            </a>
                        </li>
                        @else
                        <li class="disabled">
                            <span style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #777;">
                                Next &raquo;
                            </span>
                        </li>
                        @endif
                    </ul>
                    
                    @if($totalPages > 10)
                    <div class="go-to-page" style="margin-top: 10px;">
                        <div class="input-group" style="width: 200px; margin: 0 auto;">
                            <span class="input-group-addon">Mergi la pagina:</span>
                            <input type="number" class="form-control" id="goto_page" min="1" max="{{ $totalPages }}" 
                                   placeholder="1-{{ $totalPages }}" style="text-align: center;">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="goToPage()">
                                    <i class="glyphicon glyphicon-arrow-right"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Courier Status Modal -->
<div id="courierStatusModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="courierStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="courierStatusModalLabel">Show Courier Status</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
				<p id="courierStatusText">Loading courier status...</p>
            </div>
        </div>
    </div>
</div>

<style>
    /* Panel styling */
    .panel {
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .panel-heading {
        padding: 15px;
        border-bottom: 1px solid #ddd;
    }

    .panel-body {
        padding: 0;
    }

    .panel-footer {
        background-color: #f9f9f9;
        border-top: 1px solid #ddd;
        padding: 10px 15px;
    }

    /* Table styling */
    #orders-table {
        /*
        border-collapse: separate;
        border-spacing: 0 10px;*/
        margin-bottom: 0;
        border: 1px solid #ddd;
    }

    #orders-table th {
        font-weight: bold;
        text-align: center;
        vertical-align: middle;
        background-color: #d9edf7;
        border: 1px solid #ddd;
    }

    #orders-table td {
        border: 1px solid #ddd;
    }

    /* Target all rows except the first one */
    table#orders-table > tbody > tr {
        border-bottom: 4px solid var(--orders-border-color);
    }

    table#orders-table > tbody > tr.products {
        border-bottom: 1px solid #ddd !important;
    }

    .table-responsive {
        border: none;
        margin-bottom: 0;
    }

    /* Vertical alignment classes */
    .vert-align-center {
        vertical-align: middle !important;
        text-align: center;
    }

    .vert-align-right {
        vertical-align: middle !important;
        text-align: right;
        padding-right: 15px !important;
    }

    /* Button styling */
    .btn {
        border-radius: 3px;
        font-weight: normal;
    }

    .btn-xs {
        padding: 1px 5px;
        font-size: 12px;
        line-height: 1.5;
        border-radius: 3px;
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
        line-height: 1.5;
        border-radius: 3px;
    }

    .btn-secondary {
        color: #333;
        background-color: #fff;
        border-color: #ccc;
    }

    .btn-secondary:hover {
        color: #333;
        background-color: #e6e6e6;
        border-color: #adadad;
    }

    /* Link styling */
    .product-code-link,
    .total-link,
    .awb-link {
        color: #333;
        text-decoration: none;
    }

    .product-code-link:hover,
    .total-link:hover,
    .awb-link:hover {
        color: #337ab7;
        text-decoration: underline;
    }

    /* Label styles */
    .label-as-badge {
        border-radius: 0.25em;
        padding: 0.3em 0.6em;
        font-size: 1em;
    }

    /* Action buttons styling */
    .action-buttons-container {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        gap: 5px;
        justify-content: center;
    }

    /* Total badges */
    .total-badge {
        font-size: 16px;
        display: inline-block;
        margin-right: 15px;
    }

    .total-badge .label {
        font-size: 16px;
        padding: 5px 10px;
    }

    .totals-row {
        display: flex;
        align-items: center;
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        .action-buttons-container {
            flex-direction: column;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    }

    /* Fix button styling */
    .btn-primary {
        background-color: #337ab7;
        border-color: #2e6da4;
    }

    .btn-success {
        background-color: #5cb85c;
        border-color: #4cae4c;
    }

    .btn-info {
        background-color: #5bc0de;
        border-color: #46b8da;
    }

    .btn-warning {
        background-color: #f0ad4e;
        border-color: #eea236;
    }

    .btn-danger {
        background-color: #d9534f;
        border-color: #d43f3a;
    }

    /* Hover effects */
    .table-hover tbody tr:hover {
        background-color: #f9f9f9;
    }

    a.furnizor {
        background-color: inherit !important;
        color: #000000 !important;
    }
    a.furnizor:visited {
        color: #000000 !important;
    }
    a.furnizor:hover {
        text-decoration: underline !important;
        color: #000000 !important;
    }
	
	.highlighted {
    color: red;
    font-weight: bold;
    
}

.form-horizontal .form-group {
    margin-right: 0 !important;
    margin-left: 0 !important;
}
.modal-header {
    padding: 15px 13px 1px !important;
    min-height: 53.84px;
}
.modal-content
.btn-lg {
	font-size:13px !important;
}
</style>

<!-- Required JavaScript (place at the end of the file) -->
<script>
    $(document).ready(function() {
        console.log('Document ready - checking for invoice updates');
        
        // Check for refresh_after_invoice flag in localStorage
        if (localStorage.getItem('reload_after_invoice') === 'true') {
            const orderId = localStorage.getItem('last_order_id');
            console.log('Found reload flag for order:', orderId);
            
            // Clear localStorage flags
            localStorage.removeItem('reload_after_invoice');
            localStorage.removeItem('last_order_id');
            
            // Force reload with cache busting
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('cache_buster', new Date().getTime());
            console.log('Reloading page with cache buster:', currentUrl.toString());
            window.location.href = currentUrl.toString();
        }
        
        // Function to update buttons based on invoice status
        function updateOrderButtons() {
            console.log('Checking for orders with invoices');
            
            // Find all order rows
            $('.action-buttons-container').each(function() {
                const container = $(this);
                const row = container.closest('tr');
                const orderId = row.attr('id');//row.find('input[id^="id_cmd"]').val();
                
                if (!orderId) {
                    console.log('Order ID not found for row');
                    return;
                }
                
                console.log('Checking invoice status for order:', orderId);
                
                // Use AJAX to check invoice status from server
                $.ajax({
                    url: '/check-order-invoice/' + orderId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success && response.has_invoice) {
                            console.log('Order has invoice:', response.invoice_id);
                            
							// ✅ Wait 5 seconds before highlighting
							setTimeout(function () {
								if ($("#orders-table tbody tr").length > 0) {
									highlightTable("#orders-table", $("#q").val());
								}
							}, 2000);
		
                            // Find edit button and replace with print
                            const editBtn = container.find('a[href*="edit_extreme"]');
                            if (editBtn.length > 0) {
                                editBtn.replaceWith(
                                    '<a href="/comenzi/print-invoice/' + response.invoice_id + '"' +
                                    ' class="btn btn-sm btn-info action-button"' +
                                    ' title="Tipareste factura" target="_blank">' +
                                    '<i class="glyphicon glyphicon-print"></i></a>'
                                );
                                console.log('Replaced edit button with print button');                        
						   }
			
		
                        } else {
                            console.log('Order does not have invoice');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error checking invoice status:', error);
                    }
                });
            });
        }
        
        // Run on page load
        updateOrderButtons();
		//highlightTable("#orders-table", $("#q").val());
		
		$('.show-courier-status').on('click', function() {
			var orderId = $(this).data('order-id'); // Get the order ID

			// Show a loading message
			$('#courierStatusText').text('Loading courier status...');

			// AJAX request to fetch the courier status
			$.ajax({
				url: '/comenzi/fetch-courier-status',  // Your route to fetch the courier status
				type: 'GET',
				data: {
					orderId: orderId,
					_token: '{{ csrf_token() }}'  // CSRF token for security
				},
				success: function(response) {
					if (response.success) {
						// Display the courier status in the modal
						$('#courierStatusText').text('Courier Status: ' + response.courier_status);
					} else {
						$('#courierStatusText').text('Courier status not available.');
					}
				},
				error: function(xhr, status, error) {
					// Handle error
					$('#courierStatusText').text('An error occurred while fetching the courier status.');
				}
			});
		});
    });

    // Function to go to a specific page
    function goToPage() {
        var page = parseInt($('#goto_page').val());
        var maxPage = parseInt($('#goto_page').attr('max'));
        
        if (page && page >= 1 && page <= maxPage) {
            if (typeof loadPage === 'function') {
                loadPage(page);
            } else if (typeof load === 'function') {
                load(page);
            } else {
                // Fallback for when load function is not available
                window.location.reload();
            }
            $('#goto_page').val(''); // Clear the input
        } else {
            alert('Vă rugăm să introduceți un număr de pagină valid între 1 și ' + maxPage);
        }
    }

    // Allow Enter key to trigger go to page
    $(document).on('keypress', '#goto_page', function(e) {
        if (e.which === 13) { // Enter key
            goToPage();
        }
    });
	
	
	
// Utility: escape regex special chars
function escapeRegex(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

// Recursive function to highlight text inside an element without removing HTML
function highlightText(element, regex, wrapper) {
    element.contents().each(function () {
        if (this.nodeType === 3) { // Text node
            let text = this.nodeValue;
            if (regex.test(text)) {
                let highlighted = text.replace(regex, wrapper);
                $(this).replaceWith(highlighted);
            }
        } else {
            // Recurse into child elements
            highlightText($(this), regex, wrapper);
        }
    });
}

// Highlight searched term in the table
function highlightTable(tableSelector, searchTerm) {
    if (!searchTerm || searchTerm.trim().length === 0) return;

    let safeTerm = escapeRegex(searchTerm.trim());
    let regex = new RegExp("(" + safeTerm + ")", "gi");

    $(tableSelector).find("tbody td").each(function () {
        highlightText($(this), regex, '<span class="highlighted">$1</span>');
    });
}
</script>