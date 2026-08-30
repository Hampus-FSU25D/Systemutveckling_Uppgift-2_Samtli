# Postman Verification

Use `postman/Samtli.postman_collection.json` to demonstrate the assignment flows against a local Docker stack.

## Run Locally

1. Start the app:

```bash
docker compose up --build
docker compose exec app php bin/migrate.php
```

2. Import the collection in Postman.

3. Confirm the collection variable `base_url` is:

```text
http://localhost:38515
```

4. Run the collection in order.

The collection creates timestamped users and a timestamped group, so it can be run repeatedly against a local database. It relies on Postman's cookie jar and extracts CSRF tokens from the server-rendered PHP forms before submitting state-changing requests.

## Covered Flows

- account registration
- login
- group creation
- discussion creation
- discussion replies
- administrator invitation creation
- membership request
- administrator request approval
- invitation acceptance
- single-use invitation unavailable state
