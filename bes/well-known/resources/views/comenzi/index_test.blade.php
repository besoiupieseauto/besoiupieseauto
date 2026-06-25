@extends('layouts.header_common')
     
        <div class="jumbotron">        
            <div class="container-fluid">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <div class="btn-group pull-right">
                            <a href="comanda_noua_ext.php" class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Comanda noua</a>
                        </div>
                        <h4><i class="glyphicon glyphicon-search"></i> Comenzi externe</h4>
                    </div>
                    <div class="panel-body">
                            <!-- Modal edit status ext -->
    <div class="modal fade" id="mod_status" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Editare status comanda</h4>
                </div>
                <form class="form-horizontal" method="post" id="frmeditare_status" name="editare_status">
                    <div class="modal-body">
                        <div id="rezultat_ajax_status"></div>
                        <div class="form-group">
                            <div class="col-sm-8">
                                <input type="hidden" class="form-control" name="mod_id_cmd" id="mod_id_cmd" value="25306">
                            </div>
                        </div>
                        <div class="form-group">

                            <div class="funkyradio">
                                <div class="funkyradio-default">
                                    <label class="btn btn-warning">
                                        <input type="radio" name="stare" id="mod_stare1" value="1">
                                        Comandat </label>

                                    <label class="btn btn-info">
                                        <input type="radio" name="stare" id="mod_stare2" value="2">
                                        Sosit </label>

                                    <label class="btn btn-primary">
                                        <input type="radio" name="stare" id="mod_stare3" value="3">
                                        Expediat </label>

                                    <label class="btn btn-success">
                                        <input type="radio" name="stare" id="mod_stare4" value="4">
                                        Achitat </label>

                                    <label class="btn btn-danger">
                                        <input type="radio" name="stare" id="mod_stare5" value="5">
                                        Avans </label>
                                    
                                    <label class="btn btn-success">
                                        <input type="radio" name="stare" id="mod_stare6" value="6">
                                        Retur </label>                                    
                                </div>                                
                            </div>
                        </div>

                    </div>                      
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" id="actualizare_date">Actualizare date</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
        <!-- Modal edit culoare-->
    <div class="modal fade" id="mod_culoare" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Editare status piesa</h4>
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

                            <div class="funkyradio">
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
                                        &gt;2 zile </label>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Editare total comanda</h4>
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
                                <input type="text" class="form-control" id="mod_total_cmd" name="mod_total_cmd" placeholder="Total comanda" readonly="">
                            </div> 
                        </div>
                        <div class="form-group">
                            <label for="transport" class="col-sm-3 control-label">Transport</label>
                            <div class="col-sm-8">
                                <input type="number" step="any" class="form-control" id="mod_total_nou_cmd" name="mod_total_nou_cmd" placeholder="Transport" value="0">
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
        
    <!-- Modal edit awb -->
    <div class="modal fade" id="mod_awb" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> AWB comanda</h4>
                </div>
                <div id="rezultat_ajax_awb"></div>
                <form class="form-horizontal" method="post" id="frmeditare_awb" name="editare_awb">
                    <input type="hidden" class="form-control" name="mod_id_awb" id="mod_id_awb">
                    <div class="modal-body">
                        <div class="form-group">
                            <div class="col-xs-12">
                                <div class="row mb-3">
                                    <label for="mod_awb_cmd" class="col-xs-2 col-form-label">Nr. AWB</label>
                                    <div class="col-sm-10">
                                        <input type="text" class="form-control" id="mod_awb_cmd" name="mod_awb_cmd" placeholder="AWB comanda">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="flexCheckChecked" name="flexCheckChecked">
                                    <label for="flexCheckChecked">Fan Courier</label>
                                    <select class="form-select form-select-sm" id="contfan" name="contfan" aria-label="Cont Fan">
                                        <option value="Utvin" selected="">Utvin</option>
                                        <option value="Timisoara">Timisoara</option>
                                        <option value="Test">Test</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="flexCheckSame" name="flexCheckSame">
                                    <label for="flexCheckSame">Sameday</label>

                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <label for="tipserviciu">Tip serviciu Fan</label>
                                    <div id="serviciufan">
                                        <select class="custom-select col-md-10" id="tipserviciu" name="tipserviciu" aria-label="Tip serviciu">
                                            <option value="Cont Colector" selected="">Cont Colector</option>
                                            <option value="Standard">Standard</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">

                                    <div id="rezultat_serviciu"></div>

                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-md-6">
                                <input type="hidden" class="form-control" name="tel_awb_cmd" id="tel_awb_cmd">
                                <label for="comp_awb_cmd" class="form-label">Destinatar</label>
                                <input type="text" class="form-control" id="comp_awb_cmd" name="comp_awb_cmd" placeholder="Destinatar AWB">
                            </div>
                            <div class="col-md-6">
                                <label for="nume_awb_cmd" class="form-label">Persoana contact</label>
                                <input type="text" class="form-control" id="nume_awb_cmd" name="nume_awb_cmd" placeholder="Persoana Contact">
                            </div> 
                        </div>
                        <div class="form-group">
                            <div class="col-md-6">
                                <input type="hidden" class="form-control" name="cod_awb_cmd" id="cod_awb_cmd">
                                <label for="judet_awb_cmd" class="form-label">Judet</label>
                                <input type="text" class="form-control" id="judet_awb_cmd" name="judet_awb_cmd" placeholder="Destinatar AWB">
                            </div>
                            <div class="col-md-6">
                                <label for="local_awb_cmd" class="form-label">Localitate</label>
                                <input type="text" class="form-control" id="local_awb_cmd" name="local_awb_cmd" placeholder="Persoana Contact">
                            </div> 
                            <div class="col-md-12">
                                <label for="adresa_awb_cmd" class="form-label">Adresa</label>
                                <input type="text" class="form-control" id="adresa_awb_cmd" name="adresa_awb_cmd" placeholder="Destinatar AWB">
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3 row">
                                    <label for="km_awb_cmd" class="col-sm-4 col-form-label">Km. exteriori</label>
                                    <div class="col-xs-4">
                                        <input type="text" class="form-control" id="km_awb_cmd" name="km_awb_cmd" placeholder="Km exteriori" aria-label="Km exteriori" disabled="" readonly="">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-md-6">
                                <label for="optiuni">Optiuni</label>
                                <div class="form-check">
                                    <input form-check-input"="" type="checkbox" name="opt1" id="Optiune1" value="2">
                                    <label class="form-check-label" for="opt1">Deschidere la livrare</label>
                                </div>
                                <div class="form-check">
                                    <input form-check-input"="" type="checkbox" name="opt2" id="Optiune2" value="8">
                                    <label class="form-check-label" for="opt2">Livrare sambata</label>
                                </div>
                                <div class="form-check">
                                    <input form-check-input"="" type="checkbox" name="opt3" id="Optiune3" value="4">
                                    <label class="form-check-label" for="opt2">Livrare din sediul FAN Courier</label>
                                </div>
                                <div class="mb-3 row">
                                    <label for="agentie_awb_cmd" class="col-sm-4 col-form-label">Agentie</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="agentie_awb_cmd" name="agentie_awb_cmd" placeholder="Agentie Fan" aria-label="Agentie Fan">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 row">
                                    <label for="plic_awb_cmd" class="col-sm-4 col-form-label">Nr. plicuri</label>
                                    <div class="col-sm-4">
                                        <input type="text" class="form-control" id="plic_awb_cmd" name="plic_awb_cmd" placeholder="Nr. plicuri" aria-label="Nr. plicuri" value="0">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="colet_awb_cmd" class="col-sm-4 col-form-label">Nr. colete</label>
                                    <div class="col-sm-4">
                                        <input type="text" class="form-control" id="colet_awb_cmd" name="colet_awb_cmd" placeholder="Nr. colete" aria-label="Nr. colete" value="1">
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="greutate_awb_cmd" class="col-sm-4 col-form-label">Greutate</label>
                                    <div class="col-sm-4">
                                        <input type="text" class="form-control" id="greutate_awb_cmd" name="greutate_awb_cmd" placeholder="Greutate" aria-label="Greutate" value="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <label for="plataexpeditie">Plata expeditiei la</label>
                                    <select class="form-select form-select-sm" id="plataexpeditie" name="plataexpeditie" aria-label="Plata expeditiei">
                                        <option value="expeditor" selected="">Expeditor</option>
                                        <option value="destinatar">Destinatar</option>
                                    </select>
                                </div>
                                <div class="mb-3 row">
                                    <label for="ramburs_awb_cmd" class="col-sm-4 col-form-label">Ramburs</label>
                                    <div class="col-sm-4">
                                        <input type="text" class="form-control" id="ramburs_awb_cmd" name="ramburs_awb_cmd" placeholder="Ramburs" aria-label="Ramburs">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="optiuni">Observatii</label>
                                <div class="form-check">
                                    <input form-check-input"="" type="checkbox" name="obs1" id="Obs1" value="Atentie-Fragil" checked="">
                                    <label class="form-check-label" for="opt1">Atentie-Fragil</label>
                                </div>
                                <div class="form-check">
                                    <input form-check-input"="" type="checkbox" name="obs2" id="Obs2" value="Livrare urgenta" checked="">
                                    <label class="form-check-label" for="opt2">Livrare urgenta</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-md-6">
                                <div class="mb-6 row">
                                    <label for="tarif_awb_cmd" class="col-sm-2 col-form-label">Tarif Fan</label>
                                    <div class="col-xs-8">
                                        <input type="text" class="form-control" id="tarif_awb_cmd" name="tarif_awb_cmd" placeholder="Tarif" aria-label="Tarif" disabled="" readonly="">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-6 row">
                                    <label for="tarif_sameawb_cmd" class="col-sm-2 col-form-label">Tarif Same</label>
                                    <div class="col-xs-8">
                                        <input type="text" class="form-control" id="tarif_sameawb_cmd" name="tarif_sameawb_cmd" placeholder="Tarif Sameday" aria-label="Tarif Sameday" disabled="" readonly="">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-xs-12">
                                <div class="mb-4 row">
                                    <label for="restit_awb_cmd" class="col-xs-2 col-form-label">Restituire</label>
                                    <div class="col-sm-10">
                                        <input type="text" class="form-control" id="restit_awb_cmd" name="restit_awb_cmd" placeholder="Restituire" aria-label="Restituire">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" id="actualizare_AWB">Actualizare date</button>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Trimite SMS</h4>
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
                                <input type="text" class="form-control" id="mod_nume_sms" name="mod_nume_sms" placeholder="Nume client" readonly="">
                            </div> 
                        </div>
                        <div class="form-group">
                            <label for="telefon" class="col-sm-3 control-label">Telefon</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="mod_tel_sms" name="mod_tel_sms" placeholder="Telefon">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="mesaj" class="col-sm-3 control-label">Mesaj</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" id="mod_mesaj" name="mod_mesaj" placeholder="Mesaj" required=""></textarea>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Furnizor piesa</h4>
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

                            <div class="funkyradio">
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
        <!-- Modal Client nou -->
    <div class="modal fade" id="client_nou" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Client nou</h4>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal" method="post" id="frmclient_nou" name="nou_client">
                        <input type="hidden" name="mod_id1" id="mod_id1">
                        <div id="rezultat_ajax_client_nou"></div>
                        <div class="form-group">
                            <label for="companie_nou_cl" class="col-sm-3 control-label">Societate</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="companie_nou_cl" name="companie_nou_cl" placeholder="Denumire" aria-label="Nume societate">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="cif_nou_cl" class="col-sm-3 control-label">CUI / CNP</label>
                            <div class="row-sm-12">
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <input type="text" class="form-control input-sm" id="cif_nou_cl" name="cif_nou_cl" placeholder="Cui/CNP" aria-label="Cui/CNP">
                                        <span class="input-group-addon">
                                            <a href="#">
                                                 <span class="glyphicon glyphicon-search" id="cauta_anaf"></span></a> 
                                        </span>
                                     </div>
                                </div>
                                <label for="regcom" class="col-sm-1 control-label">J</label>
                                <div class="col-sm-3">
                                    <input type="text" class="form-control" id="regcom" name="regcom" placeholder="Reg.Com" aria-label="Reg.Com">
                                </div>
                            </div>
                        </div>  
                        <div class="form-group">
                            <label for="cont_banca" class="col-sm-3 control-label">Cont bancar</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="cont_banca" name="cont_banca" placeholder="Cont bancar">
                            </div>
                        </div>   
                        <div class="form-group">
                            <label for="nume_banca" class="col-sm-3 control-label">Banca</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="nume_banca" name="nume_banca" placeholder="Banca">
                            </div>
                        </div>                         
                        <div class="form-group">
                            <label for="nume_nou_cl" class="col-sm-3 control-label">Nume / Contact</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="nume_nou_cl" name="nume_nou_cl" placeholder="Denumire client" required="">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="telefon_nou" class="col-sm-3 control-label">Telefon</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="telefon_nou" name="telefon_nou" placeholder="Telefon">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="judet_nou_cl" class="col-sm-3 control-label">Adresa</label>
                            <div class="row-sm-8">
                                <div class="col-sm-3">
                                    <select name="judet_nou_cl" class="form-control" id="judet_nou_cl" aria-label="Judet" placeholder="Judet" required="">
                                        <option value="">-- Judet --</option>
                                                                                    <option value="Alba">Alba</option>
                                                                                    <option value="Arad">Arad</option>
                                                                                    <option value="Arges">Arges</option>
                                                                                    <option value="Bacau">Bacau</option>
                                                                                    <option value="Bihor">Bihor</option>
                                                                                    <option value="Bistrita-Nasaud">Bistrita-Nasaud</option>
                                                                                    <option value="Botosani">Botosani</option>
                                                                                    <option value="Braila">Braila</option>
                                                                                    <option value="Brasov">Brasov</option>
                                                                                    <option value="Bucuresti">Bucuresti</option>
                                                                                    <option value="Buzau">Buzau</option>
                                                                                    <option value="Calarasi">Calarasi</option>
                                                                                    <option value="Caras-Severin">Caras-Severin</option>
                                                                                    <option value="Cluj">Cluj</option>
                                                                                    <option value="Constanta">Constanta</option>
                                                                                    <option value="Covasna">Covasna</option>
                                                                                    <option value="Dambovita">Dambovita</option>
                                                                                    <option value="Dolj">Dolj</option>
                                                                                    <option value="Galati">Galati</option>
                                                                                    <option value="Giurgiu">Giurgiu</option>
                                                                                    <option value="Gorj">Gorj</option>
                                                                                    <option value="Harghita">Harghita</option>
                                                                                    <option value="Hunedoara">Hunedoara</option>
                                                                                    <option value="Ialomita">Ialomita</option>
                                                                                    <option value="Iasi">Iasi</option>
                                                                                    <option value="Ilfov">Ilfov</option>
                                                                                    <option value="Maramures">Maramures</option>
                                                                                    <option value="Mehedinti">Mehedinti</option>
                                                                                    <option value="Mures">Mures</option>
                                                                                    <option value="Neamt">Neamt</option>
                                                                                    <option value="Olt">Olt</option>
                                                                                    <option value="Prahova">Prahova</option>
                                                                                    <option value="Salaj">Salaj</option>
                                                                                    <option value="Satu" mare="">Satu Mare</option>
                                                                                    <option value="Sibiu">Sibiu</option>
                                                                                    <option value="Suceava">Suceava</option>
                                                                                    <option value="Teleorman">Teleorman</option>
                                                                                    <option value="Timis">Timis</option>
                                                                                    <option value="Tulcea">Tulcea</option>
                                                                                    <option value="Valcea">Valcea</option>
                                                                                    <option value="Vaslui">Vaslui</option>
                                                                                    <option value="Vrancea">Vrancea</option>
                                                                            </select>
                                </div>
                                <div class="col-sm-5">
                                    <select id="localitate_nou_cl" name="localitate_nou_cl" class="form-control input-value" aria-label="Localitate" required="">
                                        <option value="">Localitate</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-3">
                            </div>
                            <div class="col-sm-8">
                                <textarea class="form-control" id="adresa_nou" name="adresa_nou" placeholder="Str., nr., ..." required=""></textarea>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="marca_nou" class="col-sm-3 control-label">Marca masina</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="marca_masina" name="marca_masina" placeholder="Marca masina">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="sasiu_nou" class="col-sm-3 control-label">Serie sasiu</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="sasiu_masina" name="sasiu_masina" placeholder="Serie Sasiu">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="nrmat_nou" class="col-sm-3 control-label">Nr. inmatriculare</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="nrmat_masina" name="nrmat_masina" placeholder="Nr. inmatriculare">
                            </div>
                        </div>                           

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success" id="cauta_date">Salvare</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>  

                            
                        <form class="form-horizontal" role="form" id="date_cotizacion">
                            <div class="form-group row-fluid">

                                <div class="col-sm-2">
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <a href="#">
                                            <span class="glyphicon glyphicon-chevron-left" onclick="obtine_data(-1)"></span></a>
                                        </span>                                         
                                        <input class="form-control" id="date" name="date" placeholder="DD/MM/YYYY" type="text" value="28/03/2025" onchange="load(1);" readonly="">
                                        <span class="input-group-addon">
                                            <a href="#">
                                            <span class="glyphicon glyphicon-chevron-right" onclick="obtine_data(1)"></span></a>
                                        </span>                                        
                                    </div>
                                </div>
                                <label for="q" class="col-md-1 control-label">Cauta </label>
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="q" placeholder="Nume, telefon, marca, adresa, awb" onkeyup="load(1);">
                                        <span class="input-group-addon">
                                            <a href="#">
                                                <span class="glyphicon glyphicon-search" onclick="load(1);"></span></a></span><a href="#">
                                        </a>            
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <span id="loader"></span>  
                                </div>
                            </div>
                        </form>
                        <div id="rezultat"></div><!-- Date ajax -->
                        <div class="outer_div">        <div class="table-responsive">
            <table class="table table-light" style="width:100%">
                <tbody><tr class="info">
                    <th class="text-center">Data</th>
                    <th class="text-center">Client</th>
                    <th class="text-center">Telefon</th>
                    <th class="text-center">Marca</th>                    
                    <th class="text-center">Adresa</th>
                    <th class="text-center">Produs</th>                      
                    <th class="text-center">Cod</th>
                    <th class="text-center">Furnizor</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-center">Pret</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">AWB</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actiune</th>

                </tr>
                                    <input type="hidden" value="25309" id="id_cmd25309">
                    <input type="hidden" value="25309" id="id_prod25309">
                    <input type="hidden" value="1" id="stare_cmd25309">
                    <input type="hidden" value="Comandat" id="stare_text_cmd25309">
                    <input type="hidden" value="btn-warning" id="label_cmd25309">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25309">
                    <input type="hidden" value="420" id="total_cmd25309">
                    <input type="hidden" value="7000050321513" id="awb_cmd25309">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25309">
                    <input type="hidden" value="Harghita" id="judet_cmd25309">
                    <input type="hidden" value="Toplita" id="local_cmd25309">
                    <input type="hidden" value="2767" id="cod_cmd25309">
                    <input type="hidden" value="0" id="km_cmd25309">
                    <input type="hidden" value="Toplita" id="agentie_cmd25309">
                    <input type="hidden" value="str.1decembrie1918 nr.21" id="adresa_cmd25309">
                    <input type="hidden" value="PUI FLAVIU" id="nume_sms25309">
                    <input type="hidden" value="" id="nume_comp25309">
                    <input type="hidden" value="0755181289" id="telefon_cmd25309">
                    <input type="hidden" value="" id="mesaj_sms25309">
                    <input type="hidden" value="0" id="tarif_cmd25309">
                    <tr> 
                        <td rowspan="5" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="5" class="vert-align-center" bgcolor="#ffffff">PUI FLAVIU</td>
                        <td rowspan="5" class="vert-align-center" bgcolor="#ffffff">0755181289</td>
                        <td rowspan="5" class="vert-align-center" bgcolor="#ffffff">-</td>                        
                        <td rowspan="5" class="vert-align-center" bgcolor="#ffffff">Toplita</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">SEGMENT REPARATIE</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25309', '64390');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">6504-04-3547332K</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25309', '64390', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">150.00</td>
                        <td rowspan="5" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25309');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 420.00</font></b></a></td>
                        <td rowspan="5" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=7000050321513&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050321513</font></b></a></td>
                                            <td rowspan="5" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-warning" title="Stare" onclick="obtine_stare('25309');" data-toggle="modal" data-target="#mod_status">
            Comandat</a>
                    </td>
                    <td rowspan="5" class="vert-align-center" bgcolor="#ffffff">
                          
                            <a href="editare_comanda_ext.php?id_comanda=25309" class="btn btn-default" title="Editare"><i class="glyphicon glyphicon-edit"></i></a><br> 
                            <a href="#" class="btn btn-warning" title="Sterge" onclick="sterge('25309')"><i class="glyphicon glyphicon-trash"></i> </a>
             
                                <a href="facturi/print_ff.php?id_factura=16627" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25309">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                            <td class="vert-align-center-fl" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25309', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">-</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25309', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">__</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">30.00</td>
                        </tr>
                                                <input type="hidden" value="FFFFFF" id="cul1_cmd25309">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#ffffff">SEMNALIZARE DR</td>
                            <td class="vert-align-center-fl" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25309', '64389');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">KHA2837 141</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25309', '64389', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">45.00</td>
                        </tr>
                                                <input type="hidden" value="FFFFFF" id="cul1_cmd25309">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#ffffff">SEMNALIZARE ST</td>
                            <td class="vert-align-center-fl" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25309', '64388');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">KHA2837 140</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25309', '64388', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">45.00</td>
                        </tr>
                                                <input type="hidden" value="FFFFFF" id="cul1_cmd25309">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">CUREA TRANSMISIE</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25309', '64387');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">S SR 7PK2035</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25309', '64387', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">__</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">2</td>
                            <td class="vert-align-center" bgcolor="#ffffff">75.00</td>
                        </tr>
                                            <input type="hidden" value="25308" id="id_cmd25308">
                    <input type="hidden" value="25308" id="id_prod25308">
                    <input type="hidden" value="2" id="stare_cmd25308">
                    <input type="hidden" value="Sosit" id="stare_text_cmd25308">
                    <input type="hidden" value="btn-info" id="label_cmd25308">
                    <input type="hidden" value="#9ea5af" id="cul_cmd25308">
                    <input type="hidden" value="285" id="total_cmd25308">
                    <input type="hidden" value="7000050220746" id="awb_cmd25308">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25308">
                    <input type="hidden" value="Bucuresti" id="judet_cmd25308">
                    <input type="hidden" value="Bucuresti" id="local_cmd25308">
                    <input type="hidden" value="7000" id="cod_cmd25308">
                    <input type="hidden" value="0" id="km_cmd25308">
                    <input type="hidden" value="Bucuresti" id="agentie_cmd25308">
                    <input type="hidden" value="Str. LOGOFAT TAUTU NR.2 BL C3, SC 1, AP. 21, SECTOR 3, BUCUREȘTI" id="adresa_cmd25308">
                    <input type="hidden" value="SERBUTA  ALEXANDRU" id="nume_sms25308">
                    <input type="hidden" value="" id="nume_comp25308">
                    <input type="hidden" value="0755777124" id="telefon_cmd25308">
                    <input type="hidden" value="" id="mesaj_sms25308">
                    <input type="hidden" value="0" id="tarif_cmd25308">
                    <tr> 
                        <td rowspan="4" class="vert-align-center" bgcolor="#9ea5af">04/03/2025</td>
                        <td rowspan="4" class="vert-align-center" bgcolor="#9ea5af">SERBUTA  ALEXANDRU</td>
                        <td rowspan="4" class="vert-align-center" bgcolor="#9ea5af">0755777124</td>
                        <td rowspan="4" class="vert-align-center" bgcolor="#9ea5af"></td>                        
                        <td rowspan="4" class="vert-align-center" bgcolor="#9ea5af">Bucuresti</td>

                        <td class="vert-align-center-fl" bgcolor="#9ea5af">VAS EXPANSIUNE</td>
                        <td class="vert-align-center-fl" bgcolor="#9ea5af">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25308', '64139');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">DBW014TT</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#9ea5af">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25308', '64139', 'IC');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">IC</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#9ea5af">1</td>
                        <td class="vert-align-center-fl" bgcolor="#9ea5af">100.00</td>
                        <td rowspan="4" class="vert-align-right" bgcolor="#9ea5af">
                            <a href="#" title="Total" onclick="obtine_total('25308');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 285.00</font></b></a></td>
                        <td rowspan="4" class="vert-align-center" bgcolor="#9ea5af">
                                                        <a href="AwbPrint.php?id_awb=7000050220746&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050220746</font></b></a></td>
                                            <td rowspan="4" class="vert-align-center" bgcolor="#9ea5af">
                        <a href="#" class="btn btn-info" title="Stare" onclick="obtine_stare('25308');" data-toggle="modal" data-target="#mod_status">
            Sosit</a>
                    </td>
                    <td rowspan="4" class="vert-align-center" bgcolor="#9ea5af">
                         
                                <a href="editare_factura.php?id_comanda=25308&amp;tip_comanda=1" class="btn btn-default" title="Factureaza" target="_blank"><i class="glyphicon glyphicon-share-alt"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="#9ea5af" id="cul1_cmd25308">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">FURTUN POMPA</td>
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25308', '64383');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">2150157</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25308', '64383', 'AT');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">AT</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">1</td>
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">100.00</td>
                        </tr>
                                                <input type="hidden" value="#9ea5af" id="cul1_cmd25308">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">SURUB POMPA</td>
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25308', '64384');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">N 10638901</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25308', '64384', 'SZ');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">SZ</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">2</td>
                            <td class="vert-align-center-fl" bgcolor="#9ea5af">30.00</td>
                        </tr>
                                                <input type="hidden" value="#9ea5af" id="cul1_cmd25308">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#9ea5af">SURUB</td>
                            <td class="vert-align-center" bgcolor="#9ea5af">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25308', '64428');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">0902619</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#9ea5af">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25308', '64428', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">__</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#9ea5af">1</td>
                            <td class="vert-align-center" bgcolor="#9ea5af">25.00</td>
                        </tr>
                                            <input type="hidden" value="25307" id="id_cmd25307">
                    <input type="hidden" value="25307" id="id_prod25307">
                    <input type="hidden" value="3" id="stare_cmd25307">
                    <input type="hidden" value="Expediat" id="stare_text_cmd25307">
                    <input type="hidden" value="btn-primary" id="label_cmd25307">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25307">
                    <input type="hidden" value="130" id="total_cmd25307">
                    <input type="hidden" value="7000050319872" id="awb_cmd25307">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25307">
                    <input type="hidden" value="Timis" id="judet_cmd25307">
                    <input type="hidden" value="Lovrin" id="local_cmd25307">
                    <input type="hidden" value="6709" id="cod_cmd25307">
                    <input type="hidden" value="46" id="km_cmd25307">
                    <input type="hidden" value="Dumbravita" id="agentie_cmd25307">
                    <input type="hidden" value="principala nr.761A" id="adresa_cmd25307">
                    <input type="hidden" value="Andreia Luncasu" id="nume_sms25307">
                    <input type="hidden" value="" id="nume_comp25307">
                    <input type="hidden" value="0736396171" id="telefon_cmd25307">
                    <input type="hidden" value="" id="mesaj_sms25307">
                    <input type="hidden" value="0" id="tarif_cmd25307">
                    <tr> 
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">Andreia Luncasu</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">0736396171</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">-</td>                        
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">Lovrin</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25307', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25307', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">30.00</td>
                        <td rowspan="2" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25307');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 130.00</font></b></a></td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=7000050319872&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050319872</font></b></a></td>
                                            <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-primary" title="Stare" onclick="obtine_stare('25307');" data-toggle="modal" data-target="#mod_status">
            Expediat</a>
                    </td>
                    <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                                                            <a href="#" class="btn btn-danger" title="SMS trimis" onclick="obtine_sms('25307');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-earphone"></i></a><br> 
                                     
                                <a href="facturi/print_ff.php?id_factura=16616" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25307">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">SENZOR GALERIE ADMISIE</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25307', '35296');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">215810014500</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25307', '35296', 'AT');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">AT</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center" bgcolor="#ffffff">100.00</td>
                        </tr>
                                            <input type="hidden" value="25306" id="id_cmd25306">
                    <input type="hidden" value="25306" id="id_prod25306">
                    <input type="hidden" value="3" id="stare_cmd25306">
                    <input type="hidden" value="Expediat" id="stare_text_cmd25306">
                    <input type="hidden" value="btn-primary" id="label_cmd25306">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25306">
                    <input type="hidden" value="950" id="total_cmd25306">
                    <input type="hidden" value="7000050198605" id="awb_cmd25306">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25306">
                    <input type="hidden" value="Mures" id="judet_cmd25306">
                    <input type="hidden" value="Targu Mures" id="local_cmd25306">
                    <input type="hidden" value="2700" id="cod_cmd25306">
                    <input type="hidden" value="0" id="km_cmd25306">
                    <input type="hidden" value="Targu Mures" id="agentie_cmd25306">
                    <input type="hidden" value="Episcop Ioan Bob 9" id="adresa_cmd25306">
                    <input type="hidden" value="GHENADIE PESCARENCO" id="nume_sms25306">
                    <input type="hidden" value="" id="nume_comp25306">
                    <input type="hidden" value="0744575893" id="telefon_cmd25306">
                    <input type="hidden" value="" id="mesaj_sms25306">
                    <input type="hidden" value="0" id="tarif_cmd25306">
                    <tr> 
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">GHENADIE PESCARENCO</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">0744575893</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff"></td>                        
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">Targu Mures</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25306', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25306', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">30.00</td>
                        <td rowspan="2" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25306');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 950.00</font></b></a></td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=7000050198605&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050198605</font></b></a></td>
                                            <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-primary" title="Stare" onclick="obtine_stare('25306');" data-toggle="modal" data-target="#mod_status">
            Expediat</a>
                    </td>
                    <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                                                            <a href="#" class="btn btn-danger" title="SMS trimis" onclick="obtine_sms('25306');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-earphone"></i></a><br> 
                                     
                                <a href="facturi/print_ff.php?id_factura=16612" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25306">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">AMORTIZOR FATA</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25306', '64380');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">314 125</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25306', '64380', 'AT');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">AT</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">2</td>
                            <td class="vert-align-center" bgcolor="#ffffff">460.00</td>
                        </tr>
                                            <input type="hidden" value="25305" id="id_cmd25305">
                    <input type="hidden" value="25305" id="id_prod25305">
                    <input type="hidden" value="3" id="stare_cmd25305">
                    <input type="hidden" value="Expediat" id="stare_text_cmd25305">
                    <input type="hidden" value="btn-primary" id="label_cmd25305">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25305">
                    <input type="hidden" value="330" id="total_cmd25305">
                    <input type="hidden" value="7000050317297" id="awb_cmd25305">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25305">
                    <input type="hidden" value="Ilfov" id="judet_cmd25305">
                    <input type="hidden" value="Dragomiresti-Deal" id="local_cmd25305">
                    <input type="hidden" value="7000" id="cod_cmd25305">
                    <input type="hidden" value="0" id="km_cmd25305">
                    <input type="hidden" value="Bucuresti" id="agentie_cmd25305">
                    <input type="hidden" value="Strada Padurii 26, CT Park km13, hala J4," id="adresa_cmd25305">
                    <input type="hidden" value="IONUT CONSTANDACHE" id="nume_sms25305">
                    <input type="hidden" value="" id="nume_comp25305">
                    <input type="hidden" value="0765968650" id="telefon_cmd25305">
                    <input type="hidden" value="" id="mesaj_sms25305">
                    <input type="hidden" value="0" id="tarif_cmd25305">
                    <tr> 
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">IONUT CONSTANDACHE</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">0765968650</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff"></td>                        
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">Dragomiresti-Deal</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25305', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25305', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">30.00</td>
                        <td rowspan="2" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25305');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 330.00</font></b></a></td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=7000050317297&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050317297</font></b></a></td>
                                            <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-primary" title="Stare" onclick="obtine_stare('25305');" data-toggle="modal" data-target="#mod_status">
            Expediat</a>
                    </td>
                    <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                                                            <a href="#" class="btn btn-danger" title="SMS trimis" onclick="obtine_sms('25305');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-earphone"></i></a><br> 
                                     
                                <a href="facturi/print_ff.php?id_factura=16614" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25305">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">ARC FATA</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25305', '64376');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">14875866</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25305', '64376', 'AT');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">AT</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">2</td>
                            <td class="vert-align-center" bgcolor="#ffffff">150.00</td>
                        </tr>
                                            <input type="hidden" value="25304" id="id_cmd25304">
                    <input type="hidden" value="25304" id="id_prod25304">
                    <input type="hidden" value="5" id="stare_cmd25304">
                    <input type="hidden" value="Avans" id="stare_text_cmd25304">
                    <input type="hidden" value="btn-danger" id="label_cmd25304">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25304">
                    <input type="hidden" value="130" id="total_cmd25304">
                    <input type="hidden" value="7000050122449" id="awb_cmd25304">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25304">
                    <input type="hidden" value="Timis" id="judet_cmd25304">
                    <input type="hidden" value="Utvin" id="local_cmd25304">
                    <input type="hidden" value="6660" id="cod_cmd25304">
                    <input type="hidden" value="0" id="km_cmd25304">
                    <input type="hidden" value="Chisoda" id="agentie_cmd25304">
                    <input type="hidden" value="nr 489" id="adresa_cmd25304">
                    <input type="hidden" value="BESOIU FLORIN" id="nume_sms25304">
                    <input type="hidden" value="" id="nume_comp25304">
                    <input type="hidden" value="0733274696" id="telefon_cmd25304">
                    <input type="hidden" value="" id="mesaj_sms25304">
                    <input type="hidden" value="0" id="tarif_cmd25304">
                    <tr> 
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">BESOIU FLORIN</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">0733274696</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">OPEL CORSA F / BZ4X</td>                        
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">Utvin</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25304', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25304', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">30.00</td>
                        <td rowspan="3" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25304');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 130.00</font></b></a></td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=7000050122449&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050122449</font></b></a></td>
                                            <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-danger" title="Stare" onclick="obtine_stare('25304');" data-toggle="modal" data-target="#mod_status">
            Avans</a>
                    </td>
                    <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                            <a href="#" class="btn btn-danger" title="SMS trimis" onclick="obtine_sms('25304');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-earphone"></i></a><br> 
                                     
                                <a href="editare_factura.php?id_comanda=25304&amp;tip_comanda=1" class="btn btn-default" title="Factureaza" target="_blank"><i class="glyphicon glyphicon-share-alt"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25304">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#ffffff">CUREA ACC</td>
                            <td class="vert-align-center-fl" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25304', '64370');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">AVX10X925-OP</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25304', '64370', 'SZ');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">SZ</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">25.00</td>
                        </tr>
                                                <input type="hidden" value="FFFFFF" id="cul1_cmd25304">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">CAP BARA DR</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25304', '64368');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">12162515</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25304', '64368', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center" bgcolor="#ffffff">75.00</td>
                        </tr>
                                            <input type="hidden" value="25303" id="id_cmd25303">
                    <input type="hidden" value="25303" id="id_prod25303">
                    <input type="hidden" value="6" id="stare_cmd25303">
                    <input type="hidden" value="Retur" id="stare_text_cmd25303">
                    <input type="hidden" value="btn-success" id="label_cmd25303">
                    <input type="hidden" value="#6d7177" id="cul_cmd25303">
                    <input type="hidden" value="345" id="total_cmd25303">
                    <input type="hidden" value="7000050119559" id="awb_cmd25303">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25303">
                    <input type="hidden" value="Timis" id="judet_cmd25303">
                    <input type="hidden" value="Utvin" id="local_cmd25303">
                    <input type="hidden" value="6660" id="cod_cmd25303">
                    <input type="hidden" value="0" id="km_cmd25303">
                    <input type="hidden" value="Chisoda" id="agentie_cmd25303">
                    <input type="hidden" value="nr 489" id="adresa_cmd25303">
                    <input type="hidden" value="BESOIU FLORIN" id="nume_sms25303">
                    <input type="hidden" value="" id="nume_comp25303">
                    <input type="hidden" value="0733274696" id="telefon_cmd25303">
                    <input type="hidden" value="" id="mesaj_sms25303">
                    <input type="hidden" value="0" id="tarif_cmd25303">
                    <tr> 
                        <td rowspan="2" class="vert-align-center" bgcolor="#6d7177">04/03/2025</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#6d7177">BESOIU FLORIN</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#6d7177">0733274696</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#6d7177">OPEL CORSA F / BZ4X</td>                        
                        <td rowspan="2" class="vert-align-center" bgcolor="#6d7177">Utvin</td>

                        <td class="vert-align-center-fl" bgcolor="#6d7177">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="#6d7177">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25303', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#6d7177">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25303', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#6d7177">1</td>
                        <td class="vert-align-center-fl" bgcolor="#6d7177">30.00</td>
                        <td rowspan="2" class="vert-align-right" bgcolor="#6d7177">
                            <a href="#" title="Total" onclick="obtine_total('25303');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 345.00</font></b></a></td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#6d7177">
                                                        <a href="AwbPrint.php?id_awb=7000050119559&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050119559</font></b></a></td>
                                            <td rowspan="2" class="vert-align-center" bgcolor="#6d7177">
                        <a href="#" class="btn btn-success" title="Stare" onclick="obtine_stare('25303');" data-toggle="modal" data-target="#mod_status">
            Retur</a>
                    </td>
                    <td rowspan="2" class="vert-align-center" bgcolor="#6d7177">
                         
                                <a href="editare_factura.php?id_comanda=25303&amp;tip_comanda=1" class="btn btn-default" title="Factureaza" target="_blank"><i class="glyphicon glyphicon-share-alt"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="#6d7177" id="cul1_cmd25303">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#6d7177">ARMATURA BARA</td>
                            <td class="vert-align-center" bgcolor="#6d7177">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25303', '64372');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">KH0015 942</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#6d7177">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25303', '64372', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#6d7177">1</td>
                            <td class="vert-align-center" bgcolor="#6d7177">315.00</td>
                        </tr>
                                            <input type="hidden" value="25302" id="id_cmd25302">
                    <input type="hidden" value="25302" id="id_prod25302">
                    <input type="hidden" value="2" id="stare_cmd25302">
                    <input type="hidden" value="Sosit" id="stare_text_cmd25302">
                    <input type="hidden" value="btn-info" id="label_cmd25302">
                    <input type="hidden" value="#9ea5af" id="cul_cmd25302">
                    <input type="hidden" value="560" id="total_cmd25302">
                    <input type="hidden" value="7000050315828" id="awb_cmd25302">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25302">
                    <input type="hidden" value="Valcea" id="judet_cmd25302">
                    <input type="hidden" value="Ramnicu Valcea" id="local_cmd25302">
                    <input type="hidden" value="3800" id="cod_cmd25302">
                    <input type="hidden" value="0" id="km_cmd25302">
                    <input type="hidden" value="Ramnicu Valcea" id="agentie_cmd25302">
                    <input type="hidden" value="Str Marin Sorescu nr 6 Bl A37/3 Sc B ap 4" id="adresa_cmd25302">
                    <input type="hidden" value="ENACHE ADELINA" id="nume_sms25302">
                    <input type="hidden" value="" id="nume_comp25302">
                    <input type="hidden" value="0750667917" id="telefon_cmd25302">
                    <input type="hidden" value="" id="mesaj_sms25302">
                    <input type="hidden" value="0" id="tarif_cmd25302">
                    <tr> 
                        <td rowspan="2" class="vert-align-center" bgcolor="#9ea5af">04/03/2025</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#9ea5af">ENACHE ADELINA</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#9ea5af">0750667917</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#9ea5af"></td>                        
                        <td rowspan="2" class="vert-align-center" bgcolor="#9ea5af">Ramnicu Valcea</td>

                        <td class="vert-align-center-fl" bgcolor="#9ea5af">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="#9ea5af">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25302', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#9ea5af">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25302', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#9ea5af">1</td>
                        <td class="vert-align-center-fl" bgcolor="#9ea5af">30.00</td>
                        <td rowspan="2" class="vert-align-right" bgcolor="#9ea5af">
                            <a href="#" title="Total" onclick="obtine_total('25302');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 560.00</font></b></a></td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#9ea5af">
                                                        <a href="AwbPrint.php?id_awb=7000050315828&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050315828</font></b></a></td>
                                            <td rowspan="2" class="vert-align-center" bgcolor="#9ea5af">
                        <a href="#" class="btn btn-info" title="Stare" onclick="obtine_stare('25302');" data-toggle="modal" data-target="#mod_status">
            Sosit</a>
                    </td>
                    <td rowspan="2" class="vert-align-center" bgcolor="#9ea5af">
                         
                                <a href="facturi/print_ff.php?id_factura=16613" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="#9ea5af" id="cul1_cmd25302">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#9ea5af">STOP DR</td>
                            <td class="vert-align-center" bgcolor="#9ea5af">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25302', '58543');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">11-12245-06-2</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#9ea5af">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25302', '58543', 'AT');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">AT</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#9ea5af">1</td>
                            <td class="vert-align-center" bgcolor="#9ea5af">530.00</td>
                        </tr>
                                            <input type="hidden" value="25301" id="id_cmd25301">
                    <input type="hidden" value="25301" id="id_prod25301">
                    <input type="hidden" value="3" id="stare_cmd25301">
                    <input type="hidden" value="Expediat" id="stare_text_cmd25301">
                    <input type="hidden" value="btn-primary" id="label_cmd25301">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25301">
                    <input type="hidden" value="90" id="total_cmd25301">
                    <input type="hidden" value="7000050317624" id="awb_cmd25301">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25301">
                    <input type="hidden" value="Neamt" id="judet_cmd25301">
                    <input type="hidden" value="Podoleni" id="local_cmd25301">
                    <input type="hidden" value="2800" id="cod_cmd25301">
                    <input type="hidden" value="11" id="km_cmd25301">
                    <input type="hidden" value="Piatra-Neamt" id="agentie_cmd25301">
                    <input type="hidden" value="STR Gării Nr 188" id="adresa_cmd25301">
                    <input type="hidden" value="BULAI IOAN ALEXANDRU" id="nume_sms25301">
                    <input type="hidden" value="" id="nume_comp25301">
                    <input type="hidden" value="0750437460" id="telefon_cmd25301">
                    <input type="hidden" value="" id="mesaj_sms25301">
                    <input type="hidden" value="0" id="tarif_cmd25301">
                    <tr> 
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">BULAI IOAN ALEXANDRU</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">0750437460</td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">-</td>                        
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">Podoleni</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25301', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25301', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">30.00</td>
                        <td rowspan="2" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25301');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 90.00</font></b></a></td>
                        <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=7000050317624&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050317624</font></b></a></td>
                                            <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-primary" title="Stare" onclick="obtine_stare('25301');" data-toggle="modal" data-target="#mod_status">
            Expediat</a>
                    </td>
                    <td rowspan="2" class="vert-align-center" bgcolor="#ffffff">
                                                            <a href="#" class="btn btn-danger" title="SMS trimis" onclick="obtine_sms('25301');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-earphone"></i></a><br> 
                                     
                                <a href="facturi/print_ff.php?id_factura=16615" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25301">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">FILTRU</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25301', '5161');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">S SF OF0018</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25301', '5161', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">4</td>
                            <td class="vert-align-center" bgcolor="#ffffff">15.00</td>
                        </tr>
                                            <input type="hidden" value="25300" id="id_cmd25300">
                    <input type="hidden" value="25300" id="id_prod25300">
                    <input type="hidden" value="6" id="stare_cmd25300">
                    <input type="hidden" value="Retur" id="stare_text_cmd25300">
                    <input type="hidden" value="btn-success" id="label_cmd25300">
                    <input type="hidden" value="#6d7177" id="cul_cmd25300">
                    <input type="hidden" value="360" id="total_cmd25300">
                    <input type="hidden" value="1ONB24347777406" id="awb_cmd25300">
                    <input type="hidden" value="same" id="cont_awb_cmd25300">
                    <input type="hidden" value="Bucuresti" id="judet_cmd25300">
                    <input type="hidden" value="Bucuresti" id="local_cmd25300">
                    <input type="hidden" value="7000" id="cod_cmd25300">
                    <input type="hidden" value="0" id="km_cmd25300">
                    <input type="hidden" value="Bucuresti" id="agentie_cmd25300">
                    <input type="hidden" value="MUNICIPIUL BUCUREŞTI, Drumul lunca ilvei 100" id="adresa_cmd25300">
                    <input type="hidden" value="ALEXANDRU TIRZIU" id="nume_sms25300">
                    <input type="hidden" value="TEHNO URBAN RIDE S.R.L." id="nume_comp25300">
                    <input type="hidden" value="0751783850" id="telefon_cmd25300">
                    <input type="hidden" value="" id="mesaj_sms25300">
                    <input type="hidden" value="0" id="tarif_cmd25300">
                    <tr> 
                        <td rowspan="4" class="vert-align-center" bgcolor="#6d7177">04/03/2025</td>
                        <td rowspan="4" class="vert-align-center" bgcolor="#6d7177">ALEXANDRU TIRZIU/TEHNO URBAN RIDE S.R.L.</td>
                        <td rowspan="4" class="vert-align-center" bgcolor="#6d7177">0751783850</td>
                        <td rowspan="4" class="vert-align-center" bgcolor="#6d7177"></td>                        
                        <td rowspan="4" class="vert-align-center" bgcolor="#6d7177">Bucuresti</td>

                        <td class="vert-align-center-fl" bgcolor="#6d7177">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="#6d7177">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25300', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#6d7177">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25300', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#6d7177">1</td>
                        <td class="vert-align-center-fl" bgcolor="#6d7177">50.00</td>
                        <td rowspan="4" class="vert-align-right" bgcolor="#6d7177">
                            <a href="#" title="Total" onclick="obtine_total('25300');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 360.00</font></b></a></td>
                        <td rowspan="4" class="vert-align-center" bgcolor="#6d7177">
                                                        <a href="AwbPrint.php?id_awb=1ONB24347777406&amp;cont_awb=same" title="AWB" target="_new">
                                <b><font color="#000000"> 1ONB24347777406</font></b></a></td>
                                            <td rowspan="4" class="vert-align-center" bgcolor="#6d7177">
                        <a href="#" class="btn btn-success" title="Stare" onclick="obtine_stare('25300');" data-toggle="modal" data-target="#mod_status">
            Retur</a>
                    </td>
                    <td rowspan="4" class="vert-align-center" bgcolor="#6d7177">
                         
                                <a href="facturi/print_ff.php?id_factura=16608" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="#6d7177" id="cul1_cmd25300">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#6d7177">SUPORT ST</td>
                            <td class="vert-align-center-fl" bgcolor="#6d7177">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25300', '42438');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">KH8169 9313</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#6d7177">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25300', '42438', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#6d7177">1</td>
                            <td class="vert-align-center-fl" bgcolor="#6d7177">20.00</td>
                        </tr>
                                                <input type="hidden" value="#6d7177" id="cul1_cmd25300">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#6d7177">SUPORT DR</td>
                            <td class="vert-align-center-fl" bgcolor="#6d7177">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25300', '42437');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">KH8169 9314</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#6d7177">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25300', '42437', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#6d7177">1</td>
                            <td class="vert-align-center-fl" bgcolor="#6d7177">20.00</td>
                        </tr>
                                                <input type="hidden" value="#6d7177" id="cul1_cmd25300">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#6d7177">BARA FATA</td>
                            <td class="vert-align-center" bgcolor="#6d7177">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25300', '21558');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">KH8167 904</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#6d7177">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25300', '21558', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#6d7177">1</td>
                            <td class="vert-align-center" bgcolor="#6d7177">270.00</td>
                        </tr>
                                            <input type="hidden" value="25299" id="id_cmd25299">
                    <input type="hidden" value="25299" id="id_prod25299">
                    <input type="hidden" value="3" id="stare_cmd25299">
                    <input type="hidden" value="Expediat" id="stare_text_cmd25299">
                    <input type="hidden" value="btn-primary" id="label_cmd25299">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25299">
                    <input type="hidden" value="900" id="total_cmd25299">
                    <input type="hidden" value="7000050550725" id="awb_cmd25299">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25299">
                    <input type="hidden" value="Arges" id="judet_cmd25299">
                    <input type="hidden" value="Stefanesti" id="local_cmd25299">
                    <input type="hidden" value="349" id="cod_cmd25299">
                    <input type="hidden" value="0" id="km_cmd25299">
                    <input type="hidden" value="Pitesti" id="agentie_cmd25299">
                    <input type="hidden" value="Str.Garii Florica nr.8" id="adresa_cmd25299">
                    <input type="hidden" value="ANGHEL MIHAI" id="nume_sms25299">
                    <input type="hidden" value="" id="nume_comp25299">
                    <input type="hidden" value="0770509304" id="telefon_cmd25299">
                    <input type="hidden" value="" id="mesaj_sms25299">
                    <input type="hidden" value="0" id="tarif_cmd25299">
                    <tr> 
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">ANGHEL MIHAI</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">0770509304</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">-</td>                        
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">Stefanesti</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25299', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25299', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">35.00</td>
                        <td rowspan="3" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25299');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 900.00</font></b></a></td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=7000050550725&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050550725</font></b></a></td>
                                            <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-primary" title="Stare" onclick="obtine_stare('25299');" data-toggle="modal" data-target="#mod_status">
            Expediat</a>
                    </td>
                    <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                            <a href="#" class="btn btn-danger" title="SMS trimis" onclick="obtine_sms('25299');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-earphone"></i></a><br> 
                                     
                                <a href="facturi/print_ff.php?id_factura=16628" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25299">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#ffffff">ARMATURA BARA</td>
                            <td class="vert-align-center-fl" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25299', '64372');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">KH0015 942</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25299', '64372', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">315.00</td>
                        </tr>
                                                <input type="hidden" value="FFFFFF" id="cul1_cmd25299">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">FAR ST</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25299', '64371');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">20-11773-06-2</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25299', '64371', 'AT');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">AT</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center" bgcolor="#ffffff">550.00</td>
                        </tr>
                                            <input type="hidden" value="25298" id="id_cmd25298">
                    <input type="hidden" value="25298" id="id_prod25298">
                    <input type="hidden" value="3" id="stare_cmd25298">
                    <input type="hidden" value="Expediat" id="stare_text_cmd25298">
                    <input type="hidden" value="btn-primary" id="label_cmd25298">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25298">
                    <input type="hidden" value="450" id="total_cmd25298">
                    <input type="hidden" value="1ONB24347775871" id="awb_cmd25298">
                    <input type="hidden" value="same" id="cont_awb_cmd25298">
                    <input type="hidden" value="Constanta" id="judet_cmd25298">
                    <input type="hidden" value="Chirnogeni" id="local_cmd25298">
                    <input type="hidden" value="1490" id="cod_cmd25298">
                    <input type="hidden" value="43" id="km_cmd25298">
                    <input type="hidden" value="Mangalia" id="agentie_cmd25298">
                    <input type="hidden" value="STR. RECOLTEI NR. 11" id="adresa_cmd25298">
                    <input type="hidden" value="CHIRITA IULIA" id="nume_sms25298">
                    <input type="hidden" value="" id="nume_comp25298">
                    <input type="hidden" value="0758806441" id="telefon_cmd25298">
                    <input type="hidden" value="" id="mesaj_sms25298">
                    <input type="hidden" value="0" id="tarif_cmd25298">
                    <tr> 
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">CHIRITA IULIA</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">0758806441</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">-</td>                        
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">Chirnogeni</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25298', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25298', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">50.00</td>
                        <td rowspan="3" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25298');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 450.00</font></b></a></td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=1ONB24347775871&amp;cont_awb=same" title="AWB" target="_new">
                                <b><font color="#000000"> 1ONB24347775871</font></b></a></td>
                                            <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-primary" title="Stare" onclick="obtine_stare('25298');" data-toggle="modal" data-target="#mod_status">
            Expediat</a>
                    </td>
                    <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                            <a href="#" class="btn btn-danger" title="SMS trimis" onclick="obtine_sms('25298');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-earphone"></i></a><br> 
                                     
                                <a href="facturi/print_ff.php?id_factura=16607" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25298">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#ffffff">ARIPA ST</td>
                            <td class="vert-align-center-fl" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25298', '8947');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">KH0029 311</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25298', '8947', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">200.00</td>
                        </tr>
                                                <input type="hidden" value="FFFFFF" id="cul1_cmd25298">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">ARIPA DR</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25298', '9317');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">KH0029 312</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25298', '9317', 'ET');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">ET</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center" bgcolor="#ffffff">200.00</td>
                        </tr>
                                            <input type="hidden" value="25297" id="id_cmd25297">
                    <input type="hidden" value="25297" id="id_prod25297">
                    <input type="hidden" value="3" id="stare_cmd25297">
                    <input type="hidden" value="Expediat" id="stare_text_cmd25297">
                    <input type="hidden" value="btn-primary" id="label_cmd25297">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25297">
                    <input type="hidden" value="190" id="total_cmd25297">
                    <input type="hidden" value="7000050134668" id="awb_cmd25297">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25297">
                    <input type="hidden" value="Arad" id="judet_cmd25297">
                    <input type="hidden" value="Julita" id="local_cmd25297">
                    <input type="hidden" value="200" id="cod_cmd25297">
                    <input type="hidden" value="70" id="km_cmd25297">
                    <input type="hidden" value="Arad" id="agentie_cmd25297">
                    <input type="hidden" value="STR PRINCIPALA NR 298" id="adresa_cmd25297">
                    <input type="hidden" value="MARIS VALENTIN" id="nume_sms25297">
                    <input type="hidden" value="" id="nume_comp25297">
                    <input type="hidden" value="0734510679" id="telefon_cmd25297">
                    <input type="hidden" value="" id="mesaj_sms25297">
                    <input type="hidden" value="0" id="tarif_cmd25297">
                    <tr> 
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">MARIS VALENTIN</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">0734510679</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff"></td>                        
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">Julita</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">PIVOT</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25297', '64367');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">12191455</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25297', '64367', 'MA');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">MA</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">85.00</td>
                        <td rowspan="3" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25297');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 190.00</font></b></a></td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=7000050134668&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050134668</font></b></a></td>
                                            <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-primary" title="Stare" onclick="obtine_stare('25297');" data-toggle="modal" data-target="#mod_status">
            Expediat</a>
                    </td>
                    <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                            <a href="#" class="btn btn-danger" title="SMS trimis" onclick="obtine_sms('25297');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-earphone"></i></a><br> 
                                     
                                <a href="facturi/print_ff.php?id_factura=16606" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25297">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                            <td class="vert-align-center-fl" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25297', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">-</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25297', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">__</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">30.00</td>
                        </tr>
                                                <input type="hidden" value="FFFFFF" id="cul1_cmd25297">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">CAP BARA DR</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25297', '64368');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">12162515</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25297', '64368', 'MA');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">MA</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center" bgcolor="#ffffff">75.00</td>
                        </tr>
                                            <input type="hidden" value="25296" id="id_cmd25296">
                    <input type="hidden" value="25296" id="id_prod25296">
                    <input type="hidden" value="3" id="stare_cmd25296">
                    <input type="hidden" value="Expediat" id="stare_text_cmd25296">
                    <input type="hidden" value="btn-primary" id="label_cmd25296">
                    <input type="hidden" value="FFFFFF" id="cul_cmd25296">
                    <input type="hidden" value="520" id="total_cmd25296">
                    <input type="hidden" value="7000050174267" id="awb_cmd25296">
                    <input type="hidden" value="Utvin" id="cont_awb_cmd25296">
                    <input type="hidden" value="Maramures" id="judet_cmd25296">
                    <input type="hidden" value="Rozavlea" id="local_cmd25296">
                    <input type="hidden" value="2580" id="cod_cmd25296">
                    <input type="hidden" value="30" id="km_cmd25296">
                    <input type="hidden" value="Sighetu Marmatiei" id="agentie_cmd25296">
                    <input type="hidden" value="STR PRINCIPALA NR 440" id="adresa_cmd25296">
                    <input type="hidden" value="PAUL FLORIN" id="nume_sms25296">
                    <input type="hidden" value="" id="nume_comp25296">
                    <input type="hidden" value="0756603700" id="telefon_cmd25296">
                    <input type="hidden" value="" id="mesaj_sms25296">
                    <input type="hidden" value="0" id="tarif_cmd25296">
                    <tr> 
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">04/03/2025</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">PAUL FLORIN</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">0756603700</td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff"></td>                        
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">Rozavlea</td>

                        <td class="vert-align-center-fl" bgcolor="#ffffff">CHELTUIELI TRANSPORT</td>
                        <td class="vert-align-center-fl" bgcolor="FFFFFF">
                            <a href="#" title="cod produs" onclick="obtine_culoare('25296', '32066');" data-toggle="modal" data-target="#mod_culoare">
                                <b><font color="#000000">-</font></b></a></td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">
                            <a href="#" class="btn btn-secondary" title="furnizor" onclick="obtine_furnizor('25296', '32066', '__');" data-toggle="modal" data-target="#mod_furnizor">
                                <b><font color="#000000">__</font></b></a></td>                                  
                        <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                        <td class="vert-align-center-fl" bgcolor="#ffffff">30.00</td>
                        <td rowspan="3" class="vert-align-right" bgcolor="#ffffff">
                            <a href="#" title="Total" onclick="obtine_total('25296');" data-toggle="modal" data-target="#mod_total">
                                <b><font color="#000000"> 520.00</font></b></a></td>
                        <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                        <a href="AwbPrint.php?id_awb=7000050174267&amp;cont_awb=Utvin" title="AWB" target="_new">
                                <b><font color="#000000"> 7000050174267</font></b></a></td>
                                            <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                        <a href="#" class="btn btn-primary" title="Stare" onclick="obtine_stare('25296');" data-toggle="modal" data-target="#mod_status">
            Expediat</a>
                    </td>
                    <td rowspan="3" class="vert-align-center" bgcolor="#ffffff">
                                                            <a href="#" class="btn btn-danger" title="SMS trimis" onclick="obtine_sms('25296');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-earphone"></i></a><br> 
                                     
                                <a href="facturi/print_ff.php?id_factura=16610" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a> <br> 
                                                    </td>
                    </tr>
                                            <input type="hidden" value="FFFFFF" id="cul1_cmd25296">
                        <tr> 
                            <td class="vert-align-center-fl" bgcolor="#ffffff">OGLINDA DR</td>
                            <td class="vert-align-center-fl" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25296', '1980');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">A9202994</font></b></a></td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25296', '1980', 'AN');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">AN</font></b></a></td>                                      
                            <td class="vert-align-center-fl" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center-fl" bgcolor="#ffffff">245.00</td>
                        </tr>
                                                <input type="hidden" value="FFFFFF" id="cul1_cmd25296">
                        <tr> 
                            <td class="vert-align-center" bgcolor="#ffffff">OGLINDA ST</td>
                            <td class="vert-align-center" bgcolor="FFFFFF">
                                <a href="#" title="cod produs" onclick="obtine_culoare('25296', '8200');" data-toggle="modal" data-target="#mod_culoare">
                                    <b><font color="#000000">A9201994</font></b></a></td>
                            <td class="vert-align-center" bgcolor="#ffffff">
                                <a href="#" title="furnizor" onclick="obtine_furnizor('25296', '8200', 'AN');" data-toggle="modal" data-target="#mod_furnizor">
                                    <b><font color="#000000">AN</font></b></a></td>                                      
                            <td class="vert-align-center" bgcolor="#ffffff">1</td>
                            <td class="vert-align-center" bgcolor="#ffffff">245.00</td>
                        </tr>
                                        <tr>
                    <td>
                        <div style="font-size: 24px"><!-- pretend an enclosing class has big font size -->
                            <span class="label label-info label-as-badge">Total luna: 11.285</span>
                        </div>
                    </td>                      
                    <td>
                        <div style="font-size: 24px"><!-- pretend an enclosing class has big font size -->
                            <span class="label label-success label-as-badge">Total zi: 4.955</span>
                        </div>
                    </td>

                    <td colspan="13"><span class="pull-right"><ul class="pagination pagination-large"><li class="disabled"><span><a>‹ Prev</a></span></li><li class="active"><a>1</a></li><li class="disabled"><span><a>Next ›</a></span></li></ul></span></td>
                </tr>
            </tbody></table>	  
        </div>
        </div><!-- Date ajax -->
                    </div>	
                </div>	
            </div>
        </div>
        <div class="navbar navbar-inverse navbar-fixed-bottom">
    <div class="container-fluid">
      <span class="navbar-text pull-left">© 2025 - Sistem comenzi.</span>
      <span class="navbar-text pull-right">   
          <a href="bkup_com.php?bkcup=true"><i class="glyphicon glyphicon-save-file"></i> Backup</a>
       </span>
   </div>
