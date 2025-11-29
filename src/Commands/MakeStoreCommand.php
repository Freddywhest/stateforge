<?php

namespace Roddy\StateForge\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeStoreCommand extends Command
{
    protected $signature = 'make:store {name : The name of the store}';

    protected $description = 'Create a new StateForge store class';

    public function handle(Filesystem $files): void
    {
        $name = Str::studly($this->argument('name'));
        $path = app_path('Stores');

        if (!$files->isDirectory($path)) {
            $files->makeDirectory($path, 0755, true);
        }

        $storePath = $path . '/' . $name . '.php';

        if ($files->exists($storePath)) {
            $this->error("Store {$name} already exists!");
            return;
        }

        $stub = $files->get(__DIR__ . '/../../stubs/store.stub');
        $stub = str_replace('{{class}}', $name, $stub);

        $files->put($storePath, $stub);

        $this->info("Store created successfully: {$storePath}");
        $this->info("Store will be auto-discovered and available as: StateForge::get(\\App\\Stores\\{$name}::class)");
    }
}
