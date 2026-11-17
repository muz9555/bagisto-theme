<?php

namespace Webkul\MXTechnology\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Repositories\ChannelRepository;

class InstallTheme extends Command
{
    /**
     * The installation command and optional channel activation.
     */
    protected $signature = 'mx-theme:install {--activate : Select the theme for a channel} {--channel=default : Channel code}';

    /**
     * The command description.
     */
    protected $description = 'Publish MX Technology assets and optionally select it for a channel without changing catalog data';

    /**
     * Publish assets and preserve existing merchant configuration.
     */
    public function handle(ChannelRepository $channels): int
    {
        if (! is_file(public_path(trim(config('themes.shop.default.vite.build_directory', 'themes/shop/default/build'), '/').'/manifest.json'))) {
            $this->error('Build the original Shop assets before installing this child theme.');

            return self::FAILURE;
        }

        $channel = $this->option('activate') ? $channels->findOneWhere(['code' => $this->option('channel')]) : null;

        if ($this->option('activate') && ! $channel) {
            $this->error('The requested channel does not exist.');

            return self::FAILURE;
        }

        $this->call('vendor:publish', ['--tag' => 'mx-technology-assets', '--force' => true]);
        $this->call('vendor:publish', ['--tag' => 'mx-technology-config']);

        if ($this->option('activate')) {
            DB::transaction(fn () => $channels->update([
                'theme' => 'mx-technology',
                'locales' => $channel->locales->pluck('id')->all(),
                'currencies' => $channel->currencies->pluck('id')->all(),
                'inventory_sources' => $channel->inventory_sources->pluck('id')->all(),
                'logo' => [$channel->logo],
                'favicon' => [$channel->favicon],
            ], $channel->id));
            $this->info('MX Technology selected for channel '.$channel->code.'.');
        }

        $this->call('optimize:clear');
        if ($this->getApplication()->has('responsecache:clear')) {
            $this->call('responsecache:clear');
        }
        $this->info('Theme installed. Product records, prices, URLs and payment configuration were preserved.');

        return self::SUCCESS;
    }
}
