<!-- SMS Modal -->
<div class="modal fade" id="mod_sms" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="glyphicon glyphicon-phone"></i> Trimite SMS</h4>
      </div>
      <form id="frmeditare_sms"> 
        <div class="modal-body">
          <div id="rezultat_ajax_sms"></div>
          
          <!-- Hidden fields -->
          <input type="hidden" id="mod_id_sms" name="order_id">
          
          <div class="form-group">
            <label for="mod_nume_sms">Nume client</label>
            <input type="text" class="form-control" id="mod_nume_sms" readonly>
          </div>
          
          <div class="form-group">
            <label for="mod_tel_sms">Telefon</label>
            <input type="text" class="form-control" id="mod_tel_sms" name="phone">
          </div>
          
          <div class="form-group">
            <label for="mod_mesaj">Mesaj</label>
            <textarea class="form-control" id="mod_mesaj" name="message" rows="4"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
          <button type="submit" class="btn btn-success">Trimite SMS</button>
        </div>
      </form>
    </div>
  </div>
</div>