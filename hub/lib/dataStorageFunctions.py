# -*- coding: utf-8 -*-
"""
Created on Mon Oct 31 2016

@author: Neville Shane
"""
import os
import json 

UPLOAD_DIR = "/Users/Neville/software/hub/hub/userspaces/"


"""
Return the user's allocated file spade.  If they don't have one yet, create it.
File space name is based on user's Orcid or Google ID
"""
def getUserSpace(session):
	user_dir = os.path.join(UPLOAD_DIR,session['id'])
	if not os.path.exists(user_dir):
		try:
			os.makedirs(user_dir)
			session['user_space'] = user_dir
			print("Successfully created user space: %s" %user_dir)
		except:
			print("Can't create user space: %s" %user_dir)
			return None
		#create a user metadata json file
		createUserMetaFile(session)
	else: 
		session['user_space'] = user_dir
	return user_dir

"""
Create a user metadata JSON file
"""
def createUserMetaFile(session):
	user_file = os.path.join(session['user_space'], 'user_metadata.json')
	user_data = {'id': session['id'],
	             'authentication': session['authentication'], 
	             'user_name': session['user_name'], 
	             'affiliation': session['affiliation']}
	try:
		with open(user_file, 'w') as outfile:
			json.dump(user_data, outfile)
		session['user_file'] = user_file
	except:
		print("Can't create user metadata file: %s" %user_file)

"""
Return the user_name from the user metadata JSON file
"""
def getUserNameFromUserMetaFile(session):
	user_file = os.path.join(session['user_dir'], 'user_metadata.json')
	try:
		with open(user_file) as infile:
			user_data = json.load(infile)
	except:
		print("Can't read user metadata file: %s" %user_file)
		return None
	if user_data.get('user_name'):
		return user_data['user_name']
	else:
		return None

"""
Add user_name to the user metadata JSON file
"""
def addUserNameToUserMetaFile(session, user_name):
	user_file = os.path.join(session['user_dir'], 'user_metadata.json')
	#read in JSON file
	try:
		with open(user_file) as infile:
			user_data = json.load(infile)
	except:
		print("Can't read user metadata file: %s" %user_file)
		return False
	user_data['user_name'] = user_name

	#rewrite JSON file
	try:
		with open(user_file, 'w') as outfile:
			json.dump(user_data, outfile)
		session['user_file'] = user_file
	except:
		print("Can't write user metadata file: %s" %user_file)

	return True

"""
Create a dataset metadata JSON filen
"""
def createDatasetMetaFile(dataset_dir, dataset_meta):
	dataset_file = os.path.join(dataset_dir, 'dataset_metadata.json')
	print(dataset_meta)
	try:
		with open(dataset_file, 'w') as outfile:
			json.dump(dataset_meta, outfile)
	except:
		print("Can't create dataset metadata file: %s" %dataset_file)