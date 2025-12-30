<?php

declare(strict_types=1);

namespace Mrmarchone\LaravelAutoCrud\Console\Commands;

use Illuminate\Console\Command;

class PublishTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto-crud:publish-translations
                            {--force : Overwrite existing translation files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish ResponseMessages translation files to your application';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Publishing ResponseMessages translations...');

        $this->call('vendor:publish', [
            '--tag' => 'auto-crud-translations',
            '--force' => $this->option('force'),
        ]);

        $this->info('ResponseMessages translations published successfully!');
        $this->line('Translation files are located at: <comment>lang/vendor/laravel-auto-crud/</comment>');
        $this->line('You can customize the translations by editing the files in that directory.');

        return Command::SUCCESS;
    }
}

