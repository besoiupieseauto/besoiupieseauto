@extends('layouts.header_common_create')
@section('title', 'Editează Utilizator | Comenzi')
@section('content')
<div class="clint">
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4><i class="glyphicon glyphicon-user"></i> Editează utilizator</h4>
        </div>
        <div class="panel-body">
            <!-- User Edit Form -->
            <form class="form-horizontal" role="form" id="user_form" 
                  method="POST" 
                  action="{{ route('utilizatori.update', $user->Id) }}">
                @csrf
                @method('PUT')
				

                <div class="form-group">
                    <label for="username" class="col-md-2 control-label">Username</label>
                    <div class="col-md-4">
                        <input type="text" class="form-control input-sm" name="username" id="username" 
                               value="{{ old('username', $user->username) }}" required>
                    </div>

                    <label for="email" class="col-md-2 control-label">Email</label>
                    <div class="col-md-4">
                        <input type="email" class="form-control input-sm" name="email" id="email" 
                               value="{{ old('email', $user->email) }}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nume" class="col-md-2 control-label">Nume</label>
                    <div class="col-md-4">
                        <input type="text" class="form-control input-sm" name="nume" id="nume" 
                               value="{{ old('nume', $user->nume) }}" required>
                    </div>

                    <label for="prenume" class="col-md-2 control-label">Prenume</label>
                    <div class="col-md-4">
                        <input type="text" class="form-control input-sm" name="prenume" id="prenume" 
                               value="{{ old('prenume', $user->prenume) }}">
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefon" class="col-md-2 control-label">Telefon</label>
                    <div class="col-md-4">
                        <input type="text" class="form-control input-sm" name="telefon" id="telefon" 
                               value="{{ old('telefon', $user->telefon) }}" required>
                    </div>

                    <label for="rol" class="col-md-2 control-label">Rol</label>
                    <div class="col-md-4">
                        <select name="rol" id="rol" class="form-control input-sm" required>
                            <option value="">Selectează rol</option>
                            <option value="admin" {{ old('rol', $user->rol) == 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="user" {{ old('rol', $user->rol) == 'user' ? 'selected' : '' }}>User</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="col-md-2 control-label">Parola Nouă</label>
                    <div class="col-md-4">
                        <input type="password" class="form-control input-sm" name="password" id="password" 
                               placeholder="Lasă gol dacă nu vrei să schimbi parola">
                    </div>

                    <label for="password_confirmation" class="col-md-2 control-label">Confirmă Parola</label>
                    <div class="col-md-4">
                        <input type="password" class="form-control input-sm" name="password_confirmation" id="password_confirmation" 
                               placeholder="Confirmă parola nouă">
                    </div>
                </div>

                <div class="form-group">
                    <label for="active" class="col-md-2 control-label">Activ</label>
                    <div class="col-md-4">
                        <select name="active" id="active" class="form-control input-sm">
                            <option value="1" {{ old('active', $user->active) == 1 ? 'selected' : '' }}>Da</option>
                            <option value="0" {{ old('active', $user->active) == 0 ? 'selected' : '' }}>Nu</option>
                        </select>
                    </div>

                    <label for="requires_2fa" class="col-md-2 control-label">2FA Necesită?</label>
                    <div class="col-md-4">
                        <select name="requires_2fa" id="requires_2fa" class="form-control input-sm">
                            <option value="0" {{ old('requires_2fa', $user->requires_2fa) == 0 ? 'selected' : '' }}>Nu</option>
                            <option value="1" {{ old('requires_2fa', $user->requires_2fa) == 1 ? 'selected' : '' }}>Da</option>
                        </select>
                    </div>
                </div>
				
				<div class="form-group">
					<label class="col-md-2 control-label">Permisiuni</label>
					<div class="col-md-2">
						<label><input type="checkbox" name="permissions[comenzi_tm]" value="1" {{ (!empty($permissions['comenzi_tm']) && $permissions['comenzi_tm']) ? 'checked' : '' }}> Comenzi TM</label><br>
						<label><input type="checkbox" name="permissions[comenzi_utvin]" value="1" {{ (!empty($permissions['comenzi_utvin']) && $permissions['comenzi_utvin']) ? 'checked' : '' }}> Comenzi UTVIN</label><br>
						<label><input type="checkbox" name="permissions[comenzi_externe]" value="1" {{ (!empty($permissions['comenzi_externe']) && $permissions['comenzi_externe']) ? 'checked' : '' }}> Comenzi externe</label><br>
						<label><input type="checkbox" name="permissions[produse]" value="1" {{ (!empty($permissions['produse']) && $permissions['produse']) ? 'checked' : '' }}> Produse</label><br>
						<label><input type="checkbox" name="permissions[clienti]" value="1" {{ (!empty($permissions['clienti']) && $permissions['clienti']) ? 'checked' : '' }}> Clienti</label><br>
						<label><input type="checkbox" name="permissions[facturi]" value="1" {{ (!empty($permissions['facturi']) && $permissions['facturi']) ? 'checked' : '' }}> Facturi</label><br>
						<label><input type="checkbox" name="permissions[incasari]" value="1" {{ (!empty($permissions['incasari']) && $permissions['incasari']) ? 'checked' : '' }}> Incasari</label><br>
						<label><input type="checkbox" name="permissions[utilizatori]" value="1" {{ (!empty($permissions['utilizatori']) && $permissions['utilizatori']) ? 'checked' : '' }}> Utilizatori</label><br>
						<label><input type="checkbox" name="permissions[pieseauto]" value="1" {{ (!empty($permissions['pieseauto']) && $permissions['pieseauto']) ? 'checked' : '' }}> Pieseauto</label><br>
						<label><input type="checkbox" name="permissions[searching]" value="1" {{ (!empty($permissions['searching']) && $permissions['searching']) ? 'checked' : '' }}>Supplier Search</label><br>
					</div>
				</div>

                <div class="col-md-12" style="margin-top: 20px;">
                    <div class="pull-right">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-floppy-disk"></span> Actualizează
                        </button>
                        <a href="{{ route('utilizatori.index') }}" class="btn btn-default action-btn">
                            <span class="glyphicon glyphicon-remove"></span> Anulează
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@section('page_scripts')
@endsection
