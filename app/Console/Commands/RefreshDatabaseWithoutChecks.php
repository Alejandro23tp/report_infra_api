<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshDatabaseWithoutChecks extends Command
{
    protected $signature = 'db:refresh-without-checks';
    protected $description = 'Refresh database by disabling foreign key checks';

    public function handle()
    {
        $database = DB::getDatabaseName();
        
        // Drop all views
        $views = DB::select("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = '$database'");
        foreach ($views as $view) {
            DB::statement("DROP VIEW IF EXISTS `{$view->TABLE_NAME}`");
        }
        
        // Drop all tables
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");
        $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database' AND TABLE_TYPE = 'BASE TABLE'");
        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS `{$table->TABLE_NAME}`");
        }
        DB::statement("SET FOREIGN_KEY_CHECKS = 1");
        
        $this->call('migrate');
        $this->call('db:seed');
        
        $this->info('Database refreshed successfully!');
    }
}
