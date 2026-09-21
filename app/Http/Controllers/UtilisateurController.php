<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UtilisateurController extends Controller
{
    // Lister tous les utilisateurs
    public function index()
    {
        $users = User::with('role')->get();
        return response()->json($users);
    }

    // Créer un utilisateur
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role_id'  => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role_id'  => $request->role_id,
        ]);

        return response()->json($user->load('role'), 201);
    }

    // Modifier un utilisateur
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'    => 'sometimes|string',
            'email'   => 'sometimes|email|unique:users,email,' . $id,
            'role_id' => 'sometimes|exists:roles,id',
        ]);

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }

        $user->update($request->except('password'));
        return response()->json($user->load('role'));
    }

    // Supprimer un utilisateur
    public function destroy($id)
    {
        User::findOrFail($id)->delete();
        return response()->json(['message' => 'Utilisateur supprimé']);
    }

    // Liste des rôles
    public function roles()
    {
        return response()->json(Role::all());
    }

    // Route publique (sans auth) — utilisée par le formulaire d'inscription.
    // Exclut super_admin, qui ne doit jamais être auto-sélectionnable à l'inscription.
    public function rolesPublic()
    {
        return response()->json(Role::where('nom', '!=', 'super_admin')->get(['id', 'nom']));
    }
}