<!-- Add New Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-edit"></i> Produs nou</h4>
            </div>
            <div class="modal-body">
                <div id="add-product-alerts"></div>
                <form id="add-product-form" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Produs</label>
                        <div class="col-sm-9">
                            <textarea class="form-control" id="product-name" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Cod produs</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="product-code" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Pret</label>
                        <div class="col-sm-9">
                            <input type="number" step="0.01" class="form-control" id="product-price" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Anulare</button>
                <button type="button" class="btn btn-primary" id="save-product-btn">Salveaza</button>
            </div>
        </div>
    </div>
</div>