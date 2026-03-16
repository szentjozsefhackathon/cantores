<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direktorium_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direktorium_edition_id')->constrained()->cascadeOnDelete();

            // Date and ordering
            $table->date('entry_date');
            $table->unsignedTinyInteger('entry_key')->default(0); // 0 = primary, 1,2... = alternatives (v.)

            // Celebration
            $table->string('celebration_name');
            $table->enum('rank', ['főünnep', 'ünnep', 'emléknap', 'emléknap_szabadon', 'köznap']);
            $table->string('liturgical_color')->nullable(); // viola, fehér, piros, zöld, fekete, stb.
            $table->string('diocese')->nullable(); // Kassa, Eger, stb. – null = általános

            // Mass permissions – what CANNOT be held (critical for cantors)
            $table->string('funeral_mass_code')->nullable(); // GY0, GY1, GY2
            $table->string('votive_mass_code')->nullable();  // V0, V1, V2
            $table->boolean('is_pro_populo')->default(false); // † jelzés
            $table->unsignedTinyInteger('penitential_level')->default(0); // 0=nincs, 1=†, 2=††, 3=†††

            // Mass – music-relevant
            $table->boolean('has_gloria')->nullable();
            $table->boolean('has_credo')->nullable();
            $table->string('preface')->nullable(); // pl. "I. adventi pref.", "karácsonyi pref."
            $table->text('special_mass_notes')->nullable(); // pl. "A IV. Eucharisztikus ima nem mondható"

            // Readings – JSON array of reading sets (supports alternatives with "v.")
            // Format: [{first_reading, psalm, second_reading, gospel}, ...]
            $table->json('readings')->nullable();

            // Divine Office (Zsolozsma)
            // Format: {main, lectio, lauds, vespers, compline, note}
            $table->json('office')->nullable();

            // Omitted / Transferred
            $table->boolean('is_omitted')->default(false); // Elmarad
            $table->date('transferred_to_date')->nullable(); // Áthelyezésre kerül

            // General rubrical notes
            $table->text('special_notes')->nullable();

            // PDF reference
            $table->unsignedSmallInteger('pdf_page_start')->nullable();
            $table->unsignedSmallInteger('pdf_page_end')->nullable();

            // Debug / audit
            $table->text('raw_ai_response')->nullable();

            $table->timestamps();

            $table->index(['entry_date', 'entry_key']);
            $table->index('direktorium_edition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direktorium_entries');
    }
};
