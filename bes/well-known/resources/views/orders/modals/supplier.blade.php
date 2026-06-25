<style>
    /* Radio Button Styling */
	.radio-container {
		display: flex;
		flex-direction: column;
		align-items: left;
		width: 293px;
		margin-left: -12px;
	}
	.radio-container label {
		display: flex;
		align-items: center;
		padding: 5px;
		padding-left: 101px;
		border: 1px solid #c3bbbb;
		background-color: #e5e5e5;
		cursor: pointer;
		transition: all 0.3s ease;
	}
    .radio-container input[type="radio"] {
        margin-right: 10px; /* दाईं ओर थोड़ी जगह */
    }
    .radio-container label:hover {
        background-color: #f8f9fa;
    }
    .radio-container input[type="radio"]:checked + span {
        font-weight: bold;
    }
    .radio-container input[type="radio"]:checked {
        accent-color: #28a745; /* हरे रंग में चेक मार्क */
    }
    
    label.btn {
    color: black !important;
}

label.btn:hover,
label.btn:focus,
input[type="radio"]:checked + label.btn {
    color: black !important;
}

</style>

<!-- Modal edit furnizor -->
<div class="modal fade" id="mod_furnizor" tabindex="-1" role="dialog" aria-labelledby="furnizorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="furnizorModalLabel">
                    <i class="glyphicon glyphicon-edit"></i> Furnizor piesa
                </h4>
            </div>
            <form class="form-horizontal" method="post" id="frmeditare_furnizor">
                <div class="modal-body"> 
                    <div id="rezultat_ajax_fur"></div>
                    <div class="form-group">
                        <div class="col-sm-8">
                            <input type="hidden" class="form-control" name="order_id" id="mod_id_cmd_fur">
                            <input type="hidden" class="form-control" name="product_id" id="mod_id_prod_fur">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="funkyradio">
                            @php
                                $suppliers = ['ET', 'MA', 'IC', 'AN', 'AT', 'BA', 'AB', 'SZ', 'AP', 'AD', 'Stoc'];
                            @endphp

                            @foreach($suppliers as $index => $supplier)
                                <div class="funkyradio-default">
                                    <label class="btn btn-default btn-sm btn-block">
                                        <input type="radio" name="supplier" id="mod_fur{{ $index }}" value="{{ $supplier }}">
                                        {{ $supplier }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <!--<div class="modal-footer">
                    <button type="submit" class="btn btn-success" id="actualizare_fur">Actualizare date</button>
                </div>-->
            </form>
        </div>
    </div>
</div>