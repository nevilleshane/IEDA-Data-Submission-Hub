# -*- coding: utf-8 -*-
"""
Created on Thu Jul 21 11:40:08 2016

@author: Neville
"""
import flask
from flask import render_template, request, json, redirect, session, jsonify
#from ..lib import messaging
import mmap
import os, sys, glob
import requests
import webbrowser
from ..lib import extractMetadata
from ..lib.dataStorageFunctions import createDatasetMetaFile
from ..lib.fileIntrospectionFunctions import determineFiletype
from time import gmtime, strftime

index_page = flask.Blueprint("index_page", __name__)
DATA_TYPE_URL = 'http://www.ldeo.columbia.edu/~nshane/data_type.json'
DEVICE_URL = 'http://www.ldeo.columbia.edu/~nshane/device.json'
MGDS_DATA_TYPE_URL = 'http://www.ldeo.columbia.edu/~nshane/mgds_data_type.json'



DEFAULT_DATA_TYPE = 'Geochemistry'


def getJsonFromUrl(url):
    try:
        uResponse = requests.get(url)
    except requests.ConnectionError:
       return "Connection Error"  
    Jresponse = uResponse.text
    data = json.loads(Jresponse)
    return data


def resetSubmissionForm(template_dict):
    template_dict['data_type'] = DEFAULT_DATA_TYPE
    template_dict['uploaded_file'] =  '' 
    template_dict['dataset'] = ''
    template_dict['filetype'] = ''
    template_dict['confirm_datatype'] = False
    return template_dict

# def determineFiletype(f):
#     mimetype = f.mimetype
#     if (mimetype in GEOPHYSICS_MIMETYPES): return 'Geophysics'
#     if (mimetype in GEOCHEMESTRY_MIMETYPES): return 'Geochemistry'
#     return 'unknown'

def runGeophysPreingest(meta_dict):

    #get the file's metadata
    fileid = getFileId()
    secretkey = getKey()
    r=requests.get('%sapi/files/%s/metadata?key=%s' % (host, fileid, secretkey))
    r.raise_for_status()
    filedata = r.json()
    filetype = filedata['content-type']
    inputfile = os.path.join(UPLOAD_DIR,filedata['filename'])
    print(filetype)
    print(inputfile)
    if (glob.glob(inputfile)) != [] : 
        if (filetype == 'application/octet-stream'):
            meta = extractMetadata.extractMetadataFromNcdf(inputfile, meta_dict)

            if (meta.has_key('Script Error')):
                print(meta['Script Error'])
            else:
                print ("uploading metadata")
                uploadMetadata("Geophysics Metadata", meta)
                print ("upload completed")
        #delete file from cache dir
        os.remove(inputfile)


# @index_page.route('/check_file', methods=['GET', 'POST'])
# def check_file():
#     print(request)
#     print(request.files)
#     f = request.files['data_file']
#     filetype = determineFiletype(f)
#     template_dict['filetype'] = filetype
#     return redirect('/')


@index_page.route('/view_metadata', methods=['GET', 'POST'])
def view_metadata():
    dataset = request.args['dataset']
    dataset_dir = os.path.join(session['user_space'],dataset.replace(" ",""))
    metafile = os.path.join(dataset_dir, 'dataset_metadata.json')
    with open(metafile) as infile:
            data = json.load(infile)
    return jsonify(data)

@index_page.route('/view_user_metadata', methods=['GET', 'POST'])
def view_user_metadata():
    metafile = os.path.join(session['user_space'], 'user_metadata.json')
    with open(metafile) as infile:
            data = json.load(infile)
    return jsonify(data)


@index_page.route('/', methods=['GET', 'POST'])
@index_page.route('/hub/', methods=['GET', 'POST'])
def index():
    print(request.form)
    cached_file = ''
    template_dict = {}
    meta_dict = {}
    template_dict['metadata_status'] = None
    template_dict['data_types_list'] = getJsonFromUrl(DATA_TYPE_URL)
    template_dict['device_list'] = getJsonFromUrl(DEVICE_URL)
    template_dict['mgds_data_type_list'] = getJsonFromUrl(MGDS_DATA_TYPE_URL)
    template_dict['data_type'] = DEFAULT_DATA_TYPE

    template_dict['confirm_datatype'] = False

    # get uploaded file
    if request.method == 'POST':
        #posted flile details
        print(request.form['submit'])
        print(request.form)

        if request.form['submit'] == 'file_submit':
            dataset = request.form['dataset_name']
            template_dict['dataset'] = dataset
           
            data_type = request.form['data_type']
            template_dict['data_type'] = data_type
            dataset_dir = os.path.join(session['user_space'],dataset.replace(" ",""))
          
            print(request.files)
            f = request.files['data_file']
            filename=f.filename
            filetype = None
            
            if filename != "": 

                #save the file in the user_space
                #first make a folder using the dataset name    
                if not os.path.exists(dataset_dir):
                    try:
                        os.makedirs(dataset_dir)
                    except:
                        print("Can't create dataset dir: %s" %dataset_dir)

                cached_file = os.path.join(dataset_dir, filename)
                f.save(cached_file)   

                #determine the mimetype and filetype
                mimetype, filetype = determineFiletype(cached_file)
                template_dict['filetype'] = filetype
                session['mimetype'] = mimetype

                #get size of file
                session['filesize'] = os.path.getsize(cached_file)

                #if the determined filetype is different from manually selected
                #datatype, ask the user to confirm which they want to use

                if filetype != data_type and not request.form.get('confirmed') :
                    template_dict['confirm_datatype'] = True
  
                template_dict['uploaded_file'] = filename
            #if the request has been confirmed, or if there was no need to confirm...
            if not template_dict['confirm_datatype'] or request.form.get('confirmed'): 

                #if confirmed request, get the filename from 'confirmed'
                if request.form.get('confirmed'):
                    filename = request.form['confirmed']
                if request.form.get('filetype'):
                    filetype = request.form['filetype']
                #create a metadata file
                dataset_meta = {'dataset_name':dataset, 
                                'data_type':data_type,
                                'filetype':filetype,
                                'files':[{'file_name':filename,
                                          'mime_type':session['mimetype'],
                                          'file_size':session['filesize']}],
                                'status':'uploaded',
                                'user_name':session['user_name'],
                                'user_affiliation':session['affiliation'],
                                'upload_date':strftime("%Y-%m-%d %H:%M:%S", gmtime())}
                createDatasetMetaFile(dataset_dir, dataset_meta)
                template_dict['status'] = 'Uploaded'
                template_dict['filename_success'] = filename
                template_dict['dataset_success'] = dataset
                #reset form fields
                template_dict = resetSubmissionForm(template_dict)

        #posted geophysics metadata 
        elif request.form['submit'] == 'geophys_submit':
            meta_dict['Principal Investigator(s)'] = request.form.get(key='pi_list', default=None)
            meta_dict['Device'] = request.form.get(key='device', default=None)
            meta_dict['MGDS_Data_Type'] = request.form.get(key='mgds_data_type', default=None)
            meta_dict['Expedition or Compilation ID(s)'] = request.form.get(key='id', default=None)

        
            #upload the metadata to the file's Clowder page
            for field in meta_dict.keys():
               uploadMetadata(field, meta_dict[field])
            
            template_dict['metadata_status'] = 'done'

            runGeophysPreingest(meta_dict)

        
        else:
            pass

        
    return render_template("index.html", **template_dict)