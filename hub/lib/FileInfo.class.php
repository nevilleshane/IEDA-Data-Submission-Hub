<?php

/**
 * FileInfo
 * 
 * Parent class of TextInfo.class, PDFInfo.class, GridInfo.class,
 * ExcelInfo.class, ShapeInfo.class
 *
 * @author John Morton
 * @copyright MGDS (c) 2013
 */
date_default_timezone_set('GMT');
//require_once("/public/mgg/web/www.marine-geo.org/inc/PGDefaults.php");
require_once("PGDefaults.php");

abstract class FileInfo {

	//Class variables
	protected $verbose, $db;
	protected $post = array();
	protected $ingest_file_directory, $filelistorig, $sizes, $llcolumns, $delimeter;
	
	//Variables for holding metadata
	protected $data_doi, $data_set_title, $data_keywords, $data_geo_keywords, $dms_project;
	protected $data_table = "entry_data_file";
	protected $content_type = 'data_set';
	protected $repository = 'org.marine-geo';
	protected $data_path, $related_data_sets, $event_set_list, $investigators, $reference_list;
	protected $data_types, $data_class, $file_format , $mbio_id;
	protected $entry, $entry_type, $award, $platform, $platform_type, $folder;
	protected $device_array, $device, $device_make, $device_model, $quality;
	protected $release_date , $released , $details , $initiative;
	protected $contorg , $contpers , $fname , $lname;
	protected $west , $east , $south , $north, $fid, $nav_source, $utm_zone;
	protected $start_latitude, $start_longitude, $stop_latitude, $stop_longitude, $start_date, $stop_date;
	protected $coordinate_type = "WGS84";
	protected $data_language_id = 'en';
	protected $is_data_global = 'f';
	protected $is_raw = 'f';
	protected $is_time_series = 'f';
	protected $is_in_gma = 'f';
	protected $is_tar = 'f';
	protected $is_displayed = 't';
	protected $is_in_gmrt = 'f';
	protected $is_in_gmrt_merc = 'f';
	protected $is_in_gmrt_geogr = 'f';
	protected $data_set_url = 'MGDS_URL';
	protected $current = 't';
	
	//Output file names and arrays
	protected $do_data = array();
	protected $ds_data = array();
	protected $dt_data = array();
	protected $ei_data = array();
	protected $dt_name, $do_name, $ds_name, $ei_name;
	
	//gis geometry variables
	protected $geom = array();
	protected $sgeom = array();
	protected $polygon = 0;
	protected $tolerance = 100;
	protected $gis_geometry;
	
	//The ingest file ordering
	protected $setorder = array(
		'data_set_uid', 'entry_id', 'file_format', 'data_table',
		'platform_type', 'platform_id', 'current',
		'contributor_organization_id', 'contributor_person_id', 'quality',
		'feature_uid', 'repository_id', 'survey_datum', 'is_released', 'url',
		'is_displayed', 'is_in_gmrt', 'details', 'ECS_data_provenance',
		'ECS_data_processing', 'ECS_quality_description',
		'ECS_processing_description', 'dms_project', 'data_type_list',
		'event_set_list', 'related_data_set_list', 'reference_uid_list',
		'investigator_id_list', 'device_file', 'data_object_file',
		'data_doi', 'data_set_title', 'data_keywords', 'data_geo_keywords',
		'data_language_id', 'is_data_global', 'is_raw',
		'is_time_series', 'content_type', 'award_id_list'
	);
	protected $objectorder = array(
		'data_set_uid', 'data_uid', 'entry_id', 'url', 'file_name',
		'device_sn', 'current', 'file_format', 'is_tar', 'file_size',
		'file_size_compressed', 'start_date', 'start_longitude',
		'start_latitude', 'start_elevation', 'stop_date', 'stop_longitude',
		'stop_latitude', 'stop_elevation', 'nav_type', 'coordinate_type',
		'westernmost', 'easternmost', 'southernmost', 'northernmost',
		'is_in_gma', 'repository_id', 'dms_access', 'dms_release_date',
		'feature_uid', 'details', 'dms_project', 'related_event_uid_list',
		'related_data_uid_list','gis_geometry'
	);
	protected $observationorder = array(
		'data_set_uid', 'data_uid', 'entry_id', 'date', 'longitude', 'latitude',
		'elevation', 'local_origin_longitude', 'local_origin_latitude',
		'local_x', 'local_y', 'sea_state', 'nav_type', 'depth', 'altitude',
		'heading', 'value', 'units', 'url', 'url_caption', 'details',
		'feature_uid', 'dms_project', 'related_event_uid_list',
		'related_data_uid_list'
	);
	protected $deviceorder = array(
		'data_set_uid', 'device_sn', 'device_type', 'device_make',
		'device_model', 'device_x', 'device_y', 'device_z', 'device_roll',
		'device_pitch', 'device_heading', 'platform_origin_location',
		'details', 'dms_project'
	);
	protected $entryinitiativeorder = array(
		'entry_id', 'initiative_id', 'dms_project'
	);

