<?php

namespace Soara\Debugger\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Soara\Debugger\Assets;

/**
 * Copies the built CSS, JS and fonts to public/vendor/debugger, so the web
 * server serves them as static files. Run it after every install or update,
 * e.g. from Composer's post-autoload-dump script.
 */
class PublishCommand extends Command
{
    protected $signature = 'debugger:publish';

    protected $description = 'Copy the soara/laravel-debugger assets to public/vendor/debugger';

    public function handle(Assets $assets): int
    {
        $source = $assets->distPath().'/assets';
        $target = public_path(Assets::PUBLIC_DIR);

        if (! is_dir($source)) {
            $this->components->error('The package has no built assets in dist/assets.');

            return self::FAILURE;
        }

        // Start clean, so files from older versions don't pile up.
        File::deleteDirectory($target);
        File::ensureDirectoryExists($target);
        File::copyDirectory($source, $target.'/assets');

        $count = count(File::files($target.'/assets'));
        $this->components->info("Published {$count} files to public/".Assets::PUBLIC_DIR.'.');

        return self::SUCCESS;
    }
}
