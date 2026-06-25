<!-- Modal Client nou -->
<div class="modal fade" id="client_nou" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-edit"></i> Client nou</h4>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" id="frmclient_nou" name="nou_client">
                    @csrf <!-- Important: Add CSRF token -->
                    <div id="rezultat_ajax_client_nou"></div>
                    
                    <div class="form-group">
                        <label for="companie_nou_cl" class="col-sm-3 control-label">Societate</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="companie_nou_cl" name="companie_nou_cl" placeholder="Denumire" aria-label="Nume societate">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cif_nou_cl" class="col-sm-3 control-label">CUI / CNP</label>
                        <div class="row-sm-12">
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <input type="text" class="form-control input-sm" id="cif_nou_cl" name="cif_nou_cl" placeholder="Cui/CNP" aria-label="Cui/CNP">
                                    <span class="input-group-addon">
                                        <a href="javascript:void(0);" id="cauta_anaf">
                                            <span class="glyphicon glyphicon-search"></span>
                                        </a>
                                    </span>
                                </div>
                            </div>
                            <label for="regcom" class="col-sm-1 control-label">J</label>
                            <div class="col-sm-3">
                                <input type="text" class="form-control" id="regcom" name="regcom" placeholder="Reg.Com" aria-label="Reg.Com">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cont_banca" class="col-sm-3 control-label">Cont bancar</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="cont_banca" name="cont_banca" placeholder="Cont bancar">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nume_banca" class="col-sm-3 control-label">Banca</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="nume_banca" name="nume_banca" placeholder="Banca">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nume_nou_cl" class="col-sm-3 control-label">Nume / Contact</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="nume_nou_cl" name="nume_nou_cl" placeholder="Denumire client" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefon_nou" class="col-sm-3 control-label">Telefon</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="telefon_nou" name="telefon_nou" placeholder="Telefon">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="judet_nou_cl" class="col-sm-3 control-label">Adresa</label>
                        <div class="row-sm-8">
                            <div class="col-sm-3">
                                <select name="judet_nou_cl" class="form-control" id="judet_nou_cl" aria-label="Judet" required>
                                    <option value="">-- Judet --</option>
                                    @foreach($counties as $county)
                                        <option value="{{ $county->judet }}">{{ $county->judet }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <select id="localitate_nou_cl" name="localitate_nou_cl" class="form-control" required>
                                    <option value="">Localitate</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="adresa_nou" class="col-sm-3 control-label"></label>
                        <div class="col-sm-8" style="margin-top:5px;">
                            <textarea class="form-control" id="adresa_nou" name="adresa_nou" placeholder="Str., nr., ..." required></textarea>
                        </div>
                    </div>
					
					
					<div class="form-group">
						<label for="judet_nou_cl" class="col-sm-3 control-label"></label>
						<div class="form-check mb-3">
							<input class="form-check-input" type="checkbox" value="1" name="billing_same_as_delivery" id="same_as_delivery">
							<label class="form-check-label" for="same_as_delivery">
							  Adresa de livrare si facturare sunt la fel
							</label>
						</div>
					</div>
					
					<div id="billing_section">
						<div class="form-group">
							<label for="judet_facturare" class="col-sm-3 control-label">Adresa livrare</label>
							<div class="row-sm-8">
								<div class="col-sm-3">
									<select name="judet_facturare" class="form-control county-select" id="judet_facturare">
										<option value="">-- Judet --</option>
										@foreach($counties as $county)
											<option value="{{ $county->judet }}">{{ $county->judet }}</option>
										@endforeach
									</select>
								</div>
								<div class="col-sm-5">
									<select id="localitate_facturare" name="localitate_facturare" class="form-control">
										<option value="">Localitate</option>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label for="adresa_facturare" class="col-sm-3 control-label"></label>
							<div class="col-sm-8" style="margin-top:5px;">
								<textarea class="form-control" id="adresa_facturare" name="adresa_facturare" placeholder="Str., nr., ..."></textarea>
							</div>
						</div>
                    </div>

                    
                    <div class="form-group">
                        <label for="marca_masina" class="col-sm-3 control-label">Marca masina</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="marca_masina" name="marca_masina" placeholder="Marca masina">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="sasiu_masina" class="col-sm-3 control-label">Serie sasiu</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="sasiu_masina" name="sasiu_masina" placeholder="Serie Sasiu">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nrmat_masina" class="col-sm-3 control-label">Nr. inmatriculare</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="nrmat_masina" name="nrmat_masina" placeholder="Nr. inmatriculare">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
                        <button type="submit" class="btn btn-success" id="salveaza_client">Salvare</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>