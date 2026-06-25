@extends('layouts.mainappv1')
@section('title', 'Supplier Search')

<style>
.table-bordered { border: 1px solid #ddd; }
.table-striped > tbody > tr:nth-of-type(odd) { background-color: #f9f9f9; }
.table th { background-color: #d9edf7; color: #31708f; }
.custom-search-container > .row { display:flex; gap:15px; align-items:center; }
.filters-row { align-items: flex-start; }
.filters-row .btn { padding: 2px 12px; }
.filters-container { display:flex; flex-direction:column; gap:5px; width:100%; }
.filter-line { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.filter-label { font-weight: bold; color:#333; }
.filter-actions { display:flex; gap:6px; }
.filter-actions .btn { padding: 2px 8px; }
.filter-buttons .btn { margin-right: 5px; margin-bottom: 5px; }
.filters-container .filter-buttons label.filter-toggle-btn input[type="checkbox"] {
	margin-right: 5px;
	margin-left: 0;
	vertical-align: middle;
}
.filters-container .filter-line:first-child .filter-buttons .btn {
	min-height: 28px;
	line-height: 1.4;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
}
#filtersHelpText { color:#666; font-size:12px; }
#productsTable td { vertical-align:middle; padding-left: 40px !important; padding-right: 40px !important; }
#productsTable th { padding-left: 40px !important; padding-right: 40px !important; }
#loader { text-align:center; padding:20px; display:none; }
.collapse-trigger {
    cursor: pointer;
    color: #007bff;
    font-weight: bold;
    user-select: none;
}
.collapse-trigger:hover {
    color: #0056b3;
}
.collapse-trigger::before {
    content: '▶';
    display: inline-block;
    margin-right: 5px;
    transition: transform 0.3s;
}
.collapse-trigger.expanded::before {
    transform: rotate(90deg);
}
.collapsible-row {
    display: none;
    background-color: #f9f9f9 !important;
}
.collapsible-row.show {
    display: table-row !important;
}
#productsTable .collapsible-row.show td.collapsible-content {
    padding: 20px !important;
}
#productsTable .collapsible-row.show .sub-table {
    width: 100% !important;
    max-width: 100% !important;
}
/* Fix sub-table styling to prevent parent table conflicts */
#productsTable .collapsible-row td.collapsible-content {
    padding: 20px !important;
    background-color: #f9f9f9;
    border-top: 2px solid #ddd;
    box-sizing: border-box;
    display: table-cell;
}
#productsTable .collapsible-row .sub-table {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    background-color: #fff !important;
    border: 1px solid #ddd !important;
    margin: 0 !important;
    table-layout: auto;
    box-sizing: border-box;
    display: table;
}
#productsTable {
	margin: auto;
    width: auto !important;
    table-layout: auto;
}
#productsTable .collapsible-row td {
    padding: 0 !important;
}
#productsTable .collapsible-row td.collapsible-content {
    padding: 20px !important;
}
#productsTable tbody .collapsible-row td[colspan] {
    width: 100% !important;
    padding: 0 !important;
}
#productsTable tbody .collapsible-row.show td[colspan] {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 !important;
}
#productsTable tbody .collapsible-row.show td[colspan].collapsible-content {
    padding: 20px !important;
}
#productsTable .collapsible-row .sub-table tr {
    background-color: #fff !important;
}
#productsTable .collapsible-row .sub-table tr:nth-of-type(even) {
    background-color: #f9f9f9 !important;
}
#productsTable .collapsible-row .sub-table td, 
#productsTable .collapsible-row .sub-table th {
    padding: 8px !important;
    border: 1px solid #ddd !important;
}
.stock-zero {
    background-color: #000 !important;
    color: #fff !important;
}

/* Sort icon styling */
.sort-icon {
    display: inline-block;
    padding: 2px 5px;
    font-size: 14px;
    color: #888;
    transition: all 0.2s;
    user-select: none;
}
.sort-icon:hover {
    color: #333;
    background-color: #f0f0f0;
    border-radius: 3px;
}
.sort-icon.active {
    color: #333;
    font-weight: bold;
}

/* Produs header styling */
.produs-header {
    cursor: pointer;
}
.produs-header:hover {
    background-color: #f5f5f5;
}
.produs-label {
    display: inline-block;
}


/* Parent rows only */
#productsTable tr.even {
    background-color: #f4f9ef !important;
}

#productsTable tr.odd {
    background-color: #ffffff !important;
}
.btn-default:hover{
	background-position:0px !important;
}
#promotionsButton {
	background-color:#ebaf53 !important;
	margin-left:auto;
	border-color:#ebaf53 !important;
}


.filters-container .filter-line:nth-child(2) .filter-buttons label.active:nth-child(1), 
.filters-container .filter-line:nth-child(2) .filter-buttons label.btn-default:nth-child(1) {
	background-color:#28a745;
	text-shadow:none !important;
	color:#fff;
	background-image:none!important;
}
.filters-container .filter-line:nth-child(2) .filter-buttons label.active:nth-child(2), 
.filters-container .filter-line:nth-child(2) .filter-buttons label.btn-default:nth-child(2) {
	background-color:#007bff;
	text-shadow:none !important;
	color:#fff;
	background-image:none!important;
}
.filters-container .filter-line:nth-child(2) .filter-buttons label.active:nth-child(3),
.filters-container .filter-line:nth-child(2) .filter-buttons label.btn-default:nth-child(3) {
	background-color:#fd7e14;
	text-shadow:none !important;
	color:#fff;
	background-image:none!important;
}
.filters-container .filter-line:nth-child(2) .filter-buttons label.active:nth-child(4),
 .filters-container .filter-line:nth-child(2) .filter-buttons label.active:nth-child(5),
.filters-container .filter-line:nth-child(2) .filter-buttons label.btn-default:nth-child(4),
.filters-container .filter-line:nth-child(2) .filter-buttons label.btn-default:nth-child(5) {
	background-color:#dc3545;
	text-shadow:none !important;
	color:#fff;
	background-image:none!important;
}
</style>

@section('content')
<div class="jumbotron">
<div class="container">
<div class="panel panel-info">

<div class="panel-heading">
    <h4><i class="glyphicon glyphicon-search"></i> Cautare Piese</h4>
</div>

<div class="panel-body">

<div class="custom-search-container">
	<div class="row">
		<button id="promotionsButton" class="btn btn-info">
			<i class="glyphicon glyphicon-tag"></i> Promotii
		</button>
		@if(Auth::check())
			<a href="{{ route('searching.wishlistSaved') }}" class="btn btn-info pull-right">
				Oferte salvate
			</a>
		@endif
	</div>
	<div class="row" style="margin-top:10px;">
		<input type="text"
			   id="customSearch"
			   class="form-control"
			   placeholder="OE / AM Code or VIN"
			   style="width:400px">

		<button id="searchButton" class="btn btn-primary">
			<i class="glyphicon glyphicon-search"></i> Cautare
		</button>

		<a href="{{ route('searching.getOrders') }}"
		   class="btn btn-success"
		   style="margin-left:auto">
			<i class="glyphicon glyphicon-list-alt"></i> Comenzi
		</a>

		<a href="{{ route('searching.cartShow') }}"
		   class="btn btn-success">
			<i class="glyphicon glyphicon-shopping-cart"></i> Cos
		</a>
	</div>

	<!-- Filters (buttons that behave like checkboxes) -->
	<div class="row filters-row" style="margin-top:10px;">
		<div class="filters-container">
			<div class="filter-line">
				<div class="btn-group filter-buttons">
					@foreach(['materom','elit','intercars','autototal','autonet','autopartner'] as $s)
						@php($isChecked = !in_array($s, ['intercars']))
						<label class="btn btn-default filter-toggle-btn {{ $isChecked ? 'active' : '' }}" role="button" aria-pressed="{{ $isChecked ? 'true' : 'false' }}">
							<input type="checkbox"
								   class="supplier-checkbox"
								   value="{{ $s }}"
								   {{ $isChecked ? 'checked' : '' }}
								   autocomplete="off">
							{{ $supplierlabels[$s] ?? strtoupper(substr($s, 0, 2)) }}
						</label>
					@endforeach
					<label class="btn btn-default filter-toggle-btn supplier-select-all-label" role="button" aria-pressed="false">
						<input type="checkbox" class="supplier-select-all-checkbox" autocomplete="off" title="Selectează/debifează toți furnizorii">
					</label>
				</div>
			</div>

			<div class="filter-line">
				<div class="btn-group filter-buttons">
					@foreach(['Azi','Maine','2 zile','3 zile','Depozite externe'] as $plant)
						@php($isChecked = in_array($plant, ['Azi','Maine']))
						<label class="btn btn-default filter-toggle-btn {{ $isChecked ? 'active' : '' }}" role="button" aria-pressed="{{ $isChecked ? 'true' : 'false' }}">
							<input type="checkbox"
								   class="plant-checkbox"
								   value="{{ $plant }}"
								   {{ $isChecked ? 'checked' : '' }}
								   autocomplete="off">
							{{ $plant }}
						</label>
					@endforeach
				</div>
			</div>
		</div>
	</div>
</div>

<hr>

<!-- Brand Filter Dropdown (hidden, positioned dynamically) -->
<div class="dropdown" id="brandFilterDropdown" style="display:none; position:absolute; z-index:1050;">
	<ul class="dropdown-menu" id="brandFilterMenu" style="max-height:400px; overflow-y:auto; min-width:200px; display:block;">
		<!-- Brand filter menu will be populated by JavaScript -->
	</ul>
</div>

<table id="productsTable" class="table table-bordered table-striped">
	<thead>
		<tr>
			<th class="produs-header" style="cursor:pointer; position:relative;">
				<span class="produs-label">Produs</span>
				<span class="sort-icon" data-sort="brand" style="cursor:pointer; margin-left:5px; color:#888;">⇅</span>
			</th>
			<th style="position:relative;">
				<span>Stoc</span>
				<span class="sort-icon" data-sort="stock" style="cursor:pointer; margin-left:5px; color:#888;">⇅</span>
			</th>
			<th style="position:relative;">
				<span>Preț Minim</span>
				<span class="sort-icon" data-sort="price" style="cursor:pointer; margin-left:5px; color:#888;">⇅</span>
			</th>
			<th>Acțiuni</th>
		</tr>
	</thead>
	<tbody></tbody>
</table>

<div id="loader">Loading…</div>

</div>
</div>
</div>
</div>


<div class="modal fade" id="variantsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Vezi alte variante</h4>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
							<th>Brand</th>
                            <th>Disponibilitate / Livrare / Depozit</th>
                            <th>Preț</th>
                            <th>Adaugă în coș</th>
                        </tr>
                    </thead>
                    <tbody id="variantsModalBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="priceConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Confirmă prețul</h4>
            </div>

            <div class="modal-body">
                <label>Preț final de vânzare</label>
                <input type="number" id="finalPriceInput" class="form-control" />
                <input type="hidden" id="pricePayload">
            </div>

            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Anulează</button>
                <button class="btn btn-success" id="confirmAddToCart">
                    <i class="glyphicon glyphicon-shopping-cart"></i> Adaugă în coș
                </button>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="promotionsModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Selectează promoția</h4>
            </div>
            <div class="modal-body">
                <h5>Adaugă promoție nouă:</h5>
                <div id="promotionsContainer">
                    <p class="text-muted">Nu au fost găsite produse. Vă rugăm să căutați mai întâi produse.</p>
                </div>
                <hr style="margin-top: 20px; margin-bottom: 20px;">
                <div id="existingPromotions">
                    <h5>Promoții existente:</h5>
                    <div id="promotionsList" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                        <p class="text-muted">Se încarcă...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Închide</button>
                <button class="btn btn-primary" id="savePromotions">
                    <i class="glyphicon glyphicon-ok"></i> Salvează promoția
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

