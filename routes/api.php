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
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\ComptabiliteController;

// ── Publiques ────────────────────────────────────────────────
Route::post('/login',         [AuthController::class, 'login']);
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::get('/roles-public',   [UtilisateurController::class, 'rolesPublic']);

// ── Toutes les routes protégées par auth:sanctum uniquement ──
// Les contrôleurs gèrent eux-mêmes les autorisations par rôle
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout',     [AuthController::class, 'logout']);
    Route::post('/auth/logout',[AuthController::class, 'logout']);
    Route::get('/me',          [AuthController::class, 'me']);
    Route::get('/auth/me',     [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard/stats',             [DashboardController::class, 'stats']);
    Route::get('/dashboard/ventes',            [DashboardController::class, 'graphVentes']);
    Route::get('/dashboard/demandes-recentes', [DashboardController::class, 'demandesRecentes']);

    // Produits
    Route::get('/produits',         [ProduitController::class, 'index']);
    Route::post('/produits',        [ProduitController::class, 'store']);
    Route::get('/produits/{id}',    [ProduitController::class, 'show']);
    Route::put('/produits/{id}',    [ProduitController::class, 'update']);
    Route::delete('/produits/{id}', [ProduitController::class, 'destroy']);
    Route::get('/produits-liste',   [ProduitController::class, 'index']);

    // Ventes
    Route::get('/ventes',                  [VenteController::class, 'index']);
    Route::post('/ventes',                 [VenteController::class, 'store']);
    Route::get('/ventes/classement',       [VenteController::class, 'classementVendeurs']);
    Route::get('/ventes/stats',            [VenteController::class, 'chiffreAffaires']);
    Route::get('/ventes/chiffre-affaires', [VenteController::class, 'chiffreAffaires']);
    Route::get('/ventes/historique-ca',    [VenteController::class, 'historiqueCa']);
    Route::get('/ventes/par-caissiere',    [VenteController::class, 'chiffreAffairesParCaissiere']);
    Route::post('/ventes/{id}/annuler',    [VenteController::class, 'annuler']);
    Route::put('/ventes/{id}',             [VenteController::class, 'update']);
    Route::delete('/ventes/{id}',          [VenteController::class, 'supprimer']); // super_admin uniquement

    // Demandes livreurs
    Route::get('/demandes',                    [DemandeController::class, 'index']);
    Route::post('/demandes',                   [DemandeController::class, 'store']);
    Route::patch('/demandes/{id}/valider',     [DemandeController::class, 'valider']);
    Route::patch('/demandes/{id}/refuser',     [DemandeController::class, 'refuser']);
    Route::patch('/demandes/{id}/cloturer',    [DemandeController::class, 'cloturer']);

    // Livraisons
    Route::get('/livraisons',                  [LivraisonController::class, 'index']);
    Route::post('/livraisons',                 [LivraisonController::class, 'store']);
    Route::get('/livraisons/{id}',             [LivraisonController::class, 'show']);
    Route::patch('/livraisons/{id}/statut',    [LivraisonController::class, 'updateStatut']);
    Route::post('/livraisons/{id}/accepter',   [LivraisonController::class, 'accepter']);
    Route::post('/livraisons/{id}/rejeter',    [LivraisonController::class, 'rejeter']);
    Route::post('/livraisons/{id}/valider',    [LivraisonController::class, 'valider']);
    Route::post('/livraisons/{id}/assigner',   [LivraisonController::class, 'assignerLivreur']);
    Route::post('/livraisons/{id}/cloturer',   [LivraisonController::class, 'cloturer']);
    Route::post('/livraisons/{id}/valider-cloture', [LivraisonController::class, 'validerCloture']);
    Route::post('/livraisons/{id}/refuser-cloture', [LivraisonController::class, 'refuserCloture']);
    Route::post('/livraisons/{id}/notif-lue',      [LivraisonController::class, 'marquerNotifLue']);
    Route::post('/livraisons/{id}/confirmer-remise', [LivraisonController::class, 'confirmerRemise']);

    // Dossiers journaliers
    Route::get('/dossiers',                    [DossierController::class, 'index']);
    Route::post('/dossiers/{id}/cloturer',     [DossierController::class, 'cloturer']);

    // Géolocalisation
    Route::post('/position',                   [GeolocalisationController::class, 'updatePosition']);
    Route::get('/livreurs/positions',          [GeolocalisationController::class, 'livreurs']);
    Route::post('/livreurs/plus-proche',       [GeolocalisationController::class, 'livreurLePlusProche']);

    // Dépenses
    Route::get('/depenses',                    [DepenseController::class, 'index']);
    Route::post('/depenses',                   [DepenseController::class, 'store']);
    Route::put('/depenses/{id}',               [DepenseController::class, 'update']);
    Route::delete('/depenses/{id}',            [DepenseController::class, 'destroy']);
    Route::get('/depenses/stats',              [DepenseController::class, 'stats']);

    // Comptabilité
    Route::get('/comptabilite/journalier',     [ComptabiliteController::class, 'journalier']);

    // Utilisateurs & Rôles
    Route::get('/utilisateurs',                [UtilisateurController::class, 'index']);
    Route::post('/utilisateurs',               [UtilisateurController::class, 'store']);
    Route::put('/utilisateurs/{id}',           [UtilisateurController::class, 'update']);
    Route::delete('/utilisateurs/{id}',        [UtilisateurController::class, 'destroy']);
    Route::get('/roles',                       [UtilisateurController::class, 'roles']);
});