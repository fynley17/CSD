# Deploying RevisionHub to Plesk

The live site runs on one domain: Apache serves the built Vue files, and any
address starting with `/api` goes to PHP. This keeps the page and the API on
the same address, which the login cookie needs, just as the Vite proxy does
locally.

## 1. How the build gets made

Every push to `main` runs the **Plesk deploy build** workflow
(`.github/workflows/plesk-deploy.yml`). It runs `scripts/build-plesk.sh` and
commits the result to the `plesk-deploy` branch. Plesk pulls that branch, so
the server never has to build anything. Don't commit to `plesk-deploy` by hand,
because the next build replaces everything on it.

The branch only appears after the workflow's first run. To run it straight
away, go to GitHub → **Actions → Plesk deploy build → Run workflow**.

The build on `plesk-deploy` contains:

```
app/  config/  resources/  vendor/  routes.php
public/          <- the only folder the web can see
  index.html, assets/   (Vue)
  index.php             (PHP API)
  .htaccess             (routing)
```

The build never contains a `.env` file, so the database password and the
Gmail app password are never committed.

## 2. Set up the domain in Plesk

1. **Websites & Domains → (your domain) → PHP:** choose PHP 8.3 or newer
   (8.4 to match local development), served by **FPM application served by
   Apache**. Check that `pdo_mysql` is enabled.
2. **Hosting & DNS → Hosting:** set **Document root** to `httpdocs/public`.
   Everything outside `public/` (including `config/.env`) then can't be
   downloaded.
3. **SSL/TLS Certificates:** install a free Let's Encrypt certificate and turn
   on **Redirect from HTTP to HTTPS**. Logging in needs HTTPS, because the
   session cookie is set to secure on HTTPS.
4. **Apache & nginx Settings:** leave **Proxy mode** on. Turning on "Serve
   static files directly by nginx" is fine. Do not switch the site to
   nginx-only, because that ignores `.htaccess`.

## 3. Create the database

1. **Databases → Add Database:** for example `revisionhub`, with a new
   database user. Note the host Plesk shows (usually `localhost`).
2. Open **phpMyAdmin** from the database and import
   `database/init/01-schema.sql`. The schema should already include every
   migration, so a fresh database doesn't need `database/migrations`. When a
   PR adds a migration, it must also change `01-schema.sql`, or a fresh
   database will be missing that change.
3. Don't import `database/seeds/sample-tests.sql` on the live site, because
   its accounts have a publicly known password. Import
   `database/seeds/beta-test-data.sql` only for the beta-test period, and
   remove or disable those accounts afterwards.
4. The schema contains no accounts. Add the first administrator in
   phpMyAdmin, with a password hashed by PHP's `password_hash()`.

## 4. Connect Plesk to the repository

1. **Files → httpdocs:** delete Plesk's default `index.html` and other
   placeholder files.
2. **Websites & Domains → Git → Add Repository**, choose **Remote Git
   hosting**:
   - **Repository URL:** `https://github.com/fynley17/CSD.git` if the repo is
     public. If it's private, use `git@github.com:fynley17/CSD.git` and copy
     the SSH key Plesk shows. Add it in GitHub under **Settings → Deploy keys**
     and leave "Allow write access" off.
   - **Deployment mode:** Automatic.
   - **Server path:** `/httpdocs`.
3. Once Plesk has added the repository, open its settings and change the
   **branch** to `plesk-deploy`, then click **Pull now**. `httpdocs` should
   now contain `app/`, `public/`, `vendor/` and the rest.
4. To deploy on every push: copy the **Webhook URL** from the repository's
   settings in Plesk. In GitHub, open **Settings → Webhooks → Add webhook**,
   paste it as the Payload URL and keep "Just the push event". Without the
   webhook, click **Pull now** in Plesk after each build.
5. In `httpdocs/config/`, create a file named `.env`. It isn't in git, so
   this is the only file you create on the server:

   ```ini
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=revisionhub
   DB_USER=the-plesk-db-user
   DB_PASSWORD=the-plesk-db-password

   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=your-address@gmail.com
   SMTP_PASS=your-16-char-app-password
   MAIL_FROM_NAME=RevisionHub

   # the live address, used for the links in emails
   APP_URL=https://your-domain.example
   ```

## 5. Check it works

- `https://your-domain/` shows the landing page.
- `https://your-domain/api/csrf-token` returns JSON with a `csrfToken`.
- Reloading `https://your-domain/login` still shows the login page, not a
  404.
- Registering sends a verification email, and its link opens your domain,
  not `localhost`.

If something fails, check **Websites & Domains → Logs**.

## Updating the live site

Merge into `main`. The workflow builds and pushes `plesk-deploy`, and the
webhook tells Plesk to pull it, usually within a couple of minutes. Check the
run on GitHub's **Actions** tab if the site doesn't change. After the first
automatic deployment, check that `httpdocs/config/.env` is still there.

Database changes are not deployed by git. Apply any new files in
`database/migrations` through phpMyAdmin before merging code that needs them.

To roll back, revert the change on `main`. The workflow then builds and
deploys the reverted code.

### Without GitHub Actions

`./scripts/build-plesk.sh` also creates `build/revisionhub-plesk.zip`. You can
upload it in **Files → httpdocs** and **Extract** it over the old files.
