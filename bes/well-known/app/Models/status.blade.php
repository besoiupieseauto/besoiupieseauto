{{-- resources/views/orders/modals/status.blade.php --}}
<div class="modal fade" id="mod_status" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modificare Status</h5>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="currentColor"></rect>
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="currentColor"></rect>
                        </svg>
                    </span>
                </div>
            </div>
            <form method="post" id="update_status" name="update_status">
                <div class="modal-body">
                    <div class="alert alert-danger" role="alert" id="status_error" style="display:none;">
                        <strong>Error!</strong> <span id="error_message"></span>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex flex-column">
                            <input type="hidden" name="mod_id" id="mod_id">
                            <label class="form-label">Status:</label>
                            <select class="form-select" name="status" id="status">
                                <option value="0">Nou</option>
                                <option value="1">In Procesare</option>
                                <option value="2">Finalizat</option>
                                <option value="3">Anulat</option>
                                <!-- Add more status options as needed -->
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Inchide</button>
                    <button type="submit" class="btn btn-primary">Salveaza</button>
                </div>
            </form>
        </div>
    </div>
</div>