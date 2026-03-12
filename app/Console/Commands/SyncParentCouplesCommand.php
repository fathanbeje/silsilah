<?php

namespace App\Console\Commands;

use App\Services\ParentCoupleResolver;
use Illuminate\Console\Command;

class SyncParentCouplesCommand extends Command
{
    protected $signature = 'family:sync-parent-couples';

    protected $description = 'Sinkronkan parent_id dari kombinasi father_id dan mother_id untuk semua user';

    public function __construct(private ParentCoupleResolver $parentCoupleResolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->parentCoupleResolver->syncAllUsers();

        $this->info('Sinkronisasi parent couple selesai.');
        $this->line('Diproses : '.$result['processed']);
        $this->line('Diupdate : '.$result['updated']);
        $this->line('Dikosongkan : '.$result['cleared']);

        return self::SUCCESS;
    }
}
