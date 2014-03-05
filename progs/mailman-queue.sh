#! /bin/sh
# $Id: mailman-queue.sh 9228 2010-12-06 10:07:38Z rene $

QFILES=/var/lib/mailman/qfiles
if ! test -d $QFILES; then
	exit 1
fi
cd $QFILES

echo "mailman.qfiles.graph.title=Mailman qfiles"
for i in *
do
	COUNT=`ls $i | wc -l`
	echo "mailman.qfiles.line.$i.value=$COUNT"
	echo "mailman.qfiles.line.$i.type=GAUGE"
done
