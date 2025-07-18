<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_applications', function (Blueprint $table) {
            $table->uuid('ticket_code')->nullable()->after('status')->unique();
        });
    }

    public function down()
    {
        Schema::table('user_applications', function (Blueprint $table) {
            $table->dropColumn('ticket_code');
        });
    }
}; 