# Container with CalDAVTester incl. GUI
FROM python:2.7-alpine
MAINTAINER rb@egroupware.org

RUN apk --update add git apache2 \
		php-apache2 php-cli php-json php-pdo php-pdo_sqlite php-simplexml; \
	git clone https://github.com/CalConnect/caldavtester.git; \
	cd caldavtester; \
	git clone https://github.com/apple/ccs-pycalendar.git pycalendar; \
	git clone https://github.com/CalConnect/caldavtester-tools; \
	sed -e 's|/var/www/localhost/htdocs|/caldavtester/caldavtester-tools|g' \
		-e 's|^ErrorLog logs/error.log|ErrorLog /dev/stderr|' \
		-i /etc/apache2/httpd.conf

COPY httpd-foreground /
COPY .caldavtests.json /caldavtester/caldavtester-tools/
COPY caldavtests.php /caldavtester/caldavtester-tools/
COPY index.php /caldavtester/caldavtester-tools/

EXPOSE 80
CMD ["/httpd-foreground"]