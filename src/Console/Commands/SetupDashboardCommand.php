<?php

namespace MicSoleLaravelGen\Console\Commands;

use Illuminate\Console\Command;
use MicSoleLaravelGen\Services\DashboardSetupService;

class SetupDashboardCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mic-sole:setup-dashboard
                            {--force : Force overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup dashboard structure for Vue 3 TypeScript admin panel';

    /**
     * Execute the console command.
     */
    public function handle(DashboardSetupService $service)
    {
        $this->info('🚀 Setting up dashboard structure...');

        try {
            $service->setup($this->option('force'));

            $this->info('✅ Dashboard structure setup completed successfully!');
            $this->newLine();
            $this->info('📝 Next steps:');
            $this->line('   1. Run: npm install (if needed)');
            $this->line('   2. Run: npm run dev');
            $this->line('   3. Start using @dashboard imports in your Vue components');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

