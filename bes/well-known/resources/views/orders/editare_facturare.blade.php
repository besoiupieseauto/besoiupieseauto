<table class="table table-bordered">
    <thead>
        <tr class="warning">
            <th>Nr. Crt.</th>
            <th>PRODUS</th>
            <th>UM</th>
            <th>CANT.</th>
            <th>PRET UNIT.</th>
            <th>VALOARE.</th>
            <th>TVA</th>
            <th>Cota TVA</th>
            <th></th>
        </tr>
    </thead>
    <tbody id="invoice-items-body">
        @if(count($orderDetails) > 0)
            @php
                $suma_total = 0;
                $suma_tva = 0;
            @endphp
            @foreach($orderDetails as $detail)
                @php
                    // Force negative quantities
                    $quantity = $quantity_multiplier * abs($detail->cantitate);
                    $pret_unitar = $detail->pret / (($detail->TVA + 100)/100);

                    $pret_unitar_f = number_format($pret_unitar, 2);//Formateo variables
                    $pret_unitar_r = str_replace(",", "", $pret_unitar_f);//Reemplazo las comas
                    $valoare = $pret_unitar_r * $quantity;
                    $valoare_f = number_format($valoare, 2);//Precio total formateado
                    $valoare_r = str_replace(",", "", $valoare_f);//Reemplazo las comas

                    $ctva = $valoare_r * $detail->TVA / 100;//Tva

                    $suma_total +=$valoare_r;//Sumador
                    $suma_tva +=$ctva;

                    // order total
                    $total_factura = $suma_total + $suma_tva;
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $detail->denumire }}</td>
                    <td>{{ $detail->um }}</td>
                    <td>{{ $quantity }}</td>
                    <td>{{ number_format($pret_unitar, 2) }}</td>
                    <td>{{ $valoare_f }}</td>
                    <td>{{ number_format($ctva, 2) }}</td>
                    <td>{{ $detail->TVA }}</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-xs delete-item" data-id="{{ $detail->idprodus }}">
                            <i class="glyphicon glyphicon-trash"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        @else
            @php
                $suma_total =0;//Sumador
                $suma_tva =0;//Sumador TVA
            @endphp
            <tr>
                <td colspan="9" class="text-center">No items found</td>
            </tr>
        @endif
    </tbody>
    <tfoot>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="text-right"><strong>SUBTOTAL</strong></td>
            <td><span id="sub-total-amount">{{ number_format($suma_total, 2, '.', '') }}</span></td>
            <td><span id="total-tva">{{ number_format($suma_tva, 2, '.', '') }}</span></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="text-right"><strong>TOTAL</strong></td>
            <td><span id="total-amount">{{ number_format($total_factura, 2) }}</span></td>
            <td>lei</td>
            <td></td>
        </tr>
    </tfoot>
</table>
                        