<script>
/* ================= HELPERS ================= */

let searchState = {
    products: [],
    selectedSuppliers: [],
    selectedPlants: [],
    brandSelection: [],
    sortKey: 'stock',
    sortDir: 'asc',
    promotions: [] // Array of objects: {supplier: 'materom', brand: 'Mann'}
};

// Deprecated: Use variant.calculated_price from backend instead
function sellingPrice(base) {
    // Fallback for backward compatibility
    return Math.ceil(base * 1.21 * 1.35);
}

function getCalculatedPrice(variant) {
    // Use pre-calculated price from backend if available
    if (variant.calculated_price !== undefined && variant.calculated_price !== null) {
        return variant.calculated_price;
    }
    // Fallback to old calculation method
    return sellingPrice(variant.price ?? 0);
}

function getPriceEPWithVAT(variant) {
    // Calculate priceEP + 21% rounded up for Autototal
    if (variant.priceEP && variant.priceEP > 0) {
        return Math.ceil(variant.priceEP * 1.21);
    }
    return null;
}

function getDepositPriceWithVAT(variant) {
    // Calculate DepositPrice + 21% rounded up for Autopartner
    const depositPrice = variant.deposit_price || variant.DepositPrice;
    if (depositPrice && depositPrice > 0) {
        return Math.ceil(depositPrice * 1.21);
    }
    return null;
}

function getWarrantyPriceWithVAT(variant) {
    // Calculate warranty.price + 21% rounded up for Materom
    if (variant.warranty && variant.warranty.price && variant.warranty.price > 0) {
        return Math.ceil(variant.warranty.price * 1.21);
    }
    return null;
}

/**
 * Effective stock count: for Materom (and suppliers with both keys),
 * use stock if it has a value (>0), else use supplier_stock.
 */
function getEffectiveStock(supplierName, variant) {
    const s = variant.stock;
    if (s !== undefined && s !== null && s !== '' && Number(s) > 0) {
        return Number(s);
    }
    return variant.supplier_stock ?? 0;
}

function formatStock(supplierName, v) {
    let stock = getEffectiveStock(supplierName, v);
    let info  = v.delivery?.info_text ?? '';
    return `${stock} buc.<br><small>${info}</small>`;
}

function stockBadge(stock, bgColor) {
    return `
        <span class="label ggggggggggggg"
              style="
                padding:4px 10px;
                border-radius:6px;
                margin-right:5px;
                background-color:${bgColor};
                color:#fff;
              ">
            ${stock} buc.
        </span>
    `;
}

function normalizeDeliveryText(value) {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ã¢|Ã¢/g, 'a')
        .replace(/ã®|Ã®/g, 'i')
        .replace(/ãă|Ãă/g, 'a');
}

function getStockColor(supplierName, variant) {
    supplierName = supplierName.toLowerCase();

    /* ===== MATEROM ===== */
    if (supplierName === 'materom') {
        const plant = variant.delivery?.plant_name ?? '';
        const plantNormalized = normalizeDeliveryText(plant);
        const info  = (variant.delivery?.info_text ?? '').toLowerCase();
        const livrare = (variant.livrare ?? '').toLowerCase();
        const combinedText = (info + ' ' + livrare).toLowerCase();
        const combinedNormalized = normalizeDeliveryText(combinedText);

        if (plantNormalized.includes('timisoara')) return '#28a745';       // today - green
        if (plantNormalized.includes('centru logistic')) return '#007bff'; // tomorrow - blue
        if (/\b(?:azi|astazi)\b/i.test(combinedNormalized)) return '#28a745';
        if (/\bmaine\b/i.test(combinedNormalized)) return '#007bff';

        // Support Materom date format: dd.mm.yyyy la ora hh:mm
        const roDateMatch = combinedText.match(/(\d{2})\.(\d{2})\.(\d{4})/);
        if (roDateMatch) {
            const [, d, m, y] = roDateMatch;
            const deliveryDate = new Date(`${y}-${m}-${d}T00:00:00`);
            if (!isNaN(deliveryDate.getTime())) {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const diffDays = Math.round((deliveryDate - today) / (1000 * 60 * 60 * 24));
                if (diffDays === 0) return '#28a745';
                if (diffDays === 1) return '#007bff';
                if (diffDays === 2) return '#fd7e14';
                if (diffDays >= 3) return '#dc3545';
            }
        }

        // Check for poimâine (day after tomorrow) - orange
        if (/\bpoimaine\b/i.test(combinedNormalized)) return '#fd7e14';
        
        // Check for "2 zile" or "2-3 zile" patterns - orange (2 days delivery)
        const daysMatch = combinedText.match(/(\d+)[\s-]*zile/i);
        if (daysMatch) {
            const days = parseInt(daysMatch[1]);
            if (days === 2) return '#fd7e14'; // orange - 2 zile
            if (days >= 3) return '#dc3545';  // red - 3+ zile
        }
        
        // Check for "2-3" pattern in delivery text (2 days) - orange
        if (combinedText.match(/2[\s-]*3/i) && combinedText.includes('zile')) return '#fd7e14';
        
        // Check for 3+ days patterns - red
        if (combinedText.match(/[3-9][\s-]*[0-9]/) && combinedText.includes('zile')) return '#dc3545';
        if (combinedText.includes('3') || combinedText.includes('4') || combinedText.includes('5') || 
            combinedText.includes('6') || combinedText.includes('7') || combinedText.includes('8') || 
            combinedText.includes('9')) {
            // Only if it's clearly 3+ days, not just a number in other context
            if (combinedText.includes('zile') || combinedText.match(/\d+\s*zile/i)) {
                return '#dc3545'; // red - 3+ days
            }
        }

        return '#dc3545'; // default red
    }

    /* ===== AUTOPARTNER ===== */
    if (supplierName === 'autopartner') {
        const dept = String(variant.departamentCode ?? '').trim();
        if (dept === 'CN') return '#007bff';               // tomorrow
        if (dept === '120' || dept === '72') return '#fd7e14'; // 2 days
        return '#dc3545'; // 3+ days
    }

    /* ===== AUTONET / AUTOTOTAL ===== */
    if (supplierName === 'autonet' || supplierName === 'autototal') {
        const deliveryStr = variant.delivery?.info_text;
        if (!deliveryStr) return '#dc3545';

        let deliveryDate = null;

        // Try to parse ISO date first (Autonet/Autototal style: 2026-02-03T13:10:00)
        const isoMatch = deliveryStr.match(/(\d{4}-\d{2}-\d{2})[Tt](\d{2}:\d{2}):\d{2}/i);
        if (isoMatch) {
            const [, datePart] = isoMatch;
            deliveryDate = new Date(datePart + 'T00:00:00');
        }

        // Try to parse RO date (Autototal style, dd.mm.yyyy hh:mm)
        const roMatch = deliveryStr.match(/(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}:\d{2})/);
        if (!deliveryDate && roMatch) {
            const [, d, m, y] = roMatch;
            deliveryDate = new Date(`${y}-${m}-${d}T00:00:00`);
        }

        if (!deliveryDate || isNaN(deliveryDate.getTime())) return '#dc3545'; // fallback red

        // Normalize delivery date to midnight for accurate day comparison
        deliveryDate.setHours(0,0,0,0);

        const today = new Date();
        today.setHours(0,0,0,0);

        const diffDays = Math.round((deliveryDate - today) / (1000 * 60 * 60 * 24));

        if (diffDays === 0) return '#28a745'; // green - Azi
        if (diffDays === 1) return '#007bff'; // blue - Mâine
        if (diffDays === 2) return '#fd7e14'; // orange - Poimâine
        if (diffDays >= 3) return '#dc3545';  // red - 3+ zile
    }

    // default
    return '#dc3545';
}

function getFastestVariant(supplierName, variants) {
    if (!variants.length) return null;

    supplierName = supplierName.toLowerCase();

    // Materom logic
    if (supplierName === 'materom') {
        return variants.sort((a, b) => {
            const rank = v => {
                const plant = v.delivery?.plant_name ?? '';
                if (plant === 'Timișoara') return 1;
                if (plant === 'Centru Logistic') return 2;
                return 3;
            };
            return rank(a) - rank(b);
        })[0];
    }

    // Autopartner logic
    if (supplierName === 'autopartner') {
        return variants.sort((a, b) => {
            const rank = v => {
                const dept = v.departamentCode ?? '';
                if (dept === 'CN') return 1;
                if (dept === '120' || dept === '72') return 2;
                return 3;
            };
            return rank(a) - rank(b);
        })[0];
    }

    // Fallback: highest stock
    return variants.sort((a, b) =>
        getEffectiveStock(supplierName, b) - getEffectiveStock(supplierName, a)
    )[0];
}

function getParentStockColor(supplierName, variants) {
    if (!variants.length) return '#dc3545';

    const colorPriority = ['#28a745', '#007bff', '#fd7e14', '#dc3545'];
    let foundColors = variants.map(v => getStockColor(supplierName, v));

    for (let c of colorPriority) {
        if (foundColors.includes(c)) return c;
    }
    return '#dc3545';
}

function getDeliveryPriority(supplierName, variants) {
    if (!variants.length) return 999; // No stock = lowest priority

    // Get the best (fastest) delivery color
    const bestColor = getParentStockColor(supplierName, variants);
    
    // Map color to priority: 1=green/today, 2=blue/tomorrow, 3=orange/day after, 4=red/3+ days
    const colorPriorityMap = {
        '#28a745': 1,  // Green - Today
        '#007bff': 2,  // Blue - Tomorrow
        '#fd7e14': 3,  // Orange - Day after tomorrow
        '#dc3545': 4   // Red - 3+ days
    };
    
    return colorPriorityMap[bestColor] || 999;
}

function getFastestDeliverySupplier(allVariantsWithSupplier) {
    if (!allVariantsWithSupplier.length) return null;
    
    // Group variants by supplier
    const supplierVariants = {};
    allVariantsWithSupplier.forEach(item => {
        const supplier = item.supplier;
        if (!supplierVariants[supplier]) {
            supplierVariants[supplier] = [];
        }
        supplierVariants[supplier].push(item.variant);
    });
    
    // Find the supplier with the fastest delivery
    let fastestSupplier = null;
    let fastestPriority = 999;
    let fastestVariant = null;
    
    Object.keys(supplierVariants).forEach(supplier => {
        const variants = supplierVariants[supplier];
        const priority = getDeliveryPriority(supplier, variants);
        
        if (priority < fastestPriority) {
            fastestPriority = priority;
            fastestSupplier = supplier;
            fastestVariant = getFastestVariant(supplier, variants);
        }
    });
    
    if (!fastestSupplier || !fastestVariant) return null;
    
    return {
        supplier: fastestSupplier,
        variant: fastestVariant,
        priority: fastestPriority
    };
}

