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
        Schema::create('apply_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrer_id')->nullable()->constrained('careers')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->longText('cover_letter')->nullable();
            $table->json('document')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apply_forms');
    }
};
