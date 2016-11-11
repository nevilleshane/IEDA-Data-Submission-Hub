# -*- coding: utf-8 -*-
"""
Created on Thu Oct 27

@author: Neville Shane
"""
import flask
from flask import Flask, request, redirect, url_for, session, \
     render_template, json, jsonify
from flask_oauth import OAuth

from index import index_page
import index
import requests
from ..lib.dataStorageFunctions import getUserSpace, getUserNameFromUserMetaFile, addUserNameToUserMetaFile

login_page = flask.Blueprint("login_page", __name__)

# configuration
SECRET_KEY = 'development key'

# You must configure these 3 values from Google APIs console
# https://code.google.com/apis/console
ROOT = 'http://localhost:5000'
GOOGLE_CLIENT_ID = '540890461252-s9hflnu39ae5j5vfqsnq6anrkk26paj6.apps.googleusercontent.com'
GOOGLE_CLIENT_SECRET = 'AfkXtb1Y1Du6rhJ2ikgXT47x'
GOOGLE_REDIRECT_URI = '/google_authorized'  # one of the Redirect URIs from Google APIs console
ORCID_CLIENT_ID = 'APP-U3XAEAFHC1QP8RQQ'
ORCID_CLIENT_SECRET = 'c88e1190-6f90-45be-a3a2-03da2941e425'
ORCID_REDIRECT_URI = '/orcid_authorized'  # one of the Redirect URIs from Google APIs console
ORDCID_AUTH_URL = 'https://orcid.org/oauth/authorize'

# setup flask
# app = Flask(__name__)
# app.debug = DEBUG
# app.secret_key = SECRET_KEY
oauth = OAuth()

google = oauth.remote_app('google',
                          base_url='https://www.google.com/accounts/',
                          authorize_url='https://accounts.google.com/o/oauth2/auth',
                          request_token_url=None,
                          request_token_params={'scope': 'https://www.googleapis.com/auth/userinfo.email',
                                                'response_type': 'code'},
                          access_token_url='https://accounts.google.com/o/oauth2/token',
                          access_token_method='POST',
                          access_token_params={'grant_type': 'authorization_code'},
                          consumer_key=GOOGLE_CLIENT_ID,
                          consumer_secret=GOOGLE_CLIENT_SECRET)


orcid = oauth.remote_app('orcid',
                          base_url='https://orcid.org/oauth/',
                          authorize_url='https://orcid.org/oauth/authorize',
                          request_token_url=None,
                          request_token_params={'scope': '/authenticate',
                                                'response_type': 'code'},
                          access_token_url='https://pub.orcid.org/oauth/token',
                          access_token_method='POST',
                          access_token_params={'grant_type': 'authorization_code'},
                          consumer_key=ORCID_CLIENT_ID,
                          consumer_secret=ORCID_CLIENT_SECRET)


@login_page.route('/google_login')
def google_login():
    callback=url_for('login_page.google_authorized', _external=True)
    return google.authorize(callback=callback)

@login_page.route('/orcid_login')
def orcid_login():
    callback=url_for('login_page.orcid_authorized', _external=True)
    return orcid.authorize(callback=callback)


@login_page.route(GOOGLE_REDIRECT_URI)
@google.authorized_handler
def google_authorized(resp):
    print resp
    access_token = resp['access_token']
    session['access_token'] = access_token, ''
    headers = {'Authorization': 'OAuth '+access_token}
    from urllib2 import Request, urlopen, URLError
    req = Request('https://www.googleapis.com/oauth2/v1/userinfo',
                  None, headers)
    try:
        res = urlopen(req)
        #email = json.loads(res.read())['email']
        data = json.loads(res.read())
        email = data['email']
        print (data)
        session['user_name']  = data['name']
        session['id'] = data['id']
        session['affiliation'] = ''
        session['authentication'] = 'Google'
        getUserSpace(session)

        """
        Only get data['name'] if user has a Google+ account
        If they don't we need to ask for one and store it in 
        User metadata file
        """
        if data['name'] == "" : 
            #check if name is already in user metadata file
            name_in_file = getUserNameFromUserMetaFile(session)
            if (name_in_file == None or name_in_file == ""):
            	session['ask_for_name'] = True
            else: 
            	session['user_name'] = name_in_file


    except URLError, e:
        if e.code == 401:
            # Unauthorized - bad token
            session.pop('access_token', None)
            return redirect(url_for('login_page.google_login'))
        return res.read()
    return redirect('/')

@login_page.route(ORCID_REDIRECT_URI)
@orcid.authorized_handler
def orcid_authorized(resp):
    print(resp)
    session['user_name'] = resp['name']
    session['id'] = resp['orcid']
    #see if the user has a current employer set in their Orcid account
    try:
	    r = requests.get('https://pub.orcid.org/v1.2/'+resp['orcid']+'/affiliations', headers={'accept': 'application/json'}).json()
	    affiliation = r['orcid-profile']['orcid-activities']['affiliations']['affiliation'][0]['organization']['name']
	    session['affiliation'] = affiliation
	    session['authentication'] = 'Orcid'
    except:
    	print('no affiliation found')
        session['affiliation'] = ''
    getUserSpace(session)
    return redirect('/')
    #return jsonify(r)

@google.tokengetter
def get_access_token():
    return session.get('access_token')
@orcid.tokengetter
def get_access_token():
    return session.get('access_token')

@login_page.route('/logout')
def logout():
	session.pop('user_name', None)
	session.pop('id', None)
	session.pop('affiliation', None)
	session.pop('user_space', None)
	session.pop('authentication', None)
	return redirect('/')

@login_page.route('/add_name', methods=['POST'])
def add_name():
	if request.method == 'POST':
		session['user_name'] = request.form['user_name']
		session['ask_for_name'] = False
		#add to user metadata file
		addUserNameToUserMetaFile(session, request.form['user_name'])
	return redirect('/')

