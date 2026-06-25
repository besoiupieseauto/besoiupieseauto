@extends('layouts.header_common_create')
@section('title', 'Comanda Noua Timisoara| Comenzi')
@section('content')
	<div class="clint">
		<div class="panel panel-info">
			<div class="panel-heading">
				<h4><i class="glyphicon glyphicon-user"></i> Utilizator nou</h4>
			</div>
			<div class="panel-body">
				<!-- User Create Form -->
				<form class="form-horizontal" role="form" id="user_form" method="POST" action="{{ route('utilizatori.store') }}">
					@csrf

					<div class="form-group">
						<label for="username" class="col-md-2 control-label">Username</label>
						<div class="col-md-4">
							<input type="text" class="form-control input-sm" name="username" id="username" placeholder="Username" required>
						</div>

						<label for="email" class="col-md-2 control-label">Email</label>
						<div class="col-md-4">
							<input type="email" class="form-control input-sm" name="email" id="email" placeholder="Email" required>
						</div>
					</div>

					<div class="form-group">
						<label for="nume" class="col-md-2 control-label">Nume</label>
						<div class="col-md-4">
							<input type="text" class="form-control input-sm" name="nume" id="nume" placeholder="Nume complet" required>
						</div>

						<label for="prenume" class="col-md-2 control-label">Prenume</label>
						<div class="col-md-4">
							<input type="text" class="form-control input-sm" name="prenume" id="prenume" placeholder="Prenume">
						</div>
					</div>

					<div class="form-group">
						<label for="telefon" class="col-md-2 control-label">Telefon</label>
						<div class="col-md-4">
							<input type="text" class="form-control input-sm" name="telefon" id="telefon" placeholder="Telefon" required>
						</div>

						<label for="rol" class="col-md-2 control-label">Rol</label>
						<div class="col-md-4">
							<select name="rol" id="rol" class="form-control input-sm" required>
								<option value="">Selectează rol</option>
								<option value="admin">Admin</option>
								<option value="user">User</option>
							</select>
						</div>
					</div>

					<div class="form-group">
						<label for="password" class="col-md-2 control-label">Parola</label>
						<div class="col-md-4">
							<input type="password" class="form-control input-sm" name="password" id="password" placeholder="Parola" required>
						</div>

						<label for="password_confirmation" class="col-md-2 control-label">Confirmă Parola</label>
						<div class="col-md-4">
							<input type="password" class="form-control input-sm" name="password_confirmation" id="password_confirmation" placeholder="Confirmă parola" required>
						</div>
					</div>

					<div class="form-group">
						<label for="active" class="col-md-2 control-label">Activ</label>
						<div class="col-md-4">
							<select name="active" id="active" class="form-control input-sm">
								<option value="1">Da</option>
								<option value="0">Nu</option>
							</select>
						</div>

						<label for="requires_2fa" class="col-md-2 control-label">2FA Necesită?</label>
						<div class="col-md-4">
							<select name="requires_2fa" id="requires_2fa" class="form-control input-sm">
								<option value="0">Nu</option>
								<option value="1">Da</option>
							</select>
						</div>
					</div>
					
					<div class="form-group">
						<label class="col-md-2 control-label">Permisiuni</label>
						<div class="col-md-2">
							<label><input type="checkbox" name="permissions[comenzi_tm]" value="1"> Comenzi TM</label><br>
							<label><input type="checkbox" name="permissions[comenzi_utvin]" value="1"> Comenzi UTVIN</label><br>
							<label><input type="checkbox" name="permissions[comenzi_externe]" value="1"> Comenzi externe</label><br>
							<label><input type="checkbox" name="permissions[produse]" value="1"> Produse</label><br>
							<label><input type="checkbox" name="permissions[clienti]" value="1"> Clienti</label><br>
							<label><input type="checkbox" name="permissions[facturi]" value="1"> Facturi</label><br>
							<label><input type="checkbox" name="permissions[incasari]" value="1">Incasari</label><br>
							<label><input type="checkbox" name="permissions[ultilizatori]" value="1">Utilizatori</label><br>
							<label><input type="checkbox" name="permissions[pieseauto]" value="1">Pieseauto</label><br>
							<label><input type="checkbox" name="permissions[searching]" value="1">Supplier Search</label>
						</div>
					</div>

					<div class="col-md-12" style="margin-top: 20px;">
						<div class="pull-right">
							<button type="submit" class="btn btn-success">
								<span class="glyphicon glyphicon-floppy-disk"></span> Salvează
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
