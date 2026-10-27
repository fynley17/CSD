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
existing cohorts stay active. The test question count migration adds the optional
`Tests.questionCount` column, the number of questions each attempt draws at
random from the questions assigned to the test in `TestQuestions` (NULL asks
them all). Do not run migrations through the init directory; init scripts run only
for a new volume.

## Sample data

`database/seeds/sample-tests.sql` adds a sample cohort, a student (`student1`), a
lecturer (`lecturer`), a subject and two tests with question pools, so the full
student test flow can be tried locally. Both accounts use the password
`Revision1!`. Run it after the migrations, through phpMyAdmin or with:

```sh
docker compose exec -T db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < database/seeds/sample-tests.sql
```

It uses fixed IDs, so running it again changes nothing. Never run it on a live
server, because the sample accounts have a known password.
