#!/bin/bash

SCRIPT_PATH="`dirname \"$0\"`"                   # relative
SCRIPT_PATH="`( cd \"$SCRIPT_PATH\" && pwd )`"   # absolutized and normalized
if [ -z "$SCRIPT_PATH" ] ; then
  # error; for some reason, the path is not accessible
  # to the script (e.g. permissions re-evaled after suid)
  exit 1  # fail
fi

## PHP_INCLUDE_PATH="`php -i | grep include_path | awk 'BEGIN{ FS=" => " }{ printf($NF) }'`:$SCRIPT_PATH"
## php -d include_path=${PHP_INCLUDE_PATH} oacreator.php $1 $2 $3 $4 $5 $6 $7 $8 $9

php $SCRIPT_PATH/oacreator.php $1 $2 $3 $4 $5 $6 $7 $8 $9

exit 0
