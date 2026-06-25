<!-- Modal produs nou-->
<div class="modal fade" id="produs_nou" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="form-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Produs nou</h4>
            </div>
            <div class="modal-body">
                <div class="form-horizontal">
                    <div id="rezultat_ajax_produs"></div>
                    
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Produs</label>
                        <div class="col-sm-8">
                            <textarea class="form-control" id="denumire_input" placeholder="Denumire produs" required></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">Cod produs</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="cod_input" placeholder="Cod produs" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Pret</label>
                        <div class="col-sm-8">
                            <input type="number" step="any" class="form-control" id="pret_input" placeholder="Pret unitar" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" onclick="saveProduct()">Salveaza</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
