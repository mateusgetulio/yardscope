<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('job_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('photo_count');
            $table->json('observation')->nullable();
            $table->string('failure')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_runs');
    }
};
