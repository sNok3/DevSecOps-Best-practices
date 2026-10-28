<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('original_name');          // encrypted cast
            $table->uuid('stored_name')->unique();  // random name on disk, never user input
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);             // hash of the plaintext, checked after decryption
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_files');
    }
};
