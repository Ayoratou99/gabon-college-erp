<?php

declare(strict_types=1);

namespace Modules\Parametrage\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Parametrage\Models\Setting;
use Modules\Parametrage\Services\SettingsService;

/**
 * Declares the settings catalog and seeds it with sensible defaults derived
 * from the legacy hard-coded values (frais=10300 FCFA, homepage texts, etc).
 *
 * Re-running this seeder NEVER overwrites a value an admin may have
 * customised in production — only the *declarative metadata* (label,
 * description, type, validation rules) is refreshed. See SettingsService::declare().
 *
 * Operational secrets (eBilling URL, username, shared key, HMAC secret) live
 * exclusively in .env / config('concours.ebilling.*'). Older databases that
 * had them seeded into the settings table are cleaned up on re-run.
 */
final class SettingsSeeder extends Seeder
{
    public function run(SettingsService $settings): void
    {
        // Purge legacy eBilling rows — they're now driven from .env.
        Setting::query()->where('category', 'ebilling')->forceDelete();

        // Rewrite values that still match retired hard-coded defaults so
        // older installs pick up the new bundled images. Admin overrides
        // are NOT touched (value-equality check).
        $this->migrateLegacyDefaults();

        foreach ($this->catalog() as $definition) {
            $settings->declare($definition);
        }
    }

