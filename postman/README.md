# Postman

Use `Samtli.postman_collection.json` to verify the assignment flows against the local Docker app.

The collection expects:

- `base_url` set to `http://localhost:38515`
- Postman's cookie jar enabled
- requests run in collection order

It exercises registration, login, group creation, discussion creation, replies, administrator invitations, membership requests, administrator approval, invitation acceptance, and the used invitation unavailable state.