	protected function db_connect() {
		if (!$this->db) {	
			$this->db = pg_connect(
					"host=" . PGHOST .
					" port=" . PGPORT .
					" dbname=" . PGDATABASE .
					" user=" . PGUSER .
					" password=" . PGPASS
			);
		}
	}
	
	//Construct runs every function for creating ingest files
	public function __construct($POST) {

		$this->db_connect();
		
		//Set instance variables based on POST array
		$this->setpostdata($POST);

		//Set platform and project information
		$this->platform_info();
		if (!$this->dms_project)
			$this->project_info();

		//Set file metadata
		$this->mutter("\nSetting object metadata...");
		$data = $this->filedata();
		
		//Set output file names
		$root = "{$this->ingest_file_directory}/{$this->entry}_{$this->lname}_{$this->folder}";
		$this->ds_name = "$root.data_set.ingest";
		$this->dt_name = "$root.device.ingest";
		$this->do_name = "$root.object.ingest";
		$this->ei_name = "$root.initiative.ingest";

		//Set dataset and device metadata
		$this->mutter("\nSetting data set and device metadata...");
		$this->datasetdata();
		$this->devicedata();
		if ($this->initiative)
			$this->initiativedata();

		//Output files
		$this->mutter("\nWriting data object ingest file " . basename($this->do_name) . "...");
		$this->dataobjectfile($this->do_name);

		$this->mutter("\nWriting data set ingest file " . basename($this->ds_name) . "...");
		$this->datasetfile($this->ds_name);

		$this->mutter("\nWriting device ingest file " . basename($this->dt_name) . "...");
		$this->devicefile($this->dt_name);

		if ($this->ei_data) {
			$this->mutter("\nWriting initiative ingest file " . basename($this->ei_name) . "...");
			$this->entryinitiativefile($this->ei_name);
		}
		//Chmod for group write
		foreach (array($this->dt_name, $this->ds_name, $this->do_name, $this->ei_name) as $file) {
			if (file_exists($file))
				chmod($file, 0660);
		}
		$this->mutter("\n");
	}

	//All child classes must have a file_bounds function to get file bounds and gis_geometry
	abstract public function file_bounds($inputfile);
	
	//All child classes must have a zipsize function to get file sizes and maybe compress
	abstract public function zipsize($val);
	
	//All child classes must have object_data function for additional object level metadata
	abstract public function object_data(&$data);
	
	// Get the coordinate_type "term" based on the srid, using the database.
	public function fetchCoordinateType($srs_code) {	
		$query = "SELECT term FROM vocab_coordinate_type WHERE srid={$srs_code};";
		$result = pg_query($this->db,$query);
		if (!$result || pg_num_rows($result) == 0) {
			return NULL;
		}
		$row = pg_fetch_array($result);
		$coord_string = $row["term"];
		if (!$coord_string) {
			return NULL;
		} else if (substr ( $coord_string, 0, 3 ) == 'UTM') {
			$parts = explode ( "-", $coord_string );
			return array (
					'coordinate_type' => $parts [0] . $parts [1],
					'utm_zone' => $parts [1] 
			);
		} else {
			return array (
					'coordinate_type' => $coord_string,
					'utm_zone' => '' 
			);
		}
	}
	
	// Project points in-place according to $this->coordinate_type
	public function projectPoints($pts) {
		if (preg_match('/^UTM/',$this->coordinate_type)) {
			foreach ($pts as $i=>$pt) {
				$latlon = $this->utm2latlon($pts[$i][0], $pts[$i][1],$this->utm_zone);
				$pts[$i] = array(
						round($latlon['longitude'],6),
						round($latlon['latitude'],6)
				);
			}
		}
	}

