#!/bin/bash -x
mkdir -p /tmp/rpmbuild/SOURCES
cp -pv ci/wp-cli.spec /tmp/rpmbuild/
cp -pv bin/wp /tmp/rpmbuild/SOURCES/
chown -Rv 1000:1000 /tmp/rpmbuild
git clone https://github.com/mmornati/docker-mock-rpmbuilder.git
cd docker-mock-rpmbuilder
docker build -t mmornati/mockrpmbuilder .
docker run -d --name rpm -e MOCK_CONFIG=epel-6-x86_64 -e SOURCES=SOURCES/ -e SPEC_FILE=wp-cli.spec -v /tmp/rpmbuild:/rpmbuild --privileged=true mmornati/mockrpmbuilder
docker logs -f rpm
find /tmp/rpmbuild/output/epel-6-x86_64/
docker stop rpm
docker rm rpm
docker run -d --name rpm -e MOCK_CONFIG=epel-6-x86_64 -e SOURCE_RPM=/rpmbuild/output/epel-6-x86_64/wp-cli-VERSION-RELEASE.src.rpm -v /tmp/rpmbuild:/rpmbuild --privileged=true mmornati/mockrpmbuilder
find /tmp/rpmbuild/output/epel-6-x86_64/
