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

use CallbackFilterIterator;
use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Ded\UtilityModule\Message;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\PlaceLocation;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\HousekeepingService;
use Fisharebest\Webtrees\Services\MapDataService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\User;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemOperator;
use stdClass;

use function array_diff_assoc;
use function array_key_exists;
use function array_merge;
use function exif_read_data;
use function fopen;
use function fclose;
use function filemtime;
use function fgetcsv;
use function fputcsv;
use function pathinfo;
use function rename;
use function sprintf;
use function strlen;
use function str_replace;
use function strtolower;
use function strtotime;

use const DIRECTORY_SEPARATOR;

/**
 * Class Functions
 * @package Drury\WebtreesModules\Utility;
 */
class Functions
{
    private const DEV_EXCLUDE_FOLDERS   = 'public.ckeditor|\.git|node_modules|\.vscode|data.(%s|cache';
    private const PROD_EXCLUDE_FOLDERS  = 'public.ckeditor|data.(%s|cache';
    private const FLAGS                 = FilesystemIterator::SKIP_DOTS | FilesystemIterator::KEY_AS_PATHNAME;

    /** @var array<mixed,int> */
    private $seqNo;

    /**
     * needed for when 2 media objects point to same file
     * @var array<int|string,(array|string)>
     */
    private $mapping;

    /** @var string */
    private $root;

    /** @var object */
    private $options;

    /** @var TreeService */
    private $tree_service;

    /** @var MapDataService */
    private $map_data_service;

    /** @var UserService */
    private $user_service;

    /** @var FilesystemOperator */
    private $data_filesystem;

    /** @var FilesystemOperator */
    private $root_filesystem;

