<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('observation_run_id')->constrained()->cascadeOnDelete();
            $table->string('line_id');
            $table->string('field');
            $table->string('model_value');
            $table->string('customer_value');
            $table->string('reason')->nullable();
            $table->string('source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_records');
    }
};