function formatDisponibilitate(deliveryInfo, plantName, supplierName) {
    if (!deliveryInfo) return '';

    let text = deliveryInfo.toLowerCase().trim();
    let time = '';
    let label = '';

    // Extract time (for plain text suppliers)
    let timeMatch = text.match(/(\d{1,2}:\d{2})/);
    if (timeMatch) time = timeMatch[1].replace(/^0/, '');

    // --- Autonet case ---
	if (supplierName === 'autonet' || supplierName === 'autototal') {
        // Match ISO date(s)
        const isoRegex = /(\d{4}-\d{2}-\d{2})t(\d{2}:\d{2}):\d{2}/g;
        const isoMatches = [...text.matchAll(isoRegex)];

        if (isoMatches.length > 0) {
            const [firstMatch] = isoMatches;
            const [_, datePart, timePart] = firstMatch;

            time = timePart.replace(/^0/, '');
            const dateObj = new Date(`${datePart}T${timePart}:00`);

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const diffDays = Math.round(
                (dateObj - today) / (1000 * 60 * 60 * 24)
            );

            if (diffDays === 0) {
                label = 'Azi';
            } else if (diffDays === 1) {
                label = 'Mâine';
            } else if (diffDays === 2) {
                label = 'Poimâine';
            } else {
                label = `${diffDays} zile`;
            }

            return time ? `${label} ${time}` : label;
        }

        // Fallback: no ISO date
        return deliveryInfo;
    }

    // --- Existing plant-specific logic ---
    if (!plantName) return deliveryInfo;

    if (plantName === 'Timișoara') return `${time ? `azi ${time}` : 'azi'} / TM`;
    if (plantName === 'Centru Logistic') return `${time ? `maine ${time}` : 'maine'} / Mures`;

    // Default fallback
    return `${deliveryInfo} / ${plantName}`;
}

function getManufacturerName(manufacturer) {
    if (!manufacturer) return '-';
    if (typeof manufacturer === 'string') return manufacturer;
    return manufacturer.name ?? '-';
}

/** For Autonet, show "3 - 4 zile" instead of "Verifica stoc" in the UI. */
function displayLivrare(supplierName, livrare, variant = null) {
    const s = (supplierName || '').toLowerCase();
    const l = (livrare || '').trim();
    if (s === 'autonet' && (!l || l.toLowerCase().includes('verifica stoc'))) {
        return '3 - 4 zile';
    }
    if (s === 'materom') {
        const infoText = String(variant?.delivery?.info_text || '');
        const plantName = String(variant?.delivery?.plant_name || '');
        const plantNormalized = plantName.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        const merged = `${l} ${infoText}`.trim();
        const ln = merged.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        const timeMatch = ln.match(/\b(\d{1,2}:\d{2})\b/);
        const hhmm = timeMatch ? timeMatch[1].padStart(5, '0') : '';
        if (plantNormalized.includes('centru logistic')) return hhmm ? `Mâine ${hhmm}` : 'Mâine';
        if (plantNormalized.includes('timisoara')) return hhmm ? `Azi ${hhmm}` : 'Azi';
        if (/\b(?:azi|astazi)\b/.test(ln)) return 'Azi';
        if (/\bmaine\b/.test(ln)) return hhmm ? `Mâine ${hhmm}` : 'Mâine';
    }
    return l || '-';
}

function displayDepozit(supplierName, depozit) {
    const s = (supplierName || '').toLowerCase();
    const d = (depozit || '').trim();
    if (s !== 'materom') return d || '-';
    const dn = d.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (dn === 'timisoara') return 'Tm';
    if (dn === 'centru logistic') return 'Mureș';
    return d || '-';
}

function isLemforderBrand(name) {
    if (!name || typeof name !== 'string') return false;
    const n = name.toUpperCase();
    return n.includes('LEMFÖRDER') || n.includes('LEMFORDER');
}

function formatAutonetPartCodeForDisplay(code, brandName) {
    if (!code || typeof code !== 'string') return code || '-';
    const s = code.trim();
    if (isLemforderBrand(brandName) && /LMI$/i.test(s)) {
        return s.replace(/LMI$/i, ' 01').trim() || code || '-';
    }
    return cleanAutonetCode(code);
}

function cleanAutonetCode(code) {
    if (!code || typeof code !== 'string') return code || '-';
    
    let cleaned = code.trim();
    const original = cleaned;
    
    // Remove prefixes (GKN, SP, STA)
    cleaned = cleaned.replace(/^(GKN|SP|STA)/i, '');
    
    // Remove suffixes with space before (AIR, CO, LS, HI, LESJ, LPR, MD, MY, MB, NRF, POL, PRA, TEX, TO, ER, AT, DY)
    cleaned = cleaned.replace(/\s+(AIR|CO|LS|HI|LESJ|LPR|MD|MY|MB|NRF|POL|PRA|TEX|TO|ER|AT|DY)$/i, '');
    
    // Remove suffixes without space (AS, BS, BUG, CF, CO, EL, FA, -GT, -DY, HEP, HE)
    cleaned = cleaned.replace(/(AS|BS|BUG|CF|CO|EL|FA|-GT|-DY|HEP|HE)$/i, '');
    
    // Remove dots from codes (like 09.9464.14 -> 09946414)
    cleaned = cleaned.replace(/\./g, '');
    
    // Remove all spaces
    cleaned = cleaned.replace(/\s+/g, '');
    
    // Remove all hyphens
    cleaned = cleaned.replace(/-/g, '');
    
    // Trim and return cleaned code, or original if cleaning resulted in empty string
    cleaned = cleaned.trim();
    return cleaned || original || '-';
}

function cleanElitCode(code){
	if (!code || typeof code !== 'string') return code || '-';
	let cleaned = code.trim();
	
	cleaned = cleaned.replace(/\./g, '');
	return cleaned;
}

function cleanAutopartnerCode(code) {
    if (!code || typeof code !== 'string') return code || '-';
    
    let cleaned = code.trim();
    const original = cleaned;
    
    // Remove dots from codes
    cleaned = cleaned.replace(/\./g, '');
    
    // Remove all spaces
    cleaned = cleaned.replace(/\s+/g, '');
    
    // Remove all hyphens
    cleaned = cleaned.replace(/-/g, '');
    
    // Trim and return cleaned code, or original if cleaning resulted in empty string
    cleaned = cleaned.trim();
    return cleaned || original || '-';
}

function normalizeBrandKey(name) {
    if (!name) return 'NECUNOSCUT';
    
    // Canonicalize some known brand spelling variants before building the key
    let canonical = name.toUpperCase().trim();
    
    // Treat Dr!ve+ and DRIVE+ as the same brand
    canonical = canonical.replace(/DR!VE\+/g, 'DRIVE+');
    
    // Finally, strip everything except letters and digits
    return canonical.replace(/[^A-Z0-9]/g, '') || 'NECUNOSCUT';
}

/** Remove trailing " - OEM" / " - AM" / " - OE" from brand labels (matches Materom API / PHP normalize). */
function stripBrandSuffix(name) {
    if (!name || name === '-') return name;
    let s = String(name);
    let prev = null;
    while (prev !== s) {
        prev = s;
        s = s.replace(/\s*[-\u2013\u2014]\s*(OEM|AM|OE)\s*$/iu, '').trim();
    }
    return s || name;
}

/** Base brand name (without - OEM / - AM) for a product. */
function getProductBrandBaseName(product) {
    const name = getProductBrandName(product);
    const base = stripBrandSuffix(name);
    return (base && base !== '-') ? base : null;
}

function getProductBrandName(product) {
    return getManufacturerName(product.manufacturer ?? null);
}

function getProductBrandKey(product) {
    const baseName = getProductBrandBaseName(product);
    if (!baseName) return 'NECUNOSCUT';
    return normalizeBrandKey(baseName);
}

function checkProductPromotion(product, supplierName) {
    if (!searchState.promotions || !searchState.promotions.length) {
        return false;
    }
    
    const brandBase = getProductBrandBaseName(product);
    if (!brandBase) return false;
    const supplier = supplierName.toLowerCase();
    
    return searchState.promotions.some(promo => {
        return promo.supplier.toLowerCase() === supplier && 
               stripBrandSuffix(promo.brand).toLowerCase() === brandBase.toLowerCase();
    });
}

function checkProductHasAnyPromotion(product, suppliers) {
    if (!searchState.promotions || !searchState.promotions.length) {
        return false;
    }
    
    const brandBase = getProductBrandBaseName(product);
    if (!brandBase) return false;
    
    // Check if any of the suppliers has a promotion for this brand
    return suppliers.some(supplierName => {
        const supplier = supplierName.toLowerCase();
        return searchState.promotions.some(promo => {
            return promo.supplier.toLowerCase() === supplier && 
                   stripBrandSuffix(promo.brand).toLowerCase() === brandBase.toLowerCase();
        });
    });
}

function getPromotionIcon() {
    return '<img src="/image/promotions.jpg" style="width: 20px; height: 20px; margin-left: 5px; vertical-align: middle;" data-toggle="tooltip" title="Promotion" />';
}

/* ================= LOAD PRODUCTS ================= */

function loadPromotions() {
    $.get("{{ route('searching.getPromotions') }}", function(res) {
        if (res.success && res.promotions) {
            searchState.promotions = res.promotions;
            updatePromotionsList();
        }
    });
}

function updatePromotionsList() {
    const $list = $('#promotionsList');
    if ($list.length === 0) {
        return; // List not in DOM yet
    }
    
    $list.empty();
    
    if (!searchState.promotions || !searchState.promotions.length) {
        $list.html('<p class="text-muted">Nu au fost salvate promoții.</p>');
        return;
    }
    
    searchState.promotions.forEach(promo => {
        const supplierDisplay = promo.supplier.charAt(0).toUpperCase() + promo.supplier.slice(1);
        const item = $(`
            <div class="promotion-item" style="padding: 8px; margin-bottom: 5px; background: #f9f9f9; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                <span><strong>${supplierDisplay}</strong> + <strong>${stripBrandSuffix(promo.brand)}</strong></span>
                <button class="btn btn-xs btn-danger delete-promotion" data-id="${promo.id}" style="margin-left: 10px;">
                    <i class="glyphicon glyphicon-trash"></i> Șterge
                </button>
            </div>
        `);
        $list.append(item);
    });
}

function loadProducts() {
    const selectedSuppliers = $('.supplier-checkbox:checked').map(function () {
        return $(this).val();
    }).get();

    if (!selectedSuppliers.length) return;

    const selectedPlants = $('.plant-checkbox:checked').map(function () {
        return $(this).val();
    }).get();

    const $tbody = $('#productsTable tbody');
    $tbody.empty();
    $('#loader').show();

    $.post("{{ route('searching.searchSuppliers') }}", {
        _token: "{{ csrf_token() }}",
        query: $('#customSearch').val(),
        suppliers: selectedSuppliers
    }, function (res) {
        $('#loader').hide();

        if (!res.success || !res.products?.length) {
            searchState.products = [];
            searchState.selectedSuppliers = selectedSuppliers;
            searchState.selectedPlants = selectedPlants;
            buildBrandFilterMenu([]);
            renderProducts();
            return;
        }

        searchState.products = res.products;
        searchState.selectedSuppliers = selectedSuppliers;
        searchState.selectedPlants = selectedPlants;
        buildBrandFilterMenu(res.products);
        renderProducts();
    });
}

