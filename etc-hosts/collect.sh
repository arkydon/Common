#!/bin/bash

if [ -n "$1" ]; then
    host -t A -W 2 "$1" >/dev/null 2>&1 && echo "$1"
    exit
fi

this="$0"
this=`realpath "$this"`

dir=`mktemp -d`
cd "$dir"
echo "CWD: `pwd`"

wget -q 'http://winhelp2002.mvps.org/hosts.txt' -O 1.txt
wget -q 'http://pgl.yoyo.org/as/serverlist.php?showintro=0;hostformat=hosts' -O 2.txt
wget -q 'http://hostsfile.mine.nu/Hosts' -O 3.txt
cat 1.txt 2.txt 3.txt | awk '{print $2}' | grep -E '^([0-9a-z])' | sort | uniq > all1.txt
rm -f 1.txt 2.txt 3.txt
c=`cat all1.txt | grep -c .`
echo "ALL1: ${c}"

wget -q 'https://easylist-downloads.adblockplus.org/ruadlist+easylist.txt' -O 1.txt
cat 1.txt | grep -Eo '^\|\|[a-z0-9]+(\.[a-z0-9]+)+\^' | cut -c 3- | rev | cut -c 2- | rev | sort | uniq > all2.txt
rm -f 1.txt
c=`cat all2.txt | grep -c .`
echo "ALL2: ${c}"

cat all1.txt all2.txt | tr '[:upper:]' '[:lower:]' | tr -d '\r' | sort | uniq > all.txt
rm -f all1.txt all2.txt
c=`cat all.txt | grep -c .`
echo "ALL: ${c}"

cat all.txt | parallel --no-notice -j 32 "$this" "{}" > hosts.txt
cat hosts.txt | awk '{print "127.0.0.1 " $1}' > hosts.new.txt
mv hosts.new.txt hosts.txt
