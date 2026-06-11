# AIRBNP

Just a weak clone, nothing more.

## STARTING INSTRUCTIONS

Start docker before running the commands

```bash
make install
make start
```

## TESTING ACCOUNTS

admin@example.com / password
host@example.com / password
user@example.com / password

## USEFUL COMMANDS

seed :

```bash
make fixtures
```

migrate :

```bash
make migrate
```

migration roll back :

```bash
docker compose exec -it php php bin/console doctrine:migrations:migrate prev
```

By Dilan EESHVARAN 4IW1
