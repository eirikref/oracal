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
  | Authors: Eirik Refsdal <eirikref@pvv.ntnu.no>                        |
  |          Joe Pletcher <joepletcher@gmail.com>                        |
  |                                                                      |
  | With an absurd amount of help from:                                  |
  |     Oyvind Grønnesby <oyving@pvv.ntnu.no>                            |
  |     Pål Løberg <pallo@pvv.ntnu.no>                                   |
  |     Frode Lundgren <frodelu@pvv.ntnu.no>                             |
  |     Mats Lindh <matslin@pvv.ntnu.no>                                 |
  +----------------------------------------------------------------------+
*/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_oracal.h"


/* If you declare any globals in php_oracal.h uncomment this:
 */

ZEND_DECLARE_MODULE_GLOBALS(oracal)


/* True global resources - no need for thread safety here */
/* static int le_oracal; */
static int le_conn;
#define LE_CONN_NAME "Oracle Calendar Link"

/* Not sure about this one.  They used to be 35 characters long at
   most, but this seems to have changed in version 9.4.1.0 of the
   API */
#define UID_LENGTH 60

/* Maximum number of concurrent open agendas */
#define MAX_AGENDAS 32

CAPIStatus status = CAPI_STAT_OK;
const char *error_string = NULL;


typedef struct _oracal_connection {
    int          id;
    CAPISession  session;
    CAPIHandle  *agendas;
    int          open_agendas;
} oracal_connection;



void _close_oracal_conn(zend_rsrc_list_entry *rsrc TSRMLS_DC)
{
    oracal_connection *conn = (oracal_connection *) rsrc->ptr;

    if (conn->session != NULL) {
        if (conn->open_agendas > 0)
			CSDK_DestroyMultipleHandles(conn->session, conn->agendas,
										conn->open_agendas, CSDK_FLAG_NONE);
        CSDK_Disconnect(conn->session, CSDK_FLAG_NONE);
        CSDK_DestroySession(&(conn->session));
    }
   	efree(conn->agendas); 
   	efree(conn); 
}



/* {{{ oracal_functions[]
 *
 * Every user visible function must have an entry in oracal_functions[].
 */
function_entry oracal_functions[] = {
    PHP_FE(oracal_connect, NULL)
    PHP_FE(oracal_disconnect, NULL)
    PHP_FE(oracal_error, NULL)
    PHP_FE(oracal_get_capabilities, NULL)
    PHP_FE(oracal_get_agenda_info, NULL)
    PHP_FE(oracal_open_agenda, NULL)
    PHP_FE(oracal_close_agendas, NULL)
    PHP_FE(oracal_get_events_by_uid, NULL)
    PHP_FE(oracal_get_events_by_range, NULL)
    PHP_FE(oracal_delete_events, NULL)
    PHP_FE(oracal_store_events, NULL)
    {NULL, NULL, NULL}
};
/* }}} */



/* {{{ oracal_module_entry
 */
zend_module_entry oracal_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
    STANDARD_MODULE_HEADER,
#endif
    "oracal",
    oracal_functions,
    PHP_MINIT(oracal),
    PHP_MSHUTDOWN(oracal),
    NULL,
    NULL,
    PHP_MINFO(oracal),
#if ZEND_MODULE_API_NO >= 20010901
    "0.1",
#endif
    STANDARD_MODULE_PROPERTIES
};
/* }}} */


/* Read value from php.ini - if present.  Otherwise, set to default
   (MAX_AGENDAS).
   FIXME:  Alter to actually USE the "variable" MAX_AGENDAS
*/

PHP_INI_BEGIN()
     STD_PHP_INI_ENTRY("oracal.max_agendas", "32", PHP_INI_SYSTEM,
                       OnUpdateInt, max_agendas, zend_oracal_globals,
                       oracal_globals)
PHP_INI_END()



#ifdef COMPILE_DL_ORACAL
ZEND_GET_MODULE(oracal)
#endif



/* {{{ PHP_MINIT_FUNCTION
 */
