# -*- coding: utf-8 -*-
"""
Created on Fri Nov 11 2016

@author: Neville Shane
"""

import tika
from tika import detector

GEOPHYSICS_MIMETYPES = ['application/x-netcdf']
GEOCHEMESTRY_MIMETYPES = ['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']

"""
Use Tika to determine the mime type of the file
"""
def determineFiletype(filepath):
    mimetype = detector.from_file(filepath)
    print(mimetype)
    if (mimetype in GEOPHYSICS_MIMETYPES): return mimetype, 'Geophysics'
    if (mimetype in GEOCHEMESTRY_MIMETYPES): return mimetype, 'Geochemistry'
    return mimetype, 'unknown'