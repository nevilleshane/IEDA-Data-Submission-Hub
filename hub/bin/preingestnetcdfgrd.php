#!/usr/bin/php
<?php	
date_default_timezone_set('UTC');

require_once(dirname(__FILE__)."/../lib/GridInfo.class.php");
require_once(dirname(__FILE__)."/../lib/PGDefaults.php");

$processUser = posix_getpwuid(posix_geteuid());
$user = $processUser['name'];

$oarray = array(
	'data_type_list:',
	'investigator_list:',
	'entry:',
	'device:',
	'release_date:',
	'platform:',
	'files:',
	'award:',
	'device_make:',
	'device_model:',
	'initiative:',
	'nav_source:',
	'quality:',
	'reference_list:',
	'feature:',
	'projection:',
	'utm_zone:',
	'node_registration:',
	'filelist:',
	'datapath:',
	'verbose',
	'related_data_sets:',
	'contrib_person:',
	'contrib_organization:',
	'help'
);

$oarray2 = array(
	'data_type_list',
	'investigator_list',
	'entry',
	'device',
	'release_date',
	'platform',
	'files',
	'award',
	'device_make',
	'device_model',
	'initiative',
	'nav_source',
	'quality',
	'reference_list',
	'feature',
	'projection',
	'utm_zone',
	'node_registration',
	'filelist',
	'datapath',
	'verbose',
	'related_data_sets',
	'contrib_person',
	'contrib_organization'
);

$options = getopt( 'h' , $oarray );

if (isset($options['help']) || isset($options['h'])) {
	echo <<<EOL
preingestnetcdfgrd.php
	options:
		--data_type_list
			Data types used as a comma delimited list
		--investigator_list
			Investigator IDs as a comma delimited list
			(Investigator sequence is determined by order of list)
		--entry
			Entry ID
		--device
			Device Type
		--release_date
			Release date in form yyyy-mm-dd
		--platform
			Platform ID if different from entry platform
		--files
			List of files as a comma delimited list.  See --filelist option
		--award
			Award ID
		--device_make
			Device make ID
		--device_model
			Device model ID
		--initiative
			Initiative ID
		--nav_source
			Nav type for data
		--quality
			Data quality (integer)
		--reference_list
			Comma delimited list of reference UIDs
		--feature
			Feature UID
		--projection
			Geographic or UTM
		--utm_zone
			UTM zone as {int}N/S
		--related_data_sets (default: NULL)
			A comma-delimited list of data set UIDs related to this data set.
		--node_registration
			Grid Node Registration. Pixel or Grid
		--filelist
			Path to file list.  Use instead of --files for long lists of files
        --contrib_person
            Specify the data contributor person ID
        --contrib_organization
            Specify the data contributor organization ID

EOL;
	exit;
}

$type = 'grid';
$POST = array();

foreach ($oarray2 as $value)
	$POST[$value]=$options[$value];
if (isset($options['verbose']))
	$POST['verbose'] = 1;
$db = pg_connect ("dbname=$PGDATABASE user=$PGUSER password=$PGPASS host=$PGHOST port=$PGPORT");

$msg = '';
if (!$POST['investigator_list'])
	$msg .= "ERROR: At least one investigator must be specified.\n";
if (!$POST['data_type_list'])
	$msg .= "ERROR: At least one data type must be specified.\n";
if ($POST['utm_zone'] && preg_match("/^(Geo|WGS84)/",$POST['projection']))
	$msg .= "ERROR: A UTM zone cannot be specified for geographic data";
if (!$POST['device']) {
	$msg .= "ERROR: A device type must be specified.\n";
} else {
	$check = pg_query($db,"SELECT term FROM vocab_device_type WHERE term = '".pg_escape_string($POST['device'])."'");
	if (pg_num_rows($check) == 0) $msg .= "ERROR: unrecognized device type.\n";
}
if (!$POST['entry']) {
	$msg .= "ERROR: A cruise or compilation ID must be specified.\n";
} else {
	if ($POST['platform']) {
		$check = pg_query(
			$db,
			"SELECT term,platform_type
			FROM vocab_platform_id
			WHERE term = '".pg_escape_string($POST['platform'])."'"
		);
		if (pg_num_rows($check))
			$POST['platform_type'] = pg_fetch_result($check,0,1);
		else
			$msg .= "ERROR: Platform {$POST['platform']} not recognized.\n";
	}
	$check2 = pg_query(
		$db,
		"SELECT
			entry_id,
			entry_type,
			e.platform_id,
			platform_type,
			e.dms_project,
			feature_uid
		FROM entry e
		JOIN vocab_platform_id vpi
			ON e.platform_id=vpi.term
		WHERE entry_id = '".pg_escape_string($POST['entry'])."'"
	);
	if (pg_num_rows($check2)) {
		if (!$POST['platform']) {
			$POST['platform'] = pg_fetch_result($check2,0,2);
			$POST['platform_type'] = pg_fetch_result($check2,0,3);
		}
		$POST['entry_type'] = pg_fetch_result($check2,0,1);
		if (!$POST['dms_project']) {
			$POST['dms_project']= pg_fetch_result($check2,0,4);
		}
		if (!$POST['feature']) {
			$POST['feature']= pg_fetch_result($check2,0,5);
		}
	}
}
if (!$POST['files'] && !$POST['filelist'])
	$msg .= "ERROR: No files specified.\n";
if ($msg) {
	echo $msg;
	exit;
}
$query="SELECT description FROM vocab_dms_user WHERE term='$user'";
$result = pg_query($db,$query);
if (pg_num_rows($result)) {
	$narray = explode(' ',pg_fetch_result($result,0,0));
	$POST['fname'] = $narray[0];
	$POST['lname'] = $narray[1];
}

if ($POST['files'])
	$POST['filelistorig']=$POST['files'];
if ($POST['filelist']) {
	$flocs = file($POST['filelist']);
	foreach ($flocs as $k=>$v) $flocs[$k]=trim($v);
	$POST['filelistorig'] = implode(',',$flocs);
}

$gridinfo = new GridInfo($POST);
