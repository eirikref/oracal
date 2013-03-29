Oracal: PHP Extension for Oracle Calendar
=========================================

This is version .4 of Oracal, a PHP extension that allows PHP to talk to an
Oracle Calendar Server. Currently, it supports the following basic features:
adding, modifying, and deleting events. Recurrences (add, modify, delete) are
also supported. Please see php_functions.txt for a list of PHP functions
available. There is a nice set of PHP classes already written to make using the
plugin easier found in the php_lib folder. The classes that are used by the
other files are found in oracallib.php. You may use the oracallib.php to build
a customized PHP interface.

A WORD OF WARNING
-----------------
This extension was written between 2003 and 2004 (or something like
that), has not been compiled since, and is not being maintained. I
happened to stumble upon the source code on an old computer and
figured it couldn't harm to share it on GitHub. Please just don't have
any expectations that bugs will be fixed or feature requests taken
care of.

NOTES
-----
- The Oracle SDK should be used as a definitive guide in almost all cases.
- Please see https://github.com/eirikref/oracal/ for more details.

INSTALL
-------
The installation procedures have not been modified for the .45 release.

1. Locate your PHP source code directory, enter the directory ext and unpack the
Oracle Calendar PHP Extension source code. Make sure it ends up in a
subdirectory called oracal. This should happen automatically, but double-check
to be on the safe side.
2. Put ctapi.h in /usr/local/include and the library files in /usr/local/lib.
3. Make sure /etc/ld.so.conf includes both /usr/local/lib and
$ORACLE_HOME/lib (substitute $ORACLE_HOME with the name of the directory where
you installed the calendar server) and rerun ldconfig to make sure the system
is aware these libraries.
4. Make sure that the PHP configure script is aware that we've put a new
extension in the ext subdirectory. The quick and dirty way to do this is to
delete the file <configure> and run <./buildconf --force>.
5. If, and only if, you need to run buildconf more than once, make sure you
delete the directory autom4te.cache.
6. Run <./configure --help | grep oracal> and look for a line saying
"--with-oracal Include Oracle Calendar support" to make sure that the configure
script is aware of the Oracle Calendar extension.
7. Next you have to run configure as usual, but include
--with-oracal[=shared], and then run make and make install as usual.
8. If you used --with-oracal=shared, locate your php.ini file and make sure
that it contains the line extension=oracal.so.
9. Restart Apache.

KNOWN BUGS
----------
- As far as I can tell, passing CAPI_THISANDFUTURE or CAPI_THISANDPAST does not
seem to work. If you have any suggestions please contact me.
- Does not install on Solaris. Again, if you have any suggestions, please
contact me.
- Has not been tested with anything other than PHP 4.3.11 and Apache 1.3.36
- PHP5 support:
As you've probably noticed, v0.45 only compiles against PHP4. But find line 134
(the function PHP_INI_BEGIN()), substitute OnUpdateInt with OnUpdateLong and
you're in business.

TODO
----
- Test with PHP5
- Add Solaris Support
- Change install procedure to make it easier/one version for PHP4/5
- Add the ability to add daily notes
- Enhance the modify abilities in the PHP

CREDIT
------
Eirik Refsdal <eirikref@gmail.com> created and laid the original framework with
version .3 and huge amounts of credit go to him for his work. Without his
original files, none of our modifications would have been possible. Joe
Pletcher rewrote most of the C in oracal.c, and Mike MacDonald rewrote almost
all of the original PHP. For version .45, Mike Herring cleaned up various
aspects of both oracal.c and the assorted php files.

If anyone actually uses this, send Joe an email, as he is pretty sure making
releases public is pointless. 

CONTACT
-------
- herringm@denison.edu - oracal.c, minor php changes
- joepletcher@gmail.com - oracal.c, minor php changes
- mjm85@case.edu - *.php
