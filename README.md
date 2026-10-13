# CSD — RevisionHub

RevisionHub is a group web application developed as part of the CSD project.

## Technology Stack

- **Frontend:** Vue.js
- **Backend:** PHP
- **Database:** SQL / MySQL
- **Database Management:** phpMyAdmin
- **Version Control:** Git and GitHub

## Project Architecture

RevisionHub separates the frontend user interface from the PHP backend and database.

```text
CSD/
├── .gitignore
├── README.md
├── Documentation/
│   └── requirement breakdown.md
├── backend/
│   ├── controllers/
│   ├── helpers/
│   ├── models/
│   ├── .env
│   └── index.php
├── frontend/
│   ├── assets/
│   ├── components/
│   ├── router/
│   ├── services/
│   ├── views/
│   ├── app.vue
│   ├── main.js
│   ├── package.json
│   └── package-lock.json
└── tests/
    ├── test-plan/
    └── unit/
        ├── controllers/
        ├── helpers/
        └── models/
```

### Backend — `backend/`

The backend contains the PHP server-side code responsible for application logic and database communication.

#### `backend/controllers/`

Contains the application controllers. Controllers handle incoming requests, coordinate the required operations and return appropriate responses.

#### `backend/models/`

Contains the models responsible for working with application data and database operations, including retrieving, creating, updating and deleting records where required.

#### `backend/helpers/`

Contains reusable PHP functions and utilities, such as database connection, validation and authentication helpers.

#### `backend/index.php`

The intended entry point for the PHP backend. It will be used to initialise and route requests into the backend application.

#### `backend/.env`

Stores environment-specific configuration, including database connection details.

Example configuration keys:

```text
DB_HOST=
DB_NAME=
DB_USER=
DB_PASSWORD=
```

Actual credentials must be supplied through the appropriate environment configuration. Do not commit real passwords or other secrets to GitHub.

### Frontend — `frontend/`

The frontend contains the Vue.js user interface and navigation for RevisionHub.

#### `frontend/components/`

Contains reusable Vue components, such as navigation bars, buttons, tables, modals and forms.

#### `frontend/views/`

Contains the main application pages, including the landing page, dashboards, student management, test management and question management.

#### `frontend/assets/`

Contains frontend assets, including images, logos, icons and stylesheets.

#### `frontend/router/`

Contain the Vue Router configuration for mapping application routes to the appropriate views.

#### `frontend/services/`

Contains the JavaScript services responsible for communicating with the PHP backend.

#### `frontend/app.vue`

The main Vue application component.

#### `frontend/main.js`

The frontend entry point used to initialise the Vue application.

#### `frontend/package.json`

Defines the frontend npm project, its dependencies and available scripts.

#### `frontend/package-lock.json`

Records the resolved npm dependency versions to support consistent installations.

### Root `.gitignore`

The `.gitignore` file is stored at the repository root so its rules can apply across the project.

It excludes files and directories that should not be committed, including environment files, Node.js dependencies and common IDE or operating-system files.

## Database

RevisionHub uses a MySQL-compatible SQL database to store application data. phpMyAdmin is used to manage and inspect the database.

The PHP backend is responsible for communicating with the database. Connection details are configured through environment variables rather than being hard-coded into application files.

Database setup and import instructions will be documented once the team's development environment and database setup have been finalised.

## Development Setup

### Prerequisites

- Git
- Node.js and npm
- PHP
- A MySQL-compatible database
- phpMyAdmin or another suitable database management tool

### Frontend Setup

1. Clone the repository and navigate to the project directory.
2. Open a terminal and navigate to the frontend directory:

   ```bash
   cd frontend
   ```

3. Install the frontend dependencies:

   ```bash
   npm install
   ```

4. Start the frontend development server:

   ```bash
   npm run dev
   ```

The development server will display the local URL to open in a browser.

### API Communication

RevisionHub uses an API to allow the Vue.js frontend to communicate with the PHP backend.

The application follows this request flow:

1. The user interacts with a Vue.js page.
2. The relevant frontend service sends an API request.
3. The PHP backend processes the request through the appropriate controller.
4. The backend uses models and helper functions to perform the required operations and communicate with the database.
5. The backend returns a response, using JSON for data exchange.
6. The frontend processes the response and updates the user interface.

API routes, request and response formats, and error handling will be implemented consistently across the application.

### Backend Setup

The PHP backend provides the API used by the Vue.js frontend. During local development, the backend runs using PHP's built-in development server.

To ensure you have the packages rewuired for the backend run the following from /backend:
```bash
composer install
```

To start the backend, run the following command from the root of the repository:

```bash
php -S localhost:8000 -t backend
```

The backend will be available on port `8000`. In GitHub Codespaces, use the forwarded address shown in the Ports tab to access it.

The terminal must remain open while the server is running. Press `Ctrl+C` to stop it.

The API endpoints and database connection will be configured as development progresses.

#### Requirements

- Apache web server
- PHP configured to run with Apache
- MySQL-compatible database
- PHP database connectivity extension, such as PDO MySQL

#### Configuration

1. Ensure Apache and PHP are installed and configured.
2. Configure Apache to serve the project's `backend/` directory, or the appropriate backend entry point.
3. Configure the database connection using the environment variables defined in `backend/.env`.
4. Ensure the backend can connect to the MySQL database.
5. Configure the API routes so that frontend requests are directed to the appropriate PHP controllers.

#### Running the Backend

Start or enable Apache using the method supported by the development environment. The backend API will then be accessible through its configured URL.

The API base URL must be configured in the frontend so that the Vue.js services can send requests to the PHP backend.

The exact Apache configuration, API URL and database setup instructions will be added once the development environment has been confirmed.

## Git and Branching

GitHub is used for version control and collaboration.

### Branch Naming Convention

Branches should use the agreed category and Jira ticket reference in the following format:

`<category>/CTS-<ticket-number>-<short-description>`

Examples:

- `chore/CTS-52-repo-architecture`
- `feature/CTS-53-question-management`
- `bug/CTS-54-login-validation`

Use the category that matches the work being completed and the relevant Jira ticket number.

### Pull Requests

- Complete development work on the appropriate branch.
- Use meaningful commit messages that describe the changes.
- Push the branch to GitHub when the work is ready for review.
- Open a pull request against `main`.
- Request review from another team member.
- Address review comments before the changes are merged.
- Only merge through the team's agreed pull request approval process.

## Project Documentation

Project documentation is stored in the `Documentation/` directory.

This includes the requirements breakdown and other supporting project documents, such as designs, diagrams and planning materials where applicable.

## Testing

Tests are organised under the root `tests/` directory.

- `tests/unit/` contains unit tests organised by application area.
- `tests/test-plan/` contains test plans and related testing documentation.

## Security

The application should follow secure development practices, including:

- Hashing passwords securely.
- Using prepared statements or parameterised database queries.
- Validating user input.
- Managing sessions securely.
- Enforcing appropriate access controls for students, lecturers and administrators.
- Keeping database credentials and other secrets out of version control.

## Future Setup Documentation

The README will be expanded as the project develops to include:

- Confirmed backend hosting and startup instructions.
- Database creation and import instructions.
- Required environment variables.
- Test execution instructions.
- Appropriate demonstration login credentials for student, lecturer and administrator roles once these have been configured.
