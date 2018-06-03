
This module alters the existing Drupal "site information" form and add new text field named "Site API Key" gets added to the "Site Information" form with the default value of “No API Key yet”..

This module also provides a endpoint that gives the JSON response of a given node with the content type "page" only if the configured API Key matched and node id (nid) of an appropriate node type are present, otherwise it will respond with "access denied".


## Example URL
http://hostname/page_json/FOOBAR/7

Where "FOOBAR" 7 is Site API Key and 7 is the nid


