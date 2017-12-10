#!/bin/bash

# This is a sample file that shows you how to run the monitor from the command line. You
# can either run it without looping and waiting and utilize a cron job to run this script
# periodically ( to do so, make sure that the 'run_once' field of your config file is set
# to true) - or you can run it as a standalone process.

docker run --rm --name gats-monitor -v /data/monitor:/etc/gats-monitor gatsiva/gats-monitor:latest
