<!-- Modal edit status -->
<div class="modal fade" id="mod_status" tabindex="-1" role="dialog" aria-labelledby="statusModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="statusModalLabel"><i class="glyphicon glyphicon-edit"></i> Editare status comanda</h4>
            </div>
            <form id="frmeditare_status">
                <input type="hidden" id="mod_id_cmd" name="order_id">
                <div class="modal-body">
                    <div id="rezultat_ajax_status"></div>
                     
                    <div class="form-group">
                        <p class="text-bold text-underline">Status comanda</p>
                        <div class="status-btn-group" data-toggle="buttons">
                            <label class="btn btn-warning">
                                <input type="radio" id="mod_stare1" name="stare" value="1"> Comandat
                            </label>
                            <label class="btn btn-info">
                                <input type="radio" id="mod_stare2" name="stare" value="2"> Sosit
                            </label>
							<!-- New Cancelled Status -->
							<label class="btn btn-danger">
								<input type="radio" id="mod_stare8" name="stare" value="8"> Anulat
							</label>
							<label class="btn btn-retur">
                                <input type="radio" id="mod_stare5" name="stare" value="5"> Retur
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <p class="text-bold text-underline">Incasare comanda</p>
                        <div class="status-btn-group" data-toggle="buttons">
                            <label class="btn btn-success">
                                <input type="radio" id="mod_stare3" name="stare" value="3"> Cash
                            </label>
                            <label class="btn btn-card">
                                <input type="radio" id="mod_stare6" name="stare" value="6"> Card
                            </label>
                            <label class="btn btn-fd">
                                <input type="radio" id="mod_stare7" name="stare" value="7"> FD
                            </label>
                            <label class="btn btn-danger">
                                <input type="radio" id="mod_stare4" name="stare" value="4"> Avans
                            </label>
							<label class="btn btn-op">
								<input type="radio" id="mod_stare9" name="stare" value="9"> OP
							</label>
							
							<label class="btn btn-fd">
								<input type="radio" id="mod_stare10" name="stare" value="10"> Avans FD
							</label>
							<label class="btn btn-success">
								<input type="radio" id="mod_stare11" name="stare" value="11"> Avans Cash
							</label>
							<label class="btn btn-card">
								<input type="radio" id="mod_stare12" name="stare" value="12"> Avans Card
							</label>
							<label class="btn btn-op">
								<input type="radio" id="mod_stare13" name="stare" value="13"> Avans OP
							</label>
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

<!-- Custom CSS for status buttons -->
<style>
/* Force white text for ALL buttons in all states */
.btn, 
.btn:hover, 
.btn:focus, 
.btn:active, 
.btn.active {
    color: #ffffff !important;
}

/* Custom button classes */
.btn-card {
    background-color: #000000;
    border-color: #000000;
    color: #ffffff !important;
}
.btn-card:hover, .btn-card:focus, .btn-card:active, .btn-card.active {
    background-color: #333333;
    border-color: #333333;
    color: #ffffff !important;
}

.btn-fd {
    background-color: #dc7d0d;
    border-color: #dc7d0d;
    color: #ffffff !important;
}
.btn-fd:hover, .btn-fd:focus, .btn-fd:active, .btn-fd.active {
    background-color: #c56f0c;
    border-color: #c56f0c;
    color: #ffffff !important;
}

.btn-retur {
    background-color: #6545f0;
    border-color: #6545f0;
    color: #ffffff !important;
}
.btn-retur:hover, .btn-retur:focus, .btn-retur:active, .btn-retur.active {
    background-color: #533bd9;
    border-color: #533bd9;
    color: #ffffff !important;
}

.btn-op {
    background-color: #ff9800; /* example color, you can change it */
    border-color: #ff9800;
    color: #ffffff !important;
}

.btn-op:hover,
.btn-op:focus,
.btn-op:active,
.btn-op.active {
    background-color: #e68900; /* slightly darker shade for hover/focus */
    border-color: #e68900;
    color: #ffffff !important;
}

/* Add these rules to ensure all button types maintain white text on hover */
.btn-success, .btn-success:hover, .btn-success:focus, .btn-success:active, .btn-success.active,
.btn-danger, .btn-danger:hover, .btn-danger:focus, .btn-danger:active, .btn-danger.active,
.btn-info, .btn-info:hover, .btn-info:focus, .btn-info:active, .btn-info.active,
.btn-warning, .btn-warning:hover, .btn-warning:focus, .btn-warning:active, .btn-warning.active {
    color: #ffffff !important;
}

/* Extra overrides for button labels */
.status-btn-group label.btn {
    color: #ffffff !important;
}

.status-btn-group label.btn:hover,
.status-btn-group label.btn:focus,
.status-btn-group label.btn:active,
.status-btn-group label.btn.active {
    color: #ffffff !important;
}

/* Status button groups */
.status-btn-group {
    margin-bottom: 10px;
}

.status-btn-group label.btn {
    margin-right: 5px;
    margin-bottom: 5px;
}

/* Text styling */
.text-bold {
    font-weight: bold;
}

.text-underline {
    text-decoration: underline;
}

/* Fix for active state */
.status-btn-group label.btn.active {
    box-shadow: inset 0 3px 5px rgba(0,0,0,.125);
}

/* Override for radio buttons to prevent positioning and clipping */
#frmeditare_status input[type="radio"] {
    position: static !important;
    clip: auto !important;
    width: auto !important;
    height: auto !important;
    margin: 0 4px 0 0 !important;
    visibility: visible !important;
}

/* Additional styling to ensure radio buttons appear normally */
.status-btn-group label.btn input[type="radio"] {
    display: inline-block !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}
</style>

<!-- Additional JavaScript for the modal -->
<script>
$(document).ready(function() {
    // Prevent modal backdrop click from closing modal
    $('#mod_status').data('bs.modal', {backdrop: 'static', keyboard: false});
    
    // Debug events
    $('#mod_status').on('show.bs.modal', function(e) {
        console.log('Status modal is about to show');
    });
    
    $('#mod_status').on('shown.bs.modal', function(e) {
        console.log('Status modal is now visible');
    });
    
    $('#mod_status').on('hide.bs.modal', function(e) {
        console.log('Status modal is about to hide');
    });
    
    $('#mod_status').on('hidden.bs.modal', function(e) {
        console.log('Status modal is now hidden');
        // Reset form when modal is closed
        $('#frmeditare_status')[0].reset();
        $('#rezultat_ajax_status').html('');
    });
    
    // Additional code to ensure text color stays white when buttons are hovered
    $('.btn').on('mouseenter mouseleave focus blur', function() {
        $(this).css('color', '#ffffff !important');
    });
});

// Function to open status modal
function obtineStare(orderId) {
    console.log("Opening status modal for order ID:", orderId);
    
    // Reset form and clear previous results
    $('#frmeditare_status')[0].reset();
    $('#rezultat_ajax_status').html('');
    
    // Set order ID in hidden field
    $('#mod_id_cmd').val(orderId);
    
    // Show the modal with custom options
    $('#mod_status').modal({
        backdrop: 'static',
        keyboard: false,
        show: true
    });
    
    return false;
}
</script>