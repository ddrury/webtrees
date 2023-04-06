<?php

namespace Ded\UtilityModule;

// webtrees: Web based Family History software
// Copyright (C) 2022 webtrees development team.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
// Module developed by David Drury

//use Fisharebest\Webtrees\Carbon;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;

/**
 * Class Message
 * @package Drury\WebtreesModules\Utility;
 */
class Message
{
    /** @var ModuleCustomInterface */
    private $module;
    /** @var string */
    private $title;
    /** @var array<int,string> */
    private $content = [];
    /** @var array<int,string> */
    private $footer  = [];

    /**
     *
     * @param string $title
     * @return void
     */
    public function __construct(string $title = '')
    {
        /** @var ModuleService $module_service */
        $module_service = Registry::container()->get(ModuleService::class);
        $module         = $module_service->findByName('_utility_');
        assert($module instanceof ModuleCustomInterface);
        $this->module = $module;
        $this->title  = $title;
    }

    /**
     * @param string $txt
     */
    public function append(string $txt): void
    {
        $this->content[] = $txt;
    }

    /**
     *
     * @param string $footer
     * @return void
     */
    public function footer(string $footer): void
    {
        $this->footer[] = $footer;
    }

    /**
     * @return string
     */
    public function text(): string
    {
        $date   = Registry::timestampFactory()->now()->format('jS F Y - H:i:s');
        $footer = $this->footer ?: [I18N::translate('Routine complete')];

        return $this->title . "\n\n" . implode("\n", $this->content) . "\n\n" . $date . "\n" . implode("\n", $footer);
    }

    /**
     *
     * @return string
     */
    public function html(): string
    {
        return view($this->module->name() . '::admin/message', [
            'title'  => $this->title,
            'body'   => $this->content,
            'footer' => $this->footer ?: [I18N::translate('Routine complete')],
            'date'   => Registry::timestampFactory()->now()->format('jS F Y - H:i:s'),
        ]);
    }
}
