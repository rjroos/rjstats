#! /bin/sh
# $Id: netfilter.sh 9898 2011-03-08 13:07:12Z ronald $

SYSCTL="/sbin/sysctl -n -e"
COUNT=`$SYSCTL net.ipv4.netfilter.ip_conntrack_count`
test -z "$COUNT" && exit 0
MAX=`$SYSCTL net.ipv4.netfilter.ip_conntrack_max`

echo "netfilter.conntrack.graph.title=Netfilter"
echo "netfilter.conntrack.line.ip_conntrack_count.value=$COUNT"
echo "netfilter.conntrack.line.ip_conntrack_count.type=GAUGE"
echo "netfilter.conntrack.line.ip_conntrack_max.value=$MAX"
echo "netfilter.conntrack.line.ip_conntrack_max.type=GAUGE"