	//Default zipsize function
	//Gets uncompressed size, then runs gzip, then gets compressed file size
	public function default_zip($val) {
		$this->mutter("\ngzipping ".basename($val)."...");
		$data['file_size'] = filesize($val);
		exec("/usr/bin/gzip -c $val > $val.gz",$output);
		usleep(10000);
		//unlink("{$this->dir}/$val");
		$data['file_size_compressed'] = filesize("$val.gz");
		$data['file_name'] = basename($val);
		return $data;
	}

	//dirlist function gets absolute path to directory of file and sets filename to base name
	public static function dirlist(&$file, $cwd) {
		$dir = '';
		if (preg_match("/^\//", $file)) {
			$dir = dirname($file);
		} elseif (preg_match("/^http:\/\//", $file)) {
			$url = parse_url($file);
			$dir = $url['scheme']."://".$url['host'].dirname($url['path']);
			$file = basename($url['path']);
		} elseif (!strcmp(basename($file),$file)) {
			$dir = $cwd . "/" . dirname($file);
		}
		$file=basename($file);
		return $dir;
	}

	//The following functions output ingest files
	public function dataobjectfile($name) {
		$array = array();
		$array[] = "#" . implode("|", $this->objectorder);
		foreach (array_keys($this->do_data) as $k1) {
			$ret = '';
			$i = 0;
			foreach ($this->objectorder as $key) {
				if ($i++)
					$ret .= '|';
				if ($this->do_data[$k1][$key] === "" || !isset($this->do_data[$k1][$key]))
					$this->do_data[$k1][$key] = "NULL";
				$ret .= $this->do_data[$k1][$key];
			}
			$array[] = $ret;
		}
		file_put_contents($name, implode("\n", $array) . "\n");
		return;
	}

	public function datasetfile($name) {
		$array = array();
		$array[] = '#' . implode('|', $this->setorder);
		$i = 0;
		foreach ($this->setorder as $key) {
			if ($this->ds_data[$key] === "" || !isset($this->ds_data[$key]))
				$this->ds_data[$key] = "NULL";
			$ret .= (($i++) ? '|' : '') . $this->ds_data[$key];
		}
		$array[] = $ret;
		file_put_contents($name, implode("\n", $array) . "\n");
		return;
	}

	public function devicefile($name) {
		$array = array();
		$array[] = '#' . implode('|', $this->deviceorder);
		foreach (array_keys($this->dt_data) as $k1) {
			$ret = '';
			$i = 0;
			foreach ($this->deviceorder as $key) {
				if ($this->dt_data[$k1][$key] === "" || !isset($this->dt_data[$k1][$key]))
					$this->dt_data[$k1][$key] = "NULL";
				$ret .= (($i++) ? '|' : '') . $this->dt_data[$k1][$key];
			}
			$array[] = $ret;
		}
		file_put_contents($name, implode("\n", $array) . "\n");
		return;
	}

	public function entryinitiativefile($name) {
		$array = array();
		$array[] = "#" . implode("|", $this->entryinitiativeorder);
		foreach (array_keys($this->ei_data) as $k1) {
			$ret = '';
			$i = 0;
			foreach ($this->entryinitiativeorder as $key) {
				if ($i++)
					$ret .= '|';
				if ($this->ei_data[$k1][$key] === "" || !isset($this->ei_data[$k1][$key]))
					$this->ei_data[$k1][$key] = "NULL";
				$ret .= $this->ei_data[$k1][$key];
			}
			$array[] = $ret;
		}
		file_put_contents($name, implode("\n", $array) . "\n");
		return;
	}

	//Echos messages if a user wants to see them
	public function mutter($string) {
		if ($this->verbose) {
			echo $string;
		}
		return;
	}

