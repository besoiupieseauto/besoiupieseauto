<!-- Product Search Modal -->
<div class="modal fade" id="searchProductModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Cauta produs</h4>
            </div>
            <div class="modal-body">
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="form-control" id="search-product-input" placeholder="Cauta produs">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" id="search-product-btn">
                                    <i class="glyphicon glyphicon-search"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#addProductModal">
                            <i class="glyphicon glyphicon-plus"></i> Produs nou
                        </button>
                    </div>
                </div>

                <div id="search-results" class="mt-3">
                    <table class="table table-bordered table-hover" style="width:100%">
                        <thead>
                            <tr class="warning">
                                <th>Produs</th>
                                <th>Cod Produs</th>
                                <th class="col-xs-1"><span class="pull-right">Furnizor</span></th>
                                <th class="col-xs-1"><span class="pull-right">Disponibilitate</span></th>
                                <th class="col-xs-1"><span class="pull-right">Cant.</span></th>
                                <th class="col-xs-2"><span class="pull-right">Pret</span></th>
                                <th class="text-center">Adauga</th>
                            </tr>
                        </thead>
                        <tbody id="search-results-body">
                            <!-- Search results will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>