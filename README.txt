WebEx Acitivity (mod_webexactivity)

Maintainer  Eric Merrill (merrill@oakland.edu)
Copyright   2014 Oakland University
License     GNU GPL v3 or later http://www.gnu.org/copyleft/gpl.html
=========

A Moodle activity module for Cisco WebEx.

Disclaimer
==========
This module is in no way affiliated with, endorsed by, or approved by Cisco. 
As specified in GNU GPL v3, this software is provided as is, without any warranty.


Current Limitations
===================
As this software is in beta, you should see the Limitations and Bugs list at:
https://github.com/merrill-oakland/mod_webexactivity/wiki/Limitations-and-Bugs

Highlights for this release:
- Backup and restore does not work.
- When a Moodle user is used to create or host a meeting, a new WebEx user is created for them, with the prefix setting prepended to
  the username. If the email address is already taken, the user will be redirected to a field to enter their WebEx password. A
  workaround for this is to change the users existing email address in WebEx from something like user1@example.com to
  user1+webex@example.com. Everything between the + and the @ is ignored by email systems.
- Only Meeting Center and Training Center are currently supported.
- User WebEx passwords are stored in the database.
  This is because WebEx doesn't provide a way or retrieving a token for authentication, we must send the password with each request.


Documentation
=============
For Requirements, Installation information, and the Change Log, please visit:
https://github.com/merrill-oakland/mod_webexactivity/wiki