PHP_MINIT_FUNCTION(oracal)
{
#ifdef ZTS
    php_oracal_globals *oracal_globals;

    ts_allocate_id(&oracal_globals_id, sizeof(php_oracal_globals), NULL, NULL);
    oracal_globals = ts_resource(oracal_globals_id);
#endif

    REGISTER_INI_ENTRIES();

    le_conn = zend_register_list_destructors_ex(_close_oracal_conn, NULL,
                                                LE_CONN_NAME, module_number);

    return SUCCESS;
}
/* }}} */



/* {{{ PHP_MSHUTDOWN_FUNCTION
 */
PHP_MSHUTDOWN_FUNCTION(oracal)
{
    UNREGISTER_INI_ENTRIES();
    return SUCCESS;
}
/* }}} */



/* This is the information that shows up in phpinfo().  We show off
   what we can without connecting to the server.  That is, information
   from the library it was compiled against plus the maximum number of
   concurrent open agendas, possibly set in php.ini. */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(oracal)
{
    CAPISession  session = NULL;
    const char  *tmp     = NULL;
    char         buf[32];

    php_info_print_table_start();
    php_info_print_table_header(2, "Oracle Calendar support", "enabled");

    CSDK_GetCapabilities(session, CAPI_CAPAB_VERSION, CSDK_FLAG_NONE, &tmp);
    php_info_print_table_row(2, "Calendar SDK version", tmp);
    
    CSDK_GetCapabilities(session, CAPI_CAPAB_MAXDATE, CSDK_FLAG_NONE, &tmp);
    php_info_print_table_row(2, "Largest supported date", tmp);

    CSDK_GetCapabilities(session, CAPI_CAPAB_UNSUPPORTED_ICAL_COMP,
                         CSDK_FLAG_NONE, &tmp);
    php_info_print_table_row(2, "Unsupported iCalendar components", tmp);

    sprintf(buf, "%ld", ORACAL_G(max_agendas));
    php_info_print_table_row(2, "Max open agendas per connection", buf);

    php_info_print_table_end();
}
/* }}} */



/* {{{ proto int oracal_connect(string host [, string username,
   string password])
   Establish connection with calendar server */
PHP_FUNCTION(oracal_connect)
{
    oracal_connection *conn = NULL;
    char              *hostname, *username, *password;
    int                host_len, user_len, pass_len;
    int               max_agendas;

	status = CAPI_STAT_OK;
	
    hostname = username = password = NULL;
    host_len = user_len = pass_len = 0;

	/* Check the given parameters */
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sss", &hostname,
                              &host_len, &username, &user_len, &password,
                              &pass_len) == FAILURE)
        RETURN_FALSE;
	
	max_agendas = (ORACAL_G(max_agendas))
        ? ORACAL_G(max_agendas)
        : MAX_AGENDAS;

	/* Allocate memory for the struct and it's contents.*/
    conn = (oracal_connection *) emalloc(sizeof(oracal_connection));
    conn->agendas = emalloc(sizeof(CAPIHandle) * max_agendas);
    conn->open_agendas = 0;

	/* Try to create a session by connecting to the server and
	   then authenticate using the given username and password.
	   Keep in mind that we _don't_ open an agenda automatically
	   like before, and this has to be done manually through
	   oracal_open_agenda(). */
    conn->session = CSDK_SESSION_INITIALIZER;
    status = CSDK_CreateSession(CSDK_FLAG_NONE, &(conn->session));
     
    if (CAPI_STAT_OK == status) {
        status = CSDK_Connect(conn->session, CSDK_FLAG_NONE, hostname);

        if (CAPI_STAT_OK == status && username && password)
            status = CSDK_Authenticate(conn->session, CSDK_FLAG_NONE,
                                       username, password);
    }

    if (CAPI_STAT_OK == status) {
        conn->id = ZEND_REGISTER_RESOURCE(return_value, conn, le_conn);
        RETURN_RESOURCE(conn->id);
	}

	/* Clean up if things go wrong */
    CSDK_Disconnect(conn->session, CSDK_FLAG_NONE);
    CSDK_DestroySession(&(conn->session));
    conn->session = NULL;
    conn->open_agendas = 0;
            
    efree(conn->agendas);
    efree(conn);
    RETURN_FALSE;
}
/* }}} */



/* {{{ proto bool oracal_disconnect(int link_identifier)
   Disconnect from the calendar server */
