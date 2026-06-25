
	<!DOCTYPE html>
<html lang="ro">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no"/>
  <title>Comenzi | Login</title>
	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="{{ asset('public/backend/css/bootstrap.min.css') }}">
  <!-- CSS  -->
   <link href="{{ asset('public/backend/css/login.css') }}" type="text/css" rel="stylesheet" media="screen,projection"/>
</head>
<body>
 <div class="container">
        <div class="card card-container">
            <img id="profile-img" class="profile-img-card" src="{{ asset('public/backend/img/avatar_2x.png') }}" />
            <p id="profile-name" class="profile-name-card"></p>
                <form method="POST" accept-charset="utf-8" action="{{ route('login') }}"  name="loginform" autocomplete="off" role="form" class="form-signin">
                     @csrf
			                <span id="reauth-email" class="reauth-email"></span>
                <input class="form-control" placeholder="User" name="email" type="text" value="" autofocus="" required>
                
                  @error('email')
                            <span class="error">{{ $message }}</span>
                        @enderror
                <input class="form-control" placeholder="Parola" name="password" type="password" value="" autocomplete="off" required>
                  @error('password')
                            <span class="error">{{ $message }}</span>
                        @enderror
                <button type="submit" class="btn btn-lg btn-success btn-block btn-signin" name="login" id="submit">ACCES</button>
            </form><!-- /form -->
            
        </div><!-- /card-container -->
    </div><!-- /container -->
  </body>
</html>

	







