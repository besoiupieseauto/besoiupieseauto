<!-- Modal edit adresa - FIXED -->
<div class="modal fade" id="mod_adresa" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="glyphicon glyphicon-edit"></i> Magazin
                </h4>
            </div>
            <form id="frmeditare_adresa">
                <div class="modal-body">
                    <div id="rezultat_ajax_adr"></div>
                    <!-- Fix: Changed hidden field ID to be unique -->
                    <input type="hidden" name="order_id" id="mod_id_cmd_adr">
                    
                    <div class="form-group">
                        <div class="radio">
                            <label>
                                <input type="radio" name="location" value="1"> Timisoara
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="location" value="2"> Utvin
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Actualizare date</button>
                </div>
            </form>
        </div>
    </div>
</div>