	public function setpostdata($POST) {
		$this->verbose = $POST['verbose'];
		$this->mbio_id = $POST['mbio_id'];
		$this->ingest_file_directory = $POST['ingest_file_directory'];
		if (!$this->ingest_file_directory)
			$this->ingest_file_directory = getcwd();
		$this->sizes = $POST['sizes'];
		$this->setvalue($this->dms_project, $POST['dms_project']);
		$this->data_doi = $POST['data_doi'];
		$this->data_set_title = $POST['data_set_title'];
		$this->data_keywords = $POST['keywords'];
		$this->data_geo_keywords = $POST['geo_keywords'];
		if ($POST['is_data_global'])
			$this->is_data_global = $POST['is_data_global'];
		$this->llcolumns = $POST['cols'];
		$this->delimeter = $POST['delimeter'];
		$this->setvalue($this->file_format, $POST['file_format']);

		$this->mutter("\nCreating ingestion files...");

		$this->data_path = $POST['datapath'];
		if ($this->data_path)
			$this->data_path = preg_replace(array("/\/\$/", "/^\/data\/mgds\//"), '', $this->data_path) . '/';
		$this->related_data_sets = $POST['related_data_sets'];
		$this->event_set_list = $POST['event_set_list'];

		$this->investigators = $POST['investigator_list'];
		$this->reference_list = $POST['reference_list'];
		$this->setvalue($this->data_types, $POST['data_type_list']);
		$this->entry = $POST['entry'];
		$this->entry_type = $POST['entry_type'];
		$this->award = $POST['award'];
		$this->setvalue($this->platform, $POST['platform']);
		$this->setvalue($this->platform_type, $POST['platform_type']);
		if (!$POST['device_array'] && !$this->device_array) {
			$this->setvalue($this->device, $POST['device']);
			$this->device_make = $POST['device_make'];
			$this->device_model = $POST['device_model'];
			$this->device_array = array(array(
					'device_type' => $this->device,
					'device_make' => $this->device_make,
					'device_model' => $this->device_model
					));
		} elseif (!$this->device_array)
			$this->device_array = $POST['device_array'];
		$this->setvalue($this->nav_source, $POST['nav_source']);
		$this->setvalue($this->quality, $POST['quality']);
		if (isset($POST['quality']) && ((int) $POST['quality']) == 0)
			$this->is_raw = 't';
		elseif (isset($POST['is_raw']))
			$this->is_raw = $POST['is_raw'];
		if (isset($POST['is_displayed']))
			$this->is_displayed = $POST['is_displayed'];
		if (isset($POST['is_in_gmrt']))
			$this->is_in_gmrt = $POST['is_in_gmrt'];
		if (isset($POST['is_in_gmrt_merc']))
			$this->is_in_gmrt_merc = $POST['is_in_gmrt_merc'];
		if (isset($POST['is_in_gmrt_geogr']))
			$this->is_in_gmrt_geogr = $POST['is_in_gmrt_geogr'];
		$this->setvalue($this->contorg, $POST['contrib_organization']);
		$this->contpers = $POST['contrib_person'];
		$this->fid = ($POST['feature']) ? $POST['feature'] : 'FEATURE_UID';
		$this->release_date = $POST['release_date'];
		$this->fname = $POST['fname'];
		$this->lname = $POST['lname'];
		$this->details = $POST['details'];
		$this->initiative = ($POST['initiative']) ?
				explode(',', $POST['initiative']) : array();
		$this->filelistorig = explode(',', $POST['filelistorig']);
		$this->west = $POST['west'];
		$this->east = $POST['east'];
		$this->south = $POST['south'];
		$this->north = $POST['north'];
		$this->setvalue($this->data_language_id, $POST['language']);
		if ($this->entry_type) {
            $et1 = explode(":", $this->entry_type);
			$this->entry_type = $et1[0];
        }
		if (!$this->contorg)
			$this->contorg = 'NotProvided';
		if (!$this->contpers)
			$this->contpers = "{$this->lname}_{$this->fname}";
		if (!$this->release_date) {
			echo "Release date not set. Default is today's date\n";
			$this->release_date = date('Y-m-d');
		}
		$date2 = date('Y-m-d');
		$this->released = (strtotime($this->release_date) > strtotime($date2)) ? 'f' : 't';
	}

	public function platform_info() {
		if (!$this->platform) {
			$this->mutter("\nDetermining platform for {$this->entry}...");
			$query = pg_query($this->db, "SELECT e.platform_id,v.platform_type
				FROM entry e
				JOIN vocab_platform_id v
					ON e.platform_id=v.term
				WHERE entry_id='" . pg_escape_string($this->entry) . "'"
			);
			if (pg_num_rows($query)) {
				$row = pg_fetch_row($query);
				$this->platform = $row[0];
				if (!$this->platform_type)
					$this->platform_type = $row[1];
			}
			if (!$this->platform) {
				$this->platform = 'NotProvided';
				if (!$this->platform_type)
					$this->platform_type = 'NotProvided';
			}
		} else {
			$this->mutter("\nDetermining platform type for {$this->platform}...");
			$query = pg_query($this->db, "SELECT v.platform_type
				FROM vocab_platform_id v
				WHERE v.term='" . pg_escape_string($this->platform) . "'"
			);
			if (pg_num_rows($query) && !$this->platform_type) {
				$row = pg_fetch_row($query);
				$this->platform_type = $row[0];
			} elseif (!$this->platform_type) {
				$this->platform_type = 'NotProvided';
			}
		}
	}

