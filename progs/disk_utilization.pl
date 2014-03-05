#!/usr/bin/env perl
use warnings;
use strict;

my %data;

open IOSTAT, "iostat -x -d 1 2 |" or die "Cannot start iostat: $!\n";
my $run = 0;
while (<IOSTAT>) {
	m/Device:/ && $run++;
	if ($run == 2) {
		m/(^[^\s+]+).*(\d+\.\d+)$/ and $data{$1} = $2;
	}
}
close IOSTAT;

while (my ($dev, $util) = each(%data)) {
	print "diskstats.utilization_$dev.graph.title=Disk Utilization\n";
	print "diskstats.utilization_$dev.graph.unit=%\n";
	print "diskstats.utilization_$dev.line.reads.value=$util\n";
	print "diskstats.utilization_$dev.line.reads.type=GAUGE\n";
}
