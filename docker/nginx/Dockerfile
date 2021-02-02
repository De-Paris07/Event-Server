FROM nginx:alpine

RUN apk add --no-cache curl netcat-openbsd fcgi tree \
    && rm -rf \
        /usr/share/nginx/html/* \
        /etc/nginx/conf.d/default.conf

COPY snippets/ /etc/nginx/snippets
COPY conf.d/ /etc/nginx/conf.d