	public function project_info() {
		$this->mutter("\nDetermining dms_project from initiative...");

		$this->dms_project = 'MGG';
		$ini = "NULL";
		if ($this->initiative)
			$ini = implode(",", $this->initiative);
		$ridgematch = preg_match("/Ridge/", $ini);
		$marginsmatch = preg_match("/MARGINS/", $ini);
		$geopmatch = preg_match("/GeoPRISMS/", $ini);
		$ambsmatch = preg_match("/^(LMG|NBP)/", $this->entry);

		if ($ambsmatch) {
			$this->dms_project = 'AMBS';
		} elseif ($ridgematch xor $marginsmatch xor $geopmatch) {
			if ($ridgematch)
				$this->dms_project = 'RODES';
			elseif ($marginsmatch)
				$this->dms_project = 'MARGINS';
			elseif ($geopmatch)
				$this->dms_project = 'GeoPRISMS';
		}
	}

	public function filedata() {
		foreach ($this->filelistorig as $key => $val) {
			$this->mutter("\nExtracting bounds for $val (if applicable)...");
			$dir = self::dirlist($val, getcwd());
			//file_bounds gets the wesn and gis_geometry
			if ($this->llcolumns || $this->delimeter) {
				if ($this->delimeter) {
					$this->file_bounds("{$dir}/{$val}", $this->llcolumns,$this->delimeter);
				} else {
					$this->file_bounds("{$dir}/{$val}", $this->llcolumns);
				}
			} else {
				$this->file_bounds("{$dir}/{$val}");
			}
			foreach ($this->objectorder as $n)
				$data[$n] = '';
			$this->mutter("\nDetermining metadata for $val...");
			$data['start_longitude'] = $this->start_longitude;
			$data['start_latitude'] = $this->start_latitude;
			$data['stop_longitude'] = $this->stop_longitude;
			$data['stop_latitude'] = $this->stop_latitude;
			$data['start_date'] = $this->start_date;
			$data['stop_date'] = $this->stop_date;
			$data['westernmost'] = $this->west;
			$data['easternmost'] = $this->east;
			$data['southernmost'] = $this->south;
			$data['northernmost'] = $this->north;
			$data['quality'] = $this->quality;
			$data['entry_id'] = $this->entry;
			$data['coordinate_type'] = $this->coordinate_type;
			$data['current'] = $this->current;
			$data['file_format'] = $this->file_format;
			$data['feature_uid'] = $this->fid;
			$data['nav_type'] = $this->nav_source;
			$data['dms_project'] = $this->dms_project;
			$data['repository_id'] = $this->repository;
			$data['dms_access'] = ($this->released == 'f') ? 'None' : 'PUBLIC';
			$data['dms_release_date'] = $this->release_date;
			$data['is_in_gma'] = $this->is_in_gma;
			$data['is_tar'] = $this->is_tar;
			$data['gis_geometry'] = $this->gis_geometry;
			$data['url'] = preg_replace("/\/\$/","",( ($this->data_path) ?
				$this->data_path
				: preg_replace(array("/\/\$/", "/^\/data\/mgds\//"), '', $dir)
			))."/";
			$data['is_in_gmrt_merc'] = $this->is_in_gmrt_merc;
			$data['is_in_gmrt_geogr'] = $this->is_in_gmrt_geogr;
			$data['is_in_gmrt_np'] = $data['is_in_gmrt_sp'] = 'f';
			$this->object_data($data);
			$zdata = $this->zipsize("{$dir}/{$val}");
			$data['file_name'] = $zdata['file_name'];
			$data['file_size'] = $zdata['file_size'];
			$data['file_size_compressed'] = $zdata['file_size_compressed'];
			if (isset($zdata['is_tar']))
				$data['is_tar'] = $zdata['is_tar'];
			$this->do_data[] = $data;
		}
	}

