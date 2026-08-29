# Arkitektur

Samtli ska vara en serverrenderad PHP-applikation med tydliga ansvar utan ett tungt ramverk.

## Controllers

Controllers hanterar HTTP-requester, läser validerad input och koordinerar rätt use case. De ska inte innehålla SQL eller affärsregler som hör hemma i services.

## Services

Services innehåller affärslogik och användningsfall, exempelvis registrering, medlemsansökan, medlemsgodkännande, diskussionsskapande och inbjudningsinlösen.

## Repositories

Repositories ansvarar för databaspersistens och frågor via PDO. De ska använda prepared statements och får inte interpolera rå användarinput i SQL.

## Security

Säkerhetskod samlas kring autentisering, auktorisering, CSRF-skydd, lösenordshantering och server-side behörighetskontroller. UI-logik får aldrig vara enda behörighetskontrollen.

## Templates

Templates är serverrenderade PHP-vyer för presentation. Affärsregler ska inte vara beroende av HTML-templates.

## Database

Databasschema och seed-data ska ligga under `database/`. Migreringar ska vara SQL-baserade och passa MariaDB/MySQL.

`database/migrations/` innehåller versionsstyrda SQL-migreringar som körs i lexikografisk ordning. `src/Database/Connection.php` skapar PDO-anslutningar från miljövariabler och `src/Database/Migrator.php` ansvarar för migreringslås, tabellen `schema_migrations`, att hoppa över redan körda migreringar och att stoppa vid fel.

Migreringar körs manuellt via CLI, inte automatiskt i HTTP-requester:

```bash
docker compose exec app php bin/migrate.php
```

Datamodellen dokumenteras i `docs/database/SCHEMA.md`.

## Princip

Strukturen ska göra informationsflöden och behörigheter lätta att följa, men undvika onödiga abstraktioner innan funktionaliteten kräver dem.
