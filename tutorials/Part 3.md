# Docker Tutorial
## Part 3 — Persistent Database Storage with Docker Volumes

### 1. Introduction

In Part 2, we extended our PHP application to use a MySQL database running in a separate Docker container.

We also investigated what happens to database data when we stop, restart, remove and recreate the container.

We discovered that stopping a container preserves its writable filesystem, but removing the container destroys that filesystem. Any database files stored exclusively in that writable layer are lost.

In this part, we will solve that problem by introducing a Docker-managed named volume.

By the end of this tutorial, you will understand how to:

- Explain the difference between a container's writable layer and a Docker volume.
- Configure a named volume in Docker Compose.
- Store MySQL's database files in a volume.
- Verify that database records survive container removal and recreation.
- Inspect and manage Docker volumes.
- Understand the limitations of persistent storage and why backups are still necessary.

We will continue using the PHP application and MySQL database from Part 2.

### 2. Review the existing architecture

Our project currently has the following structure:

```text
php-docker-tutorial/
├── app/
│   ├── Dockerfile
│   └── index.php
├── database/
│   └── init.sql
└── compose.yml
```

Our Compose configuration defines two services:

- `app`, which runs PHP and Apache.
- `database`, which runs MySQL.

The application can save messages to MySQL and retrieve them when the page loads.

However, we have not yet configured persistent storage for the database.

Our current architecture looks like this:

```text
             Docker Compose
                   |
          +--------+--------+
          |                 |
          v                 v
     +---------+       +---------+
     |   app   |       | database|
     |         |       |         |
     | PHP     |------>| MySQL   |
     | Apache  |       |         |
     +---------+       +---------+
                            |
                            v
                      Database files
                      in the container
                      writable layer
```

The database files belong to the database container's writable layer.

This is sufficient to demonstrate how MySQL works, but it ties the lifetime of our data to the lifetime of that container.

We want to change that.

### 3. Understand Docker volumes

A Docker volume is a storage mechanism managed by Docker.

Unlike files stored exclusively in a container's writable layer, volume data exists independently of the container that uses it.

We can attach a volume to a container and configure an application to store its files there.

For MySQL, we want to store the database files in a volume rather than relying on the database container's writable layer.

Our architecture will become:

```text
             Docker Compose
                   |
          +--------+--------+
          |                 |
          v                 v
     +---------+       +---------+
     |   app   |       | database|
     |         |       |         |
     | PHP     |------>| MySQL   |
     | Apache  |       |         |
     +---------+       +---------+
                            |
                            v
                     +-------------+
                     | Docker      |
                     | named volume|
                     |             |
                     | Database    |
                     | files       |
                     +-------------+
```

The database container uses the volume to store its data.

If we remove the database container and create a replacement, we can attach the same volume to the new container.

MySQL can then access the existing database files.

This gives us an important separation:

- The container provides the environment in which MySQL runs.
- The volume stores the database files.
- The container can be replaced without necessarily replacing the data.

### 4. Update Docker Compose

We will configure the volume in `compose.yml`.

Open the file.

The database service currently looks similar to this:

```yaml
  database:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: php_app
      MYSQL_USER: php_user
      MYSQL_PASSWORD: development_password
      MYSQL_ROOT_PASSWORD: development_root_password
    volumes:
      - ./database/init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro
```

We need to add a second mount for the database files.

Update the service to:

```yaml
  database:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: php_app
      MYSQL_USER: php_user
      MYSQL_PASSWORD: development_password
      MYSQL_ROOT_PASSWORD: development_root_password
    volumes:
      - db_data:/var/lib/mysql
      - ./database/init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro
```

The important new line is:

```yaml
- db_data:/var/lib/mysql
```

Let's break it down.

#### `db_data`

This is the name we have chosen for our Docker volume.

We are using a named volume because we want to be able to identify and manage the storage independently of the database container.

#### `/var/lib/mysql`

This is the location inside the MySQL container where the database image stores its database files.

By mounting our volume at this location, we tell Docker to make the volume's contents available to MySQL there.

MySQL can continue using its normal data directory, but the underlying storage is now provided by the volume.

Notice that we have not changed our PHP application. It still connects to MySQL using the `database` hostname and uses the same database credentials.

The change is entirely within the database service's storage configuration.

### 5. Declare the named volume

We have referenced `db_data`, but we also need to declare it in our Compose configuration.

Add the following section at the bottom of `compose.yml`, aligned with `services`:

```yaml
volumes:
  db_data:
```

Your complete `compose.yml` should now look like this:

```yaml
services:
  app:
    build: ./app
    ports:
      - "8080:80"
    volumes:
      - ./app:/var/www/html
    environment:
      DB_HOST: database
      DB_NAME: php_app
      DB_USER: php_user
      DB_PASSWORD: development_password
    depends_on:
      - database

  database:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: php_app
      MYSQL_USER: php_user
      MYSQL_PASSWORD: development_password
      MYSQL_ROOT_PASSWORD: development_root_password
    volumes:
      - db_data:/var/lib/mysql
      - ./database/init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro

volumes:
  db_data:
```

