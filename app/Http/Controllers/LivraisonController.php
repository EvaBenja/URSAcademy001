<?php
namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\LivraisonProduit;
use App\Models\DossierJournalier;
use App\Models\Produit;
use App\Models\User;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
class LivraisonController extends Controller
{
    public function index(Request $request)
    {
        $hasProduits = \Illuminate\Support\Facades\Schema::hasTable('livraison_produits');
        $hasVente    = \Illuminate\Support\Facades\Schema::hasColumn('livraisons','vente_id');
        $with = ['livreur','gestionnaire'];
        if ($hasProduits) $with[] = 'produits.produit';
        if ($hasVente)    $with[] = 'vente.caissiere';
        if ($hasVente)    $with[] = 'vente.items.produit';

        $user    = $request->user();
        $roleNom = $user->role?->nom ?? '';

        $query = Livraison::with($with)->orderByDesc('created_at');

        if ($roleNom === 'livreur') {
            // Livreur voit :
            // 1. Les courses disponibles (sans livreur assigné)
            // 2. Ses propres courses (livreur_id = lui)
            $query->where(function($q) use ($user) {
                $q->where(function($sub) {
                    // Cours disponibles : pas de livreur assigné ET statut en_attente
                    $sub->whereNull('livreur_id')
                        ->whereIn('statut', ['en_attente', 'validee']);
                })->orWhere('livreur_id', $user->id); // ses propres courses
            });
        }

        return response()->json($query->get());
    }

    // Livreur soumet une demande avec plusieurs produits
    public function store(Request $request)
    {
        $request->validate([
            'date_livraison'  => 'required|date',
            'notes'           => 'nullable|string',
            'zone_livraison'  => 'nullable|string',
            'produits'        => 'nullable|array',
            'produits.*.produit_id' => 'required_with:produits|exists:produits,id',
            'produits.*.quantite'   => 'required_with:produits|integer|min:1',
        ]);

        $livraison = Livraison::create([
            'livreur_id'     => $request->user()->id,
            'statut'         => 'en_attente',
            'date_livraison' => $request->date_livraison,
            'notes'          => $request->notes,
            'zone_livraison' => $request->zone_livraison ?? null,
        ]);

        // Associer les produits si fournis
        if ($request->has('produits') && is_array($request->produits)) {
            foreach ($request->produits as $item) {
                LivraisonProduit::create([
                    'livraison_id' => $livraison->id,
                    'produit_id'   => $item['produit_id'],
                    'quantite'     => $item['quantite'],
                ]);
            }
        }

        return response()->json($livraison->load(['livreur','produits.produit']), 201);
    }

    public function show($id)
    {
        $livraison = Livraison::with(['livreur','gestionnaire','dossier','produits.produit'])->findOrFail($id);
        return response()->json($livraison);
    }

    public function valider(Request $request, $id)
    {
        $livraison = Livraison::findOrFail($id);
        if ($livraison->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette livraison ne peut plus être modifiée'], 422);
        }
        $request->validate([
            'montant_carburant' => 'required|numeric|min:0',
            'notes'             => 'nullable|string',
        ]);
        $livraison->update([
            'gestionnaire_id' => $request->user()->id,
            'statut'          => 'validee',
            'notes'           => $request->notes ?? $livraison->notes,
        ]);
        DossierJournalier::create([
            'livreur_id'        => $livraison->livreur_id,
            'livraison_id'      => $livraison->id,
            'montant_carburant' => $request->montant_carburant,
            'statut'            => 'ouvert',
            'date'              => $livraison->date_livraison,
        ]);
        return response()->json($livraison->load(['livreur','gestionnaire','dossier','produits.produit']));
    }

    public function accepter(Request $request, $id)
    {
        $livraison = Livraison::findOrFail($id);

        // Vérifier que la course est encore disponible (pas déjà prise par un autre)
        if ($livraison->statut !== 'en_attente' && $livraison->statut !== 'validee') {
            return response()->json(['message' => 'Cette course a déjà été prise par un autre livreur'], 422);
        }

        // Assigner ce livreur et passer en cours
        $livraison->update([
            'livreur_id' => $request->user()->id,
            'statut'     => 'en_cours',
        ]);

        return response()->json([
            'message'   => 'Course acceptée — vous êtes en route !',
            'livraison' => $livraison->load(['livreur','gestionnaire']),
        ]);
    }

