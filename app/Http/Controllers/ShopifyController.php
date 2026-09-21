<?php

namespace App\Http\Controllers;

use App\Models\CommandeClosing;
use App\Models\Boutique;
use App\Models\Vente;
use App\Models\VenteItem;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyController extends Controller
{
    // Clé de signature webhook Shopify
    const WEBHOOK_SECRET = 'e2632e690bec13dcac7bd1efa8ef60f92fc0a62b3cca03bf85716d52c6acc461';

    // ── Webhook Shopify — reçoit les nouvelles commandes ──
    public function webhookOrders(Request $request)
    {
        // Vérifier la signature Shopify
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $data       = $request->getContent();
        $calculated = base64_encode(hash_hmac('sha256', $data, self::WEBHOOK_SECRET, true));

        if ($hmacHeader !== $calculated) {
            Log::warning('Shopify webhook: signature invalide');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = $request->all();

        // Éviter les doublons
        if (CommandeClosing::where('shopify_order_id', $order['id'])->exists()) {
            return response()->json(['message' => 'already processed'], 200);
        }

        // Extraire les infos client
        $shipping = $order['shipping_address'] ?? $order['billing_address'] ?? [];
        $customer = $order['customer'] ?? [];

        // Extraire les produits
        $produits = collect($order['line_items'] ?? [])->map(function ($item) {
            return [
                'nom'      => $item['title'],
                'quantite' => $item['quantity'],
                'prix'     => $item['price'],
                'sku'      => $item['sku'] ?? null,
                'variante' => $item['variant_title'] ?? null,
            ];
        })->toArray();

        // Boutique par défaut (URSTORE Burkina)
        $boutique = Boutique::where('actif', true)->first();

        CommandeClosing::create([
            'boutique_id'         => $boutique?->id,
            'shopify_order_id'    => $order['id'],
            'shopify_order_number'=> $order['order_number'] ?? null,
            'client_nom'          => trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? ''))
                                     ?: ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''),
            'client_telephone'    => $shipping['phone'] ?? $customer['phone'] ?? null,
            'client_email'        => $customer['email'] ?? $order['email'] ?? null,
            'client_adresse'      => $shipping['address1'] ?? null,
            'client_ville'        => $shipping['city'] ?? null,
            'client_pays'         => $shipping['country'] ?? null,
            'montant_total'       => $order['total_price'] ?? 0,
            'devise'              => $order['currency'] ?? 'XOF',
            'produits'            => $produits,
            'statut'              => 'disponible',
            'shopify_data'        => $order,
        ]);

        Log::info('Shopify webhook: commande #' . ($order['order_number'] ?? $order['id']) . ' reçue');
        return response()->json(['message' => 'ok'], 200);
    }

    // ── Lister les commandes closing ──
    public function index(Request $request)
    {
        $user    = $request->user()->load('role');
        $roleNom = $user->role->nom;

        $query = CommandeClosing::with(['vendeur', 'vente'])
            ->orderByDesc('created_at');

        // Vendeur voit les commandes disponibles + les siennes
        if ($roleNom === 'vendeur') {
            $query->where(function ($q) use ($user) {
                $q->where('statut', 'disponible')
                  ->orWhere('vendeur_id', $user->id);
            });
        }

        // Filtres
        if ($request->statut) {
            $query->where('statut', $request->statut);
        }

        return response()->json($query->get());
    }

    // ── Vendeur prend une commande ──
    public function prendreCommande(Request $request, $id)
    {
        $commande = CommandeClosing::findOrFail($id);

        if ($commande->statut !== 'disponible') {
            return response()->json(['message' => 'Cette commande n\'est plus disponible'], 422);
        }

        $commande->update([
            'statut'     => 'prise',
            'vendeur_id' => $request->user()->id,
            'prise_le'   => now(),
        ]);

        return response()->json([
            'message'  => 'Commande prise avec succès !',
            'commande' => $commande->load('vendeur'),
        ]);
    }

    // ── Vendeur traite la commande après appel ──
    public function traiterCommande(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:confirmer,relancer,rejeter',
            'motif'  => 'required_if:action,rejeter,relancer|nullable|string',
            // Infos modifiées si le client veut changer
            'client_nom'       => 'nullable|string',
            'client_telephone' => 'nullable|string',
            'client_adresse'   => 'nullable|string',
            'client_ville'     => 'nullable|string',
            'zone_livraison'   => 'nullable|string',
        ]);

        $commande = CommandeClosing::findOrFail($id);

        if ($commande->vendeur_id !== $request->user()->id) {
            return response()->json(['message' => 'Cette commande ne vous appartient pas'], 403);
        }

        switch ($request->action) {

            case 'confirmer':
                // Mettre à jour les infos si modifiées
                $updateData = ['statut' => 'envoyee_livraison', 'traitee_le' => now()];
                if ($request->client_nom)       $updateData['client_nom']       = $request->client_nom;
                if ($request->client_telephone) $updateData['client_telephone'] = $request->client_telephone;
                if ($request->client_adresse)   $updateData['client_adresse']   = $request->client_adresse;
                if ($request->client_ville)      $updateData['client_ville']     = $request->client_ville;
                $commande->update($updateData);

                // Créer la vente automatiquement
                $vente = $this->creerVenteDepuisCommande($commande, $request);
                $commande->update(['vente_id' => $vente->id]);

                return response()->json([
                    'message'  => 'Commande confirmée et envoyée en livraison !',
                    'commande' => $commande->fresh('vente'),
                    'vente_id' => $vente->id,
                ]);

            case 'relancer':
                $commande->update([
                    'statut'        => 'a_relancer',
                    'motif_relance' => $request->motif,
                ]);
                return response()->json(['message' => 'Commande mise en attente de relance']);

            case 'rejeter':
                $commande->update([
                    'statut'      => 'rejetee',
                    'motif_rejet' => $request->motif,
                    'traitee_le'  => now(),
                ]);
                return response()->json(['message' => 'Commande rejetée — remontée au coordinateur']);
        }
    }

    // ── Créer une vente depuis une commande Shopify confirmée ──
    private function creerVenteDepuisCommande(CommandeClosing $commande, Request $request): Vente
    {
        $vente = Vente::create([
            'caissiere_id'    => $commande->vendeur_id,
            'boutique_id'     => $commande->boutique_id,
            'produit_id'      => null,
            'quantite'        => count($commande->produits ?? []),
            'prix_unitaire'   => $commande->montant_total,
            'prix_vendeur'    => $commande->montant_total,
            'remise'          => 0,
            'montant_total'   => $commande->montant_total,
            'date_vente'      => now()->toDateString(),
            'zone_livraison'  => $request->zone_livraison ?? $commande->client_ville,
            'statut'          => 'en_attente',
            'client_nom'      => $commande->client_nom,
            'client_telephone'=> $commande->client_telephone,
            'client_quartier' => $commande->client_adresse,
            'notes'           => 'Commande Shopify #' . $commande->shopify_order_number,
        ]);

        return $vente;
    }

    // ── Stats closing pour coordinateur/admin ──
    public function stats()
    {
        return response()->json([
            'disponibles'      => CommandeClosing::where('statut', 'disponible')->count(),
            'prises'           => CommandeClosing::where('statut', 'prise')->count(),
            'a_relancer'       => CommandeClosing::where('statut', 'a_relancer')->count(),
            'rejetees'         => CommandeClosing::where('statut', 'rejetee')->count(),
            'envoyees_livraison' => CommandeClosing::where('statut', 'envoyee_livraison')->count(),
            'total'            => CommandeClosing::count(),
        ]);
    }
}