@if($numrows > 0)
<div class="table-responsive">
    <table class="table table-light" id="incasari-table" style="width:100%; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #f0f8ff; border-bottom: 2px solid #dee2e6;" class="info">
                <th style="padding: 10px; text-align: center;">Data comanda</th>
                <th style="padding: 10px; text-align: center;">Utilizator</th>
                <th style="padding: 10px; text-align: left;">Client</th>
                <th style="padding: 10px; text-align: right;">Suma incasata</th>
                <th style="padding: 10px; text-align: center;">Magazin</th>
                <th style="padding: 10px; text-align: center;">Metoda incasare</th>
            </tr>
        </thead>
        <tbody>
        @foreach($data as $row)
            @php
                $id = $row->id;
                $id_comanda = $row->idcmd;
                $id_client = $row->idclient;
                $magazin = $row->locatie_mgz;
                
                if($magazin == '1'){
                    $nume_magazin = "Timisoara";
                } else {
                    $nume_magazin = "Utvin";
                }
                
                //tip incasare
                $tip_incas = $row->idstare;
                if($tip_incas == 3){
                    $nume_incasare="Cash";
                } else if($tip_incas == 4){
                    $nume_incasare="Avans";
                } else if($tip_incas == 5){
                    $nume_incasare="Retur";
                } else if($tip_incas == 6){
                    $nume_incasare="Card";
                } else if($tip_incas == 7){
					$nume_incasare="FD";
                } else if($tip_incas == 9){
                    $nume_incasare="OP";
				} else if($tip_incas == 10){
                    $nume_incasare="Avans FD";
				} else if($tip_incas == 11){
                    $nume_incasare="Avans Cash";
				} else if($tip_incas == 12){
                    $nume_incasare="Avans Card";
				} else if($tip_incas == 13){
                    $nume_incasare="Avans OP";
                } else {
                    $nume_incasare="Necunoscut";
                }

                $data = date("d/m/Y", strtotime($row->data));
                $data_cmd = $row->datacmd ? date("d/m/Y", strtotime($row->datacmd)) : '-';
                $nume = $row->nume;
                $companie_com = $row->companie;
                if (empty($companie_com)) {
                    $numeafis = $nume;
                } else {
                    $numeafis = $nume;
                }
                
                $total = $row->suma;
                $total_f = number_format($total, 2, '.', ',');
				
				if($row->idclient == 0){
					$numeafis = $row->cstmtext ? $row->cstmtext : '-';
				}
            @endphp
            
            <tr class="data-row {{empty($row->datacmd) ? 'red-row-cs' : ''}}" style="border-bottom: 1px solid #dee2e6;">
                <td style="padding: 12px; text-align: center;">{{ $data_cmd }}</td>
                <td style="padding: 12px; text-align: center;">
					<p>{{ $row->user_name }}</p>
					{{ \Carbon\Carbon::parse($row->data)->format('d/m/Y') }}
					@if($row->data_time)
						<p>{{ \Carbon\Carbon::parse($row->data_time)->format('H:i') }}</p>
					@endif
				</td>
                <td style="padding: 12px; text-align: left;">{{ $numeafis }}</td>
                <td style="padding: 12px; text-align: right;">{{ $total_f }}</td>
                <td style="padding: 12px; text-align: center;">{{ $nume_magazin }}</td>
                <td style="padding: 12px; text-align: center;">{{ $nume_incasare }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <table class="table table-light" style="width:100%">
        <tr class="info">
            <td class="bottom">
                <div style="font-size: 24px"><!-- pretend an enclosing class has big font size -->
                    <span class="label label-info label-as-badge">Total zi: {{ $tot_zi_f }}</span>
                </div>
            </td>
            <td class="bottom">
                <div style="font-size: 24px"><!-- pretend an enclosing class has big font size -->
                    <span class="label label-success label-as-badge">Cash: {{ $tot_cash_f }}</span>
                </div>
            </td>
            <td class="bottom">
                <div style="font-size: 24px"><!-- pretend an enclosing class has big font size -->
                    <span class="label label-success label-as-badge">OP: {{ $tot_op_f }}</span>
                </div>
            </td>
            <td class="bottom">
                <div style="font-size: 24px"><!-- pretend an enclosing class has big font size -->
                    <span class="label label-success label-as-badge">Card: {{ $tot_card_f }}</span>
                </div>
            </td>
            <td class="bottom">
                <div style="font-size: 24px"><!-- pretend an enclosing class has big font size -->
                    <span class="label label-success label-as-badge">FD: {{ $tot_fd_f }}</span>
                </div>
            </td>
            <td class="bottom">
                <div style="font-size: 24px"><!-- pretend an enclosing class has big font size -->
                    <span class="label label-success label-as-badge">Retur: {{ $tot_retur_f }}</span>
                </div>
            </td>
            <td class="bottom">
                <div style="font-size: 24px">
                    <span class="label label-success label-as-badge">Avans: {{ $tot_avans_f }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan=12><span class="pull-right">
                <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                    <ul class="pagination">
                        @if($page > 1)
                        <li><a href="javascript:void(0);" onclick="loadIncomeData({{ $page-1 }})" style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #337ab7;">&laquo; Prev</a></li>
                        @else
                        <li class="disabled"><span style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #777;">&laquo; Prev</span></li>
                        @endif
                        
                        @for($i = 1; $i <= $total_pages; $i++)
                            @if($page == $i)
                            <li><span style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; background-color: #337ab7; color: white;">{{ $i }}</span></li>
                            @else
                            <li><a href="javascript:void(0);" onclick="loadIncomeData({{ $i }})" style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #337ab7;">{{ $i }}</a></li>
                            @endif
                        @endfor
                        
                        @if($page < $total_pages)
                        <li><a href="javascript:void(0);" onclick="loadIncomeData({{ $page+1 }})" style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #337ab7;">Next &raquo;</a></li>
                        @else
                        <li class="disabled"><span style="border: 1px solid #ddd; padding: 6px 12px; margin-left: -1px; text-decoration: none; color: #777;">Next &raquo;</span></li>
                        @endif
                    </ul>
                </div>
            </span></td>
        </tr>
    </table>
</div>
<div class="clearfix"></div>
@else
<!--<div class="alert alert-info">
    <h4><i class="fa fa-info-circle"></i> Info</h4>
    <p>Nu există înregistrări pentru data selectată.</p>
</div>
<div class="clearfix"></div>-->
@endif