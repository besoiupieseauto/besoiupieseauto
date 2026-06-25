<!-- Modal Structure -->
<div class="modal fade" id="mod_total" tabindex="-1" aria-labelledby="totalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="totalModalLabel">
                    <i class="glyphicon glyphicon-edit"></i> Editare total comanda
                </h4>
            </div>
            <form id="frmeditare_total">
                <div class="modal-body">
                    <div id="rezultat_ajax_total"></div>
                    <input type="hidden" name="order_id" id="mod_id_cmd_total">
                    
                    <div class="mb-3">
                        <label for="mod_total_cmd" class="form-label">Total actual</label>
                        <input type="text" class="form-control" id="mod_total_cmd" name="old_total" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="mod_total_nou_cmd" class="form-label">Transport</label>
                        <input type="number" step="any" class="form-control" id="mod_total_nou_cmd" name="transport" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success" id="actualizare_total">Actualizare date</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Custom CSS for the modal header and close button -->
<style>
.close-btn {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    margin: 0;
    line-height: 1;
    color: #000;
}

.close-btn:hover {
    color: #555;
}

.modal-footer {
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>

<!-- JavaScript for modal functionality -->
<script>
// Function to close the modal
function closeModal() {
    // Try multiple methods to ensure the modal closes properly
    
    // Method 1: Using Bootstrap 5 Modal API
    try {
        const modal = document.getElementById('mod_total');
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
    } catch (e) {
        console.log("Bootstrap 5 method failed, trying alternatives");
    }
    
    // Method 2: Using jQuery for Bootstrap 4
    try {
        $('#mod_total').modal('hide');
    } catch (e) {
        console.log("jQuery method failed, trying alternatives");
    }
    
    // Method 3: Direct DOM manipulation as a fallback
    try {
        const modal = document.getElementById('mod_total');
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.parentNode.removeChild(backdrop);
        }
    } catch (e) {
        console.log("DOM manipulation method failed");
    }
}

// Initialize event listeners when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // For the close button in header
    const closeBtn = document.querySelector('#mod_total .close-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    
    // For the Close button in footer
    const closeButton = document.querySelector('#mod_total .modal-footer .btn-secondary');
    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }
});
</script>