#!/bin/sh

CURRENT_BRANCH=`git branch --no-color | grep '*' | cut -d " " -f 2`
BRANCH=`echo $CURRENT_BRANCH | cut -d "/" -f 1`
VERSION=`echo $CURRENT_BRANCH | cut -d "/" -f 2`

if [ $BRANCH != 'release' ]; then
    echo "this script must be executed on 'release' branch." >&2
    exit 1;
fi

for directory in `find . -type d -maxdepth 1`
do
    if [ $directory == "." ]; then
        continue;
    fi
    if [ ${directory:2:1} == "." ]; then
        continue;
    fi
    for file in `find $directory -type f`
    do
        sed -i "" "/VERSION/s/[0-9]\.[0-9]\.[0-9]/$VERSION/g" $file
    done
done