Let's look at the declaration:

```yaml
volumes:
  db_data:
```

This tells Docker Compose that the project requires a named volume called `db_data`.

We have not specified a custom storage location or driver, so Docker will manage the volume using its default local volume driver.

Docker Compose will normally create a volume whose actual name includes the project name. For example, if the project is named `php-docker-tutorial`, the volume might be called:

```text
php-docker-tutorial_db_data
```

The exact name depends on the Compose project name.

We refer to the volume as `db_data` inside our Compose file, allowing Compose to manage the underlying name for us.

We now have two different types of mount in our configuration:

| Mount | Type | Purpose |
|---|---|---|
| `./app:/var/www/html` | Bind mount | Makes our local PHP source files available inside the application container. |
| `./database/init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro` | Read-only bind mount | Makes the SQL initialisation script available to MySQL. |
| `db_data:/var/lib/mysql` | Named volume | Stores MySQL's database files independently of the database container. |

These mounts solve different problems.

The bind mounts connect specific files or directories on our computer to locations inside containers.

The named volume provides storage managed by Docker that can outlive the container using it.

### 6. Start the application with persistent storage

Before proceeding, make sure you have saved the updated Compose configuration.

If you have been following Part 2, you should already have removed and recreated the database container during the data-loss demonstration. Your previous test messages will therefore be gone.

Start the application:

```bash
docker compose up -d
```

Docker Compose will create the named volume if it does not already exist and attach it to the database container.

Because the volume is new and empty, MySQL will initialise its data directory there.

The MySQL image will create the configured database and user and execute our initialisation script.

Check the status of the services:

```bash
docker compose ps
```

Both `app` and `database` should be running.

If MySQL is still initialising, check its logs:

```bash
docker compose logs database
```

Wait until MySQL is ready to accept connections.

Now open the application:

`http://localhost:8080`

Submit several messages through the form.

For example:

```text
First persistent message
Second persistent message
Testing Docker volumes
```

Confirm that the messages appear in the browser.

We now have a working application with its database files stored in a named volume.

### 7. Verify that the volume exists

Let's inspect the volumes Docker is managing.

Run:

```bash
docker volume ls
```

You should see the named volume created for this project.

For example:

```text
DRIVER    VOLUME NAME
local     php-docker-tutorial_db_data
```

Your actual volume name may differ.

The important thing is that Docker has created a volume independently of the container.

We can inspect its configuration using:

```bash
docker volume inspect php-docker-tutorial_db_data
```

Replace the example name with the actual volume name shown on your computer.

The output is JSON containing information about the volume, including its name, driver and mountpoint.

On a typical Linux Docker Engine installation, the mountpoint will be somewhere under Docker's data directory, often beneath `/var/lib/docker/volumes/`.

If you're using Docker Desktop, the storage may reside inside Docker Desktop's Linux environment rather than in an ordinary directory on your host.

**You generally shouldn't edit MySQL's files directly in the volume.** MySQL manages its own data files, and manually changing them while the server is running can corrupt the database.

For this tutorial, we only need to establish that the volume exists and is managed separately from the container.

### 8. Test persistence by stopping and restarting the containers

We already demonstrated in Part 2 that database data survives stopping and restarting a container.

Let's repeat the test with our new configuration.

First, make sure you have at least two messages saved in the application.

Stop the containers:

```bash
docker compose stop
```

Now start them again:

```bash
docker compose start
```

Visit:

`http://localhost:8080`

Your messages should still be present.

This works because the container has retained its filesystem, and the database files are now stored in the named volume.

The volume hasn't been removed or replaced.

Although this test is useful, it doesn't yet demonstrate the main advantage of the volume. The same container would generally retain its writable filesystem even without a volume.

We therefore need a more meaningful test.

### 9. Test persistence by removing and recreating the containers

This is the key exercise in Part 3.

In Part 2, removing the database container caused us to lose our messages.

Now we will repeat that experiment with the named volume configured.

First, confirm that you have saved several messages.

Next, stop and remove the containers:

```bash
docker compose down
```

This removes the Compose project's containers and its default network.

It does **not** normally remove the named volume declared in our Compose file.

Now list the volumes again:

```bash
docker volume ls
```

You should still see the project's `db_data` volume.

The containers have been removed, but the volume remains.

Now recreate the application:

```bash
docker compose up -d
```

Docker Compose creates new containers and attaches the existing volume to the new database container.

MySQL starts using the database files already present in that volume.

It does not need to create a new empty database.

Open the application again:

`http://localhost:8080`

**Your previously saved messages should still be present.**

This is the result we wanted.

We removed the containers, created replacements and retained our database records because the data was stored in a volume rather than exclusively in the old database container's writable layer.

We can summarise the result as follows:

