<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('job_request_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('reason')->nullable();
            $table->unsignedInteger('adjusted_price_cents')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_actions');
    }
};