PHP_FUNCTION(oracal_disconnect)
{
    oracal_connection *conn;
    zval              *arg_link = NULL;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r",
                              &arg_link) == FAILURE)
        RETURN_FALSE;

	/* Fetch the connection/resource */
    ZEND_FETCH_RESOURCE(conn, oracal_connection *, &arg_link, -1, LE_CONN_NAME,
                        le_conn);

    if (conn->session != NULL) {
        if (conn->open_agendas > 0) 
            CSDK_DestroyMultipleHandles(conn->session, conn->agendas,
                                        conn->open_agendas, CSDK_FLAG_NONE);
        
        CSDK_Disconnect(conn->session, CSDK_FLAG_NONE);
        CSDK_DestroySession(&(conn->session));
        conn->session = NULL;
        conn->open_agendas = 0;

		/* These should not be commented out, even though I'm
		   sometimes tempted to :) */
        /*	efree(conn->agendas); */
        /* 	efree(conn); */
        
        zend_list_delete(Z_RESVAL_PP(&arg_link));

        RETURN_TRUE;
    }
    RETURN_FALSE;

}
/* }}} */



/* {{{ proto string oracal_error()
   Return the status string for the last operation */
PHP_FUNCTION(oracal_error)
{
    CSDK_GetStatusString(status, &error_string);

    if (strcmp((char *)error_string, "CAPI_STAT_OK"))
        RETURN_STRING((char *)error_string, 1);

}
/* }}} */



/* {{{ proto array oracal_get_capabilities([int link_identifier])
   Get capabilities */
PHP_FUNCTION(oracal_get_capabilities)
{
    CAPISession        session = CSDK_SESSION_INITIALIZER;
    oracal_connection *conn;
    zval              *link    = NULL;
    const char        *tmp     = NULL;
    char               buf[32];

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|r",
                              &link) == FAILURE)
        RETURN_FALSE;

    if (link != NULL)
        ZEND_FETCH_RESOURCE(conn, oracal_connection *, &link, -1, LE_CONN_NAME,
                            le_conn);

    array_init(return_value);
    
	/* List everything we know without connecting to a server, just
	   like in the phpinfo function above */
    CSDK_GetCapabilities(session, CAPI_CAPAB_VERSION, CSDK_FLAG_NONE, &tmp);
    add_index_stringl(return_value, 0, (char *) tmp, strlen(tmp), 1);
    
    CSDK_GetCapabilities(session, CAPI_CAPAB_MAXDATE, CSDK_FLAG_NONE, &tmp);
    add_index_stringl(return_value, 1, (char *) tmp, strlen(tmp), 1);

    CSDK_GetCapabilities(session, CAPI_CAPAB_UNSUPPORTED_ICAL_COMP,
                         CSDK_FLAG_NONE, &tmp);
    add_index_stringl(return_value, 2, (char *) tmp, strlen(tmp), 1);

    sprintf(buf, "%ld", ORACAL_G(max_agendas));
    add_index_stringl(return_value, 3, (char *) buf, strlen(buf), 1);


	/* Add the rest if we're actually connected to a server */
    if (conn->session != NULL) {
        CSDK_GetCapabilities(conn->session, CAPI_CAPAB_SERVER_VERSION,
                             CSDK_FLAG_NONE, &tmp);
        add_index_stringl(return_value, 4, (char *) tmp, strlen(tmp), 1);
        
        CSDK_GetCapabilities(conn->session, CAPI_CAPAB_UNSUPPORTED_ICAL_PROP,
                             CSDK_FLAG_NONE, &tmp);
        add_index_stringl(return_value, 5, (char *) tmp, strlen(tmp), 1);
        
        CSDK_GetCapabilities(conn->session, CAPI_CAPAB_AUTH, CSDK_FLAG_NONE,
                             &tmp);
        add_index_stringl(return_value, 6, (char *) tmp, strlen(tmp), 1);

        CSDK_GetCapabilities(conn->session, CAPI_CAPAB_COMP, CSDK_FLAG_NONE,
                             &tmp);
        add_index_stringl(return_value, 7, (char *) tmp, strlen(tmp), 1);

        CSDK_GetCapabilities(conn->session, CAPI_CAPAB_ENCR, CSDK_FLAG_NONE,
							&tmp);
        add_index_stringl(return_value, 8, (char *) tmp, strlen(tmp), 1);
    }
}
/* }}} */



