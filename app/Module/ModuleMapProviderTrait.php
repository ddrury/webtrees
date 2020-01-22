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

use Fisharebest\Webtrees\Services\ModuleService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Transliterator;

/**
 * Trait ModuleMapProviderTrait
 */
trait ModuleMapProviderTrait
{
    /**
     * Provide mapping details to modules that draw a map
     *
     * @return array
     * @throws BindingResolutionException
     */
    public function providerDetails(): array
    {
        $layers    = [];    // connection data for each provider/style
        $def_mod   = null;  // pointer to initial layer provider
        $def_style = null;  // pointer to initial layer style
        $module    = app(ModuleService::class)->findByName('map-provider');
        if ($module instanceof MapProviderModule) {
            $transliterator = Transliterator::create('Any-Latin;Latin-ASCII');
            list($default_provider, $default_style) = explode('.', $module->getPreference('default_style', 'openstreetmap.mapnik'));
            $data      = $module->getProviderData();
            $styles    = [];
            $midx      = 0;

            foreach ($data as $provider) {
                if (!$module->providerIsEnabled($provider)) {
                    continue;
                }
                $layers[] = [
                    'label'     => "<span class='text-primary font-weight-bold'>" . $provider->title . "</span>",
                    'collapsed' => 'true',
                    'children'  => $module->styleData($provider->internal_name),
                ];
                if ($def_mod === null && $provider->internal_name === $default_provider) {
                    $styles  = $provider->styles;
                    $def_mod = $midx;
                }
                $midx++;
            }

            foreach ($styles as $ndx => $style) {
                $key = $transliterator->transliterate($style->title);
                $key = strtolower(preg_replace('/[\W]/', '', $key));
                if (strcasecmp($default_style, $key) === 0) {
                    $def_style = $ndx;
                    break;
                }
            }
        }

        return [
            'layers'  => $layers,
            'default' => ['module' => $def_mod ?? 0, 'style' => $def_style ?? 0],
        ];
    }

    /**
     * Mapping is available if the module is enabled and at least one map provider has been enabled and configured
     *
     * @return bool
     * @throws BindingResolutionException
     */
    public function mapsAvailable(): bool
    {
        $module = app(ModuleService::class)->findByName('map-provider');
        if ($module instanceof MapProviderModule) {
            $data = $module->getProviderData();
            foreach ($data as $datum) {
                if ($module->providerIsEnabled($datum)) {
                    return true;
                }
            }
        }

        return false;
    }
}
