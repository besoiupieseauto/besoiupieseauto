@extends('layouts.mainappv1')
@section('title', 'Excluded AutoTotal Cart')
@section('content')
<div class="jumbotron">
    <div class="container">
        <div class="panel panel-info">
            <div class="panel-heading clearfix">
                <h4 class="pull-left" style="margin:0;">
                    <i class="glyphicon glyphicon-shopping-cart"></i> Excluded AutoTotal Cart
                </h4>
                <a href="{{ route('searching.cartShow') }}" class="btn btn-default btn-sm pull-right">
                    <i class="glyphicon glyphicon-arrow-left"></i> Back to Cart
                </a>
            </div>

            <div class="panel-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if($excludedCart->isEmpty())
                    <div class="alert alert-info" style="margin-bottom:0;">
                        Excluded AutoTotal cart is empty.
                    </div>
                @else
                    <p><strong>Date:</strong> {{ $selectedDate ?: '-' }}</p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="80">Select</th>
                                    <th>Supplier</th>
                                    <th>Brand</th>
                                    <th>Product</th>
                                    <th>AM Code</th>
                                    <th>Livrare</th>
                                    <th>Depozit</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                    <th>Saved From</th>
                                    <th width="90">Remove</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($excludedCart as $item)
                                    @php
                                        $itemId = (int)($item['id'] ?? 0);
                                        $itemPrice = (float)($item['price'] ?? 0);
                                        $itemQty = (int)($item['qty'] ?? 1);
                                        $rowTotal = $itemPrice * $itemQty;
                                        // Same key as /searching/cart/show: AM Code = product_code.
                                        // Excluded rows may store formatted codes (e.g. 24.0110-0314.1); strip separators for display only.
                                        $rawProductCode = trim((string) ($item['product_code'] ?? ''));
                                        $amCodeDisplay = $rawProductCode === '' ? '-' : preg_replace('/[.\s\-\/|\\\\]+/', '', $rawProductCode);
                                    @endphp
                                    <tr>
                                        <td class="text-center">
                                            <input
                                                type="checkbox"
                                                class="excluded-item-checkbox"
                                                value="{{ $itemId }}"
                                                data-row-total="{{ $rowTotal }}"
                                                checked>
                                        </td>
                                        <td>{{ strtoupper($item['supplier'] ?? 'autototal') }}</td>
                                        <td>{{ $item['manufacturer'] ?? '-' }}</td>
                                        <td>{{ $item['product_name'] ?? '-' }}</td>
                                        <td>{{ $amCodeDisplay }}</td>
                                        <td>
                                            @php
                                                $livrareRaw = (string)($item['livrare'] ?? '-');
                                                $livrare = $livrareRaw;
                                                if (!empty($livrare) && $livrare !== '-') {
                                                    $livrare = ucfirst(trim(ltrim($livrare, '/')));
                                                }

                                                // Dot color logic (similar UX to /searching/cart/show):
                                                // - "Azi" => green
                                                // - "Maine" => blue
                                                // - "2 zile" / "2-3" => orange
                                                // - fallback => red
                                                $dotImg = '/image/red-dot.png';

                                                // In cart/show, dot is derived from "plant" (Timișoara / Centru Logistic).
                                                // Here we don't store dot_image_path, so we approximate using "depozit" text.
                                                $depozitRaw = (string) ($item['depozit'] ?? '');
                                                $depLower = mb_strtolower($depozitRaw, 'UTF-8');

                                                // Timișoara (green)
                                                if (
                                                    preg_match('/\b(tm|timi|timisoara)\.?/iu', $depozitRaw) ||
                                                    str_contains($depLower, 'tm')
                                                ) {
                                                    $dotImg = '/image/green-dot.png';
                                                }
                                                // Centru Logistic (tomorrow) - use the same "blue dot" as cart/show
                                                else if (
                                                    str_contains($depLower, 'buc') ||
                                                    str_contains($depLower, 'imgb') ||
                                                    str_contains($depLower, 'imgb')
                                                ) {
                                                    $dotImg = '/image/blue-dot.png';
                                                }
                                                // Fallbacks from livrare text (best-effort)
                                                else {
                                                    $livLower = mb_strtolower($livrareRaw, 'UTF-8');
                                                    if (preg_match('/\baz[iî]/iu', $livrareRaw) || str_contains($livLower, 'azi')) {
                                                        $dotImg = '/image/green-dot.png';
                                                    } elseif (preg_match('/ma(?:i|â|ă|î)ne/iu', $livrareRaw) || str_contains($livLower, 'maine')) {
                                                    $dotImg = '/image/blue-dot.png';
                                                    } elseif (
                                                        str_contains($livLower, '2-3') ||
                                                        str_contains($livLower, '2 zile') ||
                                                        str_contains($livLower, '2-')
                                                    ) {
                                                        $dotImg = '/image/orange-dot.png';
                                                    }
                                                }
                                            @endphp
                                            <img
                                                src="{{ $dotImg }}"
                                                alt=""
                                                style="width:14px; height:14px; margin-right:6px; vertical-align:middle;"
                                            />
                                            {{ $livrare }}
                                        </td>
                                        <td>{{ $item['depozit'] ?? '-' }}</td>
                                        <td>{{ number_format($itemPrice, 2) }} {{ $item['currency'] ?? 'RON' }}</td>
                                        <td>{{ $itemQty }}</td>
                                        <td>{{ number_format($rowTotal, 2) }} {{ $item['currency'] ?? 'RON' }}</td>
                                        <td>{{ $item['order_from'] ?? '-' }}</td>
                                        <td class="text-center">
                                            <form method="POST" action="{{ route('searching.removeExcludedAutototalCartItem') }}" style="display:inline;">
                                                @csrf
                                                <input type="hidden" name="item_id" value="{{ $itemId }}">
                                                <input type="hidden" name="date" value="{{ $selectedDate }}">
                                                <input type="hidden" name="order_from_context" value="{{ $selectedOrderFrom }}">
                                                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Remove this item from excluded cart?');">
                                                    <i class="glyphicon glyphicon-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="text-right" style="margin-top:20px;">
                        <h4>Selected Total: <strong id="selectedExcludedTotal">{{ number_format($total, 2) }}</strong></h4>
                    </div>

                    <div class="row">
                        <div class="col-sm-3 col-sm-offset-9">
                            <form method="POST" action="{{ route('searching.placeExcludedAutototalOrder') }}">
                                @csrf
                                <div id="selectedExcludedItemsContainer"></div>
                                <input type="hidden" name="date" value="{{ $selectedDate }}">
                                <input type="hidden" name="order_from_context" value="{{ $selectedOrderFrom }}">
                                <div class="form-group">
                                    <label>Order from location</label>
                                    <select name="order_from" class="form-control" required>
                                        @foreach($availableLocations as $location)
                                            <option value="{{ $location }}" {{ $location === $defaultOrderFrom ? 'selected' : '' }}>
                                                {{ $location }}
                                            </option>
                                        @endforeach

                                        @if($availableLocations->isEmpty())
                                            <option value="UTVIN" selected>UTVIN</option>
                                            <option value="TIMISOARA">TIMISOARA</option>
                                        @endif
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-success btn-block">
                                    Place AutoTotal Order
                                </button>
                            </form>
                        </div>
                    </div>

                    <script>
                        (function () {
                            const checkboxes = document.querySelectorAll('.excluded-item-checkbox');
                            const totalEl = document.getElementById('selectedExcludedTotal');
                            const selectedContainer = document.getElementById('selectedExcludedItemsContainer');
                            const orderForm = selectedContainer ? selectedContainer.closest('form') : null;

                            function refreshSelectedState() {
                                let total = 0;
                                if (selectedContainer) {
                                    selectedContainer.innerHTML = '';
                                }

                                checkboxes.forEach(function (checkbox) {
                                    if (!checkbox.checked) {
                                        return;
                                    }

                                    const rowTotal = parseFloat(checkbox.dataset.rowTotal || '0') || 0;
                                    total += rowTotal;

                                    if (selectedContainer) {
                                        const hidden = document.createElement('input');
                                        hidden.type = 'hidden';
                                        hidden.name = 'selected_item_ids[]';
                                        hidden.value = checkbox.value;
                                        selectedContainer.appendChild(hidden);
                                    }
                                });

                                if (totalEl) {
                                    totalEl.textContent = total.toFixed(2);
                                }
                            }

                            checkboxes.forEach(function (checkbox) {
                                checkbox.addEventListener('change', refreshSelectedState);
                            });

                            if (orderForm) {
                                orderForm.addEventListener('submit', function (event) {
                                    if (!selectedContainer || selectedContainer.querySelectorAll('input[name="selected_item_ids[]"]').length === 0) {
                                        event.preventDefault();
                                        alert('Please select at least one item to place the order.');
                                    }
                                });
                            }

                            refreshSelectedState();
                        })();
                    </script>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
