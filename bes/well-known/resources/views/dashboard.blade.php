@extends('layouts.mainapp')

@section('title', 'Page Title')
@section('content')
    <div class="jumbotron">        
            <div class="container-fluid">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <div class="btn-group pull-right">
                            <a  href="comanda_noua.php" class="btn btn-info"><span class="glyphicon glyphicon-plus" ></span> Comanda noua</a>
                        </div>
                        <h4><i class='glyphicon glyphicon-search'></i> Comenzi Timisoara</h4>
                    </div>
                    <div class="panel-body">
                            <!-- Modal edit status tm-->
    <div class="modal fade" id="mod_status" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editare status comanda</h4>
                </div>
                <form class="form-horizontal" id="frmeditare_status" method="post" name="editare_status">
                    <div class="col-md-12 col-sm-8"><input class="form-control form-control" type="hidden" id="mod_id_cmd" name="mod_id_cmd"></div>
                    <div class="modal-body">
                        <div id="rezultat_ajax_status"></div>
						<sup style="font-size: 15.5px;font-weight: bold;text-decoration:  underline;">Status comanda</sup>
                        <div class="form-group" style="margin: 18px;margin-bottom: 12px;margin-top: 0px;">
							<label class="control-label btn btn-succes" id="label1" style="background: rgb(235,147,22);margin: 3px;padding: 7px 12px 5px;padding-left: 10px;padding-bottom: 6px;">
								<input type="radio" id="mod_stare1" name="stare" value="1"> Comandat </label>
							<label class="control-label btn btn-succes" id="label2" style="background: rgb(42,171,210);margin: 6px;">
								<input type="radio" id="mod_stare2" name="stare" value="2"> Sosit </label></div>
						<sup style="font-size: 15.5px;font-weight: bold;text-decoration:  underline;">Incasare comanda</sup>
                        <div class="form-group" style="margin-right: -7px;margin-bottom: 9px;margin-top: 0px;">
							<label class="control-label btn btn-success" id="label3" style="margin: 6px;">
								<input type="radio" id="mod_stare3" name="stare" value="3"> Cash</label>
							<label class="control-label btn btn-success" id="label6" style="margin: 6px;background: rgb(18,38,18);">
								<input type="radio" id="mod_stare6" name="stare" value="6"> Card</label>
							<label class="control-label btn btn-success" id="label7" style="margin: 6px;background: rgb(220,125,13);">
								<input type="radio" id="mod_stare7" name="stare" value="7"> FD</label>
							<label class="control-label btn btn-success" id="label5" style="background: rgb(101,78,240);margin: 6px;">
								<input type="radio" id="mod_stare5" name="stare" value="5"> Retur </label>
							<label class="control-label btn btn-succes" id="label4" style="background: rgb(193,46,42);margin: 3px;">
								<input type="radio" id="mod_stare4" name="stare" value="4"> Avans </label></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-success" id="actualizare_date" type="submit">Actualizare date</button></div>
                </form>
            </div>
        </div>
    </div>
        <!-- Modal edit culoare-->
    <div class="modal fade" id="mod_culoare" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editare status piesa</h4>
                </div>
                <form class="form-horizontal" method="post" id="frmeditare_culoare" name="editare_culoare">
                    <input type="hidden" class="form-control" name="mod_id_cmd_fur" id="mod_id_cmd_culoare">
                    <input type="hidden" class="form-control" name="mod_id_prod_fur" id="mod_id_prod_culoare">
                    <div class="modal-body">
                        <div id="rezultat_ajax_culoare"></div>
                        <div class="form-group">
                            <div class="col-sm-8">
                                
                            </div>
                        </div>
                        <div class="form-group">

                            <div class="funkyradio" >
                                <div class="funkyradio-default">
                                    <label class="btn btn-maine  btn-lg btn-block">
                                        <input type="radio" name="xcul" id="mod_cul1" value="7CFC00">
                                        Azi </label>
                                </div>        
                                <div class="funkyradio-default">
                                    <label class="btn btn-poimaine  btn-lg btn-block">
                                        <input type="radio" name="xcul" id="mod_cul2" value="ADD8E6">
                                        Maine </label>
                                </div>        
                                <div class="funkyradio-default">
                                    <label class="btn btn-more3  btn-lg btn-block">
                                        <input type="radio" name="xcul" id="mod_cul3" value="FF0000">
                                        >2 zile </label>
                                </div>
                                <div class="funkyradio-default">
                                    <label class="btn btn-default  btn-lg btn-block">
                                        <input type="radio" name="xcul" id="mod_cul4" value="FFFFFF">
                                        Sosit </label>
                                </div>                                
                            </div>
                        </div>

                    </div>                      
                    <div class="modal-footer">
                         <button type="submit" class="btn btn-success" id="actualizare_culoare">Actualizare date</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
        <!-- Modal edit total-->
    <div class="modal fade" id="mod_total" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editare total comanda</h4>
                </div>
                <form class="form-horizontal" method="post" id="frmeditare_total" name="editare_status">
                    <div class="modal-body">
                        <div id="rezultat_ajax_total"></div>
                        <div class="form-group">
                            <div class="col-sm-8">
                                <input type="hidden" class="form-control" name="mod_id_cmd" id="mod_id_cmd">

                            </div>
                        </div>
                        <div class="form-group">
                            <label for="total" class="col-sm-3 control-label">Total actual</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="mod_total_cmd" name="mod_total_cmd" placeholder="Total comanda" readonly></input>
                            </div> 
                        </div>
                        <div class="form-group">
                            <label for="transport" class="col-sm-3 control-label">Transport</label>
                            <div class="col-sm-8">
                                <input type="number" step="any" class="form-control" id="mod_total_nou_cmd" name="mod_total_nou_cmd" placeholder="Transport" value="0"></input>
                            </div>
                        </div>

                    </div>                      
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" id="actualizare_total">Actualizare date</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
        <!-- Modal edit sms-->
    <div class="modal fade" id="mod_sms" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Trimite SMS</h4>
                </div>
                <form class="form-horizontal" method="post" id="frmeditare_sms" name="editare_sms">
                    <div class="modal-body">
                        <div id="rezultat_ajax_sms"></div>
                        <div class="form-group">
                            <div class="col-sm-8">
                                <input type="hidden" class="form-control" name="mod_id_sms" id="mod_id_sms">
                                <input type="hidden" class="form-control" name="mod_awb_sms" id="mod_awb_sms">
                                <input type="hidden" class="form-control" name="mod_total_sms" id="mod_total_sms">

                            </div>
                        </div>
                        <div class="form-group">
                            <label for="nume" class="col-sm-3 control-label">Nume client</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="mod_nume_sms" name="mod_nume_sms" placeholder="Nume client" readonly></input>
                            </div> 
                        </div>
                        <div class="form-group">
                            <label for="telefon" class="col-sm-3 control-label">Telefon</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="mod_tel_sms" name="mod_tel_sms" placeholder="Telefon"></input>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="mesaj" class="col-sm-3 control-label">Mesaj</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" id="mod_mesaj" name="mod_mesaj" placeholder="Mesaj" required></textarea>
                            </div>
                        </div>
                    </div>                      
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" id="actualizare_sms">Trimite SMS</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
        <!-- Modal edit furnizor-->
    <div class="modal fade" id="mod_furnizor" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Furnizor piesa</h4>
                </div>
                <form class="form-horizontal" method="post" id="frmeditare_furnizor" name="editare_furnizor">
                    <div class="modal-body">
                        <div id="rezultat_ajax_fur"></div>
                        <div class="form-group">
                            <div class="col-sm-8">
                                <input type="hidden" class="form-control" name="mod_id_cmd_fur" id="mod_id_cmd_fur">
                                <input type="hidden" class="form-control" name="mod_id_prod_fur" id="mod_id_prod_fur">
                            </div>
                        </div>
                        <div class="form-group">

                            <div class="funkyradio" >
                                                                    <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur0" value="ET">
                                            ET </label>
                                    </div>
                                                                        <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur1" value="MA">
                                            MA </label>
                                    </div>
                                                                        <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur2" value="IC">
                                            IC </label>
                                    </div>
                                                                        <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur3" value="AN">
                                            AN </label>
                                    </div>
                                                                        <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur4" value="AT">
                                            AT </label>
                                    </div>
                                                                        <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur5" value="BA">
                                            BA </label>
                                    </div>
                                                                        <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur6" value="AB">
                                            AB </label>
                                    </div>
                                                                        <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur7" value="SZ">
                                            SZ </label>
                                    </div>
                                                                        <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur8" value="AP">
                                            AP </label>
                                    </div>
                                                                        <div class="funkyradio-default">
                                        <label class="btn btn-default  btn-sm btn-block">
                                            <input type="radio" name="xfur" id="mod_fur9" value="AD">
                                            AD </label>
                                    </div>
                                        

                            </div>
                        </div>

                    </div>                      
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" id="actualizare_fur">Actualizare date</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
        <!-- Modal edit adresa tm-->
    <div class="modal fade" id="mod_adresa" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Magazin</h4>
                </div>
                <form class="form-horizontal" method="post" id="frmeditare_adresa" name="editare_adresa">
                    <div class="modal-body">
                        <div id="rezultat_ajax_adr"></div>
                        <div class="form-group">
                            <div class="col-sm-8">
                                <input type="hidden" class="form-control" name="mod_id_cmd" id="mod_id_cmd">
                               </div>
                        </div>
                        <div class="form-group">
                                                                    <label class="btn btn-succces btn-block">
                                            <input type="radio" name="radioGroup" id="mod_adr0" value="1">
                                            Timisoara </label>
                                                                            <label class="btn btn-succces btn-block">
                                            <input type="radio" name="radioGroup" id="mod_adr1" value="2">
                                            Utvin </label>
                                        
                        </div>
                    </div>                      
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" id="actualizare_adresa">Actualizare date</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
                            <form class="form-horizontal" role="form" id="date_cotizacion">
                            <div class="form-group row-fluid">

                                <div class="col-sm-2">
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <a href="#" >
                                                <span class="glyphicon glyphicon-chevron-left" onclick='obtine_data(-1)'></span></a>
                                        </span>                                         
                                        <input class="form-control" id="date" name="date" placeholder="DD/MM/YYYY" type="text" value="12/03/2025" onchange='load(1);' readonly/>
                                        <span class="input-group-addon">
                                            <a href="#" >
                                                <span class="glyphicon glyphicon-chevron-right" onclick='obtine_data(1)'></span></a>
                                        </span>                                        
                                    </div>
                                </div>
                                <label for="q" class="col-md-1 control-label">Cauta </label>
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="q" placeholder="Nume, telefon, marca, adresa" onkeyup='load(1);'>
                                        <span class="input-group-addon">
                                            <a href="#" >
                                                <span class="glyphicon glyphicon-search" onclick='load(1);'></span></a> </span>

                                    </div>
                                </div>
                                <div class="col-md-3">
                                 <!--   <span id="loader"></span> --> 
                                </div>
                            </div>
                        </form>
                        <div id="rezultat"></div><!-- Date ajax -->
                        <div class='outer_div'></div><!-- Date ajax -->
                    </div>
                </div>	
            </div>
        </div>
@endsection