	public function datasetdata() {
		$this->mutter("\nSetting data set level metadata...");
		foreach ($this->setorder as $n)
			$ds_data[$n] = '';
		$ds_data['data_set_temp_key'] = $this->folder;
		$ds_data['file_format'] = $this->file_format;
		$ds_data['entry_id'] = $this->entry;
		$ds_data['url'] = $this->data_set_url;
		$ds_data['dms_project'] = $this->dms_project;
		$ds_data['data_table'] = $this->data_table;
		$ds_data['platform_id'] = $this->platform;
		$ds_data['platform_type'] = $this->platform_type;
		$ds_data['current'] = $this->current;
		$ds_data['contributor_organization_id'] = $this->contorg;
		$ds_data['contributor_person_id'] = $this->contpers;
		$ds_data['quality'] = $this->quality;
		$ds_data['feature_uid'] = ($this->fid) ? $this->fid : '474';
		$ds_data['repository_id'] = $this->repository;
		$ds_data['is_released'] = $this->released;
		$ds_data['platform_type'] = $this->platform_type;
		$ds_data['is_displayed'] = $this->is_displayed;
		
		$investigator_array = explode(',',$this->investigators);
		$n = 1;
		foreach($investigator_array AS $k=>$v) {
			$inv = explode(':',$v);
			$investigator_array[$k] = "{$inv[0]}:".$n++;
		}
		
		$ds_data['investigator_id_list'] = implode(',',$investigator_array);
		$ds_data['data_type_list'] = $this->data_types;
		$ds_data['device_file'] = basename($this->dt_name);
		$ds_data['data_object_file'] = basename($this->do_name);
		$ds_data['is_in_gmrt'] = $this->is_in_gmrt;
		$ds_data['details'] = $this->details;
		$ds_data['reference_uid_list'] = $this->reference_list;
		$ds_data['related_data_set_list'] = $this->related_data_sets;
		$ds_data['event_set_list'] = $this->event_set_list;
		$ds_data['data_set_title'] = $this->data_set_title;
		$ds_data['data_keywords'] = $this->data_keywords;
		$ds_data['data_geo_keywords'] = $this->data_geo_keywords;
		$ds_data['data_doi'] = $this->data_doi;
		$ds_data['is_raw'] = $this->is_raw;
		$ds_data['is_time_series'] = $this->is_time_series;
		$ds_data['is_data_global'] = $this->is_data_global;
		$ds_data['data_language_id'] = $this->data_language_id;
		$ds_data['content_type'] = $this->content_type;
		$ds_data['award_id_list'] = $this->award;

		$this->ds_data = $ds_data;
	}

	public function devicedata() {
		foreach ($this->device_array as $device) {
			foreach ($this->deviceorder as $n)
				$dt_data[$n] = '';
			$this->mutter("\nSetting device metadata...");
			$dt_data['device_type'] = $device['device_type'];
			$dt_data['device_make'] = ($device['device_make']) ? $device['device_make'] : 'NotProvided';
			$dt_data['device_model'] = ($device['device_model']) ? $device['device_model'] : 'NotProvided';
			$dt_data['dms_project'] = $this->dms_project;
			$this->dt_data[] = $dt_data;
		}
	}

	public function initiativedata() {
		$this->mutter("\nSetting initiative metadata...");
		foreach ($this->initiative AS $val) {
			if ($val == "None")
				continue;
			$data2 = array();
			$data2['entry_id'] = $this->entry;
			$data2['initiative_id'] = $val;
			$data2['dms_project'] = $this->dms_project;
			$this->ei_data[] = $data2;
		}
	}

	private function setvalue(&$var, $val) {
		if (!isset($var)) {
			$var = $val;
			return true;
		}
		return false;
	}

	private function setdatapath() {
		$data_dir = pg_query($this->db, "SELECT data_dir
			FROM vocab_platform_id
			WHERE term = '{$this->platform}'
				AND data_dir IS NOT NULL AND data_dir <> ''"
		);
		$ddir = (pg_num_rows($data_dir)) ?
				pg_fetch_result($data_dir, 0, 0) : $this->platform;
		$type = strtolower($this->entry_type);
		$platform_dir = str_replace(" ", '', $ddir);
		return "$type/$platform_dir/{$this->entry}/{$this->folder}/";
	}
	