| Operation | Expected result |
|---|---|
| Stop the containers | Messages remain available after restarting. |
| Remove the containers with `docker compose down` | Messages remain in the named volume. |
| Recreate the containers with `docker compose up -d` | MySQL reconnects to the existing data. |
| Remove the named volume | The persistent database data is deleted. |

The final operation is destructive, so we will not perform it yet.

### 10. Understand what happens during container replacement

Let's review the process we have just completed.

When we ran:

```bash
docker compose down
```

Docker removed the containers, but retained the named volume.

The volume is not owned by the container. It exists independently and can be attached to another container.

When we ran:

```bash
docker compose up -d
```

Docker created a new database container and mounted the existing volume at:

```text
/var/lib/mysql
```

MySQL then accessed the database files in that volume.

The important point is that the new container did not need to inherit the old container's writable filesystem. The database files were already stored elsewhere.

This is what makes volumes useful for stateful applications.

A container can be replaced while its data remains available.

There is an important caveat if you're applying this change to an existing application: **adding a volume does not guarantee that data from an existing container's writable layer will be migrated into it.**

In this tutorial, we configured the volume before creating the database container that holds our test data.

If you have an existing database containing important records and then change its configuration to use a new volume, you should not assume that the old records will automatically appear in the new volume.

Before making that change, back up the existing database and plan how to restore it into the new persistent storage.

For a MySQL database, a logical SQL dump is one common way to do this.

### 11. Understand the limitations of Docker volumes

We have solved one important problem, but it is worth being precise about what we have achieved.

A named volume allows data to survive container removal and recreation. It does not make the data indestructible.

There are several limitations to understand.

#### A volume is not a backup

A volume provides persistent storage, but it is not a substitute for a backup strategy.

If someone accidentally deletes the volume, the data stored in it may be lost.

If the database becomes corrupted, the volume may preserve the corrupted data rather than restore a healthy copy.

A backup gives us another copy of the data that we can use to recover from certain failures.

For a real application, we should have an appropriate backup and restore process.

#### Volumes can be deleted

Consider this command:

```bash
docker compose down -v
```

The `-v` option tells Compose to remove named volumes declared in the Compose file, as well as anonymous volumes associated with the project's containers, subject to Compose's normal removal rules.

In our project, this would remove the `db_data` volume.

If we then recreated the application, Docker would create a new, empty volume and MySQL would initialise a new database.

Our previous messages would be lost.

**Do not run this command while following the persistence exercise unless you deliberately want to destroy the test data.**

We can also remove a named volume directly using:

```bash
docker volume rm <VOLUME_NAME>
```

Replace `<VOLUME_NAME>` with the actual volume name.

Docker will normally refuse to remove a volume that is still in use by a container. Even so, volume deletion should be treated as a destructive operation.

#### A volume does not protect against every form of data loss

A named volume is normally stored on the Docker host, or within the environment managed by Docker Desktop.

If the underlying storage is lost, becomes corrupted or is damaged, the volume's data may also be lost.

A volume does not automatically provide:
- An independent backup.
- Protection against accidental deletion.
- Protection against every kind of filesystem or disk failure.
- Replication to another computer.
- Disaster recovery if the Docker host is lost.

The precise risks depend on the Docker environment and storage configuration.

For important data, we should consider backups stored separately from the volume and test that those backups can actually be restored.

#### A volume does not automatically move between computers

Our named volume is managed by the Docker environment in which it was created.

If we move the application to another computer, the volume does not automatically move with it.

We would need to migrate the data or restore it from a backup in the new environment.

This is particularly important when moving an application between development, testing and production environments.

### 12. Checkpoint

Before moving on, make sure you can answer the following questions.

1. What is a Docker volume?
2. Why do we mount the volume at `/var/lib/mysql`?
3. What is the difference between a named volume and a bind mount?
4. Why does `docker compose down` leave our database data intact?
5. How does a newly created database container access the existing data?
6. What would happen if we ran `docker compose down -v`?
7. Why is a named volume not a substitute for a backup?
8. What would we need to consider before adding a volume to an existing database containing important data?
9. Why won't our named volume automatically appear on another computer?
10. How would you inspect the volumes managed by Docker?

Try answering these questions without looking back at the explanations.

### 13. Summary

In this part, we introduced a Docker-managed named volume to store our MySQL database files.

We updated the Compose configuration to mount the volume at `/var/lib/mysql`, where MySQL stores its data.

We then demonstrated that the database records survived not only stopping and restarting the containers, but also removing the containers and creating replacements.

This works because the database files are stored independently of the container's writable layer.

We also explored the limitations of this approach. A volume provides persistence, but it does not protect against accidental deletion, data corruption or every form of storage failure. Important data still needs an appropriate backup and recovery strategy.

We now have a basic PHP application backed by a separate MySQL container, with database storage that survives container replacement.

This is a useful foundation for understanding how Docker supports applications whose data must persist independently of the containers running them.