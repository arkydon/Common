#!/usr/bin/env bash

cd "`dirname "$0"`"

dir="./sql"
mkdir -p "$dir"

if [ "$1" == "--gz" ] && [ "$2" ]; then
    table="$2"
    count=`./mysql.sh -e "SELECT COUNT(${table}_id) FROM ${table}" -s --skip-column-names 2>/dev/null`
    step=`php -r "echo round(pow(10, round(log(${count}, 10))) / 100);"`
    if [ "$step" -lt "10" ]; then step="10"; fi
    if [ "$step" -gt "10000" ]; then step="10000"; fi
    echo "Table = ${table}; Count = ${count}; Step = ${step};"
    "$0" --compact -d "$table" | gzip -9 >"$dir"/"$table"_000000.sql.gz
    min=`./mysql.sh -e "SELECT MIN(${table}_id) FROM ${table}" -s --skip-column-names 2>/dev/null`
    while [ "$min" != "NULL" ] && [ "$min" ] && [ "$min" -gt "0" ]; do
        part=$((min / step))
        part=$((part + 1))
        path="$part"
        path=`printf "%06d" "$path"`
        path="${dir}/${table}_${path}.sql.gz"
        from=$((part * step - step + 1))
        to=$((part * step))
        where1="${table}_id >= ${from} AND ${table}_id <= ${to}"
        where2="${table}_id > ${to} LIMIT 1"
        "$0" --compact --skip-add-drop-table --skip-create-options \
            --no-create-info -w"$where1" "$table" 2>/dev/null | gzip -n -9 >"$path".new
        if [ -f "$path" ]; then
            md5sum1=`md5sum "$path" | awk '{ print $1 }'`
            md5sum2=`md5sum "$path".new | awk '{ print $1 }'`
            if [ "$md5sum1" != "$md5sum2" ]; then
                mv "$path".new "$path"
                echo "${from} .. ${to} .. ${path}"
            else
                rm "$path".new
            fi
        else
            mv "$path".new "$path"
            echo "${from} .. ${to} .. ${path}"
        fi
        min=`./mysql.sh -e "SELECT ${table}_id FROM ${table} WHERE ${where2}" -s --skip-column-names 2>/dev/null`
    done
else
    ./mysql.sh --dump "$@"
fi