	//Peucker/gis_geometry functions
	public function ewktOutput($geom='') {
		if (!$geom) {
			$geom = $this->sgeom;
		}
		if ($this->polygon) {
			if (count($geom) == 1) {
				$points = self::ewktString($geom[0]);
				if ($points)
					$sg = "POLYGON(($points))";
			} else {
				$arr = array();
				foreach ($geom as $sgeom) {
					$points = self::ewktString($sgeom);
					if ($points)
						$arr[] = "(($points))";
				}
				if ($arr)
					$sg = "MULTIPOLYGON(".implode(',',$arr).")";
				unset($arr);
			}
		} else {
			if (count($geom) == 1) {
				if (count($geom[0])==2 && $geom[0][0]==$geom[0][1]) {
					unset($geom[0][1]);
					$points = self::ewktString($geom[0]);
					if ($points)
						$sg = "POINT($points)";
				} else {
					$points = self::ewktString($geom[0]);
					if ($points)
						$sg = "LINESTRING($points)";
				}
			} else {
				$arr = array();
				foreach ($geom as $sgeom) {
					$points = self::ewktString($sgeom);
					if ($points)
						$arr[] = "($points)";
				}
				if ($arr)
					$sg = "MULTILINESTRING(".implode(',',$arr).")";
				unset($arr);
			}
		}
		return $sg;
	}
	
	public static function ewktString($sgeom) {
		foreach ($sgeom as $k=>$v) {
			if ($v[0] && $v[1]) {
				foreach ($v as $k2=>$v2) {
					$sgeom[$k][$k2] = sprintf("%.6f",$v2);
				}
				$sgeom[$k] = implode(' ',$sgeom[$k]);
			}
		}
		$str = implode(',',$sgeom);
		if ($str == ','||!$str)
			return false;
		else
			return $str;
	}
	
	public function simplify() {
		$this->sgeom=array();
		foreach ($this->geom as $Ipoints) {
			$Opoints = array();
			$stack   = array();
			$anchor = $Ipoints[0]; # save first point
			array_push($Opoints, $anchor);
			$aIndex = 0; # Anchor Index
			$icount = count($Ipoints);
			$fIndex = $icount - 1; # It's a path (open polygon)
			if ($this->polygon)
				$fIndex -= 1;
			array_push($stack, $fIndex);

			# Douglas - Peucker algorithm...
			while($stack) {
				$fIndex = end($stack);
				$fPoint = $Ipoints[$fIndex];
				$max = $this->tolerance; # comparison values
				$maxIndex = 0;
				# Process middle points...
				for ( $i = $aIndex+1 ; $i<=$fIndex-1 ; $i++ ) {
					$dist = self::perpDistance($anchor, $fPoint, $Ipoints[$i]);
					if( $dist >= $max ) {
							$max = $dist;
							$maxIndex = $i;
					}
				}
				if( $maxIndex > 0 ) {
					array_push($stack,$maxIndex);
				} else {
					array_push($Opoints,$fPoint);
					$anchor = $Ipoints[array_pop($stack)];
					$aIndex = $fIndex;
				}
			}
			if ( $polygon ) {
				array_push($Opoints,end($Ipoints)); # Add the last point
				# Check for collapsed polygons, use original data in that case...
				if (count($Opoints) < 4)
					$Opoints = $Ipoints; 
			}
			if (!(count($Opoints)==1 || (count($Opoints)==2 && $Opoints[0]==$Opoints[1])))
				array_push($this->sgeom,$Opoints);
		}
		unset($Opoints);
        return;
	}
	
	public static function perpDistance($anchor,$fpoint,$point) {
        $lon1 = deg2rad($anchor[0]);
        $lon2 = deg2rad($point[0]);
        $dlon = $lon1 - $lon2;
        $dlat = deg2rad($anchor[1])- deg2rad($point[1]);
        $dist = 2 * 6378137 * asin(
			sqrt(
				pow(sin($dlon/2),2)
				+ cos($lon1) * cos($lon2) * pow(sin($dlat/2),2)
			)
		);		
        $m1 = atan2( ($fpoint[1] - $anchor[1]) , ( $fpoint[0] - $anchor[0] ) );
        $m2 = atan2( ($point[1] - $anchor[1]) , ( $point[0] - $anchor[0] ) );
        $angle = $m2 - $m1;
		return ( sprintf("%0.6f", abs($dist * sin($angle)) ) );
	}
	
