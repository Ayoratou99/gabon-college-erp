<?php

declare(strict_types=1);

namespace Modules\Referentiels\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Referentiels\Models\Nationalite;

/**
 * Seeds the 90 countries from the legacy `nationalites` table. Codes are
 * normalised to ISO 3166-1 alpha-2 where the legacy code was ambiguous.
 */
final class NationalitesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->countries() as $i => [$code, $nom]) {
            Nationalite::query()->updateOrCreate(
                ['code_iso' => $code],
                ['nom' => $nom, 'active' => true, 'display_order' => $i + 1],
            );
        }
    }

    /** @return list<array{0: string, 1: string}> */
    private function countries(): array
    {
        return [
            ['GA', 'Gabonais'],          // Listed first — the dominant nationality.
            ['DZ', 'Algérien'],
            ['DE', 'Allemand'],
            ['US', 'Américain'],
            ['AR', 'Argentin'],
            ['AU', 'Australien'],
            ['AT', 'Autrichien'],
            ['BE', 'Belge'],
            ['BJ', 'Béninois'],
            ['BW', 'Botswanais'],
            ['BR', 'Brésilien'],
            ['GB', 'Britannique'],
            ['BG', 'Bulgare'],
            ['BF', 'Burkinabè'],
            ['KH', 'Cambodgien'],
            ['CM', 'Camerounais'],
            ['CA', 'Canadien'],
            ['CL', 'Chilien'],
            ['CN', 'Chinois'],
            ['CO', 'Colombien'],
            ['CG', 'Congolais (Brazzaville)'],
            ['CD', 'Congolais (Kinshasa)'],
            ['KR', 'Coréen'],
            ['CR', 'Costaricien'],
            ['HR', 'Croate'],
            ['CU', 'Cubain'],
            ['DK', 'Danois'],
            ['EC', 'Equatorien'],
            ['EG', 'Egyptien'],
            ['ES', 'Espagnol'],
            ['EE', 'Estonien'],
            ['FI', 'Finlandais'],
            ['FR', 'Français'],
            ['GE', 'Georgien'],
            ['GR', 'Grec'],
            ['HT', 'Haïtien'],
            ['HK', 'Hong-Kong'],
            ['HU', 'Hongrois'],
            ['IN', 'Indien'],
            ['IR', 'Iranien'],
            ['IE', 'Irlandais'],
            ['IS', 'Islandais'],
            ['IL', 'Israélien'],
            ['IT', 'Italien'],
            ['CI', 'Ivoirien'],
            ['JM', 'Jamaïcain'],
            ['JP', 'Japonais'],
            ['KZ', 'Kazakh'],
            ['LV', 'Lettonien'],
            ['LB', 'Libanais'],
            ['LT', 'Lituanien'],
            ['LU', 'Luxembourgeois'],
            ['MK', 'Macédonien'],
            ['MG', 'Malgache'],
            ['ML', 'Malien'],
            ['MA', 'Marocain'],
            ['MX', 'Mexicain'],
            ['NL', 'Néerlandais'],
            ['NZ', 'Néo-Zélandais'],
            ['NO', 'Norvégien'],
            ['PS', 'Palestinien'],
            ['PE', 'Péruvien'],
            ['PL', 'Polonais'],
            ['PT', 'Portugais'],
            ['RO', 'Roumain'],
            ['RU', 'Russe'],
            ['SN', 'Sénégalais'],
            ['RS', 'Serbe'],
            ['SG', 'Singapourien'],
            ['SI', 'Slovène'],
            ['ZA', 'Sud-Africain'],
            ['SE', 'Suédois'],
            ['CH', 'Suisse'],
            ['TJ', 'Tadjik'],
            ['TW', 'Taïwanais'],
            ['TD', 'Tchadien'],
            ['CZ', 'Tchèque'],
            ['TG', 'Togolais'],
            ['TN', 'Tunisien'],
            ['TR', 'Turc'],
            ['UA', 'Ukrainien'],
            ['UY', 'Uruguayen'],
            ['VE', 'Vénézuélien'],
            ['VN', 'Vietnamien'],
        ];
    }
}
