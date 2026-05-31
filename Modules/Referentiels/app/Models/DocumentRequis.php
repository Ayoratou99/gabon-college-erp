<?php

declare(strict_types=1);

namespace Modules\Referentiels\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AcademicStructure\Models\Section;
use Modules\Referentiels\Concerns\ReferentielModel;

final class DocumentRequis extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'documents_requis';

    /** @var array<int, string> */
    protected $fillable = [
        'code', 'libelle', 'description',
        'formats_acceptes', 'taille_max_ko',
        'obligatoire', 'active', 'display_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'formats_acceptes' => 'array',
        'taille_max_ko'    => 'integer',
        'obligatoire'      => 'boolean',
        'active'           => 'boolean',
        'display_order'    => 'integer',
    ];

    /**
     * Sections this document applies to. Empty set = "universal" (every
     * candidat sees this doc regardless of section choice). Non-empty =
     * "section-specific" — only candidats whose premier choix is one of
     * these sections see the slot.
     *
     * @return BelongsToMany<Section>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(
            Section::class,
            'documents_requis_sections',
            'document_requis_id',
            'section_id',
        )->withTimestamps();
    }

    /**
     * No section pivot rows ⇒ universal. Note: we use the `sections_count`
     * eager-loaded value when available so we don't run a query per row in
     * the list view (controllers should `->withCount('sections')`).
     */
    public function isUniversal(): bool
    {
        return ((int) ($this->sections_count ?? $this->sections()->count())) === 0;
    }

    /**
     * Scope: documents that apply to a candidat whose premier choix is
     * `$sectionId`. Returns universal docs (no section link at all) UNION
     * docs explicitly linked to the section.
     *
     *   DocumentRequis::query()->active()->ordered()->forSection($sectionId)->get();
     */
    public function scopeForSection(Builder $query, ?string $sectionId): Builder
    {
        if ($sectionId === null || $sectionId === '') {
            // No section picked yet — show only the universal docs. This
            // matches what we want during the inscription wizard's documents
            // step IF the candidat hasn't completed the choix step, which
            // the wizard's step ordering prevents anyway. Defensive default.
            return $query->whereDoesntHave('sections');
        }

        return $query->where(function (Builder $q) use ($sectionId): void {
            $q->whereDoesntHave('sections')  // universal
              ->orWhereHas('sections', fn (Builder $s) => $s->where('sections.id', $sectionId));
        });
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'code'                => ['required', 'string', 'max:30'],
            'libelle'             => ['required', 'string', 'max:191'],
            'description'         => ['nullable', 'string'],
            'formats_acceptes'    => ['nullable', 'array'],
            'formats_acceptes.*'  => ['string', 'in:pdf,jpg,jpeg,png,webp,heic'],
            'taille_max_ko'       => ['sometimes', 'integer', 'min:1', 'max:51200'],
            'obligatoire'         => ['sometimes', 'boolean'],
            'active'              => ['sometimes', 'boolean'],
            'display_order'       => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
