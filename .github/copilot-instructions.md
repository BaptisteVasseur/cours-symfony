# Copilot Instructions (cours-symfony)

You are working in a Symfony project (Symfony 8, PHP 8.4) with Doctrine ORM + Migrations and API Platform.

## Ground truth
- Functional scope reference: `cahier_des_charges_airbnb.md`.
- Data model blueprint reference (UML): `planttext-bdd.txt`.
- Runtime/dev environment: `compose.yaml` + `Makefile`.

## Docker-first workflow (mandatory)
- Run project commands **inside the PHP container**.
- Use Make targets whenever available:
  - Start/build: `make install` / `make up`
  - Shell: `make sh`
  - Logs: `make logs`
  - Cache: `make cache`
- Do not change ports/services in `compose.yaml` unless explicitly requested.

## Entity creation rules (mandatory)
- Entity class names: **English**, **PascalCase** (e.g. `PropertyPhoto`, `UserBadge`).
- Field/property names: **English**, **camelCase** (e.g. `$pricePerNight`, `$createdAt`).
- Entities live in `src/Entity/`.
- Use Doctrine **PHP attributes** mapping (e.g. `#[ORM\Entity]`).
- Prefer `\DateTimeImmutable` for date/time fields.
- Add `createdAt`/`updatedAt` where relevant (align with existing `User`).
- Use enums in `src/Enum/` for finite state fields (e.g. `Role`, statuses) when appropriate.

## Data model alignment
- When implementing features, align with the UML names/relations from `planttext-bdd.txt` first:
  - `User`, `Property`, `Amenity`, `PropertyAmenity`, `PropertyPhoto`, `Availability`, `Booking`, `Payment`, `Review`, `Message`
  - Gamification entities exist in the UML; only implement them if explicitly requested.
- Create/update migrations whenever the schema changes.

## API Platform
- Expose only what is needed for the requested scope.
- Apply authorization/ownership rules by default (roles, user-owned resources).

## Do / Don't
- Do: keep changes minimal, consistent with existing code style.
- Don't: invent extra UX/features beyond the stated request or beyond the scope in `cahier_des_charges_airbnb.md`.
