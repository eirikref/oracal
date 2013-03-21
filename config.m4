dnl
dnl $Id: config.m4,v 1.3 2003/11/20 20:56:00 eirikref Exp $
dnl

PHP_ARG_WITH(oracal, for Oracle Calendar support,
[  --with-oracal         Include Oracle Calendar support])

if test "$PHP_ORACAL" != "no"; then

    SEARCH_PATH="/usr/local /usr"
    SEARCH_FOR="/include/ctapi.h"
    if test -r $PHP_ORACAL/; then # path given as parameter
        ORACAL_DIR=$PHP_ORACAL
    else # search default path list
        AC_MSG_CHECKING([for Oracle Calendar SDK files in default path])
        for i in $SEARCH_PATH ; do
            if test -r $i/$SEARCH_FOR; then
                ORACAL_DIR=$i
                AC_MSG_RESULT(found in $i)
            fi
        done
    fi

    if test -z "$ORACAL_DIR"; then
        AC_MSG_RESULT([not found])
        AC_MSG_ERROR([Please reinstall the Oracle Calendar SDK])
    fi

    dnl # --with-oracal -> add include path
    PHP_ADD_INCLUDE($ORACAL_DIR/include)


    dnl # --with-oracal -> chech for lib and symbol presence
    LIBNAME=capi
    LIBSYMBOL=CSDK_Connect

    PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
    [
        PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $ORACAL_DIR/lib,
                                  ORACAL_SHARED_LIBADD)
        AC_DEFINE(HAVE_ORACALLIB,1,[ ])
    ],[
        AC_MSG_ERROR([wrong Oracle Calendar SDK version or SDK not found])
    ],[
        -L$ORACAL_DIR/lib -lm -ldl
    ])
    
    PHP_SUBST(ORACAL_SHARED_LIBADD)

    PHP_NEW_EXTENSION(oracal, oracal.c, $ext_shared)
fi
