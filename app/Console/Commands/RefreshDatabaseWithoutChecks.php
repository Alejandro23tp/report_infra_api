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
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        $this->call('migrate:fresh');
        $this->call('db:seed');
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        $this->info('Database refreshed successfully!');
    }
}
