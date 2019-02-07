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

	# add docker volume caldavtester-data, if not existing
	docker volume ls | grep caldavtester-data || docker volume create caldavtester-data

	# re-start caldavtester container after successful build
	docker rm -f caldavtester
	docker run -d -p8081:80 \
		--add-host boulder.egroupware.org:192.168.0.101 \
		-v caldavtester-data:/data \
		-v /Volumes/htdocs/egroupware:/sources \
		-v /Users/ralf/CalDAVTester/src:/caldavtester/src \
		-v /Users/ralf/CalDAVTester/scripts:/caldavtester/scripts \
		-v /Users/ralf/CalDAVTester/caldavtester-tools:/caldavtester/caldavtester-tools \
		--name caldavtester quay.io/egroupware/caldavtester
	docker logs -f caldavtester
}