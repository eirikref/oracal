/*
  +----------------------------------------------------------------------+
  | PHP Version 5                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2004 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 2.02 of the PHP license,      |
  | that is bundled with this package in the file LICENSE, and is        |
  | available at through the world-wide-web at                           |
  | http://www.php.net/license/2_02.txt.                                 |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Author: Eirik Refsdal <eirikref@pvv.ntnu.no>                         |
  |                                                                      |
  | With an absurd amount of help from:                                  |
  |     Øyvind Grønnesby <oyving@pvv.ntnu.no>                            |
  |     Pål Løberg <pallo@pvv.ntnu.no>                                   |
  |     Frode Lundgren <frodelu@pvv.ntnu.no>                             |
  |     Mats Lindh <matslin@pvv.ntnu.no>                                 |
  +----------------------------------------------------------------------+

  $Id: php_oracal.h,v 1.4 2004/02/12 05:26:17 eirikref Exp $ 
*/

#ifndef PHP_ORACAL_H
#define PHP_ORACAL_H

extern zend_module_entry oracal_module_entry;
#define phpext_oracal_ptr &oracal_module_entry

#ifdef PHP_WIN32
#define PHP_ORACAL_API __declspec(dllexport)
#else
#define PHP_ORACAL_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

#include <ctapi.h>

PHP_MINIT_FUNCTION(oracal);
PHP_MSHUTDOWN_FUNCTION(oracal);
PHP_RINIT_FUNCTION(oracal);
PHP_RSHUTDOWN_FUNCTION(oracal);
PHP_MINFO_FUNCTION(oracal);

PHP_FUNCTION(oracal_connect);
PHP_FUNCTION(oracal_disconnect);
PHP_FUNCTION(oracal_error);
PHP_FUNCTION(oracal_get_capabilities);
PHP_FUNCTION(oracal_get_agenda_info);
PHP_FUNCTION(oracal_open_agenda);
PHP_FUNCTION(oracal_close_agendas);
PHP_FUNCTION(oracal_get_events_by_range);
PHP_FUNCTION(oracal_get_events_by_uid);
PHP_FUNCTION(oracal_delete_events);
PHP_FUNCTION(oracal_store_events);
/* 
  	Declare any global variables you may need between the BEGIN
	and END macros here:     
*/

ZEND_BEGIN_MODULE_GLOBALS(oracal)
	long max_agendas;
ZEND_END_MODULE_GLOBALS(oracal)


/* In every utility function you add that needs to use variables 
   in php_oracal_globals, call TSRM_FETCH(); after declaring other 
   variables used by that function, or better yet, pass in TSRMLS_CC
   after the last function argument and declare your utility function
   with TSRMLS_DC after the last declared argument.  Always refer to
   the globals in your function as ORACAL_G(variable).  You are 
   encouraged to rename these macros something shorter, see
   examples in any other php module directory.
*/

#ifdef ZTS
#define ORACAL_G(v) TSRMG(oracal_globals_id, zend_oracal_globals *, v)
#else
#define ORACAL_G(v) (oracal_globals.v)
#endif

#endif	/* PHP_ORACAL_H */


/*
typedef struct _php_oracal_globals {
        long max_agendas;
} php_oracal_globals;

typedef php_oracal_globals zend_oracal_globals;
*/

/*
#ifdef ZTS
#define oracal(v) TSRMG(oracal_globals_id, php_oracal_globals *, v)
#else
#define oracal(v) (oracal_globals.v)
#endif
*/

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 */
