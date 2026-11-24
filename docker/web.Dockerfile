FROM nginx:1.27-alpine

COPY docker/web.conf /etc/nginx/conf.d/default.conf
COPY web/ /usr/share/nginx/html/

# Nginx deve poter leggere i file anche se la checkout usa un umask restrittivo.
RUN chmod -R a+rX /usr/share/nginx/html
