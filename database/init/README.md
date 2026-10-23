# Database initialisation

Place the team's structure-only MariaDB/MySQL export here as `01-schema.sql`.
The MariaDB container runs SQL files in this directory, in filename order,
only when it initialises a new, empty data volume.

Do not include real credentials or personal/production data in a committed
dump. If the database has already been initialised, importing a newly added
dump requires importing it through phpMyAdmin or recreating the local volume.

For existing databases, apply SQL files from `database/migrations` manually
in order. The test-question migration preserves existing question-to-test
assignments in `TestQuestions` before removing `Questions.testID`. The test due
date migration adds the optional `Tests.dueDate` column used by student test
cards. The email verification migration adds `Users.isEmailVerified` and the
`EmailVerifications` table used by the registration verification email; existing
accounts stay verified. The cohorts migration adds `Cohorts.isActive` and widens
`Cohorts.cohortName` to 50 characters, for databases created before CTS-158;
existing cohorts stay active. Do not run migrations through the init directory; init scripts run only
for a new volume.
