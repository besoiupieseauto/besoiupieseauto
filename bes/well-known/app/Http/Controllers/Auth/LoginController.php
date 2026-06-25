<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoginController extends Controller
{
public function login(Request $request)
{
    // Validate the input
    $request->validate([
        'username' => 'required|string',
        'password' => 'required|string',
    ]);
    
    // Attempt to authenticate the user with username and password
    $user = User::where('username', $request->username)->first();
    if ($user && Auth::attempt(['username' => $request->username, 'password' => $request->password])) {
        // Use direct URL redirection instead of named route
        return redirect('/orders');
    }
    
    // If login fails
    return back()->withErrors(['username' => 'Invalid credentials'])->withInput();
}
}