/* {{{ proto array oracal_get_agenda_info(int link_identifier [, int index])
   Get information about a given agenda */
PHP_FUNCTION(oracal_get_agenda_info)
{
    oracal_connection *conn;
    zval              *link  = NULL;
    const char        *tmp   = NULL;
    int                index = -1;
	int 			   i	 = 0;
	
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r|l",
                              &link, &index) == FAILURE)
        RETURN_FALSE;

    ZEND_FETCH_RESOURCE(conn, oracal_connection *, &link, -1, LE_CONN_NAME,
                        le_conn);
        
    if (conn->session != NULL && conn->open_agendas > 0) {

		/* If no index was given as an argument, pick the last one
		   that was opened */
        if ((index < 0) || (index > (conn->open_agendas) - 1))
            index = conn->open_agendas - 1;
        
		/* Create an PHP array containing type of user, name and email */
        array_init(return_value);
        
        CSDK_GetHandleInfo(conn->session, conn->agendas[index],
                           CAPI_HANDLE_TYPE, &tmp);
        add_index_stringl(return_value, 0, (char *) tmp, strlen(tmp), 1);
    
        CSDK_GetHandleInfo(conn->session, conn->agendas[index],
                           CAPI_HANDLE_NAME, &tmp);
        add_index_stringl(return_value, 1, (char *) tmp, strlen(tmp), 1);

        CSDK_GetHandleInfo(conn->session, conn->agendas[index],
                           CAPI_HANDLE_MAILTO, &tmp);
        add_index_stringl(return_value, 2, (char *) tmp, strlen(tmp), 1);
    } else {
        zend_error(E_ERROR, "No open agendas");
        RETURN_FALSE;
    }

}
/* }}} */



/* {{{ proto bool oracal_open_agenda(int link_identifier, string username)
   Open an agenda */
PHP_FUNCTION(oracal_open_agenda)
{
    oracal_connection *conn;
    zval              *link     = NULL;
    char              *username;
    int                user_len;

	status = CAPI_STAT_OK;
	
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rs",
                              &link, &username, &user_len) == FAILURE)
        RETURN_FALSE;

    ZEND_FETCH_RESOURCE(conn, oracal_connection *, &link, -1, LE_CONN_NAME,
                        le_conn);

    if (conn->session != NULL && conn->open_agendas < ORACAL_G(max_agendas)) {

		/* Try to open the given agenda and add it to conn */
        conn->agendas[conn->open_agendas] = CSDK_HANDLE_INITIALIZER;
        status = CSDK_GetHandle(conn->session, username, CSDK_FLAG_NONE,
                                &(conn->agendas[conn->open_agendas]));

		/* FIXME?  Increase the number of open agendas and set the
		  next one to NULL. I really don't remember why I did this
		  :/ */
        if (CAPI_STAT_OK == status) {
            ++(conn->open_agendas);
            conn->agendas[conn->open_agendas] = NULL;
            RETURN_TRUE;
        }
		/* This however, I do remember.  I don't know how serious
		   it would be to leave it as "CSDK_HANDLE_INITIALIZER",
		   but somehow I feel more happy setting it to NULL
		   again. */
        conn->agendas[conn->open_agendas] = NULL;
        RETURN_FALSE;
    }
    zend_error(E_ERROR, "Maximum number of concurrent open agendas reached");
    RETURN_FALSE;
}
/* }}} */



/* {{{ proto bool oracal_close_agendas([int link_identifier])
   Close all open agendas */
