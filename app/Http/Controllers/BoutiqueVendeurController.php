<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Produit;
use App\Models\Vente;
use App\Models\VenteItem;
use App\Models\Livraison;
use App\Models\LivraisonProduit;
use App\Models\BoutiqueProduit;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BoutiqueVendeurController extends Controller
{
    private const MARQUE_NOTES = 'Commande boutique en ligne';

    // ── Générer ou récupérer le lien boutique du vendeur ──
    public function monLien(Request $request)
    {
        $user = $request->user();

        if (!$user->code_boutique) {
            $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $user->name), 0, 6))
                  . '-' . strtoupper(Str::random(4));
            $user->update(['code_boutique' => $code]);
        }

        return response()->json([
            'code_boutique'        => $user->code_boutique,
            'lien'                 => config('app.url') . '/boutique/' . $user->code_boutique,
            'nom_boutique'         => $user->nom_boutique ?? $user->name,
            'description_boutique' => $user->description_boutique,
            'whatsapp_boutique'    => $user->whatsapp_boutique,
        ]);
    }

    // ── Modifier les infos de sa boutique (seuls les champs envoyés sont modifiés) ──
    public function updateBoutique(Request $request)
    {
        $request->validate([
            'nom_boutique'         => 'nullable|string|max:100',
            'description_boutique' => 'nullable|string|max:2000',
            'whatsapp_boutique'    => 'nullable|string|max:20',
        ]);

        $data = [];
        foreach (['nom_boutique', 'description_boutique', 'whatsapp_boutique'] as $champ) {
            if ($request->exists($champ)) {
                $data[$champ] = $request->input($champ);
            }
        }

        $user = $request->user();
        if ($data) {
            $user->update($data);
        }

        return response()->json([
            'message'           => 'Boutique mise à jour',
            'nom_boutique'      => $user->nom_boutique,
            'whatsapp_boutique' => $user->whatsapp_boutique,
        ]);
    }

    private function vendeurParCode($code): ?User
    {
        return User::where('code_boutique', $code)
            ->whereHas('role', fn($q) => $q->where('nom', 'vendeur'))
            ->first();
    }

    // ── Page publique boutique vendeur (sans authentification) ──
    // N'expose QUE les produits que le vendeur a choisi de mettre dans sa vitrine.
    public function boutiquePub($code)
    {
        $vendeur = $this->vendeurParCode($code);
        if (!$vendeur) {
            return response()->json(['message' => 'Boutique introuvable'], 404);
        }

        $items = BoutiqueProduit::with('produit')
            ->where('vendeur_id', $vendeur->id)
            ->where('actif', true)
            ->whereHas('produit', fn($q) => $q->where('quantite_stock', '>', 0)
                ->where('nom', 'not like', '[Archivé]%'))
            ->orderBy('ordre')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'vendeur' => [
                'nom'               => $vendeur->nom_boutique ?? $vendeur->name,
                'description'       => $vendeur->description_boutique,
                'code_boutique'     => $vendeur->code_boutique,
                'whatsapp_boutique' => $vendeur->whatsapp_boutique,
            ],
            'produits' => $items->map(fn($i) => [
                'id'          => $i->id,
                'produit_id'  => $i->produit_id,
                'titre'       => $i->titre,
                'description' => $i->description,
                'images'      => $i->images ?? [],
                'video'       => $i->video,
                'prix'        => (float) $i->produit->prix_unitaire,
                'dispo_max'   => min(10, (int) $i->produit->quantite_stock),
            ])->values(),
        ]);
    }

    // ── Commander depuis la boutique publique (sans authentification) ──
    public function commander(Request $request, $code)
    {
        // Anti-robots : champ invisible pour un humain. On répond "succès" sans rien créer.
        if ($request->filled('site_web')) {
            return response()->json(['message' => 'Commande passée avec succès !', 'numero' => 0], 201);
        }

        $request->validate([
            'client_nom'         => 'required|string|max:100',
            'client_telephone'   => ['required', 'string', 'regex:/^[0-9+\s().-]{8,20}$/'],
            'client_adresse'     => 'nullable|string|max:255',
            'client_ville'       => 'nullable|string|max:100',
            'items'              => 'required|array|min:1|max:10',
            'items.*.produit_id' => 'required|integer',
            'items.*.quantite'   => 'required|integer|min:1|max:50',
        ], [
            'client_telephone.regex' => 'Numéro de téléphone invalide',
        ]);

        $vendeur = $this->vendeurParCode($code);
        if (!$vendeur) {
            return response()->json(['message' => 'Boutique introuvable'], 404);
        }

        // Regrouper les doublons
        $demande = [];
        foreach ($request->items as $it) {
            $pid = (int) $it['produit_id'];
            $demande[$pid] = ($demande[$pid] ?? 0) + (int) $it['quantite'];
        }

        // Seuls les produits actifs de la vitrine de CE vendeur sont commandables
        $autorises = BoutiqueProduit::where('vendeur_id', $vendeur->id)
            ->where('actif', true)
            ->whereIn('produit_id', array_keys($demande))
            ->pluck('titre', 'produit_id');

        if ($autorises->count() !== count($demande)) {
            return response()->json(['message' => 'Un des produits n\'est plus disponible'], 422);
        }

        try {
            $resultat = DB::transaction(function () use ($request, $vendeur, $demande) {
                // Verrou : deux commandes simultanées ne peuvent pas dépasser le stock
                $produits = Produit::whereIn('id', array_keys($demande))->lockForUpdate()->get()->keyBy('id');

                $lignes  = [];
                $montant = 0;
                foreach ($demande as $pid => $qte) {
                    $p = $produits->get($pid);
                    if (!$p || (int) $p->quantite_stock < $qte) {
                        throw new \RuntimeException('Stock insuffisant pour : ' . ($p->nom ?? 'produit'));
                    }
                    $sousTotal = (float) $p->prix_unitaire * $qte;
                    $montant  += $sousTotal;
                    $lignes[]  = ['p' => $p, 'qte' => $qte, 'sous_total' => $sousTotal];
                }

                $premier = $lignes[0]['p'];
                $zone    = $request->client_ville ?: $request->client_adresse;

                $vente = Vente::create([
                    'caissiere_id'     => $vendeur->id,
                    'boutique_id'      => $vendeur->boutique_id,
                    'produit_id'       => $premier->id,
                    'quantite'         => array_sum($demande),
                    'prix_unitaire'    => (float) $premier->prix_unitaire,
                    'prix_vendeur'     => (float) $premier->prix_unitaire,
                    'remise'           => 0,
                    'montant_total'    => $montant,
                    'date_vente'       => now()->toDateString(),
                    'zone_livraison'   => $zone,
                    'statut'           => 'en_attente',
                    'client_nom'       => $request->client_nom,
                    'client_telephone' => $request->client_telephone,
                    'client_quartier'  => $request->client_adresse ?: $request->client_ville,
                    'notes'            => self::MARQUE_NOTES . ' — ' . ($vendeur->nom_boutique ?? $vendeur->name),
                ]);

                foreach ($lignes as $l) {
                    VenteItem::create([
                        'vente_id'      => $vente->id,
                        'produit_id'    => $l['p']->id,
                        'quantite'      => $l['qte'],
                        'prix_unitaire' => (float) $l['p']->prix_unitaire,
                        'prix_vendeur'  => (float) $l['p']->prix_unitaire,
                        'remise'        => 0,
                        'sous_total'    => $l['sous_total'],
                    ]);
                    $l['p']->decrement('quantite_stock', $l['qte']);
                }

                // Course visible par les livreurs (comme une vente saisie par le vendeur)
                $livData = [
                    'statut'         => 'en_attente',
                    'date_livraison' => $vente->date_vente,
                    'zone_livraison' => $vente->zone_livraison,
                    'notes'          => $vente->notes,
                ];
                foreach (['vente_id' => $vente->id, 'client_nom' => $vente->client_nom,
                          'client_telephone' => $vente->client_telephone,
                          'client_quartier' => $vente->client_quartier] as $col => $val) {
                    if (Schema::hasColumn('livraisons', $col)) {
                        $livData[$col] = $val;
                    }
                }
                $livraison = Livraison::create($livData);

                if (Schema::hasTable('livraison_produits')) {
                    foreach ($lignes as $l) {
                        LivraisonProduit::create([
                            'livraison_id' => $livraison->id,
                            'produit_id'   => $l['p']->id,
                            'quantite'     => $l['qte'],
                        ]);
                    }
                }

                return ['vente' => $vente, 'livraison' => $livraison, 'montant' => $montant];
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Géocodage du quartier pour l'assignation au livreur le plus proche — jamais bloquant
        try {
            $liv     = $resultat['livraison'];
            $adresse = $resultat['vente']->client_quartier ?? $resultat['vente']->zone_livraison;
            if ($adresse && Schema::hasColumn('livraisons', 'client_latitude')) {
                $coords = (new GeocodingService())->geocode($adresse);
                if ($coords) {
                    $liv->update(['client_latitude' => $coords['lat'], 'client_longitude' => $coords['lng']]);
                }
            }
        } catch (\Throwable $e) {
            // on ignore : la commande est déjà enregistrée
        }

        return response()->json([
            'message' => 'Commande passée avec succès !',
            'numero'  => $resultat['vente']->id,
            'montant' => $resultat['montant'],
            'vendeur' => $vendeur->nom_boutique ?? $vendeur->name,
        ], 201);
    }

    // ── Stats de la boutique du vendeur (commandes passées via sa page publique) ──
    public function mesBoutiqueStats(Request $request)
    {
        $user = $request->user();

        $ventes = Vente::where('caissiere_id', $user->id)
            ->where('notes', 'like', self::MARQUE_NOTES . '%')
            ->get();

        $aujourdhui = today()->toDateString();

        return response()->json([
            'code_boutique'  => $user->code_boutique,
            'lien'           => config('app.url') . '/boutique/' . $user->code_boutique,
            'nb_commandes'   => $ventes->count(),
            'ca_total'       => $ventes->sum('montant_total'),
            'ca_aujourd_hui' => $ventes
                ->filter(fn($v) => substr((string) $v->date_vente, 0, 10) === $aujourdhui)
                ->sum('montant_total'),
        ]);
    }
}
