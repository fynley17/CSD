# CSD — RevisionHub

RevisionHub is a group web application built with Vue, PHP, and MariaDB. This
guide explains how to install the tools, prepare a fresh clone, and run the
application during each work session.

## What you need to install

Install these tools once on your computer. The download links below are the
official project or vendor instructions; choose the instructions for your
operating system.

| Tool | Why it is needed | Download / installation instructions |
| --- | --- | --- |
| Git | Clone the repository and get team changes | [Windows](https://git-scm.com/download/win) · [macOS](https://git-scm.com/download/mac) · [Linux](https://git-scm.com/download/linux) |
| Node.js LTS (includes npm) | Install and run the Vue frontend. Use Node.js 20.19+ or 22.12+. | [nodejs.org](https://nodejs.org/en/download) |
| PHP CLI, with PDO MySQL enabled | Run the PHP backend and connect it to MariaDB | [Windows downloads](https://windows.php.net/download/) · [macOS Homebrew](https://formulae.brew.sh/formula/php) · [Linux installation guide](https://www.php.net/manual/en/install.unix.php) |
| Composer | Install PHP dependencies | [getcomposer.org/download](https://getcomposer.org/download/) |
| Docker Compose | Run local MariaDB and phpMyAdmin containers | [Docker Desktop for Mac/Windows](https://www.docker.com/products/docker-desktop/) · [Docker Engine and Compose plugin for Linux](https://docs.docker.com/engine/install/) |
| Python 3 and pre-commit | Install and run the repository's Git commit/push checks | [Python downloads](https://www.python.org/downloads/) · [pre-commit installation](https://pre-commit.com/#installation) |

### Platform notes

- **Windows:** Install Git, Node.js LTS, PHP, and Composer using their Windows
  installers. Install Docker Desktop and start it before using Compose. When
  installing PHP, make sure `php.exe` is available on your `PATH` and the
  `pdo_mysql` extension is enabled.
- **macOS:** Install Git using the macOS installer above or Apple's Command
  Line Tools (`xcode-select --install` in Terminal). Install Node.js LTS,
  Composer, and PHP using the official installers or a package manager such as
  Homebrew. Install and start Docker Desktop.
- **Linux:** Install Git, PHP CLI and its MySQL PDO extension using your
  distribution's package manager. For example, on Ubuntu/Debian:

  ```bash
  sudo apt update
  sudo apt install git php-cli php-mysql unzip
  ```

  Install Node.js LTS and Composer using their official instructions. Install
  Docker Engine and the Docker Compose plugin using the instructions for your
  Linux distribution. Follow Docker's post-install steps so your user can run
  Docker commands. Install Python 3 from your distribution's package manager
  if it is not already installed.

Verify the installations from a new terminal:

```bash
git --version
node --version
npm --version
php --version
composer --version
docker compose version
```

If any command is not found, finish the installation or add the program to your
`PATH`, then open a new terminal. PHP must have the `pdo_mysql` extension
enabled; check with `php -m` and look for `pdo_mysql`.
Check Python with `python --version` on Windows and `python3 --version` on
macOS/Linux.

## First-time setup (once per clone)

Do these steps once for each working copy of the repository.

1. **Clone the repository.** In a terminal, choose a folder where you keep
   projects and run:

   ```bash
   git clone https://github.com/fynley17/CSD.git
   cd CSD
   ```

   If you already have a clone, open a terminal in that clone instead.
2. **Create your local environment files** from the examples:

   macOS/Linux:

   ```bash
   cp .env.example .env
   cp backend/config/.env.example backend/config/.env
   ```

   Windows PowerShell:

   ```powershell
   Copy-Item .env.example .env
   Copy-Item backend/config/.env.example backend/config/.env
   ```

   These files contain local development settings and are ignored by Git. The
   example values match the Compose configuration. Do not put production
   credentials in them.
3. **Install backend dependencies** from the repository root:

   ```bash
   cd backend
   composer install
   cd ..
   ```

4. **Install frontend dependencies:**

   ```bash
   cd frontend
   npm ci
   cd ..
   ```

5. **Install pre-commit once on your computer.** Run the matching command:

   Windows PowerShell:

   ```bash
   py -m pip install --user pre-commit
   ```

   macOS/Linux:

   ```bash
   python3 -m pip install --user pre-commit
   ```

   On Linux distributions that block user-level `pip` installs, use
   `pipx install pre-commit` instead, following the
   [pipx installation guide](https://pipx.pypa.io/stable/installation/).
   Then, from the repository root, enable the Git hooks for this clone:

   ```bash
   pre-commit install
   ```

   The repository config installs checks for both commits and pushes. Repeat
   `pre-commit install` for each clone; you do not need to reinstall the
   pre-commit program on the same computer.
6. **Start the local database** from the repository root:

   ```bash
   docker compose up -d
   docker compose ps
   ```

   Wait for the `db` service to report `healthy`. The first start downloads the
   MariaDB and phpMyAdmin images. MariaDB stores its data in a Docker volume,
   separately on each developer's machine.
7. **Check the schema.** The team schema belongs in
   `database/init/01-schema.sql`. On the first database start, MariaDB
   automatically imports SQL files in `database/init/`. If the database volume
   was initialised before the schema file was added, import the file through
   phpMyAdmin instead: open [http://localhost:8081](http://localhost:8081),
   sign in with the local settings below, select `revisionhub`, choose
   **Import**, and select `database/init/01-schema.sql`.

The default local settings are:

| Setting | Default |
| --- | --- |
| Database | `revisionhub` |
| Database user | `revisionhub_dev` |
| Database password | `revisionhub_dev` |
| Database address for PHP | `127.0.0.1:3306` |
| phpMyAdmin | [http://localhost:8081](http://localhost:8081) |
| phpMyAdmin login | `revisionhub_dev` / `revisionhub_dev` |

The committed schema uses MariaDB's native `UUID` type, so use the provided
MariaDB 11.4 Compose service rather than MySQL. SQL initialisation files only
run when the MariaDB data volume is first created. To apply later schema
changes to an existing database, import the updated SQL through phpMyAdmin or
apply the appropriate migration; do not delete the volume unless you intend to
erase that machine's local database.

## Each time you work

1. Start Docker Desktop first on macOS/Windows. On Linux, ensure Docker Engine
   is running.
2. Open a terminal in your clone and start the database services:

   ```bash
   docker compose up -d
   docker compose ps
   ```

   Wait for MariaDB to be healthy before starting the backend.
3. In a terminal at the repository root, start the PHP backend:

   ```bash
   php -S 127.0.0.1:8000 -t backend/public backend/public/index.php
   ```

   Keep this terminal open. The local backend is at
   [http://127.0.0.1:8000](http://127.0.0.1:8000).
4. In a second terminal, start the Vue development server:

   ```bash
   cd frontend
   npm run dev
   ```

   Open the local URL printed by Vite in that terminal.
5. When you finish, stop the PHP and frontend servers with `Ctrl+C` in their
   terminals. Stop the containers if desired:

   ```bash
   docker compose down
   ```

   This stops the services but keeps the local database volume. **Do not add
   `-v`** unless you intentionally want to delete the local database.

Git hooks run automatically when you commit or push. To run the checks
manually from the repository root:

```bash
pre-commit run --all-files
pre-commit run --all-files --hook-stage pre-push
```

## Troubleshooting

- **A port is already in use:** Change `DB_PORT` or `PMA_PORT` in the root
  `.env`. If you change `DB_PORT`, set `DB_PORT` to the same value in
  `backend/config/.env` as well.
- **The database is not healthy:** Check Docker Desktop/Engine is running, then
  inspect the database logs with `docker compose logs db`.
- **The schema did not appear:** Initialisation SQL only runs on a new empty
  database volume. Import `database/init/01-schema.sql` in phpMyAdmin as
  described above.
- **PHP cannot connect to MariaDB:** Confirm the container is healthy, that
  `backend/config/.env` exists, and that its host, port, database name, user,
  and password match the root `.env`. Confirm that `pdo_mysql` is enabled in
  the PHP installation used by the terminal.
- **`docker compose` is not found:** Install Docker Desktop on Windows/macOS or
  the Docker Compose plugin on Linux, then open a new terminal.

## Project structure and application flow

RevisionHub uses a Vue frontend, a PHP backend, and MariaDB. The repository is
organised as follows:

```text
CSD/
├── Documentation/          # Requirements and project documents
├── backend/
│   ├── config/             # Database connection and local configuration
│   ├── controllers/        # Request/controller logic
│   ├── helpers/            # Shared PHP utilities
│   ├── models/             # Data access and model logic
│   ├── tests/              # Backend tests
│   └── index.php           # PHP development-server entry point
├── database/
│   └── init/               # SQL used to initialise a new local database
├── frontend/
│   ├── assets/             # Images and styles
│   ├── components/         # Reusable Vue components
│   ├── router/             # Vue Router configuration
│   ├── services/           # Frontend service code
│   ├── views/              # Application pages
│   ├── app.vue             # Root Vue component
│   └── main.js             # Frontend entry point
├── compose.yaml            # Local MariaDB and phpMyAdmin services
└── README.md
```

The frontend is started from `frontend/` with Vite. Its services are intended
to send requests to the PHP backend. Backend controllers coordinate request
handling with models and shared helpers, while
`backend/config/database.php` creates a PDO connection using the local
`backend/config/.env` file. The current `backend/index.php` checks the database
connection; backend API routing and frontend-to-backend integration are still
under development.

Project requirements and supporting materials are in `Documentation/`,
including the [requirements breakdown](Documentation/requirement%20breakdown.md).

## Testing and code checks

The frontend currently provides a production build check:

```bash
cd frontend
npm run build
```

The repository's [.pre-commit-config.yaml](.pre-commit-config.yaml) defines
these automated checks:

- **On commit:** generic whitespace/configuration checks; PHP syntax,
  PHP-CS-Fixer and PHPStan checks for backend PHP files; and Prettier for
  matching frontend files.
- **On push:** PHPUnit for changed backend test files and Vitest for changed
  frontend test files.

The PHP checks use the Composer dependencies installed during first-time setup.
The PHPUnit and Vitest hooks are configured for matching test files, but this
repository does not currently contain those test suites or list PHPUnit/Vitest
as project dependencies. Add the relevant tests and dependencies before
depending on those hooks. Prettier is also not currently listed in
`frontend/package.json`; if the Prettier hook runs and reports that it is
unavailable, add/configure Prettier as a frontend development dependency.

## Security and local data

- Keep `.env` files, passwords, API keys, and other secrets out of commits.
- Use structure-only database exports for shared setup. Do not commit
  production or personal data.
- Hash passwords using a suitable password-hashing function; never store plain
  text passwords.
- Use prepared statements for database queries and validate user input.
- Protect sessions and enforce access controls for students, lecturers, and
  administrators.

## Team workflow

Work on a feature or fix branch rather than directly on `main`. Use the team's
agreed branch and ticket naming convention:

```text
<category>/CTS-<ticket-number>-<short-description>
```

For example, `feature/CTS-53-question-management`. Use meaningful commit
messages, push the branch to GitHub, and open a pull request against `main`.
Request review from a teammate, address review comments, and merge through the
team's agreed approval process.
