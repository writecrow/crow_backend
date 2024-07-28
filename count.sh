#!/bin/sh
dirname=corpus_data
tempfile=myTempfileName
find $dirname -type f  > $tempfile
cat $tempfile | sed 's_.*/__' | sort |  uniq -d|
while read fileName
do
 grep "/$fileName" $tempfile
done
#rm -f $tempfile
