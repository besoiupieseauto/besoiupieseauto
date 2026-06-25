<style>
    .modal-title {
        font-size: 16px;
    }
    
    /* Status option styles */
    .status-option {
        position: relative;
        display: block;
        width: 100%;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 4px;
    }
    
    /* Radio button positioning */
    .status-option input[type="radio"] {
        position: absolute;
        top: 50%;
        left: 36%;
        transform: translateY(-50%);
        margin: 0;
    }
    
    /* Text positioning */
    .status-option label {
        width: 100%;
        display: block;
        padding-left:46%;
        margin: 0;
        cursor: pointer;
        font-weight: normal;
    }
    
    /* Color styles */
    .status-azi {
        background-color: #7CFC00; /* Bright green */
    }
    
    .status-maine {
        background-color: #ADD8E6; /* Light blue */
    }
    
    .status-first-zile {
        background-color: #F5A000; /* Orange */
    }
	
    .status-zile {
        background-color: #FF0000; /* Red */
        color: white;
    }
    
   /* Increase specificity for the sosit status */
.table-striped>tbody>tr.status-sosit td,
.table-striped>tbody>tr:nth-of-type(odd).status-sosit td {
    background-color: #9EA5AF !important;
}


.status-cash,
.status-fd,
.status-card,
.status-retur {
    background-color: #6D7177;
    color: black; /* For better readability on dark background */
}

/* Override Bootstrap table striping */
.table-striped>tbody>tr.status-cash td,
.table-striped>tbody>tr:nth-of-type(odd).status-cash td,
.table-striped>tbody>tr.status-fd td,
.table-striped>tbody>tr:nth-of-type(odd).status-fd td,
.table-striped>tbody>tr.status-card td,
.table-striped>tbody>tr:nth-of-type(odd).status-card td,
.table-striped>tbody>tr.status-retur td,
.table-striped>tbody>tr:nth-of-type(odd).status-retur td {
    background-color: #6D7177 !important;
}

/* Or if you're applying the class to the row itself */
.status-sosit {
    background-color: #9EA5AF !important;
}
    
    /* Update button style */
    .update-btn {
        background-color: #5cb85c;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
    }
</style>

<!-- Modal edit culoare -->
<div class="modal fade" id="mod_culoare" tabindex="-1" role="dialog" aria-labelledby="culoareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="culoareModalLabel">
                    <i class="glyphicon glyphicon-edit"></i> Editare status piesa
                </h4>
            </div>
            <form id="frmeditare_culoare">
                <div class="modal-body">
                    <div id="rezultat_ajax_culoare"></div>
                    <input type="hidden" id="mod_id_cmd_culoare" name="order_id">
                    <input type="hidden" id="mod_id_prod_culoare" name="product_id">
                    
                    <div class="status-option status-azi">
                        <input type="radio" name="color" id="mod_cul1" value="7CFC00">
                        <label for="mod_cul1">Azi</label>
                    </div>
                    
                    <div class="status-option status-maine">
                        <input type="radio" name="color" id="mod_cul2" value="ADD8E6">
                        <label for="mod_cul2">Maine</label>
                    </div>

					<div class="status-option status-first-zile">
						<input type="radio" name="color" id="mod_cul5" value="F5A000">
						<label class="mod_cul5">2 zile</label>
					</div>
							
                    <div class="status-option status-zile">
                        <input type="radio" name="color" id="mod_cul3" value="FF0000">
                        <label for="mod_cul3">+3 zile</label>
                    </div>
                    
                    <div class="status-option status-sosit">
                        <input type="radio" name="color" id="mod_cul4" value="FFFFFF">
                        <label for="mod_cul4">Sosit</label>
                    </div>
                </div>
                <!--<div class="modal-footer">
                    <button type="submit" class="update-btn" id="actualizare_culoare">Actualizare date</button>
                </div>-->
            </form>
        </div>
    </div>
</div>