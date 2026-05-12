<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\VenteController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\GeolocalisationController;

// Routes publiques
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Produits - gestionnaire uniquement
    Route::middleware('role:gestionnaire,admin')->group(function () {
        Route::apiResource('produits', ProduitController::class);
    });

    // Ventes - caissiere et gestionnaire
    Route::middleware('role:caissiere,gestionnaire,admin')->group(function () {
        Route::get('/ventes/chiffre-affaires', [VenteController::class, 'chiffreAffaires']);
        Route::get('/ventes/par-caissiere', [VenteController::class, 'chiffreAffairesParCaissiere']);
        Route::apiResource('ventes', VenteController::class)->only(['index', 'store']);
    });

    // Livraisons - livreur peut créer, gestionnaire valide
    Route::middleware('role:livreur,gestionnaire,admin')->group(function () {
        Route::apiResource('livraisons', LivraisonController::class)->only(['index', 'store', 'show']);
        Route::post('/livraisons/{id}/cloturer', [LivraisonController::class, 'cloturer']);
    });

    Route::middleware('role:gestionnaire,admin')->group(function () {
        Route::post('/livraisons/{id}/valider', [LivraisonController::class, 'valider']);
    });

    // Géolocalisation
    Route::middleware('role:livreur,admin')->group(function () {
        Route::post('/position', [GeolocalisationController::class, 'updatePosition']);
    });

    Route::middleware('role:coordonnateur,gestionnaire,admin')->group(function () {
        Route::get('/livreurs/positions', [GeolocalisationController::class, 'livreurs']);
        Route::post('/livreurs/plus-proche', [GeolocalisationController::class, 'livreurLePlusProche']);
    });
});