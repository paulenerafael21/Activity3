# PHP Docker PMR

Isang simpleng student-record app gamit ang PHP, Apache, MySQL, at Docker Compose. Puwedeng magdagdag, mag-edit, magbura, at tumingin ng student records.

## Mga kailangan

- Docker Desktop na may Docker Compose
- Git (kung kino-clone ang repository)

## Pagsisimula

1. Gumawa ng local environment file:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Palitan ang `MYSQL_ROOT_PASSWORD` sa `.env` ng sariling local development password.
3. Simulan ang mga serbisyo:

   ```powershell
   docker compose up --build
   ```

4. Buksan ang:
   - App: <http://localhost:8080>
   - phpMyAdmin: <http://localhost:8001>

Awtomatikong ginagawa ng app ang `students` table kapag unang kumonekta sa database.

## Paghinto

```powershell
docker compose down
```

Nananatili ang database sa Docker volume na `db_data`. Para burahin pati ang lokal na database data:

```powershell
docker compose down --volumes
```

Huwag i-commit ang `.env`; kasama ito sa `.gitignore`. Ang `.env.example` ay template lamang para sa local development.
