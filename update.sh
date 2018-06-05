#!/bin/bash

 (
  c=10
 while [ $c -ne 110 ]
   do
     echo $c
     echo "###"
     echo "$c %"
     echo "###"
     c=$(( $c+10 ))
     if [ "$c" = "10" ]
     then
        cd "$(dirname "$0")"
     fi
     if [ "$c" = "20" ]
     then
        git fetch --all --quiet
     fi
     if [ "$c" = "30" ]
     then
        git reset --hard origin/master --quiet
     fi
     if [ "$c" = "40" ]
     then
        git pull --quiet
     fi
     if [ "$c" = "50" ]
     then
        chmod -R 777 storage/
     fi
     if [ "$c" = "60" ]
     then
        chmod -R 777 public/
     fi
     if [ "$c" = "70" ]
     then
        composer install --quiet
     fi
     if [ "$c" = "80" ]
     then
        composer update --quiet
     fi
done
  ) |
 dialog --title "Cole" --gauge "Updating ColeTools to the latest version..." 10 60 0

if [ "$1" != "-quiet" ]
then
dialog --title "Cole" --msgbox "ColeTools has been updated to the latest version" 5 50
clear
fi