PHP_FUNCTION(oracal_close_agendas)
{
    oracal_connection *conn;
    zval              *link = NULL;
	
	status = CAPI_STAT_OK;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r", 
				&link) == FAILURE) 
        RETURN_FALSE;

    ZEND_FETCH_RESOURCE(conn, oracal_connection *, &link, -1, LE_CONN_NAME,
                        le_conn);

    if (conn->session != NULL) {
		
		/* As you can see, it currently destroys every single agenda
		   that's open.  We should probably change this.  We could
		   accept an optional array to the PHP function containing the
		   agendas to close, and otherwise close all.  For the moment
		   though, it's good enough to just close them all. */

        status = CSDK_DestroyMultipleHandles(conn->session, conn->agendas,
                                             conn->open_agendas,
                                             CSDK_FLAG_NONE);
        //FIXME
        conn->open_agendas = 0;
        
        if (CAPI_STAT_OK == status)
            RETURN_TRUE;

		zend_error(E_ERROR, "Destroying sessions failed");
		RETURN_FALSE;
    }
	zend_error(E_ERROR, "No sessions non-null");
    RETURN_FALSE;
}
/* }}} */



/* {{{ proto string oracal_events_by_range(int link_identifier, string startdate, string enddate[, int flags])
   Get all events between two given points in time */
PHP_FUNCTION(oracal_get_events_by_range)
{
    CSDKRequestResult tmpresult            = CSDK_REQUEST_RESULT_INITIALIZER;
    CAPIStream         stream			   = CSDK_STREAM_INITIALIZER;
    oracal_connection *conn;
    zval              *link                = NULL;
    const char        *events              = NULL;
    char              *retstr              = NULL;
    char              *startdate, *enddate;
    int                start_len, end_len;
	unsigned long flags = CSDK_FLAG_NONE;
	
	status = CAPI_STAT_OK;
	
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rss|l",
                              &link, &startdate, &start_len, &enddate,
                              &end_len, (signed)&flags) == FAILURE)
        RETURN_FALSE;

    ZEND_FETCH_RESOURCE(conn, oracal_connection *, &link, -1, LE_CONN_NAME,
                        le_conn);
	
    if (conn->session != NULL && conn->open_agendas > 0) {

		/* Set of the memory stream to write into and try to fetch
		   events between the given dates. */
        status = CSDK_CreateMemoryStream(conn->session, &stream, NULL, &events,
                                         CSDK_FLAG_NONE);

        if (status == CAPI_STAT_OK ) {
            status = CSDK_FetchEventsByRange(conn->session,
                                             (CAPIFlag)flags,
                                             conn->agendas, startdate, enddate,
                                             NULL, stream, &tmpresult);

			/* Turn the returned data into a string suitable for
			   returning to the user's PHP code */
            if (CAPI_STAT_OK == status) {
                retstr = (char *) estrdup(events);
                CSDK_DestroyStream(conn->session, &stream);
                CSDK_DestroyResult(&tmpresult);
                RETURN_STRING(retstr, 0);
            }
        }
		CSDK_DestroyStream(conn->session, &stream);
		CSDK_DestroyResult(&tmpresult);
        RETURN_FALSE;
    }
    zend_error(E_ERROR, "No open agendas");
    RETURN_FALSE;
}
/* }}} */



/* {{{ proto string oracal_event_by_uid(int link_identifier, string uid[, string rid, int flags])
   Get an event by UID */
