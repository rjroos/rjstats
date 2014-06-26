#!/usr/bin/python

import fileinput
import re
import sys
import subprocess

#ds[tcp_listen].index = 1
parser = re.compile("ds\[(.*?)\]\.(.*?)=(.*)")

if len(sys.argv) != 4:
	print "Usage: $0 <datadir> <ip> <group/service>"
	sys.exit(1)

datadir = sys.argv[1]
machine = sys.argv[2]
stat = sys.argv[3]

defBase = "DEF:%s=" +  datadir + "/%s/%s.rrd:%s:AVERAGE"
xportBase = "XPORT:%s:%s"
rrdFile = datadir + "/" + machine + "/" + stat + ".rrd"

def parseIt():
	p =subprocess.Popen(["rrdtool", "info", rrdFile], stdout=subprocess.PIPE)	
	out, err = p.communicate()
	datasources = {}
	for line in out.split("\n"):
		m = parser.match(line)
		if m:
			if datasources.has_key(m.group(1)):
				datasources[m.group(1)][m.group(2)] = m.group(3)
			else:
				datasources[m.group(1)] = {}
	return datasources
	
def getDef(key):
	return defBase % (key, machine, stat, key)

def getXport(key):
	return xportBase % (key, key)

def outputJsonXporter():
	datasources = parseIt()
	print(datasources)
	for key in datasources.keys():
		print getDef(key)
		print getXport(key)


outputJsonXporter()
