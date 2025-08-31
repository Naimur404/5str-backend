<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('personalization_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('personalization_level');
            $table->decimal('response_time_ms', 8, 2);
            $table->integer('recommendation_count');
            $table->json('metrics')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('created_at');

            $table->index(['created_at', 'personalization_level']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('personalization_metrics');
    }
};
