<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('approval_chain_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_chain_id')->constrained('approval_chains')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->integer('sequence')->comment('Order in the approval chain');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->boolean('is_current')->default(false)->comment('Whether this user is the current approver');
            $table->timestamps();

            $table->unique(['approval_chain_id', 'user_id'], 'approval_chain_user_unique');
            $table->unique(['approval_chain_id', 'sequence'], 'approval_chain_sequence_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('approval_chain_users');
    }
};
