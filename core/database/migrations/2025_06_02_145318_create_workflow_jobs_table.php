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
        Schema::create('workflow_jobs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->uuid('session_id');
            $table->bigInteger('workflow_id');
            $table->string('receiver');
            $table->longText('message');
            $table->enum('status', ['sent', 'invalid', 'failed', 'pending'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_jobs');
    }
};
