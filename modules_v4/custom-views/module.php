<?php

declare(strict_types=1);

namespace Drury\WebtreesModules\Modules;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\View;

return new class () extends AbstractModule implements ModuleCustomInterface, ModuleGlobalInterface {
    use ModuleCustomTrait;
    use ModuleGlobalTrait;

    private const CUSTOM_VERSION = '2.0.0';

    private const CUSTOM_WEBSITE = 'https://github.com/ddrury/webtrees/issues';

    /**
     * Bootstrap the module
     * @return void
     */
    public function boot(): void
    {
        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        // Replace an existing view with our own version.
        // change favicon links
        View::registerCustomView('::layouts/default', $this->name() . '::layouts/default');
        // remove carousel
        View::registerCustomView('::individual-page-images', $this->name() . '::individual-page-images');
        // Remove "show more" button
        View::registerCustomView('::lists/anniversaries-list', $this->name() . '::lists/anniversaries-list');
        // Remove "show more" button & show real name
        View::registerCustomView('::modules/recent_changes/changes-list', $this->name() . '::modules/recent_changes/changes-list');
        // Remove username
        View::registerCustomView('::modules/user-messages/user-messages', $this->name() . '::modules/user-messages/user-messages');
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Custom views';
    }

    /**
     * @return string
     */
    public function description(): string
    {
        return 'Customized views';
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string
    {
        return 'Dave Drury';
    }

    /**
     * The version of this module.
     *
     * @return string  e.g. '1.2.3'
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/ddrury/webtrees/2.1_local_mods/modules_v4/custom-views/latest-version.txt';
    }

    /**
     * Where to get support for this module.  Perhaps a github respository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * Add an additional stylesheet to the header
     *
     * @return string
     */
    public function headContent(): string
    {
        $url = $this->assetUrl('css/custom.min.css');

        return '<link rel="stylesheet" href="' . e($url) . '">';
    }

    /**
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return array<string>
     */
    public function customTranslations(string $language): array
    {
        switch ($language) {
            case 'en-AU':
            case 'en-GB':
            case 'en-US':
                // Note the special characters used in plural and context-sensitive translations.
                return [
                    'Welcome to this genealogy website' => 'Welcome to the Drury Family history website',
                ];
            case 'some-other-language':
            default:
                return [];
        }
    }
};
