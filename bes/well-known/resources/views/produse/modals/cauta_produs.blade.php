<!-- Modal pentru cautare produs -->
<div class="modal fade" id="searchProduct" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="myModalLabel">Cauta produs</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal">
                    <div class="form-group">
                        <div class="col-sm-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="q" placeholder="Cauta produs" onkeyup="loadProducts(1)">
                                <span class="input-group-addon">
                                    <a href="javascript:void(0);">
                                        <span class="glyphicon glyphicon-search" onclick="loadProducts(1);"></span>
                                    </a>
                                </span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#produs_nou">
                            <span class="glyphicon glyphicon-plus"></span> Produs nou
                        </button>
                    </div>
                    <div id="loader" style="position: absolute; text-align: center; top: 55px; width: 100%;"></div>
                    <div class="outer_div">
                        <!-- Product search results will load here -->
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
