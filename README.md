- [Введение](#Введение)
- [Разворачивание](#Разворачивание)

## Введение
Сервер событий и роутинга. Сервис распределяет поступающие события в очередь по клиентам в зависимости от их подписок.
Распределяет запросы сервиса по имени роута на конечную ноду для обработки.

## Разворачивание

Собрать контейнеры
```bash
docker-compose build
```
В /etc/hosts прописать сервер
```bash
127.0.0.1 event-server.loc
```
Запустить контейнеры
```bash
docker-compose up
```
Установить зависимости
```bash
docker-compose exec php composer install
```
Создать базу
```bash
docker-compose exec php php bin/console doctrine:database:create
```
Накатить миграции
```bash
docker-compose exec php php bin/console doctrine:migrations:migrate
```
Запустить демона
```bash
docker-compose exec php php bin/console event:loop
```

После того как сервер развернут, можно раскоментировать команду в контейнере php, чтобы при старте контейнера демон запускался автоматом
```yml
php:
  build:
    context: docker/php
    args:
      PHP_VERSION: 7.4
      USER_GID: 1000
      USER_UID: 1000
  environment:
    PHP_INI_MEMORY_LIMIT: 512M
    PHP_IDE_CONFIG: "serverName=Docker"
  ports:
    - "${SOCKET_PORT}:${SOCKET_PORT}"
  #    command: "php bin/console event:loop"
  networks:
    local:
    event-service-external:
      aliases:
        - event-server.loc
  volumes:
    - "composer-cache:/srv/.composer"
    - ".:/srv"
```

После успешного разворачивания доступны следующие ресурсы
 - http://localhost:5601/ кибана для удобного просмотра и выборок логов
 - http://localhost:3001/ Дашборт состояния очереди
    - Добавить сервер 172.17.0.1:11301	


