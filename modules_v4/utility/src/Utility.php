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

use Composer\Autoload\ClassLoader;
use Ded\UtilityModule\Functions;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\Exceptions\HttpBadRequestException;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\NoReplyUser;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\SiteUser;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

use function explode;
use function file_exists;
use function ini_restore;
use function set_time_limit;

class Utility extends AbstractModule implements ModuleCustomInterface, ModuleGlobalInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleGlobalTrait;
    use ModuleConfigTrait;

    private const CUSTOM_VERSION = '2.1.0';

    private const CUSTOM_WEBSITE = 'https://github.com/ddrury/webtrees/issues';

    private const SETTING_CRON_KEY_NAME = 'cron_key';

    private const MODE = 'production';

    /** @var string */
    private $language_path;

    /** @var object */
    private $options;

    /** @var \Fisharebest\Webtrees\Services\UserService */
    public $user_service;

    /** @var \Fisharebest\Webtrees\Services\EmailService */
    public $email_service;

    /**
     *
     * @param UserService $user_service
     * @param EmailService $email_service
     *
     * @return void
     */
    public function __construct(UserService $user_service,  EmailService $email_service)
    {
        $this->user_service  = $user_service;
        $this->email_service = $email_service;
        $this->language_path = $this->resourcesFolder() . 'lang/';
    }

    /**
     *
     * @return void
     */
    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
        $loader = new ClassLoader();
        $loader->addPsr4('Ded\\UtilityModule\\', __DIR__);
        $loader->register();

        $this->options = new stdClass();
        $options = [
            'manifest'        => 'manifest',
            'email_title'     => 'webtrees utility report',
            'media_prefix'    => 'DFH_Media',
            'del_custom_tags' => '0',
            'timeout_tmp'     => (string) 60 * 60 * 24,
            'timeout_cache'   => (string) 60 * 60 * 24 * 30,
            'timeout_logs'    => (string) 60 * 60 * 24 * 30,
            'timeout_session' => (string) 60 * 60 * 24 * 30,
            'cron_command'    => 'curl -s -o /dev/null -S',
        ];
        foreach ($options as $key => $default) {
            $this->options->{$key} = $this->getPreference((string) $key, (string) $default);
        }
    }

    /**
     *
     * @return string
     */
    public function title(): string
    {
        return 'Utilities';
    }

    /**
     *
     * @return string
     */
    public function description(): string
    {
        return 'Helpful utilities for webtrees2.';
    }

    /**
     *
     * @return string
     */
    public function customModuleAuthorName(): string
    {
        return 'David Drury';
    }

    /**
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }

    /**
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/xddrury/webtrees/2.1_local_mods/modules_v4/utility/latest-version.txt';
    }

    /**
     *
     * @param string $language
     * @return array<string,string>
     */
    public function customTranslations(string $language): array
    {
        $file = $this->language_path . $language . '.mo';
        if (file_exists($file)) {
            $lang = new Translation($file);
        } else {
            $lang = new Translation($this->language_path . 'en-US.mo');
        }
        return $lang->asArray();
    }

    /**
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    /**
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getAssetAction(ServerRequestInterface $request): ResponseInterface
    {
        return response('No Content', StatusCodeInterface::STATUS_NO_CONTENT);
    }

    /**
     *
     * @return string
     */
    public function cronKey(): string
    {
        $key = $this->getPreference(self::SETTING_CRON_KEY_NAME);

        if (empty($key)) {
            $key = Str::random();
            $this->setPreference(self::SETTING_CRON_KEY_NAME, $key);
        }

        return $key;
    }

    /**
     *
     * @return array<string,string>
     */
    public function routines()
    {
        return [
            'manifest'             => I18N::translate('Create a catalogue of changed files'),
            'del_unused_locations' => I18N::translate('Delete unused locations'),
            'list_edits'           => I18N::translate('Gedcom changes in the last 24 hours'),
            'housekeeping'         => I18N::translate('Housekeeping'),
            'rename_media'         => I18N::translate('Rename media'),
            'user_activity'        => I18N::translate('Show user activity in the last 24 hours'),
            'reorder_media'        => I18N::translate('Sort Media like webtrees 1.7'),
            'census_note_format'   => I18N::translate('Update census note format'),
        ];
    }

    /**
     *
     * @return object
     */
    public function options()
    {
        return $this->options;
    }

    /**
     * Show a form to manage administrative functions
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::admin/config', [
            'title'    => $this->title(),
            'module'   => $this,
            'timeouts' => [
                60 * 60           => I18N::translate('One hour'),
                60 * 60 * 12      => I18N::translate('Twelve hours'),
                60 * 60 * 24      => I18N::translate('One day'),
                60 * 60 * 24 * 7  => I18N::translate('One week'),
                60 * 60 * 24 * 30 => I18N::translate('One month'),
                60 * 60 * 24 * 60 => I18N::translate('Two months'),
                60 * 60 * 24 * 90 => I18N::translate('Three months'),
            ],
        ]);
    }

    /**
     * Save the admin page config parameters
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();
        foreach ($params as $key => $value) {
            $this->setPreference($key, $value);
        }
        $message = I18N::translate('The options for the module “%s” have been updated.', $this->title());
        FlashMessages::addMessage($message, 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * Run routines. Called from the run actions
     *
     * @param string $routines
     * @return object
     * @throws HttpBadRequestException
     */
    private function dispatcher(string $routines): object
    {
        $routines  = explode(',', $routines);
        $functions = new Functions($this->options);

        $html = [];
        $text = [];

        set_time_limit(0);
        foreach ($routines as $routine) {
            switch ($routine) {
                case 'manifest':
                    $msg = $functions->cmpFilehash(self::MODE);
                    break;
                case 'user_activity':
                    $msg = $functions->userActivity();
                    break;
                case 'rename_media':
                    $msg = $functions->renameMedia();
                    break;
                case 'del_unused_locations':
                    $msg = $functions->deleteUnusedLocations();
                    break;
                case 'housekeeping':
                    $msg = $functions->housekeeping();
                    break;
                case 'reorder_media':
                    $msg = $functions->reorderMedia();
                    break;
                case 'list_edits':
                    $msg = $functions->editActivity();
                    break;
                case 'census_note_format':
                    $msg = $functions->noteFormat();
                    break;
                default:
                    throw new HttpBadRequestException('Unknown function ' . $routine);
            }

            $html[] = $msg->html();
            $text[] = $msg->text();
        }
        ini_restore('max_execution_time');

        return (object) [
            'html' => $html,
            'text' => $text
        ];
    }

    /**
     * Called from a cron task
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getRunAction(ServerRequestInterface $request): ResponseInterface
    {
        $mute     = Validator::queryParams($request)->boolean('mute', false);
        $routines = Validator::queryParams($request)->string('routines', '');
        $user     = Validator::serverParams($request)->string('PHP_AUTH_USER', '');
        $pw       = Validator::serverParams($request)->string('PHP_AUTH_PW', '');

        if ($user === 'cron' && $pw === $this->cronKey()) {
            $msgs = $this->dispatcher($routines);
        } else {
            throw new HttpAccessDeniedException();
        }

        if (!(bool) $mute) {
            $site_user = new SiteUser();
            $reply_to  = new NoReplyUser();
            foreach ($this->user_service->administrators() as $admin) {
                $sent = $this->email_service->send(
                    $site_user,
                    $admin,
                    $reply_to,
                    $this->options->email_title,
                    implode("\n", $msgs->text),
                    implode("<hr>", $msgs->html),
                );
                if (!$sent) {
                    return response('Utility results email failed', StatusCodeInterface::STATUS_SERVICE_UNAVAILABLE);
                }
            }
        }

        return response('Utility routines completed', StatusCodeInterface::STATUS_OK);
    }

    /**
     * Called fron the admin/run page
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postRunAction(ServerRequestInterface $request): ResponseInterface
    {
        $routines = Validator::parsedBody($request)->string('routines', '');
        $msg      = $this->dispatcher($routines);

        return response($msg->html[0], StatusCodeInterface::STATUS_OK);
    }
};
