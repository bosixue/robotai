#!/bin/sh
/usr/local/bin/inotifywait -mrq -e modify,attrib,move,create,delete /www/wwwroot/47.114.107.154/uploads/audio/ | while read file
do
    rsync -av /www/wwwroot/47.114.107.154/uploads/audio/ 47.114.77.165:/var/smartivr/uploads/audio/
    echo "$file在`date +'%F %T %A'`同步成功" >> /var/log/rsync.log
done
