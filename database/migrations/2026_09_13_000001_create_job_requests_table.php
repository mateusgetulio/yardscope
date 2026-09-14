<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('sentence');
            $table->json('profile');
            $table->json('photos');
            $table->string('readiness')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requests');
    }
};
