#!/bin/sh

# the absolute path to the project root
BASE_PATH=$(cd -P $(dirname $0)/../.. && pwd)

# get the commit details for the repository HEAD
OIFS=$IFS
IFS=$'\t'
COMMIT=($(git log --pretty=format:"%H%x09%h%x09%an%x09%ae%x09%at%x09%s" -1))

# write the commit details to the config file
cat > "${BASE_PATH}/conf/head.ini" <<EOL
hash=${COMMIT[0]}
hash_short=${COMMIT[1]}
author=${COMMIT[2]}
email=${COMMIT[3]}
timestamp=${COMMIT[4]}
message=${COMMIT[5]}
EOL

# restore the internal field separator
IFS=$OIFS