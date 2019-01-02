Run container with
```
docker run -d -p8080:80 \
  -v <directory-for-your-serverinfo.xml-and-sqlite-db>:/data \
  -v <git-clone-of-your-sources/>:/sources \
  --name caldavtester quay.io/egroupware/caldavtester
```

The GUI is then available at http://localhost:8080/ and allows to upload your serverinfo.xml, 
if it is not already in the specified data volume.
The sources volumn is currenlty the only way to specifiy revisions and branches, by reading them from git.

Please note: 
* Sqlite has problems on a Mac where the docker-host is not the Mac itself, but a VM running on the Mac and host-path get NFS mounted.
 Therefore better create a volume for /data and use that instead of a host-path, which persists updates of the container:
```
docker volume create caldavtester-data
docker run -d -p8080:80 \
  -v caldavtester-data:/data \
  -v <git-clone-of-your-sources/>:/sources \
  --name caldavtester quay.io/egroupware/caldavtester
```
