gh-nb-webhook
=============

A webservice for GeoHopper to track "home" state and actuate Ninja Blocks webhooks

## About

GeoHopper is an iOS app to create geo fences (See http://geohopper.com)
NinjaBlocks is an 'Internet of Things' enabler (See http://www.ninjablocks.com)

GeoHopper can be configured (Add-on) to send notifications to web services of your choice. The idea is that every family member would have GeoHopper installed and sends a notification to the same web service (i.e., geo_ninja_websvc.php) This script tracks the cumulative status of your house - whether the house is empty or occupied, and calls NinjaBlocks webhooks. These webhooks in turn can be used in rules to, for example, arming and disarming alarms, sending notifications, etc

## Installation

1. Install GeoHopper on all your family members' phones (iOS only)
2. Install the geo_ninja_websvc.php file anywhere in your webserver.
3. On each phone, define your home as a region
4. Set up notifications to be sent to http://<your_server>/geo_ninja_websvc.php

## Configuration

1. Create a status file somewhere on your filesystem that holds the current state of your family members in JSON encoded format. For example:
{"husband":"home","wife":away"}
assumes 2 family members, 1 being away, and the other being home.

2. Within the php file, change the $family_members array to appropriately reflect your family. Note that email address needs to be the same that was configured in GeoHopper.

3. Also define the error log location and the status file location.

4. Setup some rules in Ninja Blocks. Debugging is enabled by default, so check the error log to help troubleshoot. 

