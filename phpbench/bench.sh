#!/bin/bash

[ "$1" ] || exit
echo "Threads: ${1}"
for i in `seq 1 "$1"`; do
    php bench.php "$i" &
done
wait
