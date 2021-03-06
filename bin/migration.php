#!/usr/bin/env php
<?php

/**
 * DB migration script.
 */
require_once(__DIR__ . '/../phplib/411bootstrap.php');

define('VER_FN', __DIR__ . '/../version.txt');

function ver_cmp($a, $b) {
    $ar = array_map('intval', explode('.', $a));
    $br = array_map('intval', explode('.', $b));

    // Compare each part of the version string.
    for($i = 0; $i < 3; ++$i) {
        $d = $ar[$i] - $br[$i];
        if($d != 0) {
            return $d;
        }
    }
    return 0;
}

$old_ver = VERSION;
// Load in the current version from the version file.
if(file_exists(VER_FN)) {
    $ver = trim(@file_get_contents(VER_FN));
    if($ver !== false) {
        $old_ver = $ver;
    }
}

printf("Migrating from %s to %s\n", $old_ver, VERSION);


/**
 * Migration logic
 */

if(ver_cmp($old_ver, '1.0.1') < 0) {
    // Add indices to speed up common queries.
    FOO\DB::query('CREATE INDEX IF NOT EXISTS `jobs_target_date_idx` ON `jobs`(`target_date`)');
    FOO\DB::query('CREATE INDEX IF NOT EXISTS `type_target_id_site_id_archived_idx` ON `jobs`(type, target_id, site_id, archived)');
    FOO\DB::query('CREATE INDEX IF NOT EXISTS `jobs_type_target_id_site_id_archived_idx` ON `jobs`(type, target_id, site_id, archived)');
}

if(ver_cmp($old_ver, '1.1.0') < 0) {
    FOO\DB::query('ALTER TABLE `users` ADD COLUMN `api_key` VARCHAR(64) NOT NULL DEFAULT ""');
    $user_ids = FOO\DB::query('SELECT user_id FROM users', [], FOO\DB::COL);
    foreach($user_ids as $user_id) {
        FOO\DB::query('UPDATE `users` SET `api_key`=? WHERE `user_id`=?', [FOO\Random::base64_bytes(FOO\User::API_KEY_LEN), $user_id]);
    }
    FOO\DB::query('CREATE UNIQUE INDEX IF NOT EXISTS `users_site_id_api_key_idx` ON `users`(`site_id`, `api_key`);');
}

if(ver_cmp($old_ver, '1.2.0') < 0) {
    FOO\DB::query('ALTER TABLE `sites` ADD COLUMN `secure` BOOLEAN NOT NULL DEFAULT 1');

    FOO\DB::query('ALTER TABLE `searches` ADD COLUMN `source` VARCHAR(64) NOT NULL DEFAULT ""');
    FOO\DB::query('CREATE INDEX IF NOT EXISTS `searches_source_idx` ON `searches`(`source`)');

    FOO\DB::query('UPDATE `searches` SET `source`="logstash", `type`="es" WHERE `type`="logstash"');

    $gcfg = FOO\Config::get('graphite');
    $ecfg = FOO\Config::get('elasticsearch');

    if(FOO\Util::exists($gcfg, 'host') || count(array_diff(array_keys($ecfg), ['alerts', 'logstash'])) > 0) {
        printf("NOTE: There have been some config format changes. See Upgrading.md for details.\n");
    }
}

if(ver_cmp($old_ver, '1.3.4') < 0) {
    FOO\DB::query('ALTER TABLE `users` ADD COLUMN `timezone` VARCHAR(64) NOT NULL DEFAULT "UTC"');

    foreach(FOO\SiteFinder::getAll() as $site) {
        FOO\SiteFinder::setSite($site);
        $config = new FOO\DBConfig;
        $config['timezone'] = 'UTC';
    }
    FOO\SiteFinder::setSite(null);
}

if(ver_cmp($old_ver, '1.5.0') < 0) {
    FOO\DB::query('ALTER TABLE `alerts` ADD COLUMN `source_id` VARCHAR(64) NOT NULL DEFAULT ""');
}

/**
 * Migration logic
 */

file_put_contents(VER_FN, VERSION);

print "Migration complete!\n";
