<?php

namespace App\Console\Commands;

use App\Models\Queue;
use Illuminate\Console\Command;

class PruneExpiredQueues extends Command
{
    protected $signature = 'queue:prune-expired';

    protected $description = 'Hapus antrean aktif yang lewat 24 jam dari akhir jadwal layanan';

    public function handle(): int
    {
        $count = Queue::expired()->delete();
        $this->info("{$count} antrean aktif kedaluwarsa dihapus.");

        return self::SUCCESS;
    }
}
