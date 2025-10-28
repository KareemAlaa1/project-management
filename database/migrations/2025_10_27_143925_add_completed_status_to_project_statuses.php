<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add "Completed" status if it doesn't exist
        $exists = DB::table('project_statuses')
            ->where('name', 'Completed')
            ->exists();

        if (!$exists) {
            DB::table('project_statuses')->insert([
                'name' => 'Completed',
                'color' => '#28a745',
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Optionally remove the Completed status
        DB::table('project_statuses')
            ->where('name', 'Completed')
            ->delete();
    }
};
