<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

use App\Models\User;
use App\Models\UserPermission;
use Yajra\DataTables\DataTables;

class UtilizatoriController extends Controller
{
    public function index()
    {
        return view('utilizatori.index');
    }
	
    public function getData(Request $request)
    {
        $search = $request->get('search_value');

        // Base query for users
		$users = User::select(
			'Id',
			'username',
			'nume',
			'prenume',
			'email',
			'telefon',
			'rol',
			'active',
			'created_at',
			'last_login'
		)->where('id', '!=', Auth::user()->Id);

        // Optional search
        if ($search) {
			$users->where(function ($query) use ($search) {
				$query->where('username', 'LIKE', "%{$search}%")
					  ->orWhere('nume', 'LIKE', "%{$search}%")
					  ->orWhere('prenume', 'LIKE', "%{$search}%")
					  ->orWhere('email', 'LIKE', "%{$search}%")
					  ->orWhere('telefon', 'LIKE', "%{$search}%");
			});
        }

        return DataTables::of($users)
			->addColumn('nume_complet', function ($user) {
				return trim(($user->nume ?? '') . ' ' . ($user->prenume ?? ''));
			})
			->editColumn('active', function ($user) {
				return $user->active ? '<span class="label label-success">Activ</span>' : '<span class="label label-danger">Inactiv</span>';
			})
			->editColumn('last_login', function ($user) {
				return $user->last_login ? \Carbon\Carbon::parse($user->last_login)->format('d/m/Y H:i') : '-';
			})
			->addColumn('action', function ($user) {
				return '
					<div class="action-buttons">
						<a href="'.route('utilizatori.edit', $user->Id).'" class="btn btn-default btn-sm" title="Editează">
							<i class="glyphicon glyphicon-edit"></i>
						</a>
						<button type="button" class="btn btn-default btn-sm deleteUser" data-id="'.$user->Id.'" title="Șterge">
							<i class="glyphicon glyphicon-trash"></i>
						</button>
					</div>
				';
			})
			->rawColumns(['action', 'active'])
			->make(true);
    }
	
    public function create()
    {
        return view('utilizatori.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'nume' => 'nullable|string|max:255',
            'prenume' => 'nullable|string|max:255',
            'telefon' => 'nullable|string|max:20',
            'rol' => 'nullable|string|max:50',
            'active' => 'boolean',
        ]);
		
		$lastUserId = User::max('user_id') ?? 0;
		$newUserId = $lastUserId + 1;

        $user = User::create([
			'user_id' => $newUserId,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nume' => $request->nume,
            'prenume' => $request->prenume,
            'telefon' => $request->telefon,
            'rol' => $request->rol,
            'magazin_id' => 1,
            'nume_complet' => trim(($request->nume ?? '') . ' ' . ($request->prenume ?? '')),
            'active' => $request->active ?? 1,
        ]);
		
		if ($request->has('permissions')) {
			foreach ($request->permissions as $menu_key => $allowed) {
				UserPermission::updateOrCreate(
					[
						'user_id' => $user->id,
						'menu_key' => $menu_key
					],
					[
						'permission' => $allowed ? 1 : 0
					]
				);
			}
		}

        return redirect()->route('utilizatori.index')
                         ->with('success', 'Utilizator creat cu succes!');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
		$permissions = UserPermission::where('user_id', $id)
                ->pluck('permission', 'menu_key')->toArray();
				
        return view('utilizatori.edit', compact('user', 'permissions'));
    }

	public function update(Request $request, $id)
	{
		$request->validate([
			'username' => 'required|string|max:255|unique:users,username,' . $id . ',Id',
			'email' => 'required|string|email|max:255|unique:users,email,' . $id . ',Id',
			'nume' => 'nullable|string|max:255',
			'prenume' => 'nullable|string|max:255',
			'telefon' => 'nullable|string|max:20',
			'rol' => 'nullable|string|max:50',
			'active' => 'boolean',
		]);

		$data = $request->only([
			'username',
			'email',
			'nume',
			'prenume',
			'telefon',
			'rol',
			'active',
		]);
		
		$data['nume_complet'] = trim(($request->nume ?? '') . ' ' . ($request->prenume ?? ''));
		$data['magazin_id'] = 1;

		if ($request->filled('password')) {
			$data['password'] = Hash::make($request->password);
		}

		// Direct query bypassing primaryKey issues
		User::where('Id', $id)->update($data);
		
		$permissions = $request->permissions ?? [];
		$allPermissionKeys = UserPermission::where('user_id', $id)->pluck('menu_key')->toArray();
		$allPermissionKeys = array_unique(array_merge($allPermissionKeys, array_keys($permissions)));
		
		foreach ($allPermissionKeys as $menu_key) {
			UserPermission::updateOrCreate(
				[
					'user_id' => $id,
					'menu_key' => $menu_key
				],
				[
					'permission' => isset($permissions[$menu_key]) && $permissions[$menu_key] ? 1 : 0
				]
			);
		}

		return redirect()->route('utilizatori.edit', $id)
						 ->with('success', 'Utilizator actualizat cu succes!');
	}
	
	public function destroy($id)
	{
		UserPermission::where('user_id', $id)->delete();
		$user = User::where('Id', $id)->delete();

		return response()->json(['success' => true, 'message' => 'Utilizator șters cu succes!']);
	}
}