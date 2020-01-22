<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2020 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Registry;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;
use Transliterator;

/**
 * Class MapProviderModule.
 */
class MapProviderModule extends AbstractModule implements ModuleMapProviderInterface, ModuleConfigInterface
{
    use ModuleConfigTrait;

    /** $data stdClass[] */
    private $data = [];

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return  'Map providers';
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        return I18N::translate('Manage map providers');
    }

    /**
     * Should this module be enabled when it is first installed?
     *
     * @return bool
     */
    public function isEnabledByDefault(): bool
    {
        return true;
    }

    /**
     * Show a form to edit the map providers.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse('modules/map-provider/map-provider', [
            'title' => $this->title(),
            'data'     => (object) $this->configData(),
        ]);
    }

    /**
     * Save the map provider settings.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $settings   = (array) $request->getParsedBody();
        $this->setPreference('default_style', ($settings['PROVIDER'] ?? '') . '.' . ($settings['STYLE'] ?? ''));

        unset($settings['PROVIDER']);
        unset($settings['STYLE']);

        // The remaining settings are all module settings
        foreach ($settings as $key => $value) {
            $this->setPreference(substr($key, 0, 32), $value); // database limit is 32
        }

        return redirect($this->getConfigLink());
    }

    public function providerIsEnabled($provider): bool
    {
        // Set openstreetmap enabled by default
        return (bool) $this->getPreference($provider->internal_name . '-enabled', $provider->internal_name === 'openstreetmap' ? '1' : '0');
    }

    /**
     *
     * @param bool $clear_cache
     * @return array
     */
    public function getProviderData(bool $clear_cache = false): array
    {
        if ($clear_cache) {
            Registry::cache()->array()->forget('map_provider-data');
        }
        return Registry::cache()->array()->remember('map_provider-data', function () {
            $files = glob($this->resourcesFolder() . 'map_providers/*.json');
            foreach ($files as $file) {
                $provider = json_decode(file_get_contents($file));
                if ($provider instanceof stdClass) {
                    $provider->internal_name = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $provider->title));
                    $this->data[] = $provider;
                } else {
                    Log::addErrorLog('Map provider file invalid data: ' . $file);
                }
            }

            return $this->data;
        }, 86400);
    }

    /**
     *
     * @param mixed $provider
     * @return array[][<string><string>]
     */
    public function styleData($provider): array
    {
        return Registry::cache()->array()->remember('map_provider-' . $provider, function () use ($provider) {
            // Get the static data
            $pvr_arr = array_filter($this->getProviderData(), function ($item) use ($provider) {
                return $item->internal_name === $provider;
            });

            $pvr_obj = array_pop($pvr_arr);
            $children = [];

            foreach ($pvr_obj->styles as $style) {
                $style_data = array_merge_recursive((array) $style, (array) $pvr_obj->common);
                // now add in the user supplied parameters (eg api_key)
                foreach ($pvr_obj->user_parameters as $parameter) {
                    $style_data['parameters'][$parameter] = $this->getPreference(substr($provider . '-' . $parameter, 0, 32));
                }
                $children[] = [
                    'label' => '<span class="px-1 text-info">' . $style->title . '</span>',
                    'layer' => $style_data
                ];
            }

            return $children;
        }, 86400);
    }

    /**
     *
     * @return array<string<mixed>
     * @throws InvalidArgumentException
     */
    private function configData(): array
    {
        $config_data     = [];
        $providers       = [];
        $styles          = [];
        $selected_styles = [];

        $transliterator = Transliterator::create('Any-Latin;Latin-ASCII');
        $pref = $this->getPreference('default_style', 'openstreetmap.mapnik');
        list($default_provider, $default_style) = explode('.', $pref);

        foreach ($this->getProviderData(true) as $datum) {
            $matched_provider = $datum->internal_name === $default_provider;
            $providers[$datum->internal_name] = $datum->title;

            foreach ($datum->styles as $style) {
                $key = $transliterator->transliterate($style->title);
                $key = strtolower(preg_replace('/[\W]/', '', $key));
                $styles[$datum->internal_name][$key] = $style->title;
                if ($matched_provider) {
                    $selected_styles[$key] = $style->title;
                }
            }
            $parameters = [];
            foreach ($datum->user_parameters as $display_name => $value) {
                $key = substr($datum->internal_name . '-' . $value, 0, 32);
                $parameters[$value] = (object) [
                    'name'  => $display_name,
                    'value' => $this->getPreference($key)
                ];
            }
            $config_data[] = (object) [
                'title'      => $datum->title,
                'name'       => $datum->internal_name,
                'parameters' => $parameters,
                'enabled'    => (int) $this->providerIsEnabled($datum),
                'help_url'   => $datum->help,
            ];
        }

        return [
            'provider_names'   => $providers,
            'default_provider' => $default_provider,
            'default_style'    => $default_style,
            'selected_styles'  => $selected_styles,
            'all_styles'       => $styles,
            'config_data'      => $config_data
        ];
    }
}