    public function rejeter(Request $request, $id)
    {
        $request->validate([
            'motif'           => 'required|string',
            'motif_categorie' => 'nullable|string',
        ]);
        $livraison = Livraison::findOrFail($id);

        // On garde livreur_id pour que le coordinateur voie qui a rejeté.
        // Le statut 'rejetee' déclenche une alerte chez le coordinateur.
        $updateData = [
            'statut'      => 'rejetee',
            'motif_rejet' => $request->motif,
        ];
        if (Schema::hasColumn('livraisons','motif_rejet_categorie')) {
            $updateData['motif_rejet_categorie'] = $request->motif_categorie;
        }
        $livraison->update($updateData);

        return response()->json([
            'message'   => 'Course rejetée — coordinateur alerté pour réassignation',
            'livraison' => $livraison->load(['livreur','gestionnaire']),
        ]);
    }

    public function assignerLivreur(Request $request, $id)
    {
        $livraison = Livraison::findOrFail($id);
        $hasCoords = Schema::hasColumn('livraisons','client_latitude');

        // Choix manuel : le coordinateur a sélectionné un livreur précis
        if ($request->filled('livreur_id')) {
            $livreur = User::findOrFail($request->livreur_id);
            $livraison->update(['livreur_id' => $livreur->id, 'statut' => 'validee', 'motif_rejet' => null]);
            $nom = trim(($livreur->prenom ?? $livreur->name ?? '') . ' ' . ($livreur->nom ?? ''));
            return response()->json([
                'message'   => "Assigné manuellement à {$nom}",
                'livreur'   => $nom,
                'distance'  => null,
                'livraison' => $livraison->load('livreur'),
            ]);
        }

        // Auto-assignation GPS — exclure le livreur qui a rejeté cette course
        $livreurRejeteId = ($livraison->statut === 'rejetee') ? $livraison->livreur_id : null;

        $query = User::whereHas('role', fn($q) => $q->where('nom','livreur'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');
        if ($livreurRejeteId) $query->where('id', '!=', $livreurRejeteId);
        $livreurs = $query->get();

        $livreur  = null;
        $distance = null;

        if ($hasCoords && $livraison->client_latitude && $livraison->client_longitude && $livreurs->isNotEmpty()) {
            $meilleur = null; $meilleureDistance = null;
            foreach ($livreurs as $candidat) {
                $d = GeocodingService::distanceKm(
                    (float) $livraison->client_latitude, (float) $livraison->client_longitude,
                    (float) $candidat->latitude, (float) $candidat->longitude
                );
                if ($meilleureDistance === null || $d < $meilleureDistance) {
                    $meilleureDistance = $d; $meilleur = $candidat;
                }
            }
            $livreur  = $meilleur;
            $distance = $meilleureDistance;
        }

        if (!$livreur) $livreur = $livreurs->sortByDesc('position_updated_at')->first();
        if (!$livreur) $livreur = User::whereHas('role', fn($q) => $q->where('nom','livreur'))
            ->when($livreurRejeteId, fn($q) => $q->where('id','!=',$livreurRejeteId))
            ->first();

        if (!$livreur) {
            return response()->json(['message' => 'Aucun autre livreur disponible'], 404);
        }

        $livraison->update(['livreur_id' => $livreur->id, 'statut' => 'validee', 'motif_rejet' => null]);
        $nom = trim(($livreur->prenom ?? $livreur->name ?? '') . ' ' . ($livreur->nom ?? ''));
        return response()->json([
            'message'   => 'Livreur assigné avec succès',
            'livreur'   => $nom,
            'distance'  => $distance !== null ? round($distance, 1) . ' km' : null,
            'livraison' => $livraison->load('livreur'),
        ]);
    }

    public function updateStatut(Request $request, $id)
    {
        $request->validate(['statut' => 'required|in:en_attente,validee,en_cours,rejetee,livree_attente_validation,terminee']);
        $livraison = Livraison::findOrFail($id);
        $livraison->update(['statut' => $request->statut]);
        return response()->json($livraison->load(['livreur','gestionnaire']));
    }

    // Étape 1 — LIVREUR : coche les produits livrés/non livrés et clôture sa course.
    // La livraison passe en attente de validation finale du gestionnaire.
    public function cloturer(Request $request, $id)
    {
        $validationRules = [
            'produits_statuts'          => 'nullable|array',
            'produits_statuts.*.statut' => 'required_with:produits_statuts|in:livre,non_livre',
            'notes_cloture'             => 'nullable|string',
            'photo_recu'                => 'nullable|image|max:5120', // 5MB max
        ];
        if (Schema::hasTable('livraison_produits')) {
            $validationRules['produits_statuts.*.id'] = 'required_with:produits_statuts|integer|exists:livraison_produits,id';
        }
        $request->validate($validationRules);

        try {
            $livraison = Livraison::findOrFail($id);

            $updateData = [
                'statut' => 'livree_attente_validation',
                'notes'  => $request->notes_cloture ?? $livraison->notes,
            ];

            // Upload optionnel de la photo du reçu (notamment pour les expéditions)
            if ($request->hasFile('photo_recu') && Schema::hasColumn('livraisons','photo_recu')) {
                $path = $request->file('photo_recu')->store('recus', 'public');
                $updateData['photo_recu'] = $path;
            }

            $livraison->update($updateData);

            if ($request->has('produits_statuts') && Schema::hasTable('livraison_produits') && Schema::hasColumn('livraison_produits','statut')) {
                foreach ($request->produits_statuts as $ps) {
                    if (!empty($ps['id'])) {
                        LivraisonProduit::where('id', $ps['id'])->update(['statut' => $ps['statut']]);
                    }
                }
            }

            // Le dossier reste ouvert jusqu'à la validation finale du gestionnaire
            // (la valeur 'cloture' n'est appliquée qu'après validerCloture())

            $with = Schema::hasTable('livraison_produits') ? ['livreur','gestionnaire','produits.produit'] : ['livreur','gestionnaire'];
            return response()->json([
                'message'   => 'Course clôturée — en attente de validation du gestionnaire',
                'livraison' => $livraison->load($with),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur cloturer() livraison #' . $id . ' : ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la clôture de la course. Détail technique : ' . $e->getMessage(),
            ], 500);
        }
    }

    // Étape 2 — GESTIONNAIRE : valide définitivement la clôture après pointage du livreur.
    public function validerCloture(Request $request, $id)
    {
        $livraison = Livraison::findOrFail($id);
        if ($livraison->statut !== 'livree_attente_validation') {
            return response()->json(['message' => 'Cette course n\'est pas en attente de validation'], 422);
        }

        $livraison->update(['statut' => 'terminee']);
        if ($livraison->dossier) {
            $livraison->dossier->update(['statut' => 'cloture']);
        }

        return response()->json([
            'message'   => 'Clôture validée définitivement',
            'livraison' => $livraison->load(['livreur','gestionnaire']),
        ]);
    }

    // Étape 2 (bis) — GESTIONNAIRE : refuse la clôture avec motif obligatoire (litige, erreur de pointage, etc.)
    public function refuserCloture(Request $request, $id)
    {
        $request->validate(['motif' => 'required|string|min:3']);

        $livraison = Livraison::findOrFail($id);
        if ($livraison->statut !== 'livree_attente_validation') {
            return response()->json(['message' => 'Cette course n\'est pas en attente de validation'], 422);
        }

        $livraison->update([
            'statut'      => 'en_cours', // retour chez le livreur pour correction
            'motif_rejet' => $request->motif,
        ]);

        return response()->json([
            'message'   => 'Clôture refusée — renvoyée au livreur avec motif',
            'livraison' => $livraison->load(['livreur','gestionnaire']),
        ]);
    }

    // Marquer la notification d'assignation comme lue par le livreur
    public function marquerNotifLue(Request $request, $id)
    {
        $livraison = Livraison::findOrFail($id);
        if (Schema::hasColumn('livraisons','notif_livreur_lu')) {
            $livraison->update(['notif_livreur_lu' => true]);
        }
        return response()->json(['message' => 'Notification marquée comme lue']);
    }

    // Vendeur confirme que le livreur est arrivé et lui a remis la marchandise
    public function confirmerRemise(Request $request, $id)
    {
        $livraison = Livraison::findOrFail($id);
        if ($livraison->statut !== 'en_cours') {
            return response()->json(['message' => "La livraison n'est pas en cours"], 422);
        }
        // On note la confirmation vendeur mais le statut reste en_cours
        // (le livreur clôture de son côté avec les produits livrés/non livrés)
        $livraison->update(['notes' => ($livraison->notes ? $livraison->notes.PHP_EOL : '').'[Remise confirmée par le vendeur]']);
        return response()->json(['message' => 'Remise confirmée — le livreur peut finaliser la livraison']);
    }

}
