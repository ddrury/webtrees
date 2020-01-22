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

namespace Fisharebest\Webtrees\Schema;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;

/**
 * Upgrade the database schema from version 44 to version 45.
 */
class Migration44 implements MigrationInterface
{
    /**
     * Upgrade to to the next version
     *
     * @return void
     */
    public function upgrade(): void
    {
        // update `wt_site_setting`
        // set `setting_name` = 'xxx', `setting_value` = if(`setting_value` = 'osm','1', '0')
        // WHERE `setting_name` = 'map-provider'

        DB::table('site_setting')
            ->where('setting_name', '=', 'map-provider')
             ->update([
                 'setting_name'  => 'SHOW_MAP_PLACE_HIERARCHY_LIST',
                 'setting_value' => new Expression("IF('setting_value' = 'osm', '1', '0')")
         ]);
    }
}
