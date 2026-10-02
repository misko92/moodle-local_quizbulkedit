<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_quizbulkedit\local;

use context_module;
use quizaccess_seb\seb_quiz_settings;
use quizaccess_seb\settings_provider;
use stdClass;

/**
 * Safe Exam Browser column: turn SEB off, or copy another quiz's whole SEB setup.
 *
 * SEB settings are stored by quizaccess_seb in its own table, with many options,
 * templates and uploaded config files, so the column doesn't edit them one by
 * one. The same rules as the quiz settings form apply: the settings are locked
 * once a quiz has attempts, and each SEB mode needs its own capability.
 *
 * @package    local_quizbulkedit
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seb {
    /** @var string The column/field name. */
    public const FIELD = 'seb';

    /**
     * Whether the Safe Exam Browser access rule is installed and enabled.
     *
     * @return bool
     */
    public static function available(): bool {
        if (!class_exists(seb_quiz_settings::class)) {
            return false;
        }
        $plugin = \core_plugin_manager::instance()->get_plugin_info('quizaccess_seb');
        return $plugin && $plugin->is_enabled() !== false;
    }

    /**
     * Add seb (mode), sebtemplate, seblocked and sebcanedit to each quiz record.
     *
     * @param array $quizzes from updater::get_quizzes()
     */
    public static function load(array $quizzes): void {
        global $DB;
        foreach ($quizzes as ['quiz' => $quiz]) {
            $quiz->seb = 0;
            $quiz->sebtemplate = '';
            $quiz->seblocked = false;
            $quiz->sebcanedit = false;
        }
        if (!$quizzes || !self::available()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($quizzes));
        $settings = $DB->get_records_select(
            seb_quiz_settings::TABLE,
            "quizid $insql",
            $params,
            '',
            'quizid, requiresafeexambrowser, templateid'
        );
        $templates = $DB->get_records_menu('quizaccess_seb_template', null, '', 'id, name');
        $attempted = array_flip($DB->get_fieldset_select('quiz_attempts', 'DISTINCT quiz', "preview = 0 AND quiz $insql", $params));

        foreach ($quizzes as $quizid => ['cm' => $cm, 'quiz' => $quiz]) {
            if (isset($settings[$quizid])) {
                $quiz->seb = (int) $settings[$quizid]->requiresafeexambrowser;
                $quiz->sebtemplate = $templates[$settings[$quizid]->templateid] ?? '';
            }
            $quiz->seblocked = isset($attempted[$quizid]);
            $quiz->sebcanedit = settings_provider::can_configure_seb($cm->context) &&
                self::can_use_mode($cm->context, $quiz->seb);
        }
    }

    /**
     * A short description of a quiz's SEB setup, e.g. "Yes – Use an existing template: Exams".
     *
     * @param stdClass $quiz with seb and sebtemplate from load()
     * @return string
     */
    public static function describe(stdClass $quiz): string {
        switch ($quiz->seb) {
            case settings_provider::USE_SEB_NO:
                return get_string('no');
            case settings_provider::USE_SEB_CONFIG_MANUALLY:
                return get_string('seb_use_manually', 'quizaccess_seb');
            case settings_provider::USE_SEB_TEMPLATE:
                return get_string('seb_use_template', 'quizaccess_seb') .
                    ($quiz->sebtemplate === '' ? '' : ': ' . format_string($quiz->sebtemplate));
            case settings_provider::USE_SEB_UPLOAD_CONFIG:
                return get_string('seb_use_upload', 'quizaccess_seb');
            default:
                return get_string('seb_use_client', 'quizaccess_seb');
        }
    }

    /**
     * Whether the user may set up SEB in a given mode, as the quiz settings form checks.
     *
     * @param \context $context
     * @param int $mode one of the settings_provider::USE_SEB_ constants
     * @return bool
     */
    protected static function can_use_mode(\context $context, int $mode): bool {
        switch ($mode) {
            case settings_provider::USE_SEB_NO:
                return true;
            case settings_provider::USE_SEB_CONFIG_MANUALLY:
                return settings_provider::can_configure_manually($context);
            case settings_provider::USE_SEB_TEMPLATE:
                return settings_provider::can_use_seb_template($context);
            case settings_provider::USE_SEB_UPLOAD_CONFIG:
                return settings_provider::can_upload_seb_file($context);
            default:
                return settings_provider::can_use_seb_client_config($context);
        }
    }

    /**
     * Check a submitted value: '0' to turn SEB off, or 'q<quiz id>' to copy that quiz's setup.
     *
     * @param int $quizid the quiz being changed
     * @param string $input
     * @param array $quizzes from updater::get_quizzes(), after load()
     * @return array [value or null, error message or null]
     */
    public static function validate(int $quizid, string $input, array $quizzes): array {
        ['cm' => $cm, 'quiz' => $quiz] = $quizzes[$quizid];
        if ($quiz->seblocked) {
            return [null, get_string('errorseblocked', 'local_quizbulkedit')];
        }
        if (!$quiz->sebcanedit) {
            return [null, get_string('errorsebpermission', 'local_quizbulkedit')];
        }
        if ($input === '0') {
            return ['0', null];
        }
        $sourceid = preg_match('/^q(\d+)$/', $input, $m) ? (int) $m[1] : 0;
        $source = $quizzes[$sourceid]['quiz'] ?? null;
        if (!$source || $sourceid === $quizid || !$source->seb) {
            return [null, get_string('errorsebsource', 'local_quizbulkedit')];
        }
        if (!self::can_use_mode($cm->context, $source->seb)) {
            return [null, get_string('errorsebpermission', 'local_quizbulkedit')];
        }
        return ['q' . $sourceid, null];
    }

    /**
     * Apply a value from validate() to a quiz.
     *
     * @param stdClass $quiz
     * @param stdClass $cm
     * @param string $value '0' or 'q<quiz id>'
     */
    public static function apply(stdClass $quiz, stdClass $cm, string $value): void {
        global $DB;
        $context = context_module::instance($cm->id);
        require_capability('quizaccess/seb:manage_seb_requiresafeexambrowser', $context);
        if (settings_provider::is_seb_settings_locked($quiz->id)) {
            throw new \moodle_exception('errorseblocked', 'local_quizbulkedit');
        }

        $existing = seb_quiz_settings::get_by_quiz_id($quiz->id);
        if ($value === '0') {
            if ($existing) {
                $existing->delete();
            }
            settings_provider::delete_uploaded_config_file($cm->id);
            return;
        }

        $sourceid = (int) substr($value, 1);
        $sourcecm = get_coursemodule_from_instance('quiz', $sourceid, $quiz->course, false, MUST_EXIST);
        $source = seb_quiz_settings::get_by_quiz_id($sourceid);
        if (!$source) {
            throw new \moodle_exception('errorsebsource', 'local_quizbulkedit');
        }
        $mode = (int) $source->get('requiresafeexambrowser');
        if (!self::can_use_mode($context, $mode)) {
            throw new \required_capability_exception(
                $context,
                'quizaccess/seb:manage_seb_requiresafeexambrowser',
                'nopermissions',
                ''
            );
        }

        // An uploaded config is read from the quiz's own files when the settings are saved, so copy it first.
        settings_provider::delete_uploaded_config_file($cm->id);
        if ($mode === settings_provider::USE_SEB_UPLOAD_CONFIG) {
            $file = settings_provider::get_module_context_sebconfig_file($sourcecm->id);
            if ($file) {
                get_file_storage()->create_file_from_storedfile([
                    'contextid' => $context->id,
                    'component' => 'quizaccess_seb',
                    'filearea' => 'filemanager_sebconfigfile',
                    'itemid' => 0,
                ], $file);
            }
        }

        $record = $source->to_record();
        unset($record->id, $record->timecreated, $record->timemodified, $record->usermodified);
        $record->quizid = $quiz->id;
        $record->cmid = $cm->id;
        if ($existing) {
            $record->id = $existing->get('id');
            $existing->from_record($record);
            $existing->save();
        } else {
            (new seb_quiz_settings(0, $record))->save();
        }
    }
}