    /**
     *
     * @param object $options
     * @return void
     */
    public function __construct(object $options)
    {
        $this->options          = $options;
        /** @var TreeService $tree_service */
        $tree_service     = Registry::container()->get(TreeService::class);
        /** @var MapDataService $map_data_service */
        $map_data_service = Registry::container()->get(MapDataService::class);
        /** @var UserService $user_service */
        $user_service     = Registry::container()->get(UserService::class);
        $this->tree_service     = $tree_service;
        $this->map_data_service = $map_data_service;
        $this->user_service     = $user_service;
        $this->data_filesystem  = Registry::filesystem()->data();
        $this->root_filesystem  = Registry::filesystem()->root();
        $this->root             = realpath(Webtrees::ROOT_DIR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Record cTime for each file (excluding those detailed in REGEX)
     * in the webtrees project and compare it against a similar cTime saved on
     * the previous run. Differences (changes, additions, deletions) are then
     * reported.
     *
     * @param string $mode
     * @return Message
     */
    public function cmpFilehash(string $mode): Message
    {
        $msg             = new Message(I18N::translate('Scan for changed files'));
        $exclude_folders = $mode === 'development' ? self::DEV_EXCLUDE_FOLDERS : self::PROD_EXCLUDE_FOLDERS;
        $path            = $this->root . '/data/' . $this->options->manifest;
        $newlist         = [];
        $oldlist         = [];

        $regex   = sprintf('/^((?!(' . $exclude_folders . '))).)*$/i', $this->options->manifest);
        $it      = new RecursiveDirectoryIterator($this->root, self::FLAGS);
        $rit     = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
        $fit     = new CallbackFilterIterator($rit, function ($current, $key, $iterator) use ($regex) {
            if ($current->isDir()) {
                return false;
            }

            return (bool) preg_match_all($regex, str_replace($this->root, '', $key));
        });

        //prepare new list
        /** @var \SplFileInfo $fileinf */
        foreach ($fit as $fileinf) {
            $key = str_replace($this->root, '', $fileinf->getPathname());
            // assert(is_string($key));
            $newlist[$key] = $fileinf->getCTime() ?: 0;
        }

        // read old list from file
        try {
            $fo = fopen($path, 'r');
            if (!is_resource($fo)) {
                throw new Exception();
            }

            $line = fgetcsv($fo, 1000);
            while (is_array($line) && count($line) === 2) {
                $file = (string) $line[0];
                $cType = (int) $line[1];
                $oldlist[$file] = $cType;
                $line = fgetcsv($fo, 1000);
            }
            fclose($fo);
        } catch (Exception $e) {
            $msg->append($e->getMessage());
        }
        // compute differences between old and new lists
        $diff_ab = array_diff_assoc($newlist, $oldlist);
        $diff_ba = array_diff_assoc($oldlist, $newlist);

        $res = array_merge($diff_ab, $diff_ba);

        foreach ($res as $file => $cTime) {
            /** @var int $cTime */
            /** @var string $file */
            if (array_key_exists($file, $diff_ab) && !array_key_exists($file, $diff_ba)) {
                $msg->append(I18N::translate('%s added: %s', $file, date('Y-m-d, H:i.s', $cTime)));
            } elseif (array_key_exists($file, $diff_ba) && !array_key_exists($file, $diff_ab)) {
                $msg->append(I18N::translate('%s deleted', $file));
            } else {
                $msg->append(I18N::translate('%s modified: %s', $file, date('Y-m-d, H:i.s', $cTime)));
            }
        }
        // Write new manifest only if changes detected
        if (!empty($res)) {
            try {
                $ff = fopen($path, 'w');
                if (!is_resource($ff)) {
                    throw new Exception();
                }
                foreach ($newlist as $relfn => $cTime) {
                    fputcsv($ff, [$relfn, $cTime]);
                }
                fclose($ff);
            } catch (Exception $e) {
                $msg->append($e->getMessage());
            }
        }

        $msg->footer(I18N::translate('Scan for changed files complete'));
        return $msg;
    }

    /**
     * @return Message
     */
    public function deleteUnusedLocations(): Message
    {
        $msg = new Message(I18N::translate('Remove unused locations'));

        $all_places = DB::table('places AS p0')
            ->leftJoin('places AS p1', 'p1.p_id', '=', 'p0.p_parent_id')
            ->leftJoin('places AS p2', 'p2.p_id', '=', 'p1.p_parent_id')
            ->leftJoin('places AS p3', 'p3.p_id', '=', 'p2.p_parent_id')
            ->leftJoin('places AS p4', 'p4.p_id', '=', 'p3.p_parent_id')
            ->leftJoin('places AS p5', 'p5.p_id', '=', 'p4.p_parent_id')
            ->leftJoin('places AS p6', 'p6.p_id', '=', 'p5.p_parent_id')
            ->leftJoin('places AS p7', 'p7.p_id', '=', 'p6.p_parent_id')
            ->leftJoin('places AS p8', 'p8.p_id', '=', 'p7.p_parent_id')
            ->select([
                'p0.p_place AS part_0',
                'p1.p_place AS part_1',
                'p2.p_place AS part_2',
                'p3.p_place AS part_3',
                'p4.p_place AS part_4',
                'p5.p_place AS part_5',
                'p6.p_place AS part_6',
                'p7.p_place AS part_7',
                'p8.p_place AS part_8',
            ])
            ->get()
            ->map(static function (stdClass $row): string {
                return implode(Gedcom::PLACE_SEPARATOR, array_filter((array) $row));
            });

        $all_locations = DB::table('place_location AS p0')
            ->leftJoin('place_location AS p1', 'p1.id', '=', 'p0.parent_id')
            ->leftJoin('place_location AS p2', 'p2.id', '=', 'p1.parent_id')
            ->leftJoin('place_location AS p3', 'p3.id', '=', 'p2.parent_id')
            ->leftJoin('place_location AS p4', 'p4.id', '=', 'p3.parent_id')
            ->leftJoin('place_location AS p5', 'p5.id', '=', 'p4.parent_id')
            ->leftJoin('place_location AS p6', 'p6.id', '=', 'p5.parent_id')
            ->leftJoin('place_location AS p7', 'p7.id', '=', 'p6.parent_id')
            ->leftJoin('place_location AS p8', 'p8.id', '=', 'p7.parent_id')
            ->select([
                'p0.place AS part_0',
                'p1.place AS part_1',
                'p2.place AS part_2',
                'p3.place AS part_3',
                'p4.place AS part_4',
                'p5.place AS part_5',
                'p6.place AS part_6',
                'p7.place AS part_7',
                'p8.place AS part_8',
            ])
            ->get()
            ->map(static function (stdClass $row): string {
                return implode(Gedcom::PLACE_SEPARATOR, array_filter((array) $row));
            });

        $unused = $all_locations->diff($all_places);
        foreach ($unused as $item) {
            $tmp = new PlaceLocation($item);
            assert(is_int($tmp->id()));
            //delete location
            $this->map_data_service->deleteRecursively($tmp->id());
            $msg->append(I18N::translate('%s deleted', $item));
        }
        $msg->footer(I18N::plural('%s location removed', '%s locations removed', $unused->count(), I18N::number($unused->count())));

        return $msg;
    }

    /**
     * Name format = prefix-yyyymmdd-nnnnnn.ext
     * yyyymmdd = best guess at date of image creation
     * nnnnnn = sequence number
     * ext = file extension (jpg, png etc.)
     *
     * @return Message
     */
    public function renameMedia(): Message
    {
        $msg = new Message(I18N::translate('Bulk media renaming'));

        $this->seqNo   = [];
        $this->mapping = [];
        $prefix_length = strlen($this->options->media_prefix);

        $select = sprintf('SUBSTR(multimedia_file_refn, %s ,8) as date, max(SUBSTR(multimedia_file_refn, %s, 6)) AS seq', $prefix_length + 2, $prefix_length + 11);
        $seqs = DB::table('media_file')
            ->where('multimedia_file_refn', 'like', $this->options->media_prefix . '%')
            ->selectRaw($select)
            ->groupBy('date')
            ->get();

        foreach ($seqs as $seq) {
            $this->seqNo[$seq->date] = $seq->seq;
        }

        $rows = DB::table('media_file')
            ->where('multimedia_file_refn', 'NOT LIKE', '%http%')
            ->where('multimedia_file_refn', 'NOT REGEXP', '^' . $this->options->media_prefix . '-[0-9]{8}-[0-9]{6}\..*$')
            ->get()
            ->sortBy('m_id', SORT_NATURAL);

        /** @var object $row */
        foreach ($rows as $row) {
            $new_name = $this->renameMediaItem($row);
            if ($new_name === false) {
                $msg->append(I18N::translate('Failed to rename file %s', $row->multimedia_file_refn));
            } else {
                $msg->append(I18N::translate('Renamed file from %s to %s', $row->multimedia_file_refn, $new_name));
            }
        }
        $msg->footer(I18N::plural('Bulk rename complete. %s item scanned', 'Bulk rename complete. %s items scanned', $rows->count(), I18N::number($rows->count())));

        return $msg;
    }

    /**
     *
     * @param object $row
     * @return string|false
     */
    private function renameMediaItem(object $row)
    {
        $tree         = $this->tree_service->find($row->m_file);
        $media        = Registry::mediaFactory()->make($row->m_id, $tree);
        assert($media instanceof Media);
        $media_file   = new MediaFile($media->gedcom(), $media);
        $old_filename = $this->root . DIRECTORY_SEPARATOR .
            Site::getPreference('INDEX_DIRECTORY') .
            $tree->getPreference('MEDIA_DIRECTORY') .
            $row->multimedia_file_refn; // filenames with full path
        $new_filename = null;
        $duplicate    = array_key_exists($row->multimedia_file_refn, $this->mapping);

        if (!$duplicate && !$media_file->fileExists()) {
            // If not duplicate but old file doesn't exist then can't rename
            return false;
        } elseif ($duplicate) {
            // if this filename has already been found in another media object
            // then it's new name is stored here
            $new_name = $this->mapping[$row->multimedia_file_refn];
        } else {
            // calculate new names
            $dt = false;

            try {
                $exif = exif_read_data($old_filename);
                assert($exif !== false);
                if (isset($exif['DateTime'])) {
                    $dt = strtotime($exif['DateTime']);
                } elseif (isset($exif['FileDateTime'])) {
                    $dt = $exif['FileDateTime'];
                }
            } catch (Exception $e) {
                // exif error
            }

            $bestdate = date('Ymd', $dt ? $dt : filemtime($old_filename));
            assert($bestdate !== false);
            if (!array_key_exists($bestdate, $this->seqNo)) {
                // are there other files with this date ?
                $this->seqNo[$bestdate] = 0;
            }
            $parts        = pathinfo($row->multimedia_file_refn);
            $newname      = sprintf('%s-%s-%06d.%s', $this->options->media_prefix, $bestdate, ++$this->seqNo[$bestdate], strtolower($parts['extension'] ?? ''));
            $new_name     = str_replace($parts['basename'], $newname, $row->multimedia_file_refn);
            $new_filename = str_replace($parts['basename'], $newname, $old_filename);

            $this->mapping[$row->multimedia_file_refn] = $new_name;
        }

        $result = true;
        if (!$duplicate && $new_filename !== null) {
            $result = rename($old_filename, $new_filename);
        }
        if ($result) {
            $gedcom = $media->gedcom();
            $newged = str_replace($row->multimedia_file_refn, $new_name, $gedcom);
            $update_change = (bool) $tree->getPreference('NO_UPDATE_CHAN');
            $media->updateRecord($newged, $update_change);

            return $new_name;
        }

        return false;
    }

    /**
     * @return Message
     */
    public function userActivity(): Message
    {
        $msg = new Message(I18N::translate('User Activity'));

        $rows = DB::table('log')
            ->where('log_type', '=', 'auth')
            ->where('log_time', '>', Registry::timestampFactory()->now()->subtractDays(1)->toDateTimeString())
            ->get();

        foreach ($rows as $row) {
            $log_time = Registry::timestampFactory()->fromString($row->log_time)->toDateTimeString();
            $msg->append(I18N::translate('%s at %s from %s', $row->log_message, $log_time, $row->ip_address));
        }
        $msg->footer(I18N::plural('User activity complete - %s action found', 'User activity complete - %s actions found', $rows->count(), I18N::number($rows->count())));

        return $msg;
    }

    /**
     *
     * @return Message
     */
    public function housekeeping(): Message
    {
        $msg = new Message(I18N::translate('Housekeeping'));
        $housekeeping_service = new HousekeepingService();

        $housekeeping_service->deleteOldFiles($this->data_filesystem, 'thumbnail-cache', (int) $this->options->timeout_cache);
        $msg->append(I18N::translate('Processed cache'));

        $housekeeping_service->deleteOldFiles($this->root_filesystem, 'data/tmp', (int) $this->options->timeout_tmp);
        $msg->append(I18N::translate('Processed temporary files'));

        $housekeeping_service->deleteOldLogs((int) $this->options->timeout_logs);
        $msg->append(I18N::translate('Processed old logs'));

        $housekeeping_service->deleteOldSessions((int) $this->options->timeout_session);
        $msg->append(I18N::translate('Processed old sessions'));

        $msg->footer(I18N::translate('Housekeeping complete'));

        return $msg;
    }

    /**
     *
     * @return Message
     */
    public function reorderMedia(): Message
    {
        $msg = new Message(I18N::translate('Reorder Media'));

        $indis = DB::table('link')
            ->where('l_type', '=', '_WT_OBJE_SORT')
            ->distinct()
            ->select('l_from', 'l_file')
            ->get();

        $trees = [];
        foreach ($indis as $indi) {
            if (!array_key_exists($indi->l_file, $trees)) {
                $trees[$indi->l_file] = $this->tree_service->find($indi->l_file);
            }
            $tree       = $trees[$indi->l_file];
            $individual = Registry::IndividualFactory()->make($indi->l_from, $tree);
            assert($individual instanceof Individual);
            $msg->append(I18N::translate('Ordering media for %s (%s)', $individual->fullName(), $indi->l_from));
            $gedcom = $individual->gedcom();
            preg_match_all('/1 _WT_OBJE_SORT @(.*)@/', $gedcom, $sort_lines);
            preg_match_all('/1 OBJE @(.*)@/', $gedcom, $media_lines);
            $discard = array_diff($sort_lines[1], $media_lines[1]); // not at level 1

            /** @var object $media */
            $media = DB::table('link')
                ->join('media', 'l_to', '=', 'm_id')
                ->join('individuals', 'l_from', '=', 'i_id')
                ->where('l_from', '=', $indi->l_from)
                ->where('m_gedcom', 'like', '%_PRIM Y%')
                ->get('m_id')
                ->first();

            if ((bool) $this->options->del_custom_tags) {
                $regex = '/1 (_WT_)?OBJE(_SORT)?.*\n?/';
            } else {
                $regex = '/1 OBJE.*\n?/';
            }
            $stripped_ged = preg_replace($regex, '', $gedcom);
            assert(is_string($stripped_ged));
            $new_ged = trim($stripped_ged) . "\n";

            $lines = array_unique(array_merge([$media->m_id ?? ''], array_diff($sort_lines[1], $discard)));
            foreach ($lines as $line) {
                $new_ged .= "1 OBJE @" . $line . "@\n";
            }
            $update_change = (bool) $tree->getPreference('NO_UPDATE_CHAN');
            $individual->updateRecord($new_ged, $update_change);
        }

        $updated = 0;
        if ((bool) $this->options->del_custom_tags) {
            $updated = DB::table('media')
                ->where('m_gedcom', 'like', '%1 _PRIM%')
                ->update(['m_gedcom' => new Expression("REPLACE(REPLACE(`m_gedcom`, '1 _PRIM Y\n', ''), '1 _PRIM N\n', '')")]);
        }

        $txt = I18N::plural('%s Individual updated', '%s Individuals updated', $indis->count(), I18N::number($indis->count()));
        $txt .= ', ' . I18N::plural('%s Media object updated', '%s Media objects updated', $updated, I18N::number($updated));
        $msg->footer(I18N::translate('Reorder media complete'));
        $msg->footer($txt);

        return $msg;
    }

    /**
     *
     * @return Message
     */
    public function editActivity(): Message
    {
        $msg = new Message(I18N::translate('Gedcom changes in the last 24 hours'));

        $records = DB::table('log')
            ->select('log_time', 'log_message', 'user_id', 'gedcom_id')
            ->where('log_type', '=', 'edit')
            ->where('log_time', '>', Registry::timestampFactory()->now()->subtractDays(1)->toDateTimeString())
            ->orderBy('log_time', 'desc')
            ->get();

        foreach ($records as $record) {
            $log_time = Registry::timestampFactory()->fromString($record->log_time)->toDateTimeString();
            $editor = $this->user_service->find((int) $record->user_id);
            assert($editor instanceof User);
            list($action,, $xref) = explode(' ', $record->log_message);
            $edited = Registry::gedcomRecordFactory()->make((string) $xref, $this->tree_service->find($record->gedcom_id));
            if ($edited instanceof GedcomRecord) {
                $msg->append(I18N::translate('%s %s (%s) on %s by %s', $action, $xref, $edited->fullName(), $log_time, $editor->realName()));
            } else {
                $msg->append(I18N::translate('%s %s on %s by %s', $action, $xref, $log_time, $editor->realName()));
            }
        }

        $msg->footer(I18N::plural('There was %s edit in the last 24 hours', 'There were %s edits in the last 24 hours', count($records), I18N::number(count($records))));

        return $msg;
    }

    /**
     *
     * @return Message
     */
    public function noteFormat(): Message
    {
        $msg = new Message(I18N::translate('Update Census assistant shared note format'));

        $records = DB::table('other')
        ->select('o_id', 'o_file')
        ->where('o_type', '=', 'note')
        //->where('o_gedcom', 'like', '%census transcript%')
        ->get()
        ->sortBy('o_id', SORT_NATURAL);

        foreach ($records as $record) {
            $msg->append(I18N::translate('Processing note %s', $record->o_id));

            $tree          = $this->tree_service->find($record->o_file);
            $note          = Registry::noteFactory()->make($record->o_id, $tree);
            assert($note instanceof Note);
            $parts         = preg_split('/1 CHAN\n/', $note->gedcom());
            assert(is_array($parts));
            $change_record = '';

            foreach ($parts as $key => $part) {
                if (str_starts_with($part, '2 DATE')) {
                    $change_record = "1 CHAN\n" . $part;
                    unset($parts[$key]);
                    break;
                }
            }
            $lines  = array_filter(explode("\n", implode("\n", $parts)));
            foreach ($lines as &$line) {
                if ($line === "1 CONT" || str_starts_with($line, "1 CONT |") || str_starts_with($line, "1 CONC")) {
                    continue;
                } elseif (strpos($line, '|') === false) {
                    $line = trim($line) . '  ';
                } else {
                    $line = str_replace("1 CONT ", "1 CONT | ", $line) . " |";
                }
            }

            if (end($lines) !== '1 CONT') {
                array_push($lines, '1 CONT');
            }
            $gedcom = implode("\n", $lines);
            $update_change = (bool) $tree->getPreference('NO_UPDATE_CHAN');
            if (!$update_change) {
                $gedcom .= "\n" . $change_record;
            }
            $note->updateRecord($gedcom, $update_change);
        }

        $msg->footer(I18N::translate('Note formatting complete'));

        return $msg;
    }

    /**
     *
     * @return Message
     */
    public function errors(): Message
    {
        $msg = new Message(I18N::translate('Errors in the last 24 hours'));

        $records = DB::table('log')
            ->select('log_time', 'log_message', 'ip_address')
            ->where('log_type', '=', 'error')
            ->where('log_time', '>=', Registry::timestampFactory()->now()->subtractDays(1)->toDateTimeString())
            ->orderBy('log_time', 'desc')
            ->get();

        foreach ($records as $record) {
            $log_time      = Registry::timestampFactory()->fromString($record->log_time)->toDateTimeString();
            $error_message = Str::limit(strip_tags($record->log_message), 256);
            $msg->append(I18N::translate('On %s from %s: %s', $log_time, $record->ip_address, $error_message));
        }

        $msg->footer(I18N::plural('There was %s error in the last 24 hours', 'There were %s errors in the last 24 hours', count($records), I18N::number(count($records))));

        return $msg;
    }

}
