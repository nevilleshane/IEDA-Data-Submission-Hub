# -*- coding: utf-8 -*-
"""
Created on Thu Jul 21 10:37:59 2016

@author: Neville
"""

import flask

#from .lib import messaging

global fileid

def create_app(debug=False):
    app = flask.Flask(__name__, static_url_path='/hub/static')
    
    app.debug = debug

    SECRET_KEY = 'development key'
    app.secret_key = SECRET_KEY
   


    print("{0}App '{1}' created.{2}".format('\033[92m', __name__, '\033[0m'))
    
   #messaging.sendMessage("info", "Hub is running")

    # ---------------------
    # Register blueprints
    # ---------------------
    from .controllers.index import index_page
    from .controllers.login import login_page
    
    app.register_blueprint(index_page)
    app.register_blueprint(login_page)
    
    return app
