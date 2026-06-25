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
        <button class="btn btn-primary" onclick="window.print()">Printează factura</button>
        <a href="{{ route('facturi.index') }}" class="btn btn-default">Înapoi la facturi</a>
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
                <div><span class="label">NUMAR:</span> {{ $factura->seria }} {{ $factura->OrderID }}</div>
                <div><span class="label">DATA:</span> {{ date('d.m.Y', strtotime($factura->OrderDate)) }}</div>
                <div><span class="label">SCADENTA:</span> {{ date('d.m.Y', strtotime($factura->RequiredDate)) }}</div>
            </div>
        </div>
    </div>
    
    <div class="party-details">
        <div class="party-box">
            <div class="party-heading">FURNIZOR</div>
            <div class="party-content">
                <div><strong>BESOIU PIESE AUTO SRL</strong></div>
                <div>CUI: RO 31298897</div>
                <div>ROONRC: J35/544/2013</div>
                <div>Adresa: Utvin, nr. 489, jud. Timis, Romania</div>
                <div>BANCA: Raiffeisen Bank</div>
                <div>CONT: RO32 RZBR 0000 0600 2191 4930</div>
            </div>
        </div>
<div class="party-box">
    <div class="party-heading">CLIENT</div>
    <div class="party-content">
        @if($factura->client)
            <div style="font-weight: bold; color: #333; font-size: 14px;">
                {{ $factura->client->nume ?: 'Client necunoscut' }}
            </div>
            
            @if($factura->client->cif)
                <div>CUI/CNP: {{ $factura->client->cif }}</div>
            @endif
            
            @if($factura->client->regcom)
                <div>Reg.Com.: {{ $factura->client->regcom }}</div>
            @endif
            
            @if($factura->client->adresa)
                <div>Adresa: {{ $factura->client->adresa }}</div>
            @endif
            
            <div>Județ: {{ $factura->client->judet ?? 'Timiș' }} România</div>
            
            @if($factura->client->telefon)
                <div>Tel: {{ $factura->client->telefon }}</div>
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
        @php
            $subtotal = 0;
            $totalTva = 0;
        @endphp
        
        @forelse($details as $index => $detail)
        @php
            $subtotal += $detail->UnitPrice * $detail->Quantity;
            $totalTva += $detail->tva;
        @endphp
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $detail->produs }}</td>
            <td>{{ $detail->um ?? 'buc' }}</td>
            <td class="amount-column">{{ number_format($detail->cantitate, 2, '.', '') }}</td>
            <td class="amount-column">{{ number_format($detail->pret_unitar, 2, '.', '') }}</td>
            <td class="amount-column">{{ number_format($detail->UnitPrice * $detail->Quantity, 2, '.', '') }}</td>
            <td class="amount-column">{{ number_format($detail->tva, 2, '.', '') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="text-center">Nu există produse în factură</td>
        </tr>
        @endforelse
    </tbody>
</table>    
    <div class="totals-section">
        <div class="totals-row">
            <div class="totals-label">Subtotal</div>
            <div class="totals-value">{{ number_format($subtotal, 2, '.', '') }}</div>
        </div>
        <div class="totals-row">
            <div class="totals-label">TVA 19%</div>
            <div class="totals-value">{{ number_format($totalTva, 2, '.', '') }}</div>
        </div>
        <div class="totals-row">
            <div class="totals-label">Total</div>
            <div class="totals-value total-final">{{ number_format($subtotal + $totalTva, 2, '.', '') }}</div>
        </div>
    </div>
    
<div class="stamp">
    <div style="width: 120px; transform: rotate(-10deg); position: absolute; top: -110px; left: 20px; font-size:20px;border: 2px solid #a52a2a; padding: 5px; color: #a52a2a; font-weight: bold; text-align: center; background-color: white;">
        {{ $numeTipPlata }}
    </div>
    <div class="legal-text">
        Circula fara semnatura si stampila conform art. 319(29) Legea 227/2015 privind Codul Fiscal<br>
        Intocmita de BESOIUFLORIN-CRISTIAN CNP: 1860624350081 CI TZ372629
    </div>
</div>
    
   <div class="footer">
    {{ $numeTipPlata }}<br>
    Seria/Numar {{ $factura->seria }} {{ $factura->OrderID }} din data: {{ date('d.m.Y', strtotime($factura->OrderDate)) }}
</div>
    
    <div style="margin-top: 20px;">
        <div>BESOIU PIESE AUTO SRL</div>
        <div>CUI: RO 31298897, Reg. Com.: J35/544/2013</div>
        <div>Adresa: Utvin, nr. 489, jud. Timis, Romania</div>
    </div>
    
    
</div>
@endsection