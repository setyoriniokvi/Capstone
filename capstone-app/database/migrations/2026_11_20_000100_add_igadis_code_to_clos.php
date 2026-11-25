<?php

use App\Models\Clo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kode CLO iGadis (CLO SI, "PLOxx-CLOyy") per CLO capstone, dari sheet "Indikator Draft -> Igadis" / "Master CLO SI".
 * Disimpan utuh karena nomor CLO-nya saja bisa kembar (mis. PLO02-CLO05 & PLO05-CLO05). Diisi otomatis untuk CLO1–CLO8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clos', function (Blueprint $table) {
            $table->string('igadis_code', 30)->nullable();
        });

        foreach (Clo::IGADIS_DEFAULTS as $code => $igadis) {
            DB::table('clos')->where('code', $code)->whereNull('igadis_code')->update(['igadis_code' => $igadis]);
        }
    }

    public function down(): void
    {
        Schema::table('clos', function (Blueprint $table) {
            $table->dropColumn('igadis_code');
        });
    }
};
