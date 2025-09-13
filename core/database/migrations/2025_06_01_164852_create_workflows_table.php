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
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->uuid('session_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('message_template_id');
            $table->json('variables');
            $table->enum('status', ['dev', 'prod'])->default('prod');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
