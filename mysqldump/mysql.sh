#!/usr/bin/env bash

host="ХОСТ"
dbname="БАЗА"
login="ПОЛЬЗОВАТЕЛЬ"
passwd="ПАРОЛЬ"
args="--protocol=tcp -h ${host} -u ${login} --password=${passwd} ${dbname}"

cd "`dirname "$0"`"
dump=""

if [ "$1" == "--dump" ]; then
    dump="dump"
    shift
fi

mysql"$dump" $args "$@"
