#!/bin/bash
code_path=/www/wwwroot/127.0.0.1
dir_name=$(date '+%Y%m%d' )
log_path=$code_path/data/log/hangup/$dir_name
mkdir -p $log_path
for((i=1;i<=30;i++));
do
nohup php $code_path/index.php command/tel/hangup  >> $log_path/info.log 2>&1 &
done