function buildBrandFilterMenu(products) {
    const $menu = $('#brandFilterMenu');
    $menu.empty();

    if (!products.length) {
        $menu.append('<li class="brand-filter-empty" style="padding:6px 12px;color:#777;">Nu exista rezultate</li>');
        searchState.brandSelection = [];
        return;
    }

    const brandMap = {};
    products.forEach(product => {
        const baseName = getProductBrandBaseName(product);
        const key = getProductBrandKey(product);
        if (!brandMap[key]) {
            brandMap[key] = baseName || 'Necunoscut';
        }
    });

    const brands = Object.keys(brandMap).sort((a, b) => {
        return brandMap[a].localeCompare(brandMap[b], 'ro', { sensitivity: 'base' });
    });

    const selected = (searchState.brandSelection && searchState.brandSelection.length)
        ? new Set(searchState.brandSelection)
        : new Set(brands);

    $menu.append(`
        <li style="padding:6px 10px; display:flex; gap:8px;">
            <button type="button" class="btn btn-xs btn-default brand-select-all">Bifeaza toate</button>
            <button type="button" class="btn btn-xs btn-default brand-select-none">Debifeaza toate</button>
        </li>
        <li class="divider"></li>
    `);

    brands.forEach(key => {
        const label = brandMap[key];
        const checked = selected.has(key) ? 'checked' : '';
        $menu.append(`
            <li>
                <label style="padding:5px 10px; display:flex; align-items:center;">
                    <input type="checkbox" class="brand-filter-checkbox" value="${key}" ${checked} style="margin-right:5px;">
                    ${label}
                </label>
            </li>
        `);
    });
}

function updateBrandSelectionFromDom() {
    const selected = $('.brand-filter-checkbox:checked').map(function () {
        return $(this).val();
    }).get();
    searchState.brandSelection = selected;
}

function getSelectedBrandKeys() {
    return $('.brand-filter-checkbox:checked').map(function () {
        return $(this).val();
    }).get();
}

function filterVariantsByPlant(supplierName, variants, selectedPlants) {
    supplierName = supplierName.toLowerCase();
    
    // If no plants selected, return all variants
    if (!selectedPlants.length) {
        return variants;
    }
    
    // For Materom, apply plant-based filtering
    if (supplierName === 'materom') {
        return variants.filter(v => {
            const plant = v.delivery?.plant_name ?? '';
            const plantNormalized = normalizeDeliveryText(plant);
            const info = (v.delivery?.info_text ?? '').toLowerCase();
            const livrare = (v.livrare ?? '').toLowerCase();
            const combinedText = (info + ' ' + livrare).toLowerCase();
            const combinedNormalized = normalizeDeliveryText(combinedText);
            const stockColor = getStockColor(supplierName, v);
            const isAziVariant = plantNormalized.includes('timisoara') || /\b(?:azi|astazi)\b/i.test(combinedNormalized) || stockColor === '#28a745';
            const isMaineVariant = plantNormalized.includes('centru logistic') || /\bmaine\b/i.test(combinedNormalized) || stockColor === '#007bff';
            
            // 3 zile variants = "2-3 zile" or "3-4 zile" in delivery text
            const is3ZileVariant = /\b2\s*[-–]\s*3\s*zile/.test(combinedText) || /\b3\s*[-–]\s*4\s*zile/.test(combinedText);
            // Depozite externe = red variants that are NOT 2-3/3-4 zile (e.g. 4-5 zile, 7-13 zile)
            const isDepoziteExterneVariant = stockColor === '#dc3545' && !is3ZileVariant &&
                !isAziVariant && !isMaineVariant;
            
            // Azi = ONLY Timisoara (green)
            if (selectedPlants.includes('Azi') && isAziVariant) return true;
            
            // Maine = ONLY Centru Logistic (blue)
            if (selectedPlants.includes('Maine') && isMaineVariant) return true;
            
            // 2 zile = ONLY Orange variants (#fd7e14) - poimâine, 2 zile
            if (selectedPlants.includes('2 zile') && stockColor === '#fd7e14') return true;
            
            // 3 zile = variants with "2-3 zile" or "3-4 zile"
            if (selectedPlants.includes('3 zile') && is3ZileVariant) return true;
            
            // Depozite externe = red variants with 4+ days (4-5 zile, 7-13 zile, etc.)
            if (selectedPlants.includes('Depozite externe') && isDepoziteExterneVariant) return true;
            
            return false;
        });
    }
    
    // For Autonet, filter variants with "Verifica stoc" to show only when "Depozite externe" is selected
    if (supplierName === 'autonet') {
        return variants.filter(v => {
            const supplierStock = getEffectiveStock(supplierName, v);
            const livrare = v.livrare ?? '';
            const deliveryInfo = v.delivery?.info_text ?? '';
            
            // Check if this is a "Verifica stoc" variant
            const isVerificaStoc = supplierStock === 0 || 
                                   livrare.toLowerCase().includes('verifica stoc') || 
                                   deliveryInfo.toLowerCase().includes('verifica stoc') ||
                                   !deliveryInfo || deliveryInfo === '';
            
            // If it's "Verifica stoc", only show when "Depozite externe" is selected
            if (isVerificaStoc) {
                return selectedPlants.includes('Depozite externe');
            }
            
            // For other Autonet variants, apply normal plant filtering
            const stockColor = getStockColor(supplierName, v);
            
            // Azi = green variants
            if (selectedPlants.includes('Azi') && stockColor === '#28a745') return true;
            
            // Maine = blue variants
            if (selectedPlants.includes('Maine') && stockColor === '#007bff') return true;
            
            // 2 zile = ONLY Orange variants (#fd7e14)
            if (selectedPlants.includes('2 zile') && stockColor === '#fd7e14') return true;
            
            // Depozite externe = red variants (3+ days)
            if (selectedPlants.includes('Depozite externe') && stockColor === '#dc3545') return true;
            
            return false;
        });
    }
    
    // For Autopartner and Autototal, apply color-based filtering
    if (supplierName === 'autopartner' || supplierName === 'autototal') {
        return variants.filter(v => {
            const stockColor = getStockColor(supplierName, v);
            
            // Azi = green variants
            if (selectedPlants.includes('Azi') && stockColor === '#28a745') return true;
            
            // Maine = blue variants
            if (selectedPlants.includes('Maine') && stockColor === '#007bff') return true;
            
            // 2 zile = ONLY Orange variants (#fd7e14)
            if (selectedPlants.includes('2 zile') && stockColor === '#fd7e14') return true;
            
            // Depozite externe = red variants (3+ days)
            if (selectedPlants.includes('Depozite externe') && stockColor === '#dc3545') return true;
            
            return false;
        });
    }
    
    // For other suppliers, return all variants
    return variants;
}

