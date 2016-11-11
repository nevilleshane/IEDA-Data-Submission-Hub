#!/usr/bin/env python
import csv, subprocess, requests, glob, os

def extractMetadataFromCsv(inputfile, delim=","):
	meta={}
	if glob.glob(inputfile) == []:
		meta['Error Message'] = inputfile + " not found"
	else: 
		with open(inputfile, 'rb') as csvfile:
		  reader=csv.DictReader(csvfile, delimiter=delim)
		  for row in reader:
		    for field in reader.fieldnames:
		      meta = row
	return meta

def extractMetadataFromNcdf(inputfile, meta_dict):
	PREINGEST_PATH = '/Users/Neville/software/hub/hub/bin/preingestnetcdfgrd.php'
	print("extracting metadata from NCDF")
	meta = {}
	investigators = ""
	mgds_data_type = ""
	device = ""
	entry = ""
	
	required_dict = {'Principal Investigator(s)':'investigators', 
					   'MGDS_Data_Type':'mgds_data_type', 
					   'Device':'device', 
					   'Expedition or Compilation ID(s)':'entry'}


	missingText = "Please set the following Metadata fields: "
	missing = False

	meta_fields = meta_dict.keys()
	for meta_field in meta_fields:
		if meta_field in required_dict.keys():
			print("found metadata for %s" %meta_field)
			#set value from metadata to variable in this code
			cmd = "%s = '%s'" %(required_dict[meta_field], meta_dict[meta_field])
			exec(cmd)
	
	#look for missing required fields
	for key in required_dict.keys():
		cmd = "%s == ''" %required_dict[key]
		if eval(cmd) :
			if missing: missingText += ", "
		 	missingText += "%s" %key
		 	missing = True


	if (missing):
	 	meta['message'] = missingText
	else:
	 	#call the php preingest script
	 	cmd = ("hub/bin/preingestnetcdfgrd.php --investigator_list='%s' --data_type_list='%s' --device='%s' --entry='%s' --files=%s" %
	 		(investigators, mgds_data_type, device, entry, inputfile))
	 	print(cmd)
	 	sp = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, shell=True)
	 	out, err = sp.communicate()
	 	if err:
	 		meta['Script Error'] = err
	 	else:
		 	#this will create three output files: 
		 	#{entry}__grid.data_set.ingest
		 	#{entry}__grid.device.ingest
		 	#{entry}__grid.object.ingest
		 	meta['Data Set'] = extractMetadataFromCsv("%s__grid.data_set.ingest" %entry, "|")
		 	os.remove("%s__grid.data_set.ingest" %entry)
			meta['Device'] = extractMetadataFromCsv("%s__grid.device.ingest" %entry, "|")
			os.remove("%s__grid.device.ingest" %entry)
			meta['Object'] = extractMetadataFromCsv("%s__grid.object.ingest" %entry, "|")
			os.remove("%s__grid.object.ingest" %entry)

			os.remove("%s.gz" %inputfile)

	return meta
