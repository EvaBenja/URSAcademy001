<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Connexion
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with('role')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'name'      => $user->name,
                'prenom'    => $user->prenom,
                'nom'       => $user->nom,
                'email'     => $user->email,
                'telephone' => $user->telephone,
                'statut'    => $user->statut,
                'role'      => $user->role->nom,
            ]
        ]);
    }

    // Déconnexion
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté avec succès']);
    }

    // Profil connecté
    public function me(Request $request)
    {
        $user = $request->user()->load('role');
        return response()->json([
            'user' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'prenom'    => $user->prenom,
                'nom'       => $user->nom,
                'email'     => $user->email,
                'telephone' => $user->telephone,
                'statut'    => $user->statut,
                'role'      => $user->role->nom,
            ]
        ]);
    }

    // Inscription
    public function register(Request $request)
    {
        $request->validate([
            'prenom'    => 'required|string',
            'nom'       => 'required|string',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|min:6',
            'telephone' => 'nullable|string',
            'role_id'   => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name'      => $request->prenom . ' ' . $request->nom,
            'prenom'    => $request->prenom,
            'nom'       => $request->nom,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'telephone' => $request->telephone,
            'role_id'   => $request->role_id,
            'statut'    => 'actif',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'prenom'    => $user->prenom,
                'nom'       => $user->nom,
                'email'     => $user->email,
                'telephone' => $user->telephone,
                'statut'    => $user->statut,
                'role'      => $user->load('role')->role->nom,
            ]
        ], 201);
    }
}