function buildRenderData(product, selectedSuppliers, selectedPlants) {
    let subTableRows = '';
    const filteredSuppliers = [];
    const supplierData = []; // Collect supplier data first to find minimum price

    // First pass: collect all supplier data
    selectedSuppliers.forEach(supplier => {
        const supplierName = supplier.toLowerCase();
        let variants = product.suppliers?.[supplierName]?.variants || [];
        if (!variants.length) return;

        variants = filterVariantsByPlant(supplierName, variants, selectedPlants);
        if (!variants.length) return;

        filteredSuppliers.push(supplierName);

        const mainVariant = getFastestVariant(supplierName, variants);
        if (!mainVariant) return;

        supplierData.push({
            supplierName: supplierName,
            mainVariant: mainVariant,
            variants: variants,
            calculatedPrice: getCalculatedPrice(mainVariant)
        });
    });

    // Find minimum price
    let minPrice = Infinity;
    let minPriceSupplierName = null;
    let minPriceVariant = null;
    supplierData.forEach(data => {
        if (data.calculatedPrice < minPrice) {
            minPrice = data.calculatedPrice;
            minPriceSupplierName = data.supplierName;
            minPriceVariant = data.mainVariant;
        }
    });

    // Second pass: build rows
    supplierData.forEach(data => {
        const supplierName = data.supplierName;
        const mainVariant = data.mainVariant;
        const variants = data.variants;
        const isMinPrice = supplierName === minPriceSupplierName;

        const uniqueId = `qty_${product.mfrpn}_${supplierName}`.replace(/[^a-zA-Z0-9_]/g, '_');
        const encodedVariants = encodeURIComponent(JSON.stringify(variants));
        
        // Use formatted delivery info from backend (Autonet: show "3 - 4 zile" instead of "Verifica stoc")
		const livrare = mainVariant.livrare !== '-' ? mainVariant.livrare : null;
		const depozit = mainVariant.depozit !== '-' ? mainVariant.depozit : null;
		const livrareDisplay = displayLivrare(supplierName, livrare, mainVariant);
		const depozitDisplay = depozit ? displayDepozit(supplierName, depozit) : null;

		let deliveryDisplay = '-';

		if (livrareDisplay !== '-' && depozitDisplay) {
			deliveryDisplay = `${livrareDisplay} / ${depozitDisplay}`;
		} else if (livrareDisplay !== '-') {
			deliveryDisplay = livrareDisplay;
		} else if (depozitDisplay) {
			deliveryDisplay = depozitDisplay;
		}

        subTableRows += `
            <tr>
                <td>${supplierName.charAt(0).toUpperCase() + supplierName.slice(1)}</td>
                <td>
                    ${stockBadge(getEffectiveStock(supplierName, mainVariant), getStockColor(supplierName, mainVariant))}
                    ${deliveryDisplay}
					${varientIcon(mainVariant, supplierName)}
                </td>
                <td ${isMinPrice ? 'style="background-color: #d4edda;"' : ''}>
                    <span class="price-tooltip"
                          data-toggle="tooltip"
                          data-html="true"
                          title="${getPriceTooltip(mainVariant)}">
                        ${getCalculatedPrice(mainVariant)} ${mainVariant.currency ?? ''}
                    </span>
                    ${isMinPrice ? ' <strong style="color: #155724;">Cel mai mic pret !</strong>' : ''}
                    ${checkProductPromotion(product, supplierName) ? getPromotionIcon() : ''}
                    ${supplierName === 'autototal' && getPriceEPWithVAT(mainVariant) ? `
                        <br>
                        <span style="color: #ff8c00; font-size: 12px;">
                            <span style="background-color: #ff8c00; color: white; padding: 2px 4px; border-radius: 3px; margin-right: 4px;">+</span>
                            ${getPriceEPWithVAT(mainVariant)} ${mainVariant.currency ?? ''}
                        </span>
                    ` : ''}
                    ${supplierName === 'autopartner' && getDepositPriceWithVAT(mainVariant) ? `
                        <br>
                        <span style="color: #ff8c00; font-size: 12px;">
                            <span style="background-color: #ff8c00; color: white; padding: 2px 4px; border-radius: 3px; margin-right: 4px;">+</span>
                            ${getDepositPriceWithVAT(mainVariant)} ${mainVariant.currency ?? ''}
                        </span>
                    ` : ''}
                    ${supplierName === 'materom' && getWarrantyPriceWithVAT(mainVariant) ? `
                        <br>
                        <span style="color: #ff8c00; font-size: 12px;">
                            <span style="background-color: #ff8c00; color: white; padding: 2px 4px; border-radius: 3px; margin-right: 4px;">+</span>
                            ${getWarrantyPriceWithVAT(mainVariant)} ${mainVariant.currency ?? ''}
                        </span>
                    ` : ''}
                </td>
                <td>
					${supplierName === 'elit' ? `
						<a href="https://www.elit.ro/Product/${mainVariant.order_code}/${mainVariant.order_code}" 
						   target="_blank" 
						   class="btn btn-sm btn-primary">
						   Mergi la Elit
						</a>
					` : `
                    <input type="number"
                           class="form-control"
                           style="width:80px;display:inline-block;margin-right:5px;"
                           min="1" value="1" id="${uniqueId}">

                    <button class="btn btn-sm btn-success add-to-cart"
                        ${supplierName === 'autonet' ? '' : (getEffectiveStock(supplierName, mainVariant) <= 0 ? 'disabled' : '')}
                        data-supplier="${supplierName}"
                        data-product="${supplierName === 'autopartner' ? (mainVariant.order_code || product.mfrpn) : product.mfrpn}"
                        data-mfrpn="${product.mfrpn ?? ''}"
                        data-name="${product.name}"
                        data-manufacturer="${product.manufacturer?.name ?? product.manufacturer ?? ''}"
                        data-variant="${mainVariant.order_code}"
                        data-lookup-code="${supplierName === 'autototal' ? (mainVariant.api_lookup_code || mainVariant.order_code || '') : (supplierName === 'autonet' ? (mainVariant.autonet_partno || mainVariant.order_code || '') : (mainVariant.order_code || ''))}"
                        data-autonet-partno="${mainVariant.autonet_partno ?? ''}"
                        data-price="${mainVariant.price}"
                        data-calculated-price="${mainVariant.calculated_price ?? getCalculatedPrice(mainVariant)}"
                        data-currency="${mainVariant.currency}"
                        data-delivery="${mainVariant.delivery?.info_text ?? ''}"
                        data-plantname="${mainVariant.delivery?.plant_name ?? ''}"
                        data-departamentcode="${mainVariant.departamentCode ?? ''}"
                        data-depot="${mainVariant.depot ?? ''}"
                        data-livrare="${livrare}"
                        data-depozit="${depozit}">
                        <i class="glyphicon glyphicon-shopping-cart"></i>
                    </button>`}

                    ${variants.length > 1 ? `
                        <button class="btn btn-sm btn-info view-variants"
                            title="View variants"
                            style="margin-left:5px;"
                            data-variants="${encodedVariants}"
                            data-supplier-name="${supplierName}"
                            data-product-id="${product.mfrpn}"
                            data-product-name="${product.name}"
                            data-manufacturer="${product.manufacturer?.name ?? product.manufacturer ?? ''}">
                            <i class="glyphicon glyphicon-eye-open"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
    });

    if (!subTableRows) {
        return null;
    }

    const allVariantsWithSupplier = [];
    filteredSuppliers.forEach(s => {
        let variants = product.suppliers?.[s]?.variants || [];
        variants = filterVariantsByPlant(s, variants, selectedPlants);
        variants.forEach(v => {
            allVariantsWithSupplier.push({
                variant: v,
                supplier: s
            });
        });
    });

    if (!allVariantsWithSupplier.length) {
        return null;
    }

    const allVariants = allVariantsWithSupplier.map(item => item.variant);
    const totalStock = allVariantsWithSupplier.reduce((sum, item) => sum + getEffectiveStock(item.supplier, item.variant), 0);
    
    // Get the product manufacturer name (the one displayed in main row)
    const productManufacturerName = getProductBrandName(product);
    
    // Priority 1: Find variant with is_main_result = true
    let displayVariantItem = allVariantsWithSupplier.find(item => item.variant.is_main_result === true);
    
    // Priority 2: Find variant that matches the product manufacturer
    if (!displayVariantItem && productManufacturerName && productManufacturerName !== '-') {
        displayVariantItem = allVariantsWithSupplier.find(item => {
            const variantManufacturer = getManufacturerName(item.variant.manufacturer ?? null);
            return variantManufacturer && variantManufacturer.toUpperCase() === productManufacturerName.toUpperCase();
        });
    }
    
    // Priority 3: Fall back to minimum calculated price variant
    if (!displayVariantItem) {
        displayVariantItem = allVariantsWithSupplier.reduce(
            (min, item) => getCalculatedPrice(item.variant) < getCalculatedPrice(min.variant) ? item : min,
            allVariantsWithSupplier[0]
        );
    }
    
    const displayVariant = displayVariantItem.variant;
    const displaySupplier = displayVariantItem.supplier;
    const badgeColor = getParentStockColor(filteredSuppliers[0], allVariants);
    
    // Find fastest delivery supplier
    const fastestDelivery = getFastestDeliverySupplier(allVariantsWithSupplier);
    // Parent row label: for Autonet show "3 - 4 zile" instead of "Verifica stoc"
    const fastestLivrare = fastestDelivery 
        ? displayLivrare(fastestDelivery.supplier, fastestDelivery.variant.livrare || '', fastestDelivery.variant)
        : 'N/A';
    const fastestSupplierName = fastestDelivery 
        ? fastestDelivery.supplier.charAt(0).toUpperCase() + fastestDelivery.supplier.slice(1)
        : 'N/A';
    const fastestDeliveryColor = fastestDelivery 
        ? getStockColor(fastestDelivery.supplier, fastestDelivery.variant)
        : '#dc3545';

    // Get product code - clean based on supplier
    let productCode = product.mfrpn;
    if (displaySupplier === 'autonet') {
        const raw = displayVariant.order_code || product.mfrpn;
        const brandForAutonet = getManufacturerName(displayVariant.manufacturer ?? product.manufacturer ?? null);
        productCode = formatAutonetPartCodeForDisplay(raw, brandForAutonet);
	} else if (displaySupplier === 'elit') {
		productCode = cleanElitCode(productCode);
    } else if (displaySupplier === 'autopartner') {
        productCode = cleanAutopartnerCode(productCode);
    }

    const rowHtml = `
        <tr>
            <td>
                <div>
					${displayVariant.name ?? product.name ?? ''} 
					${(product.manufacturer?.name ?? product.manufacturer ?? displayVariant.manufacturer)?.toUpperCase() == 'ATE' 
					? '<span style="color:red; font-weight:bold; margin-left:5px;">+</span>' 
					: ''}
					<br>
                    <span>
						<strong>${(product.manufacturer?.name ?? product.manufacturer ?? displayVariant.manufacturer ?? '-').replace(/\s*-\s*(OEM|AM)$/i, '')}<strong>
					</span> <br>
                    <span style="font-size:12px;color:#737373;">${productCode}</span>
                </div>
            </td>
            <td style="display:inline-grid;">
                <span class="label" style="background-color:${fastestDeliveryColor}; padding:5px 16px; margin-bottom:4px; border-radius:7px; color:#fff;">
                    ${fastestSupplierName} ${fastestLivrare}
                </span>
                <small>${filteredSuppliers.length} supplier(s)</small>
            </td>
            <td>
                <span class="price-tooltip"
                      data-toggle="tooltip"
                      data-html="true"
                      title="${getPriceTooltip(displayVariant)}">
                    <strong>${minPrice !== Infinity ? minPrice : getCalculatedPrice(displayVariant)} ${minPriceVariant?.currency ?? displayVariant.currency ?? ''}</strong>
                </span>
                ${checkProductHasAnyPromotion(product, filteredSuppliers) ? getPromotionIcon() : ''}
                ${displaySupplier === 'autototal' && getPriceEPWithVAT(displayVariant) ? `
                    <br>
                    <span style="color: #ff8c00; font-size: 12px;">
                        <span style="background-color: #ff8c00; color: white; padding: 2px 4px; border-radius: 3px; margin-right: 4px;">+</span>
                        ${getPriceEPWithVAT(displayVariant)} ${displayVariant.currency ?? ''}
                    </span>
                ` : ''}
                ${displaySupplier === 'autopartner' && getDepositPriceWithVAT(displayVariant) ? `
                    <br>
                    <span style="color: #ff8c00; font-size: 12px;">
                        <span style="background-color: #ff8c00; color: white; padding: 2px 4px; border-radius: 3px; margin-right: 4px;">+</span>
                        ${getDepositPriceWithVAT(displayVariant)} ${displayVariant.currency ?? ''}
                    </span>
                ` : ''}
                ${displaySupplier === 'materom' && getWarrantyPriceWithVAT(displayVariant) ? `
                    <br>
                    <span style="color: #ff8c00; font-size: 12px;">
                        <span style="background-color: #ff8c00; color: white; padding: 2px 4px; border-radius: 3px; margin-right: 4px;">+</span>
                        ${getWarrantyPriceWithVAT(displayVariant)} ${displayVariant.currency ?? ''}
                    </span>
                ` : ''}
            </td>
            <td>
                <span class="collapse-trigger" onclick="toggleRow(this)">Deschide</span>
            </td>
        </tr>
        <tr class="collapsible-row">
            <td colspan="6" class="collapsible-content">
                <table class="sub-table table table-bordered">
                    <thead>
                        <tr>
                            <th>Furnizor</th>
                            <th>Stoc / Livrare / Depozit</th>
                            <th>Preț</th>
                            <th>Achiziție</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${subTableRows}
                    </tbody>
                </table>
            </td>
        </tr>
    `;

    // Calculate delivery priority for sorting (1=green/today, 2=blue/tomorrow, 3=orange/day after, 4=red/3+ days)
    // Use the fastest delivery supplier's priority
    const deliveryPriority = fastestDelivery ? fastestDelivery.priority : 999;
    
    return {
        html: rowHtml,
        brandName: getProductBrandName(product),
        brandKey: getProductBrandKey(product),
        sortPrice: minPrice !== Infinity ? minPrice : getCalculatedPrice(displayVariant),
        sortStock: deliveryPriority
    };
}

/**
 * Some suppliers return the same article code in slightly different formats
 * (for example "13046072032" vs "13.04607203.2"). On the backend we try to
 * normalize, but as a safety net we also merge products on the frontend
 * using a canonical "digits‑only" key so that they render in a single row.
 */
function getUnifiedProductKeyForMerge(product) {
    if (!product) return '';

    let code = product.mfrpn || '';

    // As a fallback, try to derive a code from any supplier variant
    if (!code && product.suppliers) {
        const supplierNames = Object.keys(product.suppliers);
        for (let s of supplierNames) {
            const variants = product.suppliers[s]?.variants || [];
            if (variants.length && variants[0].order_code) {
                code = variants[0].order_code;
                break;
            }
        }
    }

    code = String(code);
    // Remove dots, spaces and common separators so formats like
    // "13.04607203.2" and "13046072032" become identical.
    code = code.replace(/[.\s\-\/|\\]+/g, '');

    return code || '';
}

