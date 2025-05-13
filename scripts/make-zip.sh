#!/bin/sh

PLUGINDIR="/home/mvitale/repo/tutorrio-tools"
NAMEPLUGIN="icalsender"
OUTPUTDIR=~
cd $PLUGINDIR
zip -r $OUTPUTDIR/$NAMEPLUGIN.zip  moodle-plugin-icalsender -x "*.git*" "*scripts*"

echo "Zip created under $OUTPUTDIR/$NAMEPLUGIN.zip "