	public static function utm2latlon($x,$y,$zone) {
		$sm_a = 6378137.0;
		$sm_b = 6356752.314;
		$UTMScaleFactor = 0.9996;
		$zone_num = preg_replace("/[NS]/",'',$zone);
		$hemisphere = substr($zone,-1,1);
		
		$x = ( $x - 5.0e5 )/$UTMScaleFactor;
		$y = ( $y - (($hemisphere=='S') ? 1.0e7 : 0 ))/$UTMScaleFactor;
		
		$cmeridian = (-183.0 + ($zone_num * 6.0)) * ( M_PI / 180.0);
		
		$n = ($sm_a - $sm_b) / ($sm_a + $sm_b);
		$alpha_ = ( ($sm_a + $sm_b) / 2 ) * ( 1 + (pow($n,2)/4) + (pow($n,4)/64) );
		$y_ = $y / $alpha_;
		$beta_ = (3 * $n / 2)
			+ (-27 * pow($n,3) / 32)
			+ (269 * pow($n,5) / 512);
		$gamma_ = (21 * pow($n,2) / 16)
			+ (-55 * pow($n,4) / 32);
		$delta_ = (151 * pow($n,3) / 96)
			+ (-417 * pow($n,5) / 128);
		$epsilon_ = 1097 * pow($n,4) / 512;
		$phif = $y_
			+ ( $beta_    * sin( 2 * $y_) )
			+ ( $gamma_   * sin( 4 * $y_) )
			+ ( $delta_   * sin( 6 * $y_) )
			+ ( $epsilon_ * sin( 8 * $y_) );
		
		$cosphif = cos($phif);
		$tanphif = tan($phif);
		
		$nuf2 = ((pow($sm_a,2) - pow($sm_b,2)) / pow($sm_b,2)) * pow($cosphif,2);
		$Nf = pow($sm_a,2)/($sm_b*sqrt(1+$nuf2));
		
		$x1frac = 1 / ($Nf * $cosphif);
		$x2frac = $tanphif / (2 * pow($Nf,2));
		$x3frac = 1 / (6 * pow($Nf,3) * $cosphif);
		$x4frac = $tanphif / (24 * pow($Nf,4));
		$x5frac = 1 / (120 * pow($Nf,5) * $cosphif);
		$x6frac = $tanphif / (720 * pow($Nf,6));
		$x7frac = 1 / (5040 * pow($Nf,7) * $cosphif);
		$x8frac = $tanphif / (40320 * pow($Nf,8));
		
		$x1poly = 1;
		$x2poly = -1 - $nuf2;
		$x3poly = -1 - ( 2 * pow( $tanphif , 2) ) - $nuf2;
		$x4poly = 5
			+ ( 3 * pow($tanphif,2) ) + (6 * $nuf2)
			- ( 6 * pow($tanphif,2) * $nuf2) - (3 * pow($nuf2,2))
			- ( 9 * pow($tanphif,2) * pow($nuf2,2) );
		$x5poly = 5
			+ ( 28 * pow($tanphif,2))
			+ ( 24 * pow($tanphif,4)) + (6 * $nuf2)
			+ (  8 * pow($tanphif,2) * $nuf2);
		$x6poly = -61
			- (  90 * pow($tanphif,2))
			- (  45 * pow($tanphif,4)) - (107 * $nuf2)
			+ ( 162 * pow($tanphif,2) * $nuf2);
		$x7poly = -61
			- ( 662 * pow($tanphif,2))
			- (1320 * pow($tanphif,4))
			- ( 720 * pow($tanphif,6));
		$x8poly = 1385
			+ (3633 * pow($tanphif,2))
			+ (4095 * pow($tanphif,4))
			+ (1575 * pow($tanphif,6));
		
		$latitude = $phif
			+ ($x2frac * $x2poly * pow($x,2))
			+ ($x4frac * $x4poly * pow($x,4))
			+ ($x6frac * $x6poly * pow($x,6))
			+ ($x8frac * $x8poly * pow($x,8));
		$longitude = $cmeridian
			+ ($x1frac * $x1poly * $x)
			+ ($x3frac * $x3poly * pow($x,3))
			+ ($x5frac * $x5poly * pow($x,5))
			+ ($x7frac * $x7poly * pow($x,7));
		
		$result['latitude'] = $latitude * 180 / M_PI;
		$result['longitude'] = $longitude * 180 / M_PI;
		return $result;
	}
}