function mergeProductsByUnifiedCode(products) {
    if (!products || !products.length) return [];

    const merged = {};

    products.forEach(product => {
        const key = getUnifiedProductKeyForMerge(product) || (product.mfrpn || '');
        if (!key) {
            const tmpKey = 'UNKEYED_' + Math.random().toString(36).slice(2);
            merged[tmpKey] = product;
            return;
        }

        if (!merged[key]) {
            const clone = {...product};
            clone.suppliers = {...(product.suppliers || {})};
            Object.keys(clone.suppliers).forEach(s => {
                const v = clone.suppliers[s]?.variants || [];
                clone.suppliers[s] = { variants: v.slice() };
            });
            merged[key] = clone;
            return;
        }

        const existing = merged[key];

        if (!existing.name && product.name) {
            existing.name = product.name;
        }
        if (!existing.manufacturer && product.manufacturer) {
            existing.manufacturer = product.manufacturer;
        }
        if (!existing.db_name && product.db_name) {
            existing.db_name = product.db_name;
        }

        const suppliers = product.suppliers || {};
        Object.keys(suppliers).forEach(supplierName => {
            const src = suppliers[supplierName];
            if (!src || !src.variants || !src.variants.length) return;

            if (!existing.suppliers[supplierName]) {
                existing.suppliers[supplierName] = { variants: [] };
            }

            existing.suppliers[supplierName].variants =
                existing.suppliers[supplierName].variants.concat(src.variants);
        });
    });

    return Object.values(merged);
}

function renderProducts() {
    const $tbody = $('#productsTable tbody');
    $tbody.empty();

    const selectedBrands = getSelectedBrandKeys();
    
    // Merge products that share the same unified code so that
    // the same article coming from multiple suppliers (e.g.
    // Materom + Autototal) is displayed as a single row.
    const productsForRender = mergeProductsByUnifiedCode(searchState.products);
    
    // Don't update sort state here - it's already set by header clicks or dropdown changes
    // The sort state is managed by:
    // 1. Header clicks -> directly update searchState.sortKey and searchState.sortDir
    // 2. Dropdown changes -> call updateSortStateFromSelect which updates searchState

    const renderRows = [];
    productsForRender.forEach(product => {
        const brandKey = getProductBrandKey(product);
        if (selectedBrands.length && !selectedBrands.includes(brandKey)) {
            return;
        }

        const renderData = buildRenderData(product, searchState.selectedSuppliers, searchState.selectedPlants);
        if (!renderData) {
            return;
        }
        renderRows.push(renderData);
    });

    renderRows.sort((a, b) => {
        switch (searchState.sortKey) {
            case 'brand':
                if (searchState.sortDir === 'desc') {
                    return (b.brandName ?? '').localeCompare(a.brandName ?? '', 'ro', { sensitivity: 'base' });
                }
                return (a.brandName ?? '').localeCompare(b.brandName ?? '', 'ro', { sensitivity: 'base' });
            case 'price':
                if (searchState.sortDir === 'desc') {
                    return (b.sortPrice ?? Infinity) - (a.sortPrice ?? Infinity);
                }
                return (a.sortPrice ?? Infinity) - (b.sortPrice ?? Infinity);
            case 'stock':
                // Sort by availability first, then by price (low to high) within each availability group
                const stockA = a.sortStock ?? 999;
                const stockB = b.sortStock ?? 999;
                const priceA = a.sortPrice ?? Infinity;
                const priceB = b.sortPrice ?? Infinity;
                
                if (searchState.sortDir === 'desc') {
                    // First compare by availability (descending)
                    if (stockB !== stockA) {
                        return stockB - stockA;
                    }
                    // If same availability, sort by price (low to high)
                    return priceA - priceB;
                } else {
                    // First compare by availability (ascending)
                    if (stockA !== stockB) {
                        return stockA - stockB;
                    }
                    // If same availability, sort by price (low to high)
                    return priceA - priceB;
                }
            default:
                if (searchState.sortDir === 'desc') {
                    return (b.sortPrice ?? Infinity) - (a.sortPrice ?? Infinity);
                }
                return (a.sortPrice ?? Infinity) - (b.sortPrice ?? Infinity);
        }
    });

    renderRows.forEach(row => {
        $tbody.append(row.html);
    });

    let parentIndex = 0;
    $('#productsTable > tbody > tr').each(function() {
        if (!$(this).hasClass('collapsible-row')) {
            $(this).removeClass('even odd');
            $(this).addClass(parentIndex % 2 === 0 ? 'even' : 'odd');
            parentIndex++;
        }
    });

    updateHeaderSortIndicators();
    initTooltips();
    
    // Reinitialize tooltips for promotion icons
    setTimeout(() => {
        $('[data-toggle="tooltip"]').tooltip({
            html: true,
            placement: 'top'
        });
    }, 100);
}

$('#productsTable').on('click', '.view-variants', function() {
    let variants = $(this).data('variants');
    if (typeof variants === 'string') {
        variants = JSON.parse(decodeURIComponent(variants));
    }

    let supplierName = $(this).data('supplier-name');
    let productName  = $(this).data('product-name');
    let manufacturer = $(this).data('manufacturer');

    let html = '';

    variants.forEach(v => {
		let stock = getEffectiveStock(supplierName, v);
        let finalPrice = getCalculatedPrice(v);
        const rawVariantMfr = getManufacturerName(v.manufacturer ?? manufacturer ?? null);
        let variantManufacturer = stripBrandSuffix(rawVariantMfr);
        // For Materom, use matnr if available, otherwise use order_code
		

		let productCode;
		if (supplierName === 'materom') {
			if (v.matnr) {
				productCode = v.matnr;
			} else {
				let parts = v.order_code.split('#');
				productCode = parts[4] || '-';
			}
		} else if (supplierName === 'elit') {
			productCode = cleanElitCode(v.order_code || '-');
		} else if (supplierName === 'autonet') {
			productCode = formatAutonetPartCodeForDisplay(v.order_code || '-', variantManufacturer);
		} else if (supplierName === 'autopartner') {
			// Display: normalized; cart uses raw API order_code (keep hyphens for availability API).
			productCode = cleanAutopartnerCode(v.order_code || '-');
		} else {
			productCode = v.order_code || '-';
		}

        const variantCodeForOrder =
            supplierName === 'autototal' && v.variant_code
                ? v.variant_code
                : v.order_code;
        const variantApiLookupCode =
            supplierName === 'autototal'
                ? (v.api_lookup_code || v.order_code || variantCodeForOrder || '')
                : (
                    supplierName === 'autonet'
                        ? (v.autonet_partno || v.order_code || variantCodeForOrder || '')
                        : (variantCodeForOrder || '')
                );
        const productCodeForCart =
            supplierName === 'autopartner'
                ? String(v.order_code || '').trim() || productCode
                : productCode;

        html += `
            <tr>
				<td>
					<div>
						${productCode}<br>
						<span style="font-size:12px;"><strong>${variantManufacturer}</strong></span>
					</div>
				</td>
                <td>
                    ${stockBadge(getEffectiveStock(supplierName, v), getStockColor(supplierName, v))}
                    ${displayLivrare(supplierName, v.livrare, v)} / ${displayDepozit(supplierName, v.depozit)}
					${varientIcon(v, supplierName)}
                </td>
                <td>
					<span class="price-tooltip" 
						  data-toggle="tooltip" 
						  data-html="true" 
						  title="${getPriceTooltip(v)}">
						${finalPrice} ${v.currency ?? ''}
					</span>
					${supplierName === 'autototal' && getPriceEPWithVAT(v) ? `
						<br>
						<span style="color: #ff8c00; font-size: 12px;">
							<span style="background-color: #ff8c00; color: white; padding: 2px 4px; border-radius: 3px; margin-right: 4px;">+</span>
							${getPriceEPWithVAT(v)} ${v.currency ?? ''}
						</span>
					` : ''}
					${supplierName === 'autopartner' && getDepositPriceWithVAT(v) ? `
						<br>
						<span style="color: #ff8c00; font-size: 12px;">
							<span style="background-color: #ff8c00; color: white; padding: 2px 4px; border-radius: 3px; margin-right: 4px;">+</span>
							${getDepositPriceWithVAT(v)} ${v.currency ?? ''}
						</span>
					` : ''}
					${supplierName === 'materom' && getWarrantyPriceWithVAT(v) ? `
						<br>
						<span style="color: #ff8c00; font-size: 12px;">
							<span style="background-color: #ff8c00; color: white; padding: 2px 4px; border-radius: 3px; margin-right: 4px;">+</span>
							${getWarrantyPriceWithVAT(v)} ${v.currency ?? ''}
						</span>
					` : ''}
                </td>
                <td>
					${supplierName === 'elit' ? `
						<a href="https://www.elit.ro/Product/${v.order_code}/${v.order_code}"
						   target="_blank"
						   class="btn btn-sm btn-primary">
						   Go to Elit
						</a>
					` : `
						<input type="number" class="form-control qty" value="1"
							   style="width:60px; display:inline-block;"/>

						<button class="btn btn-success btn-sm modal-add-to-cart"
							${supplierName === 'autonet' ? '' : (stock <= 0 ? 'disabled title="Stoc indisponibil"' : '')}
							data-supplier="${supplierName}"
							data-product="${productCodeForCart}"
							data-mfrpn="${product.mfrpn ?? ''}"
							data-name="${productName}"
							data-manufacturer="${variantManufacturer}"
							data-variant="${variantCodeForOrder}"
							data-lookup-code="${variantApiLookupCode}"
							data-autonet-partno="${v.autonet_partno ?? ''}"
							data-price="${v.price}"
							data-calculated-price="${v.calculated_price ?? getCalculatedPrice(v)}"
							data-currency="${v.currency}"
							data-delivery="${v.delivery?.info_text ?? ''}"
							data-plantname="${v.delivery?.plant_name ?? ''}"
							data-departamentcode="${v.departamentCode ?? ''}"
							data-depot="${v.depot ?? ''}"
							data-livrare="${v.livrare || '-'}"
							data-depozit="${v.depozit || '-'}">
							<i class="glyphicon glyphicon-shopping-cart"></i>
						</button>
					`}
                </td>
            </tr>
        `;
    });

    $('#variantsModalBody').html(html);
	
	initTooltips();
    $('#variantsModal').modal('show');
});

$('#productsTable').on('click', '.add-to-cart', function () {
	if ($(this).is(':disabled')) return;
	
    let btn = $(this);
    let qty = btn.closest('td').find('input[type="number"]').val() || 1;

    openPriceModal({
        supplier: btn.data('supplier'),
        product_code: btn.data('product'),
        mfrpn: btn.data('mfrpn'),
        product_name: btn.data('name'),
        manufacturer: btn.data('manufacturer'),
        variant_code: btn.data('variant'),
        api_lookup_code: btn.data('lookup-code'),
        autonet_partno: btn.data('autonet-partno'),
        qty: qty,
        price: btn.data('price'),
        calculated_price: btn.data('calculated-price'),
        currency: btn.data('currency'),
        plantname: btn.data('plantname'),
        delivery: btn.data('delivery'),
        departamentcode: btn.data('departamentcode'),
        depot: btn.data('depot'),
        livrare: btn.data('livrare'),
        depozit: btn.data('depozit')
    });
});

