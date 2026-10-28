<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_microlearning_upgrade($oldversion) {
    global $DB;
    if ($oldversion < 2026102800) {
        $dbman = $DB->get_manager();
        $file = new xmldb_file(__DIR__ . '/install.xml');
        if (!$file->loadXMLStructure()) {
            throw new upgrade_exception('local_microlearning', 2026102800, 'Cannot load install.xml');
        }
        $tables = $file->getStructure()->getTables();
        // Original skeleton had no tables. Refuse unknown manually created schema.
        foreach ($tables as $table) {
            if ($dbman->table_exists($table)) {
                throw new upgrade_exception('local_microlearning', 2026102800,
                    'Existing schema detected; compare and migrate before upgrade.');
            }
        }
        foreach ($tables as $table) {
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        }
        // Existing manually created tables need an explicit migration after schema comparison.
        upgrade_plugin_savepoint(true, 2026102800, 'local', 'microlearning');
    }
    return true;
}
