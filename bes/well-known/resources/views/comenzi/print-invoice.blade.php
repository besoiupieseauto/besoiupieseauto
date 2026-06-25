@extends('layouts.mainapp')

@section('title', 'Printare Factura')

<style>
   body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    margin: 0;
    padding-bottom: 60px;
    -webkit-print-color-adjust: exact !important; /* Chrome, Safari */
    color-adjust: exact !important; /* Firefox */
    print-color-adjust: exact !important; /* Future standard */
}

.invoice-container {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid #000;
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    margin-top: 60px;
}

.company-logo {
    width: 260px;
    padding: 10px;
}

.invoice-title {
    font-size: 24px;
    font-weight: bold;
    text-align: center;
    margin-bottom: 10px;
}

.invoice-details {
    text-align: right;
}

.invoice-details div {
    margin-bottom: 5px;
}

.invoice-details .label {
    font-weight: bold;
    color: #a52a2a;
}

.party-details {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.party-box {
    width: 48%;
}

.party-heading {
    color: #a52a2a;
    font-weight: bold;
    margin-bottom: 5px;
    border-bottom: 1px solid #a52a2a;
    padding-bottom: 5px;
}

.party-content {
    margin-top: 10px;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.invoice-table th {
    background-color: #f0f0f0 !important;
    padding: 8px;
    text-align: left;
   
}

.invoice-table td {
    padding: 8px;
    border: 1px solid #f0f0f0;
    background-color: #ffffff;
}

.invoice-table tr:nth-child(even) td {
    background-color: #f9f9f9 !important;
}

.invoice-table .amount-column {
    text-align: center;
}

.totals-section {
    text-align: right;
    margin-top: 10px;
}

.totals-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 5px;
}

.totals-label {
    width: 100px;
    text-align: left;
}

.totals-value {
    width: 100px;
    background-color: #f0f0f0 !important;
    text-align: right;
    padding: 5px;
}

.total-final {
    background-color: #a52a2a !important;
    color: white !important;
}

.stamp {
    margin-top: 40px;
    position: relative;
}

.stamp img {
    width: 120px;
    transform: rotate(-15deg);
    position: absolute;
    top: -170px;
    left: 20px;
}

.legal-text {
    font-size: 13px;
    padding-top: 20px;
    border-bottom: 1px solid #a52a2a;
}

.footer {
    text-align: center;
    margin-top: 20px;
    font-weight: bold;
}

@media print {
    /* Force portrait orientation */
    @page {
        size: A4 portrait;
        margin: 0;
    }

    /* Ensure the print button is hidden */
    .print-button {
        display: none !important;
    }

    /* Hide URL and timestamp in footer */
    @page {
        margin-bottom: 0;
    }
    
    body::after {
        content: none !important;
    }
    
    /* Hide browser-added headers and footers */
    html {
        margin: 0;
        padding: 0;
    }
    
    body {
        margin: 0;
        padding: 0;
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    /* Keep background colors and images when printing */
    * {
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
        print-color-adjust: exact !important;
    } 
    
    /* Ensure the total red background is preserved */
    .total-final {
        background-color: #a52a2a !important;
        color: white !important;
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

@section('content')
<div class="invoice-container">
    <div class="print-button" style="text-align: center; margin-top: 20px;">
        <button class="btn btn-primary" id="print-btn">Printează factura</button>
        <a href="{{ route('comenzi.index') }}" class="btn btn-default">Înapoi la comenzi</a>
    </div>
    <div class="invoice-header">
        <div class="company-logo">
            <div style="display: flex; align-items: center;">
               <img src="{{ asset('assets/image/Capture.jpg') }}" alt="Company Logo" style="width: 100%; height: 100%; border-radius: 4%;">
            </div>
        </div>
        <div>
            <div class="invoice-title">FACTURA FISCALA</div>
            <div class="invoice-details">
                <div><span class="label">NUMAR:</span> {{ $invoice->seria ?? 'BPA_C' }} {{ $invoice->OrderID }}</div>
                <div><span class="label">DATA:</span> {{ date('d.m.Y', strtotime($invoice->OrderDate)) }}</div>
                <div><span class="label">SCADENTA:</span> {{ date('d.m.Y', strtotime($invoice->RequiredDate)) }}</div>
            </div>
        </div>
    </div>
    
    <div class="party-details">
        <div class="party-box">
            <div class="party-heading">FURNIZOR</div>
            <div class="party-content">
                <div><strong>UTVIN AUTO PARTS SRL</strong></div>
                <div>CUI: RO 45123456</div>
                <div>ROONRC: J35/123/2020</div>
                <div>Adresa: Utvin, nr. 489, jud. Timis, Romania</div>
                <div>BANCA: Raiffeisen Bank</div>
                <div>CONT: RO32 RZBR 0000 0600 1234 5678</div>
            </div>
        </div>
        <div class="party-box">
            <div class="party-heading">CLIENT</div>
            <div class="party-content">
                @if(isset($client))
                    <div style="font-weight: bold; color: #333; font-size: 14px;">
                        {{ $client->companie ?: $client->nume }}
                    </div>
                    
                    @if(isset($client->cif) && $client->cif)
                        <div>CUI/CNP: {{ $client->cif }}</div>
                    @endif
                    
                    @if(isset($client->regcom) && $client->regcom)
                        <div>Reg.Com.: {{ $client->regcom }}</div>
                    @endif
                    
                    @if(isset($client->adresa) && $client->adresa)
                        <div>Adresa: {{ $client->adresa }}</div>
                    @endif
                    
                    <div>Judet: {{ $client->judet ?? 'Timis' }} Romania</div>
                    
                    @if(isset($client->telefon) && $client->telefon)
                        <div>Tel: {{ $client->telefon }}</div>
                    @endif
                @else
                    <div><strong>Client necunoscut</strong></div>
                @endif
            </div>
        </div>
    </div>    
    
    <table class="invoice-table">
        <thead>
            <tr>
                <th>NR.</th>
                <th>PRODUS</th>
                <th>UM</th>
                <th>CANTITATE</th>
                <th>PRET UNITAR</th>
                <th>VALOARE</th>
                <th>T.V.A.</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($details) && $details->count() > 0)
                @foreach($details as $index => $detail)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $detail->denumire }}</td>
                    <td>{{ $detail->um ?? 'buc' }}</td>
                    <td class="amount-column">{{ number_format($detail->Quantity, 2, '.', '') }}</td>
                    <td class="amount-column">{{ number_format($detail->UnitPrice, 2, '.', '') }}</td>
                    <td class="amount-column">{{ number_format($detail->UnitPrice * $detail->Quantity, 2, '.', '') }}</td>
                    <td class="amount-column">{{ number_format($detail->tva, 2, '.', '') }}</td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="7" class="text-center">Nu există produse în factură</td>
                </tr>
            @endif
        </tbody>
    </table>    
    
    <div class="totals-section">
        <div class="totals-row">
            <div class="totals-label">Subtotal</div>
            <div class="totals-value">{{ number_format($subtotal, 2, '.', '') }}</div>
        </div>
        <div class="totals-row">
            <div class="totals-label">TVA 19%</div>
            <div class="totals-value">{{ number_format($totalVAT, 2, '.', '') }}</div>
        </div>
        <div class="totals-row">
            <div class="totals-label">Total</div>
            <div class="totals-value total-final">{{ number_format($total, 2, '.', '') }}</div>
        </div>
    </div>
    
    <div class="stamp">
        <div style="width: 120px; transform: rotate(-10deg); position: absolute; top: -110px; left: 20px; font-size:20px;border: 2px solid #a52a2a; padding: 5px; color: #a52a2a; font-weight: bold; text-align: center; background-color: white;">
            {{ $numeTipPlata ?? 'ACHITAT' }}
        </div>
        <div class="legal-text">
            Circula fara semnatura si stampila conform art. 319(29) Legea 227/2015 privind Codul Fiscal<br>
            Intocmita de UTVIN AUTO PARTS SRL CNP: 1234567890123 CI TZ123456
        </div>
    </div>
    
    <div class="footer">
        {{ $numeTipPlata ?? 'ACHITAT' }}<br>
        Seria/Numar {{ $invoice->seria ?? 'BPA_C' }} {{ $invoice->OrderID }} din data: {{ date('d.m.Y', strtotime($invoice->OrderDate)) }}
    </div>
    
    <div style="margin-top: 20px;">
        <div>UTVIN AUTO PARTS SRL</div>
        <div>CUI: RO 45123456, Reg. Com.: J35/123/2020</div>
        <div>Adresa: Utvin, nr. 489, jud. Timis, Romania</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Print invoice page loaded');
    
    // Print button click handler
    document.getElementById('print-btn').addEventListener('click', function() {
        window.print();
    });
    
    // Check if parent page needs refresh
    if (localStorage.getItem('reload_after_invoice') === 'true') {
        console.log('Found reload flag - will refresh parent');
        
        // Get the order ID and date from localStorage
        const orderId = localStorage.getItem('last_order_id');
        const lastDate = localStorage.getItem('last_date');
        
        console.log('Values from localStorage:', {
            order_id: orderId,
            last_date: lastDate
        });
        
        // Function to refresh parent
        function refreshParent() {
            if (window.opener) {
                // Build URL with cache buster
                const url = new URL('/comenzi', window.location.origin);
                url.searchParams.set('date', lastDate || '');
                url.searchParams.set('cache_buster', new Date().getTime());
                
                console.log('Refreshing parent window with URL:', url.toString());
                window.opener.location.href = url.toString();
                
                // Clear localStorage after refresh
                localStorage.removeItem('reload_after_invoice');
                localStorage.removeItem('last_order_id');
                localStorage.removeItem('last_date');
            }
        }
        
        // Set multiple triggers to ensure refresh happens
        window.addEventListener('afterprint', refreshParent);
        window.addEventListener('unload', refreshParent);
        setTimeout(refreshParent, 1000); // Backup timeout
    }
});
</script>



@endsection