$(document).on('click', '.modal-add-to-cart', function () {
	if ($(this).is(':disabled')) return;
	
    let btn = $(this);
    let qty = btn.closest('td').find('.qty').val() || 1;

    openPriceModal({
        supplier: btn.data('supplier'),
        product_code: btn.data('product'),
        mfrpn: btn.data('mfrpn'),
        product_name: btn.data('name'),
        manufacturer: btn.data('manufacturer'),
        variant_code: btn.data('variant'),
        api_lookup_code: btn.data('lookup-code'),
        autonet_partno: btn.data('autonet-partno'),
        qty: qty,
        price: btn.data('price'),
        calculated_price: btn.data('calculated-price'),
        currency: btn.data('currency'),
        plantname: btn.data('plantname'),
        delivery: btn.data('delivery'),
        departamentcode: btn.data('departamentcode'),
        depot: btn.data('depot'),
        livrare: btn.data('livrare'),
        depozit: btn.data('depozit')
    });

    $('#variantsModal').modal('hide');
});

function getDotImagePath(supplierName, variant) {
    supplierName = supplierName.toLowerCase();
    const stockColor = getStockColor(supplierName, variant);
    
    // Map color to dot image
    if (stockColor === '#28a745') return '/image/green-dot.png';      // green - today
    if (stockColor === '#007bff') return '/image/blue-dot.png';       // blue - tomorrow
    if (stockColor === '#fd7e14') return '/image/orange-dot.png';     // orange - day after tomorrow
    return '/image/red-dot.png';                                       // red - 3+ days or default
}

function varientIcon(variant, supplierName) {
    supplierName = String(supplierName).toLowerCase();

    let icons = '';
	
    if (supplierName === 'autototal') {
        if (String(variant.blockedOnReturn).toUpperCase() === 'DA') {
            icons += `
                <span
                    class="text-danger"
                    data-toggle="tooltip"
                    title="Produs blocat la retur"
                    style="margin-left:6px;">
                    <i class="glyphicon glyphicon-ban-circle"></i>
                </span>
            `;
        }

        if (
            variant.exchangePart &&
            variant.priceEP &&
            String(variant.exchangePart).trim() !== '' &&
            String(variant.priceEP).trim() !== ''
        ) {
            icons += `
                <span
                    class="text-warning"
                    data-toggle="tooltip"
                    title="Produs cu piesă la schimb"
                    style="margin-left:6px;">
                    <i class="glyphicon glyphicon-retweet"></i>
                </span>
            `;
        }
    }

    if (supplierName === 'autopartner') {
        if (variant.PossibleReturn === false) {
            icons += `
                <span
                    class="text-danger"
                    data-toggle="tooltip"
                    title="Produs nereturnabil"
                    style="margin-left:6px;">
                    <i class="glyphicon glyphicon-ban-circle"></i>
                </span>
            `;
        }
		
        if (
            variant.deposit_included === true &&
            variant.deposit_price !== undefined &&
            String(variant.deposit_price).trim() !== ''
        ) {
            icons += `
                <span
                    class="text-warning"
                    data-toggle="tooltip"
                    title="Produs cu piesă la schimb (garanție ${variant.DepositPrice})"
                    style="margin-left:6px;">
                    <i class="glyphicon glyphicon-retweet"></i>
                </span>
            `;
        }
    }
	
    if (supplierName === 'materom') {
        if (variant.has_warranty === true && variant.warranty) {
            icons += `
                <span
                    class="text-warning"
                    data-toggle="tooltip"
                    title="Produs cu piesă la schimb (garanție)"
                    style="margin-left:6px;">
                    <i class="glyphicon glyphicon-retweet"></i>
                </span>
            `;
        }
    }

    return icons;
}

function updateSortStateFromSelect(sortValue) {
    switch (sortValue) {
        case 'price_desc':
            searchState.sortKey = 'price';
            searchState.sortDir = 'desc';
            break;
        case 'price_asc':
            searchState.sortKey = 'price';
            searchState.sortDir = 'asc';
            break;
        case 'brand_asc':
            searchState.sortKey = 'brand';
            searchState.sortDir = 'asc';
            break;
        case 'brand_desc':
            searchState.sortKey = 'brand';
            searchState.sortDir = 'desc';
            break;
        case 'stock_asc':
            searchState.sortKey = 'stock';
            searchState.sortDir = 'asc';
            break;
        case 'stock_desc':
            searchState.sortKey = 'stock';
            searchState.sortDir = 'desc';
            break;
        default:
            searchState.sortKey = 'price';
            searchState.sortDir = 'asc';
            break;
    }
}

function setSelectFromSortState() {
    let value = 'stock_asc';
    if (searchState.sortKey === 'price' && searchState.sortDir === 'desc') {
        value = 'price_desc';
    } else if (searchState.sortKey === 'price' && searchState.sortDir === 'asc') {
        value = 'price_asc';
    } else if (searchState.sortKey === 'brand' && searchState.sortDir === 'asc') {
        value = 'brand_asc';
    } else if (searchState.sortKey === 'brand' && searchState.sortDir === 'desc') {
        value = 'brand_desc';
    } else if (searchState.sortKey === 'stock' && searchState.sortDir === 'asc') {
        value = 'stock_asc';
    } else if (searchState.sortKey === 'stock' && searchState.sortDir === 'desc') {
        value = 'stock_desc';
    }
    
    // Only update if sortSelect exists
    const $sortSelect = $('#sortSelect');
    if ($sortSelect.length) {
        $sortSelect.val(value);
    }
}

function updateHeaderSortIndicators() {
    // Remove all sort indicators and reset icons
    $('.sort-icon').html('⇅').css('color', '#888').removeClass('active');
    
    // Add indicator to active sort column
    const $sortIcon = $(`.sort-icon[data-sort="${searchState.sortKey}"]`);
    if ($sortIcon.length) {
        const indicator = searchState.sortDir === 'asc' ? '↑' : '↓';
        $sortIcon.html(indicator)
            .css('color', '#333')
            .css('font-weight', 'bold')
            .addClass('active');
    }
}

/* ================= SEARCH ================= */

function syncFilterButtonActives() {
    // Keep bootstrap "active" state in sync when we change checkbox values via JS
    $('.supplier-checkbox').each(function () {
        const $cb = $(this);
        const $label = $cb.closest('label.btn');
        if ($label.length) {
			const on = $cb.is(':checked');
			$label.toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
		}
    });
    $('.plant-checkbox').each(function () {
        const $cb = $(this);
        const $label = $cb.closest('label.btn');
        if ($label.length) {
			const on = $cb.is(':checked');
			$label.toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
		}
    });
    // Sync "Tot" (select all) checkbox: checked if all suppliers checked, unchecked if none, indeterminate if some
    const $master = $('.supplier-select-all-checkbox');
    if ($master.length) {
        const total = $('.supplier-checkbox').length;
        const checked = $('.supplier-checkbox:checked').length;
        $master.prop('indeterminate', checked > 0 && checked < total);
        $master.prop('checked', checked === total);
        $master.closest('label.supplier-select-all-label').toggleClass('active', checked === total).attr('aria-pressed', checked === total ? 'true' : 'false');
    }
}

$('#searchButton').on('click', function() {
    loadProducts();
});

/* ================= SELECT/UNSELECT ALL - SUPPLIERS ================= */
$(document).on('click', '.supplier-select-all', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.supplier-checkbox').prop('checked', true);
	syncFilterButtonActives();
});

$(document).on('click', '.supplier-unselect-all', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.supplier-checkbox').prop('checked', false);
	syncFilterButtonActives();
});

/* ================= SELECT/UNSELECT ALL - PLANTS ================= */
$(document).on('click', '.plant-select-all', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.plant-checkbox').prop('checked', true);
	syncFilterButtonActives();
});

$(document).on('click', '.plant-unselect-all', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.plant-checkbox').prop('checked', false);
	syncFilterButtonActives();
});

$(document).on('change', '.supplier-checkbox, .plant-checkbox', function () {
    syncFilterButtonActives();
});

/* ================= SELECT ALL SUPPLIERS (checkbox Tot) ================= */
$(document).on('change', '.supplier-select-all-checkbox', function() {
    const checked = $(this).prop('checked');
    $('.supplier-checkbox').prop('checked', checked);
    syncFilterButtonActives();
});

$('#sortSelect').on('change', function () {
    const sortValue = $(this).val();
    updateSortStateFromSelect(sortValue);
    updateHeaderSortIndicators();
    renderProducts();
});

$(document).on('change', '.brand-filter-checkbox', function () {
    updateBrandSelectionFromDom();
    renderProducts();
});

$(document).on('click', '.brand-select-all', function () {
    $('.brand-filter-checkbox').prop('checked', true);
    updateBrandSelectionFromDom();
    renderProducts();
});

$(document).on('click', '.brand-select-none', function () {
    $('.brand-filter-checkbox').prop('checked', false);
    updateBrandSelectionFromDom();
    renderProducts();
});

// Handle sort icon clicks for sorting
$(document).on('click', '.sort-icon', function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $icon = $(this);
    const sortKey = $icon.data('sort');
    if (!sortKey) return;
    
    // Get current direction from searchState or default to asc
    const currentDir = searchState.sortKey === sortKey ? searchState.sortDir : 'asc';
    const nextDir = currentDir === 'asc' ? 'desc' : 'asc';

    // Update sort state
    searchState.sortKey = sortKey;
    searchState.sortDir = nextDir;
    
    // Update select dropdown if it exists
    const $sortSelect = $('#sortSelect');
    if ($sortSelect.length) {
        setSelectFromSortState();
    }
    
    // Update visual indicators
    updateHeaderSortIndicators();
    
    // Re-render products with new sort
    renderProducts();
});

// Handle Produs header click to open brand filter
$(document).on('click', '.produs-header', function (e) {
    // Don't trigger if clicking the sort icon
    if ($(e.target).hasClass('sort-icon') || $(e.target).closest('.sort-icon').length) {
        return;
    }
    
    e.preventDefault();
    e.stopPropagation();
    
    const $dropdown = $('#brandFilterDropdown');
    const $menu = $('#brandFilterMenu');
    const isOpen = $dropdown.is(':visible') && $menu.parent().hasClass('open');
    
    // Build brand filter menu if not already built
    if (searchState.products && searchState.products.length > 0) {
        buildBrandFilterMenu(searchState.products);
    } else {
        // Show message if no products
        $menu.html('<li class="brand-filter-empty" style="padding:6px 12px;color:#777;">Nu exista rezultate. Cauta mai intai produse.</li>');
    }
    
    // Position and show/hide brand filter dropdown
    const $header = $('.produs-header');
    const headerOffset = $header.offset();
    
    if (isOpen) {
        // Close dropdown
        $menu.parent().removeClass('open');
        $dropdown.hide();
    } else {
        // Open dropdown
        if (headerOffset) {
            $dropdown.css({
                'position': 'absolute',
                'top': (headerOffset.top + $header.outerHeight() + 2) + 'px',
                'left': headerOffset.left + 'px',
                'display': 'block',
                'z-index': '1050'
            });
            
            // Show the dropdown menu
            $menu.parent().addClass('open');
        }
    }
});

