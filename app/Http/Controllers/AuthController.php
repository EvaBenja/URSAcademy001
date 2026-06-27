<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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
                'role'      => $user->role?->nom,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté avec succès']);
    }

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
                'role'      => $user->role?->nom,
            ]
        ]);
    }

   public function register(Request $request)
{
    // On capture l'erreur de validation proprement pour te l'afficher
    $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
        'prenom'    => 'required|string|max:255',
        'nom'       => 'required|string|max:255',
        'email'     => 'required|email|unique:users,email',
        'password'  => 'required|min:6|confirmed',
        'telephone' => 'required|string',
       'role_id' => 'required|exists:roles,id', // Enlevé le "exists" temporairement pour tester
    ]);

    if ($validator->fails()) {
        // Renvoie le premier message d'erreur précis au frontend
        return response()->json([
            'message' => $validator->errors()->first()
        ], 422);
    }

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

    $user->load('role');
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
            'role'      => $user->role?->nom,
        ]
    ], 201);
}
}
