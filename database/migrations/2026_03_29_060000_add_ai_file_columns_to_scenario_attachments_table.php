<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scenario_attachments', function (Blueprint $table) {
            $table->string('ai_provider')->nullable()->after('size');
            $table->string('ai_file_id')->nullable()->after('ai_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scenario_attachments', function (Blueprint $table) {
            $table->dropColumn(['ai_provider', 'ai_file_id']);
        });
    }
};
