<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $exists = DB::table('roles')->where('nom', 'super_admin')->exists();
        if (!$exists) {
            DB::table('roles')->insert([
                'nom'        => 'super_admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    public function down(): void {
        DB::table('roles')->where('nom', 'super_admin')->delete();
    }
};
