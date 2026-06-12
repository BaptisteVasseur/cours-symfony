# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Symfony 8 / PHP 8.4 Airbnb-style rental platform (ESGI school project). PostgreSQL 16, API Platform 4.2, Twig + Asset Mapper front-end. Communicate with the user in **French**.

A detailed companion doc lives in [AGENTS.md](AGENTS.md) (business context from `Cahier des charges.md`, target schema `PlantText.txt`, coding conventions). Read it for domain/feature guidance; this file covers what to actually run and how the code is wired. Where the two disagree, this file reflects the verified current state.

## Commands

Everything runs **inside the `php` Docker container**. The `make` targets wrap Docker Compose:

```bash
make install   # first run: build + up (also installs composer deps, runs migrations via docker/entrypoint.sh)
make up        # start services
make down      # stop
make sh        # shell into the php container
make cache     # cache:clear
make logs      # follow php logs
```

Run Symfony/Composer/PHPUnit through the container:

```bash
docker compose exec php php bin/console <command>
docker compose exec php composer <command>
docker compose exec php php bin/phpunit                 # full test suite
docker compose exec php php bin/phpunit tests/Foo.php   # single file
docker compose exec php php bin/phpunit --filter testName
```

Database / fixtures:

```bash
docker compose exec php php bin/console doctrine:migrations:diff      # generate migration from entity changes
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:fixtures:load        # loads PHP fixtures from src/DataFixtures/
docker compose exec php php bin/console hautelook:fixtures:load       # loads YAML fixtures from fixtures/
```

Services: app http://localhost:8089 · Adminer http://localhost:8088 · Mailpit http://localhost:8025 · PostgreSQL `localhost:5439`. The container serves via `php -S` on port 8000 (entrypoint), not nginx.

Note: PHPUnit runs with `failOnDeprecation`/`failOnNotice`/`failOnWarning` enabled — deprecations break the build.

## Architecture

**Entities use UUIDs.** Most entities pull in `App\Entity\Trait\UuidEntityTrait` ([src/Entity/Trait/UuidEntityTrait.php](src/Entity/Trait/UuidEntityTrait.php)) for a `Symfony\Component\Uid\Uuid` primary key via a CUSTOM Doctrine generator. New entities should do the same, not auto-increment ints.

**Controllers split Front vs Admin** ([src/Controller/Front/](src/Controller/Front/), [src/Controller/Admin/](src/Controller/Admin/)) mirrored by `templates/front/` and `templates/admin/`. Shared Twig in `templates/layout/` (inherit via `extends`), reusable bits in `templates/components/`. Routes are attribute-based (`#[Route]`); only API Platform routes are wired in `config/routes.yaml` under `/api`.

**Security** ([config/packages/security.yaml](config/packages/security.yaml)): form login (`app_login`/`app_logout`), `User` entity provider keyed on `email`, password hashing `auto`, login throttling (5 attempts / 15 min). Authorization is enforced both by URL `access_control` patterns (French route prefixes: `/logement`, `/reservations`, `/compte`, `/messages`, `/admin`) and `#[IsGranted]` on controllers. Role hierarchy: `ROLE_USER` ← `ROLE_HOST`/`ROLE_MODERATEUR` ← `ROLE_ADMIN` ← `ROLE_SUPER_ADMIN`. Role constants and UI labels live in [src/Security/Roles.php](src/Security/Roles.php) — use these rather than raw strings. Account access rules go through [src/Security/UserChecker.php](src/Security/UserChecker.php).

**API Platform** is exposed via `#[ApiResource]` attributes directly on entities (e.g. `User`), prefix `/api`, stateless. Everything under `/api` requires `ROLE_USER` except docs/contexts/validation_errors. Use serialization `#[Groups]` to avoid exposing sensitive fields.

**Fixtures are dual-stack:** PHP fixtures in `src/DataFixtures/` (Doctrine Fixtures bundle, with `DependentFixtureInterface` ordering and shared references in `FixtureReferences.php`) and Alice YAML in `fixtures/`. `TestAccountFixture` seeds a known account (`test@example.com`). They load via different commands (see above).

**Async** via Symfony Messenger over `doctrine://default`; a dedicated `messenger-worker` container runs `messenger:consume async`. Route long work (emails, notifications) to async handlers rather than doing it inline in controllers.

## Conventions

- `declare(strict_types=1);` at the top of every PHP file; typed params/returns; constructor promotion for DTOs.
- English for code identifiers; French for UI labels and user-facing messages.
- No code comments unless a non-obvious business rule needs explaining.
- Schema changes always go through a migration (`diff` then `migrate`), never `schema:update`.
- Commit/push only when the user explicitly asks.