// Close brand filter when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#brandFilterDropdown, .produs-header').length) {
        $('#brandFilterMenu').parent().removeClass('open');
        $('#brandFilterDropdown').hide();
    }
});

/* ================= COLLAPSIBLE ROW ================= */

function toggleRow(trigger) {
    const row = trigger.closest('tr');
    const nextRow = row.nextElementSibling;
    
    if (nextRow && nextRow.classList.contains('collapsible-row')) {
        nextRow.classList.toggle('show');
        trigger.classList.toggle('expanded');
        
        if (nextRow.classList.contains('show')) {
            trigger.textContent = 'Închide';
        } else {
            trigger.textContent = 'Deschide';
        }
    }
}

/* ================= ADD TO CART ================= */

function addToCart(productId, variantId, qtyInputId) {
    const qtyInput = document.getElementById(qtyInputId);
    if (!qtyInput) {
        alert('Eroare: input-ul de cantitate nu a fost găsit');
        return;
    }
    
    const quantity = parseInt(qtyInput.value) || 1;
    
    if (quantity < 1) {
        alert('Cantitatea trebuie să fie cel puțin 1');
        qtyInput.focus();
        return;
    }
    
    // Add to cart logic - adjust route as needed
    $.post("{{ route('searching.cartAdd') }}", {
        _token: "{{ csrf_token() }}",
        product_id: productId,
        variant_id: variantId,
        quantity: quantity
    }, function (res) {
        if (res.success) {
            alert('Produs adăugat în coș!');
        } else {
            alert(res.message || 'Eroare la adăugarea în coș');
        }
    }).fail(function() {
        alert('Eroare la adăugarea în coș');
    });
}

function getPriceTooltip(variant) {
    // Use pre-calculated breakdown from backend if available
    if (variant.price_breakdown) {
        const breakdown = variant.price_breakdown;
        const currency = variant.currency ?? '';
        return `
            Achizitie - ${breakdown.acquisition} ${currency}<br>
            10% - ${breakdown.plus_10} ${currency}<br>
            20% - ${breakdown.plus_20} ${currency}<br>
            30% - ${breakdown.plus_30} ${currency}
        `;
    }
    
    // Fallback to old calculation method
    const basePrice = variant.price ?? variant.raw_price ?? 0;
    const currency = variant.currency ?? '';
    const achizitie = Math.ceil(basePrice * 1.21); // acquisition
    const plus10 = Math.ceil(achizitie * 1.10);
    const plus20 = Math.ceil(achizitie * 1.20);
    const plus30 = Math.ceil(achizitie * 1.30);

    return `
        Achizitie - ${achizitie} ${currency}<br>
        10% - ${plus10} ${currency}<br>
        20% - ${plus20} ${currency}<br>
        30% - ${plus30} ${currency}
    `;
}

function openPriceModal(payload) {
    // Try to get calculated price from the variant data if available
    // Otherwise fallback to calculation
    let finalPrice;
    if (payload.calculated_price !== undefined && payload.calculated_price !== null) {
        finalPrice = payload.calculated_price;
    } else {
        let basePrice = parseFloat(payload.price);
        finalPrice = Math.ceil(basePrice * 1.21 * 1.35);
    }

    $('#finalPriceInput').val(finalPrice);
    $('#pricePayload').val(JSON.stringify(payload));
    $('#priceConfirmModal').modal('show');
}

function initTooltips() {
    $('[data-toggle="tooltip"]').tooltip({
        html: true,       // allow HTML like <br>
        placement: 'top'  // or 'bottom'
    });
}

$('#confirmAddToCart').on('click', function () {
    let payload = JSON.parse($('#pricePayload').val());

    let finalPrice = $('#finalPriceInput').val();

    payload.price = finalPrice;

    let qty = parseInt(payload.qty, 10) || 1;
    // Ensure variant_code is a string before calling replace
    let variantCode = String(payload.variant_code || '');
    let baseVariantCode = variantCode.replace(/qty:\d*$/, 'qty:');
	
	if (payload.supplier !== "autototal" && payload.supplier !== "autonet" && payload.supplier !== "autopartner") {
		payload.variant_code = baseVariantCode + qty;
	}

    // Calculate dot image path based on supplier and delivery info
    // Ensure departamentcode is properly extracted (handle both camelCase and lowercase)
    // Also check for any variations in the key name
    let deptCode = payload.departamentcode || payload.departamentCode || payload.departament_code || '';
    let variantForColor = {
        delivery: {
            info_text: payload.delivery || '',
            plant_name: payload.plantname || ''
        },
        departamentCode: String(deptCode).trim()
    };
    
    // Debug: log the values to help troubleshoot
    if (payload.supplier === 'autopartner') {
        console.log('Autopartner cart add - departamentCode:', deptCode, 'variantForColor:', variantForColor);
    }
    
    let dotImagePath = getDotImagePath(payload.supplier || '', variantForColor);
    payload.dot_image_path = dotImagePath;

    $.post("{{ route('searching.cartAdd') }}", {
        _token: "{{ csrf_token() }}",
        ...payload
    }, function (res) {
        alert('Produs adăugat în coș!');
        $('#priceConfirmModal').modal('hide');
    });
});

function buildPromotionsModal() {
    const $container = $('#promotionsContainer');
    $container.empty();
    
    if (!searchState.products || !searchState.products.length) {
        $container.html('<p class="text-muted">Nu au fost găsite produse. Vă rugăm să căutați mai întâi produse.</p>');
        return;
    }
    
    // Collect unique suppliers and brands
    const suppliersSet = new Set();
    const brandsSet = new Set();
    
    searchState.products.forEach(product => {
        const brandName = getProductBrandBaseName(product);
        if (brandName) {
            brandsSet.add(brandName);
        }
        
        Object.keys(product.suppliers || {}).forEach(supplierName => {
            const supplier = supplierName.toLowerCase();
            const variants = product.suppliers[supplierName]?.variants || [];
            
            if (variants.length > 0) {
                suppliersSet.add(supplier);
            }
        });
    });
    
    if (suppliersSet.size === 0 || brandsSet.size === 0) {
        $container.html('<p class="text-muted">Nu au fost găsite combinații furnizor + marcă.</p>');
        return;
    }
    
    // Sort suppliers and brands
    const sortedSuppliers = Array.from(suppliersSet).sort();
    const sortedBrands = Array.from(brandsSet).sort();
    
    // Get currently selected promotion if any
    const selectedPromotion = searchState.promotions && searchState.promotions.length > 0 
        ? searchState.promotions[0] 
        : { supplier: '', brand: '' };
    
    let html = '<div style="padding: 5px;">';
    html += '<div class="form-group">';
    html += '<label for="promotionSupplierSelect"><strong>Selectează furnizorul:</strong></label>';
    html += '<select id="promotionSupplierSelect" class="form-control" style="margin-bottom: 15px;">';
    html += '<option value="">Furnizor</option>';
    
    sortedSuppliers.forEach(supplier => {
        const supplierDisplay = supplier.charAt(0).toUpperCase() + supplier.slice(1);
        const selected = selectedPromotion.supplier.toLowerCase() === supplier ? 'selected' : '';
        html += `<option value="${supplier}" ${selected}>${supplierDisplay}</option>`;
    });
    
    html += '</select>';
    html += '</div>';
    
    html += '<div class="form-group">';
    html += '<label for="promotionBrandSelect"><strong>Selectează marca:</strong></label>';
    html += '<select id="promotionBrandSelect" class="form-control">';
    html += '<option value="">Marcă</option>';
    
    sortedBrands.forEach(brand => {
        const selected = stripBrandSuffix(selectedPromotion.brand).toLowerCase() === brand.toLowerCase() ? 'selected' : '';
        html += `<option value="${brand}" ${selected}>${brand}</option>`;
    });
    
    html += '</select>';
    html += '</div>';
    html += '</div>';
    
    $container.html(html);
}

$('#promotionsButton').on('click', function() {
    loadPromotions();
    buildPromotionsModal();
    $('#promotionsModal').modal('show');
});

$(document).on('click', '.delete-promotion', function() {
    const promotionId = $(this).data('id');
    const $item = $(this).closest('.promotion-item');
    
    if (!confirm('Sigur doriți să ștergeți această promoție?')) {
        return;
    }
    
    // Construct the URL properly
    const deleteUrl = "{{ url('searching/promotions/delete') }}/" + promotionId;
    
    $.ajax({
        url: deleteUrl,
        type: 'DELETE',
        data: {
            _token: "{{ csrf_token() }}"
        },
        success: function(res) {
            if (res.success) {
                // Remove from searchState
                searchState.promotions = searchState.promotions.filter(p => p.id != promotionId);
                updatePromotionsList();
                // Re-render products to remove promotion icons
                renderProducts();
            } else {
                alert('Ștergerea promoției a eșuat');
            }
        },
        error: function() {
            alert('Eroare la ștergerea promoției');
        }
    });
});

$('#savePromotions').on('click', function() {
    const selectedSupplier = $('#promotionSupplierSelect').val();
    const selectedBrand = $('#promotionBrandSelect').val();
    
    if (!selectedSupplier || !selectedBrand) {
        alert('Please select both Supplier and Brand.');
        return;
    }
    
    $.post("{{ route('searching.savePromotion') }}", {
        _token: "{{ csrf_token() }}",
        supplier: selectedSupplier,
        brand: selectedBrand
    }, function(res) {
        if (res.success) {
            // Add the new promotion to searchState immediately
            if (!searchState.promotions) {
                searchState.promotions = [];
            }
            
            // Check if promotion already exists in searchState
            const exists = searchState.promotions.some(p => 
                p.supplier.toLowerCase() === selectedSupplier.toLowerCase() && 
                stripBrandSuffix(p.brand).toLowerCase() === selectedBrand.toLowerCase()
            );
            
            if (!exists) {
                searchState.promotions.push({
                    id: res.promotion.id,
                    supplier: res.promotion.supplier,
                    brand: res.promotion.brand
                });
            }
            
            // Reload promotions from server to ensure sync
            loadPromotions();
            
            // Clear selects
            $('#promotionSupplierSelect').val('');
            $('#promotionBrandSelect').val('');
            
            // Re-render products immediately to show promotion icons
            renderProducts();
            
            // Show feedback
            const supplierDisplay = selectedSupplier.charAt(0).toUpperCase() + selectedSupplier.slice(1);
            alert(`Promotion saved: ${supplierDisplay} + ${selectedBrand}`);
        } else {
            alert('Salvarea promoției a eșuat: ' + (res.message || 'Unknown error'));
        }
    });
});

// Load promotions on page load
$(document).ready(function() {
    loadPromotions();
	syncFilterButtonActives();
});

$('.dropdown-toggle').dropdown();
$(function () {
    $('[data-toggle="tooltip"]').tooltip({
        html: true,       // allow HTML inside tooltip
        placement: 'top'  // show tooltip above the price
    });
});
</script>
@endsection