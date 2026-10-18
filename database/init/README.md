# Database initialisation

Place the team's structure-only MariaDB/MySQL export here as `01-schema.sql`.
The MariaDB container runs SQL files in this directory, in filename order,
only when it initialises a new, empty data volume.

Do not include real credentials or personal/production data in a committed
dump. If the database has already been initialised, importing a newly added
dump requires importing it through phpMyAdmin or recreating the local volume.
