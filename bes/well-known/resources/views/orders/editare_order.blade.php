<table class="table table-bordered">
    <thead>
        <tr class="warning">
            <th>Nr. Crt.</th>
            <th>PRODUS</th>
            <th>COD PRODUS</th>
            <th>CANT.</th>
            <th>PRET</th>
            <th>VALOARE.</th>
            <th></th>
        </tr>
    </thead>
    <tbody id="invoice-items-body">
        @if(count($orderDetails) > 0)
            @php
                $total_factura = 0;
            @endphp
            @foreach($orderDetails as $detail)
                @php
                    // Force negative quantities
                    $quantity = abs($detail->cantitate);
                    $pret_unitar = $detail->pret;

                    $pret_unitar_f = number_format($pret_unitar, 2);//Formateo variables
                    $pret_unitar_r = str_replace(",", "", $pret_unitar_f);//Reemplazo las comas
                    $valoare = $pret_unitar_r * $quantity;
                    $valoare_f = number_format($valoare, 2);//Precio total formateado
                    $valoare_r = str_replace(",", "", $valoare_f);//Reemplazo las comas

                    $total_factura +=$valoare_r;//Sumador
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $detail->denumire }}</td>
                    <td>{{ $detail->cod_produs }}</td>
                    <td>{{ $quantity }}</td>
                    <td>{{ number_format($pret_unitar, 2) }}</td>
                    <td>{{ $valoare_f }}</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-xs delete-item" data-id="{{ $detail->idprodus }}">
                            <i class="glyphicon glyphicon-trash"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        @else
            @php
                $total_factura = 0;//Sumador
            @endphp
            <tr>
                <td colspan="7" class="text-center">No items found</td>
            </tr>
        @endif
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="text-right"><strong>TOTAL</strong></td>
            <td><span id="total-amount">{{ number_format($total_factura, 2) }}</span></td>
            <td></td>
        </tr>
    </tfoot>
</table>
                        