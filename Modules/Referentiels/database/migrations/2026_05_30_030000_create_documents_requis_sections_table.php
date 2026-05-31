<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot between documents_requis and sections.
 *
 * Semantics, evaluated at form-render and validation time:
 *
 *   - No row in this pivot for a given documents_requis → the document is
 *     "universal": it applies to every candidat regardless of their
 *     section choice. `obligatoire` determines whether it's required at
 *     inscription.
 *
 *   - One or more rows → the document is "section-specific": it applies
 *     only to candidats whose `section_premier_choix_id` is in the linked
 *     set. Candidats outside that set never see the slot in the wizard,
 *     and the validator never asks for it.
 *
 * The "obligatoire" + "section" axes compose naturally:
 *
 *                     obligatoire=true        obligatoire=false
 *   universal         required for everyone   optional for everyone
 *   section-linked    required for matching   optional for matching;
 *                     candidats only          hidden for others
 *
 * Composite PK = (document_requis_id, section_id) — a doc can't be linked
 * twice to the same section.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_requis_sections', function (Blueprint $table): void {
            $table->uuid('document_requis_id');
            $table->uuid('section_id');
            $table->timestamps();

            $table->primary(['document_requis_id', 'section_id']);

            $table->foreign('document_requis_id')
                ->references('id')->on('documents_requis')
                ->cascadeOnDelete();
            $table->foreign('section_id')
                ->references('id')->on('sections')
                ->cascadeOnDelete();

            // Hot path: "give me every doc applicable to section X".
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_requis_sections');
    }
};
