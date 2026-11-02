<?php

namespace App\Http\Controllers;

use App\Models\BoutiqueProduit;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Produits de la vitrine d'un vendeur (images, mini-vidéo, description).
 * Chaque entrée est liée à un produit réel du catalogue : le prix, le stock,
 * la commission et la livraison restent donc gérés par le système existant.
 */
class BoutiqueProduitController extends Controller
{
    private const MAX_IMAGES = 5;

    private function vendeur(Request $request)
    {
        $user = $request->user();
        if (!in_array($user?->role?->nom, ['vendeur', 'super_admin'])) {
            abort(403, 'Accès refusé');
        }
        return $user;
    }

    private function reglesFichiers(): array
    {
        return [
            'images'   => 'nullable|array|max:' . self::MAX_IMAGES,
            'images.*' => 'image|mimes:jpeg,png,webp|max:4096',
            'video'    => 'nullable|file|mimetypes:video/mp4,video/webm,video/quicktime|max:15360',
        ];
    }

    public function index(Request $request)
    {
        $user = $this->vendeur($request);

        return response()->json(
            BoutiqueProduit::with('produit:id,nom,prix_unitaire,quantite_stock')
                ->where('vendeur_id', $user->id)
                ->orderBy('ordre')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $user = $this->vendeur($request);

        $request->validate(array_merge([
            'produit_id'  => 'required|exists:produits,id',
            'titre'       => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
        ], $this->reglesFichiers()));

        $produit = Produit::findOrFail($request->produit_id);
        if (str_starts_with((string) $produit->nom, '[Archivé]')) {
            return response()->json(['message' => 'Ce produit est archivé'], 422);
        }

        if (BoutiqueProduit::where('vendeur_id', $user->id)->where('produit_id', $produit->id)->exists()) {
            return response()->json(['message' => 'Ce produit est déjà dans votre boutique — modifiez-le plutôt'], 422);
        }

        $images = [];
        foreach ($request->file('images', []) as $file) {
            $images[] = $file->store("boutiques/{$user->id}", 'public');
        }
        $video = $request->hasFile('video')
            ? $request->file('video')->store("boutiques/{$user->id}", 'public')
            : null;

        $item = BoutiqueProduit::create([
            'vendeur_id'  => $user->id,
            'produit_id'  => $produit->id,
            'titre'       => $request->titre,
            'description' => $request->description,
            'images'      => $images,
            'video'       => $video,
            'actif'       => true,
        ]);

        return response()->json($item->load('produit:id,nom,prix_unitaire,quantite_stock'), 201);
    }

    // POST (et non PUT) : PHP ne lit pas les fichiers d'une requête PUT multipart
    public function update(Request $request, $id)
    {
        $user = $this->vendeur($request);
        $item = BoutiqueProduit::where('vendeur_id', $user->id)->findOrFail($id);

        $request->validate(array_merge([
            'titre'          => 'sometimes|required|string|max:150',
            'description'    => 'nullable|string|max:5000',
            'actif'          => 'sometimes|boolean',
            'remove_images'  => 'nullable|array',
            'remove_images.*'=> 'string',
            'remove_video'   => 'sometimes|boolean',
        ], $this->reglesFichiers()));

        $images = $item->images ?? [];

        // Suppression des images demandées (uniquement celles qui appartiennent à cette fiche)
        foreach ((array) $request->input('remove_images', []) as $path) {
            if (in_array($path, $images, true)) {
                Storage::disk('public')->delete($path);
                $images = array_values(array_diff($images, [$path]));
            }
        }

        $nouvelles = $request->file('images', []);
        if (count($images) + count($nouvelles) > self::MAX_IMAGES) {
            return response()->json(['message' => 'Maximum ' . self::MAX_IMAGES . ' images par produit'], 422);
        }
        foreach ($nouvelles as $file) {
            $images[] = $file->store("boutiques/{$user->id}", 'public');
        }

        $video = $item->video;
        if ($request->boolean('remove_video') && $video) {
            Storage::disk('public')->delete($video);
            $video = null;
        }
        if ($request->hasFile('video')) {
            if ($video) {
                Storage::disk('public')->delete($video);
            }
            $video = $request->file('video')->store("boutiques/{$user->id}", 'public');
        }

        $data = ['images' => $images, 'video' => $video];
        foreach (['titre', 'description'] as $k) {
            if ($request->exists($k)) {
                $data[$k] = $request->input($k);
            }
        }
        if ($request->exists('actif')) {
            $data['actif'] = $request->boolean('actif');
        }
        $item->update($data);

        return response()->json($item->fresh()->load('produit:id,nom,prix_unitaire,quantite_stock'));
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->vendeur($request);
        $item = BoutiqueProduit::where('vendeur_id', $user->id)->findOrFail($id);

        foreach (($item->images ?? []) as $path) {
            Storage::disk('public')->delete($path);
        }
        if ($item->video) {
            Storage::disk('public')->delete($item->video);
        }
        $item->delete();

        return response()->json(['message' => 'Produit retiré de la boutique']);
    }
}
