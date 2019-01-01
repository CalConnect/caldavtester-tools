#!/bin/bash -x
################################################################################
###
###  build-script for caldavtester container
###
###  Author:  Ralf Becker <rb@egroupware.org>
###
################################################################################

# change to directory for this script, as docker build requires that
cd $(dirname $0)

# use quay.io egroupware registry
REPO=quay.io/egroupware
IMAGE=caldavtester
BASE=python:2.7-alpine

docker pull $BASE
TAG=latest
echo -e "\nbuilding $REPO/$IMAGE:$TAG\n"

# repo build
docker build -t $REPO/$IMAGE:latest . && {
	docker push $REPO/$IMAGE:latest
	#docker tag $REPO/$IMAGE:latest $REPO/$IMAGE:$TAG
	#docker push $REPO/$IMAGE:$TAG

	# re-start caldavtester container after successful build
	docker rm -f caldavtester
	docker run -d --add-host boulder.egroupware.org:192.168.0.101 -p8081:80 -v /Users/ralf/CalDavTester:/data -v /opt/local/apache2/htdocs/egroupware:/sources --name caldavtester quay.io/egroupware/caldavtester
	docker logs -f caldavtester
}