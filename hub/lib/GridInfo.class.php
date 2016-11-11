<?php

/*GridInfo Class
	PHP front-end for grdinfo to return relevant information about a grid file
*/
require_once(dirname(__FILE__)."/FileInfo.class.php");
class GridInfo extends FileInfo
{

	protected $node_registration;
	protected $minz;
	protected $maxz;
	protected $xres;
	protected $yres;
	protected $zres;
	protected $xn;
	protected $yn;
	protected $projection;
	protected $masked;
	
	public function __construct( $POST) {
		
		$this->objectorder = array(
			'data_set_uid','data_uid','entry_id','url','file_name','device_sn',
			'current','file_format','is_tar','file_size','file_size_compressed',
			'start_date','start_longitude','start_latitude','start_elevation',
			'stop_date','stop_longitude','stop_latitude','stop_elevation','nav_type',
			'coordinate_type','westernmost','easternmost','southernmost',
			'northernmost','is_in_gma','repository_id','dms_access',
			'dms_release_date','feature_uid','details','dms_project','minimum_z',
			'maximum_z','x_resolution','y_resolution','z_resolution',
			'node_registration','projection','masked','related_event_uid_list',
			'related_data_uid_list','gis_geometry'
		);
		
		$this->file_format = "NetCDF:GMT";
		$this->folder = "grid";
		$this->is_raw = 'f';
		if (isset($POST['quality'])&&((int) $POST['quality'])==0)
			$this->is_raw='t';
		$this->is_time_series = 'f';
		$this->coordinate_type = (
			(
				(!$POST['utm_zone'] && !$POST['projection'])
				||preg_match("/^(Geo|WGS84)/",$POST['projection'])
			)?
				"WGS84"
				:("UTM-".$POST['utm_zone'])
		);
		$this->data_table = "entry_data_file_grid";
		$this->node_registration = $POST['node_registration'];
		
		parent::__construct($POST);
	}
	
	public function zipsize($val){
		return parent::default_zip($val);
	}
	
	public function file_bounds($inputfile) {
		$fileescaped = escapeshellcmd($inputfile);
		$gdalinfo = array();
		exec("/home/mgds/local/bin/anaconda3/bin/gdalinfo -mm $fileescaped 2> /dev/null", $gdalinfo);
		if ($gdalinfo) {
			$this->west = INF;
			$this->east = -INF;
			$this->south = INF;
			$this->north = -INF;
			foreach ($gdalinfo as $i => $line) {
				$matches = array();
				if (preg_match('/^(?:Upper Left|Upper Right|Lower Left|Lower Right)\s*\(\s*(-?(?:\d+|\d*\.\d+)),\s*(-?(?:\d+|\d*\.\d+))\)/', $line, $matches)) {
					$this->west = min($this->west, floatval($matches[1]));
					$this->east = max($this->east, floatval($matches[1]));
					$this->north = max($this->north, floatval($matches[2]));
					$this->south = min($this->south, floatval($matches[2]));
				} else if (preg_match('#Computed Min/Max=(-?(?:\d+|\d*\.\d+)),(-?(?:\d+|\d*\.\d+))#',$line,$matches)) {
					$this->minz = floatval($matches[1]);
					$this->maxz = floatval($matches[2]);
				} else if (preg_match('/\s*z#actual_range={(-?(?:\d+|\d*\.\d+)),(-?(?:\d+|\d*\.\d+))}/',$line,$matches)) {
					$this->minz = floatval($matches[1]);
					$this->maxz = floatval($matches[2]);
				} else if (preg_match('/Pixel Size = \((-?(?:\d+|\d*\.\d+)),(-?(?:\d+|\d*\.\d+))\)/',$line,$matches)) {
					$this->xres = floatval($matches[1]);
					$this->yres = floatval($matches[2]);
				} else if (preg_match('/Size is\s*(-?(?:\d+|\d*\.\d+)),\s*(-?(?:\d+|\d*\.\d+))/', $line, $matches)) {
					$this->xn = intval($matches[1]);
					$this->yn = intval($matches[2]);
				}
			}
			return array($this->west,$this->east,$this->south,$this->north);
		}
		return array();
	}
	
	public function object_data(&$data) {
		$data['minimum_z'] = $this->minz;
		$data['maximum_z'] = $this->maxz;
		$data['x_resolution'] = $this->xres;
		$data['y_resolution'] = $this->yres;
		$data['z_resolution'] = $this->zres;
		$data['node_registration'] = $this->node_registration;
		$data['projection'] = $this->projection;
		$data['masked'] = $this->masked;
		$data['x_n'] = $this->xn;
		$data['y_n'] = $this->yn;
	}

}
