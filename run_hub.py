# -*- coding: utf-8 -*-
"""
Created on Thu Jul 21 11:29:42 2016

@author: Neville
"""

import argparse

# ---------------------------
# Parse command line options
# ---------------------------
parser = argparse.ArgumentParser(description='script to start hub')
parser.add_argument('-d','--debug',
                     help='Launch app in debug mode.',
                     action="store_true",
                     required=False)
parser.add_argument('-p','--port',
                    help='Port to use in debug mode.',
                    default=5000,
                    type=int,
                    required=False)
args = parser.parse_args()

# ---------------------
# Create app instance
# ---------------------
from hub import create_app

app = create_app(debug=args.debug)

if __name__ == "__main__":
    if args.debug:
        app.run(debug=args.debug, port=args.port)
    else:
        app.run()
