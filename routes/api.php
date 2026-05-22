<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\VenteController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\GeolocalisationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UtilisateurController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\DossierController;

// Routes publiques
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Dashboard
    Route::middleware('role:gestionnaire,admin,super_admin')->group(function () {
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/ventes', [DashboardController::class, 'graphVentes']);
        Route::get('/dashboard/demandes-recentes', [DashboardController::class, 'demandesRecentes']);
    });

    // Produits
    Route::middleware('role:gestionnaire,admin,super_admin')->group(function () {
        Route::apiResource('produits', ProduitController::class);
    });

    // Produits - vendeur peut consulter
    Route::middleware('role:vendeur,gestionnaire,admin,super_admin')->group(function () {
        Route::get('/produits-liste', [ProduitController::class, 'index']);
    });

    // Ventes
    Route::middleware('role:vendeur,gestionnaire,admin,super_admin')->group(function () {
        Route::get('/ventes', [VenteController::class, 'index']);
        Route::post('/ventes', [VenteController::class, 'store']);
        Route::get('/ventes/stats', [VenteController::class, 'chiffreAffaires']);
        Route::get('/ventes/chiffre-affaires', [VenteController::class, 'chiffreAffaires']);
        Route::get('/ventes/par-caissiere', [VenteController::class, 'chiffreAffairesParCaissiere']);
        Route::get('/ventes/classement', [VenteController::class, 'classementVendeurs']);
    });

    Route::middleware('role:gestionnaire,admin,super_admin')->group(function () {
        Route::post('/ventes/{id}/valider', [VenteController::class, 'valider']);
        Route::post('/ventes/{id}/annuler', [VenteController::class, 'annuler']);
    });

    // Livraisons
    Route::middleware('role:livreur,gestionnaire,admin,super_admin,coordinateur')->group(function () {
        Route::apiResource('livraisons', LivraisonController::class)->only(['index', 'store', 'show']);
        Route::post('/livraisons/{id}/cloturer', [LivraisonController::class, 'cloturer']);
        Route::post('/livraisons/{id}/accepter', [LivraisonController::class, 'accepter']);
        Route::post('/livraisons/{id}/rejeter', [LivraisonController::class, 'rejeter']);
        Route::patch('/livraisons/{id}/statut', [LivraisonController::class, 'updateStatut']);
    });

    Route::middleware('role:gestionnaire,admin,super_admin')->group(function () {
        Route::post('/livraisons/{id}/valider', [LivraisonController::class, 'valider']);
        Route::post('/livraisons/{id}/assigner', [LivraisonController::class, 'assignerLivreur']);
    });

    // Demandes livreurs
    Route::middleware('role:livreur,gestionnaire,admin,super_admin,coordinateur')->group(function () {
        Route::get('/demandes', [DemandeController::class, 'index']);
        Route::post('/demandes', [DemandeController::class, 'store']);
    });

    Route::middleware('role:gestionnaire,admin,super_admin')->group(function () {
        Route::patch('/demandes/{id}/valider', [DemandeController::class, 'valider']);
        Route::patch('/demandes/{id}/refuser', [DemandeController::class, 'refuser']);
    });

    // Dossiers journaliers
    Route::middleware('role:livreur,gestionnaire,admin,super_admin')->group(function () {
        Route::get('/dossiers', [DossierController::class, 'index']);
        Route::post('/dossiers/{id}/cloturer', [DossierController::class, 'cloturer']);
    });

    // Géolocalisation
    Route::middleware('role:livreur,admin,super_admin')->group(function () {
        Route::post('/position', [GeolocalisationController::class, 'updatePosition']);
    });

    Route::middleware('role:coordinateur,gestionnaire,admin,super_admin')->group(function () {
        Route::get('/livreurs/positions', [GeolocalisationController::class, 'livreurs']);
        Route::get('/livraisons/positions', [GeolocalisationController::class, 'livreurs']);
        Route::post('/livreurs/plus-proche', [GeolocalisationController::class, 'livreurLePlusProche']);
    });

    // Utilisateurs
    Route::middleware('role:admin,super_admin,gestionnaire')->group(function () {
        Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
        Route::post('/utilisateurs', [UtilisateurController::class, 'store']);
        Route::put('/utilisateurs/{id}', [UtilisateurController::class, 'update']);
        Route::delete('/utilisateurs/{id}', [UtilisateurController::class, 'destroy']);
        Route::get('/roles', [UtilisateurController::class, 'roles']);
    });
});