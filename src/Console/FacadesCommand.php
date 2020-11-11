<?php

/**
 * Laravel IDE Helper Generator
 *
 * @author    Barry vd. Heuvel <barryvdh@gmail.com>
 * @copyright 2014 Barry vd. Heuvel / Fruitcake Studio (http://www.fruitcakestudio.nl)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      https://github.com/barryvdh/laravel-ide-helper
 */

namespace Barryvdh\LaravelIdeHelper\Console;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Barryvdh\LaravelIdeHelper\Generator;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * A command to generate autocomplete information for your IDE
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */
class FacadesCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ide-helper:generate-facades';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new IDE Helper file.';

    /** @var \Illuminate\Config\Repository */
    protected $config;

    /** @var \Illuminate\Filesystem\Filesystem */
    protected $files;

    /** @var \Illuminate\View\Factory */
    protected $view;

    protected $onlyExtend;

    /**
     *
     * @param \Illuminate\Config\Repository $config
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param \Illuminate\View\Factory $view
     */
    public function __construct(Repository $config, Filesystem $files, \Illuminate\View\Factory $view) {
        parent::__construct();

        $this->config = $config;
        $this->files = $files;
        $this->view = $view;


        $directory = $this->config->get('ide-helper.directory');
        $directory ? $this->files->ensureDirectoryExists($directory) : null;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (
            file_exists(base_path() . '/vendor/compiled.php') ||
            file_exists(base_path() . '/bootstrap/cache/compiled.php') ||
            file_exists(base_path() . '/storage/framework/compiled.php')
        ) {
            $this->error(
                'Error generating IDE Helper: first delete your compiled file (php artisan clear-compiled)'
            );
            return;
        }

        $format = $this->option('format');
        $directory = $this->config->get('ide-helper.directory');
        $filename = $this->config->get('ide-helper.facades_filename');
        $filename = $directory . $filename;

        if ($this->option('memory')) {
            $this->useMemoryDriver();
        }

        $helpers = '';
        if ($this->option('helpers') || ($this->config->get('ide-helper.include_helpers'))) {
            foreach ($this->config->get('ide-helper.helper_files', []) as $helper) {
                if (file_exists($helper)) {
                    $helpers .= str_replace(['<?php', '?>'], '', $this->files->get($helper));
                }
            }
        } else {
            $helpers = '';
        }

        $generator = new Generator($this->config, $this->view, $this->getOutput(), $helpers);
        $content = $generator->generate($format);
        $written = $this->files->put($filename, $content);

        if ($written !== false) {
            $this->info("A new helper file was written to $filename");

            if ($this->option('write_mixins')) {
                Eloquent::writeEloquentModelHelper($this, $this->files);
            }
        } else {
            $this->error("The helper file could not be created at $filename");
        }
    }

    protected function useMemoryDriver()
    {
        //Use a sqlite database in memory, to avoid connection errors on Database facades
        $this->config->set(
            'database.connections.sqlite',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ]
        );
        $this->config->set('database.default', 'sqlite');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        $filename = $this->config->get('ide-helper.filename');

        return [
            [
                'filename', InputArgument::OPTIONAL, 'The path to the helper file', $filename,
            ],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        $format = $this->config->get('ide-helper.format');
        $writeMixins = $this->config->get('ide-helper.write_eloquent_model_mixins');

        return [
            ['format', 'F', InputOption::VALUE_OPTIONAL, 'The format for the IDE Helper', $format],
            ['write_mixins', 'W', InputOption::VALUE_OPTIONAL, 'Write mixins to Laravel Model?', $writeMixins],
            ['helpers', 'H', InputOption::VALUE_NONE, 'Include the helper files'],
            ['memory', 'M', InputOption::VALUE_NONE, 'Use sqlite memory driver'],
        ];
    }
}
