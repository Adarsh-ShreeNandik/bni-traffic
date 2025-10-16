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
        Schema::create('relevants', function (Blueprint $table) {
            $table->id();
            // Foreign key to users table
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->string('name')->nullable();
            $table->string('p')->nullable();
            $table->string('a')->nullable();
            $table->string('l')->nullable();
            $table->string('m')->nullable();
            $table->string('s')->nullable();
            $table->string('rgi')->nullable();
            $table->string('rgo')->nullable();
            $table->string('rri')->nullable();
            $table->string('rro')->nullable();
            $table->string('v')->nullable();
            $table->string('1_2_1')->nullable();
            $table->string('tyfcb')->nullable();
            $table->string('ceu')->nullable();
            $table->string('t')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relevants');
    }
};
