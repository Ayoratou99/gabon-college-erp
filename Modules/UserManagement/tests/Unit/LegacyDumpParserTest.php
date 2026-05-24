<?php

declare(strict_types=1);

use Modules\UserManagement\Services\LegacyDumpParser;

it('extracts utilisateurs rows from a phpMyAdmin dump', function (): void {
    $sql = <<<'SQL'
        -- some leading comment
        INSERT INTO `utilisateurs` (`idut`, `nom`, `prenom`, `tel`, `mp`, `google_two_factor_secret`) VALUES
        (2, 'MVONE AYORATOU', 'Arthur', 'admin07', '7c222fb2927d828af22f592134e8932480637c0d', 'LWRRZFNI6WUHAPFK'),
        (101, 'IPOPA', 'Mohamed', '077063179', '94ba69fdd6ac7c1576e4b079514aa04004822824', NULL);
        SQL;

    $rows = (new LegacyDumpParser($sql))->rowsOf('utilisateurs');

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray([
            'idut'   => '2',
            'nom'    => 'MVONE AYORATOU',
            'tel'    => 'admin07',
            'mp'     => '7c222fb2927d828af22f592134e8932480637c0d',
            'google_two_factor_secret' => 'LWRRZFNI6WUHAPFK',
        ])
        ->and($rows[1]['google_two_factor_secret'])->toBeNull();
});

it('handles commas inside quoted values', function (): void {
    $sql = <<<'SQL'
        INSERT INTO `motifs` (`idetu`, `motif`) VALUES
        (5, 'Attestation, vraiment manquante'),
        (7, '- Plusieurs, motifs, ici');
        SQL;

    $rows = (new LegacyDumpParser($sql))->rowsOf('motifs');

    expect($rows[0]['motif'])->toBe('Attestation, vraiment manquante')
        ->and($rows[1]['motif'])->toBe('- Plusieurs, motifs, ici');
});

it('returns empty list when the table is absent', function (): void {
    expect((new LegacyDumpParser('-- no inserts here'))->rowsOf('utilisateurs'))->toBe([]);
});
