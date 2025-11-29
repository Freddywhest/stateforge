<?php

namespace Roddy\StateForge\Commands;

use Illuminate\Console\Command;
use Roddy\StateForge\ClientIdentifier;

class CleanupStoresCommand extends Command
{
    protected $signature = 'stateforge:cleanup 
                            {--days=30 : Remove stores for clients not seen in X days}';

    protected $description = 'Clean up expired state stores';

    public function handle(ClientIdentifier $clientIdentifier): void
    {
        $days = $this->option('days');

        $this->info("Cleaning up stores for clients not seen in {$days} days...");

        $removed = $clientIdentifier->cleanupExpiredClients($days);

        $this->info("Cleanup completed! Removed {$removed} expired clients.");
    }
}