</div>
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/bootstrap-theme.min.css">
<link rel="stylesheet" href="css/jquery-ui.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous"></script>
<script type="text/javascript" src="js/jquery-ui.js"></script>
<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
        <script type="text/javascript" src="js/VentanaCentrada.js"></script>
        <script type="text/javascript" src="js/comenzi_ext.js"></script>
        <script type="text/javascript" src="js/edit_status_ext.js"></script>
        <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
        <script type="text/javascript" src="//code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="js/foundation-datepicker.js"></script>         
<script src="js/foundation-datepicker.ro.js"></script>        
<script type="text/javascript">
 
                                $(window).load(function () {

                                    //$('#date').datepicker();
                                    $('#date').fdatepicker({
                                        format: 'dd/mm/yyyy',
                                        language: 'ro'
                                       });
                                    //glDatePicker();


                                });

</script>
    
<veepn-guard-alert><style>@font-face{font-family:FigtreeVF;src:url(chrome-extension://majdfhpaihoncoakbjgbdhglocklcgno/fonts/FigtreeVF.woff2) format("woff2 supports variations"),url(chrome-extension://majdfhpaihoncoakbjgbdhglocklcgno/fonts/FigtreeVF.woff2) format("woff2-variations");font-weight:100 1000;font-display:swap}</style></veepn-guard-alert><veepn-lock-screen><style>@font-face{font-family:FigtreeVF;src:url(chrome-extension://majdfhpaihoncoakbjgbdhglocklcgno/fonts/FigtreeVF.woff2) format("woff2 supports variations"),url(chrome-extension://majdfhpaihoncoakbjgbdhglocklcgno/fonts/FigtreeVF.woff2) format("woff2-variations");font-weight:100 1000;font-display:swap}</style></veepn-lock-screen><div class="datepicker datepicker-dropdown dropdown-menu" style="display: none; top: 161.797px; left: 70px;"><div class="datepicker-days" style="display: block;"><table class=" table-condensed"><thead><tr><th class="prev" style="visibility: visible;"><i class="fa fa-chevron-left fi-arrow-left"></i></th><th colspan="5" class="date-switch">Martie 2025</th><th class="next" style="visibility: visible;"><i class="fa fa-chevron-right fi-arrow-right"></i></th></tr><tr><th class="dow">Lu</th><th class="dow">Ma</th><th class="dow">Mi</th><th class="dow">Jo</th><th class="dow">Vi</th><th class="dow">Sâ</th><th class="dow">Du</th></tr></thead><tbody><tr><td class="day   old">24</td><td class="day   old">25</td><td class="day   old">26</td><td class="day   old">27</td><td class="day   old">28</td><td class="day  ">1</td><td class="day  ">2</td></tr><tr><td class="day  ">3</td><td class="day   active">4</td><td class="day  ">5</td><td class="day  ">6</td><td class="day  ">7</td><td class="day  ">8</td><td class="day  ">9</td></tr><tr><td class="day  ">10</td><td class="day  ">11</td><td class="day  ">12</td><td class="day  ">13</td><td class="day  ">14</td><td class="day  ">15</td><td class="day  ">16</td></tr><tr><td class="day  ">17</td><td class="day  ">18</td><td class="day  ">19</td><td class="day  ">20</td><td class="day  ">21</td><td class="day  ">22</td><td class="day  ">23</td></tr><tr><td class="day  ">24</td><td class="day  ">25</td><td class="day  ">26</td><td class="day  ">27</td><td class="day  ">28</td><td class="day  ">29</td><td class="day  ">30</td></tr><tr><td class="day  ">31</td><td class="day   new">1</td><td class="day   new">2</td><td class="day   new">3</td><td class="day   new">4</td><td class="day   new">5</td><td class="day   new">6</td></tr></tbody><tfoot><tr><th colspan="7" class="today" style="display: none;">Astăzi</th></tr></tfoot></table></div><div class="datepicker-months" style="display: none;"><table class="table-condensed"><thead><tr><th class="prev" style="visibility: visible;"><i class="fa fa-chevron-left fi-arrow-left"></i></th><th colspan="5" class="date-switch">2025</th><th class="next" style="visibility: visible;"><i class="fa fa-chevron-right fi-arrow-right"></i></th></tr></thead><tbody><tr><td colspan="7"><span class="month">Ian</span><span class="month">Feb</span><span class="month active">Mar</span><span class="month">Apr</span><span class="month">Mai</span><span class="month">Iun</span><span class="month">Iul</span><span class="month">Aug</span><span class="month">Sep</span><span class="month">Oct</span><span class="month">Nov</span><span class="month">Dec</span></td></tr></tbody><tfoot><tr><th colspan="7" class="today" style="display: none;">Astăzi</th></tr></tfoot></table></div><div class="datepicker-years" style="display: none;"><table class="table-condensed"><thead><tr><th class="prev" style="visibility: visible;"><i class="fa fa-chevron-left fi-arrow-left"></i></th><th colspan="5" class="date-switch">2020-2029</th><th class="next" style="visibility: visible;"><i class="fa fa-chevron-right fi-arrow-right"></i></th></tr></thead><tbody><tr><td colspan="7"><span class="year old">2019</span><span class="year">2020</span><span class="year">2021</span><span class="year">2022</span><span class="year">2023</span><span class="year">2024</span><span class="year active">2025</span><span class="year">2026</span><span class="year">2027</span><span class="year">2028</span><span class="year">2029</span><span class="year old">2030</span></td></tr></tbody><tfoot><tr><th colspan="7" class="today" style="display: none;">Astăzi</th></tr></tfoot></table></div><a class="button datepicker-close small alert right" style="width: auto; display: none;"><i class="fa fa-remove fa-times fi-x"></i></a></div></body>