    private function migrateLegacyDefaults(): void
    {
        $oldHero = 'https://images.unsplash.com/photo-1607237138185-eedd9c632b0b?auto=format&fit=crop&w=1920&q=80';
        Setting::query()
            ->where('key', 'site.banner.background_image')
            ->where('value', $oldHero)
            ->update(['value' => '/img/cuk/campus-hero.jpg']);

        Setting::query()
            ->where('key', 'site.brand.logo_url')
            ->whereIn('value', ['', '""'])
            ->update(['value' => '/img/cuk/logo.jpg']);

        $oldSectionMap = [
            'photo-1518770660439-4636190af475' => '/img/cuk/laboratoires.jpg',
            'photo-1523050854058-8df90110c9f1' => '/img/cuk/amphi.jpg',
            'photo-1455390582262-044cdead277a' => '/img/cuk/equipements.jpg',
            'photo-1532153975070-2e9ab71f1b14' => '/img/cuk/campus-view.jpg',
        ];

        $row = Setting::query()->where('key', 'site.home.sections')->first();
        if ($row !== null && is_string($row->value)) {
            // Case A — value still carries an Unsplash URL: substitute by id.
            if (str_contains($row->value, 'unsplash.com')) {
                $patched = $row->value;
                foreach ($oldSectionMap as $needle => $replacement) {
                    $patched = preg_replace(
                        '~https?://images\.unsplash\.com/' . preg_quote($needle, '~') . '[^"\\\\]*~',
                        $replacement,
                        $patched,
                    );
                }
                $row->value = $patched;
                $row->save();
            }

            // Case B — strip the `image` key from every section. The
            // services-candidats cards are now icon-only; per-section images
            // moved to the formations grid (driven by Section.image_url).
            $sections = json_decode($row->value, true);
            if (is_array($sections)) {
                $changed = false;
                foreach ($sections as $i => $s) {
                    if (array_key_exists('image', $s)) {
                        unset($sections[$i]['image']);
                        $changed = true;
                    }
                }
                if ($changed) {
                    $row->value = json_encode(array_values($sections), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $row->save();
                }
            }
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

            // (eBilling credentials are intentionally NOT in this catalog.
            // They live in .env / config('concours.ebilling.*') — secrets
            // don't belong in the settings table.)

            // ---------------- Site public ----------------
            [
                'key'         => 'site.brand.logo_url',
                'category'    => 'site',
                'type'        => 'image_url',
                'label'       => 'Identité — Logo (URL)',
                'description' => 'URL d\'une image affichée dans la barre de navigation publique (PNG / SVG transparent recommandé). Images CUK fournies sous /img/cuk/.',
                'value'       => '/img/cuk/logo.jpg',
                'is_public'   => true,
                'display_order' => 1,
            ],
            [
                'key'         => 'site.brand.short_name',
                'category'    => 'site',
                'type'        => 'string',
                'label'       => 'Identité — Nom court',
                'description' => 'Affiché à côté du logo dans la barre de navigation.',
                'value'       => 'CUK',
                'is_public'   => true,
                'display_order' => 2,
            ],
            [
                'key'         => 'site.brand.full_name',
                'category'    => 'site',
                'type'        => 'string',
                'label'       => 'Identité — Nom complet',
                'value'       => 'Centre Universitaire de Koulamoutou',
                'is_public'   => true,
                'display_order' => 3,
            ],
            [
                'key'         => 'site.auth.background_image',
                'category'    => 'site',
                'type'        => 'image_url',
                'label'       => 'Connexion — Image de fond',
                'description' => 'Photo affichée en arrière-plan de la page de connexion / activation. Locale (livrée avec l\'app) : /img/cuk/amphi.jpg, /img/cuk/laboratoires.jpg, /img/cuk/campus-view.jpg.',
                'value'       => '/img/cuk/amphi.jpg',
                'is_public'   => true,
                'display_order' => 4,
            ],
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
                'key'         => 'site.banner.secondary_cta_text',
                'category'    => 'site',
                'type'        => 'string',
                'label'       => 'Bannière — Bouton secondaire',
                'value'       => 'Vérifier mon dossier',
                'is_public'   => true,
                'display_order' => 41,
            ],
            [
                'key'         => 'site.banner.secondary_cta_link',
                'category'    => 'site',
                'type'        => 'url',
                'label'       => 'Bannière — Lien du bouton secondaire',
                'value'       => '/verifier-demande',
                'is_public'   => true,
                'display_order' => 42,
            ],
            [
                'key'         => 'site.banner.background_image',
                'category'    => 'site',
                'type'        => 'image_url',
                'label'       => 'Bannière — Image de fond (URL)',
                'description' => 'Photo affichée derrière le titre principal. Locale (livrée avec l\'app) : /img/cuk/campus-hero.jpg, /img/cuk/amphi.jpg, /img/cuk/laboratoires.jpg.',
                'value'       => '/img/cuk/campus-hero.jpg',
                'is_public'   => true,
                'display_order' => 50,
            ],
            [
                'key'         => 'site.about.title',
                'category'    => 'site',
                'type'        => 'string',
                'label'       => 'À propos — Titre',
                'value'       => 'Le Centre Universitaire de Koulamoutou',
                'is_public'   => true,
                'display_order' => 60,
            ],
            [
                'key'         => 'site.about.text',
                'category'    => 'site',
                'type'        => 'text',
                'label'       => 'À propos — Texte',
                'value'       => 'Le CUK forme la nouvelle génération de techniciens et d\'ingénieurs du Gabon à travers ses formations DUT en sciences et technologies. Notre concours d\'entrée sélectionne chaque année les meilleurs profils issus des séries scientifiques.',
                'is_public'   => true,
                'display_order' => 70,
            ],
            [
                'key'         => 'site.stats',
                'category'    => 'site',
                'type'        => 'json',
                'label'       => 'Chiffres clés',
                'description' => 'Liste de métriques (icon FontAwesome, value, label).',
                'value'       => [
                    ['icon' => 'fas fa-graduation-cap', 'value' => '7',  'label' => 'Filières DUT'],
                    ['icon' => 'fas fa-map-marker-alt', 'value' => '9',  'label' => 'Centres d\'examen'],
                    ['icon' => 'fas fa-users',          'value' => '350', 'label' => 'Places par session'],
                    ['icon' => 'fas fa-calendar-check', 'value' => '6',  'label' => 'Années d\'expérience'],
                ],
                'is_public'   => true,
                'display_order' => 80,
            ],
            [
                'key'         => 'site.procedure_steps',
                'category'    => 'site',
                'type'        => 'json',
                'label'       => 'Procédure d\'inscription — Étapes',
                'value'       => [
                    ['icon' => 'fas fa-edit',          'title' => '1. Remplir le formulaire', 'body' => 'Saisissez vos informations personnelles et choisissez votre filière.'],
                    ['icon' => 'fas fa-file-upload',   'title' => '2. Téléverser les pièces', 'body' => 'Acte de naissance, attestation BAC, relevés, photo d\'identité.'],
                    ['icon' => 'fas fa-clipboard-check','title'=> '3. Validation du dossier', 'body' => 'Notre équipe vérifie votre dossier sous 48 à 72 heures.'],
                    ['icon' => 'fas fa-credit-card',   'title' => '4. Paiement des frais',     'body' => 'Payez en ligne via eBilling pour finaliser votre inscription.'],
                ],
                'is_public'   => true,
                'display_order' => 90,
            ],
            [
                'key'         => 'site.home.sections',
                'category'    => 'site',
                'type'        => 'json',
                'label'       => 'Page d\'accueil — Cartes services',
                'description' => 'Liste ordonnée de blocs. Chaque entrée : title, body, icon, cta, link, order.',
                'value'       => [
                    ['title' => 'Vérifier mon dossier',  'body' => 'Suivez en temps réel l\'état de votre demande après son dépôt.',                  'icon' => 'fas fa-search',      'cta' => 'Vérifier',            'link' => '/verifier-demande',  'order' => 10],
                    ['title' => 'Résultats du concours', 'body' => 'Consultez les listes d\'admis dès leur publication officielle.',                     'icon' => 'fas fa-trophy',      'cta' => 'Voir les résultats',  'link' => '/resultats',         'order' => 20],
                    ['title' => 'Modifier mon dossier',  'body' => 'Votre dossier a été rejeté ? Récupérez-le avec email + téléphone pour le compléter.', 'icon' => 'fas fa-edit',        'cta' => 'Modifier',            'link' => '/recuperer-dossier', 'order' => 30],
                    ['title' => 'Procédure complète',    'body' => 'Documents requis, frais, dates clés — tout ce qu\'il faut savoir avant de postuler.', 'icon' => 'fas fa-info-circle', 'cta' => 'En savoir plus',      'link' => '/inscription',       'order' => 40],
                ],
                'is_public'   => true,
                'display_order' => 100,
            ],
            [
                'key'         => 'site.footer.about_text',
                'category'    => 'site',
                'type'        => 'text',
                'label'       => 'Pied de page — Texte de présentation',
                'value'       => 'Le Centre Universitaire de Koulamoutou est un établissement public d\'enseignement supérieur dédié à la formation technologique de pointe.',
                'is_public'   => true,
                'display_order' => 110,
            ],
            [
                'key'         => 'site.footer.address',
                'category'    => 'site',
                'type'        => 'text',
                'label'       => 'Pied de page — Adresse postale',
                'value'       => 'BP 240, Koulamoutou, Ogooué-Lolo, Gabon',
                'is_public'   => true,
                'display_order' => 120,
            ],
            [
                'key'         => 'site.footer.quick_links',
                'category'    => 'site',
                'type'        => 'json',
                'label'       => 'Pied de page — Liens rapides',
                'value'       => [
                    ['label' => 'Inscription au concours', 'url' => '/inscription'],
                    ['label' => 'Vérifier mon dossier',    'url' => '/verifier-demande'],
                    ['label' => 'Récupérer mon dossier',   'url' => '/recuperer-dossier'],
                    ['label' => 'Résultats',               'url' => '/resultats'],
                ],
                'is_public'   => true,
                'display_order' => 130,
            ],
            [
                'key'         => 'site.social.facebook',
                'category'    => 'site',
                'type'        => 'url',
                'label'       => 'Réseaux sociaux — Facebook',
                'value'       => 'https://facebook.com/',
                'is_public'   => true,
                'display_order' => 140,
            ],
            [
                'key'         => 'site.social.twitter',
                'category'    => 'site',
                'type'        => 'url',
                'label'       => 'Réseaux sociaux — Twitter / X',
                'value'       => 'https://twitter.com/',
                'is_public'   => true,
                'display_order' => 150,
            ],
            [
                'key'         => 'site.social.linkedin',
                'category'    => 'site',
                'type'        => 'url',
                'label'       => 'Réseaux sociaux — LinkedIn',
                'value'       => 'https://linkedin.com/',
                'is_public'   => true,
                'display_order' => 160,
            ],

            // ---------------- Thème visuel ----------------
            [
                'key'         => 'site.theme.primary_color',
                'category'    => 'site',
                'type'        => 'color',
                'label'       => 'Thème — Couleur primaire',
                'value'       => '#1d4ed8',
                'is_public'   => true,
                'display_order' => 200,
            ],
            [
                'key'         => 'site.theme.accent_color',
                'category'    => 'site',
                'type'        => 'color',
                'label'       => 'Thème — Couleur d\'accent',
                'value'       => '#0ea5e9',
                'is_public'   => true,
                'display_order' => 210,
            ],
            [
                'key'         => 'site.theme.dark_color',
                'category'    => 'site',
                'type'        => 'color',
                'label'       => 'Thème — Couleur sombre (footer / sidebar)',
                'value'       => '#0f172a',
                'is_public'   => true,
                'display_order' => 220,
            ],
            [
                'key'         => 'site.theme.success_color',
                'category'    => 'site',
                'type'        => 'color',
                'label'       => 'Thème — Couleur succès',
                'value'       => '#16a34a',
                'is_public'   => true,
                'display_order' => 230,
            ],
            [
                'key'         => 'site.theme.danger_color',
                'category'    => 'site',
                'type'        => 'color',
                'label'       => 'Thème — Couleur erreur',
                'value'       => '#dc2626',
                'is_public'   => true,
                'display_order' => 240,
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
