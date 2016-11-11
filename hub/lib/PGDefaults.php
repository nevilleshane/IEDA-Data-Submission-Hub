<?php
/* PGDefaults.php
 * These are the default values for accessing th database.
 * All php files that access the database should include this file.
 * In the case of the search pages, there is a link from the local directory 
 * to this file to allow us to override the values of a single directory for
 * testing purposes by making the local file different than this global include.
 *
 * 11/3/08 sohara
 * 2012/02/08 jmorton - added R2R database
 * 2012/10 jmorton - added IEDA/fastlane and defined constants
 * 2013/05/16 jmorton - added NDSF
 * 2015/09/22 jmorton - Static class developed with pg_connect functions included
 */
if (!class_exists('DB')) {
    class DB {
        public static $connectionstring = "dbname=%s user=%s password=%s host=%s port=%s";
        public static $connectioninfo = array(
            'PGUSER' => 'anonymous',
            'PGPASS' => 'anonymous',
            
            'PGDATABASE' => 'mgds',
            'PGDATASTATS' => 'mgds_stats',
            'PGDATABASEIEDA' => 'ieda',
            'PGDATABASER2R' => 'r2r',
            'PGDATABASEFASTLANE' => 'fastlane', //this is an archival database and should not be used for new development
            'PGDATABASENDSF' => 'ndsf',
            
            'PGHOST' => 'mgds-db.ldeo-mgg.org',
            'PGHOSTIEDA' => 'sql.ldeo-mgg.org',
            'PGHOSTR2R' => 'sql.ldeo-mgg.org',
            'PGHOSTFASTLANE' => 'sql.ldeo-mgg.org',
            'PGHOSTNDSF' => 'ndsfdh3.whoi.edu',
            
            'PGPORT' => '6432',
            'PGPORTIEDA' => '5434',
            'PGPORTR2R' => '5435',
            'PGPORTFASTLANE' => '5434',
            'PGPORTNDSF' => '5432',
            
            'PGRTNEW' => 'rtnew'
        );
        
        public static function set_vars() {
            foreach (self::$connectioninfo AS $k=>$v) {
                $GLOBALS[$k]=$v;
                define($k,$v);
            }
        }
        
        public static function connectionstring($db,$user,$pass,$host,$port) {
            return sprintf(
                self::$connectionstring,
                self::$connectioninfo[$db],
                self::$connectioninfo[$user],
                self::$connectioninfo[$pass],
                self::$connectioninfo[$host],
                self::$connectioninfo[$port]
            );
        }
        
        public static function connect_mgds() {
            return pg_connect(self::connectionstring('PGDATABASE','PGUSER','PGPASS','PGHOST','PGPORT'));
        }
        
        public static function connect_mgds_stats() {
            return pg_connect(self::connectionstring('PGDATASTATS','PGUSER','PGPASS','PGHOST','PGPORT'));
        }
        
        public static function connect_ieda() {
            return pg_connect(self::connectionstring('PGDATABASEIEDA','PGUSER','PGPASS','PGHOSTIEDA','PGPORTIEDA'));
        }
        
        public static function connect_fastlane() {
            return pg_connect(self::connectionstring('PGDATABASEFASTLANE','PGUSER','PGPASS','PGHOSTIEDA','PGPORTIEDA'));
        }
        
        public static function connect_r2r() {
            return pg_connect(self::connectionstring('PGDATABASER2R','PGUSER','PGPASS','PGHOSTR2R','PGPORTR2R'));
        }
        
        public static function connect_ndsf() {
            return pg_connect(self::connectionstring('PGDATABASENDSF','PGUSER','PGPASS','PGHOSTNDSF','PGPORTNDSF'));
        }
    }
    
}
DB::set_vars();