<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_calendar', function (Blueprint $table) {
            $table->enum('institution_type', ['university', 'college'])->default('university')->after('institution_id');
        });

        // Синхронизируем institution_type с institutions.type для существующих записей
        if (\DB::getDriverName() === 'mysql') {
            \DB::statement("UPDATE events_calendar ec JOIN institutions i ON ec.institution_id = i.id SET ec.institution_type = i.type");
        } else {
            // sqlite / pgsql: UPDATE ... JOIN не поддерживается, используем подзапрос
            \DB::statement("UPDATE events_calendar SET institution_type = (SELECT type FROM institutions WHERE institutions.id = events_calendar.institution_id) WHERE institution_id IN (SELECT id FROM institutions)");
        }
    }

    public function down(): void
    {
        Schema::table('events_calendar', function (Blueprint $table) {
            $table->dropColumn('institution_type');
        });
    }
};