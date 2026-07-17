#!/bin/sh

PLUGINDIR="/home/mvitale/repo-tutorrio"
NAMEPLUGIN="local_icalsender"
OUTPUTDIR=~
cd $PLUGINDIR

zip -r "$OUTPUTDIR/$NAMEPLUGIN.zip" moodle-local_icalsender \
  -x "*/.git/*" \
  -x "*/scripts/*" \
  -x "*/.agents/*" \
  -x "*/.codex/*" \
  -x "*/moodledata/*" \
  -x "*/moodle/*" \
  -x "*/node_modules/*"

echo "Zip created under $OUTPUTDIR/$NAMEPLUGIN.zip "
