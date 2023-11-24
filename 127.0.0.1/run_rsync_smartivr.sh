#!/bin/sh
/usr/local/bin/inotifywait -mrq -e modify,attrib,move,create,delete /www/wwwroot/47.114.107.154/uploads/asrapi/ | while read file
do
    rsync -av --delete /www/wwwroot/47.114.107.154/uploads/asrapi/ 47.114.77.165:/var/smartivr/asrapi/
    array=(${file// / })
    smartivr_file=${array[2]}
    ssh root@47.114.77.165 "fs_cli -x \"vad_config reload asrconfig /var/smartivr/asrapi/${smartivr_file}\""
    echo "$file在`date +'%F %T %A'`同步成功" >> /var/log/rsync.log
done