PHP_FUNCTION(oracal_get_events_by_uid)
{
    oracal_connection  *conn;
    CSDKRequestResult  tmpresult = CSDK_REQUEST_RESULT_INITIALIZER;
	CAPIStream          stream   = CSDK_STREAM_INITIALIZER;
	HashPosition        pos;
    zval               *link;
    zval               *phparray;
    zval              **element;
    char               *retstr    = NULL;
    const char         *events    = NULL;
	char              **sdkarray;
    unsigned int        count     = 0; 
	unsigned int        i         = 0;
	unsigned int        j         = 0;
	unsigned long flags = CSDK_FLAG_NONE;
	char				*rid = NULL;
	int					rid_len = 0;
	
	status = CAPI_STAT_OK;
	
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ra|sl", &link,
                              &phparray, &rid, &rid_len, 
							  (signed)&flags) == FAILURE) 
        RETURN_FALSE;

    ZEND_FETCH_RESOURCE(conn, oracal_connection *, &link, -1, LE_CONN_NAME,
                        le_conn);

    if (conn->session != NULL && Z_TYPE_P(phparray) == IS_ARRAY &&
        zend_hash_num_elements(Z_ARRVAL_P(phparray)) > 0) {

		/* Allocate memory for the "array of strings" */
        count = zend_hash_num_elements(Z_ARRVAL_P(phparray));

		if (count <= 0) 
			RETURN_FALSE;

		sdkarray = emalloc(sizeof(char *) * (count + 1));
		
		for (j = 0; j < count; ++j)
			sdkarray[j] = emalloc(sizeof(char) * UID_LENGTH);
		
		/* Turn the PHP array into an array suitable for the CSDK */
		for (zend_hash_internal_pointer_reset_ex(Z_ARRVAL_P(phparray), &pos);
			 zend_hash_get_current_data_ex(Z_ARRVAL_P(phparray),
									(void **) &element, &pos) == SUCCESS;
			 zend_hash_move_forward_ex(Z_ARRVAL_P(phparray), &pos)) {
			
			/* Turn the PHP string into a C "string" and put it into
			   the array */
			register char *str;
			convert_to_string_ex(element);
			ZVAL_STRINGL(return_value, Z_STRVAL_PP(element),
						 Z_STRLEN_PP(element), 1);
			str = Z_STRVAL_P(return_value);
			
			strncpy(sdkarray[i], str + '\0', strlen(str) + 1);
			++i;
		}
		
		/* The last element of the array has to be NULL or an empty
		   string, according to the SDK documentation */
		sdkarray[count] = NULL;
		
		/* Set up a stream for the CSDK to write into and try to
		   fetch the events */
		status = CSDK_CreateMemoryStream(conn->session, &stream, NULL,
										 &events, CSDK_FLAG_NONE);
		
		if (CAPI_STAT_OK == status)
			status = CSDK_FetchEventsByUID(conn->session,
										   (CAPIFlag)flags, NULL,
										   (CAPIUIDSet) sdkarray, rid,
										   CAPI_THISINSTANCE, NULL,
										   stream, &tmpresult);
		
		/* Free the memory allocated for the C array */
		for (j = 0; j < count; ++j) 
			efree(sdkarray[j]);
		efree(sdkarray);
		
		/* Return the data from the stream */
		if (CAPI_STAT_OK == status) {
			retstr = (char *) estrdup(events);
			CSDK_DestroyStream(conn->session, &stream);
			CSDK_DestroyResult(&tmpresult);
			RETURN_STRING(retstr, 0);
		}
		CSDK_DestroyStream(conn->session, &stream);
		CSDK_DestroyResult(&tmpresult);
		RETURN_FALSE;
	}
}
/* }}} */



/* {{{ proto string oracal_delete_by_uid(int link_identifier, array uid[, int flags, string datetime])
   Delete an event by UID */
