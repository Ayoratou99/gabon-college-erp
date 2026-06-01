<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * epreuve_sections — an épreuve now targets ONE OR MANY sections (de concours),
 * replacing the single scope_type/scope_id pair as the source of truth for
 * eligibility, moyenne calculation and the candidate's emploi du temps.
 *
 * The legacy `epreuves.scope_type` / `scope_id` columns are KEPT (not dropped)
 * for backward compatibility, but the new code reads this pivot. We backfill it
 * from the old scope:
 *   - scope_type='section' → one pivot row for that section
 *   - scope_type='cycle'   → one pivot row per section belonging to the cycle
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epreuve_sections', function (Blueprint $table): void {
            // Pure pivot — composite PK (epreuve_id, section_id), no surrogate id,
            // so belongsToMany sync() inserts cleanly without UUID generation.
            $table->uuid('epreuve_id');
            $table->uuid('section_id');
            $table->timestamps();

            $table->primary(['epreuve_id', 'section_id']);
            $table->index('section_id');

            $table->foreign('epreuve_id')->references('id')->on('epreuves')->cascadeOnDelete();
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
        });

        // --- Backfill from the legacy single-scope columns ---
        $now  = now();
        $rows = [];

        foreach (DB::table('epreuves')->whereNull('deleted_at')->get(['id', 'scope_type', 'scope_id']) as $e) {
            $sectionIds = [];

            if ($e->scope_type === 'section' && $e->scope_id) {
                $sectionIds = [$e->scope_id];
            } elseif ($e->scope_type === 'cycle' && $e->scope_id) {
                $sectionIds = DB::table('sections')
                    ->where('cycle_id', $e->scope_id)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->all();
            }

            foreach ($sectionIds as $sid) {
                $rows[] = [
                    'epreuve_id' => $e->id,
                    'section_id' => $sid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('epreuve_sections')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('epreuve_sections');
    }
};
