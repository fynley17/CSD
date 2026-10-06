# CSD

Group webapp - RevisionHub

## Stack

- Vue.js
- PHP
- SQL
- phpMyAdmin

## Project Architecture

The RevisionHub application is separated into a Vue.js frontend and a PHP backend.

### Backend

The backend contains the server-side logic, database communication and application processing.

#### `backend/controllers/`

Contains the controllers for the application.

Controllers receive requests from the frontend and determine what action needs to be performed. They communicate with the relevant models and return the appropriate response.

#### `backend/models/`

Contains the models used to handle application data and database operations.

Models are responsible for retrieving, creating, updating and deleting data where required.

#### `backend/helpers/`

Contains reusable PHP functions that can be used by different parts of the backend.

Examples include database connection functions and other common utilities.

#### `backend/index.php`

The main entry point for the PHP backend.

This file will be used to initialise and route requests into the backend application.

#### `backend/.env`

Stores environment-specific configuration such as database connection details.

Example:

DB_HOST=
DB_NAME=
DB_USER=
DB_PASSWORD=

The `.env` file must not be committed to GitHub because it may contain sensitive information.

#### `backend/.gitignore`

Contains rules for files that Git should not track.

The `.env` file is included in the `.gitignore` so that database credentials are not committed to the repository.

---

### Frontend

The frontend is built using Vue.js and contains the pages and user interface of RevisionHub.

#### `frontend/components/`

Contains reusable Vue components.

Components are smaller parts of the user interface that can be reused across multiple pages.

Examples include:

- Navigation bars
- Buttons
- Tables
- Modals
- Forms

#### `frontend/views/`

Contains the main pages of the RevisionHub application.

Examples include:

- Landing Page
- Lecturer Dashboard
- Student Dashboard
- Student Management
- Test Management

Views can use components from the `components` folder to build the page.

#### `frontend/assets/`

Contains frontend assets used by the application.

This can include:

- Images
- Logos
- Icons
- CSS
- Other static resources

#### `frontend/router/`

Handles navigation between the different pages of the Vue application.

The router maps URLs to the appropriate Vue views.

For example:

- `/` → Landing Page
- `/lecturer-dashboard` → Lecturer Dashboard
- `/students` → Student Management
- `/tests` → Test Management

#### `frontend/services/`

Contains code used to communicate with the PHP backend.

This allows the Vue frontend to send requests to the backend and receive data from it.

---

## Database

The application uses SQL for data storage and phpMyAdmin to manage the database.

The database connection will be handled by the PHP backend.

Database credentials will be stored in the `.env` file and must not be committed to GitHub.

---

## Development Setup

### Backend

To run the PHP backend locally, open a terminal and navigate to the backend folder:

```bash
cd backend
```

### Frontend

To run the Vue.js frontend locally, open a terminal and navigate to the frontend folder:

```bash
cd frontend
npm install
npm run dev
```

### Git and Branching

The project uses GitHub for version control.

Branches should follow the naming convention:

`CTS-<ticket-number>-<branch-name>`

For example:

`CTS-52-repo-architecture`

Development work should be completed on feature branches and merged into `main` through a pull request.

### Project Documentation

Additional project documentation is stored in the `Documentation` folder.

This includes project requirements and other supporting documentation.

### Testing

Unit tests are stored in the `tests` folder.

The test command will be added once the testing framework and test setup have been finalised.