PHP_FUNCTION(oracal_delete_events)
{
    oracal_connection  *conn;
    CSDKRequestResult  tmpresult = CSDK_REQUEST_RESULT_INITIALIZER;
	HashPosition        pos;
    zval               *link;
    zval               *phparray;
    zval              **element;
	char              **sdkarray;
	const char 		   *datetime = NULL;
	int				   date_len  = 0;
    unsigned int       count     = 0;
	unsigned int       i         = 0;
	unsigned int       j         = 0;
	unsigned long 	   flags 	 = CSDK_FLAG_NONE;
	status = CAPI_STAT_OK;
	
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "ra|ls", &link,
                              &phparray, (signed)&flags,
							  &datetime, &date_len) == FAILURE) 
        RETURN_FALSE;

    ZEND_FETCH_RESOURCE(conn, oracal_connection *, &link, -1, LE_CONN_NAME,
                        le_conn);

    if (conn->session != NULL && Z_TYPE_P(phparray) == IS_ARRAY &&
        zend_hash_num_elements(Z_ARRVAL_P(phparray)) > 0) {

		/* Allocate memory for the "array of strings" */
        count = zend_hash_num_elements(Z_ARRVAL_P(phparray));

		if (count <= 0) 
			RETURN_FALSE;
		
		sdkarray = emalloc(sizeof(char *) * (count + 1));

		for (j = 0; j < count; ++j) 
			sdkarray[j] = emalloc(sizeof(char) * UID_LENGTH);

		/* Turn the PHP array into an array suitable for the CSDK */
		for (zend_hash_internal_pointer_reset_ex(Z_ARRVAL_P(phparray), &pos);
				 zend_hash_get_current_data_ex(Z_ARRVAL_P(phparray),
				 (void **) &element, &pos) == SUCCESS;
		 		 zend_hash_move_forward_ex(Z_ARRVAL_P(phparray), &pos), i++) {
				
			/* Turn the PHP string into a C "string" and put it into
			   the array */
			register char *str;
			convert_to_string_ex(element);
			ZVAL_STRINGL(return_value, Z_STRVAL_PP(element),
						 Z_STRLEN_PP(element), 1);
			str = Z_STRVAL_P(return_value);
			
			strncpy(sdkarray[i], str + '\0', strlen(str) + 1);
		}

		/* The last element of the array has to be NULL or an empty
		   string, according to the SDK documentation */
		sdkarray[count] = NULL;
		/*
		 * As far as I can tell, CAPI_THISANDPRIOR and CAPI_THISANDFUTURE
		 * flags do not work. If you can get them to work, please let me know
		 */
		status = CSDK_DeleteEvents(conn->session, (CAPIFlag)flags,
								   (CAPIUIDSet) sdkarray, datetime,
								   CAPI_THISINSTANCE, &tmpresult);
	}
		
	/* Free the memory allocated for the C array */
	for (j = 0; j < count; ++j) 
		efree(sdkarray[j]);
	
	efree(sdkarray);
	
	if (CAPI_STAT_OK == status) {
		CSDK_DestroyResult(&tmpresult);
		RETURN_TRUE;
	}
	CSDK_DestroyResult(&tmpresult);
	RETURN_FALSE;
}
/* }}} */



/* {{{ proto array oracal_store_events(int link_identifier, string data[, int flags])
   Create one or more events in the calendar */
PHP_FUNCTION(oracal_store_events)
{

    zval              *link          = NULL;
    oracal_connection *conn;
    char              *data          = NULL;
    int                data_len      = 0;
    CAPIStream         stream        = CSDK_STREAM_INITIALIZER;
    CSDKRequestResult tmpresult      = CSDK_REQUEST_RESULT_INITIALIZER;
	CAPIStatus         result_status = CAPI_STAT_OK;
	CAPIStatus         loop_status   = CAPI_STAT_OK;
	const char        *result_uid    = NULL;
	unsigned int               i     = 0;
	unsigned long flags = CSDK_FLAG_STORE_CREATE;

	status = CAPI_STAT_OK;
	
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rs|l", &link, &data, 
				&data_len, (signed)&flags) == FAILURE)
        RETURN_FALSE;

    ZEND_FETCH_RESOURCE(conn, oracal_connection *, &link, -1, LE_CONN_NAME,
                        le_conn);

    if (conn->session != NULL) {

        status = CSDK_CreateMemoryStream(conn->session, &stream, data, NULL,
                                         CSDK_FLAG_NONE);

        if (CAPI_STAT_OK != status) {
			CSDK_DestroyStream(conn->session, &stream);
			RETURN_FALSE;
		}

        /* Store the events. */
		status = CSDK_StoreEvents(conn->session, (CAPIFlag)flags, 
								stream, &tmpresult);
		
		if (CAPI_STAT_OK != status && CAPI_STAT_API_NULL != status) {
			CSDK_DestroyStream(conn->session, &stream);
			CSDK_DestroyResult(&tmpresult);
			RETURN_FALSE;
		}
		
		/* In case we managed to store the events, return an array
		   containing the UIDs from the events just created. */
		array_init(return_value);

		loop_status = CSDK_GetFirstResult(tmpresult, NULL, &result_uid,
											  &result_status);
		for (i = 0; loop_status != CAPI_STAT_DATA_RRESULT_EOR &&
			   	loop_status != CAPI_STAT_API_NULL; i++) {
			add_index_stringl(return_value, i, (char *) result_uid,
							  strlen(result_uid), 1);
			loop_status = CSDK_GetNextResult(tmpresult, NULL, &result_uid,
											 &result_status);
		}
		CSDK_DestroyStream(conn->session, &stream);
		CSDK_DestroyResult(&tmpresult);
    }
}
/* }}} */



/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
