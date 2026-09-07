# Samtli – inlämning för Systemutveckling uppgift 2

Samtli är ett server-renderat PHP-community där användare skapar grupper, ansöker om medlemskap och för privata diskussioner. Projektet är byggt för uppgiften **Systemutveckling uppgift 2** och omfattar samtliga G- och VG-krav.

Den driftsatta versionen finns på [samtli.hampusandersson.dev](https://samtli.hampusandersson.dev).

## Teknik och struktur

- PHP 8.4 med server-renderade PHP-sidor
- MariaDB/MySQL och PDO med förberedda SQL-satser
- HTML, projektägd CSS och enbart mindre mängder vanlig JavaScript
- Docker Compose för lokal körning och Coolify för produktion

Kodbasen är uppdelad efter ansvar: controllers hanterar HTTP-flöden, services affärsregler, repositories datalagring, `src/Security/` autentisering och CSRF, och `templates/` presentation. Databasmigreringar finns i `database/migrations/`.

## Kravspårning

| Uppgiftskrav | Samtli |
| --- | --- |
| SQL-databas och PHP-sidor | MariaDB-migreringar, PDO och server-renderade templates |
| Konto med namn, e-post och lösenordshash | Registrering och inloggning; lösenord hanteras med `password_hash()` och `password_verify()` |
| Skapa och hitta grupper | Inloggade användare kan skapa grupper och se grupper de ännu inte tillhör |
| Diskussioner och svar | Medlemmar kan starta ämnen med första inlägg och svara i gruppens diskussioner |
| Medlemsansökan och godkännande | Användare ansöker från gruppöversikten; administratörer granskar och godkänner |
| Roller per grupp (VG) | `member` och `administrator`; administratörer kan ändra andra medlemmars roll |
| Administratörsbehörighet (VG) | Servern returnerar 403 för en medlem som försöker öppna adminfunktioner |
| Hot-link-inbjudningar (VG) | Administratören skapar en kryptografiskt slumpad länk, token lagras hashad, gäller 24 timmar och kan användas exakt en gång |

All åtkomstkontroll sker på serversidan. Alla skrivande formulär har CSRF-skydd och gruppinnehåll kontrolleras mot den inloggade användarens medlemskap. Se [docs/ASSIGNMENT.md](docs/ASSIGNMENT.md) för den fullständiga kravlistan och [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) för arkitekturen.

## Verifiering inför inlämning

En fullständig körning genomfördes mot produktionsmiljön den 7 september 2026 med Postman CLI:

```powershell
postman collection run postman/Samtli.postman_collection.json `
  --env-var "base_url=https://samtli.hampusandersson.dev" `
  --ignore-redirects -r json `
  --reporter-json-export docs/submission/postman/live-run.json
```

Resultat: **30 requestar, 41 av 41 assertions godkända, 0 request- och scriptfel**. Collectionen testar registrering, inloggning, gruppskapande, diskussionsstart, svar, medlemsansökan, administratörsgodkännande samt skapa, acceptera och återanvända en engångsinbjudan. Rapporten sparas i [docs/submission/postman/live-run.json](docs/submission/postman/live-run.json).

Manuell browserverifiering mot samma miljö bekräftade också att en administratör kan ändra en medlems roll och att en vanlig medlem nekas administrativa sidor med HTTP 403.

Lokal kodverifiering kan köras i Docker:

```powershell
docker compose up --build
docker compose exec app composer test
```

## Bilagor för inlämning

De tre obligatoriska skärmbilderna finns i [docs/submission/screenshots](docs/submission/screenshots):

1. [Startsida](docs/submission/screenshots/01-home.png)
2. [Gruppdiskussion](docs/submission/screenshots/02-group-discussion.png)
3. [Svara på diskussion](docs/submission/screenshots/03-discussion-replies.png)

Återstående manuella bilaga är genomgångsvideon med voice-over. Videon bör visa: skapa konto, ansöka om medlemskap i en grupp och starta en diskussion efter godkännande.

## Lokal start

```powershell
Copy-Item .env.example .env
docker compose up --build
docker compose exec app php bin/migrate.php
```

Öppna sedan `http://localhost:38515`. Konfiguration och hemligheter hämtas från miljövariabler; riktiga `.env`-värden eller databasdumpningar ska inte committas.

## Git-historik

Git-historiken dokumenterar den stegvisa utvecklingen med separata commits för funktionalitet, säkerhet, testning och visuella förbättringar. Den ska lämnas med tillsammans med projektet som underlag för uppgiftens krav på eget utvecklingsarbete.
