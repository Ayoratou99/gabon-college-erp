<?php

declare(strict_types=1);

namespace Modules\Parametrage\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Parametrage\Services\SettingsService;

/**
 * Declares the settings catalog and seeds it with sensible defaults derived
 * from the legacy hard-coded values (frais=10300 FCFA, eBilling endpoints,
 * homepage texts).
 *
 * Re-running this seeder NEVER overwrites a value an admin may have
 * customised in production — only the *declarative metadata* (label,
 * description, type, validation rules) is refreshed. See SettingsService::declare().
 */
final class SettingsSeeder extends Seeder
{
    public function run(SettingsService $settings): void
    {
        foreach ($this->catalog() as $definition) {
            $settings->declare($definition);
        }
    }

    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        return [
            // ---------------- Concours & frais ----------------
            [
                'key'         => 'concours.fee.amount',
                'category'    => 'concours',
                'type'        => 'integer',
                'label'       => 'Frais d\'inscription au concours (FCFA)',
                'description' => 'Montant facturé via eBilling à chaque candidat dont le dossier est accepté.',
                'value'       => 10300,
                'default_value' => 10300,
                'validation_rules' => ['min:0', 'max:1000000'],
                'is_public'   => true,
                'is_system'   => true,
                'display_order' => 10,
            ],
            [
                'key'         => 'concours.fee.currency',
                'category'    => 'concours',
                'type'        => 'string',
                'label'       => 'Devise',
                'value'       => 'FCFA',
                'default_value' => 'FCFA',
                'validation_rules' => ['size:4'],
                'is_public'   => true,
                'display_order' => 20,
            ],
            [
                'key'         => 'concours.fee.description',
                'category'    => 'concours',
                'type'        => 'text',
                'label'       => 'Libellé de la facture',
                'value'       => 'Frais d\'inscription au concours d\'entrée du Centre Universitaire de Koulamoutou.',
                'is_public'   => true,
                'display_order' => 30,
            ],

            // ---------------- eBilling (paiement) — encrypted ----------------
            [
                'key'         => 'ebilling.base_url',
                'category'    => 'ebilling',
                'type'        => 'url',
                'label'       => 'eBilling — URL de base',
                'value'       => env('EBILLING_BASE_URL', 'https://lab.billing-easy.net'),
                'is_public'   => false,
                'display_order' => 10,
            ],
            [
                'key'         => 'ebilling.username',
                'category'    => 'ebilling',
                'type'        => 'string',
                'label'       => 'eBilling — Nom d\'utilisateur',
                'value'       => env('EBILLING_USERNAME', 'CUK'),
                'is_public'   => false,
                'display_order' => 20,
            ],
            [
                'key'         => 'ebilling.shared_key',
                'category'    => 'ebilling',
                'type'        => 'string',
                'label'       => 'eBilling — Clé partagée',
                'value'       => env('EBILLING_SHARED_KEY', ''),
                'is_encrypted' => true,
                'is_public'   => false,
                'display_order' => 30,
            ],
            [
                'key'         => 'ebilling.hmac_secret',
                'category'    => 'ebilling',
                'type'        => 'string',
                'label'       => 'eBilling — Secret HMAC du callback',
                'description' => 'Utilisé pour valider la signature du POST de confirmation de paiement.',
                'value'       => env('EBILLING_HMAC_SECRET', ''),
                'is_encrypted' => true,
                'is_public'   => false,
                'display_order' => 40,
            ],

            // ---------------- Site public ----------------
            [
                'key'         => 'site.banner.title',
                'category'    => 'site',
                'type'        => 'string',
                'label'       => 'Bannière — Titre',
                'value'       => 'Concours d\'entrée — Centre Universitaire de Koulamoutou',
                'is_public'   => true,
                'display_order' => 10,
            ],
            [
                'key'         => 'site.banner.subtitle',
                'category'    => 'site',
                'type'        => 'text',
                'label'       => 'Bannière — Sous-titre',
                'value'       => 'Inscrivez-vous en ligne, déposez votre dossier et suivez l\'état de votre demande.',
                'is_public'   => true,
                'display_order' => 20,
            ],
            [
                'key'         => 'site.banner.cta_text',
                'category'    => 'site',
                'type'        => 'string',
                'label'       => 'Bannière — Bouton',
                'value'       => 'S\'inscrire',
                'is_public'   => true,
                'display_order' => 30,
            ],
            [
                'key'         => 'site.banner.cta_link',
                'category'    => 'site',
                'type'        => 'url',
                'label'       => 'Bannière — Lien du bouton',
                'value'       => '/inscription',
                'is_public'   => true,
                'display_order' => 40,
            ],
            [
                'key'         => 'site.home.sections',
                'category'    => 'site',
                'type'        => 'json',
                'label'       => 'Page d\'accueil — Sections',
                'description' => 'Liste ordonnée de blocs. Chaque entrée : title, body, icon (FontAwesome), order.',
                'value'       => [
                    [
                        'title' => 'Vérifier votre dossier',
                        'body'  => 'Suivez en temps réel l\'état de votre demande après son dépôt.',
                        'icon'  => 'fas fa-search',
                        'cta'   => 'Vérifier ma demande',
                        'link'  => '/verifier-demande',
                        'order' => 10,
                    ],
                    [
                        'title' => 'Résultats',
                        'body'  => 'Consultez les résultats des sessions précédentes.',
                        'icon'  => 'fas fa-trophy',
                        'cta'   => 'Voir les résultats',
                        'link'  => '/resultats',
                        'order' => 20,
                    ],
                    [
                        'title' => 'Documents requis',
                        'body'  => 'Préparez les pièces justificatives avant de commencer l\'inscription.',
                        'icon'  => 'fas fa-file-alt',
                        'cta'   => 'Lire la procédure',
                        'link'  => '/procedure',
                        'order' => 30,
                    ],
                ],
                'is_public'   => true,
                'display_order' => 50,
            ],

            // ---------------- Support / contact ----------------
            [
                'key'         => 'support.email',
                'category'    => 'support',
                'type'        => 'email',
                'label'       => 'Email de support',
                'value'       => 'support@cuk.ga',
                'is_public'   => true,
                'display_order' => 10,
            ],
            [
                'key'         => 'support.phone',
                'category'    => 'support',
                'type'        => 'phone',
                'label'       => 'Téléphone de support',
                'value'       => '+241 77 06 31 79',
                'is_public'   => true,
                'display_order' => 20,
            ],

            // ---------------- Sécurité ----------------
            [
                'key'         => 'security.2fa.force_for_roles',
                'category'    => 'security',
                'type'        => 'json',
                'label'       => '2FA obligatoire pour les rôles',
                'description' => 'Liste des codes de rôles pour lesquels la double authentification est forcée.',
                'value'       => ['super-admin', 'dg', 'de', 'chef-centre'],
                'is_public'   => false,
                'display_order' => 10,
            ],
            [
                'key'         => 'security.recaptcha.min_score',
                'category'    => 'security',
                'type'        => 'decimal',
                'label'       => 'reCAPTCHA — Score minimum accepté',
                'value'       => 0.5,
                'validation_rules' => ['min:0', 'max:1'],
                'is_public'   => false,
                'display_order' => 20,
            ],
        ];
    }
}
