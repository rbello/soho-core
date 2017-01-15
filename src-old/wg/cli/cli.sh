#!/bin/bash

PWD=$(cd -P -- "$(dirname -- "$0")" && pwd -P)

echo "Welcome in SoHo command line interface."
echo "Type 'help' for a list of available commands, or 'exit' to leave this command.";

typePassword=0

while [ 1 ]
do

	# Password input
	if [ $typePassword = 1 ]; then
		stty -echo
		read password
		stty echo
		php -f "$PWD/cli-run.php" -- --identity=auto $cmd "--passwd=$password"
		typePassword=0
		continue
	fi

	# Command input
	echo -n "> "
	#read -e -d "	" cmd
	read -er cmd
	if [ "$cmd" = "exit" ]; then
		exit
	fi
	if [ "$cmd" = "cls" ]; then
		clear
		continue
	fi
	if [ "$cmd" = "" ]; then
		cmd="$lastcmd"
		if [ "$cmd" != "" ]; then
			echo "> $cmd"
		fi
	fi
	php -f "$PWD/cli-run.php" -- --identity=auto $cmd
	#echo "Returns: $?"
	if [ "$?" = 254 ]; then
		typePassword=1
	fi
	lastcmd="$cmd"

done
