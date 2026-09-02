<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('devices', function(Blueprint $table){$table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->nullOnDelete()->index();});
        $projectId=DB::table('projects')->where('name','XLSmart-BWA_10.5GHz')->value('id');
        if($projectId) DB::table('devices')->whereNull('project_id')->update(['project_id'=>$projectId]);
    }
    public function down(): void { Schema::table('devices', function(Blueprint $table){$table->dropConstrainedForeignId('project_id');}); }
};
