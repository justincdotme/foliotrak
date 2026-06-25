FROM nginx:stable-alpine

COPY docker/nginx/http.conf docker/nginx/https.conf /opt/foliotrak-nginx/templates/
COPY docker/nginx/start-nginx.sh /opt/foliotrak-nginx/start-nginx.sh

RUN chmod +x /opt/foliotrak-nginx/start-nginx.sh

CMD ["/opt/foliotrak-nginx/start-nginx.sh"]
